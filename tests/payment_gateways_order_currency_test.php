<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a PayPal Checkout, PayTR or iyzico payment of an order kept in
 * another currency than the store's base one (a market's order,
 * orders.charged_currency): the request in the order's own currency, the
 * capture's check and the refund in it, the protected currency left out of
 * such an order, and each driver offered only in its own currencies. No
 * service is reached: stand-ins take the place of the HTTP callers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/payments/paypal.php');
require_once(PG_FUNCTIONS_DIR . '/includes/payments/paytr.php');
require_once(PG_FUNCTIONS_DIR . '/includes/payments/iyzico.php');

/* ---------------------------------------------------------------------------
   Fixtures
   --------------------------------------------------------------------------- */

// Stands in for PayPal: answers by "METHOD path", records every call.
function payment_gateways_order_currency_test_paypal_http($answers = array())
{
	$GLOBALS['payment_gateways_order_currency_test_calls'] = array();
	$answers += array('POST /v1/oauth2/token' => array(200, array('access_token' => 'A21AAF', 'expires_in' => 32400)));

	return function ($method, $url, $headers, $body) use ($answers) {
		$path = (string) preg_replace('#^https://[^/]+#', '', $url);
		$GLOBALS['payment_gateways_order_currency_test_calls'][] = array('path' => $method . ' ' . $path, 'body' => $body);

		if (!isset($answers[$method . ' ' . $path])) {
			return array('status' => 0, 'body' => null, 'error' => 'Could not resolve host: api-m.paypal.com');
		}

		return array('status' => $answers[$method . ' ' . $path][0], 'body' => $answers[$method . ' ' . $path][1], 'error' => '');
	};
}

function payment_gateways_order_currency_test_sent($path)
{
	foreach ($GLOBALS['payment_gateways_order_currency_test_calls'] as $call) {
		if ($call['path'] === $path) {
			return json_decode((string) $call['body'], true);
		}
	}

	return null;
}

// A lira store's PayPal settings.
function payment_gateways_order_currency_test_paypal_settings()
{
	return array(
		'client_id'     => 'client',
		'client_secret' => 'secret',
		'mode'          => 'live',
		'webhook_id'    => '',
		'currency'      => 'TRY',
		'locale'        => '',
	);
}

function payment_gateways_order_currency_test_order($currency)
{
	return array(
		'id'                    => '438',
		'reference_code'        => 'AB12CD34EF',
		'billing_email_address' => 'ayse@example.com',
		'transaction_id'        => '3C679366HH908993F',
		'refunded_amount'       => '0',
		'charged_currency'      => $currency,
	);
}

/* ---------------------------------------------------------------------------
   PayPal Checkout
   --------------------------------------------------------------------------- */

function test_payment_paypal_order_settings()
{
	$settings = payment_gateways_order_currency_test_paypal_settings();

	pg_assert_same('EUR', pg_payment_paypal_order_settings($settings, array('charged_currency' => 'eur'))['currency']);
	pg_assert_same('TRY', pg_payment_paypal_order_settings($settings, array('charged_currency' => ''))['currency'], 'a base order');
	pg_assert_same('TRY', pg_payment_paypal_order_settings($settings, array())['currency'], 'no column yet');
	pg_assert_same(pg_payment_paypal_order_settings($settings, array('charged_currency' => 'EUR')), pg_payment_paypal_order_settings(pg_payment_paypal_order_settings($settings, array('charged_currency' => 'EUR')), array('charged_currency' => 'EUR')));
}

function test_payment_paypal_charges_the_orders_currency()
{
	$http = payment_gateways_order_currency_test_paypal_http(array('POST /v2/checkout/orders' => array(200, array(
		'id'     => '5O190127TN364715T',
		'status' => 'PAYER_ACTION_REQUIRED',
		'links'  => array(array('rel' => 'payer-action', 'href' => 'https://www.paypal.com/checkoutnow?token=5O190127TN364715T')),
	))));

	// A lira store cannot use PayPal for its own orders, but its euro market can.
	$answer = pg_payment_answer('authorize', pg_payment_paypal_authorize_with(payment_gateways_order_currency_test_order('EUR'), 12345, array('return_url' => 'https://shop.example/pinegrap/express_order.php'), payment_gateways_order_currency_test_paypal_settings(), $http));

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('currency_code' => 'EUR', 'value' => '123.45'), payment_gateways_order_currency_test_sent('POST /v2/checkout/orders')['purchase_units'][0]['amount']);

	$answer = pg_payment_answer('authorize', pg_payment_paypal_authorize_with(payment_gateways_order_currency_test_order(''), 12345, array('return_url' => 'https://shop.example/pinegrap/express_order.php'), payment_gateways_order_currency_test_paypal_settings(), $http));
	pg_assert_false($answer['success'], 'a lira order is still refused');
}

