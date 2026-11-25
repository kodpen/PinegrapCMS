<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for refunds given as store credit (includes/fn/store_credit_refunds.php):
 * the bonus setting, the card amount with and without the bonus, the
 * reference kept on a cancelled order and what the refund mail says.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_store_credit_refund_bonus_is_stored_as_a_plain_percentage()
{
	pg_assert_same('10', pg_credit_refund_parse_bonus('10'), 'whole percentage');
	pg_assert_same('2.5', pg_credit_refund_parse_bonus('2,5'), 'decimal comma');
	pg_assert_same('2.5', pg_credit_refund_parse_bonus('2.5'), 'decimal dot');
	pg_assert_same('7.13', pg_credit_refund_parse_bonus('7.125'), 'two decimals, rounded');
	pg_assert_same('100', pg_credit_refund_parse_bonus('250'), 'capped at 100');
	pg_assert_same('', pg_credit_refund_parse_bonus(''), 'empty is no bonus');
	pg_assert_same('', pg_credit_refund_parse_bonus('0'), 'zero is no bonus');
	pg_assert_same('', pg_credit_refund_parse_bonus('-5'), 'negative is no bonus');
	pg_assert_same('', pg_credit_refund_parse_bonus('abc'), 'not a number is no bonus');
	pg_assert_same('', pg_credit_refund_parse_bonus(array('5')), 'an array is no bonus');
}

function test_store_credit_refund_card_amount_without_bonus_is_the_refund()
{
	pg_assert_same(12050, pg_credit_refund_card_amount(12050, 0), 'no bonus');
	pg_assert_same(12050, pg_credit_refund_card_amount(12050, ''), 'setting empty');
	pg_assert_same(12050, pg_credit_refund_card_amount(12050, '-3'), 'a negative bonus is none');
	pg_assert_same(0, pg_credit_refund_card_amount(0, 10), 'nothing refunded gives nothing');
	pg_assert_same(0, pg_credit_refund_card_amount(-100, 10), 'a negative refund gives nothing');
}

function test_store_credit_refund_card_amount_adds_the_bonus_rounded()
{
	pg_assert_same(11000, pg_credit_refund_card_amount(10000, 10), 'ten percent');
	pg_assert_same(10250, pg_credit_refund_card_amount(10000, '2.5'), 'a fraction of a percent');
	pg_assert_same(1036, pg_credit_refund_card_amount(999, 3.7), '36.963 rounds to 37');
	pg_assert_same(1035, pg_credit_refund_card_amount(1000, 3.45), '34.5 rounds half up');
	pg_assert_same(2, pg_credit_refund_card_amount(1, 50), 'half a minor unit rounds up');
	pg_assert_same(20000, pg_credit_refund_card_amount(10000, 250), 'capped at 100 percent');
}

// The bonus is the exact product rounded half away from zero on every PHP
// version: a float round($amount * $percent / 100) gave 46.51 for 8.86% of
// 525.00 on PHP 8.4 and later, where round() no longer pre-rounds.
function test_store_credit_refund_bonus_is_exact_on_every_php_version()
{
	pg_assert_same(52500 + 4652, pg_credit_refund_card_amount(52500, 8.86), '8.86% of 525.00 is 46.515');
	pg_assert_same(115750 + 77090, pg_credit_refund_card_amount(115750, 66.6), '66.6% of 1157.50 is 770.895');
	pg_assert_same(365000 + 282328, pg_credit_refund_card_amount(365000, 77.35), '77.35% of 3650.00 is 2823.275');

	for ($i = 0; $i < 5000; $i++) {
		$amount = mt_rand(1, 5000000);
		$hundredths = mt_rand(1, 10000);
		$product = $amount * $hundredths;
		$bonus = intdiv($product, 10000) + ((($product % 10000) * 2 >= 10000) ? 1 : 0);

		pg_assert_same($amount + $bonus, pg_credit_refund_card_amount($amount, $hundredths / 100), $amount . ' at ' . ($hundredths / 100) . '%');
	}

	$function = new ReflectionFunction('pg_credit_refund_card_amount');
	$lines = array_slice(file($function->getFileName()), $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1);
	pg_assert_true(strpos(implode('', $lines), 'pg_percent_of(') !== false, 'the bonus goes through pg_percent_of()');
}

