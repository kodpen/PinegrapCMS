<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the counter sale's typed line quantity (local_sale_qty_input(),
 * includes/local_sale.php) and source pins for the counter cart's own
 * session key and what a completed sale writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/pinegrap/includes/local_sale.php');

function test_local_sale_qty_input_whole_numbers()
{
	pg_assert_same(0, local_sale_qty_input('0'), 'zero removes');
	pg_assert_same(12, local_sale_qty_input(' 12 '), 'trimmed');
	pg_assert_same(9999, local_sale_qty_input('9999'), 'upper bound');
	pg_assert_same(3, local_sale_qty_input(3), 'int');
}

function test_local_sale_qty_input_refused()
{
	foreach (array('', 'abc', '-3', '12,50', '1.5', '99999', null, -1) as $raw) {
		pg_assert_same(null, local_sale_qty_input($raw), var_export($raw, true));
	}
}

function test_local_sale_cart_has_its_own_session_key()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/local_sale.php');
	pg_assert_contains("\$_SESSION['ecommerce']['local_sale_order_id'] ?? 0", $source, 'local_sale_order_id()');
	pg_assert_contains("local_sale_with_cart(function () use (\$product, \$quantity) {", $source, 'add item through the swap');
	pg_assert_contains("order_date = '\" . \$now . \"',", $source, 'completion dates the order');
	pg_assert_contains("DELETE FROM ship_tos WHERE order_id = '\" . \$order_id . \"'", $source, 'no empty recipient');
}
