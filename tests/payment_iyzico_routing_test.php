<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that every iyzico payment at checkout is taken by the iyzico drivers
 * (includes/payments/iyzico.php) and never by gateway code in
 * submit_order.php: the gateway Iyzipay and the method Pay With Iyzico reach
 * the driver branch (pg_payment_checkout_driver()) on the first submit and on
 * both return addresses, and submit_order.php no longer calls the iyzico SDK
 * itself.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The modes a checkout submit arrives with: the first submit, the two iyzico
// returns, another service's return and an unknown value.
function payment_iyzico_routing_test_modes()
{
	return array('', 'iyzipay_threedsecure_return', 'pay_with_iyzico_return', 'paypal_express_checkout_return', 'anything');
}

// The card with the gateway Iyzipay goes to the iyzico driver in every mode.
function test_payment_iyzico_routing_card_reaches_the_driver()
{
	foreach (payment_iyzico_routing_test_modes() as $mode) {
		pg_assert_same('iyzico', pg_payment_checkout_driver('Credit/Debit Card', 'Iyzipay', $mode), 'mode ' . $mode);
	}
}

// Pay With Iyzico goes to its driver in every mode, whatever the card gateway.
function test_payment_iyzico_routing_pay_with_iyzico_reaches_the_driver()
{
	foreach (payment_iyzico_routing_test_modes() as $mode) {
		foreach (array('Iyzipay', 'Stripe', '') as $gateway) {
			pg_assert_same('pay_with_iyzico', pg_payment_checkout_driver('Pay With Iyzico', $gateway, $mode), 'mode ' . $mode . ', gateway ' . $gateway);
		}
	}
}

// Both drivers are shipped, load, and finish their own returns, so the one
// case that hands a return back to gateway code (a driver without
// complete()) never applies to them.
function test_payment_iyzico_routing_drivers_finish_their_returns()
{
	foreach (array('iyzico', 'pay_with_iyzico') as $key) {
		pg_assert_true(pg_payment_load($key), $key . ' loads');
		pg_assert_true(pg_payment_can($key, 'authorize'), $key . ' authorizes');
		pg_assert_true(pg_payment_can($key, 'complete'), $key . ' completes');
	}
}

// submit_order.php runs its gateway code only when no driver took the
// payment, and has no iyzico payment code of its own left.
function test_payment_iyzico_routing_no_inline_iyzico_code()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	pg_assert_contains("if ((\$nonrecurring_transaction == TRUE) && (\$payment_driver === ''))", $source, 'the gateway code waits for no driver');
	pg_assert_false(strpos($source, '\\Iyzipay\\') !== false, 'no iyzico SDK class is used');
	pg_assert_false(strpos($source, 'IyzipayBootstrap') !== false, 'the iyzico SDK is not loaded');
}
