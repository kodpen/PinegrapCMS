<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the amount column of the orders list (includes/fn/order_list.php):
 * an incomplete order shows its cart total, muted and titled; every other
 * order its charged total; the cart query sums only active lines of the ids
 * it was given.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_list_amount_incomplete_shows_cart_total()
{
	$html = pg_order_list_amount_html('incomplete', 0, 12550);

	pg_assert_contains(prepare_amount(125.5), $html, 'the cart total is shown in major units');
	pg_assert_contains('fst-italic', $html, 'the cart total is drawn apart from a charged total');
	pg_assert_contains('title="' . h(lang('Cart total')) . '"', $html, 'the cart total says what it is');
}

function test_order_list_amount_complete_keeps_order_total()
{
	pg_assert_same(prepare_amount(39.95), pg_order_list_amount_html('complete', 3995, 5000), 'a complete order shows orders.total');
	pg_assert_same(prepare_amount(0), pg_order_list_amount_html('cancelled', 0, 5000), 'a cancelled order keeps its own total');
}

function test_order_list_amount_incomplete_without_cart_lines()
{
	pg_assert_same(prepare_amount(0), pg_order_list_amount_html('incomplete', 0, null), 'an empty cart shows the plain zero');
	pg_assert_same(prepare_amount(20), pg_order_list_amount_html('incomplete', 2000, 5000), 'an incomplete order with a total keeps it');
}

function test_order_list_cart_totals_sql_ids()
{
	pg_assert_same('', pg_order_list_cart_totals_sql(array()), 'no ids, no query');
	pg_assert_same('', pg_order_list_cart_totals_sql(array('x', 0, -3)), 'only positive ids count');

	$sql = pg_order_list_cart_totals_sql(array('7', 7, '12; DROP TABLE orders', 3), ' AND (order_items.saved_for_later = 0)');

	pg_assert_contains('IN (7, 12, 3)', $sql, 'ids are cast and kept once');
	pg_assert_contains('CAST(order_items.quantity AS signed)', $sql, 'the unsigned quantity is cast before the product');
	pg_assert_contains('saved_for_later = 0', $sql, 'lines saved for later stay out');
	pg_assert_contains('GROUP BY order_items.order_id', $sql, 'one row per order');
}
