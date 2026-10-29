<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for panel dates that follow the site's date order and
 * 12/24-hour clock: Application Access, Connected applications,
 * Connections, the chat window (server labels and chat_backend.src.js)
 * and the barcode inventory scan logs. These screens printed fixed
 * date('d.m.Y') patterns or hand-built JavaScript times; a unit test
 * cannot draw the screens, so these pin the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_dates_site_order_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_panel_dates_site_order_no_fixed_day_month_dates()
{
	foreach (array('api_settings.php', 'connected_apps.php', 'connections.php', 'chat.php') as $file) {
		$source = panel_dates_site_order_source($file);
		pg_assert_same(0, preg_match_all("/date\\('d\\.m\\.Y/", $source), $file . ' prints a fixed d.m.Y date');
	}
}

function test_panel_dates_site_order_screens_use_get_absolute_time()
{
	pg_assert_same(3, substr_count(panel_dates_site_order_source('api_settings.php'), "get_absolute_time(array('timestamp' => (int)"));
	pg_assert_contains("get_absolute_time(array('timestamp' => (int)\$connection['created_timestamp'], 'type' => 'date'))", panel_dates_site_order_source('connected_apps.php'));
	pg_assert_contains("get_absolute_time(array('timestamp' => (int)\$account['created_timestamp'], 'type' => 'date'))", panel_dates_site_order_source('connections.php'));

	$chat = panel_dates_site_order_source('chat.php');
	pg_assert_contains("get_absolute_time(array('timestamp' => \$timestamp, 'type' => 'date'))", $chat);
	pg_assert_contains("get_absolute_time(array('timestamp' => \$timestamp))", $chat);
}

function test_panel_dates_site_order_chat_window_reads_site_clock()
{
	$chat = panel_dates_site_order_source('chat.php');
	pg_assert_contains("'clock' => array(", $chat);
	pg_assert_contains("DATE_FORMAT == 'month_day'", $chat);
	pg_assert_contains("TIME_FORMAT == 'twelve_hours'", $chat);

	foreach (array('chat_backend.src.js', 'chat_backend.min.js') as $file) {
		$source = panel_dates_site_order_source('assets/js/' . $file);
		pg_assert_contains('month_day', $source, $file);
		pg_assert_contains('twelve_hours', $source, $file);
	}

	$source = panel_dates_site_order_source('assets/js/chat_backend.src.js');
	pg_assert_false(strpos($source, "return pad(date.getDate()) + '.' + pad(date.getMonth() + 1)") !== false, 'formatTime builds a fixed day.month date');
}

function test_panel_dates_site_order_barcode_logs_use_server_time()
{
	foreach (array('barcode_decrease_inventory.php', 'barcode_increase_inventory.php') as $file) {
		$source = panel_dates_site_order_source($file);
		pg_assert_false(strpos($source, 'currentdate.getHours()') !== false, $file . ' builds the scan time by hand');
		pg_assert_contains("'scanned_at' => get_absolute_time(array('timestamp' => time()))", $source, $file);
		pg_assert_contains('response.data.scanned_at', $source, $file);
	}
}
