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

function test_order_report_first_row_opens_a_group_even_when_its_name_is_empty()
{
	pg_assert_same(true, pg_order_report_new_group('', null), 'the first row opens a group with no name');
	pg_assert_same(true, pg_order_report_new_group('Ankara', null), 'the first row opens a named group');
	pg_assert_same(false, pg_order_report_new_group('', ''), 'a second empty name stays in the group');
	pg_assert_same(false, pg_order_report_new_group('ANKARA', 'ankara'), 'names compare without case');
	pg_assert_same(true, pg_order_report_new_group('Bursa', 'Ankara'), 'a new name opens a group');
}

function test_order_report_new_report_leaves_out_carts_and_cancelled_orders()
{
	$filters = pg_order_report_default_filters();

	pg_assert_same(2, count($filters), 'two default filters');
	pg_assert_same(array('field' => 'order_status', 'operator' => 'is not equal to', 'value' => 'incomplete'), $filters[0], 'carts are left out');
	pg_assert_same(array('field' => 'order_status', 'operator' => 'is not equal to', 'value' => 'cancelled'), $filters[1], 'cancelled orders are left out');
}

function test_order_report_days_ago_takes_only_a_whole_number()
{
	pg_assert_same(true, pg_order_report_days_ago_valid('5'), 'a whole number');
	pg_assert_same(true, pg_order_report_days_ago_valid('0'), 'zero is today');
	pg_assert_same(true, pg_order_report_days_ago_valid(''), 'empty is today');
	pg_assert_same(false, pg_order_report_days_ago_valid('-5'), 'a negative number');
	pg_assert_same(false, pg_order_report_days_ago_valid('abc'), 'not a number');
	pg_assert_same(false, pg_order_report_days_ago_valid('2.5'), 'not a whole number');
	pg_assert_same(false, pg_order_report_days_ago_valid('99999999999'), 'more than the column holds');
}

function test_order_report_cart_shipping_is_its_recipients_cost()
{
	// 3 units at 333 per unit (round(1000 / 3)) plus a 500 base rate,
	// 20% off by a shipping offer: the recipient was charged 1200.
	$carts = pg_order_report_carts(
		array(array('id' => '9', 'subtotal' => '3000', 'tax' => '600')),
		array(array('id' => '9', 'shipping' => '1200'))
	);
	$cart = pg_order_report_order(
		order_report_test_row(array('id' => '9', 'status' => 'incomplete', 'order_number' => '0')),
		$carts['9'] ?? null
	);

	pg_assert_same(1200, $cart['shipping'], 'the cart shipping is the recipients\' cost');
	pg_assert_same(4800, $cart['total'], 'the cart total carries that shipping');
}

function test_order_report_cart_without_recipient_cost_or_lines()
{
	$carts = pg_order_report_carts(
		array(array('id' => '4', 'subtotal' => '2000', 'tax' => '400')),
		array(array('id' => '5', 'shipping' => '700'))
	);

	pg_assert_same(0, (int) $carts['4']['shipping'], 'no shipping step yet, no shipping');
	pg_assert_same(false, isset($carts['5']), 'a cart with no active line is left out');
}