function test_payment_paypal_refunds_in_the_orders_currency()
{
	$answers = array('POST /v2/payments/captures/3C679366HH908993F/refund' => array(201, array('id' => '1JU08902781691411', 'status' => 'COMPLETED', 'amount' => array('value' => '2000', 'currency_code' => 'JPY'))));

	$answer = pg_payment_answer('refund', pg_payment_paypal_refund_with(payment_gateways_order_currency_test_order('JPY'), 200000, payment_gateways_order_currency_test_paypal_settings(), payment_gateways_order_currency_test_paypal_http($answers)));

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('amount' => array('currency_code' => 'JPY', 'value' => '2000')), payment_gateways_order_currency_test_sent('POST /v2/payments/captures/3C679366HH908993F/refund'));
}

function test_payment_paypal_capture_checked_in_the_orders_currency()
{
	pg_assert_true(pg_payment_paypal_money_is(array('currency_code' => 'EUR', 'value' => '123.45'), 12345, pg_payment_paypal_order_settings(payment_gateways_order_currency_test_paypal_settings(), array('charged_currency' => 'EUR'))['currency']));
	pg_assert_false(pg_payment_paypal_money_is(array('currency_code' => 'TRY', 'value' => '123.45'), 12345, pg_payment_paypal_order_settings(payment_gateways_order_currency_test_paypal_settings(), array('charged_currency' => 'EUR'))['currency']));
}

/* ---------------------------------------------------------------------------
   PayTR
   --------------------------------------------------------------------------- */

function test_payment_paytr_charges_the_orders_currency()
{
	$GLOBALS['payment_gateways_order_currency_test_paytr'] = null;
	$http = function ($fields) {
		$GLOBALS['payment_gateways_order_currency_test_paytr'] = $fields;

		return array('status' => 200, 'body' => array('status' => 'success', 'token' => 'abc123TOKEN'), 'error' => '');
	};
	$settings = array(
		'merchant_id'   => '123456',
		'merchant_key'  => 'KEYkey',
		'merchant_salt' => 'SALTsalt',
		'test_mode'     => '0',
		'currency'      => 'TRY',
		'lang'          => 'tr',
		'ok_url'        => 'https://shop.example.com/receipt',
		'fail_url'      => 'https://shop.example.com/checkout',
		'time'          => 1761730000,
	);
	$order = array('id' => '42', 'charged_currency' => 'EUR');

	$answer = pg_payment_answer('authorize', pg_payment_paytr_authorize_with($order, 12350, array('ip' => '88.230.1.2', 'lines' => array()), $settings, $http));
	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('EUR', $GLOBALS['payment_gateways_order_currency_test_paytr']['currency']);
	pg_assert_same('12350', $GLOBALS['payment_gateways_order_currency_test_paytr']['payment_amount'], 'the order\'s amount, not converted');
	pg_assert_same(pg_payment_paytr_token_hash($GLOBALS['payment_gateways_order_currency_test_paytr'], 'KEYkey', 'SALTsalt'), $GLOBALS['payment_gateways_order_currency_test_paytr']['paytr_token']);

	pg_assert_same('TL', pg_payment_paytr_currency(pg_payment_paytr_order_settings($settings, array('charged_currency' => ''))['currency']), 'a base order in lira');

	$answer = pg_payment_answer('authorize', pg_payment_paytr_authorize_with(array('id' => '42', 'charged_currency' => 'JPY'), 12350, array(), $settings, $http));
	pg_assert_false($answer['success'], 'a currency PayTR does not take');
}

