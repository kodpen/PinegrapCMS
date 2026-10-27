<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order's last-change stamp (pg_order_touch_sql(),
 * includes/fn/order_status.php): every writer that changes what
 * GET /orders/{id} shows moves orders.last_modified_timestamp, so
 * GET /orders?modified_since= finds the order again. The writers run
 * queries, so their source is read: each UPDATE of the orders table in them
 * sets the stamp before its WHERE.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The UPDATE statements of the orders table in a piece of code, each from
// "UPDATE orders" to its WHERE.
function order_touch_test_updates($code)
{
	preg_match_all('/UPDATE orders\b(.*?)\bWHERE\b/s', $code, $matches);

	return $matches[1];
}

function order_touch_test_assert_touched($code, $label, $expected_count)
{
	$updates = order_touch_test_updates($code);

	pg_assert_same($expected_count, count($updates), $label . ': the orders UPDATEs found');

	foreach ($updates as $n => $update) {
		pg_assert_true(strpos($update, 'pg_order_touch_sql()') !== false, $label . ': UPDATE ' . ($n + 1) . ' moves the stamp');
	}
}

function test_order_touch_sql()
{
	pg_assert_same('last_modified_timestamp = UNIX_TIMESTAMP()', pg_order_touch_sql(), 'the stamp takes the database\'s clock');
}

function test_order_touch_cancellation_and_refund_outcome()
{
	// The cancellation itself and its refund outcome.
	order_touch_test_assert_touched(pg_test_function_source('process_order_cancellation'), 'cancellation', 2);
}

function test_order_touch_restore()
{
	order_touch_test_assert_touched(pg_test_function_source('pg_order_restore_cancelled'), 'restore', 3);
}

function test_order_touch_api_status_and_notes()
{
	order_touch_test_assert_touched(pg_test_function_source('api_orders_update'), 'API status and notes', 1);
}

function test_order_touch_workspace_status_and_fields()
{
	order_touch_test_assert_touched(pg_test_function_source('ws_change_write_order'), 'Workspace order writer', 2);
}

function test_order_touch_list_status_cell()
{
	order_touch_test_assert_touched(pg_test_function_source('pg_order_quick_update'), 'order list status cell', 1);
}

function test_order_touch_operator_refund_mark_and_exports()
{
	// Screens and the ShipWorks feed are scripts, read whole.
	foreach (array('view_order.php' => 2, 'view_orders.php' => 2, 'shipworks.php' => 1) as $file => $count) {
		order_touch_test_assert_touched(file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file, $count);
	}
}
