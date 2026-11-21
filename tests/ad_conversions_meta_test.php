<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/ad_conversions_meta.php: the server event and body
 * Meta's documents ask for (hashed e-mail and phone, plain browser ids, the
 * person only with consent), Meta's recorded answers read as a send's
 * outcome, the refusals made without a call, the settings the page writes,
 * the field shapes, and the places outside the module that reach it.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ad_conversions_meta.php');

function ad_conversions_meta_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A due purchase row as SC1 leaves it, with the person.
function ad_conversions_meta_test_event($person = true)
{
	$row = array(
		'event' => 'purchase',
		'event_id' => 'R7K2Q9',
		'person' => $person ? 1 : 0,
		'ids' => $person ? json_encode(array('fbp' => 'fb.1.1700000000000.123456789', 'fbc' => 'fb.1.1700000000000.AbCdEf', 'ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0')) : null,
		'detail' => json_encode(array('value' => 12550, 'currency' => 'try', 'order_number' => '1042', 'time' => 1763600000,
			'contents' => array(array('id' => 'SKU-1', 'quantity' => 2, 'price' => 5000), array('id' => 'SKU-2', 'quantity' => 1, 'price' => 2550)))),
	);

	return pg_ad_conversion_event($row, array('billing_email_address' => ' Buyer@Example.com ', 'billing_phone_number' => '+90 (532) 111 22 33'));
}

// The server event with the person: Meta's names, hashed contact, plain ids.
function test_ad_conversions_meta_server_event_person()
{
	$server = pg_ad_conversion_meta_server_event(ad_conversions_meta_test_event(), 'https://shop.example/');

	pg_assert_same('Purchase', $server['event_name'], 'standard event name');
	pg_assert_same(1763600000, $server['event_time'], 'unix seconds');
	pg_assert_same('R7K2Q9', $server['event_id'], 'the dedup id is the order reference');
	pg_assert_same('website', $server['action_source'], 'action source');
	pg_assert_same('https://shop.example/', $server['event_source_url'], 'source url required for website events');
	pg_assert_same(array(hash('sha256', 'buyer@example.com')), $server['user_data']['em'], 'e-mail trimmed, lower-cased, SHA-256');
	pg_assert_same(array(hash('sha256', '905321112233')), $server['user_data']['ph'], 'phone digits with country code, SHA-256');
	pg_assert_same('fb.1.1700000000000.123456789', $server['user_data']['fbp'], 'fbp never hashed');
	pg_assert_same('fb.1.1700000000000.AbCdEf', $server['user_data']['fbc'], 'fbc never hashed');
	pg_assert_same('203.0.113.9', $server['user_data']['client_ip_address'], 'address never hashed');
	pg_assert_same('Mozilla/5.0', $server['user_data']['client_user_agent'], 'browser name');
	pg_assert_same(125.5, $server['custom_data']['value'], 'value as a number');
	pg_assert_same('TRY', $server['custom_data']['currency'], 'ISO 4217 upper case');
	pg_assert_same(array('id' => 'SKU-1', 'quantity' => 2, 'item_price' => 50.0), $server['custom_data']['contents'][0], 'contents line');
	pg_assert_same('product', $server['custom_data']['content_type'], 'content type');
	pg_assert_same('1042', $server['custom_data']['order_id'], 'order id');
}

// Without the person: no contact, no id, an empty user_data object.
function test_ad_conversions_meta_server_event_without_person()
{
	$server = pg_ad_conversion_meta_server_event(ad_conversions_meta_test_event(false), '');
	$json = json_encode($server);

	pg_assert_same('{}', json_encode($server['user_data']), 'user_data is an empty object');
	pg_assert_false(strpos($json, 'fbp') !== false || strpos($json, '203.0.113.9') !== false || strpos($json, 'Mozilla') !== false, 'no id, address or browser');
	pg_assert_false(isset($server['event_source_url']), 'no empty source url');
	pg_assert_same(125.5, $server['custom_data']['value'], 'the amount still goes');
}

