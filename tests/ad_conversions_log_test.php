<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/ad_conversions_log.php and GET
 * /reports/ad-conversions: a row as the log shows it (its state and tone,
 * the reason only for a refusal or a retry, never the ids), the daily counts
 * and the share sent with the person, the day a service refused everything,
 * the report's answer against its declared type, and the places outside the
 * module that reach it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/ad_conversions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ad_conversions_log.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/response.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/reports.php');

function ad_conversions_log_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Each status has its words and the tone of the panel's status colours.
function test_ad_conversions_log_states()
{
	pg_assert_same('gray', pg_ad_conversion_log_state(0, 0)['tone'], 'waiting for the order is neutral');
	pg_assert_same('due', pg_ad_conversion_log_state(1, 0)['state'], 'due, not tried yet');
	pg_assert_same('retrying', pg_ad_conversion_log_state(1, 2)['state'], 'due after a failed try');
	pg_assert_same('gray', pg_ad_conversion_log_state(1, 2)['tone'], 'a retry waits on nobody');
	pg_assert_same('teal', pg_ad_conversion_log_state(2, 1)['tone'], 'sent is teal');
	pg_assert_same('rose', pg_ad_conversion_log_state(3, 1)['tone'], 'refused is rose');
}

// A row as the log shows it: the order from the detail, the reason only for
// a refusal or a retry, and nothing of the ids.
function test_ad_conversions_log_item()
{
	$row = array(
		'id' => 9, 'event' => 'purchase', 'order_id' => 41, 'person' => 1, 'status' => 3, 'attempts' => 1,
		'last_status_code' => 400, 'last_error' => 'Invalid parameter (code 100)', 'created_at' => 1763600000, 'sent_at' => 0,
		'detail' => json_encode(array('value' => 100, 'order_number' => '1042')),
		'ids' => json_encode(array('fbp' => 'fb.1.1.2', 'ip' => '203.0.113.9')),
	);

	$item = pg_ad_conversion_log_item($row);

	pg_assert_same('1042', $item['order_number'], 'the order number from the detail');
	pg_assert_same(true, $item['person'], 'with the person');
	pg_assert_same('Invalid parameter (code 100)', $item['reason'], 'a refusal keeps its reason');
	pg_assert_same(false, strpos(json_encode($item), '203.0.113.9'), 'no address');
	pg_assert_same(false, strpos(json_encode($item), 'fb.1.1.2'), 'no browser id');

	$sent = pg_ad_conversion_log_item(array('status' => 2, 'attempts' => 1, 'last_error' => 'old', 'created_at' => 100, 'sent_at' => 160, 'event' => 'refund'));
	pg_assert_same('', $sent['reason'], 'a sent row tells no reason');
	pg_assert_same(160, $sent['at'], 'a sent row is dated when it was sent');
	pg_assert_same('', $sent['order_number'], 'no detail, no order number');

	$retry = pg_ad_conversion_log_item(array('status' => 1, 'attempts' => 2, 'last_error' => 'Timed out', 'created_at' => 100));
	pg_assert_same('Timed out', $retry['reason'], 'a retry tells why');

	$bare = pg_ad_conversion_log_item(array('status' => 3, 'attempts' => 1, 'last_status_code' => 500, 'last_error' => '', 'created_at' => 100));
	pg_assert_same('HTTP 500', $bare['reason'], 'a refusal without words names its status');
}

// Every day of the window is present, newest first; rows outside it are left
// out; the share is of the sent events.
function test_ad_conversions_log_daily_counts()
{
	$groups = array(
		array('day' => 20000, 'status' => 2, 'person' => 1, 'n' => 3),
		array('day' => 20000, 'status' => 2, 'person' => 0, 'n' => 1),
		array('day' => 20000, 'status' => 3, 'person' => 1, 'n' => 2),
		array('day' => 19999, 'status' => 3, 'person' => 0, 'n' => 4),
		array('day' => 19990, 'status' => 2, 'person' => 1, 'n' => 7),
	);

	$counts = pg_ad_conversion_daily_counts($groups, 20000, 3);

	pg_assert_same(3, count($counts), 'three days');
	pg_assert_same(gmdate('Y-m-d', 20000 * 86400), $counts[0]['day'], 'newest first');
	pg_assert_same(4, $counts[0]['sent'], 'sent');
	pg_assert_same(2, $counts[0]['failed'], 'failed');
	pg_assert_same(75, $counts[0]['share'], 'three of four sent with the person');
	pg_assert_same(null, $counts[1]['share'], 'nothing sent, no share');
	pg_assert_same(0, $counts[2]['sent'], 'an empty day is there too');

	$total = pg_ad_conversion_counts_total($counts);
	pg_assert_same(4, $total['sent'], 'the day outside the window is left out');
	pg_assert_same(6, $total['failed'], 'failed added up');
	pg_assert_same(75, $total['share'], 'the window\'s share');

	pg_assert_same(false, pg_ad_conversion_refused_all($counts[0]), 'some sent');
	pg_assert_same(true, pg_ad_conversion_refused_all($counts[1]), 'all refused');
	pg_assert_same(false, pg_ad_conversion_refused_all($counts[2]), 'nothing tried is no refusal');
}

