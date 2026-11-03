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

function test_local_sale_cash_short()
{
	pg_assert_same(400, local_sale_cash_short('cash', 200, 600), 'received less than the total');
	pg_assert_same(0, local_sale_cash_short('cash', 600, 600), 'exact');
	pg_assert_same(0, local_sale_cash_short('cash', 1000, 600), 'change due');
	pg_assert_same(0, local_sale_cash_short('cash', 0, 600), 'empty box: the whole total');
	pg_assert_same(0, local_sale_cash_short('card', 200, 600), 'not cash');
	pg_assert_same(0, local_sale_cash_short('cash', -5, 600), 'a minus reads as nothing');
}

function test_local_sale_add_qty_error()
{
	pg_assert_same('', local_sale_add_qty_error(0, 9999), 'a new line of 9999');
	pg_assert_same('', local_sale_add_qty_error(9998, 1), 'up to the limit');
	pg_assert_contains('9999', local_sale_add_qty_error(1, 9999), 'past the limit');
	pg_assert_contains('9999', local_sale_add_qty_error(9999, 1), 'a full line');
}

function test_local_sale_add_item_reads_the_quantity_strictly()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/local_sale.php');
	pg_assert_same(0, substr_count($source, 'max(1, min(9999, (int) $quantity))'), 'no silent clamp');
	pg_assert_contains('$quantity = local_sale_qty_input($quantity);', $source, 'add_item reads the typed quantity');
	pg_assert_contains('local_sale_cash_short($pay_method_in, (int) ($input[\'received\'] ?? 0), (int) local_sale_totals($lines)[\'gross\'])', $source, 'complete refuses a short cash payment');

	$page = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/add_order.php');
	pg_assert_contains("local_sale_add_item((string) (\$_POST['barcode'] ?? ''), (int) (\$_POST['product_id'] ?? 0), \$_POST['quantity'] ?? '1')", $page, 'raw quantity');

	$script = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/add_order.js');
	pg_assert_contains('function cashShort()', $script, 'the screen checks before sending');
	pg_assert_contains('/^\d{1,4}$/.test(raw)', $script, 'next product quantity read strictly');
}
