<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the site log lines of a cancelled order (the gift cards that paid
 * for it, pg_order_gift_card_refund_line(); the order number, status and money
 * text the lines use) and the money a cancelled order still owes
 * (pg_order_cancel_refund_owed()), in includes/fn/ecommerce.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_cancel_gift_cards_none()
{
	pg_assert_same('', pg_order_gift_card_refund_line('1187', array()), 'no gift card');
	pg_assert_same('', pg_order_gift_card_refund_line('1187', array(
		array('code' => 'ABCD1234EFGH5678', 'gift_card_id' => '3', 'amount' => '0', 'givex' => '0'))), 'a card that paid nothing');
}

function test_order_cancel_gift_cards_line()
{
	$line = pg_order_gift_card_refund_line('1187', array(
		array('code' => 'ABCD1234EFGH5678', 'gift_card_id' => '3', 'amount' => '1250', 'givex' => '0'),
		array('code' => '6035711234567890123', 'gift_card_id' => '0', 'amount' => '500000', 'givex' => '1')));

	pg_assert_contains('REFUND ACTION REQUIRED — order #1187 was cancelled.', $line, 'the order number, not the row id');
	pg_assert_contains('Gift cards paid ' . pg_order_log_money(501250) . ' of it:', $line, 'the total of the cards as money text');
	pg_assert_contains('************5678 (#3) ' . pg_order_log_money(1250), $line, 'the shop card, masked, with its id');
	pg_assert_contains('603571*****6789012* (Givex) ' . pg_order_log_money(500000), $line, 'the Givex card, masked');
	pg_assert_false(strpos($line, 'ABCD1234EFGH5678') !== false, 'no full code in the log');
	pg_assert_false(strpos($line, '&#') !== false, 'no HTML entity in the log');
}

function test_order_log_number_and_money()
{
	pg_assert_same('1187', pg_order_log_number(array('id' => '459', 'order_number' => '1187')), 'the order number');
	pg_assert_same('459', pg_order_log_number(array('id' => '459', 'order_number' => '')), 'a cart with no number: its id');
	pg_assert_same('459', pg_order_log_number(array('id' => 459, 'order_number' => '0')), 'a zero number is no number');

	pg_assert_same(pg_format_money(5636.4, pg_order_log_currency_symbol()), pg_order_log_money(563640), 'plain money text');
	pg_assert_false(strpos(pg_order_log_money(563640), '&') !== false, 'no HTML entity');
	pg_assert_same(lang('Complete'), pg_order_log_status('complete'), 'complete in the panel language');
	pg_assert_same(lang('Exported'), pg_order_log_status('exported'), 'exported in the panel language');
}

// What a cancelled order still owes: the payment money only when a payment
// arrived, the gift card part always.
function test_order_cancel_refund_owed()
{
	$offline_unpaid = array('payment_method' => 'Offline Payment', 'paid_at' => '0', 'total' => '1364830', 'refunded_amount' => '0', 'gift_card_discount' => '15050');
	pg_assert_same(array('payment' => 0, 'gift_card' => 15050, 'total' => 15050), pg_order_cancel_refund_owed($offline_unpaid), 'a transfer that never arrived owes only the gift card part');

	$offline_paid = array_merge($offline_unpaid, array('paid_at' => '1761900000'));
	pg_assert_same(array('payment' => 1364830, 'gift_card' => 15050, 'total' => 1379880), pg_order_cancel_refund_owed($offline_paid), 'an arrived transfer is owed back');

	$pending = array('payment_method' => 'PayPal Checkout', 'paid_at' => 0, 'total' => 5000, 'refunded_amount' => 0, 'gift_card_discount' => 0);
	pg_assert_same(0, pg_order_cancel_refund_owed($pending)['total'], 'a payment its service left pending is not owed');

	$card = array('payment_method' => 'Credit/Debit Card', 'paid_at' => 0, 'total' => 10000, 'refunded_amount' => 2500, 'gift_card_discount' => 0);
	pg_assert_same(7500, pg_order_cancel_refund_owed($card)['payment'], 'a card payment less what was refunded');

	$old_schema = array('payment_method' => 'Offline Payment', 'total' => 10000, 'gift_card_discount' => 0);
	pg_assert_same(10000, pg_order_cancel_refund_owed($old_schema)['payment'], 'no paid_at column: the payment counts as arrived');

	$over = array('payment_method' => 'Credit/Debit Card', 'paid_at' => 1, 'total' => 1000, 'refunded_amount' => 1500, 'gift_card_discount' => 0);
	pg_assert_same(0, pg_order_cancel_refund_owed($over)['payment'], 'never below zero');
}

function test_order_cancel_refund_owed_sql()
{
	$sql = pg_order_cancel_refund_owed_sql(true, true);
	pg_assert_contains("orders.paid_at = 0", $sql, 'the unpaid test');
	pg_assert_contains("'Offline Payment'", $sql, 'the offline method');
	pg_assert_contains("'PayPal Checkout'", $sql, 'the pending method');
	pg_assert_contains('orders.refunded_amount', $sql, 'what was refunded');
	pg_assert_contains('orders.gift_card_discount', $sql, 'the gift card part');

	$bare = pg_order_cancel_refund_owed_sql(false, false);
	pg_assert_false(strpos($bare, 'paid_at') !== false, 'no paid_at column, no test on it');
	pg_assert_false(strpos($bare, 'refunded_amount') !== false, 'no refunded_amount column, no use of it');
}

