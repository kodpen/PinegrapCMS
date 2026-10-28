<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an order whose payment its service left pending
 * (includes/fn/ecommerce.php pg_order_checkout_paid_at(),
 * pg_order_awaiting_payment(), pg_order_payment_pending()): a PayPal Checkout
 * capture PayPal answers PENDING places the order unpaid, and the order waits
 * for the operator like a bank transfer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A confirmed payment is paid when the order is placed; an offline payment
// and a pending PayPal Checkout capture are not.
function test_order_checkout_paid_at_leaves_a_pending_paypal_capture_unpaid()
{
	pg_assert_same(1700000000, pg_order_checkout_paid_at('PayPal Checkout', '3C679366HH908993F', 'paid', 1700000000));
	pg_assert_same(0, pg_order_checkout_paid_at('PayPal Checkout', '3C679366HH908993F', 'pending', 1700000000), 'pending capture');
	pg_assert_same(0, pg_order_checkout_paid_at('Offline Payment', '', '', 1700000000), 'offline');
	pg_assert_same(1700000000, pg_order_checkout_paid_at('Credit/Debit Card', 'ch_1', '', 1700000000), 'legacy gateway');
}

// A pending answer of another method keeps writing paid_at as before: its
// order screen has no way to record the payment later.
function test_order_checkout_paid_at_keeps_other_pending_methods_paid()
{
	pg_assert_same(1700000000, pg_order_checkout_paid_at('Credit/Debit Card', 'pi_1', 'pending', 1700000000), 'Stripe processing');
	pg_assert_same(1700000000, pg_order_checkout_paid_at('Pay With Iyzico', '123', 'pending', 1700000000), 'iyzico review');
}

// The order waits as awaiting payment, told apart from a bank transfer.
function test_order_payment_pending_reads_as_awaiting_payment()
{
	$pending = array('payment_method' => 'PayPal Checkout', 'paid_at' => 0, 'status' => 'complete');

	pg_assert_true(pg_order_awaiting_payment($pending));
	pg_assert_true(pg_order_payment_pending($pending));

	pg_assert_false(pg_order_awaiting_payment(array('paid_at' => 1700000000) + $pending), 'recorded');
	pg_assert_false(pg_order_payment_pending(array('status' => 'cancelled') + $pending), 'cancelled');
	pg_assert_false(pg_order_payment_pending(array('status' => 'incomplete') + $pending), 'incomplete');

	$transfer = array('payment_method' => 'Offline Payment') + $pending;
	pg_assert_true(pg_order_awaiting_payment($transfer), 'bank transfer');
	pg_assert_false(pg_order_payment_pending($transfer), 'bank transfer is not a pending service payment');

	pg_assert_false(pg_order_awaiting_payment(array('payment_method' => 'Pay With Iyzico') + $pending), 'an older paid_at 0 order of another method');
}