// The body: one event, the token in the body, the test code only for a test.
function test_ad_conversions_meta_body()
{
	$server = pg_ad_conversion_meta_server_event(ad_conversions_meta_test_event(), '');
	$real = pg_ad_conversion_meta_body($server, 'EAAtoken');
	$test = pg_ad_conversion_meta_body($server, 'EAAtoken', 'TEST123');

	pg_assert_same(1, count($real['data']), 'one event per row');
	pg_assert_same('EAAtoken', $real['access_token'], 'token as a body parameter');
	pg_assert_false(isset($real['test_event_code']), 'no test code on a real event');
	pg_assert_same('TEST123', $test['test_event_code'], 'test code at the top level');
	pg_assert_same('https://graph.facebook.com/v27.0/1234567890/events', pg_ad_conversion_meta_url('1234567890'), 'the address');
	pg_assert_false(strpos(pg_ad_conversion_meta_url('1234567890'), 'token') !== false, 'no token in the address');
}

// Meta's recorded answers.
function test_ad_conversions_meta_answers()
{
	$sent = pg_ad_conversion_meta_answer(array('ok' => true, 'status' => 200, 'body' => '{"events_received":1,"messages":[],"fbtrace_id":"AbC123"}'));
	pg_assert_true($sent['ok'], 'events_received 1 is sent');

	$none = pg_ad_conversion_meta_answer(array('ok' => true, 'status' => 200, 'body' => '{"events_received":0,"messages":[]}'));
	pg_assert_false($none['ok'], 'nothing received is not sent');
	pg_assert_same(400, $none['status'], 'and not tried again');

	$bad = pg_ad_conversion_meta_answer(array('ok' => false, 'status' => 400, 'body' => '{"error":{"message":"Invalid parameter","type":"OAuthException","code":100,"error_subcode":2804003,"error_user_msg":"The event time is too old.","fbtrace_id":"Xyz789"}}'));
	pg_assert_same(400, $bad['status'], 'a refusal stays 400');
	pg_assert_same('The event time is too old. (code 100, 2804003) fbtrace_id Xyz789', $bad['error'], 'Meta\'s words, code and trace id');
	pg_assert_false(api_http_should_retry(array('ok' => false, 'status' => $bad['status'])), 'a refusal is not retried');

	$token = pg_ad_conversion_meta_answer(array('ok' => false, 'status' => 400, 'body' => '{"error":{"message":"Error validating access token","type":"OAuthException","code":190,"fbtrace_id":"T1"}}'));
	pg_assert_same(400, $token['status'], 'an expired token is given up');

	$throttled = pg_ad_conversion_meta_answer(array('ok' => false, 'status' => 400, 'body' => '{"error":{"message":"Application request limit reached","type":"OAuthException","code":4,"fbtrace_id":"T2"}}'));
	pg_assert_same(429, $throttled['status'], 'throttling is tried again');
	pg_assert_true(api_http_should_retry(array('ok' => false, 'status' => $throttled['status'])), 'retried');

	$down = pg_ad_conversion_meta_answer(array('ok' => false, 'status' => 500, 'body' => '{"error":{"message":"An unknown error occurred","code":1}}'));
	pg_assert_same(429, $down['status'], 'downtime is tried again');

	$silent = pg_ad_conversion_meta_answer(array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'Operation timed out'));
	pg_assert_same(0, $silent['status'], 'no answer holds the service');
	pg_assert_same('Operation timed out', $silent['error'], 'the transport error');

	$html = pg_ad_conversion_meta_answer(array('ok' => false, 'status' => 502, 'body' => '<html>Bad gateway</html>'));
	pg_assert_same('HTTP 502', $html['error'], 'a body that is not JSON');
}

// The refusals made without a call.
function test_ad_conversions_meta_refusals()
{
	$keys = array('pixel_id' => '1234567890', 'token' => 'EAAtoken');
	$event = ad_conversions_meta_test_event();
	$now = 1763600000 + 3600;

	pg_assert_same('', pg_ad_conversion_meta_refusal($event, $keys, $now), 'a fresh purchase goes');
	pg_assert_same('The pixel ID or the access token is missing.', pg_ad_conversion_meta_refusal($event, array('pixel_id' => '1', 'token' => ''), $now), 'no token');
	pg_assert_same('Meta takes events up to 7 days old.', pg_ad_conversion_meta_refusal($event, $keys, 1763600000 + 7 * 86400), 'too old');
	pg_assert_same('Meta has no event for this.', pg_ad_conversion_meta_refusal(array('event' => 'refund') + $event, $keys, $now), 'no refund event at Meta');
	pg_assert_same('', pg_ad_conversion_meta_event_name('refund'), 'refund has no name');
	pg_assert_same('InitiateCheckout', pg_ad_conversion_meta_event_name('begin_checkout'), 'begin checkout');
}

