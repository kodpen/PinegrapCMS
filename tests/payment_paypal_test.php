<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the PayPal Checkout driver (includes/payments/paypal.php): the
 * driver in the registry and the checkout's method list, the requests made to
 * PayPal (token, order create, read, capture, refund, the PayPal-Request-Id),
 * how each order and capture status is answered, the total that changed while
 * the visitor was at PayPal, refund and cancel by the capture id, amounts in
 * PayPal's text. PayPal is never reached: a stand-in takes the place of the
 * HTTP caller and answers by method and path with the saved answers in
 * tests/fixtures/paypal/ (built in the shape of the Orders v2 and Payments v2
 * documentation; no live account was asked).
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

/* ---------------------------------------------------------------------------
   Fixtures
   --------------------------------------------------------------------------- */

function payment_paypal_test_fixture($name, $changes = array())
{
	$body = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/paypal/' . $name . '.json'), true);

	return array_replace_recursive($body, $changes);
}

// Stands in for PayPal: answers by "METHOD path" (host cut off), records every
// call; an answer is array(status, body).
function payment_paypal_test_http($answers = array())
{
	$GLOBALS['payment_paypal_test_calls'] = array();
	$GLOBALS['payment_paypal_test_answers'] = $answers + array('POST /v1/oauth2/token' => array(200, payment_paypal_test_fixture('token')));

	return function ($method, $url, $headers, $body) {
		$path = (string) preg_replace('#^https://[^/]+#', '', $url);

		$GLOBALS['payment_paypal_test_calls'][] = array(
			'method'  => $method,
			'url'     => $url,
			'path'    => $path,
			'headers' => $headers,
			'body'    => $body,
		);

		$answers = $GLOBALS['payment_paypal_test_answers'];
		$name = $method . ' ' . $path;

		if (!isset($answers[$name])) {
			return array('status' => 0, 'body' => null, 'error' => 'Could not resolve host: api-m.paypal.com');
		}

		return array('status' => $answers[$name][0], 'body' => $answers[$name][1], 'error' => '');
	};
}

function payment_paypal_test_calls()
{
	return $GLOBALS['payment_paypal_test_calls'];
}

// The calls after the token, as "METHOD path".
function payment_paypal_test_paths()
{
	return array_map(function ($call) {
		return $call['method'] . ' ' . $call['path'];
	}, payment_paypal_test_calls());
}

function payment_paypal_test_header($call, $name)
{
	foreach ($call['headers'] as $line) {
		if (stripos($line, $name . ':') === 0) {
			return trim(substr($line, strlen($name) + 1));
		}
	}

	return null;
}

function payment_paypal_test_settings($changes = array())
{
	return array_merge(array(
		'client_id'     => 'AYSq3RDGsmBLJE-otTkBtM-jBRd1TCQwFf9RGfwddNXWz0uFU9ztymylOhRS',
		'client_secret' => 'EGnHDxD_qRPdaLdZz8iCr8N7_MzF-YHPTkjs6NKYQvQSBngp4PTTVWkPZRbL',
		'mode'          => 'live',
		'currency'      => 'EUR',
		'locale'        => 'tr',
	), $changes);
}

function payment_paypal_test_order($changes = array())
{
	return array_merge(array(
		'id'                    => '438',
		'reference_code'        => 'AB12CD34EF',
		'billing_email_address' => 'ayse@example.com',
		'transaction_id'        => '3C679366HH908993F',
		'refunded_amount'       => '0',
	), $changes);
}

function payment_paypal_test_state($changes = array())
{
	return array_merge(array('paypal_order' => '5O190127TN364715T', 'order_id' => '438', 'amount' => 12345), $changes);
}

function payment_paypal_test_authorize($answers, $settings = array(), $amount = 12345)
{
	return pg_payment_answer('authorize', pg_payment_paypal_authorize_with(
		payment_paypal_test_order(),
		$amount,
		array('return_url' => 'https://shop.example/pinegrap/express_order.php?mode=paypal_return&page_id=1023'),
		payment_paypal_test_settings($settings),
		payment_paypal_test_http($answers)));
}

