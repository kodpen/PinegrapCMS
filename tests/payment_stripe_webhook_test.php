<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Stripe webhook: the Stripe-Signature check (valid, wrong,
 * stale, missing), how each event is answered by the driver's notify(), and
 * what the callback endpoint makes of the answer for an order (nothing, a
 * log line and an alert, an acknowledged event, a refusal). No order is ever
 * changed by a webhook here.
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
require_once(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');
require_once(PG_FUNCTIONS_DIR . '/waf.php');

/* ---------------------------------------------------------------------------
   Fixtures
   --------------------------------------------------------------------------- */

function payment_stripe_webhook_test_secret()
{
	return 'whsec_test_abc123';
}

function payment_stripe_webhook_test_settings($changes = array())
{
	return array_merge(array(
		'publishable_key' => 'pk_test_123',
		'secret_key'      => 'sk_test_456',
		'webhook_secret'  => payment_stripe_webhook_test_secret(),
		'link'            => false,
		'currency'        => 'try',
		'capture'         => 'automatic',
		'locale'          => 'tr',
	), $changes);
}

function payment_stripe_webhook_test_header($body, $time, $secret = null)
{
	$secret = ($secret === null) ? payment_stripe_webhook_test_secret() : $secret;

	return 't=' . $time . ',v1=' . hash_hmac('sha256', $time . '.' . $body, $secret) . ',v0=6ffbb59b2300aae63f272406069a9788598b792a944a07aba816edb039989a39';
}

function payment_stripe_webhook_test_event($type, $object)
{
	return json_encode(array(
		'id'       => 'evt_1',
		'object'   => 'event',
		'type'     => $type,
		'livemode' => false,
		'data'     => array('object' => $object),
	));
}

function payment_stripe_webhook_test_intent($changes = array())
{
	return array_merge(array(
		'id'              => 'pi_3Abc',
		'object'          => 'payment_intent',
		'amount'          => 4714,
		'amount_received' => 4714,
		'currency'        => 'try',
		'status'          => 'succeeded',
		'metadata'        => array('order_id' => '438', 'reference_code' => 'AB12CD34EF'),
	), $changes);
}

function payment_stripe_webhook_test_notify($body, $now = 1760950000, $header = null, $settings = array())
{
	$header = ($header === null) ? payment_stripe_webhook_test_header($body, $now) : $header;
	$headers = ($header === false) ? array() : array('stripe-signature' => $header);

	return pg_payment_answer('notify', pg_payment_stripe_notify_with($headers, $body, payment_stripe_webhook_test_settings($settings), $now));
}

/* ---------------------------------------------------------------------------
   The signature
   --------------------------------------------------------------------------- */

function test_payment_stripe_webhook_signature_valid()
{
	$body = '{"id":"evt_1"}';
	$now = 1760950000;

	pg_assert_same('', pg_payment_stripe_signature_check(payment_stripe_webhook_test_header($body, $now), $body, payment_stripe_webhook_test_secret(), $now));
	pg_assert_same('', pg_payment_stripe_signature_check(payment_stripe_webhook_test_header($body, $now - 300), $body, payment_stripe_webhook_test_secret(), $now), 'five minutes old is still in time');
	pg_assert_same('', pg_payment_stripe_signature_check(payment_stripe_webhook_test_header($body, $now + 30), $body, payment_stripe_webhook_test_secret(), $now), 'a clock a little ahead');

	// Stripe sends one v1 per active secret while a secret is rolled.
	$rolled = 't=' . $now . ',v1=' . hash_hmac('sha256', $now . '.' . $body, 'whsec_old') . ',v1=' . hash_hmac('sha256', $now . '.' . $body, payment_stripe_webhook_test_secret());
	pg_assert_same('', pg_payment_stripe_signature_check($rolled, $body, payment_stripe_webhook_test_secret(), $now), 'any v1 may match');
}

function test_payment_stripe_webhook_signature_invalid()
{
	$body = '{"id":"evt_1"}';
	$now = 1760950000;

	pg_assert_same('invalid', pg_payment_stripe_signature_check(payment_stripe_webhook_test_header($body, $now, 'whsec_other'), $body, payment_stripe_webhook_test_secret(), $now), 'another secret');
	pg_assert_same('invalid', pg_payment_stripe_signature_check(payment_stripe_webhook_test_header($body, $now), $body . ' ', payment_stripe_webhook_test_secret(), $now), 'a changed body');
	pg_assert_same('invalid', pg_payment_stripe_signature_check('t=' . $now . ',v0=' . hash_hmac('sha256', $now . '.' . $body, payment_stripe_webhook_test_secret()), $body, payment_stripe_webhook_test_secret(), $now), 'v0 alone is not read');
	pg_assert_same('invalid', pg_payment_stripe_signature_check('v1=' . hash_hmac('sha256', '.' . $body, payment_stripe_webhook_test_secret()), $body, payment_stripe_webhook_test_secret(), $now), 'no time');
	pg_assert_same('invalid', pg_payment_stripe_signature_check('garbage', $body, payment_stripe_webhook_test_secret(), $now));
}

function test_payment_stripe_webhook_signature_stale()
{
	$body = '{"id":"evt_1"}';
	$now = 1760950000;

	pg_assert_same('stale', pg_payment_stripe_signature_check(payment_stripe_webhook_test_header($body, $now - 301), $body, payment_stripe_webhook_test_secret(), $now), 'a replay after five minutes');
	pg_assert_same('stale', pg_payment_stripe_signature_check(payment_stripe_webhook_test_header($body, $now + 301), $body, payment_stripe_webhook_test_secret(), $now), 'from the future');
}

function test_payment_stripe_webhook_signature_missing()
{
	$body = '{"id":"evt_1"}';

	pg_assert_same('missing', pg_payment_stripe_signature_check('', $body, payment_stripe_webhook_test_secret(), 1760950000));

	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent()), 1760950000, false);
	pg_assert_false($answer['success']);
	pg_assert_same('missing', $answer['raw']['signature']);

	// No secret stored: nothing is believed, whatever the header says.
	$body = payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent());
	$answer = payment_stripe_webhook_test_notify($body, 1760950000, payment_stripe_webhook_test_header($body, 1760950000, ''), array('webhook_secret' => ''));
	pg_assert_false($answer['success']);
	pg_assert_same('no_secret', $answer['raw']['signature']);
	pg_assert_same(400, pg_payment_notify_outcome('stripe', $answer, null)['http']);
}

