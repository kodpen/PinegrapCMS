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

// An order_items row with its recurring columns, as the bridge reads it with
// the product's start (product_start).
function pg_test_erp_order_recurring_item($price, $period, $start_date, $product_start = 0)
{
	$item = pg_test_erp_order_item($price, 1, erp_apply_rate($price, 20), '20.000');
	$item['recurring_payment_period'] = $period;
	$item['recurring_start_date'] = $start_date;
	$item['product_start'] = $product_start;

	return $item;
}

// The checkout charges every line but a recurring one that starts after the
// order day (submit_order.php, pg_order_line_charged_today()), so those are
// the lines the invoice carries. The day is the order's, not today's; a line
// without a start date starts the product's start days after the order day,
// as at checkout. The rows that stay are indexed from 0 again, as
// erp_order_lines() and the refund dialog count them.
function test_erp_order_charged_items_leave_out_a_recurring_line_that_starts_later()
{
	$order = array('order_date' => strtotime('2026-10-21 14:30:00'));
	$items = array(
		pg_test_erp_order_recurring_item(10000, '', ''),
		pg_test_erp_order_recurring_item(5000, 'Monthly', '2026-11-04'),
		pg_test_erp_order_recurring_item(700, 'Monthly', '2026-10-21'),
		pg_test_erp_order_recurring_item(800, 'Monthly', '0000-00-00', 14),
		pg_test_erp_order_recurring_item(900, 'Monthly', '', 0),
	);

	$charged = erp_order_charged_items($order, $items, false);

	pg_assert_same(array(0, 1, 2), array_keys($charged));
	pg_assert_same(array(10000, 700, 900), array_column($charged, 'price'));
}

// On the ClearCommerce gateway no recurring line is charged with the order:
// the gateway bills them itself.
function test_erp_order_charged_items_leave_out_every_recurring_line_on_clearcommerce()
{
	$order = array('order_date' => strtotime('2026-10-21'));
	$items = array(
		pg_test_erp_order_recurring_item(10000, '', ''),
		pg_test_erp_order_recurring_item(700, 'Monthly', '2026-10-21'),
	);

	pg_assert_same(array(10000), array_column(erp_order_charged_items($order, $items, true), 'price'));
	pg_assert_same(array(10000, 700), array_column(erp_order_charged_items($order, $items, false), 'price'));
}

// The order of the report: a mug of 100.00 and a monthly box of 50.00 that
// starts in 14 days, both at 20%, with a 10.00 order discount. The checkout
// charged the mug alone: 100.00 - 10.00 + 18.00 = 108.00. Built from every line the
// invoice came to more and was refused; built from the charged lines it
// meets the order, and the discount lands on the line that earned it.
function test_erp_order_lines_of_the_charged_lines_meet_the_order()
{
	$order = array('order_date' => strtotime('2026-10-21'), 'discount' => 1000, 'tax' => 1800, 'total' => 10800);
	$items = array(
		pg_test_erp_order_recurring_item(10000, '', ''),
		pg_test_erp_order_recurring_item(5000, 'Monthly', '2026-11-04'),
	);

	$every = erp_order_lines($order, $items);
	pg_assert_same(16800, $every['totals']['grand_total']);

	$built = erp_order_lines($order, erp_order_charged_items($order, $items, false));
	pg_assert_same(1, count($built['lines']));
	pg_assert_same(1000, $built['lines'][0]['discount_amount']);
	pg_assert_same(1800, $built['lines'][0]['tax_total']);
	pg_assert_same(10800, $built['totals']['grand_total']);
}

// A discount re-takes the tax once per rate on the discounted lines and
// spreads it back: 1.03 and 2.03 at 20% less 0.03 come to 1.02 and 2.01,
// 0.61 of VAT on 3.03, 0.21 and 0.40 - not 0.20 and 0.40 with the order's
// kurus put on the larger line afterwards.
function test_erp_order_lines_take_a_discounted_rate_once()
{
	$order = array('discount' => 3, 'tax' => 61);
	$built = erp_order_lines($order, array(
		pg_test_erp_order_item(103, 1, 21, '20.000'),
		pg_test_erp_order_item(203, 1, 40, '20.000'),
	));

	pg_assert_same(array(1, 2), array($built['lines'][0]['discount_amount'], $built['lines'][1]['discount_amount']));
	pg_assert_same(array(21, 40), array($built['lines'][0]['tax_total'], $built['lines'][1]['tax_total']));
	pg_assert_same(306 - 3 + 61, $built['totals']['grand_total']);
}
