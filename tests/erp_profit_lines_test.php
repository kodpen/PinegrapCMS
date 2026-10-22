<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lines the profit report counts for completed orders without
 * an invoice, erp_profit_order_lines() (includes/erp/profit.php): only the
 * lines the checkout charged, the way the invoice would carry them.
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

// The body of a top-level function of includes/erp/profit.php, or ''.
function erp_profit_lines_test_function_body($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/profit.php');

	if (!preg_match('/\nfunction ' . preg_quote($name, '/') . '\s*\([^\n]*\)\s*\{(.*?)\n\}/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

// The report's net for one order, summed as erp_profit_order_lines() sums it:
// line total less the line's share of the discount.
function erp_profit_lines_test_net($built)
{
	$net = 0;

	foreach ($built['lines'] as $line) {
		$net += (int) $line['line_total'] - (int) $line['discount_amount'];
	}

	return $net;
}

// The batched read selects the product's start, which the charged-line rule
// needs for a recurring line without a start date, and filters every order's
// rows with erp_order_charged_items() before building the lines; an order
// left with nothing charged is skipped.
function test_erp_profit_order_lines_count_only_the_charged_lines()
{
	$body = erp_profit_lines_test_function_body('erp_profit_order_lines');

	pg_assert_true($body !== '', 'erp_profit_order_lines() found');
	pg_assert_true(strpos($body, 'products.start AS product_start') !== false, 'the batched read selects the product start');

	$filter = strpos($body, '$order_items = erp_order_charged_items($order, $order_items);');
	$build = strpos($body, 'erp_order_lines($order, $order_items)');

	pg_assert_true($filter !== false, 'the order rows are filtered with erp_order_charged_items()');
	pg_assert_true(($filter !== false) && ($build !== false) && ($filter < $build), 'the filter runs before erp_order_lines()');

	$skip = ($filter !== false) ? strpos($body, 'continue;', $filter) : false;
	pg_assert_true(($skip !== false) && ($build !== false) && ($skip < $build), 'an order with nothing charged is skipped');
}

// The order of the report: a mug of 100.00 and a monthly box of 50.00 that
// starts in 14 days, both at 20%, with a 10.00 order discount; the order came
// to 108.00 with 18.00 VAT. Its net from the charged lines is 90.00, the
// report's own header figure (total + gift card - tax); from every line it
// was 140.00. An order with the box alone has nothing charged.
function test_erp_profit_order_lines_net_meets_the_header_figure()
{
	$order = array('order_date' => strtotime('2026-10-21'), 'discount' => 1000, 'tax' => 1800, 'total' => 10800, 'gift_card_discount' => 0);
	$items = array();

	foreach (array(array(10000, '', ''), array(5000, 'Monthly', '2026-11-04')) as $row) {
		$item = array(
			'product_id' => 7,
			'product_name' => 'Item',
			'short_description' => '',
			'quantity' => 1,
			'price' => $row[0],
			'tax_total' => erp_apply_rate($row[0], 20),
			'tax_rate' => '20.000',
			'product_tax_rate' => '20.000',
			'recurring_payment_period' => $row[1],
			'recurring_start_date' => $row[2],
			'product_start' => 0,
		);
		$items[] = $item;
	}

	$header = $order['total'] + $order['gift_card_discount'] - $order['tax'];

	pg_assert_same(14000, erp_profit_lines_test_net(erp_order_lines($order, $items)));
	pg_assert_same($header, erp_profit_lines_test_net(erp_order_lines($order, erp_order_charged_items($order, $items, false))));
	pg_assert_same(array(), erp_order_charged_items($order, array($items[1]), false));
}