function test_payment_stripe_webhook_headers_are_read_case_blind()
{
	$body = payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent());
	$headers = pg_payment_request_headers(array(
		'HTTP_STRIPE_SIGNATURE' => payment_stripe_webhook_test_header($body, 1760950000),
		'CONTENT_TYPE'          => 'application/json',
		'REQUEST_METHOD'        => 'POST',
	));

	pg_assert_same('application/json', $headers['content-type']);
	pg_assert_false(isset($headers['request-method']), 'only headers');

	$answer = pg_payment_answer('notify', pg_payment_stripe_notify_with($headers, $body, payment_stripe_webhook_test_settings(), 1760950000));
	pg_assert_true($answer['success']);
}

function test_payment_stripe_webhook_bad_signature_changes_nothing()
{
	$body = payment_stripe_webhook_test_event('charge.refunded', array('id' => 'ch_1', 'payment_intent' => 'pi_3Abc', 'amount_refunded' => 1000, 'currency' => 'try'));

	foreach (array('wrong' => payment_stripe_webhook_test_header($body, 1760950000, 'whsec_other'), 'stale' => payment_stripe_webhook_test_header($body, 1760949000)) as $case => $header) {
		$answer = payment_stripe_webhook_test_notify($body, 1760950000, $header);
		$outcome = pg_payment_notify_outcome('stripe', $answer, array('id' => '438', 'status' => 'complete'));

		pg_assert_false($answer['success'], $case);
		pg_assert_same(400, $outcome['http'], $case);
		pg_assert_same(array(), $outcome['log'], $case);
		pg_assert_same('', $outcome['alert'], $case);
	}
}

/* ---------------------------------------------------------------------------
   The events
   --------------------------------------------------------------------------- */

function test_payment_stripe_webhook_succeeded_on_a_complete_order_does_nothing()
{
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent()));

	pg_assert_true($answer['success']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('438', $answer['order_reference']);
	pg_assert_same('pi_3Abc', $answer['transaction_id']);
	pg_assert_same(4714, $answer['amount']);

	$outcome = pg_payment_notify_outcome('stripe', $answer, array('id' => '438', 'status' => 'complete', 'transaction_id' => 'pi_3Abc'));
	pg_assert_same(200, $outcome['http']);
	pg_assert_same(array(), $outcome['log']);
	pg_assert_same('', $outcome['alert']);
}

