<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the step boxes of the workspace's flows
 * (includes/workspace/steps.php): the post and e-mail boxes as the store
 * keeps and checks them, the outcomes they leave on, the {{node.*}} values
 * a box reads from the boxes before it, and, read from the source, that a
 * step is checked with the owner's rights at publish and as it runs, and is
 * never run a second time once it began.
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

// A function's source without its comments.
function ws_flow_steps_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// Started by hand, a message posted, then an e-mail; a failed post goes on
// to the end.
function ws_flow_steps_test_graph($post = array(), $email = array())
{
	return ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual'),
			array('id' => 'say', 'kind' => 'post', 'config' => $post + array('channel_id' => 4, 'body' => 'Hello')),
			array('id' => 'mail', 'kind' => 'email', 'config' => $email + array('to' => 'a@example.com', 'subject' => 'Hi', 'body' => 'Text')),
			array('id' => 'stop', 'kind' => 'end'),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'say'),
			array('from' => 'say', 'to' => 'mail'),
			array('from' => 'say', 'to' => 'stop', 'on' => 'failed'),
		),
	));
}

// The steps are kinds of box: never a start, left on done or failed, an
// arrow without an outcome leaving on done.
function test_workspace_flow_steps_are_kinds()
{
	$kinds = ws_flow_kinds();

	foreach (array('post', 'email') as $kind) {
		pg_assert_true($kinds[$kind]['step'], $kind);
		pg_assert_false($kinds[$kind]['trigger'], $kind);
		pg_assert_same(array('done', 'failed'), $kinds[$kind]['outcomes'], $kind);
	}

	pg_assert_false($kinds['task']['step']);

	$graph = ws_flow_steps_test_graph();

	pg_assert_same('done', $graph['edges'][1]['on'], 'the first outcome');
	pg_assert_same(array(), ws_flow_graph_check($graph), 'a whole graph has no problem');
}

// Settings are kept as their kind keeps them: numbers cast, texts cut,
// addresses given as a list written as one line, unknown keys dropped.
function test_workspace_flow_steps_config_normalize()
{
	pg_assert_same(array('channel_id' => 7, 'body' => 'Hi'), ws_step_config_normalize('post', array('channel_id' => '7', 'body' => " Hi \r\n", 'extra' => 1)));
	pg_assert_same(0, ws_step_config_normalize('post', array('channel_id' => -3))['channel_id']);
	pg_assert_same(0, ws_step_config_normalize('post', array('channel_id' => true))['channel_id']);
	pg_assert_same(4000, mb_strlen(ws_step_config_normalize('post', array('body' => str_repeat('ğ', 5000)))['body']));

	$email = ws_step_config_normalize('email', array('to' => array('a@example.com', 'b@example.com'), 'subject' => "One\nline", 'page_id' => '12'));

	pg_assert_same('a@example.com, b@example.com', $email['to']);
	pg_assert_same('One line', $email['subject']);
	pg_assert_same(12, $email['page_id']);
	pg_assert_same(array('to', 'subject', 'body', 'page_id'), array_keys($email));
	pg_assert_same(array(), ws_step_config_normalize('nothing', array('a' => 1)));
}

// What a box lacks is a problem of the draft: no channel, no message, no
// address, no subject or text unless a page is sent.
function test_workspace_flow_steps_problems()
{
	$codes = function ($graph) {
		return array_map(function ($problem) {
			return $problem['code'] . ':' . $problem['node'];
		}, ws_flow_graph_check($graph));
	};

	pg_assert_same(array('bad_config:say', 'bad_config:say'), $codes(ws_flow_steps_test_graph(array('channel_id' => 0, 'body' => ''))));
	pg_assert_same(array('bad_config:mail'), $codes(ws_flow_steps_test_graph(array(), array('to' => ''))));
	pg_assert_same(array('bad_config:mail'), $codes(ws_flow_steps_test_graph(array(), array('subject' => ''))));
	pg_assert_same(array(), $codes(ws_flow_steps_test_graph(array(), array('subject' => '', 'body' => '', 'page_id' => 3))), 'a page is the text');

	// An outcome a step does not have.
	$graph = ws_flow_steps_test_graph();
	$graph['edges'][2]['on'] = 'cancelled';

	pg_assert_same(array('bad_outcome:say'), $codes($graph));
}

