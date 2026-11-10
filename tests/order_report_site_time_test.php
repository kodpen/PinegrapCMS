<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the Order Report reads its days in the site's own time across
 * daylight saving time: the Date filter and the year, month and day groups
 * go through pg_site_time_sql() over the window the report's orders come
 * from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_order_report_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order_report.php');
}

// The Date filter operand and the three day groupings use the helper.
function test_order_report_reads_days_in_site_time()
{
	$source = pg_test_order_report_source();

	pg_assert_contains("case 'order_date': \$operand_1 = pg_site_time_sql('orders.order_date', '%Y-%m-%d', \$site_time_from, \$site_time_to);", $source, 'Date filter');
	pg_assert_contains('function get_summarize_by_column($summarize_by, $from = null, $to = null)', $source, 'same signature as the visitor report');
	pg_assert_contains("case 'year': return pg_site_time_sql('orders.order_date', '%Y', \$from, \$to);", $source, 'year grouping');
	pg_assert_contains("case 'month': return pg_site_time_sql('orders.order_date', '%m', \$from, \$to);", $source, 'month grouping');
	pg_assert_contains("case 'day': return pg_site_time_sql('orders.order_date', '%d', \$from, \$to);", $source, 'day grouping');
	pg_assert_same(3, substr_count($source, ", \$site_time_from, \$site_time_to) . ','"), 'three groupings get the window');
	pg_assert_same(0, preg_match("/FROM_UNIXTIME\\(orders\\.order_date, '/", str_replace("\\'", "'", $source)), 'no plain day reading');
}

// The window is the date changer's range, or with a Date filter (which
// replaces that range) every order from the oldest one through now.
function test_order_report_site_time_window()
{
	$source = pg_test_order_report_source();
	$start = strpos($source, '$site_time_from = $start_timestamp;');
	$loop = strpos($source, "case 'order_date': \$operand_1 = ");

	pg_assert_true($start !== false, 'the date changer range is the window');
	pg_assert_true(($loop !== false) && ($start < $loop), 'the window is set before the filter loop');

	$block = substr($source, $start, $loop - $start);

	pg_assert_contains('$site_time_to = $stop_timestamp;', $block, 'window end');
	pg_assert_contains('db_value("SELECT MIN(order_date) FROM orders WHERE order_date > 0")', $block, 'Date filter window start');
	pg_assert_contains('$site_time_to = time();', $block, 'Date filter window end');
	pg_assert_same(
		"if (\$order_date_filter_exists == false) {\n        ",
		substr($source, $start - 50, 50),
		'chosen by the Date filter'
	);
}
