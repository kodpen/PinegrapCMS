<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Stripe driver (includes/payments/stripe.php) and what the
 * checkout asks of it: the requests made to Stripe (paths, parameters, the
 * idempotency key, the pinned API version), how each PaymentIntent status is
 * answered, the 3-D Secure return, refund and cancel, amounts in Stripe's
 * units, the return modes the checkout screens recognise, the hosted card
 * driver and the security policy's sources. Stripe is never reached: a
 * stand-in takes the place of the HTTP caller and answers by method and path.
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
require_once(PG_FUNCTIONS_DIR . '/waf.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/update.php');

/* ---------------------------------------------------------------------------
   Fixtures
   --------------------------------------------------------------------------- */

// Stands in for Stripe: answers by "METHOD path", records every call.
function payment_stripe_test_http($answers = array())
{
	$GLOBALS['payment_stripe_test_calls'] = array();
	$GLOBALS['payment_stripe_test_answers'] = $answers;

	return function ($secret_key, $method, $path, $params, $idempotency_key) {
		$GLOBALS['payment_stripe_test_calls'][] = array(
			'key'         => $secret_key,
			'method'      => $method,
			'path'        => $path,
			'params'      => $params,
			'idempotency' => $idempotency_key,
		);

		$answers = $GLOBALS['payment_stripe_test_answers'];
		$name = $method . ' ' . $path;

		if (!isset($answers[$name])) {
			return array('status' => 0, 'body' => null, 'error' => 'Could not resolve host: api.stripe.com');
		}

		$answer = $answers[$name];

		return array('status' => isset($answer[0]) ? $answer[0] : 200, 'body' => isset($answer[1]) ? $answer[1] : null, 'error' => '');
	};
}

function payment_stripe_test_calls()
{
	return $GLOBALS['payment_stripe_test_calls'];
}

function payment_stripe_test_settings($changes = array())
{
	return array_merge(array(
		'publishable_key' => 'pk_test_123',
		'secret_key'      => 'sk_test_456',
		'link'            => false,
		'currency'        => 'try',
		'capture'         => 'automatic',
		'locale'          => 'tr',
	), $changes);
}

function payment_stripe_test_order($changes = array())
{
	return array_merge(array(
		'id'                    => '438',
		'reference_code'        => 'AB12CD34EF',
		'billing_email_address' => 'ayse@example.com',
		'transaction_id'        => 'pi_3Abc',
	), $changes);
}

function payment_stripe_test_payment($changes = array())
{
	return array_merge(array(
		'token'      => 'ctoken_1Xyz',
		'return_url' => 'https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023',
	), $changes);
}