function test_payment_stripe_webhook_succeeded_on_an_incomplete_order_alerts()
{
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent()));
	$outcome = pg_payment_notify_outcome('stripe', $answer, array('id' => '438', 'status' => 'incomplete', 'transaction_id' => ''));

	pg_assert_same(200, $outcome['http']);
	pg_assert_same('alerted', $outcome['status']);
	pg_assert_same(1, count($outcome['log']));
	pg_assert_contains('order #438', $outcome['log'][0]);
	pg_assert_contains('pi_3Abc', $outcome['log'][0]);
	pg_assert_contains('47.14 TRY', $outcome['log'][0]);
	pg_assert_contains('not completed', $outcome['log'][0]);
	pg_assert_same($outcome['log'][0], $outcome['alert']);
}

function test_payment_stripe_webhook_refund_and_dispute_alert_without_changing_the_order()
{
	$refund = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('charge.refunded', array(
		'id'              => 'ch_1',
		'object'          => 'charge',
		'payment_intent'  => 'pi_3Abc',
		'amount'          => 4714,
		'amount_refunded' => 1000,
		'currency'        => 'try',
		'metadata'        => array(),
	)));

	pg_assert_true($refund['success']);
	pg_assert_same('refunded', $refund['status']);
	pg_assert_same('pi_3Abc', $refund['transaction_id']);
	pg_assert_same('pi_3Abc', $refund['order_reference'], 'no metadata: the order is found by its payment');
	pg_assert_same(1000, $refund['amount']);

	$outcome = pg_payment_notify_outcome('stripe', $refund, array('id' => '438', 'status' => 'complete', 'transaction_id' => 'pi_3Abc'));
	pg_assert_same(200, $outcome['http']);
	pg_assert_contains('order #438', $outcome['log'][0]);
	pg_assert_contains('10.00 TRY', $outcome['log'][0]);
	pg_assert_same($outcome['log'][0], $outcome['alert']);
	pg_assert_false(isset($outcome['order_status']), 'the order status is never set from a webhook');

	$dispute = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('charge.dispute.created', array(
		'id'             => 'dp_1',
		'object'         => 'dispute',
		'charge'         => 'ch_1',
		'payment_intent' => 'pi_3Abc',
		'amount'         => 4714,
		'currency'       => 'try',
		'reason'         => 'fraudulent',
		'status'         => 'needs_response',
	)));

	pg_assert_true($dispute['success']);
	pg_assert_same('pending', $dispute['status']);
	pg_assert_same('pi_3Abc', $dispute['order_reference']);

	$outcome = pg_payment_notify_outcome('stripe', $dispute, array('id' => '438', 'status' => 'shipped', 'transaction_id' => 'pi_3Abc'));
	pg_assert_same(200, $outcome['http']);
	pg_assert_contains('dispute', $outcome['log'][0]);
	pg_assert_contains('fraudulent', $outcome['log'][0]);
	pg_assert_contains('order #438', $outcome['log'][0]);
	pg_assert_same($outcome['log'][0], $outcome['alert']);
}

function test_payment_stripe_webhook_other_events_are_acknowledged()
{
	foreach (array('customer.created', 'payment_intent.created', 'charge.succeeded') as $type) {
		$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event($type, array('id' => 'x_1')));
		$outcome = pg_payment_notify_outcome('stripe', $answer, null);

		pg_assert_false($answer['success'], $type);
		pg_assert_true($answer['raw']['ignored'], $type);
		pg_assert_same(200, $outcome['http'], $type);
		pg_assert_same('ignored', $outcome['status'], $type);
		pg_assert_same(array(), $outcome['log'], $type);
	}

	// A payment another shop on the same Stripe account took names no order.
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent(array('metadata' => array()))));
	pg_assert_same(200, pg_payment_notify_outcome('stripe', $answer, null)['http']);
}

function test_payment_stripe_webhook_renewal_names_its_subscription()
{
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent(array('metadata' => array('subscription_id' => '7')))));

	pg_assert_true($answer['success']);
	pg_assert_same('paid', $answer['status']);
	pg_assert_same('7', $answer['raw']['subscription_id']);
	pg_assert_same('subscription-7', $answer['order_reference']);

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');
	pg_assert_true(strpos($source, 'pg_payment_callback_renewal($key, $answer);') < strpos($source, '$order = $answer[\'success\'] ? pg_payment_notify_order($answer) : null;'), 'taken before any order lookup');

	// An order's payment keeps its order even with a subscription named.
	$order = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent(array('metadata' => array('order_id' => '438', 'subscription_id' => '7')))));
	pg_assert_same('438', $order['order_reference']);
	pg_assert_false(isset($order['raw']['subscription_id']));
}

