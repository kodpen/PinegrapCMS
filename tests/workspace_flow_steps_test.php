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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');

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

	foreach (array('post', 'email', 'notify', 'task_open', 'webhook', 'web_check', 'task_digest', 'report', 'checkin', 'start_action') as $kind) {
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

// Each step box is the scheduled action's step of its type; the task opened
// without waiting is a task step, never the task box that waits.
function test_workspace_flow_steps_types()
{
	pg_assert_same(array('type' => 'task', 'title' => 'T'), ws_step_action('task_open', array('title' => 'T')));
	pg_assert_same('webhook', ws_step_action('webhook', array())['type']);

	$steps = array_keys(ws_scheduled_outcomes()) ? ws_step_kinds() : array();

	foreach ($steps as $kind => $info) {
		pg_assert_true(in_array($info['type'], array('post', 'email', 'notify', 'task', 'webhook', 'web_check', 'task_digest', 'report', 'checkin', 'trigger', 'change', 'lead', 'approval'), true), $kind);
		pg_assert_same(array(), array_diff($info['lines'], $info['texts']), $kind . ': one-line texts are texts');
	}

	pg_assert_false(ws_flow_kinds()['task']['step'], 'the task box waits');
}

// The settings of the four steps of this slice, kept in their shape.
function test_workspace_flow_steps_more_config()
{
	pg_assert_same(array('user_ids' => array(3, 5), 'text' => 'Look'), ws_step_config_normalize('notify', array('user_ids' => array('3', 5, 3, -1, 'x'), 'text' => " Look\n")));
	pg_assert_same(50, count(ws_step_config_normalize('notify', array('user_ids' => range(1, 80)))['user_ids']));

	$task = ws_step_config_normalize('task_open', array('title' => 'Call', 'due_in' => '4000', 'priority' => 'odd', 'assignees' => range(1, 30)));

	pg_assert_same(3650, $task['due_in']);
	pg_assert_same('normal', $task['priority']);
	pg_assert_same(20, count($task['assignees']));
	pg_assert_same('', ws_step_config_normalize('task_open', array('due_in' => ''))['due_in']);

	pg_assert_same('generic', ws_step_config_normalize('webhook', array('format' => 'xml'))['format']);
	pg_assert_same('discord', ws_step_config_normalize('webhook', array('format' => 'discord'))['format']);

	$check = ws_step_config_normalize('web_check', array('mode' => 'ssl', 'days' => 0, 'max_ms' => 999999, 'redirect' => 1));

	pg_assert_same('ssl', $check['mode']);
	pg_assert_same(14, $check['days']);
	pg_assert_same(60000, $check['max_ms']);
	pg_assert_true($check['redirect']);
	pg_assert_same('status', ws_step_config_normalize('web_check', array())['mode']);
}

// What these boxes lack: people and text, a title, an https address, and
// a chat message's text.
function test_workspace_flow_steps_more_problems()
{
	pg_assert_same(2, count(ws_step_config_problems('notify', ws_step_config_normalize('notify', array()), 'N')));
	pg_assert_same(1, count(ws_step_config_problems('task_open', ws_step_config_normalize('task_open', array()), 'T')));
	pg_assert_same(0, count(ws_step_config_problems('task_open', ws_step_config_normalize('task_open', array('title' => 'x')), 'T')));
	pg_assert_same(1, count(ws_step_config_problems('webhook', ws_step_config_normalize('webhook', array('url' => 'http://example.com')), 'W')));
	pg_assert_same(0, count(ws_step_config_problems('webhook', ws_step_config_normalize('webhook', array('url' => 'https://example.com/h')), 'W')), 'the details of the run need no text');
	pg_assert_same(1, count(ws_step_config_problems('webhook', ws_step_config_normalize('webhook', array('url' => 'https://example.com/h', 'format' => 'text')), 'W')));
	pg_assert_same(0, count(ws_step_config_problems('web_check', ws_step_config_normalize('web_check', array('url' => 'https://example.com')), 'C')));

	// A notice's text is filled on one line.
	$action = ws_step_fill_action('notify', array('type' => 'notify', 'text' => '{{node.a.text}}'), array('a' => array('text' => "one\ntwo")));

	pg_assert_same('one two', $action['text']);
}

// A webhook box's key is one per run, box and pass; the call sends it, and
// the details of the run name the flow. A task opened keeps its id, and a
// notice of a flow is a line of its own titled as a flow's.
function test_workspace_flow_steps_effects()
{
	pg_assert_same('pg-flow-12-hook-0', ws_step_idempotency_key(12, 'hook', 0));
	pg_assert_same(ws_step_idempotency_key(12, 'hook', 0), ws_step_idempotency_key('12', 'hook', '0'));

	$hook = ws_flow_steps_test_source('ws_scheduled_webhook');

	pg_assert_contains("'Idempotency-Key: '", $hook);
	pg_assert_contains("\$payload['flow'] = \$context['flow']", $hook);
	pg_assert_contains("'headers' => \$headers", $hook);

	pg_assert_contains("'task_id' => (int) \$made['task_id']", ws_flow_steps_test_source('ws_scheduled_task'));
	pg_assert_contains("'separate'   => ((int) \$row['id'] <= 0)", ws_flow_steps_test_source('ws_scheduled_notify'));
	pg_assert_contains("lang('Flow')", ws_flow_steps_test_source('ws_inbox_describe'));

	$run = ws_flow_steps_test_source('ws_step_run');

	pg_assert_contains("'idempotency_key' => ws_step_idempotency_key(", $run);
	pg_assert_contains("ws_step_result_data(\$kind, \$node['config'], \$done)", $run);
}

// A hook's address carries its secret: only whoever may change the flow
// reads it whole, on the screen, in the API and in a run's graph; everybody
// else reads its host. A web check's sentence names the host only.
function test_workspace_flow_steps_hook_address_is_kept_back()
{
	pg_assert_same('https://hooks.slack.com/…', ws_flow_url_masked('https://hooks.slack.com/services/T0/B0/secret'));
	pg_assert_same('https://example.com/…', ws_flow_url_masked('https://user:pass@Example.com:8443/x?token=1'));
	pg_assert_same('', ws_flow_url_masked(''));

	$graph = ws_flow_graph_normalize(array('nodes' => array(
		array('id' => 'start', 'kind' => 'manual'),
		array('id' => 'hook', 'kind' => 'webhook', 'config' => array('url' => 'https://hooks.slack.com/services/T0/B0/secret', 'format' => 'text', 'text' => 'Hi')),
		array('id' => 'look', 'kind' => 'web_check', 'config' => array('url' => 'https://user:pass@example.com/health')),
		array('id' => 'say', 'kind' => 'post', 'config' => array('channel_id' => 4, 'body' => 'https://keep.example.com/x')),
	)));
	$url = function ($present, $index) {
		return ((array) $present['nodes'][$index]['config'])['url'] ?? null;
	};

	$reader = ws_flow_graph_present($graph);
	$editor = ws_flow_graph_present($graph, true);

	pg_assert_same('https://hooks.slack.com/…', $url($reader, 1));
	pg_assert_same('https://example.com/…', $url($reader, 2));
	pg_assert_same('https://hooks.slack.com/services/T0/B0/secret', $url($editor, 1));
	pg_assert_same('https://keep.example.com/x', ((array) $reader['nodes'][3]['config'])['body'], 'other boxes as they are');
	pg_assert_true(is_object($reader['nodes'][1]['config']), 'settings stay an object');

	// Every presenter passes whether the reader may change the flow.
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

	pg_assert_contains('ws_flow_graph_present($live, $edit)', ws_flow_steps_test_source('ws_flow_detail'));
	pg_assert_contains('ws_flow_graph_present($draft, $edit)', ws_flow_steps_test_source('ws_flow_detail'));
	pg_assert_contains('ws_api_flow_graph_present($draft, $edit)', ws_flow_steps_test_source('ws_api_flow_present'));
	pg_assert_contains('ws_api_flow_graph_present(ws_flow_graph_of($version), $edit)', ws_flow_steps_test_source('ws_api_flow_present'));
	pg_assert_contains("ws_api_flow_graph_present(\$graph, \$edit)", ws_flow_steps_test_source('ws_api_flow_run_present'));
	pg_assert_contains("\$edit = ((string) \$run['access'] === 'edit');", ws_flow_steps_test_source('ws_api_flow_run_present'));

	pg_assert_contains("\$url = ws_scheduled_host((string) \$action['url']);", ws_flow_steps_test_source('ws_scheduled_web_check'));
}

// The task e-mail, the summary of counts, the check-in and the start of a
// scheduled action, kept in their shape; a box that starts an action is the
// trigger step.
function test_workspace_flow_steps_digest_report_checkin_start()
{
	$digest = ws_step_config_normalize('task_digest', array('mode' => 'channel', 'channel_id' => '9', 'only' => 'soon', 'ai' => true, 'subject' => "A\nB"));

	pg_assert_same('channel', $digest['mode']);
	pg_assert_same(9, $digest['channel_id']);
	pg_assert_same('open', $digest['only']);
	pg_assert_false($digest['ai'], 'no recap without what they missed');
	pg_assert_same('A B', $digest['subject']);
	pg_assert_same('mine', ws_step_config_normalize('task_digest', array())['mode']);

	$report = ws_step_config_normalize('report', array('metrics' => array('tasks_open', array('key' => 'Stock_Low!', 'param' => '5'), array('key' => '')), 'to' => array('a@example.com')));

	pg_assert_same(array(array('key' => 'tasks_open', 'param' => 0), array('key' => 'stock_low', 'param' => 5)), $report['metrics']);
	pg_assert_same('a@example.com', $report['to']);
	pg_assert_same(20, count(ws_step_config_normalize('report', array('metrics' => array_fill(0, 30, 'tasks_open')))['metrics']));

	pg_assert_same(array('target_id' => 3, 'delay' => 10080), ws_step_config_normalize('start_action', array('target_id' => '3', 'delay' => 99999)));
	pg_assert_same(array('type' => 'trigger', 'target_id' => 3, 'delay' => 0), ws_step_action('start_action', array('target_id' => 3, 'delay' => 0)));

	$problems = function ($kind, $config) {
		return count(ws_step_config_problems($kind, ws_step_config_normalize($kind, $config), 'X'));
	};

	pg_assert_same(1, $problems('task_digest', array('mode' => 'channel')));
	pg_assert_same(0, $problems('task_digest', array()));
	pg_assert_same(2, $problems('report', array()));
	pg_assert_same(0, $problems('report', array('metrics' => array('tasks_open'), 'to' => 'a@example.com')));
	pg_assert_same(1, $problems('checkin', array()));
	pg_assert_same(1, $problems('start_action', array()));
	pg_assert_same(0, $problems('start_action', array('target_id' => 2)));

	// The action a box starts names no source action.
	pg_assert_contains("'run_id'          => 0", ws_flow_steps_test_source('ws_step_run'));
}

// The change box: kept in its shape, a delete changing no fields, what it
// lacks, and the change step it is.
function test_workspace_flow_steps_change_config()
{
	pg_assert_same('change', ws_step_kinds()['change']['type'] ?? null);

	$config = ws_step_config_normalize('change', array(
		'record' => ' Product ',
		'action' => 'update',
		'id'     => '12',
		'fields' => array('price' => '1500', 'enabled' => true, 'Bad Name!' => 'x', 'answers' => array('a' => 'one', 'b' => array('no'))),
	));

	pg_assert_same('product', $config['record']);
	pg_assert_same('update', $config['action']);
	pg_assert_same(12, $config['id']);
	pg_assert_same(array('price' => '1500', 'enabled' => true, 'answers' => array('a' => 'one')), $config['fields']);
	pg_assert_same(array(), ws_step_config_normalize('change', array('action' => 'delete', 'fields' => array('price' => 1)))['fields'], 'a delete changes no fields');
	pg_assert_same('update', ws_step_config_normalize('change', array('action' => 'drop'))['action']);
	pg_assert_same(30, count(ws_step_config_normalize('change', array('fields' => array_fill_keys(array_map(function ($n) {
		return 'f' . $n;
	}, range(1, 40)), 'x')))['fields']));

	$problems = function ($config) {
		return count(ws_step_config_problems('change', ws_step_config_normalize('change', $config), 'X'));
	};

	pg_assert_same(3, $problems(array()));
	pg_assert_same(1, $problems(array('record' => 'product', 'id' => 3)), 'an update needs a field');
	pg_assert_same(0, $problems(array('record' => 'product', 'id' => 3, 'action' => 'delete')));
	pg_assert_same(0, $problems(array('record' => 'product', 'id' => 3, 'fields' => array('price' => 100))));

	pg_assert_same(array('type' => 'change', 'record' => 'page', 'action' => 'delete', 'id' => 4, 'fields' => array()),
		ws_step_action('change', ws_step_config_normalize('change', array('record' => 'page', 'action' => 'delete', 'id' => 4))));

	// ws_scheduled_change() reads the channel of the action that runs it: a
	// box has none, so no decision is posted under a scheduled action's name.
	pg_assert_contains("'channel_id'  => 0", ws_flow_steps_test_source('ws_step_run'));
}

// Which record a change box changes, and to what, is read only by whoever
// may change the flow: everybody else reads the kind of record, the action
// and the names of the fields, on the screen, in the API and in a run; a
// change box's sentence in a run names neither the record nor its values.
function test_workspace_flow_steps_change_is_kept_back()
{
	$graph = ws_flow_graph_normalize(array('nodes' => array(
		array('id' => 'start', 'kind' => 'manual'),
		array('id' => 'set', 'kind' => 'change', 'config' => array('record' => 'contact', 'id' => 7, 'fields' => array('email' => 'jane@example.com', 'notes' => 'VIP'))),
	)));

	$reader = (array) ws_flow_graph_present($graph)['nodes'][1]['config'];
	$editor = (array) ws_flow_graph_present($graph, true)['nodes'][1]['config'];

	pg_assert_same('contact', $reader['record']);
	pg_assert_same('update', $reader['action']);
	pg_assert_same(0, $reader['id']);
	pg_assert_same(array('email' => '', 'notes' => ''), $reader['fields']);
	pg_assert_same(7, $editor['id']);
	pg_assert_same('jane@example.com', $editor['fields']['email']);

	$said = '“Jane” changed (E-mail: “a@example.com” → “jane@example.com”)';

	pg_assert_same($said, ws_step_result_shown('change', 'done', $said, true));
	pg_assert_same('post sentence', ws_step_result_shown('post', 'done', 'post sentence', false), 'other boxes as they are');
	pg_assert_true(strpos(ws_step_result_shown('change', 'done', $said, false), 'jane') === false);
	pg_assert_true(strpos(ws_step_result_shown('change', 'failed', $said, false), 'jane') === false);
	pg_assert_same('', ws_step_result_shown('change', '', '', false), 'a box not run yet says nothing');

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

	pg_assert_contains('ws_step_result_shown(', ws_flow_steps_test_source('ws_api_flow_run_step_present'));
	pg_assert_contains("ws_api_flow_run_step_present(\$step, \$graph, \$edit)", ws_flow_steps_test_source('ws_api_flow_run_present'));
}

// A box tried on its own: its texts filled with the values of a run chosen,
// and the values that run does not give named, left empty.
function test_workspace_flow_steps_try_texts()
{
	$values = array('say' => array('text' => 'written in #sales', 'outcome' => 'done', 'status' => 'done', 'message_id' => '42'));

	pg_assert_same(array('{{node.mail.text}}', '{{node.say.task_id}}'), ws_step_fill_missing('{{node.say.text}} {{node.mail.text}} {{node.say.task_id}} {{node.mail.text}} {{date}}', $values));
	pg_assert_same(array(), ws_step_fill_missing('{{node.say.message_id}}', $values));
	pg_assert_same(array(), ws_step_fill_missing('{{node.Say.text}}', $values), 'not a box id, never filled');

	$tried = ws_step_try_texts('email', array('type' => 'email', 'to' => array('{{node.say.text}}'), 'subject' => "Re:\n{{node.say.text}}", 'body' => '{{node.say.message_id}} {{node.later.text}}'), $values);

	pg_assert_same(array(
		array('key' => 'subject', 'text' => 'Re: written in #sales'),
		array('key' => 'body', 'text' => '42 '),
	), $tried['texts']);
	pg_assert_same(array('{{node.later.text}}'), $tried['empty']);
	pg_assert_same(array('{{node.say.text}}'), $tried['action']['to'], 'addresses are never filled');

	// Without a run every value is left empty.
	$bare = ws_step_try_texts('post', array('type' => 'post', 'channel_id' => 4, 'body' => 'Hi {{node.say.text}}, {{date}}'), array());

	pg_assert_same(array(array('key' => 'body', 'text' => 'Hi , {{date}}')), $bare['texts']);
	pg_assert_same(array('{{node.say.text}}'), $bare['empty']);
	pg_assert_same(array(), ws_step_try_texts('web_check', array('type' => 'web_check', 'url' => 'https://example.com'), $values)['texts']);
}

// The next times a start box at a time starts the flow, as the scheduled
// action's form lists them.
function test_workspace_flow_steps_try_anchor_times()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');

	$after = strtotime('2030-01-01 08:00:00');
	$daily = array('type' => 'at', 'date' => '2030-01-01', 'time' => '09:00', 'repeat' => 'daily', 'every' => 1);

	pg_assert_same(array(strtotime('2030-01-01 09:00:00'), strtotime('2030-01-02 09:00:00'), strtotime('2030-01-03 09:00:00')), ws_flow_anchor_next_times($daily, $after, 3));
	pg_assert_same(array(strtotime('2030-01-01 09:00:00')), ws_flow_anchor_next_times(array('repeat' => 'none') + $daily, $after, 3), 'once');
	pg_assert_same(array(), ws_flow_anchor_next_times(array('type' => 'event', 'event' => 'workspace.task.created'), $after, 3), 'no time of its own');
}