function payment_stripe_test_intent($changes = array())
{
	return array_merge(array(
		'id'             => 'pi_3Abc',
		'object'         => 'payment_intent',
		'amount'         => 4714,
		'currency'       => 'try',
		'status'         => 'succeeded',
		'metadata'       => array('order_id' => '438', 'reference_code' => 'AB12CD34EF'),
		'payment_method' => array('id' => 'pm_1', 'card' => array('brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030)),
	), $changes);
}

function payment_stripe_test_authorize($answers, $settings = array(), $payment = array(), $amount = 4714)
{
	return pg_payment_answer('authorize', pg_payment_stripe_authorize_with(payment_stripe_test_order(), $amount, payment_stripe_test_payment($payment), payment_stripe_test_settings($settings), payment_stripe_test_http($answers)));
}

/* ---------------------------------------------------------------------------
   The driver in the registry
   --------------------------------------------------------------------------- */

function test_payment_stripe_driver_is_shipped()
{
	pg_assert_same('stripe', pg_payment_driver_for_gateway('Stripe PaymentIntents'));
	pg_assert_same('', pg_payment_driver_for_gateway('Stripe'), 'the classic value stays the Charges code');
	pg_assert_true(in_array('Stripe', pg_payment_legacy_gateways(), true));
	pg_assert_false(in_array('Stripe PaymentIntents', pg_payment_legacy_gateways(), true));
	pg_assert_same('connect', pg_payment_driver('stripe')['settings'], 'the keys live in connect_apps');
	pg_assert_same('Stripe', pg_payment_log_name('stripe'));

	foreach (array('prepare', 'authorize', 'complete', 'refund', 'cancel', 'hosted_fields', 'threeds', 'wallets', 'partial_refund') as $what) {
		pg_assert_true(pg_payment_can('stripe', $what), $what);
	}

	pg_assert_false(pg_payment_can('stripe', 'installments'));

	$names = array_map(function ($field) {
		return $field['name'] . ':' . $field['type'] . ($field['required'] ? '!' : '');
	}, pg_payment_fields('stripe'));
	pg_assert_same(array('publishable_key:text!', 'secret_key:secret!', 'webhook_secret:secret', 'link:switch'), $names);

	pg_assert_contains('<option value="Stripe PaymentIntents">Stripe</option>', pg_payment_settings_gateway_options('Iyzipay'));
	pg_assert_contains('selected="selected"', pg_payment_settings_gateway_options('Stripe PaymentIntents'));
}

function test_payment_stripe_hosted_card_driver()
{
	pg_assert_same('stripe', pg_payment_hosted_card_driver('Stripe PaymentIntents'));
	pg_assert_same('', pg_payment_hosted_card_driver('Iyzipay'), 'iyzico takes the site\'s own card inputs');
	pg_assert_same('', pg_payment_hosted_card_driver('Stripe'));
	pg_assert_same('', pg_payment_hosted_card_driver(''));
}

function test_payment_stripe_return_modes()
{
	$modes = pg_payment_return_modes();

	pg_assert_same('iyzipay_threedsecure_return', $modes['iyzico'], 'the shipped addresses stay');
	pg_assert_same('pay_with_iyzico_return', $modes['pay_with_iyzico']);
	pg_assert_same('stripe_return', $modes['stripe']);
	pg_assert_same('stripe_return', pg_payment_return_mode('stripe'));

	foreach (array('paypal_express_checkout_return', 'iyzipay_threedsecure_return', 'pay_with_iyzico_return', 'stripe_return') as $mode) {
		pg_assert_true(pg_payment_is_return_mode($mode), $mode);
	}

	foreach (array('', 'preview', 'nosuch_return', 'STRIPE_RETURN') as $mode) {
		pg_assert_false(pg_payment_is_return_mode($mode), $mode);
	}

	pg_assert_same('Credit/Debit Card', pg_payment_return_method('iyzipay_threedsecure_return'));
	pg_assert_same('Pay With Iyzico', pg_payment_return_method('pay_with_iyzico_return'));
	pg_assert_same('Credit/Debit Card', pg_payment_return_method('stripe_return'));
	pg_assert_same('', pg_payment_return_method('paypal_express_checkout_return'), 'PayPal Express sets its own method');
	pg_assert_same('', pg_payment_return_method(''));

	pg_assert_same('stripe', pg_payment_checkout_driver('Credit/Debit Card', 'Stripe PaymentIntents', 'stripe_return'), 'the return is finished by complete()');
	pg_assert_same('stripe', pg_payment_checkout_driver('Credit/Debit Card', 'Stripe PaymentIntents', ''));

	// A driver that cannot finish a return gets no return mode: the mode
	// would skip the form token and the field checks with nothing to take
	// the payment.
	$key = 'pgtestnoreturn';

	if (pg_payment_driver($key) === null) {
		$file = sys_get_temp_dir() . '/pg_payment_' . $key . '_' . getmypid() . '.php';
		file_put_contents($file, implode("\n", array(
			'<?php',
			'function pg_payment_' . $key . '_capabilities() { return array(); }',
			'function pg_payment_' . $key . '_authorize($order, $amount, $payment) { return array(\'success\' => false); }',
			'',
		)));
		pg_assert_true(pg_payment_driver_register($key, array('label' => 'No Return', 'kind' => 'card', 'gateway' => 'Pg Test No Return', 'file' => $file)));
		pg_assert_true(pg_payment_can($key, 'authorize'), 'the driver loads');
		@unlink($file);
	}

	pg_assert_false(pg_payment_can($key, 'complete'));
	pg_assert_same('', pg_payment_return_mode($key), 'no complete(), no return mode');
	pg_assert_false(pg_payment_is_return_mode($key . '_return'));
	pg_assert_same('stripe_return', pg_payment_return_mode('stripe'), 'the others are kept');
}

function test_payment_stripe_security_policy_sources()
{
	$built_in = "default-src 'self' blob:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; connect-src 'self' https://cdn.jsdelivr.net; object-src 'none'; base-uri 'self'; frame-ancestors 'self'";

	pg_assert_same(
		"default-src 'self' blob:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://js.stripe.com https://*.js.stripe.com; connect-src 'self' https://cdn.jsdelivr.net https://api.stripe.com https://link.com https://*.link.com; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; frame-src 'self' blob: https://js.stripe.com https://*.js.stripe.com https://hooks.stripe.com https://link.com https://*.link.com",
		waf_csp_add_payment_sources($built_in, 'Stripe PaymentIntents', true));

	pg_assert_same($built_in, waf_csp_add_payment_sources($built_in, 'Stripe PaymentIntents', false), 'cards off');
	pg_assert_same($built_in, waf_csp_add_payment_sources($built_in, 'Iyzipay', true), 'a driver that loads nothing in the page');
	pg_assert_same($built_in, waf_csp_add_payment_sources($built_in, 'Stripe', true), 'the classic code loads nothing either');

	pg_assert_same(
		"script-src 'self' https://js.stripe.com https://*.js.stripe.com; frame-src 'self' https://js.stripe.com https://*.js.stripe.com https://hooks.stripe.com https://link.com https://*.link.com; connect-src https://api.stripe.com https://link.com https://*.link.com",
		waf_csp_add_payment_sources("script-src 'self'; frame-src 'self'", 'Stripe PaymentIntents', true),
		'an existing frame-src is added to');

	$hostile = pg_payment_driver_definition_normalize('csptest', array('label' => 'X', 'kind' => 'card', 'gateway' => 'X', 'file' => '/srv/x.php', 'csp' => array(
		'script-src'  => array('https://ok.example.com', "https://x.example.com; script-src *", 'http://plain.example.com', 'https://*.cdn.example.com'),
		'img-src'     => array('https://img.example.com'),
	)), '');
	pg_assert_same(array('script-src' => array('https://ok.example.com', 'https://*.cdn.example.com')), $hostile['csp'], 'only https origins, only the payment directives');
}

/* ---------------------------------------------------------------------------
   Amounts and encoding
   --------------------------------------------------------------------------- */

function test_payment_stripe_amounts_in_stripes_units()
{
	pg_assert_same(4714, pg_payment_stripe_amount(4714, 'try'));
	pg_assert_same(4714, pg_payment_stripe_amount(4714, 'USD'));
	pg_assert_same(1200, pg_payment_stripe_amount(120000, 'jpy'), 'zero-decimal');
	pg_assert_same(1201, pg_payment_stripe_amount(120050, 'jpy'), 'rounded, not cut');
	pg_assert_same(47140, pg_payment_stripe_amount(4714, 'kwd'), 'three-decimal, ending in 0');
	pg_assert_same(1999, pg_payment_stripe_amount(1998.9999, 'try'));

	pg_assert_same(4714, pg_payment_stripe_hundredths(4714, 'try'));
	pg_assert_same(120000, pg_payment_stripe_hundredths(1200, 'jpy'));
	pg_assert_same(4714, pg_payment_stripe_hundredths(47140, 'kwd'));
	pg_assert_same(4714, pg_payment_stripe_hundredths('4714', 'try'));
	pg_assert_same(null, pg_payment_stripe_hundredths('47.14', 'try'));
	pg_assert_same(null, pg_payment_stripe_hundredths(null, 'try'));
}

function test_payment_stripe_form_encoding()
{
	pg_assert_same(
		'amount=4714&confirm=true&off=false&payment_method_types%5B0%5D=card&payment_method_types%5B1%5D=link&metadata%5Border_id%5D=438',
		pg_payment_stripe_encode(array('amount' => 4714, 'confirm' => true, 'off' => false, 'payment_method_types' => array('card', 'link'), 'metadata' => array('order_id' => '438'))));
	pg_assert_same('', pg_payment_stripe_encode(array()));
	pg_assert_same('2024-06-20', pg_payment_stripe_api_version());
}

function test_payment_stripe_request_options()
{
	$env = array('user_agent' => 'Pinegrap', 'proxy' => '', 'resolve' => '');

	$get = pg_payment_stripe_request_options('sk_test_1', 'GET', '/v1/payment_intents/pi_1', array('expand' => array('latest_charge')), '');
	pg_assert_same('GET', $get['method']);
	pg_assert_same('https://api.stripe.com/v1/payment_intents/pi_1?expand%5B0%5D=latest_charge', $get['url']);
	pg_assert_same(array(
		'userpwd'         => 'sk_test_1:',
		'headers'         => array('Stripe-Version: 2024-06-20', 'Content-Type: application/x-www-form-urlencoded'),
		'body'            => null,
		'timeout'         => 60,
		'connect_timeout' => 15,
	), $get['options']);
	$curl = pg_curl_run_options($get['method'], $get['url'], $get['options'], $env);
	pg_assert_false(isset($curl['CURLOPT_POST']) || isset($curl['CURLOPT_CUSTOMREQUEST']) || isset($curl['CURLOPT_POSTFIELDS']), 'GET carries no body');
	pg_assert_same('CURLPROTO_HTTPS', $curl['CURLOPT_PROTOCOLS']);

	$post = pg_payment_stripe_request_options('sk_test_1', 'POST', '/v1/payment_intents', array('amount' => 4714), 'order-438');
	pg_assert_same('https://api.stripe.com/v1/payment_intents', $post['url']);
	pg_assert_same('amount=4714', $post['options']['body']);
	pg_assert_same('Idempotency-Key: order-438', $post['options']['headers'][2]);

	// A POST with no parameters still goes out as a POST with an empty body.
	$empty = pg_payment_stripe_request_options('sk_test_1', 'POST', '/v1/payment_intents/pi_1/cancel', array(), '');
	$curl = pg_curl_run_options($empty['method'], $empty['url'], $empty['options'], $env);
	pg_assert_same(true, $curl['CURLOPT_POST']);
	pg_assert_same('', $curl['CURLOPT_POSTFIELDS']);
	pg_assert_false(isset($curl['CURLOPT_CUSTOMREQUEST']), 'not a bare custom request');
	pg_assert_same('sk_test_1:', $curl['CURLOPT_USERPWD']);
	pg_assert_same(60, $curl['CURLOPT_TIMEOUT']);
	pg_assert_same(15, $curl['CURLOPT_CONNECTTIMEOUT']);

	pg_assert_same('GET', pg_payment_stripe_request_options('sk_test_1', 'DELETE', '/v1/x', array(), '')['method']);
}

/* ---------------------------------------------------------------------------
   prepare()
   --------------------------------------------------------------------------- */

function test_payment_stripe_prepare_gives_the_page_what_it_needs()
{
	$answer = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_test_order(), 4714, payment_stripe_test_settings()));

	pg_assert_true($answer['success']);
	pg_assert_same(array(
		'script'               => 'https://js.stripe.com/v3/',
		'publishable_key'      => 'pk_test_123',
		'amount'               => 4714,
		'currency'             => 'try',
		'payment_method_types' => array('card'),
		'capture_method'       => 'automatic',
		'locale'               => 'tr',
	), $answer['client']);
	pg_assert_false(strpos(json_encode($answer), 'sk_test') !== false, 'the secret key never reaches the page');

	$link = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_test_order(), 4714, payment_stripe_test_settings(array('link' => true))));
	pg_assert_same(array('card', 'link'), $link['client']['payment_method_types']);

	pg_assert_false(pg_payment_answer('prepare', pg_payment_stripe_prepare_with(array(), 4714, payment_stripe_test_settings(array('publishable_key' => ''))))['success'], 'no publishable key');
	pg_assert_false(pg_payment_answer('prepare', pg_payment_stripe_prepare_with(array(), 4714, payment_stripe_test_settings(array('secret_key' => ''))))['success'], 'no secret key');
	pg_assert_false(pg_payment_answer('prepare', pg_payment_stripe_prepare_with(array(), 0, payment_stripe_test_settings()))['success'], 'nothing to pay');
}

