<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the events of customer returns (includes/fn/returns.php): what an
 * order.return.* event carries (pg_return_event_payload()), the four names in
 * the webhook catalogue, the Workspace trigger order.return.requested and the
 * record it is about, where each event is announced (after the order's lock,
 * never from the move to refunded), and the fields the audit trail keeps of a
 * new return.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The outbound webhook module's gate asks for init.php. Behind the gate the
// file only declares functions, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');

// A return as pg_return_load() gives it: the row's columns as strings, the
// lines with their names.
function returns_events_test_row($changes = array())
{
	return array_merge(array(
		'id' => '12', 'order_id' => '9001', 'ship_to_id' => '9001', 'status' => 'requested', 'reason' => 'damaged',
		'customer_note' => 'Kutu ezik geldi', 'decision_note' => '', 'carrier' => '', 'carrier_code' => '', 'tracking_number' => '',
		'source' => 'customer', 'requested_by' => '9001', 'decided_by' => '0', 'requested_at' => '1792731600',
		'decided_at' => '0', 'shipped_at' => '0', 'received_at' => '0', 'refunded_at' => '0', 'closed_at' => '0',
		'items' => array(
			array('order_item_id' => 9001, 'quantity' => 2, 'product_id' => 71, 'name' => 'Yeşil Ofis Sandalyesi'),
			array('order_item_id' => 9002, 'quantity' => 1, 'product_id' => 70, 'name' => 'Mocha Ofis Sandalyesi'),
		),
	), $changes);
}

function returns_events_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function returns_events_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// Flat, like order.shipped: id is the order's, then the return, the lines as
// ids and quantities only.
function test_returns_events_payload_shape()
{
	$payload = pg_return_event_payload(returns_events_test_row(), '99001');

	pg_assert_same(array(
		'id'           => 9001,
		'order_number' => '99001',
		'return_id'    => 12,
		'ship_to_id'   => 9001,
		'status'       => 'requested',
		'source'       => 'customer',
		'reason'       => 'damaged',
		'items'        => array(
			array('order_item_id' => 9001, 'quantity' => 2),
			array('order_item_id' => 9002, 'quantity' => 1),
		),
	), $payload);
}

// The refund adds its amount, how it was made and whether the service gave a
// clear answer; an extra field never replaces one of the return's own.
function test_returns_events_payload_refunded_extras()
{
	$payload = pg_return_event_payload(returns_events_test_row(array('status' => 'refunded', 'refunded_at' => '1792818000')), '99001',
		array('amount' => 12500, 'refund_method' => 'driver', 'unclear' => true, 'id' => 1, 'status' => 'received'));

	pg_assert_same(9001, $payload['id'], 'the order id stays');
	pg_assert_same('refunded', $payload['status'], 'the status after the move stays');
	pg_assert_same(12500, $payload['amount']);
	pg_assert_same('driver', $payload['refund_method']);
	pg_assert_same(true, $payload['unclear']);
	pg_assert_same(array('id', 'order_number', 'return_id', 'ship_to_id', 'status', 'source', 'reason', 'items', 'amount', 'refund_method', 'unclear'),
		array_keys($payload), 'the extras come after the return');
}

// A row read without its lines still gives a list.
function test_returns_events_payload_without_lines()
{
	$row = returns_events_test_row();
	unset($row['items']);

	pg_assert_same(array(), pg_return_event_payload($row, '')['items']);
	pg_assert_same('', pg_return_event_payload($row, '')['order_number']);
}

// The four names are in the catalogue, so pg_announce() does not drop them;
// the moves that are not announced have no name there.
function test_returns_events_catalogue_holds_the_four()
{
	$events = api_webhook_events();

	foreach (array('order.return.requested', 'order.return.approved', 'order.return.received', 'order.return.refunded') as $event) {
		pg_assert_true(api_webhook_event_is_valid($event), $event . ' is announced');
		pg_assert_true(trim((string) $events[$event]) !== '', $event . ' is described');
	}

	foreach (array('order.return.rejected', 'order.return.shipped', 'order.return.closed') as $event) {
		pg_assert_false(api_webhook_event_is_valid($event), $event . ' is not announced');
	}

	pg_assert_contains('not announced', $events['order.return.approved'], 'a rejection is said not to be announced');
	pg_assert_contains('unclear', $events['order.return.refunded'], 'the unclear refund is named');
}

