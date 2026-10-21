<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the site log line a cancelled order writes about the gift cards
 * that paid for it (includes/fn/ecommerce.php,
 * pg_order_gift_card_refund_line()).
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
	pg_assert_same('', pg_order_gift_card_refund_line(12, array()), 'no gift card');
	pg_assert_same('', pg_order_gift_card_refund_line(12, array(
		array('code' => 'ABCD1234EFGH5678', 'gift_card_id' => '3', 'amount' => '0', 'givex' => '0'))), 'a card that paid nothing');
}

function test_order_cancel_gift_cards_line()
{
	$line = pg_order_gift_card_refund_line('12', array(
		array('code' => 'ABCD1234EFGH5678', 'gift_card_id' => '3', 'amount' => '1250', 'givex' => '0'),
		array('code' => '6035711234567890123', 'gift_card_id' => '0', 'amount' => '500000', 'givex' => '1')));

	pg_assert_contains('REFUND ACTION REQUIRED — order #12 was cancelled.', $line);
	pg_assert_contains('Gift cards paid 5,012.50 of it:', $line, 'the total of the cards');
	pg_assert_contains('************5678 (#3) 12.50', $line, 'the shop card, masked, with its id');
	pg_assert_contains('603571*****6789012* (Givex) 5,000.00', $line, 'the Givex card, masked');
	pg_assert_false(strpos($line, 'ABCD1234EFGH5678') !== false, 'no full code in the log');
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