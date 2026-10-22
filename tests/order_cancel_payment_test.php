<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what cancelling an order does to the money its payment took
 * (includes/fn/ecommerce.php, pg_order_cancel_payment_plan()): the order
 * screen's cancel window and process_order_cancellation() both decide by it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_cancel_payment_void_untouched_payment()
{
	pg_assert_same(
		array('action' => 'void', 'remaining' => 10000, 'refunded' => 0),
		pg_order_cancel_payment_plan(10000, 0, 'Credit/Debit Card', true, 'iyzipay', true),
		'a card payment nothing was refunded of is voided');
}

function test_order_cancel_payment_fully_refunded()
{
	pg_assert_same(
		array('action' => 'none', 'remaining' => 0, 'refunded' => 10000),
		pg_order_cancel_payment_plan(10000, 10000, 'Credit/Debit Card', true, 'iyzipay', true),
		'money given back in full: no void, nothing to flag');
	pg_assert_same(
		array('action' => 'none', 'remaining' => 0, 'refunded' => 10000),
		pg_order_cancel_payment_plan('10000', '10000', 'PayPal Express', true, '', false),
		'a method without a driver, refunded in full: nothing to flag');
	pg_assert_same(
		'none',
		pg_order_cancel_payment_plan(10000, 12000, 'Credit/Debit Card', true, 'iyzipay', true)['action'],
		'more refunded than the total never leaves money to return');
}

function test_order_cancel_payment_partly_refunded()
{
	// A void takes no amount and reverses the whole payment, so a payment
	// that was partly given back already is left to the operator.
	pg_assert_same(
		array('action' => 'manual', 'remaining' => 6000, 'refunded' => 4000),
		pg_order_cancel_payment_plan(10000, 4000, 'Credit/Debit Card', true, 'iyzipay', true),
		'partly refunded: the rest is flagged, not voided');
}

function test_order_cancel_payment_manual()
{
	pg_assert_same('manual', pg_order_cancel_payment_plan(10000, 0, 'Credit/Debit Card', true, 'iyzipay', false)['action'], 'auto refund off');
	pg_assert_same('manual', pg_order_cancel_payment_plan(10000, 0, 'Credit/Debit Card', false, 'iyzipay', true)['action'], 'no transaction id');
	pg_assert_same('manual', pg_order_cancel_payment_plan(10000, 0, 'PayPal Express', true, '', true)['action'], 'no driver can cancel');
}

function test_order_cancel_payment_nothing_taken()
{
	pg_assert_same('none', pg_order_cancel_payment_plan(0, 0, 'Credit/Debit Card', true, 'iyzipay', true)['action'], 'a free order');
	pg_assert_same('none', pg_order_cancel_payment_plan(10000, 0, 'Offline Payment', false, '', true)['action'], 'offline payment');
	pg_assert_same('none', pg_order_cancel_payment_plan(10000, 0, '', false, '', true)['action'], 'no payment method');
	pg_assert_same(10000, pg_order_cancel_payment_plan(10000, 0, 'Offline Payment', false, '', true)['remaining'], 'what is left is still reported');
}
