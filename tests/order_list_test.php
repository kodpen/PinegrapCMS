<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the amount column of the orders list (includes/fn/order_list.php):
 * an incomplete order shows its cart total, muted and titled; every other
 * order its charged total; the cart query sums only active lines of the ids
 * it was given. The CSV exports give each line its real tax, less its share of
 * the order discount so the lines add up to the order, and the order's
 * installment charge.
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

function test_order_item_line_tax_prefers_line_total()
{
	pg_assert_same(2000, pg_order_item_line_tax('2000', '0', '2'), 'a checkout line exports its tax_total, not the zeroed unit tax');
	pg_assert_same(2000, pg_order_item_line_tax(2000, 1000, 2), 'tax_total is never multiplied or added to');
}

function test_order_item_line_tax_falls_back_to_unit_tax()
{
	pg_assert_same(2000, pg_order_item_line_tax('0', '2000', '1'), 'a marketplace line exports its unit tax');
	pg_assert_same(6000, pg_order_item_line_tax(0, 2000, 3), 'the unit tax is taken for each unit, as the order tax was');
	pg_assert_same(0, pg_order_item_line_tax(null, null, null), 'a line with no tax exports zero');
}

// The order from the report: two lines at 20%, a 10% order discount; the
// lines' taxes come to orders.tax, not to their undiscounted tax_total.
function test_order_line_taxes_spread_the_discount()
{
	$lines = array(
		11 => array('amount' => 1499800, 'rate' => '20.000', 'tax' => 299960),
		12 => array('amount' => 2299800, 'rate' => '20.000', 'tax' => 459960),
	);
	$taxes = pg_order_line_charged_taxes($lines, 379960, 683928);

	pg_assert_same(array(11 => 269964, 12 => 413964), $taxes, 'each line less its share of the discount');
	pg_assert_same(683928, array_sum($taxes), 'the lines add up to orders.tax');
	pg_assert_same(pg_order_charged_tax($lines, 379960), array_sum($taxes), 'the same rule as the order tax');
}

function test_order_line_taxes_without_discount()
{
	$lines = array(
		1 => array('amount' => 1000, 'rate' => '20', 'tax' => 200),
		2 => array('amount' => 500, 'rate' => '10', 'tax' => 50),
	);
	pg_assert_same(array(1 => 200, 2 => 50), pg_order_line_charged_taxes($lines, 0, 250), 'no discount: the lines keep their tax');
	pg_assert_same(array(), pg_order_line_charged_taxes(array(), 100, 0), 'no lines');
}

function test_order_line_taxes_two_rates()
{
	$lines = array(
		1 => array('amount' => 10000, 'rate' => '20', 'tax' => 2000),
		2 => array('amount' => 10000, 'rate' => '10', 'tax' => 1000),
	);
	$taxes = pg_order_line_charged_taxes($lines, 2000, pg_order_charged_tax($lines, 2000));

	pg_assert_same(array(1 => 1800, 2 => 900), $taxes, 'each rate taken again on what the discount leaves');
}

function test_order_line_taxes_without_rate()
{
	// Lines placed before order_items.tax_rate: the order tax was lowered by
	// the discount's share of the subtotal; the lines share that out by tax.
	$lines = array(
		1 => array('amount' => 3000, 'rate' => null, 'tax' => 600),
		2 => array('amount' => 1000, 'rate' => null, 'tax' => 200),
	);
	$taxes = pg_order_line_charged_taxes($lines, 400, 720);

	pg_assert_same(array(1 => 540, 2 => 180), $taxes, 'the lowered tax by line tax');
	pg_assert_same(pg_order_charged_tax($lines, 400), array_sum($taxes), 'the same rule as the order tax');
}

function test_order_line_taxes_settle_rounding()
{
	$lines = array(
		1 => array('amount' => 1000, 'rate' => '20', 'tax' => 200),
		2 => array('amount' => 2000, 'rate' => '20', 'tax' => 400),
	);
	pg_assert_same(601, array_sum(pg_order_line_charged_taxes($lines, 0, 601)), 'a minor unit of rounding goes to the lines');
	pg_assert_same(600, array_sum(pg_order_line_charged_taxes($lines, 0, 900)), 'a wider gap is not papered over');
}

function test_orders_csv_exports_line_tax_and_installment_charges()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/view_orders.php');

	pg_assert_same(3, substr_count($source, 'pg_order_export_line_tax('), 'the zip row and both flat item rows use the line tax after the discount');
	pg_assert_same(2, substr_count($source, "'\"installment_charges\"';"), 'both orders files carry the installment charge heading');
	pg_assert_same(2, substr_count($source, "\$order['installment_charges'] / 100"), 'both orders files print the installment charge');
	pg_assert_contains('orders.installment_charges,', $source, 'the flat orders query selects the installment charge');
	pg_assert_true(strpos($source, 'order_items.tax_total AS tax') === false, 'the line total is not read back as the unit tax');
}
