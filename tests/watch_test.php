<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the site's events in the workspace: which events are kept for the
 * workspace (includes/fn/events.php), which record an event is about, which
 * events a watching channel hears, and whether an event rule of a scheduled
 * action matches an event, the workspace's own events included
 * (includes/workspace/watch.php). The functions tested here read nothing
 * from the database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The module file holds functions and constants only; loading it runs nothing.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');

// The workspace keeps the shop's, the contacts' and the forms' events, never
// its own nor the ERP's.
function test_watch_recorded_events_leave_out_workspace_and_erp()
{
	$events = pg_event_workspace_events();

	foreach (array('order.created', 'order.status_changed', 'order.shipped', 'order.delivered', 'order.cancelled', 'stock.low', 'customer.updated', 'product.updated', 'form.submitted') as $event) {
		pg_assert_true(in_array($event, $events, true), $event . ' kept');
	}

	foreach ($events as $event) {
		pg_assert_false(strpos($event, 'workspace.') === 0, $event . ' is not the workspace\'s own');
		pg_assert_false(strpos($event, 'erp.') === 0, $event . ' is not the ERP\'s');
	}
}

// Without the workspace switched on (no WORKSPACE_ENABLED in the runner)
// nothing is written and nothing is asked of the database.
function test_watch_record_is_silent_when_workspace_is_off()
{
	pg_assert_false(defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED, 'off in the test runner');
	pg_assert_same(null, pg_event_record('order.created', array('id' => 1)), 'returns without a query');
}

// Each event names its record under its own key.
function test_watch_record_of_maps_events_to_tags()
{
	pg_assert_same(array('order', 1045), ws_events_record_of('order.created', array('id' => 1045, 'order_number' => 2001)), 'order.created');
	pg_assert_same(array('order', 7), ws_events_record_of('order.status_changed', array('id' => '7', 'status' => 'exported')), 'id as a string');
	pg_assert_same(array('product', 12), ws_events_record_of('stock.low', array('product_id' => 12, 'quantity' => 2)), 'stock.low by product_id');
	pg_assert_same(array('product', 13), ws_events_record_of('product.updated', array('id' => 13)), 'product.updated by id');
	pg_assert_same(array('contact', 5), ws_events_record_of('customer.updated', array('id' => 5)), 'customer.updated');
	pg_assert_same(array('form', 740), ws_events_record_of('form.submitted', array('id' => 740, 'form_id' => 395)), 'the submission, not the form page');
	pg_assert_same(array('', 0), ws_events_record_of('file.created', array('id' => 3)), 'an event about nothing tagged');
	pg_assert_same(array('order', 0), ws_events_record_of('order.created', array()), 'no id');
}

// A channel hears the orders' events and a change to a contact or a product;
// a submitted form and a new contact cannot have been tagged before.
function test_watch_watched_events()
{
	pg_assert_true(ws_events_watched('order.shipped'), 'order.shipped');
	pg_assert_true(ws_events_watched('stock.low'), 'stock.low');
	pg_assert_true(ws_events_watched('customer.updated'), 'customer.updated');
	pg_assert_false(ws_events_watched('form.submitted'), 'form.submitted');
	pg_assert_false(ws_events_watched('customer.created'), 'customer.created');
	pg_assert_false(ws_events_watched('workspace.message.created'), 'the workspace\'s own');
}

// An event rule matches its event, narrowed by the form page or the status.
function test_watch_rule_matches_event_and_filters()
{
	$any_form = array('type' => 'event', 'event' => 'form.submitted', 'page_id' => 0);
	$one_form = array('type' => 'event', 'event' => 'form.submitted', 'page_id' => 395);
	$completed = array('type' => 'event', 'event' => 'order.status_changed', 'status' => 'complete');
	$submitted = array('id' => 740, 'form_id' => 395);

	pg_assert_true(ws_events_rule_matches($any_form, 'form.submitted', $submitted), 'any form');
	pg_assert_true(ws_events_rule_matches($one_form, 'form.submitted', $submitted), 'its form');
	pg_assert_false(ws_events_rule_matches($one_form, 'form.submitted', array('id' => 741, 'form_id' => 472)), 'another form');
	pg_assert_false(ws_events_rule_matches($any_form, 'order.created', array('id' => 1)), 'another event');
	pg_assert_true(ws_events_rule_matches($completed, 'order.status_changed', array('id' => 1, 'status' => 'complete')), 'its status');
	pg_assert_false(ws_events_rule_matches($completed, 'order.status_changed', array('id' => 1, 'status' => 'exported')), 'another status');
	pg_assert_true(ws_events_rule_matches(array('type' => 'event', 'event' => 'order.status_changed', 'status' => ''), 'order.status_changed', array('status' => 'exported')), 'any status');
	pg_assert_false(ws_events_rule_matches(array('type' => 'join', 'event' => 'order.created'), 'order.created', array()), 'not an event rule');
	pg_assert_false(ws_events_rule_matches(null, 'order.created', array()), 'no rule');
}

