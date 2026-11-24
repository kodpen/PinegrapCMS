<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for leaving a flow (includes/workspace/flow_leave.php): the leave
 * rules as the graph keeps and checks them, the events offered, what an
 * event names (its record and that record's contact), which rule of a run
 * an event matches, what a box of a run that left says, and, read from the
 * source, that the events' run wakes the hook and a left run is its own
 * state.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// Each rule is one event, kept once, in the order written; junk dropped.
function test_workspace_flow_leave_normalize()
{
	pg_assert_same(array(), ws_flow_leave_normalize(null));
	pg_assert_same(array(), ws_flow_leave_normalize('order.created'));
	pg_assert_same(
		array(array('event' => 'order.created'), array('event' => 'contact_group.joined')),
		ws_flow_leave_normalize(array(array('event' => ' order.created '), 'x', array('event' => ''), array('event' => 'contact_group.joined'), array('event' => 'order.created'), array('event' => 'bad event!')))
	);
}

// The graph keeps the rules; an empty graph has none; more than the limit
// is a problem a draft may hold.
function test_workspace_flow_leave_in_the_graph()
{
	pg_assert_same(array(), ws_flow_graph_empty()['leave']);
	pg_assert_same(array(array('event' => 'order.created')), ws_flow_graph_normalize(array('leave' => array(array('event' => 'order.created'))))['leave']);

	$many = array();

	for ($i = 0; $i <= WS_FLOW_MAX_LEAVE; $i++) {
		$many[] = array('event' => 'event.n' . $i);
	}

	$codes = array_column(ws_flow_graph_check(array('nodes' => array(), 'leave' => $many)), 'code');

	pg_assert_true(in_array('too_many', $codes, true));
	pg_assert_same(10, WS_FLOW_MAX_LEAVE);
}

// Offered: the catalogue's events about a person or a record; never one
// about nothing or a channel, one kept only on demand, or the workspace's own.
function test_workspace_flow_leave_offered()
{
	$catalog = array(
		'order.created'           => array('record' => 'order'),
		'contact_group.joined'    => array('record' => 'contact'),
		'campaign.sent'           => array('record' => ''),
		'workspace.mail.received' => array('record' => 'channel'),
		'inventory.changed'       => array('record' => 'product'),
		'workspace.task.completed' => array('record' => 'task'),
	);

	pg_assert_same(array('order.created', 'contact_group.joined'), ws_flow_leave_offered($catalog, array('inventory.changed'), array('workspace.task.completed')));
}

// An event names its record and, for a record with a person, that
// person's contact first; a contact's event names the contact once.
function test_workspace_flow_leave_subjects()
{
	$contact_of = function ($type, $id) {
		return (($type === 'order') && ($id === 7)) ? 40 : 0;
	};

	pg_assert_same(array(array('contact', 40), array('order', 7)), ws_flow_leave_subjects('order.created', array('id' => 7), $contact_of));
	pg_assert_same(array(array('order', 8)), ws_flow_leave_subjects('order.created', array('id' => 8), $contact_of));
	pg_assert_same(array(array('contact', 12)), ws_flow_leave_subjects('contact_group.joined', array('id' => 12), $contact_of));
	pg_assert_same(array(), ws_flow_leave_subjects('order.created', array(), $contact_of));
	pg_assert_same(array(), ws_flow_leave_subjects('campaign.sent', array('id' => 3), $contact_of));
}

// The run of a contact who orders: its rule matches by the event's name,
// the first such rule; an event no rule names leaves it as it is.
function test_workspace_flow_leave_rule_match()
{
	$leave = array(array('event' => 'contact_group.joined'), array('event' => 'order.created'));

	pg_assert_same(1, ws_flow_leave_rule($leave, 'order.created', array('id' => 7)));
	pg_assert_same(0, ws_flow_leave_rule($leave, 'contact_group.joined', array('id' => 3)));
	pg_assert_same(-1, ws_flow_leave_rule($leave, 'order.shipped', array('id' => 7)));
	pg_assert_same(-1, ws_flow_leave_rule(array(), 'order.created', array('id' => 7)));
	pg_assert_same(-1, ws_flow_leave_rule(null, 'order.created', array('id' => 7)));
}

// An event a flow's own box caused (it carries a chain depth) ends no run,
// so a run never ends itself through what it does.
function test_workspace_flow_leave_not_by_a_box()
{
	$leave = array(array('event' => 'customer.updated'));

	pg_assert_same(-1, ws_flow_leave_rule($leave, 'customer.updated', array('id' => 3, '_depth' => 1)));
	pg_assert_same(0, ws_flow_leave_rule($leave, 'customer.updated', array('id' => 3, '_depth' => 0)));
}

// A run's subject at its start: the first thing the event names.
function test_workspace_flow_leave_run_subject()
{
	$contact_of = function ($type, $id) {
		return ($type === 'form') ? 9 : 0;
	};

	pg_assert_same(array('contact', 9), ws_flow_leave_run_subject('form.submitted', array('id' => 4), $contact_of));
	pg_assert_same(array('', 0), ws_flow_leave_run_subject('campaign.sent', array(), $contact_of));
	pg_assert_same(array('', 0), ws_flow_leave_run_subject('', array('id' => 4), $contact_of));
}

// An open box of a run that left says which rule ended it; a task stays,
// an approval request stays open.
function test_workspace_flow_leave_box_result()
{
	pg_assert_true(strpos(ws_flow_leave_result('', 'An order is placed'), 'An order is placed') !== false);
	pg_assert_true(strpos(ws_flow_leave_result('task:5', 'An order is placed'), 'task') !== false);
	pg_assert_true(strpos(ws_flow_leave_result('approval:5', 'An order is placed'), 'approval') !== false);
}

// Left is a run state of its own, swept as the other ended ones, kept off
// a converted action's failure; the events' run calls the hook.
function test_workspace_flow_leave_wired()
{
	pg_assert_true(isset(ws_flow_run_statuses()['left']));
	pg_assert_same('skipped', ws_flow_anchor_run_status('left'));

	$runs = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
	$watch = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');

	pg_assert_true(strpos($runs, "status IN ('done', 'failed', 'cancelled', 'left')") !== false);
	pg_assert_true(strpos($watch, 'ws_flow_leave((string) $row[\'event\'], $payload)') !== false);
}
