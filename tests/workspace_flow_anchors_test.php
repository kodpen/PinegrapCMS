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
 * scheduled actions and their reports; a converted anchor stays in them,
 * starts by hand and by a chain, and gets its flow run's end written back.
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
	pg_assert_contains("ws_flow_anchor_start(\$viewer, \$row, \$fill + array('by_hand' => \$by_hand))", ws_flow_anchors_test_source('ws_scheduled_execute'));

	pg_assert_contains('!ws_scheduled_anchor_hidden($row)', ws_flow_anchors_test_source('ws_scheduled_action'));
	pg_assert_contains('ws_scheduled_anchors_out()', ws_flow_anchors_test_source('ws_scheduled_list'));
	pg_assert_contains('ws_scheduled_anchors_out()', ws_flow_anchors_test_source('ws_scheduled_targets'));
	pg_assert_contains('if (ws_scheduled_anchor_hidden($row)) {', ws_flow_anchors_test_source('ws_scheduled_report'));
}

// Hidden and converted anchors: the mark decides, a row read before the
// mark existed is hidden when it has a flow.
function test_workspace_flow_anchor_marks()
{
	pg_assert_true(ws_scheduled_anchor_hidden(array('flow_id' => 3, 'anchor' => 'hidden')));
	pg_assert_false(ws_scheduled_anchor_hidden(array('flow_id' => 3, 'anchor' => 'converted')));
	pg_assert_false(ws_scheduled_anchor_hidden(array('flow_id' => 0, 'anchor' => '')));
	pg_assert_true(ws_scheduled_anchor_hidden(array('flow_id' => 3)));
	pg_assert_false(ws_scheduled_anchor_hidden(array('flow_id' => 0)));

	pg_assert_true(ws_scheduled_anchor_converted(array('flow_id' => 3, 'anchor' => 'converted')));
	pg_assert_false(ws_scheduled_anchor_converted(array('flow_id' => 0, 'anchor' => 'converted')));
	pg_assert_false(ws_scheduled_anchor_converted(array('flow_id' => 3, 'anchor' => 'hidden')));
	pg_assert_false(ws_scheduled_anchor_converted(array('flow_id' => 3)));

	// The filter reads the mark once the column is there, else the flow.
	$filter = ws_flow_anchors_test_source('ws_scheduled_anchors_out');

	pg_assert_contains("anchor <> 'hidden'", $filter);
	pg_assert_contains('flow_id = 0', $filter);

	// A new anchor is written hidden.
	pg_assert_contains("\$follow['anchor'] = \"'hidden'\"", ws_flow_anchors_test_source('ws_flow_anchor_sync'));
}

