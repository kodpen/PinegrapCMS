<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the texts a flow's waiting task box opens its task with
 * (ws_flow_task_texts()): the values of the boxes before it and of what
 * started the run ({{node.<id>.<field>}}, {{trigger.<field>}}), escaped like
 * a task opened without waiting, then the scheduled tokens ({{date}},
 * {{record}} …) of the run; a box try shows the same texts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A function's source without its comments.
function ws_flow_task_fill_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The box values and trigger values filled in, each escaped as a message's
// value is; the title stays one line.
function test_workspace_flow_task_fill_values()
{
	$values = array(
		'say'           => array('text' => "Ask <@user:5>\nabout {{record}}", 'outcome' => 'done', 'status' => 'done'),
		WS_STEP_TRIGGER => array('title' => 'Broken printer', 'record_id' => '12'),
	);
	$texts = ws_flow_task_texts(array('title' => 'Fix {{trigger.title}}: {{node.say.text}}', 'description' => "Task {{trigger.record_id}}\n{{node.say.text}} {{node.later.text}}"), $values);

	pg_assert_same("Fix Broken printer: Ask <\u{2060}@user:5> about {\u{2060}{\u{2060}record}}", $texts['title']);
	pg_assert_same("Task 12\nAsk <\u{2060}@user:5>\nabout {\u{2060}{\u{2060}record}} ", $texts['description']);
	pg_assert_same(array('{{node.later.text}}'), $texts['empty'], 'a value the run does not give is named');
	pg_assert_same(array(array('key' => 'title', 'text' => $texts['title']), array('key' => 'description', 'text' => $texts['description'])), $texts['texts']);
}

// The scheduled tokens of the run, after the box values: a value of a box
// before it is never read again as a token; one left empty is named.
function test_workspace_flow_task_fill_scheduled()
{
	$context = array('viewer' => array(), 'row' => array('name' => 'Flow', 'run_count' => 2), 'source' => 'Nightly', 'source_result' => 'done');
	$fill = function ($text) use ($context) {
		return ws_scheduled_fill($text, $context);
	};
	$values = array('say' => array('text' => '{{source}}', 'outcome' => 'done', 'status' => 'done'));
	$texts = ws_flow_task_texts(array('title' => '{{name}} run {{run}}', 'description' => '{{node.say.text}} {{source}} {{record}}'), $values, $fill);

	pg_assert_same('Flow run 3', $texts['title']);
	pg_assert_same("{\u{2060}{\u{2060}source}} Nightly ", $texts['description']);
	pg_assert_same(array('{{record}}'), $texts['empty']);

	// Without a run the scheduled tokens stay as written.
	pg_assert_same('{{name}} run {{run}}', ws_flow_task_texts(array('title' => '{{name}} run {{run}}', 'description' => ''), array())['title']);

	// A title a filled value made longer than a task box's title is cut.
	$long = ws_flow_task_texts(array('title' => '{{trigger.text}}', 'description' => ''), array(WS_STEP_TRIGGER => array('text' => str_repeat('a', 300))));

	pg_assert_same(200, mb_strlen($long['title']));
}

// The run fills the box's texts with the owner's rights, as a step box's run
// does; a retry finds its task by the box's line, not by the filled title,
// which a {{time}} may have changed; a box try shows the same texts.
function test_workspace_flow_task_fill_wired()
{
	$task = ws_flow_task_fill_test_source('ws_flow_step_task');

	pg_assert_contains('ws_step_run_values($run, $graph, $owner, $context, $step)', $task);
	pg_assert_contains('ws_flow_task_texts($config, $values', $task);
	pg_assert_contains("'viewer' => \$owner", $task);
	pg_assert_contains('ws_step_trigger_context($context)', $task);
	pg_assert_contains("'description'   => ws_flow_task_description(\$texts['description'], \$marker)", $task);
	pg_assert_false(strpos($task, "(string) \$config['title']") !== false, 'the raw title is not used');

	$values = ws_flow_task_fill_test_source('ws_step_run_values');

	pg_assert_contains('ws_step_trigger_values_for($owner, $context)', $values, 'the owner\'s rights decide the trigger values');
	pg_assert_contains('ws_flow_pure_values($graph, $values)', $values);
	pg_assert_contains('ws_step_run_values($run, $graph, $owner, $context, $step)', ws_flow_task_fill_test_source('ws_step_run'));

	$find = ws_flow_task_fill_test_source('ws_flow_step_task_find');

	pg_assert_false(strpos($find, 'title') !== false, 'a retry does not match the filled title');

	$try = ws_flow_task_fill_test_source('ws_flow_box_try');

	pg_assert_contains("ws_flow_task_texts(\$node['config'], \$values", $try);
}
