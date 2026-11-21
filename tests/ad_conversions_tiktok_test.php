<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/ad_conversions_tiktok.php: the web event and body
 * TikTok's Events API 2.0 asks for (hashed e-mail, the phone hashed in E.164,
 * plain browser and click ids, the person only with consent), TikTok's
 * recorded answers read as a send's outcome, the refusals made without a
 * call, the settings the page writes, the field shapes, and the places
 * outside the module that reach it.
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

function ad_conversions_tiktok_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A due purchase row as SC1 leaves it, with the person and TikTok's ids.
function ad_conversions_tiktok_test_event($person = true)
{
	$row = array(
		'event' => 'purchase',
		'event_id' => 'R7K2Q9',
		'person' => $person ? 1 : 0,
		'ids' => $person ? json_encode(array('ttp' => '2Xk9AbCdEfGhIjKlMnOp', 'ttclid' => 'E.C.P.AbCdEf123', 'ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0')) : null,
		'detail' => json_encode(array('value' => 12550, 'currency' => 'try', 'order_number' => '1042', 'time' => 1763600000,
			'contents' => array(array('id' => 'SKU-1', 'quantity' => 2, 'price' => 5000), array('id' => 'SKU-2', 'quantity' => 1, 'price' => 2550)))),
	);

	return pg_ad_conversion_event($row, array('billing_email_address' => ' Buyer@Example.com ', 'billing_phone_number' => '+90 (532) 111 22 33'));
}

// The phone in E.164 with its +, unlike Meta's digits alone.
function test_ad_conversions_tiktok_phone_hash()
{
	pg_assert_same(hash('sha256', '+905321112233'), pg_ad_conversion_tiktok_hash_phone('+90 (532) 111 22 33'), 'E.164 with the +');
	pg_assert_same(hash('sha256', '+905321112233'), pg_ad_conversion_tiktok_hash_phone('0090 532 111 22 33'), 'a leading 00 is the +');
	pg_assert_same(hash('sha256', '+12133734253'), pg_ad_conversion_tiktok_hash_phone('(+1)2133734253'), 'TikTok\'s own example');
	pg_assert_same('', pg_ad_conversion_tiktok_hash_phone('12 34'), 'too short');
	pg_assert_false(pg_ad_conversion_tiktok_hash_phone('+905321112233') === pg_ad_conversion_hash_phone('+905321112233'), 'not Meta\'s hash');
}

// The web event with the person: TikTok's names, hashed contact, plain ids.
function test_ad_conversions_tiktok_web_event_person()
{
	$phone = pg_ad_conversion_tiktok_hash_phone('+90 (532) 111 22 33');
	$web = pg_ad_conversion_tiktok_web_event(ad_conversions_tiktok_test_event(), $phone, 'https://shop.example/');

	pg_assert_same('Purchase', $web['event'], 'standard event name');
	pg_assert_same(1763600000, $web['event_time'], 'unix seconds');
	pg_assert_same('R7K2Q9', $web['event_id'], 'the dedup id is the order reference');
	pg_assert_same(array('url' => 'https://shop.example/'), $web['page'], 'the page of a web event');
	pg_assert_same(hash('sha256', 'buyer@example.com'), $web['user']['email'], 'e-mail trimmed, lower-cased, SHA-256');
	pg_assert_same($phone, $web['user']['phone'], 'phone hashed in E.164');
	pg_assert_same('2Xk9AbCdEfGhIjKlMnOp', $web['user']['ttp'], 'ttp never hashed');
	pg_assert_same('E.C.P.AbCdEf123', $web['user']['ttclid'], 'the click id from the row\'s ids');
	pg_assert_same('203.0.113.9', $web['user']['ip'], 'address never hashed');
	pg_assert_same('Mozilla/5.0', $web['user']['user_agent'], 'browser name');
	pg_assert_same(125.5, $web['properties']['value'], 'value as a number');
	pg_assert_same('TRY', $web['properties']['currency'], 'ISO 4217 upper case');
	pg_assert_same(array('content_id' => 'SKU-1', 'quantity' => 2, 'price' => 50.0), $web['properties']['contents'][0], 'contents line');
	pg_assert_same('product', $web['properties']['content_type'], 'content type');
	pg_assert_same('1042', $web['properties']['order_id'], 'order id');
}