// Trying a box never sends, writes or calls: it checks the box with the
// owner's rights, as publishing and the run do, for whoever may change the
// flow, and is told nothing the run would not do.
function test_workspace_flow_steps_try_writes_nothing()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');

	$try = ws_flow_steps_test_source('ws_flow_box_try');

	foreach (array('ws_scheduled_do(', 'ws_flow_step_settle(', 'db(', 'ws_message_send(', 'ws_scheduled_execute(', 'log_activity(') as $call) {
		pg_assert_true(strpos($try, $call) === false, $call);
	}

	foreach (array('ws_step_check($owner', 'ws_flow_condition_check($owner', 'ws_flow_anchor_check($owner', 'ws_scheduled_rules_hold($owner', 'ws_step_try_texts(', 'ws_step_node_values(', "'edit'", 'ws_can_schedule($owner)') as $call) {
		pg_assert_contains($call, $try);
	}

	pg_assert_contains('ws_flow_graph_of($flow)', $try, 'the draft');
	pg_assert_contains('ws_flow_run_for($viewer', $try, 'a run the person reaches');

	// The canvas and the API ask the same function; the route is a read and
	// no MCP tool.
	pg_assert_contains("case 'ws_flow_box_try':", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php'));
	pg_assert_contains('ws_flow_box_try($viewer, $flow', ws_flow_steps_test_source('ws_api_flows_box_try'));
	pg_assert_contains("'ws_flow_box_try',", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php'), 'the panel lets it through');

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
	$route = substr($api, strpos($api, "'id'          => 'workspace.flows.box_try'"), 400);

	pg_assert_contains("'method'      => 'GET'", $route);
	pg_assert_contains("'mcp'         => false", $route);
}

// Only the owner tries a box: it is looked at with their rights, which a
// colleague the flow is shared with at edit does not hold.
function test_workspace_flow_steps_try_is_the_owners()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_shares.php');

	pg_assert_true(ws_flow_share_manages(true, 'edit'));
	pg_assert_false(ws_flow_share_manages(false, 'edit'), 'an edit share');

	$try = ws_flow_steps_test_source('ws_flow_box_try');

	pg_assert_contains('!ws_flow_share_manager($viewer, $flow)', $try);
	pg_assert_true(strpos($try, 'ws_flow_share_manager(') < strpos($try, 'ws_change_viewer_for_id('), 'refused before the owner is read');
	pg_assert_true(strpos($try, 'ws_flow_share_manager(') < strpos($try, 'ws_flow_run_for('), 'refused before a run is read');

	// The canvas offers it to the owner alone.
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_true((bool) preg_match('/if \(state\.flow && state\.flow\.manage\) \{\s*tryField\(box, node\);/', $js));
}