// A converted action's run: its context names the action and its source,
// a manual start box is started by hand or by a chain, the run's end is
// written back as the action's outcome.
function test_workspace_flow_anchor_converted_runs()
{
	$flow = array('channel_id' => 4, 'name' => 'Daily');
	$trigger = array('id' => 'start', 'kind' => 'manual');
	$row = array('id' => 21, 'flow_id' => 8, 'anchor' => 'converted');
	$context = ws_flow_anchor_context($flow, $trigger, array('run_id' => 30, 'row' => $row, 'source' => 'Morning', 'source_result' => 'ok'));

	pg_assert_same(21, $context['anchor_id']);
	pg_assert_same('Morning', $context['source']);
	pg_assert_same('ok', $context['source_result']);
	pg_assert_false(isset(ws_flow_anchor_context($flow, $trigger, array('run_id' => 30, 'row' => array('id' => 21, 'flow_id' => 8, 'anchor' => 'hidden')))['anchor_id']));

	pg_assert_same('Started by “Morning”.', ws_flow_anchor_start_said('manual', array('source' => 'Morning')));
	pg_assert_same('Started by hand.', ws_flow_anchor_start_said('manual', array()));
	pg_assert_same('Started by hand.', ws_flow_anchor_start_said('schedule', array('by_hand' => true)));
	pg_assert_same('Started on its own: At a time.', ws_flow_anchor_start_said('schedule', array()));

	pg_assert_same('done', ws_flow_anchor_run_status('done'));
	pg_assert_same('failed', ws_flow_anchor_run_status('failed'));
	pg_assert_same('skipped', ws_flow_anchor_run_status('cancelled'));
	pg_assert_same('', ws_flow_anchor_run_status('running'));

	pg_assert_contains("(\$converted && ((string) \$trigger['kind'] === 'manual'))", ws_flow_anchors_test_source('ws_flow_anchor_start'));

	// Reported once the flow's run ends, by the request that ended it.
	pg_assert_contains('!ws_scheduled_anchor_converted($row)', ws_flow_anchors_test_source('ws_scheduled_execute'));
	pg_assert_contains('ws_scheduled_report($row, $status, $steps, ws_scheduled_run_news(', ws_flow_anchors_test_source('ws_flow_anchor_run_ended'));
	pg_assert_contains('ws_flow_anchor_run_ended($run, $state);', ws_flow_anchors_test_source('ws_flow_run_refresh'));
	pg_assert_contains("ws_flow_anchor_run_ended(\$run, 'cancelled');", ws_flow_anchors_test_source('ws_flow_run_cancel'));
	pg_assert_contains("ws_flow_anchor_run_ended(\$run, 'cancelled');", ws_flow_anchors_test_source('ws_flow_runs_cancel_ids'));
	pg_assert_contains("ws_flow_anchor_run_ended(\$run, 'failed');", ws_flow_anchors_test_source('ws_flow_runs_heal'));
}

// A converted action's run counts as skipped when one of its conditions
// left on no, with that condition's sentence.
function test_workspace_flow_anchor_skip_reason()
{
	$graph = array('nodes' => array(
		array('id' => 'start', 'kind' => 'schedule'),
		array('id' => 'if1', 'kind' => 'condition'),
		array('id' => 'step', 'kind' => 'post'),
	));
	$step = function ($node, $outcome, $result = '') {
		return array('node_id' => $node, 'status' => 'done', 'outcome' => $outcome, 'result' => $result);
	};

	pg_assert_same('', ws_flow_anchor_skip_reason($graph, array($step('start', 'next'), $step('if1', 'yes'), $step('step', 'done'))));
	pg_assert_same('Not a working day.', ws_flow_anchor_skip_reason($graph, array($step('start', 'next'), $step('if1', 'no', ws_flow_result_pack('Not a working day.')))));
	pg_assert_same('A condition did not hold.', ws_flow_anchor_skip_reason($graph, array($step('if1', 'no'))));
	pg_assert_same('', ws_flow_anchor_skip_reason($graph, array($step('step', 'no'))), 'only a condition box skips');
	pg_assert_same('', ws_flow_anchor_skip_reason(null, array($step('if1', 'no'))));
}

// Changed on its flow by any staff member: the form refuses it, the card
// opens the flow, publishing is open to them.
function test_workspace_flow_anchor_converted_edit_path()
{
	pg_assert_contains('ws_scheduled_anchor_converted($existing)', ws_flow_anchors_test_source('ws_scheduled_save_check'));
	pg_assert_contains("\$out['flow'] = array('id' => (int) \$row['flow_id']", ws_flow_anchors_test_source('ws_scheduled_present'));
	pg_assert_contains("'converted' => \$staff && ws_flow_converted(", ws_flow_anchors_test_source('ws_flow_access'));
	pg_assert_contains('!ws_flow_share_manager($viewer, $flow) && !$converted', ws_flow_anchors_test_source('ws_flow_publish_check'));
	pg_assert_contains("anchor = 'converted'", ws_flow_anchors_test_source('ws_flow_converted'));
	pg_assert_contains("ws_flow_anchor_skip_reason(ws_flow_run_graph(\$run), ws_flow_run_steps(\$run['id']))", ws_flow_anchors_test_source('ws_flow_anchor_run_ended'));
}