function payment_paypal_test_complete($answers, $amount = 12345, $state = array())
{
	return pg_payment_answer('complete', pg_payment_paypal_complete_with(
		payment_paypal_test_order(),
		$amount,
		array(),
		payment_paypal_test_state($state),
		payment_paypal_test_settings(),
		payment_paypal_test_http($answers)));
}

/* ---------------------------------------------------------------------------
   The driver in the registry
   --------------------------------------------------------------------------- */

function test_payment_paypal_driver_is_shipped()
{
	pg_assert_same('paypal', pg_payment_driver_for_method('PayPal Checkout'));
	pg_assert_same('', pg_payment_driver_for_method('PayPal Express Checkout'), 'the classic method stays the NVP code');
	pg_assert_same('redirect', pg_payment_driver('paypal')['kind']);
	pg_assert_same('connect', pg_payment_driver('paypal')['settings'], 'the keys live in connect_apps');
	pg_assert_same(array(), pg_payment_driver('paypal')['csp'], 'nothing of PayPal\'s is loaded into the checkout');
	pg_assert_same('PayPal', pg_payment_log_name('paypal'));
	pg_assert_same('paypal', pg_payment_order_driver('PayPal Checkout', 'Iyzipay'), 'whatever the card gateway');
	pg_assert_same('paypal', pg_payment_refund_driver('PayPal Checkout'));
	pg_assert_same('paypal', pg_payment_cancel_driver('PayPal Checkout'));
	pg_assert_same('paypal_return', pg_payment_return_mode('paypal'));
	pg_assert_same('PayPal Checkout', pg_payment_return_method('paypal_return'));
	pg_assert_true(pg_payment_is_return_mode('paypal_return'));

	foreach (array('authorize', 'complete', 'refund', 'cancel', 'partial_refund') as $what) {
		pg_assert_true(pg_payment_can('paypal', $what), $what);
	}

	foreach (array('prepare', 'capture', 'notify', 'hosted_fields', 'recurring') as $what) {
		pg_assert_false(pg_payment_can('paypal', $what), $what);
	}

	$names = array_map(function ($field) {
		return $field['name'] . ':' . $field['type'] . ($field['required'] ? '!' : '') . '=' . $field['default'];
	}, pg_payment_fields('paypal'));
	pg_assert_same(array('client_id:text!=', 'client_secret:secret!=', 'mode:select=live'), $names);
}

function test_payment_paypal_method_in_the_checkout_lists()
{
	pg_assert_true(in_array('PayPal Checkout', pg_payment_method_values(), true), 'a value orders keep');
	pg_assert_same('paypal', pg_payment_driver_methods()['PayPal Checkout']);
	pg_assert_same('PayPal Checkout', pg_payment_method_label('PayPal Checkout'));

	$switches = array('Credit/Debit Card' => true, 'PayPal Express Checkout' => true, 'Pay With Iyzico' => false, 'Offline Payment' => true);
	$drivers = array('PayPal Checkout');

	pg_assert_same(array('Credit/Debit Card', 'PayPal Express Checkout', 'PayPal Checkout', 'Offline Payment'), pg_payment_checkout_methods_decide($switches, $drivers, false, true), 'after the built-in redirect methods, before Offline');
	pg_assert_false(in_array('PayPal Checkout', pg_payment_checkout_methods_decide($switches, $drivers, true, true), true), 'never for a recurring order');
}

