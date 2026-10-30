<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a role-3 member's own flows (includes/workspace/flow_member.php):
 * who builds flows, the kinds of box a member's flow may hold and the ones
 * refused, a summary of counts kept off e-mail addresses, no home channel,
 * the two caps (flows kept, starts an hour), a box stopped at run once its
 * owner no longer may use it, a run started by somebody else refused, and
 * staff unchanged throughout.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_member.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_values.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_loops.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A box of a graph.
function ws_flows_member_test_node($id, $kind, $config = array())
{
	return array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
}

// A flow started by hand whose second box is of the kind asked.
function ws_flows_member_test_graph($kind, $config = array())
{
	return array(
		'nodes' => array(ws_flows_member_test_node('start', 'manual'), ws_flows_member_test_node('box_1', $kind, $config)),
		'edges' => array(array('from' => 'start', 'to' => 'box_1', 'on' => 'next')),
	);
}

function test_workspace_flows_member_builder_kind_by_role()
{
	pg_assert_same('staff', ws_flow_builder_kind(array('member' => true, 'role' => 0)));
	pg_assert_same('staff', ws_flow_builder_kind(array('member' => true, 'role' => 1)));
	pg_assert_same('staff', ws_flow_builder_kind(array('member' => true, 'role' => 2)));
	pg_assert_same('member', ws_flow_builder_kind(array('member' => true, 'role' => 3)));
	pg_assert_same('', ws_flow_builder_kind(array('member' => false, 'role' => 0)));
	pg_assert_same('', ws_flow_builder_kind(null));
}

function test_workspace_flows_member_allowed_kinds_pass()
{
	foreach (array('manual', 'schedule', 'event', 'end', 'task', 'approval', 'delay', 'condition', 'constants', 'value',
		'post', 'notify', 'task_open', 'report', 'task_digest', 'row_add', 'row_set') as $kind) {
		pg_assert_same('', ws_flow_member_node_refusal(ws_flows_member_test_node('b', $kind)), $kind);
	}

	pg_assert_true(in_array('change', ws_flow_member_kinds(), true));
}

function test_workspace_flows_member_refused_kinds_stop()
{
	foreach (array('webhook', 'web_check', 'mcp', 'email', 'lead', 'checkin', 'start_action',
		'start_flow', 'for_each', 'for_each_end', 'join', 'row') as $kind) {
		pg_assert_same('This kind of box is not open to your own flows.', ws_flow_member_node_refusal(ws_flows_member_test_node('b', $kind)), $kind);
	}
}

function test_workspace_flows_member_report_goes_to_a_channel_only()
{
	pg_assert_same('', ws_flow_member_node_refusal(ws_flows_member_test_node('b', 'report', array('channel_id' => 4, 'to' => ''))));
	pg_assert_contains('not to e-mail addresses', ws_flow_member_node_refusal(ws_flows_member_test_node('b', 'report', array('channel_id' => 4, 'to' => 'someone@example.com'))));
}

function test_workspace_flows_member_change_box_is_saved_with_a_channel_to_ask_in()
{
	$change = array('record' => 'contact', 'action' => 'update', 'id' => 4, 'fields' => array('first_name' => 'Ada'));

	pg_assert_same('', ws_flow_member_node_refusal(ws_flows_member_test_node('b', 'change', $change + array('channel_id' => 3))));
	pg_assert_contains('choose the channel to ask in', ws_flow_member_node_refusal(ws_flows_member_test_node('b', 'change', $change + array('channel_id' => 0))));
	pg_assert_contains('choose the channel to ask in', ws_flow_member_node_refusal(ws_flows_member_test_node('b', 'change', $change)));

	$saved = ws_flow_member_flow_refusal('member', 0, ws_flows_member_test_graph('change', $change + array('channel_id' => 3)));
	pg_assert_same('', $saved['refusal']);
}