// The workspace's own events are kept by ws_event(), never by
// pg_event_record(), and are the five a scheduled action can wait for.
function test_watch_own_events_are_kept_apart()
{
	$recorded = pg_event_workspace_events();

	foreach (ws_events_own() as $event) {
		pg_assert_true(ws_events_is_own($event), $event . ' is the workspace\'s own');
		pg_assert_false(in_array($event, $recorded, true), $event . ' not kept by pg_event_record()');
	}

	pg_assert_same(9, count(ws_events_own()), 'six events, and the three of a workflow table\'s rows');
	pg_assert_false(ws_events_is_own('workspace.message.created'), 'a message in a public channel is the webhooks\' only');
	pg_assert_false(ws_events_is_own('order.created'), 'a site event');
	pg_assert_false(ws_event('workspace.task.completed', array('id' => 1)), 'nothing kept without the workspace\'s tables');
}

// A task's events tag the task; a settled request the task it asked about,
// else its channel; a guest's message the guest's room.
function test_watch_record_of_own_events()
{
	pg_assert_same(array('task', 42), ws_events_record_of('workspace.task.completed', array('id' => 42, 'channel_id' => 3)), 'completed');
	pg_assert_same(array('task', 42), ws_events_record_of('workspace.task.status_changed', array('id' => '42', 'status' => 'waiting')), 'status changed');
	pg_assert_same(array('task', 7), ws_events_record_of('workspace.task.overdue', array('id' => 7)), 'overdue');
	pg_assert_same(array('task', 9), ws_events_record_of('workspace.approval.decided', array('id' => 2, 'channel_id' => 3, 'record_type' => 'task', 'record_id' => 9)), 'a request about a task');
	pg_assert_same(array('channel', 3), ws_events_record_of('workspace.approval.decided', array('id' => 2, 'channel_id' => 3, 'record_type' => 'order', 'record_id' => 9)), 'a request about another record');
	pg_assert_same(array('channel', 3), ws_events_record_of('workspace.approval.decided', array('id' => 2, 'channel_id' => 3, 'record_type' => '', 'record_id' => 0)), 'a request about nothing');
	pg_assert_same(array('channel', 11), ws_events_record_of('workspace.guest.message', array('id' => 500, 'channel_id' => 11)), 'a guest\'s message');
	pg_assert_same(array('channel', 12), ws_events_record_of('workspace.mail.received', array('id' => 40, 'channel_id' => 12)), 'an e-mail, by its channel');
	pg_assert_false(ws_events_watched('workspace.task.completed'), 'never written into a watching channel');
}

// A workspace rule narrowed to a channel and a status matches only there.
function test_watch_rule_matches_channel_and_task_status()
{
	$any = array('type' => 'event', 'event' => 'workspace.task.completed', 'channel_id' => 0);
	$design = array('type' => 'event', 'event' => 'workspace.task.completed', 'channel_id' => 4);
	$waiting = array('type' => 'event', 'event' => 'workspace.task.status_changed', 'status' => 'waiting', 'channel_id' => 0);
	$approved = array('type' => 'event', 'event' => 'workspace.approval.decided', 'status' => 'approved', 'channel_id' => 4);

	pg_assert_true(ws_events_rule_matches($any, 'workspace.task.completed', array('id' => 1, 'channel_id' => 9)), 'any channel');
	pg_assert_true(ws_events_rule_matches($any, 'workspace.task.completed', array('id' => 1, 'channel_id' => 0)), 'a task of no channel');
	pg_assert_true(ws_events_rule_matches($design, 'workspace.task.completed', array('id' => 1, 'channel_id' => 4)), 'its channel');
	pg_assert_false(ws_events_rule_matches($design, 'workspace.task.completed', array('id' => 1, 'channel_id' => 5)), 'another channel');
	pg_assert_false(ws_events_rule_matches($design, 'workspace.task.completed', array('id' => 1)), 'a task of no channel, for a rule of one');
	pg_assert_true(ws_events_rule_matches($waiting, 'workspace.task.status_changed', array('id' => 1, 'status' => 'waiting')), 'its status');
	pg_assert_false(ws_events_rule_matches($waiting, 'workspace.task.status_changed', array('id' => 1, 'status' => 'doing')), 'another status');
	pg_assert_true(ws_events_rule_matches($approved, 'workspace.approval.decided', array('channel_id' => 4, 'status' => 'approved')), 'approved there');
	pg_assert_false(ws_events_rule_matches($approved, 'workspace.approval.decided', array('channel_id' => 4, 'status' => 'rejected')), 'rejected there');
	pg_assert_false(ws_events_rule_matches($approved, 'workspace.task.completed', array('channel_id' => 4, 'status' => 'approved')), 'another event');
}

// A run says its depth as it starts and ends; what it causes stands one
// step below it, and outside a run at the head of a chain.
function test_watch_event_depth_follows_the_run()
{
	pg_assert_same(0, ws_event_depth(), 'outside a run');
	pg_assert_same(1, ws_event_depth(0), 'a run at the head of a chain');
	pg_assert_same(1, ws_event_depth(), 'read while it runs');
	pg_assert_same(4, ws_event_depth(3), 'a run three steps down');
	pg_assert_same(0, ws_event_depth(-1), 'the run ended');
	pg_assert_same(0, ws_event_depth(), 'outside again');
}