// The ENUM step appends the value the driver stores.
function test_payment_paypal_migration_appends_the_method()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_paypal_checkout();        // 11.0.39', $source);
	pg_assert_contains("\",'PayPal Checkout') \"", $source);
	pg_assert_same(1, preg_match("/install_column_info\\('orders', 'payment_method'\\);\\s*\\n\\s*if \\(!is_array\\(\\\$column\\)\\) \\{\\s*\\n\\s*install_skipped\\(lang\\('orders.payment_method does not exist, skipped'\\)\\);\\s*\\n\\s*\\} elseif \\(stripos\\(\\(string\\) \\\$column\\['Type'\\], \"'PayPal Checkout'\"\\)/", $source), 'checked before it is added');
}

/* ---------------------------------------------------------------------------
   Amounts
   --------------------------------------------------------------------------- */

function test_payment_paypal_amounts_in_paypals_text()
{
	pg_assert_same('123.45', pg_payment_paypal_value(12345, 'EUR'));
	pg_assert_same('0.10', pg_payment_paypal_value(10, 'USD'));
	pg_assert_same('1500', pg_payment_paypal_value(150000, 'JPY'), 'whole units');
	pg_assert_same('', pg_payment_paypal_value(150050, 'HUF'), 'a fraction of a whole-unit currency cannot be sent');
	pg_assert_false(in_array('TRY', pg_payment_paypal_currencies(), true), 'PayPal takes no Turkish lira');
	pg_assert_true(pg_payment_paypal_money_is(array('currency_code' => 'EUR', 'value' => '123.45'), 12345, 'EUR'));
	pg_assert_false(pg_payment_paypal_money_is(array('currency_code' => 'USD', 'value' => '123.45'), 12345, 'EUR'), 'another currency');
	pg_assert_false(pg_payment_paypal_money_is(array('currency_code' => 'EUR', 'value' => '123.44'), 12345, 'EUR'), 'another amount');
	pg_assert_same('https://api-m.sandbox.paypal.com', pg_payment_paypal_host('sandbox'));
	pg_assert_same('https://api-m.paypal.com', pg_payment_paypal_host('live'));
}

/* ---------------------------------------------------------------------------
   authorize(): create the PayPal order, send the visitor to approve it
   --------------------------------------------------------------------------- */

function test_payment_paypal_authorize_creates_the_order_and_redirects()
{
	$answer = payment_paypal_test_authorize(array('POST /v2/checkout/orders' => array(200, payment_paypal_test_fixture('order_created'))));

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('redirect', $answer['status']);
	pg_assert_same('https://www.paypal.com/checkoutnow?token=5O190127TN364715T', $answer['redirect_url']);
	pg_assert_same(payment_paypal_test_state(), $answer['state']);
	pg_assert_same(array('PayPal Checkout payment for order #438 was started (PayPal order 5O190127TN364715T).'), $answer['log']);

	$calls = payment_paypal_test_calls();
	pg_assert_same(array('POST /v1/oauth2/token', 'POST /v2/checkout/orders'), payment_paypal_test_paths());
	pg_assert_same('https://api-m.paypal.com/v1/oauth2/token', $calls[0]['url']);
	pg_assert_same('Basic ' . base64_encode(payment_paypal_test_settings()['client_id'] . ':' . payment_paypal_test_settings()['client_secret']), payment_paypal_test_header($calls[0], 'Authorization'));
	pg_assert_same('grant_type=client_credentials', $calls[0]['body']);
	pg_assert_same('Bearer ' . payment_paypal_test_fixture('token')['access_token'], payment_paypal_test_header($calls[1], 'Authorization'));
	pg_assert_same(1, preg_match('/^pg-create-438-[0-9a-f]{16}$/', (string) payment_paypal_test_header($calls[1], 'PayPal-Request-Id')));

	$body = json_decode($calls[1]['body'], true);
	pg_assert_same('CAPTURE', $body['intent']);
	pg_assert_same(array('custom_id' => '438', 'amount' => array('currency_code' => 'EUR', 'value' => '123.45'), 'description' => 'Order AB12CD34EF'), $body['purchase_units'][0]);
	pg_assert_same('ayse@example.com', $body['payment_source']['paypal']['email_address']);

	$context = $body['payment_source']['paypal']['experience_context'];
	pg_assert_same('https://shop.example/pinegrap/express_order.php?mode=paypal_return&page_id=1023', $context['return_url']);
	pg_assert_same($context['return_url'], $context['cancel_url'], 'a cancel comes back to the same address');
	pg_assert_same('PAY_NOW', $context['user_action']);
	pg_assert_same('NO_SHIPPING', $context['shipping_preference']);
	pg_assert_same('tr', $context['locale']);
}

