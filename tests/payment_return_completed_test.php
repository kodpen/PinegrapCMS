<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor's return from a payment service when the service's
 * own message completed the order first (includes/fn/payments.php
 * pg_payment_return_state_order_id(), pg_payment_return_shows_receipt()):
 * the return leads to the receipt instead of checking out an empty cart.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_payment_return_state_order_id()
{
	$states = array(
		'stripe' => array('payment_intent' => 'pi_3Abc', 'order_id' => '812', 'amount' => 4714),
		'paypal' => array('paypal_order' => '5O190127TN364715T', 'order_id' => 77, 'amount' => 2000),
		'iyzico' => array('installment' => 3),
	);

	pg_assert_same(812, pg_payment_return_state_order_id('stripe_return', $states), 'the order the Stripe return was started for');
	pg_assert_same(77, pg_payment_return_state_order_id('paypal_return', $states), 'the order the PayPal return was started for');
	pg_assert_same(0, pg_payment_return_state_order_id('iyzipay_threedsecure_return', $states), 'a state that names no order');
	pg_assert_same(0, pg_payment_return_state_order_id('stripe_return', array()), 'no state: the wait has ended');
	pg_assert_same(0, pg_payment_return_state_order_id('paypal_express_checkout_return', $states), 'the legacy PayPal return has no driver state');
	pg_assert_same(0, pg_payment_return_state_order_id('stripe_return', array('stripe' => array('order_id' => 'x1'))), 'an order id that is no number');
	pg_assert_same(0, pg_payment_return_state_order_id('stripe_return', array('stripe' => 'pi_3Abc')), 'a state that is no list');
	pg_assert_same(0, pg_payment_return_state_order_id(array('stripe_return'), $states), 'a mode sent as a list');
}

function test_payment_return_shows_receipt()
{
	pg_assert_true(pg_payment_return_shows_receipt(812, 0, 'complete'), 'the message completed the order and init.php let go of it');
	pg_assert_true(pg_payment_return_shows_receipt(812, 0, 'exported'), 'already exported');
	pg_assert_true(pg_payment_return_shows_receipt(812, 905, 'complete'), 'another cart was opened meanwhile');
	pg_assert_false(pg_payment_return_shows_receipt(812, 812, 'complete'), 'the session still holds the order: the checkout finishes it');
	pg_assert_false(pg_payment_return_shows_receipt(812, 0, 'incomplete'), 'still incomplete: the checkout completes it');
	pg_assert_false(pg_payment_return_shows_receipt(812, 0, 'cancelled'), 'a cancelled order shows no receipt');
	pg_assert_false(pg_payment_return_shows_receipt(812, 0, ''), 'no such order');
	pg_assert_false(pg_payment_return_shows_receipt(0, 0, 'complete'), 'no order in the state');
}

// Both checkout screens look for the completed order before they open a new
// one for a return the session no longer holds an order for.
function test_payment_return_receipt_called_before_new_order()
{
	foreach (array('order_preview.php' => 'order_preview', 'express_order.php' => 'express_order') as $file => $type) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$call = strpos($source, "pg_payment_return_receipt('" . $type . "'");

		pg_assert_true($call !== false, $file . ' leads a return to the receipt');
		pg_assert_true($call < strpos($source, 'initialize_order();'), $file . ': before a new order is opened');
	}
}