function test_payment_stripe_webhook_unknown_order_is_acknowledged_quietly()
{
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent(array('metadata' => array('order_id' => '9999')))));
	$outcome = pg_payment_notify_outcome('stripe', $answer, null);

	pg_assert_same(200, $outcome['http']);
	pg_assert_same('unknown_order', $outcome['status']);
	pg_assert_same(array(), $outcome['log']);
	pg_assert_same('', $outcome['alert']);

	// An order id that is another payment's: the order keeps its own.
	$refund = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('charge.refunded', array('id' => 'ch_9', 'payment_intent' => 'pi_9Other', 'amount_refunded' => 100, 'currency' => 'try', 'metadata' => array('order_id' => '438'))));
	pg_assert_same('438', $refund['order_reference']);
	$outcome = pg_payment_notify_outcome('stripe', $refund, array('id' => '438', 'status' => 'complete', 'transaction_id' => 'pi_3Abc'));
	pg_assert_same('unknown_order', $outcome['status']);
	pg_assert_same('', $outcome['alert']);
}

function test_payment_stripe_webhook_unreadable_body_is_refused()
{
	$answer = payment_stripe_webhook_test_notify('not json');

	pg_assert_false($answer['success']);
	pg_assert_same(400, pg_payment_notify_outcome('stripe', $answer, null)['http']);
}

function test_payment_stripe_webhook_order_lookup_terms()
{
	pg_assert_same(array('id' => 438, 'transaction_id' => ''), pg_payment_notify_order_terms(array('order_reference' => '438', 'transaction_id' => 'pi_3Abc')));
	pg_assert_same(array('id' => 0, 'transaction_id' => 'pi_3Abc'), pg_payment_notify_order_terms(array('order_reference' => 'pi_3Abc', 'transaction_id' => 'pi_3Abc')));
	pg_assert_same(array('id' => 0, 'transaction_id' => ''), pg_payment_notify_order_terms(array('order_reference' => '', 'transaction_id' => '')));
}

/* ---------------------------------------------------------------------------
   Settings, address and firewall
   --------------------------------------------------------------------------- */

function test_payment_stripe_webhook_secret_field_and_address()
{
	$fields = array();

	foreach (pg_payment_fields('stripe') as $field) {
		$fields[$field['name']] = $field;
	}

	pg_assert_true(isset($fields['webhook_secret']));
	pg_assert_same('secret', $fields['webhook_secret']['type'], 'sealed with the other secrets');
	pg_assert_false($fields['webhook_secret']['required'], 'card payments work without the webhook');

	pg_assert_true(pg_payment_can('stripe', 'notify'));
	pg_assert_contains('/payment_callback.php?driver=stripe', pg_payment_callback_url('stripe'));
	pg_assert_contains('payment_callback.php?driver=stripe', pg_payment_settings_fields_html(pg_payment_driver('stripe'), pg_payment_fields('stripe'), array('values' => array(), 'secrets' => array(), 'readable' => true)));
	pg_assert_false(strpos(pg_payment_settings_fields_html(pg_payment_driver('iyzico'), pg_payment_fields('iyzico'), array('values' => array(), 'secrets' => array(), 'readable' => true)), 'payment_callback.php') !== false, 'only a driver with notify() shows an address');
}

function test_payment_stripe_webhook_firewall_lists()
{
	pg_assert_true(in_array('payment_callback.php', waf_country_api_scripts(), true), 'called from Stripe\'s servers wherever they run');

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	pg_assert_contains("'payment_callback.php'", substr($waf, strpos($waf, 'function waf_is_api_request()'), 600), 'outside the sign-in allowance');
}