function test_payment_paypal_authorize_in_sandbox_and_with_the_approve_link()
{
	$created = payment_paypal_test_fixture('order_created');
	$created['links'][1]['rel'] = 'approve';
	$created['links'][1]['href'] = 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T';

	$answer = payment_paypal_test_authorize(array('POST /v2/checkout/orders' => array(201, $created)), array('mode' => 'sandbox', 'locale' => ''));

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T', $answer['redirect_url']);
	pg_assert_same('https://api-m.sandbox.paypal.com/v2/checkout/orders', payment_paypal_test_calls()[1]['url']);
	pg_assert_false(isset(json_decode(payment_paypal_test_calls()[1]['body'], true)['payment_source']['paypal']['experience_context']['locale']), 'no locale: PayPal\'s own');
}

function test_payment_paypal_authorize_refusals()
{
	$created = array('POST /v2/checkout/orders' => array(201, payment_paypal_test_fixture('order_created')));

	$answer = payment_paypal_test_authorize($created, array('client_secret' => ''));
	pg_assert_false($answer['success']);
	pg_assert_same('This payment method is not available.', $answer['error']);
	pg_assert_same(array(), payment_paypal_test_calls(), 'nothing asked without keys');

	$answer = payment_paypal_test_authorize($created, array('currency' => 'TRY'));
	pg_assert_false($answer['success']);
	pg_assert_same('PayPal does not take payments in this currency.', $answer['error']);
	pg_assert_same(array('PayPal Checkout payment for order #438 was not accepted: the store currency TRY is not one PayPal takes.'), $answer['log']);
	pg_assert_same(array(), payment_paypal_test_calls());

	$answer = payment_paypal_test_authorize($created, array(), 0);
	pg_assert_false($answer['success'], 'nothing to pay');

	$answer = payment_paypal_test_authorize($created + array('POST /v1/oauth2/token' => array(401, payment_paypal_test_fixture('token_invalid_client'))));
	pg_assert_false($answer['success']);
	pg_assert_same('This payment method is not available.', $answer['error'], 'wrong keys: the visitor is not told the store\'s secret is wrong');
	pg_assert_same(array('PayPal Checkout payment for order #438 was not accepted: Client Authentication failed (invalid_client).'), $answer['log']);

	$answer = payment_paypal_test_authorize(array());
	pg_assert_false($answer['success']);
	pg_assert_same('The payment service could not be reached.', $answer['error']);
	pg_assert_same(array(), $answer['state']);

	$created = payment_paypal_test_fixture('order_created');
	unset($created['links'][1]);
	$answer = payment_paypal_test_authorize(array('POST /v2/checkout/orders' => array(200, $created)));
	pg_assert_false($answer['success'], 'no approval address');
	pg_assert_same(array(), $answer['state']);
}

/* ---------------------------------------------------------------------------
   complete(): read the order again, capture it
   --------------------------------------------------------------------------- */

