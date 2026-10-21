<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the invoice lines an order turns into, erp_order_lines()
 * (includes/erp/order_bridge.php): the rate each line carries, its tax, and
 * the order discount spread over the lines.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php');

// An order_items row as the bridge reads it (order_items.*, the product's
// name and rate). $rate is order_items.tax_rate; false leaves the column out,
// the way a row read before the column existed comes.
function pg_test_erp_order_item($price, $quantity, $tax_total, $rate, $product_rate = null)
{
	$item = array(
		'product_id' => 7,
		'product_name' => 'Item',
		'short_description' => '',
		'quantity' => $quantity,
		'price' => $price,
		'tax_total' => $tax_total,
		'product_tax_rate' => $product_rate,
	);

	if ($rate !== false) {
		$item['tax_rate'] = $rate;
	}

	return $item;
}

// The rate the checkout wrote is the line's rate, even where the line's tax
// is not its own rate x amount: 1.02 carrying 0.21 of a group's 0.41 is at
// 20%, though 21% would give 0.21 on its own.
function test_erp_order_lines_take_the_stored_rate()
{
	$order = array('discount' => 0, 'tax' => 41);
	$built = erp_order_lines($order, array(
		pg_test_erp_order_item(102, 1, 21, '20.000'),
		pg_test_erp_order_item(102, 1, 20, '20.000'),
	));

	pg_assert_same(array(20.0, 20.0), array($built['lines'][0]['tax_rate'], $built['lines'][1]['tax_rate']));
	pg_assert_same(array(21, 20), array($built['lines'][0]['tax_total'], $built['lines'][1]['tax_total']));
	pg_assert_same(245, $built['totals']['grand_total']);
}

// A line written before the column, or with it empty, has its rate worked
// back out of its amounts as before: the product's rate when it explains the
// tax, else the simplest one that does.
function test_erp_order_lines_work_the_rate_out_without_one()
{
	$order = array('discount' => 0, 'tax' => 361 + 20);
	$built = erp_order_lines($order, array(
		pg_test_erp_order_item(2008, 1, 361, false),
		pg_test_erp_order_item(102, 1, 20, null, '20.000'),
	));

	pg_assert_same(18.0, (float) $built['lines'][0]['tax_rate']);
	pg_assert_same(20.0, (float) $built['lines'][1]['tax_rate']);
}

// A zero rate written for an untaxed line stays zero.
function test_erp_order_lines_keep_a_zero_rate()
{
	$built = erp_order_lines(array('discount' => 0, 'tax' => 0), array(pg_test_erp_order_item(500, 2, 0, '0.000')));

	pg_assert_same(0.0, $built['lines'][0]['tax_rate']);
	pg_assert_same(1000, $built['totals']['grand_total']);
}
