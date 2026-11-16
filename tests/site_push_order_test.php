<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an order's moments sent as visitor notifications
 * (includes/fn/site_push_order.php): the text of a shipped and a delivered
 * notice, their limits, and an unknown moment refused.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_order.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function test_site_push_order_text_shipped_and_delivered()
{
	$shipped = pg_site_push_order_text('shipped', '1042', 'en');
	pg_assert_same('Your order has shipped', $shipped['title']);
	pg_assert_same('Order 1042 is on its way.', $shipped['body']);

	$delivered = pg_site_push_order_text('delivered', '1042', 'en');
	pg_assert_same('Your order has been delivered', $delivered['title']);
	pg_assert_same('Order 1042 has been delivered.', $delivered['body']);
}

function test_site_push_order_text_unknown_moment_is_null()
{
	pg_assert_same(null, pg_site_push_order_text('cancelled', '1042', 'en'));
	pg_assert_same(null, pg_site_push_order_text('', '1042', 'en'));
}

function test_site_push_order_text_fits_the_message_columns()
{
	$text = pg_site_push_order_text('shipped', str_repeat('9', 300), 'en');
	pg_assert_true(mb_strlen($text['title'], 'UTF-8') <= 60);
	pg_assert_true(mb_strlen($text['body'], 'UTF-8') <= 200);
}

function test_site_push_order_moment_bits()
{
	pg_assert_same(array(1, false), pg_site_push_order_moment_bit('shipped'), 'shipped sets bit 1');
	pg_assert_same(array(0, true), pg_site_push_order_moment_bit('delivered'), 'delivered is the last moment');
	pg_assert_same(null, pg_site_push_order_moment_bit('cancelled'));
}

function test_site_push_order_may_watch_own_orders_only()
{
	$order = array('id' => '12', 'user_id' => '7', 'status' => 'complete');

	pg_assert_true(pg_site_push_order_may_watch($order, 12, 0), 'the receipt of the order this session completed');
	pg_assert_true(pg_site_push_order_may_watch($order, 0, 7), 'the account\'s own order');
	pg_assert_false(pg_site_push_order_may_watch($order, 13, 0), 'another session\'s order');
	pg_assert_false(pg_site_push_order_may_watch($order, 0, 8), 'another account\'s order');
	pg_assert_false(pg_site_push_order_may_watch(array('user_id' => '0') + $order, 0, 0), 'a guest order, signed out, not this session\'s');
	pg_assert_false(pg_site_push_order_may_watch(array('status' => 'incomplete') + $order, 12, 7), 'a cart is no order');
	pg_assert_false(pg_site_push_order_may_watch(null, 12, 7), 'no order');
}

function test_site_push_watch_insert_keeps_the_first_row()
{
	list($sql, $params) = pg_site_push_watch_insert(3, array(41, 42), 9, 1000);

	pg_assert_contains('INSERT IGNORE INTO site_push_watch', $sql);
	pg_assert_same(2, substr_count($sql, '(?, ?, ?, 0, ?)'));
	pg_assert_same(array('3', '41', '9', '1000', '3', '42', '9', '1000'), $params);
}

function test_site_push_watch_take_sends_each_moment_once()
{
	list($sql, $params) = pg_site_push_watch_take_sql(3, 41, 9, 1, false);
	pg_assert_contains('SET moments = moments | ?', $sql);
	pg_assert_contains('(moments & ?) = 0', $sql, 'a moment already sent changes no row');
	pg_assert_same(array('1', '3', '41', '9', '1'), $params);

	list($sql, $params) = pg_site_push_watch_take_sql(3, 41, 9, 0, true);
	pg_assert_contains('DELETE FROM site_push_watch', $sql, 'the last moment takes the row');
	pg_assert_same(array('3', '41', '9'), $params);
}

function test_site_push_watch_wiring()
{
	pg_assert_contains('pg_site_push_watch_forget(', pg_test_function_source('pg_site_push_subscription_remove'), 'Stop forgets the waits');
	pg_assert_contains('pg_site_push_watch_forget(', pg_test_function_source('pg_site_push_queue_run'), 'a gone endpoint forgets the waits');
	pg_assert_contains('pg_site_push_watch_tidy(', pg_test_function_source('pg_site_push_queue_tidy'));
	pg_assert_contains('INNER JOIN site_push_subscription', pg_test_function_source('pg_site_push_watchers'), 'a wait without its browser reaches nobody');
	pg_assert_contains("pg_site_push_order_moment((int) \$recipient['order_id'], \$ship_to_id, 'delivered')", pg_test_function_source('pg_shipment_delivered'));

	$update = file_get_contents(PG_FUNCTIONS_DIR . '/update_order.php');
	pg_assert_contains("pg_site_push_order_moment((int) \$order['id'], (int) \$shipped_recipient['id'], 'shipped')", $update);

	$delete = file_get_contents(PG_FUNCTIONS_DIR . '/delete_order.php');
	pg_assert_true(strpos($delete, 'pg_site_push_order_drop(') < strpos($delete, 'DELETE FROM ship_tos'), 'the waits go before the addresses they are found by');

	pg_assert_contains("'site_push_watch',", file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'));
	pg_assert_contains('upgrade_2026_11_0_site_push_watch();', file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php'));
}