function test_payment_paypal_complete_captures_an_approved_order()
{
	$answer = payment_paypal_test_complete(array(
		'GET /v2/checkout/orders/5O190127TN364715T'          => array(200, payment_paypal_test_fixture('order_approved')),
		'POST /v2/checkout/orders/5O190127TN364715T/capture' => array(201, payment_paypal_test_fixture('order_captured')),
	));

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('3C679366HH908993F', $answer['transaction_id'], 'the capture is what refunds go by');
	pg_assert_same(12345, $answer['amount']);
	pg_assert_same(array(), $answer['state'], 'the wait ends');
	pg_assert_same(array('PayPal Checkout payment for order #438 was taken (capture 3C679366HH908993F).'), $answer['log']);

	$calls = payment_paypal_test_calls();
	pg_assert_same(array('POST /v1/oauth2/token', 'GET /v2/checkout/orders/5O190127TN364715T', 'POST /v2/checkout/orders/5O190127TN364715T/capture'), payment_paypal_test_paths());
	pg_assert_same('pg-capture-5O190127TN364715T', payment_paypal_test_header($calls[2], 'PayPal-Request-Id'), 'a repeated capture is the same request');
	pg_assert_same('{}', $calls[2]['body']);
	pg_assert_same('return=representation', payment_paypal_test_header($calls[2], 'Prefer'));
	pg_assert_same('', $calls[1]['body'], 'a read sends no body');
}

function test_payment_paypal_complete_reads_an_order_captured_already()
{
	$answer = payment_paypal_test_complete(array('GET /v2/checkout/orders/5O190127TN364715T' => array(200, payment_paypal_test_fixture('order_captured'))));

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('3C679366HH908993F', $answer['transaction_id']);
	pg_assert_same(array('POST /v1/oauth2/token', 'GET /v2/checkout/orders/5O190127TN364715T'), payment_paypal_test_paths(), 'no second capture');
}

function test_payment_paypal_complete_pending_capture()
{
	$answer = payment_paypal_test_complete(array(
		'GET /v2/checkout/orders/5O190127TN364715T'          => array(200, payment_paypal_test_fixture('order_approved')),
		'POST /v2/checkout/orders/5O190127TN364715T/capture' => array(201, payment_paypal_test_fixture('order_captured', array('purchase_units' => array(array('payments' => array('captures' => array(array('status' => 'PENDING')))))))),
	));

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('pending', $answer['status']);
	pg_assert_same('3C679366HH908993F', $answer['transaction_id']);
	pg_assert_same(array('PayPal Checkout payment for order #438 is PENDING on PayPal (capture 3C679366HH908993F, reason not given). Check it on PayPal before shipping: PayPal may still deny it.'), $answer['log']);

	$answer = payment_paypal_test_complete(array(
		'GET /v2/checkout/orders/5O190127TN364715T'          => array(200, payment_paypal_test_fixture('order_approved')),
		'POST /v2/checkout/orders/5O190127TN364715T/capture' => array(201, payment_paypal_test_fixture('order_captured', array('purchase_units' => array(array('payments' => array('captures' => array(array('status' => 'PENDING', 'status_details' => array('reason' => 'PENDING_REVIEW'))))))))),
	));
	pg_assert_contains('reason PENDING_REVIEW', $answer['log'][0]);
}

function test_payment_paypal_complete_declined_and_cancelled()
{
	$answer = payment_paypal_test_complete(array(
		'GET /v2/checkout/orders/5O190127TN364715T'          => array(200, payment_paypal_test_fixture('order_approved')),
		'POST /v2/checkout/orders/5O190127TN364715T/capture' => array(422, payment_paypal_test_fixture('capture_instrument_declined')),
	));

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_same('PayPal declined the payment. Please choose another way to pay.', $answer['error']);
	pg_assert_same(array(), $answer['state']);
	pg_assert_contains('(INSTRUMENT_DECLINED)', $answer['log'][0]);

	$answer = payment_paypal_test_complete(array(
		'GET /v2/checkout/orders/5O190127TN364715T'          => array(200, payment_paypal_test_fixture('order_approved')),
		'POST /v2/checkout/orders/5O190127TN364715T/capture' => array(201, payment_paypal_test_fixture('order_captured', array('purchase_units' => array(array('payments' => array('captures' => array(array('status' => 'DECLINED')))))))),
	));
	pg_assert_false($answer['success']);
	pg_assert_same('PayPal declined the payment. Please choose another way to pay.', $answer['error']);

	// The visitor pressed cancel at PayPal: the order was never approved.
	foreach (array('PAYER_ACTION_REQUIRED', 'CREATED', 'VOIDED') as $status) {
		$answer = payment_paypal_test_complete(array('GET /v2/checkout/orders/5O190127TN364715T' => array(200, payment_paypal_test_fixture('order_approved', array('status' => $status)))));
		pg_assert_false($answer['success'], $status);
		pg_assert_same('The payment was cancelled.', $answer['error'], $status);
		pg_assert_same(array('POST /v1/oauth2/token', 'GET /v2/checkout/orders/5O190127TN364715T'), payment_paypal_test_paths(), $status . ': nothing captured');
	}
}