// The application an assistant works through is refused, and a run the
// application does not reach gives no values.
function test_workspace_flow_steps_try_refuses_assistants()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

	$api = ws_flow_steps_test_source('ws_api_flows_box_try');

	pg_assert_true(strpos($api, 'ws_api_is_assistant()') < strpos($api, 'ws_api_viewer()'), 'refused first');
	pg_assert_contains("api_fail(403, 'forbidden'", $api);
	pg_assert_contains('ws_api_flow_run_reachable($viewer, $flow, $run)', $api);
	pg_assert_true(strpos($api, 'ws_api_flow_run_reachable(') < strpos($api, 'ws_flow_box_try('), 'the run is checked before it is read');
}

// A flow that starts when somebody joins a channel, then greets them there.
function ws_flow_steps_test_join_graph($start = 'join', $channel_id = -1)
{
	return ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => $start, 'config' => ($start === 'join') ? array('channel_id' => 0) : array()),
			array('id' => 'say', 'kind' => 'post', 'config' => array('channel_id' => $channel_id, 'body' => 'Welcome {{newcomer}}')),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'say'),
		),
	));
}

// A post box writes in the channel joined (-1) only in a flow a join starts;
// any other number below one is no channel.
function test_workspace_flow_steps_post_in_the_channel_joined()
{
	pg_assert_same(-1, ws_step_config_normalize('post', array('channel_id' => -1))['channel_id']);
	pg_assert_same(-1, ws_step_config_normalize('post', array('channel_id' => '-1'))['channel_id']);
	pg_assert_same(0, ws_step_config_normalize('post', array('channel_id' => -2))['channel_id']);
	pg_assert_same(0, ws_step_config_normalize('email', array('page_id' => -1))['page_id'], 'only a post box has the channel joined');

	pg_assert_true(ws_flow_starts_on_join(ws_flow_steps_test_join_graph()));
	pg_assert_false(ws_flow_starts_on_join(ws_flow_steps_test_join_graph('manual')));

	$codes = function ($graph) {
		return array_map(function ($problem) {
			return $problem['code'] . ':' . $problem['node'];
		}, ws_flow_graph_check($graph));
	};

	pg_assert_same(array(), $codes(ws_flow_steps_test_join_graph()));
	pg_assert_same(array('bad_config:say'), $codes(ws_flow_steps_test_join_graph('manual')), 'no channel was joined');

	$config = ws_step_config_normalize('post', array('channel_id' => -1, 'body' => 'Hi'));

	pg_assert_same(0, count(ws_step_config_problems('post', $config, 'P', true)));
	pg_assert_same(1, count(ws_step_config_problems('post', $config, 'P')));

	// Checked with the scheduled action's own rule for the channel joined, at
	// publish, as the box runs and as it is tried.
	pg_assert_contains('ws_scheduled_action_input($viewer, ws_step_action($kind, $config), 0, 0, $joined)', ws_flow_steps_test_source('ws_step_check'));
	pg_assert_contains('ws_flow_starts_on_join($graph)', ws_flow_steps_test_source('ws_flow_publish_check'));
	pg_assert_contains('ws_flow_starts_on_join($graph)', ws_flow_steps_test_source('ws_step_run'));
	pg_assert_contains('ws_flow_starts_on_join(', ws_flow_steps_test_source('ws_flow_box_try'));
}

