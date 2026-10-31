<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the values of what started a flow's run, {{trigger.<field>}}:
 * the fields each kind of start box declares (ws_flow_trigger_outputs()),
 * the reference check refusing a field the start does not give, and the
 * values filled from the run's context (ws_step_trigger_values()), escaped
 * for where the text lands like a box's values.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');

// A box of the graph.
function ws_flow_trig_test_node($id, $kind, $config = array())
{
	return array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
}

// The bad_reference problems of a start box followed by a post box saying $body.
function ws_flow_trig_test_refs($start, $body)
{
	$graph = array(
		'nodes' => array($start, ws_flow_trig_test_node('post_1', 'post', array('channel_id' => 5, 'body' => $body))),
		'edges' => array(array('from' => $start['id'], 'to' => 'post_1', 'on' => 'next')),
	);

	return array_values(array_filter(ws_flow_graph_check($graph), function ($problem) {
		return $problem['code'] === 'bad_reference';
	}));
}

function test_workspace_flow_trigger_outputs_per_start_kind()
{
	pg_assert_same(array(), ws_flow_trigger_outputs('manual', array()), 'started by hand: nothing');
	pg_assert_same(array(), ws_flow_trigger_outputs('schedule', array()), 'at a time: nothing');
	pg_assert_same(array('newcomer_id', 'newcomer_name', 'channel_id', 'channel_name'), array_column(ws_flow_trigger_outputs('join', array()), 'key'));
	pg_assert_same(array('event', 'record_id'), array_column(ws_flow_trigger_outputs('event', array('event' => '')), 'key'), 'no event chosen yet');
	pg_assert_same(array('event', 'record_id', 'title', 'status', 'previous_status', 'priority', 'channel_id', 'actor_id'),
		array_column(ws_flow_trigger_outputs('event', array('event' => 'workspace.task.status_changed')), 'key'));
	pg_assert_same(array('event', 'record_id', 'guest', 'text', 'channel_id', 'message_id'),
		array_column(ws_flow_trigger_outputs('event', array('event' => 'workspace.guest.message')), 'key'));

	foreach (ws_flow_event_outputs() as $event => $outputs) {
		foreach ($outputs as $output) {
			pg_assert_true(in_array($output['type'], ws_step_output_types(), true), $event . '.' . $output['key'] . ' has a known type');
			pg_assert_same(1, preg_match('/^[a-z_][a-z0-9_]{0,31}$/', $output['key']), $event . '.' . $output['key'] . ' is a field {{trigger.*}} can read');
		}
	}
}

function test_workspace_flow_trigger_reference_check()
{
	$event = ws_flow_trig_test_node('start', 'event', array('event' => 'workspace.task.completed'));

	pg_assert_same(array(), ws_flow_trig_test_refs($event, 'Done: {{trigger.title}} ({{trigger.record_id}}, {{trigger.event}})'), 'declared fields');

	$found = ws_flow_trig_test_refs($event, 'Who: {{trigger.newcomer_id}} {{trigger.newcomer_id}}');
	pg_assert_same(1, count($found), 'an unknown field, once');
	pg_assert_same('post_1', $found[0]['node']);
	pg_assert_contains('{{trigger.newcomer_id}}', $found[0]['text']);

	pg_assert_same(1, count(ws_flow_trig_test_refs(ws_flow_trig_test_node('start', 'manual'), '{{trigger.event}}')), 'started by hand gives nothing');
	pg_assert_same(array(), ws_flow_trig_test_refs(ws_flow_trig_test_node('start', 'join', array('channel_id' => 0)), 'Hi {{trigger.newcomer_name}} in {{trigger.channel_name}}'));
	pg_assert_same(1, count(ws_flow_trig_test_refs(ws_flow_trig_test_node('start', 'join', array('channel_id' => 0)), '{{trigger.record_id}}')));
}