// The settings the page writes.
function test_ad_conversions_meta_settings()
{
	$stored = pg_ad_conversion_meta_stored(json_encode(array('connected' => true, 'pixel_id' => '111111', 'token' => 'sealed:iv', 'events' => array('purchase'), 'person_notice_off' => true)));

	pg_assert_same('sealed:iv', $stored['token'], 'stored token read');
	pg_assert_true($stored['person_notice_off'], 'box read');

	$kept = pg_ad_conversion_meta_settings_merge($stored, array('pixel_id' => ' 222222 ', 'events' => array('purchase', 'evil'), 'without_person' => array('purchase')), '');
	pg_assert_same('sealed:iv', $kept['token'], 'empty field keeps the token');
	pg_assert_same('222222', $kept['pixel_id'], 'pixel trimmed');
	pg_assert_same(array('purchase'), $kept['events'], 'unknown events dropped');
	pg_assert_same(array(), $kept['without_person'], 'a posted without the person is not saved for Meta');
	pg_assert_false($kept['person_notice_off'], 'a cleared box is cleared');
	pg_assert_true($kept['connected'], 'connected with pixel and token');

	$parsed = pg_ad_conversion_settings_parse(json_encode($kept));
	pg_assert_same(array('purchase'), $parsed['events'], 'the queue reads the same row');

	$new = pg_ad_conversion_meta_settings_merge(pg_ad_conversion_meta_stored(''), array('pixel_id' => '333333'), 'new:iv');
	pg_assert_same('new:iv', $new['token'], 'a new token');
	pg_assert_same(array(), $new['events'], 'nothing ticked, nothing sent');
	pg_assert_false(pg_ad_conversion_meta_settings_merge(pg_ad_conversion_meta_stored(''), array('pixel_id' => '1'), '')['connected'], 'no token, not connected');
}

function test_ad_conversions_meta_field_shapes()
{
	pg_assert_true(pg_ad_conversion_meta_pixel_valid('123456789012345'), 'pixel digits');
	pg_assert_false(pg_ad_conversion_meta_pixel_valid('12a45'), 'pixel letters');
	pg_assert_true(pg_ad_conversion_meta_token_valid(str_repeat('EAAB', 40)), 'token');
	pg_assert_false(pg_ad_conversion_meta_token_valid('EAA token with spaces here'), 'token spaces');
	pg_assert_true(pg_ad_conversion_meta_test_code_valid('TEST12345'), 'test code');
	pg_assert_true(pg_ad_conversion_meta_test_code_valid(''), 'empty test code');
	pg_assert_false(pg_ad_conversion_meta_test_code_valid('TEST-1'), 'test code dash');

	$test = pg_ad_conversion_meta_test_event(1763600000, 'TRY');
	pg_assert_same(array(), $test['user'], 'the test event has no person');
	pg_assert_same('pg-test-1763600000', $test['event_id'], 'its own id');
}

// The places outside the module.
function test_ad_conversions_meta_places()
{
	pg_assert_same('pg_ad_conversion_send_meta_capi', pg_ad_conversion_sender('meta_capi'), 'the dispatcher finds the sender');

	$connections = ad_conversions_meta_test_source('connections.php');
	pg_assert_true(strpos($connections, "pg_ad_conversion_meta_post_page(\$action, \$_POST, \$liveform)") !== false, 'the write');
	pg_assert_true(strpos($connections, "pg_ad_conversion_meta_page(\$page, connections_privacy_may_manage(\$user), \$can_seal)") !== false, 'the page, under the Visitors and privacy gate');
	pg_assert_true(strpos($connections, "array('meta_capi_save', 'meta_capi_remove')") !== false && strpos($connections, '&& connections_privacy_may_manage($user)') !== false, 'the write under the same gate');

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
	$registry = pg_connections_registry();
	pg_assert_true(isset($registry['meta_capi']), 'the registry row');
	pg_assert_same('connections.php', $registry['meta_capi']['place'], 'its page');
	pg_assert_same('pg_ad_conversion_meta_connected', $registry['meta_capi']['state']['function'], 'its state reader');

	$reseal = ad_conversions_meta_test_source('includes/fn/reseal.php');
	pg_assert_true(strpos($reseal, "'ad_conversions_meta_capi', 'connect', array('fields' => array('token'))") !== false, 'the token is sealed again on a key reset');
}

