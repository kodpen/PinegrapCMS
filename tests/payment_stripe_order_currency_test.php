<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Stripe payment of an order kept in another currency than the
 * store's base one (a market's order, orders.charged_currency): the intent,
 * its check on the return and the refund in the order's own currency, the
 * methods beside the card offered by that currency, the switches drawn for
 * the store's currencies, and which checkout methods such an order is
 * offered. Stripe is never reached: a stand-in takes the place of the HTTP
 * caller.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/payments/stripe.php');

/* ---------------------------------------------------------------------------
   Fixtures
   --------------------------------------------------------------------------- */

// Stands in for Stripe: answers by "METHOD path", records every call.
function payment_stripe_order_currency_test_http($answers = array())
{
	$GLOBALS['payment_stripe_order_currency_test_calls'] = array();

	return function ($secret_key, $method, $path, $params, $idempotency_key) use ($answers) {
		$GLOBALS['payment_stripe_order_currency_test_calls'][] = array('method' => $method, 'path' => $path, 'params' => $params);
		$name = $method . ' ' . $path;

		if (!isset($answers[$name])) {
			return array('status' => 0, 'body' => null, 'error' => 'Could not resolve host: api.stripe.com');
		}

		return array('status' => $answers[$name][0], 'body' => $answers[$name][1], 'error' => '');
	};
}

function payment_stripe_order_currency_test_calls()
{
	return $GLOBALS['payment_stripe_order_currency_test_calls'];
}

// The store's settings: base currency Turkish lira, iDEAL switched on.
function payment_stripe_order_currency_test_settings()
{
	return array(
		'publishable_key' => 'pk_test_123',
		'secret_key'      => 'sk_test_456',
		'link'            => false,
		'currency'        => 'try',
		'capture'         => 'automatic',
		'locale'          => 'nl',
		'methods'         => array('ideal' => true),
	);
}

function payment_stripe_order_currency_test_order($changes = array())
{
	return array_merge(array(
		'id'                    => '438',
		'reference_code'        => 'AB12CD34EF',
		'billing_email_address' => 'anna@example.com',
		'transaction_id'        => 'pi_3Abc',
		'charged_currency'      => 'EUR',
		'charged_rate'          => '36.50000000',
	), $changes);
}

/* ---------------------------------------------------------------------------
   The order's currency
   --------------------------------------------------------------------------- */

function test_payment_stripe_order_settings_take_the_order_currency()
{
	$settings = payment_stripe_order_currency_test_settings();

	pg_assert_same('eur', pg_payment_stripe_order_settings($settings, payment_stripe_order_currency_test_order())['currency']);
	pg_assert_same('try', pg_payment_stripe_order_settings($settings, payment_stripe_order_currency_test_order(array('charged_currency' => '')))['currency'], 'an order without a charged currency is in base');
	pg_assert_same('try', pg_payment_stripe_order_settings($settings, array())['currency'], 'no order row: base');
	pg_assert_same('try', pg_payment_stripe_order_settings($settings, payment_stripe_order_currency_test_order(array('charged_currency' => 'TRY')))['currency']);

	$once = pg_payment_stripe_order_settings($settings, payment_stripe_order_currency_test_order());
	pg_assert_same($once, pg_payment_stripe_order_settings($once, payment_stripe_order_currency_test_order()), 'given its own answer again it changes nothing');

	$no_base = array_merge($settings, array('currency' => ''));
	pg_assert_same('', pg_payment_stripe_order_settings($no_base, payment_stripe_order_currency_test_order())['currency'], 'a store without a base currency stays not available');
}

function test_payment_stripe_intent_is_in_the_order_currency()
{
	$settings = payment_stripe_order_currency_test_settings();
	$client = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_order_currency_test_order(), 4714, $settings))['client'];

	pg_assert_same('eur', $client['currency']);
	pg_assert_same(4714, $client['amount'], 'the order\'s amount is charged as it is, never converted');
	pg_assert_same(array('card', 'ideal'), $client['payment_method_types'], 'iDEAL is offered by the order\'s euro');

	$base = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_order_currency_test_order(array('charged_currency' => '')), 4714, $settings))['client'];
	pg_assert_same('try', $base['currency']);
	pg_assert_same(array('card'), $base['payment_method_types'], 'a lira order is offered no euro method');

	pg_payment_stripe_authorize_with(payment_stripe_order_currency_test_order(), 4714, array(
		'token'      => 'ctoken_1Xyz',
		'return_url' => 'https://shop.example/express_order.php?mode=stripe_return',
	), $settings, payment_stripe_order_currency_test_http());
	$params = payment_stripe_order_currency_test_calls()[0]['params'];

	pg_assert_same('eur', $params['currency']);
	pg_assert_same(4714, $params['amount']);
	pg_assert_same(array('card', 'ideal'), $params['payment_method_types'], 'the intent and the Payment Element agree');

	// A zero-decimal currency: the order's hundredths are whole yen.
	$yen = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_order_currency_test_order(array('charged_currency' => 'JPY')), 4700, $settings))['client'];
	pg_assert_same('jpy', $yen['currency']);
	pg_assert_same(47, $yen['amount']);
}