function test_payment_paypal_complete_leaves_a_changed_total_uncaptured()
{
	$answer = payment_paypal_test_complete(array(
		'GET /v2/checkout/orders/5O190127TN364715T' => array(200, payment_paypal_test_fixture('order_approved')),
	), 13000);

	pg_assert_false($answer['success']);
	pg_assert_same('The order total changed while you were at PayPal. Nothing was charged. Please review your order and try again.', $answer['error']);
	pg_assert_same(array('PayPal order 5O190127TN364715T for order #438 was approved for 123.45, the order now totals 130.00; it was not captured.'), $answer['log']);
	pg_assert_same(array('POST /v1/oauth2/token', 'GET /v2/checkout/orders/5O190127TN364715T'), payment_paypal_test_paths());
}

function test_payment_paypal_complete_refunds_a_capture_for_another_total()
{
	$answers = array(
		'GET /v2/checkout/orders/5O190127TN364715T'          => array(200, payment_paypal_test_fixture('order_captured')),
		'POST /v2/payments/captures/3C679366HH908993F/refund' => array(201, payment_paypal_test_fixture('refund_completed', array('amount' => array('value' => '123.45')))),
	);

	$answer = payment_paypal_test_complete($answers, 13000);

	pg_assert_false($answer['success']);
	pg_assert_same('The order total changed while the payment was being verified. The payment was cancelled. Please review your order and try again.', $answer['error']);
	pg_assert_same('PayPal capture 3C679366HH908993F was refunded in full.', $answer['log'][1]);
	pg_assert_same('{}', payment_paypal_test_calls()[2]['body'], 'what is left of the capture');

	unset($answers['POST /v2/payments/captures/3C679366HH908993F/refund']);
	$answer = payment_paypal_test_complete($answers, 13000);

	pg_assert_false($answer['success']);
	pg_assert_contains('The store will refund it.', $answer['error']);
	pg_assert_contains('MANUAL REFUND REQUIRED', $answer['log'][1]);
}

function test_payment_paypal_complete_checks_whose_order_it_is()
{
	// No state of this checkout: PayPal is not asked, the wait goes on.
	$answer = payment_paypal_test_complete(array(), 12345, array('order_id' => '439'));
	pg_assert_false($answer['success']);
	pg_assert_same(payment_paypal_test_state(array('order_id' => '439')), $answer['state']);
	pg_assert_same(array(), payment_paypal_test_calls());

	$answer = payment_paypal_test_complete(array(), 12345, array('paypal_order' => '../x'));
	pg_assert_same(array(), payment_paypal_test_calls());

	foreach (array(
		'another order'    => array('purchase_units' => array(array('custom_id' => '999'))),
		'another amount'   => array('purchase_units' => array(array('amount' => array('value' => '1.00')))),
		'another currency' => array('purchase_units' => array(array('amount' => array('currency_code' => 'USD')))),
		'another id'       => array('id' => '7XX00000AA000000A'),
	) as $label => $change) {
		$answer = payment_paypal_test_complete(array('GET /v2/checkout/orders/5O190127TN364715T' => array(200, payment_paypal_test_fixture('order_approved', $change))));
		pg_assert_false($answer['success'], $label);
		pg_assert_contains('cannot be read', $answer['error'], $label);
		pg_assert_same(2, count(payment_paypal_test_calls()), $label . ': not captured');
	}
}

