<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Flows list (flows.php, workspace_flow.js): converted flows
 * are ordinary rows, merged into the list in its order; a flow that starts
 * at a time gives its next run in the start column; the flow's own answer
 * carries the list's words of its row; the list ends with the
 * scheduled actions the conversion refused only, and a cancelled row that
 * was never converted, a converted row and a flow's hidden anchor are not
 * in that group. Read from the source: the Scheduled actions section and
 * the converted group are gone and the old address opens the list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');

// Only rows left on the old runner (no flow, no anchor, not cancelled) end
// the list, in the order given; converted rows are flows of the table.
function test_workspace_flows_list_scheduled_groups()
{
	$rows = array(
		array('id' => 1, 'kind' => 'action', 'flow_id' => 7, 'anchor' => 'converted', 'status' => 'active'),
		array('id' => 2, 'kind' => 'action', 'flow_id' => 0, 'anchor' => '', 'status' => 'failed'),
		array('id' => 3, 'kind' => 'action', 'flow_id' => 0, 'anchor' => '', 'status' => 'cancelled'),
		array('id' => 4, 'kind' => 'action', 'flow_id' => 9, 'anchor' => 'hidden', 'status' => 'active'),
		array('id' => 5, 'kind' => 'action', 'flow_id' => 8, 'anchor' => 'converted', 'status' => 'cancelled'),
		array('id' => 6, 'kind' => 'message', 'flow_id' => 0, 'anchor' => '', 'status' => 'active'),
		array('id' => 7, 'kind' => 'action', 'flow_id' => 0, 'anchor' => '', 'status' => 'done'),
		array('id' => 8, 'flow_id' => '0', 'status' => 'paused'),
	);
	$groups = ws_flows_scheduled_groups($rows);

	pg_assert_same(array('refused'), array_keys($groups), 'no converted group');
	pg_assert_same(array(2, 7, 8), array_map(function ($row) { return (int) $row['id']; }, $groups['refused']), 'refused rows of any state but cancelled; a row from before the kind and anchor columns counts');
	pg_assert_same(array('refused' => array()), ws_flows_scheduled_groups(array()));
}

// Converted flows another staff member owns join the list once, in its
// order (updated_at DESC, id DESC); a flow already listed is not doubled.
function test_workspace_flows_list_rows_merge()
{
	$rows = array(
		array('id' => 4, 'updated_at' => 300),
		array('id' => 2, 'updated_at' => 100),
	);
	$extra = array(
		array('id' => 9, 'updated_at' => 200),
		array('id' => 4, 'updated_at' => 300),
		array('id' => 5, 'updated_at' => 300),
		array('id' => 1, 'updated_at' => 50),
	);
	$ids = function ($list) {
		return array_map(function ($row) { return (int) $row['id']; }, $list);
	};

	pg_assert_same(array(5, 4, 9, 2, 1), $ids(ws_flows_rows_merge($rows, $extra)), 'merged, newest first, a tie by the higher id, no row twice');
	pg_assert_same(array(4, 2), $ids(ws_flows_rows_merge($rows, array())), 'nothing to add keeps the list');
	pg_assert_same(array(9), $ids(ws_flows_rows_merge(array(), array(array('id' => 9, 'updated_at' => 1)))), 'only added rows');
}

// The next run is read only for a live flow whose start is a time.
function test_workspace_flows_list_next_run_ids()
{
	$rows = array(
		array('id' => 1, 'status' => 'live'),
		array('id' => 2, 'status' => 'paused'),
		array('id' => 3, 'status' => 'draft'),
		array('id' => 4, 'status' => 'live'),
		array('id' => 5, 'status' => 'live'),
	);
	$starts = array(
		1 => array('kind' => 'time', 'text' => 'Every day 09:00'),
		2 => array('kind' => 'time', 'text' => 'Every day 09:00'),
		3 => array('kind' => 'time', 'text' => 'Every day 09:00'),
		4 => array('kind' => 'event', 'text' => 'When a form is submitted'),
	);

	pg_assert_same(array(1), ws_flows_next_run_ids($rows, $starts), 'live and time only; a flow with no start read is left out');
	pg_assert_same(array(), ws_flows_next_run_ids(array(), array()));
}