// Stripe refuses a confirmation when the Payment Element was built with
// another capture method or other payment method types than the intent.
function test_payment_stripe_page_and_intent_agree()
{
	foreach (array('automatic', 'manual') as $capture) {
		foreach (array(false, true) as $link) {
			$settings = array('capture' => $capture, 'link' => $link);
			$label = $capture . ($link ? ' with Link' : '');
			$client = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_test_order(), 4714, payment_stripe_test_settings($settings)))['client'];

			payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(200, payment_stripe_test_intent())), $settings);
			$calls = payment_stripe_test_calls();

			pg_assert_same($capture, $client['capture_method'], $label);
			pg_assert_same($calls[0]['params']['capture_method'], $client['capture_method'], $label);
			pg_assert_same($calls[0]['params']['payment_method_types'], $client['payment_method_types'], $label);
			pg_assert_same($calls[0]['params']['amount'], $client['amount'], $label);
			pg_assert_same($calls[0]['params']['currency'], $client['currency'], $label);
		}
	}
}

/* ---------------------------------------------------------------------------
   authorize()
   --------------------------------------------------------------------------- */

function test_payment_stripe_authorize_creates_and_confirms_the_intent()
{
	$answer = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(200, payment_stripe_test_intent())));
	$calls = payment_stripe_test_calls();

	pg_assert_same(1, count($calls));
	pg_assert_same('sk_test_456', $calls[0]['key']);
	pg_assert_same(array(
		'amount'               => 4714,
		'currency'             => 'try',
		'confirm'              => true,
		'confirmation_token'   => 'ctoken_1Xyz',
		'payment_method_types' => array('card'),
		'capture_method'       => 'automatic',
		'metadata'             => array('order_id' => '438', 'reference_code' => 'AB12CD34EF'),
		'expand'               => array('payment_method'),
		'description'          => 'ayse@example.com',
		'return_url'           => 'https://shop.example/pinegrap/express_order.php?mode=stripe_return&page_id=1023',
	), $calls[0]['params']);
	pg_assert_same('pg-pi-' . sha1('438|ctoken_1Xyz|4714'), $calls[0]['idempotency'], 'a second post of the same token is the same request');

	pg_assert_true($answer['success']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('pi_3Abc', $answer['transaction_id']);
	pg_assert_same(4714, $answer['amount']);
	pg_assert_same('Visa', $answer['card_brand']);
	pg_assert_same('4242', $answer['card_last4']);
	pg_assert_same('12', $answer['card_exp_month']);
	pg_assert_same('2030', $answer['card_exp_year']);
	pg_assert_same(array(), $answer['state'], 'nothing to wait for');

	$held = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(200, payment_stripe_test_intent(array('status' => 'requires_capture')))), array('capture' => 'manual', 'link' => true));
	$calls = payment_stripe_test_calls();
	pg_assert_same('manual', $calls[0]['params']['capture_method'], 'Transaction Type Authorize holds the money');
	pg_assert_same(array('card', 'link'), $calls[0]['params']['payment_method_types']);
	pg_assert_same('authorized', $held['status']);

	$settling = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(200, payment_stripe_test_intent(array('status' => 'processing')))));
	pg_assert_same('pending', $settling['status']);
}

function test_payment_stripe_authorize_sends_the_visitor_to_3d_secure()
{
	$intent = payment_stripe_test_intent(array(
		'status'      => 'requires_action',
		'next_action' => array('type' => 'redirect_to_url', 'redirect_to_url' => array('url' => 'https://hooks.stripe.com/3d_secure_2/hosted?x=1', 'return_url' => 'https://shop.example/r')),
	));

	$answer = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(200, $intent)));

	pg_assert_true($answer['success']);
	pg_assert_same('redirect', $answer['status']);
	pg_assert_same('https://hooks.stripe.com/3d_secure_2/hosted?x=1', $answer['redirect_url']);
	pg_assert_same(array('payment_intent' => 'pi_3Abc', 'order_id' => '438', 'amount' => 4714), $answer['state'], 'what complete() checks the return against');

	// A step the checkout cannot show: the intent is let go, the visitor told.
	$other = payment_stripe_test_intent(array('status' => 'requires_action', 'next_action' => array('type' => 'use_stripe_sdk')));
	$refused = payment_stripe_test_authorize(array(
		'POST /v1/payment_intents'             => array(200, $other),
		'GET /v1/payment_intents/pi_3Abc'      => array(200, $other),
		'POST /v1/payment_intents/pi_3Abc/cancel' => array(200, array_merge($other, array('status' => 'canceled'))),
	));
	$paths = array_map(function ($call) { return $call['method'] . ' ' . $call['path']; }, payment_stripe_test_calls());

	pg_assert_false($refused['success']);
	pg_assert_same(lang('This payment needs a step this checkout cannot show. Please choose another payment method.'), $refused['error']);
	pg_assert_same(array('POST /v1/payment_intents', 'GET /v1/payment_intents/pi_3Abc', 'POST /v1/payment_intents/pi_3Abc/cancel'), $paths);
}