// Without the person: no contact, no id, an empty user object.
function test_ad_conversions_tiktok_web_event_without_person()
{
	$web = pg_ad_conversion_tiktok_web_event(ad_conversions_tiktok_test_event(false), hash('sha256', '+905321112233'), '');
	$json = json_encode($web);

	pg_assert_same('{}', json_encode($web['user']), 'user is an empty object');
	pg_assert_false(strpos($json, 'ttp') !== false || strpos($json, '203.0.113.9') !== false || strpos($json, 'Mozilla') !== false, 'no id, address or browser');
	pg_assert_false(strpos($json, 'phone') !== false, 'no phone without the person, even when one is given');
	pg_assert_false(isset($web['page']), 'no empty page');
	pg_assert_same(125.5, $web['properties']['value'], 'the amount still goes');
}

// The body: event source web, the pixel code, the test code only for a test.
function test_ad_conversions_tiktok_body()
{
	$web = pg_ad_conversion_tiktok_web_event(ad_conversions_tiktok_test_event(), '', '');
	$real = pg_ad_conversion_tiktok_body($web, 'C4ABCDEF1234567890AB');
	$test = pg_ad_conversion_tiktok_body($web, 'C4ABCDEF1234567890AB', 'TEST123');

	pg_assert_same('web', $real['event_source'], 'event source');
	pg_assert_same('C4ABCDEF1234567890AB', $real['event_source_id'], 'the pixel code');
	pg_assert_same(1, count($real['data']), 'one event per row');
	pg_assert_false(isset($real['test_event_code']), 'no test code on a real event');
	pg_assert_same('TEST123', $test['test_event_code'], 'test code at the top level');
	pg_assert_false(strpos(json_encode($real), 'token') !== false, 'no token in the body');
	pg_assert_same('https://business-api.tiktok.com/open_api/v1.3/event/track/', PG_AD_CONVERSION_TIKTOK_URL, 'the address');
}

// TikTok's recorded answers.
function test_ad_conversions_tiktok_answers()
{
	$sent = pg_ad_conversion_tiktok_answer(array('ok' => true, 'status' => 200, 'body' => '{"code":0,"message":"OK","request_id":"2026112012000001","data":{}}'));
	pg_assert_true($sent['ok'], 'code 0 is sent');

	$bad = pg_ad_conversion_tiktok_answer(array('ok' => true, 'status' => 200, 'body' => '{"code":40002,"message":"Invalid value for event_source_id.","request_id":"R1","data":{}}'));
	pg_assert_false($bad['ok'], 'another code is not sent, though HTTP says 200');
	pg_assert_same(400, $bad['status'], 'a refusal');
	pg_assert_same('Invalid value for event_source_id. (code 40002) request_id R1', $bad['error'], 'TikTok\'s words, code and request id');
	pg_assert_false(api_http_should_retry(array('ok' => false, 'status' => $bad['status'])), 'a refusal is not retried');

	$throttled = pg_ad_conversion_tiktok_answer(array('ok' => true, 'status' => 200, 'body' => '{"code":40100,"message":"Too many requests.","request_id":"R2"}'));
	pg_assert_same(429, $throttled['status'], 'throttling is tried again');
	pg_assert_true(api_http_should_retry(array('ok' => false, 'status' => $throttled['status'])), 'retried');

	$down = pg_ad_conversion_tiktok_answer(array('ok' => true, 'status' => 200, 'body' => '{"code":50002,"message":"Internal error.","request_id":"R3"}'));
	pg_assert_same(429, $down['status'], 'TikTok\'s own fault is tried again');

	$http = pg_ad_conversion_tiktok_answer(array('ok' => false, 'status' => 503, 'body' => '<html>Unavailable</html>'));
	pg_assert_same(503, $http['status'], 'an HTTP error keeps its status');
	pg_assert_same('HTTP 503', $http['error'], 'a body that is not JSON');

	$empty = pg_ad_conversion_tiktok_answer(array('ok' => true, 'status' => 200, 'body' => ''));
	pg_assert_false($empty['ok'], 'a 200 without code 0 is not sent');
	pg_assert_same(400, $empty['status'], 'and not tried again');

	$silent = pg_ad_conversion_tiktok_answer(array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'Operation timed out'));
	pg_assert_same(0, $silent['status'], 'no answer holds the service');
	pg_assert_same('Operation timed out', $silent['error'], 'the transport error');
}