function test_payment_stripe_webhook_started_payment_kept_on_order()
{
	$redirect = pg_payment_answer('authorize', array(
		'success'        => true,
		'status'         => 'redirect',
		'transaction_id' => 'pi_3abc',
		'redirect_url'   => 'https://hooks.stripe.com/3d_secure/x',
		'state'          => array('payment_intent' => 'pi_3abc', 'order_id' => '42', 'amount' => 12345),
	));

	pg_assert_same(array('transaction_id' => 'pi_3abc', 'total' => 12345), pg_payment_started_order_values($redirect, 12345), 'the intent and the total it was started for');
	pg_assert_same(12346, pg_payment_started_order_values($redirect, 12345.6)['total'], 'rounded, never cut');

	$no_id = $redirect;
	$no_id['transaction_id'] = '';
	pg_assert_same(null, pg_payment_started_order_values($no_id, 12345), 'a redirect naming no payment (iyzico\'s 3-D Secure page) keeps nothing');

	foreach (array('paid', 'authorized', 'pending') as $status) {
		$taken = $redirect;
		$taken['status'] = $status;
		pg_assert_same(null, pg_payment_started_order_values($taken, 12345), $status . ': the order is completed in the same request');
	}

	$failed = $redirect;
	$failed['success'] = false;
	pg_assert_same(null, pg_payment_started_order_values($failed, 12345), 'a refusal keeps nothing');

	// The order is written only while it is incomplete, before the visitor
	// is sent away.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$at = strpos($source, 'pg_payment_started_order_values(');
	pg_assert_true($at !== false, 'submit_order.php keeps the started payment');
	pg_assert_true($at < strpos($source, "if (\$payment_answer['html'] !== '')"), 'before the redirect');
	pg_assert_contains('pg_order_payment_started_query(', substr($source, $at, 900), 'through the started-payment query');
	pg_assert_contains("AND status = 'incomplete'", pg_order_payment_started_query(42, array('transaction_id' => 'pi_3abc', 'total' => 12345), array(), array())['sql'], 'only an incomplete order');

	// The callback now finds the order's own payment id and tells another
	// payment apart.
	$event = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent()));
	$order = array('id' => '438', 'status' => 'incomplete', 'transaction_id' => 'pi_other');
	pg_assert_same('unknown_order', pg_payment_notify_outcome('stripe', $event, $order)['status']);
}

/* ---------------------------------------------------------------------------
   Completing an incomplete order from the message
   --------------------------------------------------------------------------- */

function payment_stripe_webhook_test_incomplete_order($changes = array())
{
	return array_merge(array('id' => '438', 'status' => 'incomplete', 'transaction_id' => 'pi_3Abc', 'payment_method' => 'Credit/Debit Card', 'total' => '4714'), $changes);
}

function test_payment_stripe_webhook_answer_carries_the_currency()
{
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent()));

	pg_assert_same('TRY', $answer['raw']['currency'], 'the payment\'s currency, upper case');
}

function test_payment_stripe_webhook_own_payment_completes_the_incomplete_order()
{
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent()));
	$outcome = pg_payment_notify_outcome('stripe', $answer, payment_stripe_webhook_test_incomplete_order(), 'TRY');

	pg_assert_same(200, $outcome['http']);
	pg_assert_same('complete', $outcome['status']);
	pg_assert_true($outcome['complete'], 'the receiver completes it');
	pg_assert_false($outcome['mark_paid'], 'not a pending payment');
	pg_assert_same('', $outcome['alert'], 'no alert');
	pg_assert_contains('order #438', $outcome['log'][0]);
	pg_assert_contains('The order was completed from the message.', $outcome['log'][0]);

	pg_assert_same('complete', pg_payment_notify_outcome('stripe', $answer, payment_stripe_webhook_test_incomplete_order(), 'try')['status'], 'the store\'s code in any case');
}

function test_payment_stripe_webhook_mismatch_alerts_and_completes_nothing()
{
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent()));
	$cases = array(
		'another amount' => array(payment_stripe_webhook_test_incomplete_order(array('total' => '4715')), 'TRY'),
		'another currency' => array(payment_stripe_webhook_test_incomplete_order(), 'EUR'),
		'no store currency' => array(payment_stripe_webhook_test_incomplete_order(), ''),
		'no payment kept' => array(payment_stripe_webhook_test_incomplete_order(array('transaction_id' => '')), 'TRY'),
	);

	foreach ($cases as $label => $case) {
		$outcome = pg_payment_notify_outcome('stripe', $answer, $case[0], $case[1]);

		pg_assert_same('alerted', $outcome['status'], $label);
		pg_assert_false($outcome['complete'], $label . ': nothing completed');
		pg_assert_contains('not completed', $outcome['alert'], $label . ': an alert');
	}

	$no_amount = $answer;
	$no_amount['amount'] = null;
	pg_assert_same('alerted', pg_payment_notify_outcome('stripe', $no_amount, payment_stripe_webhook_test_incomplete_order(), 'TRY')['status'], 'an amount the driver could not read');

	$no_currency = $answer;
	unset($no_currency['raw']['currency']);
	pg_assert_same('alerted', pg_payment_notify_outcome('stripe', $no_currency, payment_stripe_webhook_test_incomplete_order(), 'TRY')['status'], 'a driver that names no currency');
}