function test_payment_stripe_authorize_refusals()
{
	$declined = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(402, array('error' => array(
		'type'         => 'card_error',
		'code'         => 'card_declined',
		'decline_code' => 'insufficient_funds',
		'message'      => 'Your card has insufficient funds.',
	)))));

	pg_assert_false($declined['success']);
	pg_assert_same(lang('Your card has insufficient funds.'), $declined['error'], 'the site\'s own sentence for a known reason');
	pg_assert_same(array('Stripe payment for order #438 was not accepted: Your card has insufficient funds. (insufficient_funds).'), $declined['log']);
	pg_assert_same('', $declined['transaction_id']);

	$unknown = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(400, array('error' => array('type' => 'invalid_request_error', 'message' => 'No such confirmationtoken: ctoken_1Xyz')))));
	pg_assert_same('No such confirmationtoken: ctoken_1Xyz', $unknown['error'], 'Stripe\'s words for anything else');

	$unreached = payment_stripe_test_authorize(array());
	pg_assert_false($unreached['success']);
	pg_assert_same(lang('The payment service could not be reached.'), $unreached['error'], 'the transport\'s words stay in the log');
	pg_assert_same(array('Stripe payment for order #438 was not accepted: Could not resolve host: api.stripe.com (unreached).'), $unreached['log']);

	$failed = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(200, payment_stripe_test_intent(array(
		'status'             => 'requires_payment_method',
		'last_payment_error' => array('code' => 'card_declined', 'decline_code' => 'generic_decline', 'message' => 'Your card was declined.'),
	)))));
	pg_assert_same(lang('Your card was declined.'), $failed['error']);

	foreach (array('', 'tok_visa', 'ctoken_', 'ctoken_a b', array('ctoken_1')) as $token) {
		$answer = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(200, payment_stripe_test_intent())), array(), array('token' => $token));

		pg_assert_false($answer['success'], json_encode($token));
		pg_assert_same(lang('Please enter your card details.'), $answer['error']);
		pg_assert_same(array(), payment_stripe_test_calls(), 'Stripe is not asked');
	}

	$no_key = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(200, payment_stripe_test_intent())), array('secret_key' => ''));
	pg_assert_false($no_key['success']);
	pg_assert_same(array(), payment_stripe_test_calls());
}

function test_payment_stripe_an_intent_for_another_order_is_not_believed()
{
	$cases = array(
		'another order'    => payment_stripe_test_intent(array('metadata' => array('order_id' => '439'))),
		'another amount'   => payment_stripe_test_intent(array('amount' => 4700)),
		'another currency' => payment_stripe_test_intent(array('currency' => 'usd')),
		'not an intent'    => payment_stripe_test_intent(array('object' => 'charge')),
		'no id'            => payment_stripe_test_intent(array('id' => '')),
	);

	foreach ($cases as $label => $intent) {
		$answer = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(200, $intent)));

		pg_assert_false($answer['success'], $label);
		pg_assert_same('', $answer['transaction_id'], $label);
	}
}

/* ---------------------------------------------------------------------------
   complete()
   --------------------------------------------------------------------------- */

