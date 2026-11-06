<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the webhook events a flow can start on as well: a review, the
 * moves of a return, cart quotes, a marketplace order, subscriptions, a paid
 * form and a stock change reach the Workspace (pg_event_workspace_events()),
 * name their record (ws_events_record_of()) and give the start box values
 * (ws_flow_event_outputs()).
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

function workspace_webhook_start_events_list()
{
	return array(
		'product.review.created',
		'order.return.approved', 'order.return.received', 'order.return.refunded',
		'cart_quote.created', 'cart_quote.ordered',
		'marketplace.order.imported',
		'subscription.failed', 'subscription.renewed', 'subscription.cancelled',
		'form.paid',
		'inventory.changed',
	);
}

function test_workspace_webhook_start_events_reach_the_workspace()
{
	$outputs = ws_flow_event_outputs();

	foreach (workspace_webhook_start_events_list() as $event) {
		pg_assert_true(in_array($event, pg_event_workspace_events(), true), $event . ' is kept for the Workspace');
		pg_assert_true(isset($outputs[$event]), $event . ' gives the start box its values');
	}
}

function test_workspace_webhook_start_events_name_their_record()
{
	pg_assert_same(array('product', 12), ws_events_record_of('product.review.created', array('id' => 5, 'item_type' => 'product', 'item_id' => 12)));
	pg_assert_same(array('product_group', 7), ws_events_record_of('product.review.created', array('id' => 5, 'item_type' => 'product_group', 'item_id' => 7)));
	pg_assert_same(array('', 0), ws_events_record_of('product.review.created', array('id' => 5, 'item_type' => 'page', 'item_id' => 3)), 'a comment on a page is no shop record');
	pg_assert_same(array('order', 40), ws_events_record_of('order.return.refunded', array('id' => 40, 'return_id' => 2)));
	pg_assert_same(array('cart_quote', 9), ws_events_record_of('cart_quote.ordered', array('id' => 9, 'order_id' => 41)));
	pg_assert_same(array('order', 42), ws_events_record_of('marketplace.order.imported', array('id' => 42, 'provider' => 'trendyol')));
	pg_assert_same(array('order', 43), ws_events_record_of('subscription.renewed', array('id' => 3, 'customer_id' => 8, 'order_id' => 43)));
	pg_assert_same(array('contact', 8), ws_events_record_of('subscription.failed', array('id' => 3, 'customer_id' => 8, 'order_id' => null)));
	pg_assert_same(array('', 0), ws_events_record_of('subscription.cancelled', array('id' => 3, 'customer_id' => null, 'order_id' => null)));
	pg_assert_same(array('form', 15), ws_events_record_of('form.paid', array('id' => 15, 'form_id' => 4)));
	pg_assert_same(array('product', 21), ws_events_record_of('inventory.changed', array('product_id' => 21, 'quantity' => 3)));
}

function test_workspace_webhook_start_events_outputs_are_payload_keys()
{
	$keys = function ($event) {
		return array_column(ws_flow_event_outputs()[$event] ?? array(), 'key');
	};

	pg_assert_same(array('item_id', 'rating', 'status'), $keys('product.review.created'));
	pg_assert_same(array('order_number', 'return_id', 'reason'), $keys('order.return.approved'));
	pg_assert_same(array('order_number', 'return_id', 'reason', 'refund_method'), $keys('order.return.refunded'));
	pg_assert_same(array('status', 'contact_id', 'order_number'), $keys('cart_quote.ordered'));
	pg_assert_same(array('order_number', 'provider', 'remote_order_id'), $keys('marketplace.order.imported'));
	pg_assert_same(array('customer_id', 'product_id', 'status', 'previous_status', 'order_id', 'retry_count'), $keys('subscription.failed'));
	pg_assert_same(array('reference_code', 'contact_id', 'order_number'), $keys('form.paid'));
	pg_assert_same(array('product_id', 'quantity'), $keys('inventory.changed'));
}

function test_workspace_webhook_start_events_kept_on_demand()
{
	pg_assert_same(array('inventory.changed'), pg_event_workspace_on_demand(), 'a stock change on every sale is kept only while a flow waits for it');
	pg_assert_true(!pg_event_workspace_awaited('inventory.changed', array()), 'no active rule names it');
	pg_assert_true(pg_event_workspace_awaited('inventory.changed', array('[{"type":"event","event":"inventory.changed"}]')));
	pg_assert_true(!pg_event_workspace_awaited('inventory.changed', array('[{"type":"event","event":"stock.low"}]')));
	pg_assert_true(pg_event_workspace_awaited('order.created', array()), 'the others are always kept');
}

// api_webhook_enqueue() keeps the event for the Workspace itself (unless
// pg_announce() already did), so the API's stock write must not record it a
// second time: each waiting flow would start twice.
function test_workspace_webhook_start_events_api_stock_recorded_once()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/inventory.php');

	pg_assert_contains("api_webhook_enqueue('inventory.changed', \$stock_event);", $source);
	pg_assert_contains("api_webhook_enqueue('stock.low', \$low_event);", $source);
	pg_assert_false(strpos($source, "pg_event_record('inventory.changed'") !== false, 'inventory.changed is kept by api_webhook_enqueue() alone');
	pg_assert_false(strpos($source, "pg_event_record('stock.low'") !== false, 'stock.low is kept by api_webhook_enqueue() alone');
}