// The refusals made without a call.
function test_ad_conversions_tiktok_refusals()
{
	$keys = array('pixel_code' => 'C4ABCDEF1234567890AB', 'token' => 'abc123');
	$event = ad_conversions_tiktok_test_event();

	pg_assert_same('', pg_ad_conversion_tiktok_refusal($event, $keys), 'a purchase goes');
	pg_assert_same('The pixel code or the access token is missing.', pg_ad_conversion_tiktok_refusal($event, array('pixel_code' => 'C4A', 'token' => '')), 'no token');
	pg_assert_same('TikTok has no event for this.', pg_ad_conversion_tiktok_refusal(array('event' => 'refund') + $event, $keys), 'no refund event at TikTok');
	pg_assert_same('InitiateCheckout', pg_ad_conversion_tiktok_event_name('begin_checkout'), 'begin checkout');
	pg_assert_same('CompleteRegistration', pg_ad_conversion_tiktok_event_name('complete_registration'), 'registration');
}

// The settings the page writes.
function test_ad_conversions_tiktok_settings()
{
	$stored = pg_ad_conversion_tiktok_stored(json_encode(array('connected' => true, 'pixel_code' => 'C4AAAAAAAAAA', 'token' => 'sealed:iv', 'events' => array('purchase'), 'person_notice_off' => true)));

	pg_assert_same('sealed:iv', $stored['token'], 'stored token read');
	pg_assert_true($stored['person_notice_off'], 'box read');

	$kept = pg_ad_conversion_tiktok_settings_merge($stored, array('pixel_code' => ' c4bbbbbbbbbb ', 'events' => array('purchase', 'evil'), 'without_person' => array('purchase')), '');
	pg_assert_same('sealed:iv', $kept['token'], 'empty field keeps the token');
	pg_assert_same('C4BBBBBBBBBB', $kept['pixel_code'], 'pixel code trimmed and upper-cased');
	pg_assert_same(array('purchase'), $kept['events'], 'unknown events dropped');
	pg_assert_same(array('purchase'), $kept['without_person'], 'without the person');
	pg_assert_false($kept['person_notice_off'], 'a cleared box is cleared');
	pg_assert_true($kept['connected'], 'connected with pixel code and token');

	$parsed = pg_ad_conversion_settings_parse(json_encode($kept));
	pg_assert_same(array('purchase'), $parsed['events'], 'the queue reads the same row');

	$new = pg_ad_conversion_tiktok_settings_merge(pg_ad_conversion_tiktok_stored(''), array('pixel_code' => 'C4CCCCCCCCCC'), 'new:iv');
	pg_assert_same('new:iv', $new['token'], 'a new token');
	pg_assert_same(array(), $new['events'], 'nothing ticked, nothing sent');
	pg_assert_false(pg_ad_conversion_tiktok_settings_merge(pg_ad_conversion_tiktok_stored(''), array('pixel_code' => 'C4CCCCCCCCCC'), '')['connected'], 'no token, not connected');
}

function test_ad_conversions_tiktok_field_shapes()
{
	pg_assert_true(pg_ad_conversion_tiktok_pixel_valid('C4ABCDEF1234567890AB'), 'pixel code');
	pg_assert_false(pg_ad_conversion_tiktok_pixel_valid('c4abc'), 'pixel code too short');
	pg_assert_false(pg_ad_conversion_tiktok_pixel_valid('C4ABC-EF1234567890AB'), 'pixel code dash');
	pg_assert_true(pg_ad_conversion_tiktok_token_valid(str_repeat('a1b2', 10)), 'token');
	pg_assert_false(pg_ad_conversion_tiktok_token_valid('a token with spaces here'), 'token spaces');
	pg_assert_true(pg_ad_conversion_tiktok_test_code_valid('TEST12345'), 'test code');
	pg_assert_true(pg_ad_conversion_tiktok_test_code_valid(''), 'empty test code');
	pg_assert_false(pg_ad_conversion_tiktok_test_code_valid('TEST-1'), 'test code dash');
}