function test_payment_stripe_complete_reads_the_intent_again()
{
	$state = array('payment_intent' => 'pi_3Abc', 'order_id' => '438', 'amount' => 4714);
	$http = payment_stripe_test_http(array('GET /v1/payment_intents/pi_3Abc' => array(200, payment_stripe_test_intent())));

	$answer = pg_payment_answer('complete', pg_payment_stripe_complete_with(payment_stripe_test_order(), 4714, array('payment_intent' => 'pi_OTHER'), $state, payment_stripe_test_settings(), $http));
	$calls = payment_stripe_test_calls();

	pg_assert_same(1, count($calls));
	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same('/v1/payment_intents/pi_3Abc', $calls[0]['path'], 'the intent this checkout started, not the one the address names');
	pg_assert_same(array('expand' => array('payment_method')), $calls[0]['params']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('4242', $answer['card_last4']);
	pg_assert_same(array(), $answer['state'], 'the wait is over');

	$unfinished = pg_payment_answer('complete', pg_payment_stripe_complete_with(payment_stripe_test_order(), 4714, array(), $state, payment_stripe_test_settings(), payment_stripe_test_http(array(
		'GET /v1/payment_intents/pi_3Abc'         => array(200, payment_stripe_test_intent(array('status' => 'requires_action', 'next_action' => array('type' => 'redirect_to_url', 'redirect_to_url' => array('url' => 'https://hooks.stripe.com/x'))))),
		'POST /v1/payment_intents/pi_3Abc/cancel' => array(200, payment_stripe_test_intent(array('status' => 'canceled'))),
	))));

	pg_assert_false($unfinished['success'], 'back without finishing 3-D Secure');
	pg_assert_same(lang('Your card could not be verified. Please try again or use another card.'), $unfinished['error']);
	pg_assert_same('POST /v1/payment_intents/pi_3Abc/cancel', payment_stripe_test_calls()[2]['method'] . ' ' . payment_stripe_test_calls()[2]['path']);

	$failed = pg_payment_answer('complete', pg_payment_stripe_complete_with(payment_stripe_test_order(), 4714, array(), $state, payment_stripe_test_settings(), payment_stripe_test_http(array(
		'GET /v1/payment_intents/pi_3Abc' => array(200, payment_stripe_test_intent(array('status' => 'requires_payment_method', 'last_payment_error' => array('code' => 'payment_intent_authentication_failure', 'message' => 'We are unable to authenticate your payment method.')))),
	))));
	pg_assert_same(lang('Your card could not be verified. Please try again or use another card.'), $failed['error']);
}

function test_payment_stripe_complete_without_its_state_asks_nothing()
{
	$cases = array(
		'no state'          => array(),
		'another order'     => array('payment_intent' => 'pi_3Abc', 'order_id' => '439', 'amount' => 4714),
		'not an intent id'  => array('payment_intent' => 'ch_3Abc', 'order_id' => '438', 'amount' => 4714),
	);

	foreach ($cases as $label => $state) {
		$answer = pg_payment_answer('complete', pg_payment_stripe_complete_with(payment_stripe_test_order(), 4714, array(), $state, payment_stripe_test_settings(), payment_stripe_test_http(array('GET /v1/payment_intents/pi_3Abc' => array(200, payment_stripe_test_intent())))));

		pg_assert_false($answer['success'], $label);
		pg_assert_same(array(), payment_stripe_test_calls(), $label . ': Stripe is not asked');
		pg_assert_same($state, $answer['state'], $label . ': the real return can still finish it');
	}
}

function test_payment_stripe_complete_gives_back_a_payment_for_another_total()
{
	$state = array('payment_intent' => 'pi_3Abc', 'order_id' => '438', 'amount' => 4714);

	$answer = pg_payment_answer('complete', pg_payment_stripe_complete_with(payment_stripe_test_order(), 5000, array(), $state, payment_stripe_test_settings(), payment_stripe_test_http(array(
		'GET /v1/payment_intents/pi_3Abc' => array(200, payment_stripe_test_intent()),
		'POST /v1/refunds'                => array(200, array('id' => 're_1', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 4714)),
	))));
	$calls = payment_stripe_test_calls();

	pg_assert_false($answer['success']);
	pg_assert_same(lang('The order total changed while the payment was being verified. The payment was cancelled. Please review your order and try again.'), $answer['error']);
	pg_assert_same('POST /v1/refunds', $calls[2]['method'] . ' ' . $calls[2]['path'], 'a payment that took money is refunded');
	pg_assert_same(array('payment_intent' => 'pi_3Abc', 'reason' => 'requested_by_customer'), $calls[2]['params']);
	pg_assert_same(array(
		'Stripe payment pi_3Abc for order #438 was for 47.14, the order now totals 50.00.',
		'Stripe payment pi_3Abc was refunded in full (refund re_1).',
	), $answer['log']);

	$held = pg_payment_answer('complete', pg_payment_stripe_complete_with(payment_stripe_test_order(), 5000, array(), $state, payment_stripe_test_settings(), payment_stripe_test_http(array(
		'GET /v1/payment_intents/pi_3Abc'         => array(200, payment_stripe_test_intent(array('status' => 'requires_capture'))),
		'POST /v1/payment_intents/pi_3Abc/cancel' => array(400, array('error' => array('message' => 'This PaymentIntent could not be canceled.'))),
	))));

	pg_assert_false($held['success']);
	pg_assert_same(lang('The order total changed while the payment was being verified, and the payment could not be undone automatically. The store will refund it. Please review your order before paying again.'), $held['error'], 'the visitor is not told a held payment was cancelled');
	pg_assert_same('Stripe payment pi_3Abc could not be cancelled. MANUAL REFUND REQUIRED. Gateway message: This PaymentIntent could not be canceled.', $held['log'][1]);
}

/* ---------------------------------------------------------------------------
   refund() and cancel()
   --------------------------------------------------------------------------- */

function test_payment_stripe_refund()
{
	$answer = pg_payment_answer('refund', pg_payment_stripe_refund_with(payment_stripe_test_order(), 1250, payment_stripe_test_settings(), payment_stripe_test_http(array(
		'POST /v1/refunds' => array(200, array('id' => 're_9', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 1250)),
	))));
	$calls = payment_stripe_test_calls();

	pg_assert_same(array('payment_intent' => 'pi_3Abc', 'amount' => 1250, 'metadata' => array('order_id' => '438')), $calls[0]['params']);
	pg_assert_same('refunded', $answer['status']);
	pg_assert_same(1250, $answer['amount']);
	pg_assert_same('re_9', $answer['refund_reference']);

	$classic = pg_payment_answer('refund', pg_payment_stripe_refund_with(payment_stripe_test_order(array('transaction_id' => 'ch_1Old')), 1250, payment_stripe_test_settings(), payment_stripe_test_http(array(
		'POST /v1/refunds' => array(200, array('id' => 're_8', 'object' => 'refund', 'status' => 'pending', 'amount' => 1250)),
	))));
	pg_assert_same('ch_1Old', payment_stripe_test_calls()[0]['params']['charge'], 'a classic charge is refunded by its charge');
	pg_assert_same('pending', $classic['status']);

	$refused = pg_payment_answer('refund', pg_payment_stripe_refund_with(payment_stripe_test_order(), 99999, payment_stripe_test_settings(), payment_stripe_test_http(array(
		'POST /v1/refunds' => array(400, array('error' => array('message' => 'Refund amount (999.99) is greater than unrefunded amount on charge (47.14)'))),
	))));
	pg_assert_false($refused['success']);
	pg_assert_same('Refund amount (999.99) is greater than unrefunded amount on charge (47.14)', $refused['error']);

	$not_stripe = pg_payment_answer('refund', pg_payment_stripe_refund_with(payment_stripe_test_order(array('transaction_id' => '24680')), 1250, payment_stripe_test_settings(), payment_stripe_test_http(array())));
	pg_assert_false($not_stripe['success']);
	pg_assert_same(array(), payment_stripe_test_calls(), 'another service\'s payment id is not sent to Stripe');
}

function test_payment_stripe_cancel()
{
	$cases = array(
		'held: cancelled' => array(
			array(
				'GET /v1/payment_intents/pi_3Abc'         => array(200, payment_stripe_test_intent(array('status' => 'requires_capture'))),
				'POST /v1/payment_intents/pi_3Abc/cancel' => array(200, payment_stripe_test_intent(array('status' => 'canceled'))),
			),
			'pi_3Abc',
			'POST /v1/payment_intents/pi_3Abc/cancel',
		),
		'taken: refunded in full' => array(
			array(
				'GET /v1/payment_intents/pi_3Abc' => array(200, payment_stripe_test_intent()),
				'POST /v1/refunds'                => array(200, array('id' => 're_5', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 4714)),
			),
			're_5',
			'POST /v1/refunds',
		),
		'already cancelled' => array(
			array('GET /v1/payment_intents/pi_3Abc' => array(200, payment_stripe_test_intent(array('status' => 'canceled')))),
			'pi_3Abc',
			'GET /v1/payment_intents/pi_3Abc',
		),
	);

	foreach ($cases as $label => $case) {
		$answer = pg_payment_answer('cancel', pg_payment_stripe_cancel_with(payment_stripe_test_order(), 'pi_3Abc', payment_stripe_test_settings(), payment_stripe_test_http($case[0])));
		$calls = payment_stripe_test_calls();
		$last = end($calls);

		pg_assert_true($answer['success'], $label);
		pg_assert_same('cancelled', $answer['status'], $label);
		pg_assert_same($case[1], $answer['refund_reference'], $label);
		pg_assert_same($case[2], $last['method'] . ' ' . $last['path'], $label);
	}

	$classic = pg_payment_answer('cancel', pg_payment_stripe_cancel_with(payment_stripe_test_order(), 'ch_1Old', payment_stripe_test_settings(), payment_stripe_test_http(array(
		'POST /v1/refunds' => array(200, array('id' => 're_7', 'object' => 'refund', 'status' => 'succeeded')),
	))));
	pg_assert_same('re_7', $classic['refund_reference']);
	pg_assert_same(array('charge' => 'ch_1Old', 'reason' => 'requested_by_customer'), payment_stripe_test_calls()[0]['params']);

	$refused = pg_payment_answer('cancel', pg_payment_stripe_cancel_with(payment_stripe_test_order(), 'pi_3Abc', payment_stripe_test_settings(), payment_stripe_test_http(array(
		'GET /v1/payment_intents/pi_3Abc'         => array(200, payment_stripe_test_intent(array('status' => 'requires_capture'))),
		'POST /v1/payment_intents/pi_3Abc/cancel' => array(400, array('error' => array('message' => 'You cannot cancel this PaymentIntent.'))),
	))));
	pg_assert_false($refused['success']);
	pg_assert_same('You cannot cancel this PaymentIntent.', $refused['raw']['message'], 'what the cancellation\'s log line quotes');

	$outcome = pg_payment_cancel_outcome(438, 'pi_3Abc', pg_payment_call('stripe', 'cancel', array(payment_stripe_test_order(), 'not-a-stripe-id')), pg_payment_log_name('stripe'));
	pg_assert_same('manual_required', $outcome['refund_status']);
	pg_assert_same(array('Auto-refund FAILED via Stripe — order #438. MANUAL REFUND REQUIRED. Gateway message: not a Stripe payment id: not-a-stripe-id'), $outcome['log']);
}

/* ---------------------------------------------------------------------------
   The answer's card fields
   --------------------------------------------------------------------------- */

function test_payment_stripe_card_fields_in_the_answer()
{
	$answer = pg_payment_answer('authorize', array('success' => true, 'status' => 'paid', 'card_last4' => '4242', 'card_exp_month' => '7', 'card_exp_year' => 2031));

	pg_assert_same('4242', $answer['card_last4']);
	pg_assert_same('7', $answer['card_exp_month']);
	pg_assert_same('2031', $answer['card_exp_year']);

	$odd = pg_payment_answer('authorize', array('success' => true, 'status' => 'paid', 'card_last4' => '4242424242424242', 'card_exp_month' => '13', 'card_exp_year' => '31'));

	pg_assert_same('', $odd['card_last4'], 'a whole card number is not four digits');
	pg_assert_same('', $odd['card_exp_month']);
	pg_assert_same('', $odd['card_exp_year']);

	pg_assert_same('MasterCard', pg_payment_stripe_card_brand('mastercard'));
	pg_assert_same('American Express', pg_payment_stripe_card_brand('amex'));
	pg_assert_same('Troy', pg_payment_stripe_card_brand('troy'));
	pg_assert_same('', pg_payment_stripe_card_brand('unknown'));
}

/* ---------------------------------------------------------------------------
   Store-run subscriptions
   --------------------------------------------------------------------------- */

function payment_stripe_test_subscription($changes = array())
{
	return array_merge(array(
		'id'             => 7,
		'user_id'        => 31,
		'order_id'       => 438,
		'driver'         => 'stripe',
		'status'         => 'active',
		'period'         => 'monthly',
		'amount'         => 9900,
		'currency'       => 'TRY',
		'next_charge_at' => 1793000000,
		'retry_count'    => 0,
		'card_last4'     => '4242',
		'card_ref'       => 'cus_Q1a|pm_1Old',
		'return_url'     => 'https://shop.example/account',
	), $changes);
}

function payment_stripe_test_renewal_intent($changes = array())
{
	return array_merge(array(
		'id'             => 'pi_3Ren',
		'object'         => 'payment_intent',
		'amount'         => 9900,
		'currency'       => 'try',
		'status'         => 'succeeded',
		'customer'       => 'cus_Q1a',
		'metadata'       => array('subscription_id' => '7'),
		'payment_method' => array('id' => 'pm_1Old', 'card' => array('brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030)),
	), $changes);
}

function payment_stripe_test_wallet($ids)
{
	return array(200, array('object' => 'list', 'data' => array_map(function ($id) {
		return array('id' => $id, 'object' => 'payment_method', 'customer' => 'cus_Q1a', 'card' => array('last4' => '4242'));
	}, $ids)));
}

function payment_stripe_test_renew($answers, $subscription = array(), $settings = array())
{
	return pg_payment_answer('charge_renewal', pg_payment_stripe_charge_renewal_with(payment_stripe_test_subscription($subscription), payment_stripe_test_settings($settings), payment_stripe_test_http($answers)));
}

function test_payment_stripe_runs_subscriptions_in_store_mode()
{
	pg_assert_same('store', pg_payment_recurring_mode('stripe'));
	pg_assert_true(pg_payment_can('stripe', 'recurring'));

	foreach (array('charge_renewal', 'update_card', 'subscription_cancel') as $operation) {
		pg_assert_true(pg_payment_can('stripe', $operation), $operation);
	}
}

function test_payment_stripe_card_ref()
{
	pg_assert_same('cus_Q1a|pm_1Old', pg_payment_stripe_card_ref('cus_Q1a', 'pm_1Old'));
	pg_assert_same(array('cus_Q1a', 'pm_1Old'), pg_payment_stripe_card_ref_parts('cus_Q1a|pm_1Old'));
	pg_assert_same('', pg_payment_stripe_card_ref('pm_1Old', 'cus_Q1a'), 'the halves in their own order only');
	pg_assert_same('', pg_payment_stripe_card_ref('cus_' . str_repeat('a', 120), 'pm_' . str_repeat('b', 40)), 'longer than a card_ref may be');
	pg_assert_same(null, pg_payment_stripe_card_ref_parts('cus_Q1a'));
	pg_assert_same(null, pg_payment_stripe_card_ref_parts('cus_Q1a|pm_1|x'));
	pg_assert_false(pg_subscription_card_ref_is_sealed('cus_Q1a|pm_1Old'), 'a plain card_ref is never taken for a sealed one');
}

function test_payment_stripe_prepare_keeps_the_card_for_a_recurring_order()
{
	$client = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_test_order(array('recurring' => true)), 4714, payment_stripe_test_settings()))['client'];

	pg_assert_same('off_session', $client['setup_future_usage']);

	$plain = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_test_order(), 4714, payment_stripe_test_settings()))['client'];
	pg_assert_false(isset($plain['setup_future_usage']), 'nothing kept for an order without a recurring line');
}

