<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an order whose payment its service left pending
 * (includes/fn/ecommerce.php pg_order_checkout_paid_at(),
 * pg_order_awaiting_payment(), pg_order_payment_pending(),
 * pg_order_payment_may_pend(), pg_order_payment_confirming_note()): a PayPal
 * Checkout capture PayPal answers PENDING, or a Stripe PaymentIntent still
 * processing, places the order unpaid, and the order waits for the service's
 * message or the operator like a bank transfer.
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

// A Stripe PaymentIntent still processing (a bank debit settles days later)
// is placed unpaid; Stripe's payment_intent.succeeded records it.
function test_order_checkout_paid_at_leaves_a_processing_stripe_intent_unpaid()
{
	pg_assert_same(0, pg_order_checkout_paid_at('Credit/Debit Card', 'pi_3PqXyZ', 'pending', 1700000000), 'Stripe processing');
	pg_assert_same(1700000000, pg_order_checkout_paid_at('Credit/Debit Card', 'pi_3PqXyZ', 'paid', 1700000000), 'Stripe succeeded');
	pg_assert_same(1700000000, pg_order_checkout_paid_at('Credit/Debit Card', 'pi_3PqXyZ', 'authorized', 1700000000), 'Stripe held');
}

// A pending answer of another method keeps writing paid_at as before: its
// order screen has no way to record the payment later.
function test_order_checkout_paid_at_keeps_other_pending_methods_paid()
{
	pg_assert_same(1700000000, pg_order_checkout_paid_at('Pay With Iyzico', '123', 'pending', 1700000000), 'iyzico review');
	pg_assert_same(1700000000, pg_order_checkout_paid_at('Credit/Debit Card', 'ch_1', 'pending', 1700000000), 'a card payment of another gateway');
}

// Which payments may wait: a pending method, or a card payment that is a
// Stripe PaymentIntent. Older card orders of other gateways carry paid_at 0
// from before the column and must not read as unpaid.
function test_order_payment_may_pend()
{
	pg_assert_true(pg_order_payment_may_pend('PayPal Checkout', ''), 'PayPal Checkout');
	pg_assert_true(pg_order_payment_may_pend('Credit/Debit Card', 'pi_3PqXyZ'), 'a Stripe PaymentIntent');
	pg_assert_false(pg_order_payment_may_pend('Credit/Debit Card', 'ch_1'), 'a classic Stripe charge');
	pg_assert_false(pg_order_payment_may_pend('Credit/Debit Card', '123456'), 'another card gateway');
	pg_assert_false(pg_order_payment_may_pend('Credit/Debit Card', ''), 'no transaction');
	pg_assert_false(pg_order_payment_may_pend('Pay With Iyzico', 'pi_1'), 'the prefix is a card payment\'s only');
	pg_assert_false(pg_order_payment_may_pend('Offline Payment', ''), 'a bank transfer is told apart');

	pg_assert_same(
		"((o.payment_method IN ('PayPal Checkout')) OR ((o.payment_method = 'Credit/Debit Card') AND (SUBSTR(COALESCE(o.transaction_id, ''), 1, 3) = 'pi_')))",
		pg_order_payment_may_pend_sql('o')
	);
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

// A Stripe order placed while its intent was processing waits like a PayPal
// one; a card order of another gateway with paid_at 0 does not.
function test_order_stripe_processing_reads_as_pending()
{
	$stripe = array('payment_method' => 'Credit/Debit Card', 'transaction_id' => 'pi_3PqXyZ', 'paid_at' => 0, 'status' => 'complete');

	pg_assert_true(pg_order_awaiting_payment($stripe));
	pg_assert_true(pg_order_payment_pending($stripe));
	pg_assert_same('stripe', pg_order_payment_pending_service($stripe));
	pg_assert_same('Payment pending on Stripe', pg_order_payment_pending_label($stripe));
	pg_assert_false(pg_order_payment_pending(array('paid_at' => 1700000000) + $stripe), 'recorded');

	$older = array('transaction_id' => 'ch_1') + $stripe;
	pg_assert_false(pg_order_awaiting_payment($older), 'a classic charge with paid_at 0');
	pg_assert_false(pg_order_awaiting_payment(array('payment_method' => 'Credit/Debit Card', 'paid_at' => 0, 'status' => 'complete')), 'a row without its transaction id');

	$paypal = array('payment_method' => 'PayPal Checkout', 'paid_at' => 0, 'status' => 'complete');
	pg_assert_same('paypal', pg_order_payment_pending_service($paypal));
	pg_assert_same('Payment pending on PayPal', pg_order_payment_pending_label($paypal));
}

// The buyer's line: only while the service has not settled the payment, and
// only when the row says whether it has.
function test_order_payment_confirming_note()
{
	$stripe = array('payment_method' => 'Credit/Debit Card', 'transaction_id' => 'pi_3PqXyZ', 'paid_at' => 0, 'status' => 'complete');
	$note = 'Your payment is being confirmed. We will start on your order once it arrives.';

	pg_assert_same($note, pg_order_payment_confirming_note($stripe), 'Stripe processing');
	pg_assert_same($note, pg_order_payment_confirming_note(array('payment_method' => 'PayPal Checkout', 'transaction_id' => '3C679366HH908993F') + $stripe), 'PayPal pending');
	pg_assert_same('', pg_order_payment_confirming_note(array('paid_at' => 1700000000) + $stripe), 'settled');
	pg_assert_same('', pg_order_payment_confirming_note(array('payment_method' => 'Offline Payment', 'transaction_id' => '') + $stripe), 'a bank transfer has its own instructions');
	pg_assert_same('', pg_order_payment_confirming_note(array('status' => 'cancelled') + $stripe), 'cancelled');

	unset($stripe['paid_at']);
	pg_assert_same('', pg_order_payment_confirming_note($stripe), 'a row without paid_at cannot tell');
}