function test_payment_paypal_complete_keeps_waiting_while_paypal_is_unreached()
{
	$answer = payment_paypal_test_complete(array());
	pg_assert_false($answer['success']);
	pg_assert_same(payment_paypal_test_state(), $answer['state'], 'no token');

	$answer = payment_paypal_test_complete(array('GET /v2/checkout/orders/5O190127TN364715T' => array(200, payment_paypal_test_fixture('order_approved'))));
	pg_assert_false($answer['success']);
	pg_assert_same(payment_paypal_test_state(), $answer['state'], 'capture not answered: the next return reads the order again');

	// A server error, an answer that is not PayPal's JSON, a 2xx without a
	// capture: the capture may have gone through, so the wait goes on.
	foreach (array(
		'5xx'        => array(500, array('name' => 'INTERNAL_SERVER_ERROR', 'message' => 'An internal server error has occurred.', 'debug_id' => '90957fca61718')),
		'not JSON'   => array(200, null),
		'no capture' => array(201, payment_paypal_test_fixture('order_approved', array('status' => 'COMPLETED'))),
	) as $label => $capture) {
		$answer = payment_paypal_test_complete(array(
			'GET /v2/checkout/orders/5O190127TN364715T'          => array(200, payment_paypal_test_fixture('order_approved')),
			'POST /v2/checkout/orders/5O190127TN364715T/capture' => $capture,
		));
		pg_assert_false($answer['success'], $label);
		pg_assert_same(payment_paypal_test_state(), $answer['state'], $label);
	}

	// PayPal's own refusal ends it.
	$answer = payment_paypal_test_complete(array(
		'GET /v2/checkout/orders/5O190127TN364715T'          => array(200, payment_paypal_test_fixture('order_approved')),
		'POST /v2/checkout/orders/5O190127TN364715T/capture' => array(422, payment_paypal_test_fixture('capture_instrument_declined')),
	));
	pg_assert_same(array(), $answer['state']);
}

/* ---------------------------------------------------------------------------
   refund() and cancel() by the capture id
   --------------------------------------------------------------------------- */