// A counter sale built for Meta carries nothing of the person, even when the
// row held ids: user_data has no hashed e-mail or phone, no fbp or fbc, no
// IP address and no user agent. Meta refuses an event without any of them,
// so a counter sale is never queued for Meta, its switch is never saved and
// the page shows it off with the reason.
function test_ad_conversions_meta_counter_sale()
{
	$row = array(
		'event' => 'purchase',
		'event_id' => 'R7K2Q9',
		'person' => 1,
		'ids' => json_encode(array('fbp' => 'fb.1.1700000000000.123456789', 'fbc' => 'fb.1.1700000000000.AbCdEf', 'ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0 (Till)')),
		'detail' => json_encode(array('value' => 12550, 'currency' => 'try', 'order_number' => '1042', 'time' => 1763600000, 'contents' => array(), 'counter_sale' => true)),
	);
	$order = array('billing_email_address' => 'buyer@example.com', 'billing_phone_number' => '+90 532 111 22 33');
	$server = pg_ad_conversion_meta_server_event(pg_ad_conversion_event($row, $order), 'https://shop.example/');
	$user = (array) $server['user_data'];
	$json = json_encode($server);

	foreach (array('em', 'ph', 'fbp', 'fbc', 'client_ip_address', 'client_user_agent') as $key) {
		pg_assert_false(isset($user[$key]), 'no ' . $key);
	}

	pg_assert_same('{}', json_encode($server['user_data']), 'user_data empty');

	foreach (array(hash('sha256', 'buyer@example.com'), pg_ad_conversion_hash_phone($order['billing_phone_number']), 'fb.1.', '203.0.113.9', 'Mozilla/5.0 (Till)') as $value) {
		pg_assert_false(strpos($json, str_replace('/', '\/', $value)) !== false, 'nothing of the buyer: ' . $value);
	}

	pg_assert_same(125.5, $server['custom_data']['value'], 'the amount goes');

	// The same row as a site purchase does carry the person: the check above
	// is not empty by accident.
	$row['detail'] = json_encode(array('value' => 12550, 'currency' => 'try', 'order_number' => '1042', 'time' => 1763600000, 'contents' => array()));
	$site = (array) pg_ad_conversion_meta_server_event(pg_ad_conversion_event($row, $order), 'https://shop.example/')['user_data'];
	pg_assert_same(array('em', 'ph', 'fbp', 'fbc', 'client_ip_address', 'client_user_agent'), array_keys($site), 'a site purchase with consent carries the person');

	// Meta takes no counter sale: never planned, never saved, shown off.
	pg_assert_false(pg_ad_conversion_counter_sale_offered('meta_capi'), 'Meta takes no counter sale');
	pg_assert_same(array(), pg_ad_conversion_counter_sale_plan(array('meta_capi' => array('connected' => true, 'counter_sales' => true, 'events' => array('purchase')))), 'never queued for Meta');

	$stored = pg_ad_conversion_meta_stored(json_encode(array('connected' => true, 'pixel_id' => '111111', 'token' => 'sealed:iv', 'events' => array('purchase'))));
	pg_assert_false($stored['counter_sales'], 'off on a row saved before the switch');
	pg_assert_false(pg_ad_conversion_meta_settings_merge($stored, array('pixel_id' => '111111', 'events' => array('purchase'), 'counter_sales' => '1'), '')['counter_sales'], 'the switch is never saved for Meta');

	$fields = ad_conversions_meta_test_source('includes/fn/ad_conversions.php');
	pg_assert_contains("lang('Meta takes an event only with some customer information, so counter sales are not sent to Meta.')", $fields, 'the page says why');
	pg_assert_contains("!\$counter_offered) . '", $fields, 'the switch is disabled');
}