// A step that went well leaves on done; one that failed leaves on failed
// where an arrow does, and fails the box (and so the run) where none does.
function test_workspace_flow_steps_settlement()
{
	pg_assert_same(array('status' => 'done', 'outcome' => 'done'), ws_step_settlement(true, true));
	pg_assert_same(array('status' => 'done', 'outcome' => 'done'), ws_step_settlement(true, false));
	pg_assert_same(array('status' => 'done', 'outcome' => 'failed'), ws_step_settlement(false, true));
	pg_assert_same(array('status' => 'failed', 'outcome' => 'failed'), ws_step_settlement(false, false));

	// The walk follows the failed arrow and skips the done one.
	$graph = ws_flow_steps_test_graph();
	$walk = ws_flow_walk($graph, array(
		'start' => array('status' => 'done', 'outcome' => 'next'),
		'say'   => array('status' => 'done', 'outcome' => 'failed'),
	));

	pg_assert_same(array('stop'), $walk['ready']);
	pg_assert_same(array('mail'), $walk['skipped']);
}

// {{node.<id>.<field>}}: the sentence, outcome and status of a box, and the
// single values it kept; anything else is empty, and the other double
// braces are left for the scheduled actions' own filling.
function test_workspace_flow_steps_node_values()
{
	$values = ws_step_node_values(array(
		array('node_id' => 'say', 'status' => 'done', 'outcome' => 'done', 'result' => ws_flow_result_pack('written in #sales', array('message_id' => 42, 'flag' => true, 'list' => array(1, 2)))),
		array('node_id' => 'old', 'status' => 'failed', 'outcome' => '', 'result' => 'plain text'),
	));

	pg_assert_same(array('text' => 'written in #sales', 'outcome' => 'done', 'status' => 'done', 'message_id' => '42', 'flag' => '1'), $values['say']);
	pg_assert_same('plain text', $values['old']['text']);

	pg_assert_same('Posted: written in #sales (42), {{date}}', ws_step_fill_nodes('Posted: {{node.say.text}} ({{node.say.message_id}}), {{date}}', $values));
	pg_assert_same('[][]', ws_step_fill_nodes('[{{node.say.list}}][{{node.none.text}}]', $values), 'no value, nothing');
	pg_assert_same('{{node.Say.text}}', ws_step_fill_nodes('{{node.Say.text}}', $values), 'not a box id');

	// Only the texts are filled, and a subject stays on one line.
	$values['say']['text'] = "two\nlines";
	$action = ws_step_fill_action('email', array('type' => 'email', 'to' => array('{{node.say.text}}'), 'subject' => 'Re: {{node.say.text}}', 'body' => '{{node.say.text}}'), $values);

	pg_assert_same(array('{{node.say.text}}'), $action['to']);
	pg_assert_same('Re: two lines', $action['subject']);
	pg_assert_same("two\nlines", $action['body']);
}

// A box that began its step and stopped half way may have posted or sent
// already: it fails, never runs twice. A task box's effect still resumes.
function test_workspace_flow_steps_never_twice()
{
	pg_assert_true(ws_flow_effect_began(WS_STEP_BEGAN . 'post'));
	pg_assert_false(ws_flow_effect_began('task:4'));
	pg_assert_same('fail', ws_flow_stale_action(array('effect' => 'began:post', 'attempt' => 1)));
	pg_assert_same('resume', ws_flow_stale_action(array('effect' => 'task:4', 'attempt' => 1)));

	$run = ws_flow_steps_test_source('ws_step_run');

	pg_assert_true(strpos($run, 'WS_STEP_BEGAN') < strpos($run, 'ws_scheduled_do('), 'the mark is written before the work');
	pg_assert_contains("AND status = 'running'", $run);
	pg_assert_true(strpos($run, 'ws_step_check($owner') < strpos($run, 'ws_scheduled_do('), 'checked again as it runs');
}

// The owner's rights: at publish every step box is checked as a scheduled
// action's step is, and a box runs only while its owner is on the staff.
function test_workspace_flow_steps_owner_rights()
{
	pg_assert_contains('ws_scheduled_action_input($viewer, ws_step_action($kind, $config))', ws_flow_steps_test_source('ws_step_check'));
	pg_assert_contains('ws_step_check($viewer, $node[\'kind\'], $node[\'config\'])', ws_flow_steps_test_source('ws_flow_publish_check'));

	$step = ws_flow_steps_test_source('ws_flow_step_run');

	pg_assert_true(strpos($step, 'ws_can_schedule($owner)', strpos($step, "['step']")) < strpos($step, 'ws_step_run('), 'the owner is on the staff');
}
