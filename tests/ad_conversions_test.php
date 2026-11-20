<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/ad_conversions.php: the consent reader's three inputs
 * (notice on, the stored answer, the service's box), consent taken back, a
 * marketplace order and a counter sale never sent, a payment service's
 * message completing the order (no visitor cookie read), a second completion
 * (no second event), the purchase event and its hashing, the outcome of a
 * send, and the places outside the module that call it.
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

function ad_conversions_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A connected service's settings as the module reads them.
function ad_conversions_test_settings(array $more = array())
{
	return $more + array('connected' => true, 'events' => array('purchase'), 'person_notice_off' => false, 'without_person' => array());
}

// The consent reader: notice off and box off, notice off and box on, notice
// on and no answer, yes, no.
function test_ad_conversions_consent_reader()
{
	pg_assert_false(pg_ad_conversion_consent_by(false, array(), false), 'notice off, box off');
	pg_assert_true(pg_ad_conversion_consent_by(false, array(), true), 'notice off, box on');
	pg_assert_false(pg_ad_conversion_consent_by(false, array('marketing' => true), false), 'notice off: a stored yes does not count');
	pg_assert_false(pg_ad_conversion_consent_by(true, array(), false), 'notice on, no answer');
	pg_assert_false(pg_ad_conversion_consent_by(true, array(), true), 'notice on, no answer: the box acts only while the notice is off');
	pg_assert_true(pg_ad_conversion_consent_by(true, array('marketing' => true), false), 'notice on, yes');
	pg_assert_false(pg_ad_conversion_consent_by(true, array('marketing' => false), true), 'notice on, no');
	pg_assert_false(pg_ad_conversion_consent_by(true, array('analytics' => true), false), 'statistics is not marketing');
}

// Consent taken back: the row loses the ids and the person, as long as it
// was not sent.
function test_ad_conversions_consent_taken_back()
{
	pg_assert_same(array('ids' => null, 'person' => 0), pg_ad_conversion_withdrawn_values(), 'ids cleared, no person');

	$changes = array(array('channel' => 'cookie', 'purpose' => 'analytics', 'status' => 'refused'));
	pg_assert_false(pg_ad_conversion_marketing_refused($changes), 'statistics refused only');

	$changes[] = array('channel' => 'cookie', 'purpose' => 'marketing', 'status' => 'refused');
	pg_assert_true(pg_ad_conversion_marketing_refused($changes), 'marketing refused');
	pg_assert_false(pg_ad_conversion_marketing_refused(array(array('channel' => 'cookie', 'purpose' => 'marketing', 'status' => 'granted'))), 'marketing granted');
	pg_assert_false(pg_ad_conversion_marketing_refused(null), 'a malformed answer');

	$sql = pg_ad_conversion_withdraw_sql();
	pg_assert_contains('ids = NULL', $sql, 'ids cleared');
	pg_assert_contains('person = 0', $sql, 'person cleared');
	pg_assert_contains('status IN (0, 1)', $sql, 'a sent row is left as it is');
}

// A marketplace order is never sent; a counter sale not without its setting.
function test_ad_conversions_marketplace_order()
{
	pg_assert_false(pg_ad_conversion_order_sendable('marketplace'), 'marketplace');
	pg_assert_false(pg_ad_conversion_order_sendable('local'), 'counter sale');
	pg_assert_true(pg_ad_conversion_order_sendable('online'), 'site order');
	pg_assert_true(pg_ad_conversion_order_sendable(''), 'a site without the type column');
	pg_assert_true(pg_ad_conversion_order_sendable(null), 'no type read');
}

// The ids kept for each service, from the visitor's cookies.
function test_ad_conversions_ids_per_service()
{
	$cookies = array('_fbp' => 'fb.1.1700000000.123', '_fbc' => 'fb.1.1700000000.AbC', '_ga' => 'GA1.1.111.222', '_ttp' => 'tt-ABC', 'other' => 'x');

	pg_assert_same(array('fbp' => 'fb.1.1700000000.123', 'fbc' => 'fb.1.1700000000.AbC', 'ip' => '203.0.113.5', 'user_agent' => 'Agent'), pg_ad_conversion_ids_for('meta_capi', $cookies, '203.0.113.5', 'Agent'), 'Meta');
	pg_assert_same(array('client_id' => '111.222', 'ip' => '203.0.113.5', 'user_agent' => 'Agent'), pg_ad_conversion_ids_for('ga4_mp', $cookies, '203.0.113.5', 'Agent'), 'GA4');
	pg_assert_same(array('ttp' => 'tt-ABC', 'ip' => '203.0.113.5', 'user_agent' => 'Agent'), pg_ad_conversion_ids_for('tiktok_events', $cookies, '203.0.113.5', 'Agent'), 'TikTok');
	pg_assert_same(array('ip' => '', 'user_agent' => ''), pg_ad_conversion_ids_for('meta_capi', array('_fbp' => array('x'), '_fbc' => "a\nb"), '', ''), 'unreadable cookies left out');
	pg_assert_same('', pg_ad_conversion_ga_client_id('GA1.2'), 'a short _ga');
	pg_assert_same('5.6', pg_ad_conversion_ga_client_id('GA1.3.5.6'), 'any domain depth');
}

// At the payment step a consenting buyer gets a status 0 row per connected
// service that takes the purchase; without consent none, and a row kept
// earlier loses its ids.
function test_ad_conversions_payment_step_plan()
{
	$settings = array(
		'meta_capi' => ad_conversions_test_settings(),
		'ga4_mp' => ad_conversions_test_settings(array('person_notice_off' => true)),
		'tiktok_events' => ad_conversions_test_settings(array('events' => array())),
	);

	pg_assert_same(array('meta_capi' => 'keep', 'ga4_mp' => 'keep'), pg_ad_conversion_payment_step_plan(true, array('marketing' => true), $settings), 'notice on, yes');
	pg_assert_same(array('meta_capi' => 'withdraw', 'ga4_mp' => 'withdraw'), pg_ad_conversion_payment_step_plan(true, array('marketing' => false), $settings), 'notice on, no');
	pg_assert_same(array('meta_capi' => 'withdraw', 'ga4_mp' => 'keep'), pg_ad_conversion_payment_step_plan(false, array(), $settings), 'notice off: the box decides');
	pg_assert_same(array(), pg_ad_conversion_payment_step_plan(true, array('marketing' => true), array('meta_capi' => ad_conversions_test_settings(array('connected' => false)))), 'not connected');
}

// Completion queues what the payment step kept and never reads a cookie: a
// payment service's message completes the order with the rows as they are.
function test_ad_conversions_completion_plan()
{
	$settings = array(
		'meta_capi' => ad_conversions_test_settings(),
		'ga4_mp' => ad_conversions_test_settings(array('without_person' => array('purchase'))),
		'tiktok_events' => ad_conversions_test_settings(),
	);

	$_COOKIE['_fbp'] = 'fb.1.1.999';

	$rows = array('meta_capi' => array('status' => '0', 'person' => '1'));

	pg_assert_same(array('meta_capi' => 'due', 'ga4_mp' => 'insert'), pg_ad_conversion_completion_plan($rows, $settings), 'kept row due, a row without the person where the setting asks');

	$rows = array('meta_capi' => array('status' => '0', 'person' => '0'), 'ga4_mp' => array('status' => '0', 'person' => '0'));

	pg_assert_same(array('meta_capi' => 'give_up', 'ga4_mp' => 'due'), pg_ad_conversion_completion_plan($rows, $settings), 'consent taken back: given up, or due without the person');

	unset($_COOKIE['_fbp']);

	$module = ad_conversions_test_source('includes/fn/ad_conversions.php');
	$start = strpos($module, 'function pg_ad_conversion_order_completed(');
	$end = strpos($module, "\n}\n", $start);
	pg_assert_true(($start !== false) && ($end !== false), 'completion found');
	pg_assert_false(strpos(substr($module, $start, $end - $start), '$_COOKIE') !== false, 'completion reads no cookie');
}

// A second completion: nothing is queued twice.
function test_ad_conversions_second_completion()
{
	$settings = array('meta_capi' => ad_conversions_test_settings(array('without_person' => array('purchase'))));

	pg_assert_same(array(), pg_ad_conversion_completion_plan(array('meta_capi' => array('status' => '1', 'person' => '1')), $settings), 'already due');
	pg_assert_same(array(), pg_ad_conversion_completion_plan(array('meta_capi' => array('status' => '2', 'person' => '0')), $settings), 'already sent');

	$sql = pg_ad_conversion_insert_sql();
	pg_assert_contains('INSERT IGNORE', $sql, 'the unique key holds a second row back');

	// The call sits after pg_order_complete()'s lock: only the request that
	// completed the order reaches it.
	$completion = ad_conversions_test_source('includes/fn/order_completion.php');
	$lock = strpos($completion, 'if (mysqli_affected_rows(db::$con) < 1) {');
	$call = strpos($completion, 'pg_ad_conversion_order_completed(');
	pg_assert_true(($lock !== false) && ($call !== false) && ($call > $lock), 'called after the lock');
}

// The purchase event: amounts turned into decimals only here, the e-mail and
// phone hashed, the ids only with the person.
function test_ad_conversions_purchase_event()
{
	$detail = pg_ad_conversion_purchase_detail(12345, 'TRY', 'R-77', 1700000000, array(
		array('product_name' => 'SKU-1', 'quantity' => '2', 'price' => '5000'),
		array('product_name' => 'SKU-2', 'quantity' => '1', 'price' => '2345'),
	));

	pg_assert_same(12345, $detail['value'], 'minor units kept');
	pg_assert_same(array(array('id' => 'SKU-1', 'quantity' => 2, 'price' => 5000), array('id' => 'SKU-2', 'quantity' => 1, 'price' => 2345)), $detail['contents'], 'contents');

	$row = array('event' => 'purchase', 'event_id' => 'R-77', 'person' => '1', 'ids' => json_encode(array('fbp' => 'fb.1', 'ip' => '203.0.113.5')), 'detail' => json_encode($detail));
	$order = array('billing_email_address' => ' Ali@Example.COM ', 'billing_phone_number' => '+90 (532) 111 22 33');
	$event = pg_ad_conversion_event($row, $order);

	pg_assert_same('purchase', $event['event'], 'name');
	pg_assert_same('R-77', $event['event_id'], 'event id');
	pg_assert_same('123.45', $event['value'], 'decimal in the body');
	pg_assert_same('50.00', $event['contents'][0]['price'], 'line price decimal');
	pg_assert_same(hash('sha256', 'ali@example.com'), $event['user']['email_sha256'], 'e-mail normalised and hashed');
	pg_assert_same(hash('sha256', '905321112233'), $event['user']['phone_sha256'], 'phone digits hashed');
	pg_assert_same('fb.1', $event['user']['fbp'], 'ids with the person');
	pg_assert_false(strpos(json_encode($event), 'example.com') !== false, 'no plain e-mail');

	$row['person'] = '0';
	pg_assert_same(array(), pg_ad_conversion_event($row, $order)['user'], 'without the person nothing about them');

	pg_assert_same('R-77', pg_ad_conversion_purchase_event_id('R-77'), 'the order reference');
	pg_assert_same(64, strlen(pg_ad_conversion_purchase_event_id(str_repeat('a', 80))), 'cut to the column');
	pg_assert_same('-0.05', pg_ad_conversion_decimal(-5), 'negative');
	pg_assert_same('', pg_ad_conversion_hash_email('not an address'), 'no hash of a non-address');
	pg_assert_same('', pg_ad_conversion_hash_phone('12'), 'no hash of too short a phone');
	pg_assert_same(hash('sha256', '905321112233'), pg_ad_conversion_hash_phone('00905321112233'), 'leading 00 dropped');
}

// What a send's outcome writes to the row.
function test_ad_conversions_after_attempt()
{
	$row = array('attempts' => '0');

	$done = pg_ad_conversion_after_attempt($row, array('ok' => true, 'status' => 200, 'error' => ''), 1000);
	pg_assert_same(2, $done['status'], 'sent');
	pg_assert_same(null, $done['ids'], 'ids dropped once sent');
	pg_assert_same(1000, $done['sent_at'], 'sent time');

	$retry = pg_ad_conversion_after_attempt($row, array('ok' => false, 'status' => 503, 'error' => 'busy'), 1000);
	pg_assert_same(1, $retry['status'], 'tried again');
	pg_assert_same(1060, $retry['next_attempt_at'], 'after the backoff');
	pg_assert_false(array_key_exists('ids', $retry), 'ids kept for the retry');

	$refused = pg_ad_conversion_after_attempt($row, array('ok' => false, 'status' => 400, 'error' => str_repeat('x', 600)), 1000);
	pg_assert_same(3, $refused['status'], 'a refused body is not tried again');
	pg_assert_same(null, $refused['ids'], 'ids dropped');
	pg_assert_same(500, strlen($refused['last_error']), 'error cut to the column');

	$last = pg_ad_conversion_after_attempt(array('attempts' => '5'), array('ok' => false, 'status' => 0, 'error' => 'timeout'), 1000);
	pg_assert_same(3, $last['status'], 'given up after the last attempt');
}

// The sweep: thirty days, whatever the status.
function test_ad_conversions_sweep()
{
	$sql = pg_ad_conversion_sweep_sql(100 * 86400, 500);
	pg_assert_contains('DELETE FROM ad_conversion_events', $sql, 'deletes');
	pg_assert_contains('created_at < ' . (70 * 86400), $sql, 'thirty days back');
	pg_assert_false(strpos($sql, 'status') !== false, 'whatever the status');
	pg_assert_contains('LIMIT 500', $sql, 'in batches');
}

// The places outside the module.
function test_ad_conversions_wiring()
{
	pg_assert_contains("/includes/fn/ad_conversions.php'", ad_conversions_test_source('functions.php'), 'loaded with the modules');
	pg_assert_contains('pg_ad_conversion_payment_step(', ad_conversions_test_source('submit_order.php'), 'the payment step');
	pg_assert_contains('pg_ad_conversion_dispatch(', ad_conversions_test_source('job.php'), 'the general job');
	pg_assert_contains('pg_ad_conversion_consent_changed(', ad_conversions_test_source('consent_answer.php'), 'consent taken back');

	$migration = ad_conversions_test_source('includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_ad_conversion_events();', $migration, 'the step is called');
	pg_assert_contains("install_create_table('ad_conversion_events'", $migration, 'created with the helper');
	pg_assert_contains('UNIQUE KEY uniq_service_event (service, event_id)', $migration, 'the unique key');
	pg_assert_contains("'ad_conversion_events',", ad_conversions_test_source('install/index.php'), 'in get_tables()');

	$tables = pg_privacy_erase_tables();
	pg_assert_same(array('order_id' => 'order_ids'), $tables['ad_conversion_events']['find'], 'erased by the person\'s orders');
	pg_assert_same('delete', $tables['ad_conversion_events']['action'], 'deleted');
	pg_assert_true(isset(pg_privacy_export_sections()['ad_conversions']), 'exported');
}