// A scheduled action may start on a new return request: the Workspace keeps
// the event, and it is about the order (the payload's id).
function test_returns_events_workspace_trigger()
{
	$kept = pg_event_workspace_events();

	pg_assert_true(in_array('order.return.requested', $kept, true), 'the Workspace keeps order.return.requested');

	foreach (array('order.return.approved', 'order.return.received', 'order.return.refunded') as $event) {
		pg_assert_false(in_array($event, $kept, true), $event . ' starts nothing, so it is not kept');
	}

	$payload = pg_return_event_payload(returns_events_test_row(), '99001');

	pg_assert_same(array('order', 9001), ws_events_record_of('order.return.requested', $payload), 'the record is the order');
	pg_assert_true(ws_events_rule_matches(array('type' => 'event', 'event' => 'order.return.requested'), 'order.return.requested', $payload), 'a rule with no filter matches');
	pg_assert_false(ws_events_rule_matches(array('type' => 'event', 'event' => 'order.created'), 'order.return.requested', $payload), 'another event\'s rule does not');
	pg_assert_false(ws_events_watched('order.return.requested'), 'not written into the channels that watch the order');
}

// The trigger is offered with the shop's events (only where the shop is on),
// about an order, to whoever may see orders, with no filter.
function test_returns_events_workspace_catalog_entry()
{
	$body = returns_events_test_body(returns_events_test_source('includes/workspace/watch.php'), 'ws_events_catalog');
	$shop = strpos($body, "if (defined('ECOMMERCE') && (ECOMMERCE === true)) {");
	$entry = strpos($body, "'order.return.requested' => array('label' => lang('A return is requested'), 'when' => lang('When a return is requested'), 'record' => 'order', 'right' => 'ecommerce', 'filter' => '')");
	$end = ($shop === false) ? false : strpos($body, "\n    }\n", $shop);

	pg_assert_true($shop !== false, 'the shop block is there');
	pg_assert_true($entry !== false, 'the entry is there');
	pg_assert_true(($entry !== false) && ($end !== false) && ($entry > $shop) && ($entry < $end), 'inside the shop block');
}

// requested is announced once the request is written and the lock is let go;
// approved and received by the move; refunded by the refund alone, after its
// lock, so a refusal that puts the return back announces nothing.
function test_returns_events_announce_points()
{
	$source = returns_events_test_source('includes/fn/returns.php');

	$create = returns_events_test_body($source, 'pg_return_create');
	$announce = strpos($create, "pg_return_announce('order.return.requested'");

	pg_assert_true($announce !== false, 'pg_return_create() announces the request');
	pg_assert_true(($announce !== false) && ($announce > strrpos($create, 'COMMIT')) && ($announce > strrpos($create, 'RELEASE_LOCK')), 'after the commit and the lock');
	pg_assert_true(($announce !== false) && ($announce > strrpos($create, "'repeat' => true")), 'never for the repeated form');

	$move = returns_events_test_body($source, 'pg_return_move');

	pg_assert_contains("array('approved', 'received')", $move, 'the move announces approved and received');
	pg_assert_contains("pg_return_announce('order.return.' . \$to", $move);

	$refund = returns_events_test_body($source, 'pg_return_refund');
	$announce = strpos($refund, "pg_return_announce('order.return.refunded'");

	pg_assert_true($announce !== false, 'pg_return_refund() announces the refund');
	pg_assert_same(1, substr_count($refund, 'pg_return_announce('), 'once');
	pg_assert_true(($announce !== false) && ($announce > strrpos($refund, '$release();')), 'after the lock is let go');
	pg_assert_true(($announce !== false) && ($announce > strpos($refund, "status = 'received', refunded_at = 0")), 'after the refusal has gone back');
	pg_assert_contains("'unclear'       => (\$unclear !== null)", $refund, 'an unclear answer is announced as such');
}

// What the audit trail keeps of a new return: the request, not the time
// stamps.
function test_returns_events_audit_fields_of_a_new_return()
{
	pg_assert_same(array(
		'status'        => 'requested',
		'reason'        => 'damaged',
		'customer_note' => 'Kutu ezik geldi',
		'source'        => 'customer',
		'ship_to_id'    => '9001',
		'items'         => array(9001 => '2', 9002 => '1'),
	), pg_return_audit_create_fields(returns_events_test_row()));

	pg_assert_same(array(
		'status' => '', 'reason' => '', 'customer_note' => '', 'source' => '', 'ship_to_id' => '0', 'items' => array(),
	), pg_return_audit_create_fields(null), 'nothing read');
}
