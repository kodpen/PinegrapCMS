<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /reports/sales-watch (includes/api/resources/reports.php):
 * a channel's norm for the next 12 hours and its last 12 hours' orders, a
 * channel with too little history, a marketplace channel read up to its last
 * sync, the alerts of the last 7 days and the products the watch closed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/reports.php');

// The presenter's helpers live in includes/api/response.php, which loads only
// behind the API's own entry. Stand-ins of the same shape.
if (!function_exists('api_money')) {
	function api_money($minor_units)
	{
		return (int) $minor_units;
	}
}

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		$unix = (int) $unix;

		return ($unix <= 0) ? null : gmdate('Y-m-d\TH:i:s\Z', $unix);
	}
}

// Hourly counts: $per_hour orders in every hour of the 8 weeks and a day
// before $cut (an hour index).
function api_sales_watch_test_counts($cut, $per_hour)
{
	$counts = array();

	for ($h = $cut - (8 * 168) - 26; $h < $cut; $h++) {
		$counts[$h] = $per_hour;
	}

	return $counts;
}

function test_api_sales_watch_channel_gives_the_next_and_last_twelve_hours()
{
	$now = 1700000000 + 1800;
	$cut = intdiv($now, 3600);

	$out = api_sales_watch_channel_present('site', array('label' => 'Site', 'end' => $now), api_sales_watch_test_counts($cut, 2), $cut, array($now - 60, $now - 3600, $now - (13 * 3600)), 0, $now);

	pg_assert_same('site', $out['key']);
	pg_assert_same('Site', $out['label']);
	pg_assert_same(true, $out['learnt'], 'enough orders to learn from');
	pg_assert_same(2, $out['last_12_hours_orders'], 'the order 13 hours back is outside');
	pg_assert_same(24.0, $out['last_12_hours_expected']);
	pg_assert_same(24.0, $out['next_12_hours_expected']);
	pg_assert_same(12, count($out['next_hours']));
	pg_assert_same(gmdate('Y-m-d\TH:i:s\Z', $cut * 3600), $out['next_hours'][0]['from'], 'from the hour under way');
	pg_assert_same(2.0, $out['next_hours'][0]['expected']);
	pg_assert_same(null, $out['paused_until']);
}

function test_api_sales_watch_channel_with_little_history_has_no_norm()
{
	$now = 1700000000;
	$cut = intdiv($now, 3600);

	$out = api_sales_watch_channel_present('site', array('label' => 'Site', 'end' => $now), array($cut - 5 => 3), $cut, array($now - 60), 0, $now);

	pg_assert_same(false, $out['learnt']);
	pg_assert_same(1, $out['last_12_hours_orders'], 'orders are still counted');
	pg_assert_same(null, $out['last_12_hours_expected']);
	pg_assert_same(null, $out['next_12_hours_expected']);
	pg_assert_same(array(), $out['next_hours']);
}

function test_api_sales_watch_marketplace_channel_is_read_up_to_its_last_sync()
{
	$now = 1700000000;
	$end = $now - 7200;
	$cut = intdiv($now, 3600);

	// One order after the last sync cannot be there yet; one just before it
	// and one 13 hours before it.
	$out = api_sales_watch_channel_present('mp_4', array('label' => 'Trendyol', 'end' => $end), api_sales_watch_test_counts($cut, 1), $cut, array($end - 60, $end - (13 * 3600)), $now + 86400, $now);

	pg_assert_same('mp_4', $out['key']);
	pg_assert_same(gmdate('Y-m-d\TH:i:s\Z', $end), $out['data_until']);
	pg_assert_same(1, $out['last_12_hours_orders']);
	pg_assert_same(gmdate('Y-m-d\TH:i:s\Z', $now + 86400), $out['paused_until']);
}

function test_api_sales_watch_alert_row_names_its_code_and_product()
{
	$codes = array('price_error' => array('label' => 'A product sells at a wrong price', 'group' => 'sales'));

	$out = api_sales_watch_alert_present(array('code' => 'price_error', 'title' => 'Mug: 1.00 instead of 10.00', 'timestamp' => '1700000000', 'product_id' => '12'), $codes);

	pg_assert_same('price_error', $out['code']);
	pg_assert_same('A product sells at a wrong price', $out['label']);
	pg_assert_same('Mug: 1.00 instead of 10.00', $out['detail']);
	pg_assert_same('2023-11-14T22:13:20Z', $out['at']);
	pg_assert_same(12, $out['product_id']);

	// A row with nothing to add carries its own code as its text.
	$bare = api_sales_watch_alert_present(array('code' => 'price_error', 'title' => 'price_error', 'timestamp' => '1700000000', 'product_id' => '0'), $codes);

	pg_assert_same('', $bare['detail']);
	pg_assert_same(null, $bare['product_id']);
}

function test_api_sales_watch_closed_lists_only_products_still_off_sale()
{
	$closed = array(
		7 => array('at' => 1700000000, 'price' => 100, 'reason' => 'half'),
		8 => array('at' => 1700000100, 'price' => 250, 'reason' => 'cost'),
		9 => array('at' => 1700000200, 'price' => 300, 'reason' => 'half'),
	);

	// 8 was put back on sale; 9 is gone.
	$products = array(
		7 => array('name' => 'Mug', 'enabled' => false),
		8 => array('name' => 'Plate', 'enabled' => true),
	);

	$out = api_sales_watch_closed_present($closed, $products);

	pg_assert_same(1, count($out));
	pg_assert_same(7, $out[0]['product_id']);
	pg_assert_same('Mug', $out[0]['name']);
	pg_assert_same('2023-11-14T22:13:20Z', $out[0]['closed_at']);
	pg_assert_same(100, $out[0]['price']);
	pg_assert_same('half', $out[0]['reason']);
}

function test_api_sales_watch_closed_newest_first()
{
	$closed = array(
		7 => array('at' => 1700000000, 'price' => 100, 'reason' => 'half'),
		8 => array('at' => 1700000100, 'price' => 250, 'reason' => 'cost'),
	);

	$out = api_sales_watch_closed_present($closed, array(7 => array('name' => 'Mug', 'enabled' => false), 8 => array('name' => 'Plate', 'enabled' => false)));

	pg_assert_same(array(8, 7), array_column($out, 'product_id'));
}