function test_store_credit_refund_bonus_only_on_the_bonus_base()
{
	pg_assert_same(10500, pg_credit_refund_card_amount_on(10000, 5000, 10), 'half earns ten percent');
	pg_assert_same(10000, pg_credit_refund_card_amount_on(10000, 0, 10), 'no base gives no bonus');
	pg_assert_same(11000, pg_credit_refund_card_amount_on(10000, 20000, 10), 'the base is capped at the refund');
	pg_assert_same(10000, pg_credit_refund_card_amount_on(10000, -50, 10), 'a negative base is none');
	pg_assert_same(0, pg_credit_refund_card_amount_on(0, 100, 10), 'nothing refunded gives nothing');
	pg_assert_same(1036, pg_credit_refund_card_amount_on(999, 999, 3.7), 'rounded as the whole refund');
}

function test_store_credit_refund_cancelled_order_bonus_skips_the_gift_card_part()
{
	pg_assert_same(10000, pg_credit_refund_cancel_card_amount(array('payment' => 0, 'gift_card' => 10000, 'total' => 10000), 10),
		'an order paid with store credit only gets it back without a bonus');
	pg_assert_same(10600, pg_credit_refund_cancel_card_amount(array('payment' => 6000, 'gift_card' => 4000, 'total' => 10000), 10),
		'the bonus is on the payment part');
	pg_assert_same(11000, pg_credit_refund_cancel_card_amount(array('payment' => 10000, 'gift_card' => 0, 'total' => 10000), 10),
		'a paid order gets the whole bonus');
	pg_assert_same(10000, pg_credit_refund_cancel_card_amount(array('payment' => 6000, 'gift_card' => 4000, 'total' => 10000), 0),
		'no bonus set');
	pg_assert_same(0, pg_credit_refund_cancel_card_amount(array(), 10), 'nothing owed');
}

function test_store_credit_refund_reference_names_the_card()
{
	pg_assert_same('store_credit:42', pg_credit_refund_reference(42), 'reference');
	pg_assert_same(42, pg_credit_refund_card_id('store_credit:42'), 'read back');
	pg_assert_same(0, pg_credit_refund_card_id('42'), 'a typed reference is no card');
	pg_assert_same(0, pg_credit_refund_card_id('store_credit:'), 'no id');
	pg_assert_same(0, pg_credit_refund_card_id('store_credit:4x'), 'not a number');
	pg_assert_same(0, pg_credit_refund_card_id('store_credit:0'), 'zero');
}

function test_store_credit_refund_mail_names_the_credit()
{
	$body = pg_return_refund_mail_body(array(
		'customer'     => 'Ada',
		'order_number' => '1042',
		'lines'        => array(),
		'amount'       => '100.00',
		'credit'       => '110.00',
		'from_name'    => 'Shop',
	));

	pg_assert_true(strpos($body, '110.00') !== false, 'the credit amount is named');
	pg_assert_true(strpos($body, 'store credit') !== false, 'store credit is named');
	pg_assert_true(strpos($body, 'The money goes back the way you paid') === false, 'no payment sentence');

	$plain = pg_return_refund_mail_body(array(
		'customer'     => 'Ada',
		'order_number' => '1042',
		'lines'        => array(),
		'amount'       => '100.00',
		'by_payment'   => true,
		'from_name'    => 'Shop',
	));

	pg_assert_true(strpos($plain, 'store credit') === false, 'a payment refund names no credit');
}

function test_store_credit_refund_cancel_mail_names_the_credit()
{
	$body = pg_credit_refund_cancel_mail_body(array(
		'customer'     => 'Ada',
		'order_number' => '1042',
		'amount'       => '100.00',
		'credit'       => '110.00',
		'from_name'    => 'Shop',
	));

	pg_assert_true(strpos($body, '1042') !== false, 'the order is named');
	pg_assert_true(strpos($body, '110.00') !== false, 'the credit amount is named');
	pg_assert_true(strpos($body, '<script') === false, 'escaped');
}

// A cancelled order's refund given as store credit records its gift card part
// as a return_gift_card row, as a return's refund does: the payment money row
// alone was counted as given back, so after a restore a return or a second
// cancellation gave the gift card part as credit once more.
function test_store_credit_refund_cancel_rows_split_the_gift_card_part()
{
	pg_assert_same(array(array(7000, 'credit'), array(3000, 'return_gift_card')), pg_credit_refund_cancel_rows(10000, 7000), 'payment money and the gift card part');
	pg_assert_same(array(array(10000, 'credit')), pg_credit_refund_cancel_rows(10000, 10000), 'payment money only');
	pg_assert_same(array(array(4500, 'return_gift_card')), pg_credit_refund_cancel_rows(4500, 0), 'paid by gift cards alone');
	pg_assert_same(array(array(500, 'credit')), pg_credit_refund_cancel_rows(500, 900), 'never more payment than the refund');
	pg_assert_same(array(), pg_credit_refund_cancel_rows(0, 0), 'nothing refunded');
}
