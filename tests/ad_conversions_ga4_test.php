<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/ad_conversions_ga4.php: the Measurement Protocol
 * body Google's documents ask for (client id, consent, the purchase and
 * refund events with the transaction id), Google's recorded answers from the
 * collection and validation endpoints, the refusals made without a call, the
 * refund rows written from order_refunds, the settings the page writes and
 * the places outside the module that reach it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ad_conversions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ad_conversions_ga4.php');

function ad_conversions_ga4_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A due purchase row as SC1 leaves it for ga4_mp.
function ad_conversions_ga4_test_event($person = true)
{
	$row = array(
		'event' => 'purchase',
		'event_id' => 'R7K2Q9AB12',
		'person' => $person ? 1 : 0,
		'ids' => $person ? json_encode(array('client_id' => '1234567890.1700000000', 'ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0')) : null,
		'detail' => json_encode(array('value' => 12550, 'currency' => 'try', 'order_number' => '1042', 'time' => 1763600000,
			'contents' => array(array('id' => 'SKU-1', 'quantity' => 2, 'price' => 5000), array('id' => 'SKU-2', 'quantity' => 1, 'price' => 2550)))),
	);

	return pg_ad_conversion_event($row, array('billing_email_address' => 'buyer@example.com', 'billing_phone_number' => '+90 532 111 22 33'));
}

// The purchase with the person: the visitor's client id, consent granted,
// the ecommerce parameters, the address and browser name, no contact data.
function test_ad_conversions_ga4_body_person()
{
	$body = pg_ad_conversion_ga4_body(ad_conversions_ga4_test_event());
	$event = $body['events'][0];

	pg_assert_same('1234567890.1700000000', $body['client_id'], 'the _ga client id');
	pg_assert_same(1763600000 * 1000000, $body['timestamp_micros'], 'microseconds');
	pg_assert_same(array('ad_user_data' => 'GRANTED', 'ad_personalization' => 'GRANTED'), $body['consent'], 'consent granted');
	pg_assert_same('203.0.113.9', $body['ip_override'], 'the address');
	pg_assert_same('Mozilla/5.0', $body['user_agent'], 'the browser name');
	pg_assert_same('purchase', $event['name'], 'GA4 event name');
	pg_assert_same('R7K2Q9AB12', $event['params']['transaction_id'], 'the order reference, as a pixel sends it');
	pg_assert_same('TRY', $event['params']['currency'], 'ISO 4217 upper case');
	pg_assert_same(125.5, $event['params']['value'], 'value as a number');
	pg_assert_same(array('item_id' => 'SKU-1', 'quantity' => 2, 'price' => 50.0), $event['params']['items'][0], 'an item');
	pg_assert_false(isset($body['user_data']), 'no user_data: GA4 hashes differently and asks a user_id with it');
	pg_assert_false(strpos(json_encode($body), hash('sha256', 'buyer@example.com')) !== false, 'no e-mail hash');
}

// Without the person: a client id made from the order, consent denied.
function test_ad_conversions_ga4_body_without_person()
{
	$body = pg_ad_conversion_ga4_body(ad_conversions_ga4_test_event(false));
	$json = json_encode($body);

	pg_assert_true((bool) preg_match('/^[1-9]\d*\.[1-9]\d*$/D', $body['client_id']), 'two positive numbers joined by a period');
	pg_assert_false($body['client_id'] === '1234567890.1700000000', 'not the visitor');
	pg_assert_same($body['client_id'], pg_ad_conversion_ga4_body(ad_conversions_ga4_test_event(false))['client_id'], 'the same for the same order');
	pg_assert_same(array('ad_user_data' => 'DENIED', 'ad_personalization' => 'DENIED'), $body['consent'], 'consent denied');
	pg_assert_false(isset($body['ip_override']) || isset($body['user_agent']), 'no address or browser');
	pg_assert_false(strpos($json, '203.0.113.9') !== false, 'nothing of the visitor');
	pg_assert_same(125.5, $body['events'][0]['params']['value'], 'the amount still goes');
}