function test_payment_stripe_webhook_completed_order_is_a_no_op()
{
	// The visitor's return placed it paid (pg_payment_notify_order() always
	// reads paid_at).
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent()));
	$outcome = pg_payment_notify_outcome('stripe', $answer, payment_stripe_webhook_test_incomplete_order(array('status' => 'complete', 'paid_at' => '1760949000')), 'TRY');

	pg_assert_same('nothing', $outcome['status'], 'the visitor came back first, or a second event');
	pg_assert_false($outcome['complete']);
	pg_assert_same(array(), $outcome['log']);
}

function test_payment_stripe_webhook_receiver_completes_once()
{
	$receive = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');
	$receive = substr($receive, strpos($receive, 'function pg_payment_callback_receive('));

	pg_assert_contains("pg_payment_notify_outcome(\$key, \$answer, \$order, defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '')", $receive, 'the store\'s currency');
	pg_assert_contains("pg_order_complete_from_payment((int) \$order['id'], (string) \$answer['status'], array('amount' => \$answer['amount'], 'installments' => \$answer['installments']))", $receive, 'completes through the order completion');
	pg_assert_true(strpos($receive, 'pg_order_complete_from_payment(') < strpos($receive, "log_activity(\$line, 'SYSTEM')"), 'before the log line names its result');

	// Another request completed it first (the lock in pg_order_complete()):
	// no log line, no alert.
	$nothing = substr($receive, strpos($receive, "} elseif (\$completion['status'] === 'nothing') {"), 200);
	pg_assert_contains("\$outcome['status'] = 'nothing';", $nothing);
	pg_assert_contains("\$outcome['log'] = array();", $nothing);
}

function test_payment_stripe_webhook_renewal_failure_names_its_subscription()
{
	$intent = payment_stripe_webhook_test_intent(array(
		'status'             => 'requires_payment_method',
		'amount_received'    => 0,
		'metadata'           => array('subscription_id' => '7'),
		'last_payment_error' => array('code' => 'card_declined', 'message' => 'Your card was declined.'),
	));
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.payment_failed', $intent));

	pg_assert_false($answer['success']);
	pg_assert_true($answer['raw']['renewal_failed']);
	pg_assert_same('7', $answer['raw']['subscription_id']);
	pg_assert_same('pi_3Abc', $answer['transaction_id']);
	pg_assert_same('Your card was declined.', $answer['error']);
	pg_assert_same(200, pg_payment_notify_outcome('stripe', $answer, null)['http'], 'acknowledged');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');
	pg_assert_true(strpos($source, 'pg_payment_callback_renewal_failed($key, $answer);') < strpos($source, '$order = $answer[\'success\'] ? pg_payment_notify_order($answer) : null;'));

	// The off-session refusal that starts the confirmation is not a failure.
	$auth = $intent;
	$auth['last_payment_error']['code'] = 'authentication_required';
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.payment_failed', $auth));
	pg_assert_true($answer['raw']['ignored']);
	pg_assert_false(isset($answer['raw']['renewal_failed']));

	// An order's failed payment is its own answer, never a renewal's.
	$order = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.payment_failed', payment_stripe_webhook_test_intent(array('status' => 'requires_payment_method'))));
	pg_assert_true($order['success']);
	pg_assert_same('cancelled', $order['status']);
	pg_assert_false(isset($order['raw']['renewal_failed']));

	// A failed payment that names neither is not read.
	$none = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.payment_failed', payment_stripe_webhook_test_intent(array('status' => 'requires_payment_method', 'metadata' => array()))));
	pg_assert_true($none['raw']['ignored']);
	pg_assert_false(isset($none['raw']['renewal_failed']));
}

/* ---------------------------------------------------------------------------
   A payment whose result comes later (status processing at the return)
   --------------------------------------------------------------------------- */