// The places outside the module.
function test_ad_conversions_tiktok_places()
{
	pg_assert_same('pg_ad_conversion_send_tiktok_events', pg_ad_conversion_sender('tiktok_events'), 'the dispatcher finds the sender');

	$connections = ad_conversions_tiktok_test_source('connections.php');
	pg_assert_true(strpos($connections, "pg_ad_conversion_tiktok_post_page(\$action, \$_POST, \$liveform)") !== false, 'the write');
	pg_assert_true(strpos($connections, "pg_ad_conversion_tiktok_page(\$page, connections_privacy_may_manage(\$user), \$can_seal)") !== false, 'the page, under the Visitors and privacy gate');
	pg_assert_true(strpos($connections, "array('tiktok_events_save', 'tiktok_events_remove'), true) && connections_privacy_may_manage(\$user)") !== false, 'the write under the same gate');

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
	$registry = pg_connections_registry();
	pg_assert_true(isset($registry['tiktok_events']), 'the registry row');
	pg_assert_same('connections.php', $registry['tiktok_events']['place'], 'its page');
	pg_assert_same('pg_ad_conversion_tiktok_connected', $registry['tiktok_events']['state']['function'], 'its state reader');

	$reseal = ad_conversions_tiktok_test_source('includes/fn/reseal.php');
	pg_assert_true(strpos($reseal, "'ad_conversions_tiktok_events', 'connect', array('fields' => array('token'))") !== false, 'the token is sealed again on a key reset');

	$module = ad_conversions_tiktok_test_source('includes/fn/ad_conversions_tiktok.php');
	pg_assert_true(strpos($module, "'Access-Token: '") !== false, 'the token goes in the Access-Token header');
	pg_assert_false(strpos($module, 'ad_click_ids') !== false, 'the click id is read from the row, never the capture');
}

// The page offers every event TikTok names (no refund, which TikTok has no
// event for); the save keeps them, the forms and the counter sales switch.
function test_ad_conversions_tiktok_all_events()
{
	pg_assert_same(array('purchase', 'add_to_cart', 'begin_checkout', 'complete_registration', 'lead'), array_keys(pg_ad_conversion_tiktok_events()), 'five, in page order');
	pg_assert_same('', pg_ad_conversion_tiktok_event_name('refund'), 'no refund');

	$saved = pg_ad_conversion_tiktok_settings_merge(pg_ad_conversion_tiktok_stored(''), array(
		'pixel_code' => 'C4AAAAAAAAAA',
		'events' => array('purchase', 'refund', 'add_to_cart', 'begin_checkout', 'complete_registration', 'lead'),
		'lead_forms' => array('9'),
		'counter_sales' => '1',
	), 'sealed');
	$parsed = pg_ad_conversion_settings_parse(json_encode($saved));
	pg_assert_same(array('purchase', 'add_to_cart', 'begin_checkout', 'complete_registration', 'lead'), $parsed['events'], 'every offered event kept');
	pg_assert_same(array(9), $parsed['lead_forms'], 'the forms sent as Lead');
	pg_assert_true($parsed['counter_sales'], 'the counter sales switch');
}

// A Lead has no amount; a counter sale goes without the person.
function test_ad_conversions_tiktok_lead_and_counter_sale()
{
	$lead = pg_ad_conversion_event(array('event' => 'lead', 'event_id' => 'lead-F1', 'person' => 1, 'ids' => json_encode(array('ttp' => 'abc')),
		'detail' => json_encode(array('value' => 0, 'currency' => '', 'order_number' => '', 'time' => 1763600000, 'contents' => array()))), array());
	$web = pg_ad_conversion_tiktok_web_event($lead, '', 'https://shop.example/');
	pg_assert_same('Lead', $web['event'], 'Lead');
	pg_assert_same('{}', json_encode($web['properties']), 'no value or currency');

	$sale = pg_ad_conversion_event(array('event' => 'purchase', 'event_id' => 'R7K2Q9', 'person' => 1,
		'ids' => json_encode(array('ttp' => '2Xk9AbCdEfGhIjKlMnOp', 'ttclid' => 'E.C.P.AbCdEf123', 'ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0')),
		'detail' => json_encode(array('value' => 12550, 'currency' => 'TRY', 'order_number' => '1042', 'time' => 1763600000, 'contents' => array(), 'counter_sale' => true))),
		array('billing_email_address' => 'buyer@example.com', 'billing_phone_number' => '+90 532 111 22 33'));
	pg_assert_same('', pg_ad_conversion_tiktok_order_phone($sale), 'no phone looked up');
	$web = pg_ad_conversion_tiktok_web_event($sale, '', 'https://shop.example/');
	pg_assert_same('{}', json_encode($web['user']), 'no e-mail, phone, browser or click id');
	pg_assert_same(125.5, $web['properties']['value'], 'the amount goes');
}

