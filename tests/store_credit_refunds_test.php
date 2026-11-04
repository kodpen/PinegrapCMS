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