function payment_stripe_webhook_test_processing_order($changes = array())
{
	return array_merge(payment_stripe_webhook_test_incomplete_order(array('status' => 'complete', 'paid_at' => '0')), $changes);
}

function payment_stripe_webhook_test_failed($changes = array())
{
	return payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.payment_failed', payment_stripe_webhook_test_intent(array_merge(array(
		'status'             => 'requires_payment_method',
		'amount_received'    => 0,
		'last_payment_error' => array('code' => 'insufficient_funds', 'message' => 'The account has insufficient funds.'),
	), $changes))));
}

function test_payment_stripe_webhook_order_payment_failed_answer()
{
	$answer = payment_stripe_webhook_test_failed();

	pg_assert_true($answer['success']);
	pg_assert_same('cancelled', $answer['status']);
	pg_assert_same('pi_3Abc', $answer['transaction_id']);
	pg_assert_same('438', $answer['order_reference']);
	pg_assert_same(4714, $answer['amount'], 'the amount asked, nothing was received');
	pg_assert_same('insufficient_funds', $answer['raw']['code']);
	pg_assert_same('TRY', $answer['raw']['currency']);
	pg_assert_same(array('Stripe reports that payment pi_3Abc of 47.14 TRY failed (insufficient_funds): nothing was taken. Do not ship it'), $answer['log']);

	$quiet = payment_stripe_webhook_test_failed(array('last_payment_error' => null));
	pg_assert_same('Stripe reports that payment pi_3Abc of 47.14 TRY failed (none given): nothing was taken. Do not ship it', $quiet['log'][0], 'no error given');

	// An order id or a payment id that is not one is not read.
	pg_assert_true(payment_stripe_webhook_test_failed(array('metadata' => array('order_id' => 'x438')))['raw']['ignored']);
	pg_assert_true(payment_stripe_webhook_test_failed(array('id' => 'ch_3Abc'))['raw']['ignored']);
}

// Each declined attempt at the checkout reports a failure: the order was
// never placed and the visitor may pay again, so no log line and no alert.
function test_payment_stripe_webhook_failed_attempt_at_the_checkout_is_quiet()
{
	$outcome = pg_payment_notify_outcome('stripe', payment_stripe_webhook_test_failed(), payment_stripe_webhook_test_incomplete_order(), 'TRY');

	pg_assert_same(200, $outcome['http']);
	pg_assert_same('nothing', $outcome['status']);
	pg_assert_same(array(), $outcome['log']);
	pg_assert_same('', $outcome['alert']);
	pg_assert_false($outcome['complete']);
	pg_assert_false($outcome['mark_paid']);
}

// A debit that bounced days after the order was placed: an alert, the order
// is not changed, and the operator cancels it (as PayPal's DENIED).
function test_payment_stripe_webhook_processing_order_failed_alerts()
{
	$order = payment_stripe_webhook_test_processing_order();
	pg_assert_true(pg_order_payment_pending($order), 'placed awaiting its payment');

	$outcome = pg_payment_notify_outcome('stripe', payment_stripe_webhook_test_failed(), $order, 'TRY');

	pg_assert_same(200, $outcome['http']);
	pg_assert_same('alerted', $outcome['status']);
	pg_assert_false($outcome['mark_paid']);
	pg_assert_false($outcome['complete']);
	pg_assert_same(array('Stripe reports that payment pi_3Abc of 47.14 TRY failed (insufficient_funds): nothing was taken. Do not ship it for order #438. The order was not changed.'), $outcome['log']);
	pg_assert_same($outcome['log'][0], $outcome['alert']);

	// Cancelled before the failure came: the alert says no refund is owed.
	$outcome = pg_payment_notify_outcome('stripe', payment_stripe_webhook_test_failed(), payment_stripe_webhook_test_processing_order(array('status' => 'cancelled')), 'TRY');
	pg_assert_same('alerted', $outcome['status']);
	pg_assert_false($outcome['mark_paid']);
	pg_assert_contains('nothing was taken', $outcome['alert']);
}