// A refund: its own event id, the purchase's transaction id, no items.
function test_ad_conversions_ga4_refund()
{
	pg_assert_same('R7K2Q9AB12-refund-77', pg_ad_conversion_ga4_refund_event_id('R7K2Q9AB12', 77), 'refund event id');

	$detail = pg_ad_conversion_ga4_refund_detail(json_encode(array('value' => 12550, 'currency' => 'TRY', 'order_number' => '1042', 'time' => 1763600000, 'contents' => array(array('id' => 'SKU-1')))), 2550, 1763700000);
	pg_assert_same(array('value' => 2550, 'currency' => 'TRY', 'order_number' => '1042', 'time' => 1763700000, 'contents' => array()), $detail, 'refund detail');

	$event = pg_ad_conversion_event(array('event' => 'refund', 'event_id' => 'R7K2Q9AB12-refund-77', 'person' => 0, 'detail' => json_encode($detail)), array());
	$body = pg_ad_conversion_ga4_body($event);

	pg_assert_same('refund', $body['events'][0]['name'], 'refund event');
	pg_assert_same('R7K2Q9AB12', $body['events'][0]['params']['transaction_id'], 'the purchase\'s transaction id');
	pg_assert_same(25.5, $body['events'][0]['params']['value'], 'the refunded amount');
	pg_assert_false(isset($body['events'][0]['params']['items']), 'no items for a refund by amount');
	pg_assert_same(pg_ad_conversion_ga4_body(ad_conversions_ga4_test_event(false))['client_id'], $body['client_id'], 'the client id of the purchase without the person');

	$plan = pg_ad_conversion_ga4_refund_rows(array(
		array('refund_id' => 77, 'order_id' => 9, 'amount_cents' => 2550, 'refunded_at' => 1763700000, 'event_id' => 'R7K2Q9AB12', 'detail' => json_encode(array('currency' => 'TRY', 'order_number' => '1042'))),
		array('refund_id' => 78, 'order_id' => 9, 'amount_cents' => 0, 'refunded_at' => 1763700000, 'event_id' => 'R7K2Q9AB12', 'detail' => '{}'),
	), 1763700100);
	pg_assert_same(1, count($plan), 'a refund of nothing is not sent');
	pg_assert_same(array('ga4_mp', 'refund', 9, 'R7K2Q9AB12-refund-77'), array_slice($plan[0], 0, 4), 'service, event, order, event id');
	pg_assert_same(null, $plan[0][4], 'no ids');
	pg_assert_same(array('value' => 2550, 'currency' => 'TRY', 'order_number' => '1042', 'time' => 1763700000, 'contents' => array()), json_decode($plan[0][5], true), 'the refund detail');
	pg_assert_same(array(0, 1, 1763700100, 1763700100), array_slice($plan[0], 6), 'no person, due now');

	$sql = pg_ad_conversion_ga4_refunds_sql();
	pg_assert_contains("e.service = 'ga4_mp'", $sql, 'only GA4\'s purchases');
	pg_assert_contains('e.status = 2', $sql, 'only purchases GA4 received');
	pg_assert_contains('INTERVAL 72 HOUR', $sql, 'refunds GA4 still takes');
}

// The address and the two bodies of Google's answers.
function test_ad_conversions_ga4_answers()
{
	pg_assert_same('https://www.google-analytics.com/mp/collect?measurement_id=G-ABC123&api_secret=s3cr%2Bt', pg_ad_conversion_ga4_url('G-ABC123', 's3cr+t'), 'collection address');
	pg_assert_same('https://www.google-analytics.com/debug/mp/collect?measurement_id=G-ABC123&api_secret=x', pg_ad_conversion_ga4_url('G-ABC123', 'x', true), 'validation address');

	$sent = pg_ad_conversion_ga4_answer(array('ok' => true, 'status' => 204, 'body' => ''));
	pg_assert_true($sent['ok'], 'a 2xx is received');

	$bad = pg_ad_conversion_ga4_answer(array('ok' => false, 'status' => 400, 'body' => ''));
	pg_assert_same(400, $bad['status'], 'a refusal');
	pg_assert_false(api_http_should_retry(array('ok' => false, 'status' => $bad['status'])), 'not retried, as Google asks');

	$down = pg_ad_conversion_ga4_answer(array('ok' => false, 'status' => 503, 'body' => ''));
	pg_assert_true(api_http_should_retry(array('ok' => false, 'status' => $down['status'])), 'a server failure is retried');

	$silent = pg_ad_conversion_ga4_answer(array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'Operation timed out'));
	pg_assert_same(0, $silent['status'], 'no answer holds the service');
	pg_assert_same('Operation timed out', $silent['error'], 'the transport error');

	$valid = pg_ad_conversion_ga4_validation_answer(array('ok' => true, 'status' => 200, 'body' => '{"validationMessages":[]}'));
	pg_assert_true($valid['ok'], 'no validation message is valid');

	$invalid = pg_ad_conversion_ga4_validation_answer(array('ok' => true, 'status' => 200, 'body' => '{"validationMessages":[{"fieldPath":"events[0].params.currency","description":"Currency is required when value is set.","validationCode":"VALUE_REQUIRED"}]}'));
	pg_assert_false($invalid['ok'], 'a message is invalid');
	pg_assert_same('events[0].params.currency: Currency is required when value is set. (VALUE_REQUIRED)', $invalid['error'], 'Google\'s words');

	$odd = pg_ad_conversion_ga4_validation_answer(array('ok' => true, 'status' => 200, 'body' => '<html>'));
	pg_assert_false($odd['ok'], 'an answer that is not the validation body');
}

