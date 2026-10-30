<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Order Report's figures in includes/fn/order_report.php: one
 * order's figures (a placed order's orders.* columns, a cart's active lines),
 * the group sums and the charge columns shown only when they hold money.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_report_test_row($values)
{
	return $values + array(
		'id' => '7', 'order_number' => '1007', 'order_date' => '1761800000', 'status' => 'complete',
		'subtotal' => '0', 'discount' => '0', 'tax' => '0', 'shipping' => '0',
		'gift_card_discount' => '0', 'surcharge' => '0', 'installment_charges' => '0', 'total' => '0',
	);
}

function order_report_test_adds_up($figures)
{
	return $figures['subtotal'] - $figures['discount'] + $figures['tax'] + $figures['shipping']
		- $figures['gift_card_discount'] + $figures['surcharge'] + $figures['installment_charges'];
}

function test_order_report_installment_order_adds_up_to_its_total()
{
	$order = pg_order_report_order(order_report_test_row(array(
		'subtotal' => '100000', 'tax' => '20000', 'installment_charges' => '3600', 'total' => '123600',
	)));

	pg_assert_same(3600, $order['installment_charges'], 'the installment charge is carried');
	pg_assert_same(123600, $order['total'], 'orders.total is read as is');
	pg_assert_same($order['total'], order_report_test_adds_up($order), 'the columns add up');
}

function test_order_report_gift_card_and_surcharge_add_up_to_the_total()
{
	$gift = pg_order_report_order(order_report_test_row(array(
		'subtotal' => '10000', 'tax' => '2000', 'shipping' => '1000', 'gift_card_discount' => '5000', 'total' => '8000',
	)));
	$card = pg_order_report_order(order_report_test_row(array(
		'subtotal' => '10000', 'discount' => '1000', 'tax' => '1800', 'surcharge' => '324', 'total' => '11124',
	)));

	pg_assert_same(8000, order_report_test_adds_up($gift), 'a gift card order adds up');
	pg_assert_same(11124, order_report_test_adds_up($card), 'a card surcharge order adds up');
}

function test_order_report_cart_reads_its_active_lines()
{
	$cart = pg_order_report_order(
		order_report_test_row(array('status' => 'incomplete', 'order_number' => '0', 'subtotal' => '0', 'total' => '0')),
		array('subtotal' => '10000', 'tax' => '2000', 'shipping' => '500')
	);

	pg_assert_same(10000, $cart['subtotal'], 'the cart subtotal comes from its lines');
	pg_assert_same(12500, $cart['total'], 'the cart total is its lines with tax and shipping');
	pg_assert_same(0, $cart['installment_charges'], 'a cart has no charge yet');
	pg_assert_same($cart['total'], order_report_test_adds_up($cart), 'the cart adds up');
}

function test_order_report_cart_without_active_lines_reads_zero()
{
	$cart = pg_order_report_order(order_report_test_row(array(
		'status' => 'incomplete', 'subtotal' => '5000', 'discount' => '500', 'total' => '4500',
	)));

	pg_assert_same(0, $cart['subtotal'], 'no active line, no subtotal');
	pg_assert_same(0, $cart['discount'], 'no active line, no discount');
	pg_assert_same(0, $cart['total'], 'no active line, no total');
}

function test_order_report_cart_discount_written_at_payment_is_taken_off()
{
	$cart = pg_order_report_order(
		order_report_test_row(array('status' => 'incomplete', 'discount' => '1000')),
		array('subtotal' => '10000', 'tax' => '1800', 'shipping' => '0')
	);

	pg_assert_same(1000, $cart['discount'], 'the discount is kept');
	pg_assert_same(10800, $cart['total'], 'the total takes the discount off');
}

function test_order_report_bucket_sums_orders()
{
	$bucket = pg_order_report_bucket('October');
	$bucket = pg_order_report_add($bucket, pg_order_report_order(order_report_test_row(array('subtotal' => '100', 'installment_charges' => '5', 'total' => '105'))));
	$bucket = pg_order_report_add($bucket, pg_order_report_order(order_report_test_row(array('subtotal' => '200', 'surcharge' => '4', 'total' => '204'))));

	pg_assert_same('October', $bucket['name'], 'the name is kept');
	pg_assert_same(2, $bucket['count'], 'two orders');
	pg_assert_same(300, $bucket['subtotal'], 'subtotals summed');
	pg_assert_same(5, $bucket['installment_charges'], 'charges summed');
	pg_assert_same(309, $bucket['total'], 'totals summed');
}

function test_order_report_charge_columns_only_when_non_zero()
{
	$grand = pg_order_report_bucket('');
	pg_assert_same(array(), pg_order_report_charge_columns($grand), 'no charge, no column');

	$grand['surcharge'] = 324;
	$grand['installment_charges'] = 3600;
	pg_assert_same(array('surcharge', 'installment_charges'), pg_order_report_charge_columns($grand), 'the charges that hold money, in column order');
}
