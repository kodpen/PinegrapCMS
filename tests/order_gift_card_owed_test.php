<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the gift card part a cancelled order owes back: what the cards
 * really gave (applied_gift_cards.amount) less what a return gave back of it
 * (order_refunds of refund_type 'return_gift_card'), never below 0, never
 * orders.gift_card_discount (pg_order_gift_card_owed_amount(),
 * pg_order_gift_card_owed_sql(), pg_order_cancel_refund_owed() in
 * includes/fn/ecommerce.php), and the ERP invoice settling only what the
 * cards gave (includes/erp/order_bridge.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_gift_card_owed_row($gift_card_discount, $gift_card_owed)
{
	return array(
		'id'                 => 1042,
		'payment_method'     => 'Credit/Debit Card',
		'paid_at'            => 1790000000,
		'total'              => 0,
		'refunded_amount'    => 0,
		'gift_card_discount' => $gift_card_discount,
		'gift_card_owed'     => $gift_card_owed,
	);
}

// A card spent by another order at the same moment was not redeemed: its row
// is gone, the order asked 50.00 of it and owes nothing back.
function test_order_gift_card_owed_failed_card()
{
	pg_assert_same(0, pg_order_gift_card_owed_amount(0, 0), 'no card gave anything');
	pg_assert_same(
		array('payment' => 0, 'gift_card' => 0, 'total' => 0),
		pg_order_cancel_refund_owed(order_gift_card_owed_row(5000, 0)),
		'the written discount of a card that failed is not owed');

	$half = order_gift_card_owed_row(5000, pg_order_gift_card_owed_amount(2000, 0));
	pg_assert_same(2000, pg_order_cancel_refund_owed($half)['gift_card'], 'one of two cards redeemed: only what it gave');
}

// A return that gave back part of the gift card money leaves the rest.
function test_order_gift_card_owed_part_refund()
{
	pg_assert_same(3500, pg_order_gift_card_owed_amount(5000, 1500), 'redeemed less returned');
	pg_assert_same(3500, pg_order_gift_card_owed_amount('5000', '1500'), 'database strings');

	$row = order_gift_card_owed_row(5000, pg_order_gift_card_owed_amount(5000, 1500));
	$row['total'] = 10000;
	pg_assert_same(array('payment' => 10000, 'gift_card' => 3500, 'total' => 13500), pg_order_cancel_refund_owed($row), 'payment and the gift card rest');
}

function test_order_gift_card_owed_floor()
{
	pg_assert_same(0, pg_order_gift_card_owed_amount(5000, 7000), 'never below 0');
	pg_assert_same(0, pg_order_cancel_refund_owed(order_gift_card_owed_row(5000, -2000))['gift_card'], 'a negative figure reads as 0');
}

// The SQL twin: both correlated terms, the floor, never the written discount.
function test_order_gift_card_owed_sql()
{
	$sql = pg_order_gift_card_owed_sql('orders.id', true);
	pg_assert_contains(pg_order_gift_card_redeemed_sql('orders.id'), $sql, 'the redeemed term');
	pg_assert_contains('SUM(applied_gift_cards.amount)', $sql, 'what the cards gave');
	pg_assert_contains('applied_gift_cards.order_id = orders.id', $sql, 'correlated to the order');
	pg_assert_contains('SUM(order_refunds.amount_cents)', $sql, 'what a return gave back');
	pg_assert_contains("order_refunds.refund_type = 'return_gift_card'", $sql, 'only the gift card returns');
	pg_assert_contains('order_refunds.order_id = orders.id', $sql, 'correlated to the order');
	pg_assert_contains('GREATEST(0,', $sql, 'the floor at 0');
	pg_assert_false(strpos($sql, 'gift_card_discount') !== false, 'never the written discount');

	$bare = pg_order_gift_card_owed_sql('orders.id', false);
	pg_assert_false(strpos($bare, 'order_refunds') !== false, 'no order_refunds table, no term on it');

	$owed = pg_order_cancel_refund_owed_sql(true, true);
	pg_assert_contains($sql, $owed, 'the cancel sum reads the same figure');
	pg_assert_false(strpos($owed, 'gift_card_discount') !== false, 'the cancel sum never reads the written discount');
	pg_assert_contains(pg_order_gift_card_owed_sql('orders.id', false), pg_order_cancel_refund_owed_sql(false, false), 'an old schema still reads the redeemed sum');
}

// The ERP invoice settles the first term alone, read as the shared helper
// builds it, and erp_order_lines() takes it from that figure.
function test_order_gift_card_owed_erp_bridge()
{
	$bridge = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/erp/order_bridge.php');
	pg_assert_contains("\$order['gift_card_redeemed'] = (int) db_value(\"SELECT \" . pg_order_gift_card_redeemed_sql(", $bridge, 'the invoice reads the redeemed sum');
	pg_assert_contains("'gift_card_total' => max(0, (int) round((float) (\$order['gift_card_redeemed'] ?? 0)))", $bridge, 'the settled figure is the redeemed sum');
	pg_assert_false(strpos($bridge, "'gift_card_total' => (int) (\$order['gift_card_discount']") !== false, 'not the written discount');

	$built = erp_order_lines(array('discount' => 0, 'tax' => 0, 'gift_card_discount' => 5000, 'gift_card_redeemed' => '2000'), array());
	pg_assert_same(2000, $built['totals']['gift_card_total'], 'what the cards gave is settled');
	$built = erp_order_lines(array('discount' => 0, 'tax' => 0, 'gift_card_discount' => 5000), array());
	pg_assert_same(0, $built['totals']['gift_card_total'], 'no redeemed figure settles nothing');
}

function test_order_gift_card_owed_screens()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	pg_assert_false(strpos(file_get_contents($root . 'view_order.php'), '$gift_card_discount_cents') !== false, 'the order screen shows the owed gift card part');
	pg_assert_contains('AS gift_card_owed', file_get_contents($root . 'includes/dashboard/widgets/widget_9.php'), 'the refund lane selects the figure');
}