function test_payment_stripe_return_checks_the_order_currency()
{
	$settings = payment_stripe_order_currency_test_settings();
	$state = array('payment_intent' => 'pi_3Abc', 'order_id' => '438', 'amount' => 4714);
	$intent = array(
		'id'             => 'pi_3Abc',
		'object'         => 'payment_intent',
		'amount'         => 4714,
		'currency'       => 'eur',
		'status'         => 'succeeded',
		'metadata'       => array('order_id' => '438'),
		'payment_method' => array('id' => 'pm_1', 'card' => array('brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030)),
	);

	$paid = pg_payment_answer('complete', pg_payment_stripe_complete_with(payment_stripe_order_currency_test_order(), 4714, array(), $state, $settings, payment_stripe_order_currency_test_http(array(
		'GET /v1/payment_intents/pi_3Abc' => array(200, $intent),
	))));
	pg_assert_same('paid', $paid['status'], 'a euro payment completes a euro order');

	$other = pg_payment_answer('complete', pg_payment_stripe_complete_with(payment_stripe_order_currency_test_order(array('charged_currency' => '')), 4714, array(), $state, $settings, payment_stripe_order_currency_test_http(array(
		'GET /v1/payment_intents/pi_3Abc'         => array(200, $intent),
		'POST /v1/refunds'                        => array(200, array('id' => 're_1', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 4714)),
	))));
	pg_assert_false($other['success'], 'a euro payment never completes a lira order');
}

function test_payment_stripe_refund_is_in_the_order_currency()
{
	$settings = payment_stripe_order_currency_test_settings();

	$answer = pg_payment_answer('refund', pg_payment_stripe_refund_with(payment_stripe_order_currency_test_order(array('charged_currency' => 'JPY')), 1200, $settings, payment_stripe_order_currency_test_http(array(
		'POST /v1/refunds' => array(200, array('id' => 're_9', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 12)),
	))));

	pg_assert_same(12, payment_stripe_order_currency_test_calls()[0]['params']['amount'], 'twelve yen, counted in the order\'s currency');
	pg_assert_same('refunded', $answer['status']);
	pg_assert_same(1200, $answer['amount'], 'Stripe\'s yen read back as the order\'s hundredths');
}

/* ---------------------------------------------------------------------------
   The switches and the checkout's methods
   --------------------------------------------------------------------------- */

function test_payment_stripe_method_fields_for_the_store_currencies()
{
	$ideal = function ($currencies) {
		foreach (pg_payment_fields_normalize(pg_payment_stripe_method_fields($currencies)) as $field) {
			if ($field['name'] === 'method_ideal') {
				return $field;
			}
		}

		return null;
	};

	pg_assert_same('', $ideal(array('TRY', 'EUR'))['unavailable'], 'a euro market lets iDEAL be switched on in a lira store');
	pg_assert_same(lang(array('string' => 'Currencies: {var:1}. Off: the store\'s base currency is {var:2}.', 'vars' => array('EUR', 'TRY'))), $ideal(array('TRY'))['unavailable']);
	pg_assert_same(lang(array('string' => 'Currencies: {var:1}. Off: the store charges in {var:2}.', 'vars' => array('EUR', 'TRY, USD'))), $ideal(array('try', 'usd'))['unavailable']);
	pg_assert_same($ideal('EUR'), $ideal(array('EUR')), 'one currency as a string as before');
	pg_assert_same('', $ideal(array())['unavailable'], 'no currency known: nothing drawn off');
}

function test_payment_method_takes_currency_decide()
{
	$cases = array(
		'base order, legacy card'       => array('Credit/Debit Card', false, '', 'TRY', true),
		'base code named'               => array('PayPal Express Checkout', false, 'try', 'TRY', true),
		'market order, Stripe card'     => array('Credit/Debit Card', true, 'EUR', 'TRY', true),
		'market order, legacy card'     => array('Credit/Debit Card', false, 'EUR', 'TRY', false),
		'market order, PayPal Express'  => array('PayPal Express Checkout', false, 'EUR', 'TRY', false),
		'market order, a driver method' => array('PayTR', false, 'EUR', 'TRY', false),
		'market order, offline'         => array('Offline Payment', false, 'EUR', 'TRY', true),
	);

	foreach ($cases as $label => $case) {
		pg_assert_same($case[4], pg_payment_method_takes_currency_decide($case[0], $case[1], $case[2], $case[3]), $label);
	}

	pg_assert_true(in_array('order_currency', pg_payment_stripe_capabilities(), true), 'Stripe charges the order\'s currency');
	pg_assert_true(in_array('order_currency', pg_payment_capability_names(), true));
}