// A day number is the site's day.
function test_ad_conversions_log_day()
{
	pg_assert_same(0, pg_ad_conversion_log_day(3600, 0), 'UTC');
	pg_assert_same(1, pg_ad_conversion_log_day(86400 - 3600, 3 * 3600), 'ahead of UTC, the next day');
	pg_assert_same(-1, pg_ad_conversion_log_day(3600, -2 * 3600), 'behind UTC, the day before');
}

// The report's answer has the declared keys, and no personal data.
function test_ad_conversions_log_report_present()
{
	$counts = pg_ad_conversion_daily_counts(array(array('day' => 20000, 'status' => 2, 'person' => 0, 'n' => 2)), 20000, 2);
	$answer = api_ad_conversions_present(array('services' => array(array(
		'service' => 'meta_capi', 'name' => 'Meta Conversions API', 'connected' => true, 'events' => array('purchase'), 'waiting' => 3,
		'totals' => pg_ad_conversion_counts_total($counts), 'days' => $counts,
		'errors' => array(array('at' => 1763600000, 'event' => 'purchase', 'status_code' => 0, 'error' => 'No consent')),
	))));

	$schema = api_ad_conversions_report_schema();
	$service = $answer['services'][0];

	pg_assert_same(array_keys($schema['services'][0]), array_keys($service), 'a service answers the declared keys');
	pg_assert_same(array_keys($schema['services'][0]['days'][0]), array_keys($service['days'][0]), 'a day answers the declared keys');
	pg_assert_same(array_keys($schema['services'][0]['errors'][0]), array_keys($service['errors'][0]), 'an error answers the declared keys');
	pg_assert_same(0, $service['totals']['share'], 'sent without the person');
	pg_assert_same(null, $service['days'][1]['share'], 'an empty day has no share');
	pg_assert_same(null, $service['errors'][0]['status_code'], 'a refusal made without a call has no status');
	pg_assert_same('2025-11-20T00:53:20Z', $service['errors'][0]['at'], 'times as the API writes them');
}

// The places outside the module: the alert code and its source, the page's
// card, the route and its type, the loader.
function test_ad_conversions_log_wiring()
{
	$alerts = ad_conversions_log_test_source('includes/fn/ops_alerts.php');
	pg_assert_true(strpos($alerts, "'ad_events_refused' => array(") !== false, 'the alert code is in the catalogue');
	pg_assert_true(strpos($alerts, "'ad_conversions' => 'pg_ad_conversion_check_refused'") !== false, 'the check is a source');

	pg_assert_true(strpos(ad_conversions_log_test_source('connections.php'), 'pg_ad_conversion_log_card($page_id)') !== false, 'each service\'s page shows its events');
	pg_assert_true(strpos(ad_conversions_log_test_source('functions.php'), "/includes/fn/ad_conversions_log.php'") !== false, 'the module is loaded');

	$schema = ad_conversions_log_test_source('includes/api/schema.php');
	pg_assert_true(strpos($schema, "'path'    => '/reports/ad-conversions'") !== false, 'the route');
	pg_assert_true(strpos(ad_conversions_log_test_source('includes/api/openapi.php'), "'AdConversionsReport' => 'api_ad_conversions_report_schema'") !== false, 'its type');

	pg_assert_true(strpos(ad_conversions_log_test_source('includes/fn/ad_conversions_log.php'), 'ids') !== false, 'the module names the ids only to say they are not read');
	pg_assert_same(0, preg_match('/SELECT[^;]*\bids\b[^;]*FROM ad_conversion_events/', ad_conversions_log_test_source('includes/fn/ad_conversions_log.php')), 'no query reads the ids');
}