// Meta asks for at least one customer information parameter with every
// event: a purchase without consent is never queued for Meta, even when
// "Send events without the person" was stored before; the stored value is
// kept, the switch is shown off and disabled, and a due row without the
// person is given up without a call.
function test_ad_conversions_meta_no_personless_purchase()
{
	$meta = array('connected' => true, 'events' => array('purchase'), 'person_notice_off' => false, 'without_person' => array('purchase'));
	$settings = array('meta_capi' => $meta, 'ga4_mp' => $meta, 'tiktok_events' => $meta);

	pg_assert_false(pg_ad_conversion_without_person_offered('meta_capi'), 'Meta takes no purchase without the person');
	pg_assert_true(pg_ad_conversion_without_person_offered('ga4_mp'), 'GA4 does');
	pg_assert_true(pg_ad_conversion_without_person_offered('tiktok_events'), 'TikTok does');

	pg_assert_same(array('ga4_mp' => 'insert', 'tiktok_events' => 'insert'), pg_ad_conversion_completion_plan(array(), $settings), 'no consent, no row: none written for Meta');

	$rows = array('meta_capi' => array('status' => '0', 'person' => '0'), 'ga4_mp' => array('status' => '0', 'person' => '0'));
	pg_assert_same(array('meta_capi' => 'give_up', 'ga4_mp' => 'due', 'tiktok_events' => 'insert'), pg_ad_conversion_completion_plan($rows, $settings), 'consent taken back: Meta left with 3');
	pg_assert_same(array('meta_capi' => 'due'), pg_ad_conversion_completion_plan(array('meta_capi' => array('status' => '0', 'person' => '1')), array('meta_capi' => $meta)), 'with the person it still goes');

	// The stored value is not deleted, and a posted one is not saved.
	$stored = pg_ad_conversion_meta_stored(json_encode(array('connected' => true, 'pixel_id' => '111111', 'token' => 'sealed:iv') + $meta));
	pg_assert_same(array('purchase'), pg_ad_conversion_meta_settings_merge($stored, array('pixel_id' => '111111', 'events' => array('purchase')), '')['without_person'], 'stored value kept on save');

	// A due row without the person (consent taken back after completion, or
	// queued before): no request, given up as no consent.
	$sent = pg_ad_conversion_send_meta_capi(ad_conversions_meta_test_event(false), array());
	pg_assert_same(array('ok' => false, 'status' => 400, 'error' => 'No consent'), $sent, 'refused without a call');
	pg_assert_true(pg_ad_conversion_meta_without_person(ad_conversions_meta_test_event(false)), 'without the person');
	pg_assert_false(pg_ad_conversion_meta_without_person(ad_conversions_meta_test_event()), 'with the person');

	$fields = ad_conversions_meta_test_source('includes/fn/ad_conversions.php');
	pg_assert_contains("lang('Meta asks for at least one piece of the person\\'s data with every event, so a purchase without the person is not sent to Meta.')", $fields, 'the page says why');
	pg_assert_contains("!\$without_offered) . '", $fields, 'the switch is disabled');
	pg_assert_contains("\$without_offered && in_array('purchase', \$stored['without_person'], true)", $fields, 'the switch is shown off');
}

// "Send a test event" carries a fixed external_id and a fixed browser name,
// nothing of a real person; a queued event never gets either.
function test_ad_conversions_meta_test_event_user_data()
{
	$test = pg_ad_conversion_meta_test_server_event(1763600000, 'try', 'https://shop.example/');
	$expected = array('external_id' => array(hash('sha256', 'pinegrap-test')), 'client_user_agent' => 'Pinegrap test');

	pg_assert_same($expected, $test['user_data'], 'the two fixed fields, nothing else');
	pg_assert_same('Purchase', $test['event_name'], 'a purchase');
	pg_assert_same(1.0, $test['custom_data']['value'], 'of 1.00');
	pg_assert_same('TRY', $test['custom_data']['currency'], 'in the site currency');
	pg_assert_same(0, strpos($test['event_id'], 'pg-test-'), 'its own id');

	foreach (array(ad_conversions_meta_test_event(), ad_conversions_meta_test_event(false)) as $event) {
		$json = json_encode(pg_ad_conversion_meta_server_event($event, 'https://shop.example/'));
		pg_assert_false(strpos($json, 'external_id') !== false, 'no external_id on a queue event');
		pg_assert_false(strpos($json, 'Pinegrap test') !== false, 'no test browser name on a queue event');
		pg_assert_false(strpos($json, hash('sha256', 'pinegrap-test')) !== false, 'no test id on a queue event');
	}

	// The test send uses the test builder; the queue's sender never does.
	$module = ad_conversions_meta_test_source('includes/fn/ad_conversions_meta.php');
	$start = strpos($module, 'function pg_ad_conversion_send_meta_capi(');
	$end = strpos($module, "\n}\n", $start);
	pg_assert_false(strpos(substr($module, $start, $end - $start), '_test_') !== false, 'the queue sender builds no test data');
	$start = strpos($module, 'function pg_ad_conversion_meta_send_test(');
	$end = strpos($module, "\n}\n", $start);
	pg_assert_contains('pg_ad_conversion_meta_test_server_event(', substr($module, $start, $end - $start), 'the test send uses it');
}