function test_order_cancel_refund_owed_used()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	foreach (array('view_order.php', 'view_orders.php', 'cancel_order.php', 'includes/dashboard/widgets/widget_9.php') as $file) {
		pg_assert_contains('pg_order_cancel_refund_owed', file_get_contents($root . $file), $file . ' asks the shared helper');
	}
	pg_assert_false(strpos(file_get_contents($root . 'view_orders.php'), 'COALESCE(SUM(total), 0)') !== false, 'no plain SUM(total) for the refund card');
}

// The two note templates the cancellation writes, as tr.json has them.
function pg_test_order_cancel_gift_card_templates_tr()
{
	return array(array(
		'dated' => lang(array('string' => 'This gift card was automatically expired because order #{var:1} was cancelled. Its expiration date was {var:2}.', 'language' => 'tr', 'if_known' => true)),
		'none'  => lang(array('string' => 'This gift card was automatically expired because order #{var:1} was cancelled. It had no expiration date.', 'language' => 'tr', 'if_known' => true)),
	));
}

function test_order_cancel_gift_card_expiration_before()
{
	$en = "Bought for a colleague.\nThis gift card was automatically expired because order #1042 was cancelled. Its expiration date was 2027-01-31.";
	pg_assert_same('2027-01-31', pg_gift_card_expiration_before_cancel($en, '1042'), 'the date the card had');

	$tr = "Bu hediye kartı, #1042 numaralı sipariş iptal edildiği için otomatik olarak geçersiz kılındı. Son kullanma tarihi 2027-01-31 idi.";
	pg_assert_same('2027-01-31', pg_gift_card_expiration_before_cancel($tr, 1042, pg_test_order_cancel_gift_card_templates_tr()), 'the line in Turkish, templates given');
	pg_assert_same('2027-01-31', pg_gift_card_expiration_before_cancel($tr, 1042), 'the line in Turkish, default templates');
	pg_assert_same(null, pg_gift_card_expiration_before_cancel($en, '1042', pg_test_order_cancel_gift_card_templates_tr()), 'only the given templates count');

	$none = "This gift card was automatically expired because order #1042 was cancelled. It had no expiration date.";
	pg_assert_same('0000-00-00', pg_gift_card_expiration_before_cancel($none, '1042'), 'a card without a date');

	$tr_none = "Bu hediye kartı, #1042 numaralı sipariş iptal edildiği için otomatik olarak geçersiz kılındı. Son kullanma tarihi yoktu.";
	pg_assert_same('0000-00-00', pg_gift_card_expiration_before_cancel($tr_none, '1042', pg_test_order_cancel_gift_card_templates_tr()), 'a card without a date, in Turkish');
}

function test_order_cancel_gift_card_expiration_before_other_lines()
{
	$old = "This gift card was automatically expired because the associated order was cancelled.";
	pg_assert_same(null, pg_gift_card_expiration_before_cancel($old, '1042'), 'a note written before the date was kept');
	pg_assert_same(null, pg_gift_card_expiration_before_cancel('', '1042'), 'no note');
	pg_assert_same(null, pg_gift_card_expiration_before_cancel("This gift card was automatically expired because order #10421 was cancelled. Its expiration date was 2027-01-31.", '1042'), 'another order whose number starts the same');
	pg_assert_same(null, pg_gift_card_expiration_before_cancel("This gift card was automatically expired because order #1042 was cancelled. Its expiration date was 2027-01-31.", ''), 'an order without a number');

	$twice = "This gift card was automatically expired because order #1042 was cancelled. Its expiration date was 2027-01-31.\r\n"
		. "Extended by phone.\r\n"
		. "This gift card was automatically expired because order #1042 was cancelled. Its expiration date was 2027-06-30.";
	pg_assert_same('2027-06-30', pg_gift_card_expiration_before_cancel($twice, '1042'), 'the last cancellation wins');

	pg_assert_same(null, pg_gift_card_expiration_before_cancel("This gift card was automatically expired because order #1042 was cancelled. Its expiration date was 2027-02-30.", '1042'), 'an impossible date is not put back');
}

function test_order_cancel_gift_card_expiration_before_operator_lines()
{
	$cancelled = "This gift card was automatically expired because order #1042 was cancelled. Its expiration date was 2027-01-31.";

	pg_assert_same('2027-01-31', pg_gift_card_expiration_before_cancel($cancelled . "\nCustomer called about #1042", '1042'), 'an operator line naming the order, without a date');
	pg_assert_same('2027-01-31', pg_gift_card_expiration_before_cancel($cancelled . "\n#1042 refund promised by 2026-11-05", '1042'), 'an operator line naming the order, with a date');
	pg_assert_same(null, pg_gift_card_expiration_before_cancel("Customer called about #1042", '1042'), 'an operator line alone');
	pg_assert_same(null, pg_gift_card_expiration_before_cancel("Order #1042 was cancelled. Its expiration date was 2027-01-31.", '1042'), 'a line that only looks like the note');
	pg_assert_same('2027-01-31', pg_gift_card_expiration_before_cancel("  " . $cancelled . "  ", '1042'), 'spaces around the line');
}