// The refusals made without a call.
function test_ad_conversions_ga4_refusals()
{
	$keys = array('measurement_id' => 'G-ABC123', 'secret' => 'abcdefghijklmnopqrstuv');
	$event = ad_conversions_ga4_test_event();
	$now = 1763600000 + 3600;

	pg_assert_same('', pg_ad_conversion_ga4_refusal($event, $keys, $now), 'a fresh purchase goes');
	pg_assert_same('The Measurement ID or the API secret is missing.', pg_ad_conversion_ga4_refusal($event, array('measurement_id' => '', 'secret' => 'x'), $now), 'no measurement id');
	pg_assert_same('Google Analytics takes events up to 72 hours old.', pg_ad_conversion_ga4_refusal($event, $keys, 1763600000 + 72 * 3600), 'too old');
	pg_assert_same('Google Analytics has no event for this.', pg_ad_conversion_ga4_refusal(array('event' => 'lead_form') + $event, $keys, $now), 'an unknown event');
	pg_assert_same('add_to_cart', pg_ad_conversion_ga4_event_name('add_to_cart'), 'add to cart');
	pg_assert_same('sign_up', pg_ad_conversion_ga4_event_name('complete_registration'), 'registration');
}

// The settings the page writes and the field shapes.
function test_ad_conversions_ga4_settings()
{
	$stored = pg_ad_conversion_ga4_stored(json_encode(array('connected' => true, 'secret' => 'sealed:iv', 'events' => array('purchase'), 'person_notice_off' => true)));
	pg_assert_same('sealed:iv', $stored['secret'], 'stored secret read');

	$kept = pg_ad_conversion_ga4_settings_merge($stored, array('events' => array('purchase', 'refund', 'evil'), 'without_person' => array('purchase', 'refund')), '');
	pg_assert_same('sealed:iv', $kept['secret'], 'empty field keeps the secret');
	pg_assert_same(array('purchase', 'refund'), $kept['events'], 'unknown events dropped');
	pg_assert_same(array('purchase'), $kept['without_person'], 'a refund never carries the person, so it is not a choice');
	pg_assert_false($kept['person_notice_off'], 'a cleared box is cleared');
	pg_assert_true($kept['connected'], 'connected with a secret');

	$parsed = pg_ad_conversion_settings_parse(json_encode($kept));
	pg_assert_same(array('purchase', 'refund'), $parsed['events'], 'the queue reads the same row');
	pg_assert_false(pg_ad_conversion_ga4_settings_merge(pg_ad_conversion_ga4_stored(''), array(), '')['connected'], 'no secret, not connected');

	pg_assert_true(pg_ad_conversion_ga4_measurement_id_valid('G-ABC123XYZ9'), 'measurement id');
	pg_assert_false(pg_ad_conversion_ga4_measurement_id_valid('UA-12345-1'), 'a Universal Analytics id');
	pg_assert_false(pg_ad_conversion_ga4_measurement_id_valid('GT-ABC123'), 'a Google tag that is not a stream');
	pg_assert_true(pg_ad_conversion_ga4_secret_valid('Ab3_xYz-9QwErTyUiOpAsD'), 'api secret');
	pg_assert_false(pg_ad_conversion_ga4_secret_valid('has spaces in it'), 'secret spaces');

	$test = pg_ad_conversion_ga4_test_event(1763600000, 'TRY');
	pg_assert_same(array(), $test['user'], 'the test event has no person');
	pg_assert_same('pg-test-1763600000', $test['event_id'], 'its own id');
}

// The places outside the module.
function test_ad_conversions_ga4_places()
{
	pg_assert_same('pg_ad_conversion_send_ga4_mp', pg_ad_conversion_sender('ga4_mp'), 'the dispatcher finds the sender');

	$connections = ad_conversions_ga4_test_source('connections.php');
	pg_assert_true(strpos($connections, "pg_ad_conversion_ga4_post_page(\$action, \$_POST, \$liveform)") !== false, 'the write');
	pg_assert_true(strpos($connections, "array('ga4_mp_save', 'ga4_mp_remove')) && connections_privacy_may_manage(\$user)") !== false, 'the write under the Visitors and privacy gate');
	pg_assert_true(strpos($connections, "pg_ad_conversion_ga4_page(\$page, connections_privacy_may_manage(\$user), \$can_seal)") !== false, 'the page under the same gate');

	pg_assert_true(strpos(ad_conversions_ga4_test_source('job.php'), 'pg_ad_conversion_ga4_queue_refunds();') !== false, 'the job writes the refund rows');

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
	$registry = pg_connections_registry();
	pg_assert_true(isset($registry['ga4_mp']), 'the registry row');
	pg_assert_same('connections.php', $registry['ga4_mp']['place'], 'its page');
	pg_assert_same('pg_ad_conversion_ga4_connected', $registry['ga4_mp']['state']['function'], 'its state reader');

	$reseal = ad_conversions_ga4_test_source('includes/fn/reseal.php');
	pg_assert_true(strpos($reseal, "'ad_conversions_ga4_mp', 'connect', array('fields' => array('secret'))") !== false, 'the secret is sealed again on a key reset');
}
