<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the end of the Flows list (flows.php, workspace_flow.js): the
 * scheduled actions converted into flows and the ones the conversion
 * refused, each a group of its own; a cancelled row that was never
 * converted and a flow's hidden anchor are in neither. Read from the
 * source: the Scheduled actions section is gone and its address opens the
 * list.
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

// Converted rows (a flow and anchor converted) in one group, rows left on
// the old runner (no flow, no anchor, not cancelled) in the other, each in
// the order given; the rest are in neither.
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
	$ids = function ($list) {
		return array_map(function ($row) { return (int) $row['id']; }, $list);
	};

	pg_assert_same(array(1, 5), $ids($groups['converted']), 'every converted row, a cancelled one too: its history stays');
	pg_assert_same(array(2, 7, 8), $ids($groups['refused']), 'refused rows of any state but cancelled; a row from before the kind and anchor columns counts');
	pg_assert_same(array('converted' => array(), 'refused' => array()), ws_flows_scheduled_groups(array()));
}