// The start column's words with the next run, as the cards say "Next".
function test_workspace_flows_list_start_with_next()
{
	pg_assert_same(lang(array('string' => '{var:1} · Next: {var:2}', 'vars' => array('Every day 09:00', 'Tomorrow 09:00'))), ws_flow_start_with_next('Every day 09:00', 'Tomorrow 09:00'), 'the next run follows the sentence');
	pg_assert_same('Every day 09:00', ws_flow_start_with_next('Every day 09:00', ''), 'no next run, the sentence alone');
	pg_assert_same('', ws_flow_start_with_next('', 'Tomorrow 09:00'), 'no sentence, nothing');
}

// The flow's own answer (ws_flow_detail()) carries the list's start words
// and home channel name, so the flow's row keeps them once it is opened,
// published, paused or resumed. A draft started by hand with no home: the
// list's "By hand", no channel and no next run, nothing read.
function test_workspace_flows_list_words_of_one_flow()
{
	$viewer = array('id' => 1, 'role' => 0);
	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(array('id' => 'start', 'kind' => 'manual', 'config' => array()), array('id' => 'stop', 'kind' => 'end')),
		'edges' => array(array('from' => 'start', 'to' => 'stop')),
	));
	$flow = array('id' => 7, 'status' => 'draft', 'channel_id' => 0);

	pg_assert_same(array('start_text' => lang('By hand'), 'channel_name' => ''), ws_flow_list_words($viewer, $flow, $graph, ws_flow_start_kind($graph)), 'by hand, no home');
	pg_assert_same(array('start_text' => '', 'channel_name' => ''), ws_flow_list_words($viewer, $flow, ws_flow_graph_empty(), ''), 'no start box');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
	$detail = substr($source, strpos($source, 'function ws_flow_detail('), 1200);

	pg_assert_contains('$start = ws_flow_start_kind($graph);', $detail, 'the kind of the graph it starts from');
	pg_assert_contains('+ ws_flow_list_words($viewer, $flow, $graph, $start) +', $detail, 'the list\'s words in the flow\'s own answer');
}

// The Scheduled actions section and its entry are gone; ?view=scheduled
// (old links, notifications) opens the list, and a failed action's notice
// without a card points at the list itself. The converted group is gone
// with its Run now and History (Run now is on the flow's canvas); the not
// converted rows are drawn by the cards of scheduled actions, whose list
// leaves out converted and cancelled rows where Flows lists them.
function test_workspace_flows_list_no_scheduled_section()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$notify = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_false(strpos($js, 'function showScheduled(') !== false, 'no Scheduled actions section');
	pg_assert_false((bool) preg_match('~view=scheduled\(|\?view=scheduled\'~', $js), 'the flows screen neither reads nor writes ?view=scheduled');
	pg_assert_true(strpos($js, 'K.scheduledList(') !== false, 'the not converted group is drawn by the cards of scheduled actions');
	pg_assert_false(strpos($js, 'function convertedRow(') !== false || strpos($js, 'convertedChanged(') !== false, 'no converted group');
	pg_assert_false(strpos($js, "'ws_scheduled_run_now'") !== false || strpos($js, "'ws_scheduled_get'") !== false, 'no Run now or History of a converted row in the list');
	pg_assert_false(strpos($notify, 'workspace_flows.php?view=scheduled') !== false, 'a notice points at the flow list');
	pg_assert_true(strpos($actions, "empty(\$item['flow']) && ((string) \$item['status'] !== 'cancelled')") !== false, 'the cards list only rows not converted and not cancelled');
}