function test_workspace_flows_member_change_box_proposes_for_a_member_and_writes_for_staff()
{
	pg_assert_true(ws_flow_change_asks('member'));
	pg_assert_true(!ws_flow_change_asks('staff'));
	pg_assert_true(!ws_flow_change_asks(''));

	// A staff-built change box with no channel runs on once its owner is
	// role 3: it proposes, and fails there for the missing channel.
	pg_assert_same('', ws_flow_member_box_refusal('member', ws_flows_member_test_node('b', 'change', array('record' => 'contact', 'id' => 4))));
	pg_assert_same('', ws_flow_member_box_refusal('staff', ws_flows_member_test_node('b', 'change')));
}

function test_workspace_flows_member_graph_refusal_names_the_box()
{
	pg_assert_same('', ws_flow_member_graph_refusal(ws_flows_member_test_graph('post')));

	$said = ws_flow_member_graph_refusal(ws_flows_member_test_graph('webhook'));
	pg_assert_contains('box_1', $said);
	pg_assert_contains('not open to your own flows', $said);
}

function test_workspace_flows_member_flow_has_no_home_channel()
{
	$home = ws_flow_member_flow_refusal('member', 7, ws_flows_member_test_graph('post'));
	pg_assert_same('channel_id', $home['field']);
	pg_assert_contains('no home channel', $home['refusal']);

	$box = ws_flow_member_flow_refusal('member', 0, ws_flows_member_test_graph('mcp'));
	pg_assert_same('graph', $box['field']);
	pg_assert_true($box['refusal'] !== '');

	pg_assert_same('', ws_flow_member_flow_refusal('member', 0, ws_flows_member_test_graph('notify'))['refusal']);
}

function test_workspace_flows_member_staff_flow_unchanged()
{
	// A home channel and every kind stay open to staff.
	pg_assert_same('', ws_flow_member_flow_refusal('staff', 7, ws_flows_member_test_graph('webhook'))['refusal']);
	pg_assert_same('', ws_flow_member_box_refusal('staff', ws_flows_member_test_node('b', 'mcp')));
	pg_assert_same('', ws_flow_member_starts_refusal('staff', 1000));
	pg_assert_same('', ws_flow_member_starter_refusal('staff', 5, 9));
	pg_assert_same(WS_FLOW_MAX_PER_OWNER, ws_flow_max_per_owner('staff'));
}

function test_workspace_flows_member_flows_kept_cap()
{
	pg_assert_same(10, WS_FLOW_MEMBER_MAX_PER_OWNER);
	pg_assert_same(10, ws_flow_max_per_owner('member'));
}

function test_workspace_flows_member_starts_hourly_cap()
{
	pg_assert_same(20, WS_FLOW_MEMBER_STARTS_HOURLY);
	pg_assert_same('', ws_flow_member_starts_refusal('member', 19));
	pg_assert_contains('20 times in the last hour', ws_flow_member_starts_refusal('member', 20));
}

function test_workspace_flows_member_run_stops_a_box_after_a_lost_right()
{
	// Built while on the staff, run after the owner became a role-3 member.
	$hook = ws_flows_member_test_node('b', 'webhook', array('url' => 'https://example.com/hook'));
	pg_assert_same('', ws_flow_member_box_refusal('staff', $hook));
	pg_assert_contains('did not run', ws_flow_member_box_refusal('member', $hook));

	// An allowed box runs on; its reach is asked by the box itself.
	pg_assert_same('', ws_flow_member_box_refusal('member', ws_flows_member_test_node('b', 'post')));
}

function test_workspace_flows_member_flow_is_run_by_its_owner_alone()
{
	pg_assert_same('', ws_flow_member_starter_refusal('member', 5, 5));
	pg_assert_same('Only its owner runs this flow.', ws_flow_member_starter_refusal('member', 5, 9));
}

function test_workspace_flows_member_kinds_are_flow_kinds()
{
	$kinds = ws_flow_kinds();

	foreach (ws_flow_member_kinds() as $kind) {
		pg_assert_true(isset($kinds[$kind]), $kind);
	}
}