function test_payment_stripe_first_payment_stores_the_card()
{
	$answer = payment_stripe_test_authorize(array(
		'POST /v1/customers'       => array(200, array('id' => 'cus_Q1a', 'object' => 'customer')),
		'POST /v1/payment_intents' => array(200, payment_stripe_test_intent(array('customer' => 'cus_Q1a', 'setup_future_usage' => 'off_session'))),
	), array(), array('recurring' => true, 'customer_id' => 31));
	$calls = payment_stripe_test_calls();

	pg_assert_true($answer['success']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('cus_Q1a|pm_1', $answer['card_ref']);
	pg_assert_same('4242', $answer['card_last4']);
	pg_assert_same(2, count($calls));
	pg_assert_same('/v1/customers', $calls[0]['path']);
	pg_assert_same('ayse@example.com', $calls[0]['params']['email']);
	pg_assert_same('438', $calls[0]['params']['metadata']['order_id']);
	pg_assert_same('31', $calls[0]['params']['metadata']['user_id']);
	pg_assert_same(0, strpos($calls[0]['idempotency'], 'pg-cus-'));
	pg_assert_same('cus_Q1a', $calls[1]['params']['customer']);
	pg_assert_same('off_session', $calls[1]['params']['setup_future_usage']);
	pg_assert_false(strpos(json_encode($answer['log']), 'pm_1') !== false, 'the card is never logged');

	// Built with the same setup_future_usage as the intent, or Stripe refuses
	// the confirmation token.
	$client = pg_payment_answer('prepare', pg_payment_stripe_prepare_with(payment_stripe_test_order(array('recurring' => true)), 4714, payment_stripe_test_settings()))['client'];
	pg_assert_same($calls[1]['params']['setup_future_usage'], $client['setup_future_usage']);

	$plain = payment_stripe_test_authorize(array('POST /v1/payment_intents' => array(200, payment_stripe_test_intent())));
	$calls = payment_stripe_test_calls();
	pg_assert_same(1, count($calls), 'no customer without a recurring line');
	pg_assert_false(isset($calls[0]['params']['setup_future_usage']));
	pg_assert_same('', $plain['card_ref']);
}

function test_payment_stripe_first_payment_refused_when_no_customer()
{
	$answer = payment_stripe_test_authorize(array(
		'POST /v1/customers' => array(400, array('error' => array('type' => 'invalid_request_error', 'message' => 'Invalid email address'))),
	), array(), array('recurring' => true));

	pg_assert_false($answer['success']);
	pg_assert_same(1, count(payment_stripe_test_calls()), 'no payment without the customer the card is kept for');
}

function test_payment_stripe_3d_secure_return_answers_the_stored_card()
{
	$state = array('payment_intent' => 'pi_3Abc', 'order_id' => '438', 'amount' => 4714);
	$answer = pg_payment_answer('complete', pg_payment_stripe_complete_with(payment_stripe_test_order(), 4714, array(), $state, payment_stripe_test_settings(), payment_stripe_test_http(array(
		'GET /v1/payment_intents/pi_3Abc' => array(200, payment_stripe_test_intent(array('customer' => 'cus_Q1a', 'setup_future_usage' => 'off_session'))),
	))));

	pg_assert_same('paid', $answer['status']);
	pg_assert_same('cus_Q1a|pm_1', $answer['card_ref']);
}

function test_payment_stripe_renewal_charges_the_stored_card_off_session()
{
	$answer = payment_stripe_test_renew(array(
		'GET /v1/customers/cus_Q1a/payment_methods' => payment_stripe_test_wallet(array('pm_1Old')),
		'POST /v1/payment_intents'                  => array(200, payment_stripe_test_renewal_intent()),
	));
	$calls = payment_stripe_test_calls();
	$params = $calls[1]['params'];

	pg_assert_true($answer['success']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('pi_3Ren', $answer['transaction_id']);
	pg_assert_same(9900, $answer['amount']);
	pg_assert_same('', $answer['card_ref'], 'the same card: nothing to keep again');
	pg_assert_same(9900, $params['amount']);
	pg_assert_same('try', $params['currency']);
	pg_assert_same('cus_Q1a', $params['customer']);
	pg_assert_same('pm_1Old', $params['payment_method']);
	pg_assert_true($params['off_session']);
	pg_assert_true($params['confirm']);
	pg_assert_same('automatic', $params['capture_method']);
	pg_assert_same('7', $params['metadata']['subscription_id']);
	pg_assert_false(isset($params['metadata']['order_id']), 'the webhook never takes a renewal for the first order');

	// The same attempt asked again (a cron run cut short) is the same request
	// to Stripe; the next attempt of the ladder is a new one.
	$key = $calls[1]['idempotency'];
	pg_assert_same(0, strpos($key, 'pg-renew-'));
	payment_stripe_test_renew(array('POST /v1/payment_intents' => array(200, payment_stripe_test_renewal_intent())));
	pg_assert_same($key, payment_stripe_test_calls()[1]['idempotency']);
	payment_stripe_test_renew(array('POST /v1/payment_intents' => array(200, payment_stripe_test_renewal_intent())), array('retry_count' => 1));
	pg_assert_false($key === payment_stripe_test_calls()[1]['idempotency']);
}

function test_payment_stripe_renewal_takes_the_card_the_customer_added()
{
	$answer = payment_stripe_test_renew(array(
		'GET /v1/customers/cus_Q1a/payment_methods' => payment_stripe_test_wallet(array('pm_2New', 'pm_1Old')),
		'POST /v1/payment_intents'                  => array(200, payment_stripe_test_renewal_intent(array('payment_method' => array('id' => 'pm_2New', 'card' => array('last4' => '5555'))))),
		'POST /v1/payment_methods/pm_1Old/detach'   => array(200, array('id' => 'pm_1Old', 'object' => 'payment_method', 'customer' => null)),
	));
	$calls = payment_stripe_test_calls();

	pg_assert_same('paid', $answer['status']);
	pg_assert_same('pm_2New', $calls[1]['params']['payment_method']);
	pg_assert_same('cus_Q1a|pm_2New', $answer['card_ref']);
	pg_assert_same('5555', $answer['card_last4']);
	pg_assert_same('POST /v1/payment_methods/pm_1Old/detach', $calls[2]['method'] . ' ' . $calls[2]['path'], 'the replaced card is let go once the new one paid');
}

function test_payment_stripe_renewal_declined()
{
	$answer = payment_stripe_test_renew(array(
		'GET /v1/customers/cus_Q1a/payment_methods' => payment_stripe_test_wallet(array('pm_1Old')),
		'POST /v1/payment_intents'                  => array(402, array('error' => array('type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'Your card has insufficient funds.'))),
	));

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear'], 'a refusal is a clear answer');
	pg_assert_same('insufficient_funds', $answer['raw']['code']);
	pg_assert_same('Your card has insufficient funds.', $answer['error']);
}

function test_payment_stripe_renewal_needing_authentication_answers_a_confirmation_link()
{
	$answer = payment_stripe_test_renew(array(
		'GET /v1/customers/cus_Q1a/payment_methods' => payment_stripe_test_wallet(array('pm_1Old')),
		'POST /v1/payment_intents'                  => array(402, array('error' => array(
			'type'           => 'card_error',
			'code'           => 'authentication_required',
			'message'        => 'This payment requires authentication.',
			'payment_intent' => payment_stripe_test_renewal_intent(array('status' => 'requires_payment_method', 'id' => 'pi_3Auth')),
		))),
		'POST /v1/payment_intents/pi_3Auth/confirm' => array(200, payment_stripe_test_renewal_intent(array(
			'id'          => 'pi_3Auth',
			'status'      => 'requires_action',
			'next_action' => array('type' => 'redirect_to_url', 'redirect_to_url' => array('url' => 'https://hooks.stripe.com/3d_secure_2/hosted?x=1')),
		))),
	));
	$calls = payment_stripe_test_calls();

	pg_assert_true($answer['success']);
	pg_assert_same('redirect', $answer['status']);
	pg_assert_same('https://hooks.stripe.com/3d_secure_2/hosted?x=1', $answer['redirect_url']);
	pg_assert_same('pi_3Auth', $answer['transaction_id']);
	pg_assert_same('authentication_required', $answer['raw']['code']);
	pg_assert_same('pm_1Old', $calls[2]['params']['payment_method']);
	pg_assert_same('https://shop.example/account', $calls[2]['params']['return_url']);
	pg_assert_same(0, strpos($calls[2]['idempotency'], 'pg-renew-confirm-'));

	$no_link = payment_stripe_test_renew(array(
		'GET /v1/customers/cus_Q1a/payment_methods' => payment_stripe_test_wallet(array('pm_1Old')),
		'POST /v1/payment_intents'                  => array(402, array('error' => array('type' => 'card_error', 'code' => 'authentication_required', 'message' => 'This payment requires authentication.'))),
	));
	pg_assert_false($no_link['success']);
	pg_assert_false($no_link['unclear']);
	pg_assert_same('authentication_required', $no_link['raw']['code']);
}

function test_payment_stripe_renewal_unclear_answers()
{
	$wallet = payment_stripe_test_wallet(array('pm_1Old'));

	$unreached = payment_stripe_test_renew(array('GET /v1/customers/cus_Q1a/payment_methods' => $wallet));
	pg_assert_false($unreached['success']);
	pg_assert_true($unreached['unclear'], 'no answer: the charge may have gone through');

	$server = payment_stripe_test_renew(array('GET /v1/customers/cus_Q1a/payment_methods' => $wallet, 'POST /v1/payment_intents' => array(500, array('error' => array('type' => 'api_error', 'message' => 'Something went wrong')))));
	pg_assert_true($server['unclear']);

	$foreign = payment_stripe_test_renew(array('GET /v1/customers/cus_Q1a/payment_methods' => $wallet, 'POST /v1/payment_intents' => array(200, payment_stripe_test_renewal_intent(array('amount' => 100)))));
	pg_assert_false($foreign['success']);
	pg_assert_true($foreign['unclear'], 'an intent for another amount is not believed');

	$processing = payment_stripe_test_renew(array('GET /v1/customers/cus_Q1a/payment_methods' => $wallet, 'POST /v1/payment_intents' => array(200, payment_stripe_test_renewal_intent(array('status' => 'processing')))));
	pg_assert_same('pending', $processing['status']);

	// The wallet not read: the stored card is charged.
	$fallback = payment_stripe_test_renew(array('POST /v1/payment_intents' => array(200, payment_stripe_test_renewal_intent())));
	pg_assert_same('paid', $fallback['status']);
	pg_assert_same('pm_1Old', payment_stripe_test_calls()[1]['params']['payment_method']);

	$empty = payment_stripe_test_renew(array('GET /v1/customers/cus_Q1a/payment_methods' => payment_stripe_test_wallet(array())));
	pg_assert_false($empty['success']);
	pg_assert_false($empty['unclear']);
	pg_assert_same(1, count(payment_stripe_test_calls()), 'no card left: nothing charged');

	$no_card = payment_stripe_test_renew(array(), array('card_ref' => ''));
	pg_assert_false($no_card['success']);
	pg_assert_same(0, count(payment_stripe_test_calls()));
}

function test_payment_stripe_update_card_opens_stripes_page()
{
	$http = payment_stripe_test_http(array('POST /v1/checkout/sessions' => array(200, array('id' => 'cs_test_1', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'))));
	$answer = pg_payment_answer('update_card', pg_payment_stripe_update_card_with(payment_stripe_test_subscription(), payment_stripe_test_settings(), $http, 1793000000));
	$calls = payment_stripe_test_calls();
	$params = $calls[0]['params'];

	pg_assert_true($answer['success']);
	pg_assert_same('redirect', $answer['status']);
	pg_assert_same('https://checkout.stripe.com/c/pay/cs_test_1', $answer['redirect_url']);
	pg_assert_same('setup', $params['mode']);
	pg_assert_same('cus_Q1a', $params['customer']);
	pg_assert_same(array('card'), $params['payment_method_types']);
	pg_assert_same('https://shop.example/account', $params['success_url']);
	pg_assert_same('https://shop.example/account', $params['cancel_url']);
	pg_assert_same('7', $params['setup_intent_data']['metadata']['subscription_id']);
	pg_assert_same(0, strpos($calls[0]['idempotency'], 'pg-setup-'));

	pg_assert_false(pg_payment_answer('update_card', pg_payment_stripe_update_card_with(payment_stripe_test_subscription(), payment_stripe_test_settings(), payment_stripe_test_http(), 1793000000))['success']);
	pg_assert_false(pg_payment_answer('update_card', pg_payment_stripe_update_card_with(payment_stripe_test_subscription(array('card_ref' => '')), payment_stripe_test_settings(), payment_stripe_test_http(), 1793000000))['success']);
}

function test_payment_stripe_subscription_cancel_detaches_the_cards()
{
	$detached = array(200, array('object' => 'payment_method', 'customer' => null));
	$answer = pg_payment_answer('subscription_cancel', pg_payment_stripe_subscription_cancel_with(payment_stripe_test_subscription(), payment_stripe_test_settings(), payment_stripe_test_http(array(
		'GET /v1/customers/cus_Q1a/payment_methods' => payment_stripe_test_wallet(array('pm_2New', 'pm_1Old')),
		'POST /v1/payment_methods/pm_2New/detach'   => $detached,
		'POST /v1/payment_methods/pm_1Old/detach'   => $detached,
	))));
	$paths = array_map(function ($call) {
		return $call['method'] . ' ' . $call['path'];
	}, payment_stripe_test_calls());

	pg_assert_true($answer['success']);
	pg_assert_same('cancelled', $answer['status']);
	pg_assert_same(array('GET /v1/customers/cus_Q1a/payment_methods', 'POST /v1/payment_methods/pm_2New/detach', 'POST /v1/payment_methods/pm_1Old/detach'), $paths);

	// The wallet not read: the stored card is let go.
	$stored = pg_payment_answer('subscription_cancel', pg_payment_stripe_subscription_cancel_with(payment_stripe_test_subscription(), payment_stripe_test_settings(), payment_stripe_test_http(array('POST /v1/payment_methods/pm_1Old/detach' => $detached))));
	pg_assert_same('cancelled', $stored['status']);

	$refused = pg_payment_answer('subscription_cancel', pg_payment_stripe_subscription_cancel_with(payment_stripe_test_subscription(), payment_stripe_test_settings(), payment_stripe_test_http(array(
		'GET /v1/customers/cus_Q1a/payment_methods' => payment_stripe_test_wallet(array('pm_1Old')),
		'POST /v1/payment_methods/pm_1Old/detach'   => array(400, array('error' => array('type' => 'invalid_request_error', 'message' => 'No such PaymentMethod'))),
	))));
	pg_assert_false($refused['success']);
	pg_assert_same('No such PaymentMethod', $refused['raw']['message']);
}
