<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for PayPal's capture webhooks (includes/payments/paypal.php
 * pg_payment_paypal_notify_with()) and what payment_callback.php does with
 * them (includes/payments/callback.php pg_payment_notify_outcome()): a
 * capture PayPal left pending and later completes records the order's
 * payment; a denied one alerts.
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
require_once(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');

function payment_paypal_webhook_test_event($changes = array())
{
	$event = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/paypal/webhook_capture_completed.json'), true);

	return (string) json_encode(array_replace_recursive($event, $changes), JSON_UNESCAPED_SLASHES);
}

function payment_paypal_webhook_test_headers()
{
	return array(
		'paypal-auth-algo'         => 'SHA256withRSA',
		'paypal-cert-url'          => 'https://api.paypal.com/v1/notifications/certs/CERT-360caa42-fca2a594-a5cafa77',
		'paypal-transmission-id'   => '103e3700-8b0c-11e6-8695-6b62a8a99ac4',
		'paypal-transmission-sig'  => 't8hlRk64rpEImZMKqgtp5dlWaT1W8ed/mf8Msos341QInVn3BMuITfSLmNCQZgD8QjSk6MMa5vpS3aNA7ilUw/Y/IHxu3l8cCVr8UqyD5PZZ8kWzvBX3EM6mvo/4Bmsy7XU6KxvA/4ga/MPLkGO/2eKx6rI8F2hkvEu8CigmnVHU9MmNRf2rTnOqFxv4K+NWTlTcFbEBabDmJJqF9nEUWAEOHKwtAYQnXzJDPXXJ5QuTHZnnI/lmLBFgYCUfbOAQaHXlxECwJMCK04kqRXDY+4XVVDkuVMgXDsF14iD1qGEDvi8a99KAjwW2ZTLmgUeHaKO4nkkLtACK7Ytd/Ow5TA==',
		'paypal-transmission-time' => '2026-10-28T10:15:44Z',
	);
}

function payment_paypal_webhook_test_settings($changes = array())
{
	return array_merge(array(
		'client_id'     => 'AYSq3RDGsmBLJE-otTkBtM-jBRd1TCQwFf9RGfwddNXWz0uFU9ztymylOhRS',
		'client_secret' => 'EGnHDxD_qRPdaLdZz8iCr8N7_MzF-YHPTkjs6NKYQvQSBngp4PTTVWkPZRbL',
		'mode'          => 'live',
		'webhook_id'    => '1JE4291016473214C',
		'currency'      => 'EUR',
		'locale'        => 'tr',
	), $changes);
}

// Stands in for PayPal: the token, then the signature check's answer.
function payment_paypal_webhook_test_http($verify)
{
	$GLOBALS['payment_paypal_webhook_test_calls'] = array();

	return function ($method, $url, $headers, $body) use ($verify) {
		$path = (string) preg_replace('#^https://[^/]+#', '', $url);
		$GLOBALS['payment_paypal_webhook_test_calls'][] = array('path' => $method . ' ' . $path, 'body' => $body);

		if ($path === '/v1/oauth2/token') {
			return array('status' => 200, 'body' => array('access_token' => 'A21AAFEpH4PsADK7qSS7pSRsgzfENtu', 'token_type' => 'Bearer', 'expires_in' => 32400), 'error' => '');
		}

		if (($path === '/v1/notifications/verify-webhook-signature') && ($verify !== null)) {
			return array('status' => $verify[0], 'body' => $verify[1], 'error' => '');
		}

		return array('status' => 0, 'body' => null, 'error' => 'Could not resolve host: api-m.paypal.com');
	};
}

function payment_paypal_webhook_test_notify($body, $verify, $settings = array(), $headers = null)
{
	return pg_payment_answer('notify', pg_payment_paypal_notify_with(
		($headers === null) ? payment_paypal_webhook_test_headers() : $headers,
		$body,
		payment_paypal_webhook_test_settings($settings),
		payment_paypal_webhook_test_http($verify)));
}

// A completed capture, checked by PayPal, names the order by its custom_id.
function test_payment_paypal_webhook_capture_completed()
{
	$body = payment_paypal_webhook_test_event();
	$answer = payment_paypal_webhook_test_notify($body, array(200, array('verification_status' => 'SUCCESS')));

	pg_assert_true($answer['success']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('3C679366HH908993F', $answer['transaction_id']);
	pg_assert_same('438', $answer['order_reference']);
	pg_assert_same(2000, $answer['amount']);
	pg_assert_same(array('PayPal reports that capture 3C679366HH908993F of 20.00 EUR completed'), $answer['log']);

	$calls = $GLOBALS['payment_paypal_webhook_test_calls'];
	pg_assert_same('POST /v1/notifications/verify-webhook-signature', $calls[1]['path']);

	// The event goes to the check exactly as PayPal sent it.
	$sent = json_decode($calls[1]['body'], true);
	pg_assert_same('1JE4291016473214C', $sent['webhook_id']);
	pg_assert_same('103e3700-8b0c-11e6-8695-6b62a8a99ac4', $sent['transmission_id']);
	pg_assert_contains('"webhook_event":' . $body . '}', $calls[1]['body'], 'the body byte for byte');
}

// Without a custom_id the capture id finds the order.
function test_payment_paypal_webhook_names_the_order_by_capture_without_custom_id()
{
	$event = json_decode(payment_paypal_webhook_test_event(), true);
	unset($event['resource']['custom_id']);

	$answer = payment_paypal_webhook_test_notify((string) json_encode($event), array(200, array('verification_status' => 'SUCCESS')));

	pg_assert_same('3C679366HH908993F', $answer['order_reference']);
	pg_assert_same(array('id' => 0, 'transaction_id' => '3C679366HH908993F'), pg_payment_notify_order_terms($answer));
}

function test_payment_paypal_webhook_capture_denied()
{
	$answer = payment_paypal_webhook_test_notify(payment_paypal_webhook_test_event(array('event_type' => 'PAYMENT.CAPTURE.DENIED', 'resource' => array('status' => 'DECLINED'))), array(200, array('verification_status' => 'SUCCESS')));

	pg_assert_true($answer['success']);
	pg_assert_same('cancelled', $answer['status']);
	pg_assert_contains('was DENIED: nothing was taken. Do not ship it', $answer['log'][0]);
}

// Messages that are not checked are refused (400) or, when PayPal could not
// be asked, left for PayPal to send again (500).
function test_payment_paypal_webhook_refuses_what_is_not_checked()
{
	$body = payment_paypal_webhook_test_event();

	$answer = payment_paypal_webhook_test_notify($body, null, array('webhook_id' => ''));
	pg_assert_false($answer['success']);
	pg_assert_same(array(), $GLOBALS['payment_paypal_webhook_test_calls'], 'no call without a webhook ID');
	pg_assert_same(400, pg_payment_notify_outcome('paypal', $answer, null)['http']);

	$headers = payment_paypal_webhook_test_headers();
	unset($headers['paypal-transmission-sig']);
	$answer = payment_paypal_webhook_test_notify($body, array(200, array('verification_status' => 'SUCCESS')), array(), $headers);
	pg_assert_same(400, pg_payment_notify_outcome('paypal', $answer, null)['http'], 'a header missing');

	$answer = payment_paypal_webhook_test_notify($body, array(200, array('verification_status' => 'FAILURE')));
	pg_assert_same('refused', pg_payment_notify_outcome('paypal', $answer, null)['status'], 'PayPal says it is not its message');

	$answer = payment_paypal_webhook_test_notify($body, array(400, array('name' => 'VALIDATION_ERROR', 'message' => 'Invalid request')));
	pg_assert_same(400, pg_payment_notify_outcome('paypal', $answer, null)['http'], 'PayPal refused the check');

	$answer = payment_paypal_webhook_test_notify('not json', array(200, array('verification_status' => 'SUCCESS')));
	pg_assert_same(400, pg_payment_notify_outcome('paypal', $answer, null)['http'], 'not readable');

	$answer = payment_paypal_webhook_test_notify($body, array(503, array('name' => 'SERVICE_UNAVAILABLE')));
	pg_assert_same(500, pg_payment_notify_outcome('paypal', $answer, null)['http'], 'PayPal could not check it now');

	$answer = payment_paypal_webhook_test_notify($body, null);
	pg_assert_same(500, pg_payment_notify_outcome('paypal', $answer, null)['http'], 'PayPal not reached');
}

function test_payment_paypal_webhook_ignores_other_events()
{
	$answer = payment_paypal_webhook_test_notify(payment_paypal_webhook_test_event(array('event_type' => 'CHECKOUT.ORDER.APPROVED')), array(200, array('verification_status' => 'SUCCESS')));

	pg_assert_same('ignored', pg_payment_notify_outcome('paypal', $answer, null)['status']);
	pg_assert_same(200, pg_payment_notify_outcome('paypal', $answer, null)['http']);
}

// The order a pending capture placed is marked paid once PayPal completes
// the capture for the order's total; nothing else changes an order.
function test_payment_paypal_webhook_records_a_pending_payment()
{
	$answer = payment_paypal_webhook_test_notify(payment_paypal_webhook_test_event(), array(200, array('verification_status' => 'SUCCESS')));
	$order = array('id' => '438', 'status' => 'complete', 'transaction_id' => '3C679366HH908993F', 'payment_method' => 'PayPal Checkout', 'total' => '2000', 'paid_at' => '0');

	$outcome = pg_payment_notify_outcome('paypal', $answer, $order);
	pg_assert_same('recorded', $outcome['status']);
	pg_assert_true($outcome['mark_paid']);
	pg_assert_same('', $outcome['alert']);
	pg_assert_same(array('PayPal reports that capture 3C679366HH908993F of 20.00 EUR completed for order #438, which was awaiting it. The payment was recorded.'), $outcome['log']);

	$outcome = pg_payment_notify_outcome('paypal', $answer, array('total' => '2500') + $order);
	pg_assert_same('alerted', $outcome['status'], 'another amount');
	pg_assert_false($outcome['mark_paid']);
	pg_assert_contains('not for the order\'s total. The payment was not recorded', $outcome['alert']);

	$outcome = pg_payment_notify_outcome('paypal', $answer, array('paid_at' => '1761600000') + $order);
	pg_assert_same('nothing', $outcome['status'], 'already paid: the capture completed at checkout');
	pg_assert_false($outcome['mark_paid']);

	$outcome = pg_payment_notify_outcome('paypal', $answer, array('transaction_id' => '8MC585209K746392H') + $order);
	pg_assert_same('unknown_order', $outcome['status'], 'another capture than the order\'s');

	$denied = payment_paypal_webhook_test_notify(payment_paypal_webhook_test_event(array('event_type' => 'PAYMENT.CAPTURE.DENIED')), array(200, array('verification_status' => 'SUCCESS')));
	$outcome = pg_payment_notify_outcome('paypal', $denied, $order);
	pg_assert_same('alerted', $outcome['status'], 'denied');
	pg_assert_false($outcome['mark_paid']);
	pg_assert_contains('Do not ship it for order #438. The order was not changed.', $outcome['alert']);
}

function test_payment_paypal_webhook_answer_carries_the_currency()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/payments/paypal.php');

	pg_assert_contains("'raw'             => \$raw + array('currency' => \$currency),", $source, 'the capture\'s currency for the completion\'s check');
}