/* ---------------------------------------------------------------------------
   iyzico
   --------------------------------------------------------------------------- */

function test_payment_iyzico_order_settings_leave_the_protected_currency_out()
{
	$settings = array('currency' => 'TRY', 'protected_currency' => 'USD', 'protected_rate' => 0.0312);

	$market = pg_payment_iyzico_order_settings($settings, array('charged_currency' => 'EUR'));
	pg_assert_same(array('currency' => 'EUR', 'protected_currency' => '', 'protected_rate' => 0.0), $market);
	pg_assert_same($market, pg_payment_iyzico_order_settings($market, array('charged_currency' => 'EUR')), 'given again, unchanged');

	pg_assert_same($settings, pg_payment_iyzico_order_settings($settings, array('charged_currency' => '')), 'a base order keeps the protected currency');
	pg_assert_same($settings, pg_payment_iyzico_order_settings($settings, array('charged_currency' => 'TRY')));
	pg_assert_same($settings, pg_payment_iyzico_order_settings($settings, array()));
}

function test_payment_iyzico_card_refund_of_a_base_order_still_refused_under_protection()
{
	$answer = pg_payment_answer('refund', pg_payment_iyzico_card_refund_with(array('transaction_id' => '12345', 'charged_currency' => ''), 2000, array('currency' => 'TRY', 'protected_currency' => 'USD', 'protected_rate' => 0.03)));

	pg_assert_false($answer['success']);
	pg_assert_contains('USD', $answer['error']);
}

/* ---------------------------------------------------------------------------
   Which methods a market's order is offered
   --------------------------------------------------------------------------- */

function test_payment_gateways_declare_the_orders_currency()
{
	pg_assert_true(in_array('order_currency', pg_payment_paypal_capabilities(), true), 'PayPal Checkout');
	pg_assert_true(in_array('order_currency', pg_payment_paytr_capabilities(), true), 'PayTR');
	pg_assert_true(in_array('order_currency', pg_payment_iyzico_capabilities_with(false), true), 'iyzico card');
	pg_assert_true(in_array('order_currency', pg_payment_pay_with_iyzico_capabilities(), true), 'Pay With Iyzico');

	pg_assert_same(pg_payment_paypal_currencies(), pg_payment_driver_currencies('paypal'));
	pg_assert_same(array('TRY', 'EUR', 'USD', 'GBP', 'RUB'), pg_payment_driver_currencies('paytr'));
	pg_assert_same(pg_payment_iyzico_currencies(), pg_payment_driver_currencies('iyzico'));
	pg_assert_same(pg_payment_iyzico_currencies(), pg_payment_driver_currencies('pay_with_iyzico'));
	pg_assert_same(null, pg_payment_driver_currencies('stripe'), 'Stripe names none: any');
	pg_assert_same(null, pg_payment_driver_currencies('no_such_driver'));
}

function test_payment_method_takes_currency_by_the_drivers_list()
{
	$cases = array(
		'euro order, PayPal'            => array('PayPal Checkout', true, 'EUR', 'TRY', pg_payment_paypal_currencies(), true),
		'lira market, PayPal'           => array('PayPal Checkout', true, 'TRY', 'USD', pg_payment_paypal_currencies(), false),
		'yen order, PayTR'              => array('PayTR', true, 'JPY', 'TRY', pg_payment_paytr_currencies(), false),
		'euro order, PayTR'             => array('PayTR', true, 'EUR', 'TRY', pg_payment_paytr_currencies(), true),
		'franc order, Pay With Iyzico'  => array('Pay With Iyzico', true, 'CHF', 'TRY', pg_payment_iyzico_currencies(), true),
		'a driver naming none'          => array('Credit/Debit Card', true, 'JPY', 'TRY', null, true),
		'base order, any list'          => array('PayTR', true, 'TRY', 'TRY', array('EUR'), true),
		'offline, any list'             => array('Offline Payment', false, 'JPY', 'TRY', array('EUR'), true),
	);

	foreach ($cases as $label => $case) {
		pg_assert_same($case[5], pg_payment_method_takes_currency_decide($case[0], $case[1], $case[2], $case[3], $case[4]), $label);
	}
}
