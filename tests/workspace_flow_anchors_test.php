<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the boxes that start a flow on their own
 * (includes/workspace/flow_anchors.php): their settings as the store keeps
 * them, the rule of a scheduled action each is, what they lack, the context
 * a run they start keeps, and, read from the source, that the anchor is
 * written as the flow is published, follows its pauses and deletion, starts
 * runs with one key per run of the anchor, and stays out of the lists of
 * scheduled actions and their reports.
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

// A function's source without its comments.
function ws_flow_anchors_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// Three kinds of start box, each one rule of a scheduled action, leaving on
// next; one of them and no other start makes a flow.
function test_workspace_flow_anchor_kinds()
{
	$rules = array();

	foreach (ws_flow_anchor_kinds() as $kind => $info) {
		$rules[$kind] = $info['rule'];
		pg_assert_true(ws_flow_kinds()[$kind]['trigger'], $kind . ' starts a flow');
		pg_assert_same(array('next'), ws_flow_kinds()[$kind]['outcomes']);
	}

	pg_assert_same(array('schedule' => 'at', 'event' => 'event', 'join' => 'join'), $rules);

	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'when', 'kind' => 'schedule', 'config' => array('date' => '2030-01-01', 'time' => '09:00')),
			array('id' => 'stop', 'kind' => 'end'),
		),
		'edges' => array(array('from' => 'when', 'to' => 'stop')),
	));

	pg_assert_same('next', $graph['edges'][0]['on']);
	pg_assert_same(array(), ws_flow_graph_check($graph));
}

// Settings kept in their shape, and turned into the rule.
function test_workspace_flow_anchor_settings()
{
	$schedule = ws_flow_anchor_normalize('schedule', array('date' => '2030-01-01', 'time' => '09:00', 'repeat' => 'weekly', 'every' => '0', 'days' => array('5', 1, 9, 1), 'x' => 1));

	pg_assert_same(array('date' => '2030-01-01', 'time' => '09:00', 'repeat' => 'weekly', 'every' => 1, 'days' => array(1, 5), 'until' => ''), $schedule);
	pg_assert_same(array('type' => 'at', 'date' => '2030-01-01', 'time' => '09:00', 'repeat' => 'weekly', 'every' => 1, 'days' => array(1, 5), 'until' => ''), ws_flow_anchor_rule('schedule', $schedule));
	pg_assert_false(isset(ws_flow_anchor_rule('schedule', ws_flow_anchor_normalize('schedule', array()))['days']), 'no days, none sent');

	pg_assert_same(array('event' => 'order.created', 'page_id' => 0, 'status' => '', 'channel_id' => 3), ws_flow_anchor_normalize('event', array('event' => 'order.created', 'channel_id' => '3')));
	pg_assert_same(array('type' => 'join', 'channel_id' => 0), ws_flow_anchor_rule('join', ws_flow_anchor_normalize('join', array('channel_id' => -4))));
	pg_assert_same(ws_flow_anchor_normalize('join', array('channel_id' => 2)), ws_flow_config_normalize('join', array('channel_id' => 2)));

	pg_assert_same(1, count(ws_flow_anchor_problems('schedule', ws_flow_anchor_normalize('schedule', array('time' => '09:00')), 'S')));
	pg_assert_same(1, count(ws_flow_anchor_problems('event', ws_flow_anchor_normalize('event', array()), 'E')));
	pg_assert_same(0, count(ws_flow_anchor_problems('join', ws_flow_anchor_normalize('join', array()), 'J')));
	pg_assert_false(ws_flow_anchor_check(array('id' => 1), 'manual', array())['ok']);
}

// What a run started on its own keeps: the box, the anchor's run, the event
// and its record or the newcomer; a payload over 8 KB is left out.
function test_workspace_flow_anchor_context()
{
	$flow = array('channel_id' => 4, 'name' => 'Welcome');
	$trigger = array('id' => 'start', 'kind' => 'join');
	$context = ws_flow_anchor_context($flow, $trigger, array('run_id' => 12, 'newcomer' => 7, 'join_channel_id' => 9));

	pg_assert_same(array('trigger' => 'join', 'node' => 'start', 'channel_id' => 4, 'flow_name' => 'Welcome', 'sched_run' => 12, 'newcomer' => 7, 'join_channel_id' => 9), $context);

	$event = ws_flow_anchor_context($flow, array('id' => 'e', 'kind' => 'event'), array('run_id' => 3, 'event' => 'order.created', 'event_payload' => array('id' => 5)));

	pg_assert_same(array('id' => 5), $event['event_payload']);
	pg_assert_false(isset(ws_flow_anchor_context($flow, $trigger, array('event' => 'x', 'event_payload' => array('t' => str_repeat('a', 9000))))['event_payload']));
}

// Written as the flow is published, paused and cancelled with it; a start
// keyed by the anchor's run; never listed, never reported in a channel.
function test_workspace_flow_anchor_wiring()
{
	pg_assert_contains('ws_flow_anchor_sync($viewer, $flow,', ws_flow_anchors_test_source('ws_flow_publish'));
	pg_assert_contains('ws_flow_anchor_check($viewer, $node[\'kind\'], $node[\'config\'])', ws_flow_anchors_test_source('ws_flow_publish_check'));
	pg_assert_contains("ws_flow_anchor_set_status(\$flow['id'], 'paused')", ws_flow_anchors_test_source('ws_flow_pause'));
	pg_assert_contains("ws_flow_anchor_set_status(\$flow['id'], 'cancelled')", ws_flow_anchors_test_source('ws_flow_bin'));

	$start = ws_flow_anchors_test_source('ws_flow_anchor_start');

	pg_assert_contains("'sched:' . (int) (\$fill['run_id'] ?? 0)", $start);
	pg_assert_contains('ws_flow_start_refusal($open, $recent)', $start);
	pg_assert_contains("AND status = 'live'", $start);
	pg_assert_contains('ws_flow_anchor_start($viewer, $row, $fill)', ws_flow_anchors_test_source('ws_scheduled_execute'));

	pg_assert_contains("(int) (\$row['flow_id'] ?? 0) === 0", ws_flow_anchors_test_source('ws_scheduled_action'));
	pg_assert_contains('ws_scheduled_anchors_out()', ws_flow_anchors_test_source('ws_scheduled_list'));
	pg_assert_contains('ws_scheduled_anchors_out()', ws_flow_anchors_test_source('ws_scheduled_targets'));
	pg_assert_contains("(int) (\$row['flow_id'] ?? 0) > 0", ws_flow_anchors_test_source('ws_scheduled_report'));
}