function test_payment_paypal_refund()
{
	$answers = array('POST /v2/payments/captures/3C679366HH908993F/refund' => array(201, payment_paypal_test_fixture('refund_completed')));

	$answer = pg_payment_answer('refund', pg_payment_paypal_refund_with(payment_paypal_test_order(), 2000, payment_paypal_test_settings(), payment_paypal_test_http($answers)));

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('refunded', $answer['status']);
	pg_assert_same(2000, $answer['amount']);
	pg_assert_same('1JU08902781691411', $answer['refund_reference']);

	$call = payment_paypal_test_calls()[1];
	pg_assert_same(array('amount' => array('currency_code' => 'EUR', 'value' => '20.00')), json_decode($call['body'], true));
	$request_id = payment_paypal_test_header($call, 'PayPal-Request-Id');
	pg_assert_same('pg-refund-' . sha1('438|3C679366HH908993F|2000|0'), $request_id);

	// After the first refund is recorded the same amount is a new refund.
	pg_payment_paypal_refund_with(payment_paypal_test_order(array('refunded_amount' => '2000')), 2000, payment_paypal_test_settings(), payment_paypal_test_http($answers));
	pg_assert_true(payment_paypal_test_header(payment_paypal_test_calls()[1], 'PayPal-Request-Id') !== $request_id);

	$answer = pg_payment_answer('refund', pg_payment_paypal_refund_with(payment_paypal_test_order(), 2000, payment_paypal_test_settings(), payment_paypal_test_http(array('POST /v2/payments/captures/3C679366HH908993F/refund' => array(201, payment_paypal_test_fixture('refund_completed', array('status' => 'PENDING')))))));
	pg_assert_same('pending', $answer['status']);

	$answer = pg_payment_answer('refund', pg_payment_paypal_refund_with(payment_paypal_test_order(), 2000, payment_paypal_test_settings(), payment_paypal_test_http(array('POST /v2/payments/captures/3C679366HH908993F/refund' => array(422, payment_paypal_test_fixture('refund_already_refunded'))))));
	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_same('The capture has already been fully refunded', $answer['error']);

	$answer = pg_payment_answer('refund', pg_payment_paypal_refund_with(payment_paypal_test_order(), 2000, payment_paypal_test_settings(), payment_paypal_test_http(array())));
	pg_assert_false($answer['success'], 'no token');

	$http = payment_paypal_test_http(array());
	$GLOBALS['payment_paypal_test_answers']['POST /v1/oauth2/token'] = array(200, payment_paypal_test_fixture('token'));
	$answer = pg_payment_answer('refund', pg_payment_paypal_refund_with(payment_paypal_test_order(), 2000, payment_paypal_test_settings(), $http));
	pg_assert_true($answer['unclear'], 'the refund went out unanswered');

	$answer = pg_payment_answer('refund', pg_payment_paypal_refund_with(payment_paypal_test_order(array('transaction_id' => 'EC-0AB12345CD678901E')), 2000, payment_paypal_test_settings(), payment_paypal_test_http($answers)));
	pg_assert_false($answer['success'], 'not a capture id');
	pg_assert_same(array(), payment_paypal_test_calls());

	$answer = pg_payment_answer('refund', pg_payment_paypal_refund_with(payment_paypal_test_order(), 0, payment_paypal_test_settings(), payment_paypal_test_http($answers)));
	pg_assert_same('The refund amount must be greater than 0.', $answer['error']);
}

function test_payment_paypal_cancel()
{
	$answers = array('POST /v2/payments/captures/3C679366HH908993F/refund' => array(201, payment_paypal_test_fixture('refund_completed', array('amount' => array('value' => '123.45')))));

	$answer = pg_payment_answer('cancel', pg_payment_paypal_cancel_with(payment_paypal_test_order(), '3C679366HH908993F', payment_paypal_test_settings(), payment_paypal_test_http($answers)));

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('cancelled', $answer['status']);
	pg_assert_same('1JU08902781691411', $answer['refund_reference']);
	pg_assert_same('{}', payment_paypal_test_calls()[1]['body'], 'what is left of the capture');
	pg_assert_same('pg-cancel-3C679366HH908993F', payment_paypal_test_header(payment_paypal_test_calls()[1], 'PayPal-Request-Id'));

	$answer = pg_payment_answer('cancel', pg_payment_paypal_cancel_with(payment_paypal_test_order(), '3C679366HH908993F', payment_paypal_test_settings(), payment_paypal_test_http(array('POST /v2/payments/captures/3C679366HH908993F/refund' => array(422, payment_paypal_test_fixture('refund_already_refunded'))))));
	pg_assert_false($answer['success']);
	pg_assert_same('The capture has already been fully refunded', $answer['raw']['message'], 'the cancellation log keeps PayPal\'s words');

	$outcome = pg_payment_cancel_outcome(438, '3C679366HH908993F', $answer, pg_payment_log_name('paypal'));
	pg_assert_contains('Auto-refund FAILED via PayPal', implode("\n", $outcome['log']));

	$answer = pg_payment_answer('cancel', pg_payment_paypal_cancel_with(payment_paypal_test_order(), '', payment_paypal_test_settings(), payment_paypal_test_http($answers)));
	pg_assert_false($answer['success'], 'no capture id');
	pg_assert_same(array(), payment_paypal_test_calls());
}