// The values of what started a run (the event and its record, the newcomer
// and the channel joined) reach every box's texts, as they reach a scheduled
// action's step; the flow's own keys stay out.
function test_workspace_flow_steps_trigger_values()
{
	pg_assert_same(array(), ws_step_trigger_context(array('trigger' => 'manual', 'node' => 'start', 'channel_id' => 3, 'flow_name' => 'F')));

	pg_assert_same(
		array('event' => 'form.submitted', 'event_payload' => array('id' => 9)),
		ws_step_trigger_context(array('trigger' => 'event', 'event' => 'form.submitted', 'event_payload' => array('id' => 9), 'sched_run' => 4))
	);
	pg_assert_same(array('event' => 'order.created'), ws_step_trigger_context(array('event' => 'order.created')), 'a payload over 8 KB was left out');
	pg_assert_same(
		array('newcomer' => 12, 'join_channel_id' => 5),
		ws_step_trigger_context(array('trigger' => 'join', 'newcomer' => '12', 'join_channel_id' => '5'))
	);
	pg_assert_same(array(), ws_step_trigger_context(array('newcomer' => 0, 'join_channel_id' => 5)));

	$run = ws_flow_steps_test_source('ws_step_run');

	pg_assert_contains('$step_context += ws_step_trigger_context($context)', $run);
	pg_assert_false(strpos($run, "if (\$kind === 'lead')") !== false, 'every box, not the lead box alone');
}