function test_workspace_flow_trigger_values_per_start_kind()
{
	$event = array('trigger' => 'event', 'event' => 'workspace.task.status_changed',
		'event_payload' => array('id' => 41, 'title' => 'Ship it', 'status' => 'done', 'previous_status' => 'open', 'priority' => 'high', 'channel_id' => 3, 'actor_id' => 7, 'assignees' => array(1, 2)));

	pg_assert_same(array('event' => 'workspace.task.status_changed', 'record_id' => '41', 'title' => 'Ship it', 'status' => 'done',
		'previous_status' => 'open', 'priority' => 'high', 'channel_id' => '3', 'actor_id' => '7'), ws_step_trigger_values($event, true));

	pg_assert_same(array('event' => 'workspace.task.status_changed', 'record_id' => '', 'title' => '', 'status' => '', 'previous_status' => '',
		'priority' => '', 'channel_id' => '', 'actor_id' => ''), ws_step_trigger_values($event, false), 'an owner who may not see the record now: only the event');

	$order = array('trigger' => 'event', 'event' => 'order.status_changed', 'event_payload' => array('id' => 9, 'order_number' => 1009, 'status' => 'shipped', 'previous' => 'complete'));
	pg_assert_same(array('event' => 'order.status_changed', 'record_id' => '9', 'order_number' => '1009', 'status' => 'shipped', 'previous' => 'complete'),
		ws_step_trigger_values($order, true));

	$join = array('trigger' => 'join', 'newcomer' => 12, 'join_channel_id' => 5);
	pg_assert_same(array('newcomer_id' => '12', 'newcomer_name' => 'Ayşe', 'channel_id' => '5', 'channel_name' => 'general'),
		ws_step_trigger_values($join, true, array('newcomer' => 'Ayşe', 'channel' => 'general')));

	pg_assert_same(array(), ws_step_trigger_values(array('trigger' => 'schedule', 'sched_run' => 4), true));
	pg_assert_same(array(), ws_step_trigger_values(array('trigger' => 'manual', 'event' => 'order.created', 'event_payload' => array('id' => 3)), true),
		'a run started by hand that carries an event declares no values');
}

// A field the context lacks is empty, never left as written.
function test_workspace_flow_trigger_value_missing_is_empty()
{
	$context = array('trigger' => 'event', 'event' => 'workspace.guest.message', 'event_payload' => array('id' => 3, 'channel_id' => 4, 'list' => array(1)));
	$values = array(WS_STEP_TRIGGER => ws_step_trigger_values($context, true));

	pg_assert_same('[][4][]', ws_step_fill_nodes('[{{trigger.guest}}][{{trigger.channel_id}}][{{trigger.nothing}}]', $values));
	pg_assert_same('[]', ws_step_fill_nodes('[{{trigger.guest}}]', array()), 'no trigger values at all');
	pg_assert_same(array('{{trigger.nothing}}'), ws_step_fill_missing('{{trigger.guest}} {{trigger.nothing}}', $values), 'an empty value is a value; an undeclared one is missing');
	pg_assert_same(array('{{trigger.guest}}'), ws_step_fill_missing('{{trigger.guest}}', array()));
}

// Escaped like a box's value: a word joiner after "<" in a message and a
// chat text, none in an e-mail (escaped where its HTML is written) or a
// generic webhook body (json_encode()); every "{" in all of them.
function test_workspace_flow_trigger_value_escaping()
{
	$j = "\u{2060}";
	$context = array('trigger' => 'event', 'event' => 'workspace.guest.message',
		'event_payload' => array('id' => 3, 'guest' => 'Bob <@user:1>', 'text' => '<b>hi</b> & {{customer}}', 'channel_id' => 4));
	$values = array(WS_STEP_TRIGGER => ws_step_trigger_values($context, true));

	$post = ws_step_fill_action('post', array('type' => 'post', 'channel_id' => 4, 'body' => '{{trigger.guest}}: {{trigger.text}}'), $values);
	pg_assert_same("Bob <{$j}@user:1>: <{$j}b>hi<{$j}/b> & {{$j}{{$j}customer}}", $post['body']);

	$mail = ws_step_fill_action('email', array('type' => 'email', 'to' => array('{{trigger.guest}}'), 'subject' => "From\n{{trigger.guest}}", 'body' => '{{trigger.text}}'), $values);
	pg_assert_same(array('{{trigger.guest}}'), $mail['to'], 'an address is never filled');
	pg_assert_same('From Bob <@user:1>', $mail['subject'], 'the subject stays on one line');
	pg_assert_same("<b>hi</b> & {{$j}{{$j}customer}}", $mail['body'], 'the e-mail HTML is escaped where it is written, not here');

	$hook = ws_step_fill_action('webhook', array('type' => 'webhook', 'format' => 'generic', 'url' => 'https://example.com/{{trigger.record_id}}', 'text' => '{{trigger.text}}'), $values);
	pg_assert_same('https://example.com/4', $hook['url'], 'a number goes into the path of a webhook address');
	$json = json_encode(array('text' => $hook['text']));
	pg_assert_same(array('text' => "<b>hi</b> & {{$j}{{$j}customer}}"), json_decode($json, true), 'one JSON string, read back as filled');

	$chat = ws_step_fill_action('webhook', array('type' => 'webhook', 'format' => 'text', 'text' => '{{trigger.guest}}'), $values);
	pg_assert_same("Bob <{$j}@user:1>", $chat['text']);
}

// The run's own values reach every step box, and the box try fills them
// from the run chosen.
function test_workspace_flow_trigger_values_wired()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');

	pg_assert_true(substr_count($source, '[WS_STEP_TRIGGER] = ws_step_trigger_values_for(') >= 2, 'ws_step_run() and ws_flow_box_try()');
}
