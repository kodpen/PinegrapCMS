<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what payment_callback.php does with a payment a service reports
 * for an order cancelled before its payment was recorded
 * (includes/payments/callback.php pg_payment_notify_outcome()): the money was
 * taken on an order the store no longer ships, so the message alerts instead
 * of passing as "nothing".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');

function payment_callback_cancelled_test_answer($status = 'paid')
{
	return array(
		'success'         => true,
		'status'          => $status,
		'transaction_id'  => '3C679366HH908993F',
		'order_reference' => '438',
		'amount'          => 2000,
		'installments'    => 0,
		'log'             => array('PayPal reports that capture 3C679366HH908993F of 20.00 EUR completed'),
		'raw'             => array('currency' => 'EUR'),
	);
}

function payment_callback_cancelled_test_order($changes = array())
{
	return $changes + array('id' => '438', 'status' => 'cancelled', 'transaction_id' => '3C679366HH908993F', 'payment_method' => 'PayPal Checkout', 'total' => '2000', 'paid_at' => '0');
}

// A PayPal capture left pending, the order cancelled as unpaid, then the
// capture completes: the money arrived on a cancelled order.
function test_payment_callback_paid_for_a_cancelled_unpaid_order_alerts()
{
	foreach (array('paid', 'authorized') as $status) {
		$outcome = pg_payment_notify_outcome('paypal', payment_callback_cancelled_test_answer($status), payment_callback_cancelled_test_order(), 'EUR');

		pg_assert_same('alerted', $outcome['status'], $status);
		pg_assert_same(200, $outcome['http'], $status . ': the service stops sending it');
		pg_assert_false($outcome['mark_paid'], $status . ': the cancelled order is not marked paid');
		pg_assert_false($outcome['complete'], $status . ': nor completed');
		pg_assert_same(
			'PayPal reports that capture 3C679366HH908993F of 20.00 EUR completed for order #438, which was cancelled before its payment was recorded. The order was not changed: refund the payment on PayPal\'s own screen or restore the order.',
			$outcome['alert'],
			$status
		);
		pg_assert_same(array($outcome['alert']), $outcome['log'], $status);
	}
}

// A cancelled order whose payment was recorded had it handled by the
// cancellation (refund or void); a late copy of the payment message says
// nothing new.
function test_payment_callback_paid_for_a_cancelled_paid_order_does_nothing()
{
	$outcome = pg_payment_notify_outcome('paypal', payment_callback_cancelled_test_answer(), payment_callback_cancelled_test_order(array('paid_at' => '1761600000')), 'EUR');

	pg_assert_same('nothing', $outcome['status']);
	pg_assert_same('', $outcome['alert']);
}

// Another payment than the cancelled order's own stays unknown, and a
// placed order keeps its "nothing".
function test_payment_callback_cancelled_order_keeps_the_other_answers()
{
	$outcome = pg_payment_notify_outcome('paypal', payment_callback_cancelled_test_answer(), payment_callback_cancelled_test_order(array('transaction_id' => '8MC585209K746392H')), 'EUR');
	pg_assert_same('unknown_order', $outcome['status'], 'another payment');

	$outcome = pg_payment_notify_outcome('paytr', payment_callback_cancelled_test_answer(), payment_callback_cancelled_test_order(array('status' => 'complete', 'payment_method' => 'PayTR', 'paid_at' => '1761600000')), 'EUR');
	pg_assert_same('nothing', $outcome['status'], 'placed and paid');
}