// The debit settled: payment_intent.succeeded records the order paid.
function test_payment_stripe_webhook_processing_order_succeeded_is_recorded()
{
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent()));
	$outcome = pg_payment_notify_outcome('stripe', $answer, payment_stripe_webhook_test_processing_order(), 'TRY');

	pg_assert_same(200, $outcome['http']);
	pg_assert_same('recorded', $outcome['status']);
	pg_assert_true($outcome['mark_paid'], 'the receiver marks it paid');
	pg_assert_false($outcome['complete'], 'the order is already placed');
	pg_assert_same('', $outcome['alert']);
	pg_assert_same(array('Stripe reports that payment pi_3Abc of 47.14 TRY succeeded for order #438, which was awaiting it. The payment was recorded.'), $outcome['log']);

	$outcome = pg_payment_notify_outcome('stripe', $answer, payment_stripe_webhook_test_processing_order(array('total' => '4715')), 'TRY');
	pg_assert_same('alerted', $outcome['status'], 'another amount');
	pg_assert_false($outcome['mark_paid']);
	pg_assert_contains('not for the order\'s total. The payment was not recorded', $outcome['alert']);

	$outcome = pg_payment_notify_outcome('stripe', $answer, payment_stripe_webhook_test_processing_order(array('transaction_id' => 'pi_3Other')), 'TRY');
	pg_assert_same('unknown_order', $outcome['status'], 'another payment than the order\'s');
	pg_assert_false($outcome['mark_paid']);

	// The receiver's write only takes an order still unpaid, so a second
	// delivery of the event records nothing twice.
	$receive = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');
	pg_assert_contains("pg_order_mark_paid((int) \$order['id'], 0);", $receive);
	$mark = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	$mark = substr($mark, strpos($mark, 'function pg_order_mark_paid('), 600);
	pg_assert_contains("AND paid_at = 0\");", $mark);
}

// A late succeeded for an order that is already paid, or was cancelled, does
// nothing harmful: never marked paid, never completed again.
function test_payment_stripe_webhook_late_succeeded_changes_nothing()
{
	$answer = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent()));

	$paid = pg_payment_notify_outcome('stripe', $answer, payment_stripe_webhook_test_processing_order(array('paid_at' => '1761600000')), 'TRY');
	pg_assert_same('nothing', $paid['status'], 'already recorded (a second delivery, or the operator recorded it)');
	pg_assert_false($paid['mark_paid']);
	pg_assert_false($paid['complete']);
	pg_assert_same('', $paid['alert']);

	$refunded = pg_payment_notify_outcome('stripe', $answer, payment_stripe_webhook_test_processing_order(array('status' => 'cancelled', 'paid_at' => '1761600000')), 'TRY');
	pg_assert_same('nothing', $refunded['status'], 'cancelled after its payment was recorded');
	pg_assert_false($refunded['mark_paid']);

	// Cancelled while the debit was processing, then it settled: the money
	// arrived on an order the store no longer ships.
	$cancelled = pg_payment_notify_outcome('stripe', $answer, payment_stripe_webhook_test_processing_order(array('status' => 'cancelled')), 'TRY');
	pg_assert_same('alerted', $cancelled['status']);
	pg_assert_false($cancelled['mark_paid'], 'the cancelled order is not marked paid');
	pg_assert_false($cancelled['complete']);
	pg_assert_contains('which was cancelled before its payment was recorded. The order was not changed: refund the payment on Stripe\'s own screen or restore the order.', $cancelled['alert']);
}

function test_payment_stripe_webhook_order_payment_answers_the_kept_card()
{
	// The order's payment kept its card for a subscription: the message that
	// completes the order carries the card, so the subscription is made even
	// when the visitor never comes back from the 3-D Secure page.
	$kept = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent(array(
		'customer' => 'cus_Q1', 'payment_method' => 'pm_Q2', 'setup_future_usage' => 'off_session'))));

	pg_assert_same('438', $kept['order_reference']);
	pg_assert_same('cus_Q1|pm_Q2', $kept['card_ref']);

	// A payment that keeps no card answers none.
	$plain = payment_stripe_webhook_test_notify(payment_stripe_webhook_test_event('payment_intent.succeeded', payment_stripe_webhook_test_intent(array(
		'customer' => 'cus_Q1', 'payment_method' => 'pm_Q2'))));
	pg_assert_same('', $plain['card_ref']);
}

function test_payment_stripe_webhook_completion_makes_the_subscriptions()
{
	$receive = file_get_contents(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');
	$completed = substr($receive, strpos($receive, "if (\$completion['status'] === 'completed') {"), 600);

	// The order the message completed gets the subscriptions of its paid
	// recurring lines, as the visitor's own return would have made them.
	pg_assert_contains('pg_subscription_checkout_record((int) $order[\'id\'], $key, $answer);', $completed);
}
