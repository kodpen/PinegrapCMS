<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's reports: the visitor reports in
 * includes/api/resources/visitors.php (the window a report covers, the paid /
 * organic split) and the sales report's periods and totals in
 * includes/api/resources/reports.php.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/visitors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/reports.php');

// The presenter's money helper lives in includes/api/response.php, which loads
// only behind the API's own entry. A stand-in of the same shape.
if (!function_exists('api_money')) {
	function api_money($minor_units)
	{
		return (int) $minor_units;
	}
}

// $count grouped rows as the sales query reads them, one order of 100 each.
function api_reports_test_sales_rows($count)
{
	$rows = array();

	for ($day = 0; $day < $count; $day++) {
		$rows[] = array(
			'period' => date('Y-m-d', strtotime('2015-01-01 12:00:00') + ($day * 86400)),
			'orders' => '1', 'subtotal' => '100', 'discount' => '0', 'tax' => '18',
			'shipping' => '0', 'surcharge' => '0', 'total' => '100',
		);
	}

	return $rows;
}

function test_api_reports_window_defaults_to_thirty_days_ending_today()
{
	$window = api_visitors_window(array(), 400);

	pg_assert_same(date('Y-m-d'), $window['last'], 'ends today');
	pg_assert_same(30, $window['days'], 'thirty days, today included');
}

function test_api_reports_window_counts_whole_days_across_a_clock_change()
{
	$zone = date_default_timezone_get();

	// Berlin moves its clocks on 2026-03-29: that day is 23 hours long, and a
	// count made by dividing seconds by 86400 would come out short.
	date_default_timezone_set('Europe/Berlin');

	$window = api_visitors_window(array(
		'from' => strtotime('2026-03-01 00:00:00'),
		'to'   => strtotime('2026-03-31 00:00:00'),
	), 400);

	date_default_timezone_set($zone);

	pg_assert_same('2026-03-01', $window['first'], 'first day');
	pg_assert_same('2026-03-31', $window['last'], 'last day');
	pg_assert_same(31, $window['days'], 'every day of March');
}

function test_api_reports_channel_needs_a_flag_to_call_a_visit_paid()
{
	$dimensions = api_visitors_dimensions();

	$channel = $dimensions['channel'];

	if (defined('PAY_PER_CLICK_FLAG') && trim((string)PAY_PER_CLICK_FLAG) !== '') {

		pg_assert_true(strpos($channel, "'pay_per_click'") !== false, 'a set flag makes paid visits');

	} else {

		pg_assert_true(strpos($channel, 'pay_per_click') === false, 'no flag, nothing is paid');

	}

	pg_assert_true(strpos($channel, "THEN 'organic'") !== false, 'a search engine referral is organic');
}

// Up to the limit every period comes back and the totals are theirs: the
// whole window.
function test_api_reports_sales_tally_adds_up_every_period()
{
	$max = api_reports_sales_max_periods();

	$tally = api_reports_sales_tally(api_reports_test_sales_rows($max), $max);

	pg_assert_same($max, count($tally['periods']), 'every period');
	pg_assert_same($max, $tally['totals']['orders'], 'every order counted');
	pg_assert_same($max * 100, $tally['totals']['total'], 'every order added up');
	pg_assert_same($max * 18, $tally['totals']['tax'], 'tax too');
	pg_assert_same(array('period' => '2015-01-01', 'orders' => 1, 'subtotal' => 100, 'discount' => 0,
		'tax' => 18, 'shipping' => 0, 'surcharge' => 0, 'total' => 100), $tally['periods'][0], 'one period');
}

// One period more than an answer carries is refused, not cut: cut at 400,
// 500 days of sales were reported as the first 400 and their totals.
function test_api_reports_sales_tally_refuses_a_window_past_the_limit()
{
	$max = api_reports_sales_max_periods();

	pg_assert_same(null, api_reports_sales_tally(api_reports_test_sales_rows($max + 1), $max));
}

// A window with no sales is an answer of zeros, not a refusal.
function test_api_reports_sales_tally_of_no_orders_is_zero()
{
	$tally = api_reports_sales_tally(array(), api_reports_sales_max_periods());

	pg_assert_same(array(), $tally['periods'], 'no periods');
	pg_assert_same(0, $tally['totals']['total'], 'nothing sold');
}

// The period the sales query gives an order: the same reading of the zone's
// offsets the SQL expression makes, the offset of the last stretch that began
// at or before the order, added to the order date.
function api_reports_test_sales_period($stamp, $stretches, $interval)
{
	$offset = $stretches[0][1];

	foreach ($stretches as $stretch) {
		if ($stretch[0] <= $stamp) {
			$offset = $stretch[1];
		}
	}

	return gmdate(($interval === 'month') ? 'Y-m' : 'Y-m-d', $stamp + $offset);
}

// Berlin moves its clocks forward on 2026-03-29 at 01:00 UTC. Asked from
// winter, an order placed at half past midnight on 31 March was put on the
// 30th by the winter offset.
function test_api_reports_sales_days_follow_a_spring_clock_change()
{
	$from = gmmktime(0, 0, 0, 3, 1, 2026);
	$to = gmmktime(0, 0, 0, 4, 30, 2026);

	$stretches = api_reports_sales_zone_offsets($from, $to, 'Europe/Berlin');

	pg_assert_same(array(array($from, 3600), array(gmmktime(1, 0, 0, 3, 29, 2026), 7200)), $stretches, 'winter, then summer');

	pg_assert_same('2026-03-29', api_reports_test_sales_period(gmmktime(23, 30, 0, 3, 28, 2026), $stretches, 'day'), 'the night before the change');
	pg_assert_same('2026-03-31', api_reports_test_sales_period(gmmktime(22, 30, 0, 3, 30, 2026), $stretches, 'day'), 'half past midnight in summer');
	pg_assert_same('2026-04', api_reports_test_sales_period(gmmktime(22, 30, 0, 3, 31, 2026), $stretches, 'month'), 'half past midnight on 1 April');
}

// Berlin moves its clocks back on 2026-10-25 at 01:00 UTC. Asked from winter,
// a summer order at half past midnight fell on the day and month before.
function test_api_reports_sales_days_follow_an_autumn_clock_change()
{
	$from = gmmktime(0, 0, 0, 9, 1, 2026);
	$to = gmmktime(0, 0, 0, 11, 30, 2026);

	$stretches = api_reports_sales_zone_offsets($from, $to, 'Europe/Berlin');

	pg_assert_same(array(array($from, 7200), array(gmmktime(1, 0, 0, 10, 25, 2026), 3600)), $stretches, 'summer, then winter');

	pg_assert_same('2026-10-25', api_reports_test_sales_period(gmmktime(22, 30, 0, 10, 24, 2026), $stretches, 'day'), 'summer, half past midnight');
	pg_assert_same('2026-10-26', api_reports_test_sales_period(gmmktime(23, 30, 0, 10, 25, 2026), $stretches, 'day'), 'winter, half past midnight');
	pg_assert_same('2026-10', api_reports_test_sales_period(gmmktime(22, 30, 0, 9, 30, 2026), $stretches, 'month'), 'half past midnight on 1 October');
	pg_assert_same('2026-11', api_reports_test_sales_period(gmmktime(23, 30, 0, 10, 31, 2026), $stretches, 'month'), 'half past midnight on 1 November');
}

// A zone without clock changes is one stretch, and an unknown name falls back
// to the zone PHP runs in rather than failing the report.
function test_api_reports_sales_zone_without_changes_is_one_stretch()
{
	$from = gmmktime(0, 0, 0, 1, 1, 2026);
	$to = gmmktime(0, 0, 0, 12, 31, 2026);

	pg_assert_same(array(array($from, 10800)), api_reports_sales_zone_offsets($from, $to, 'Europe/Istanbul'), 'Istanbul');
	pg_assert_same(array(array($from, 0)), api_reports_sales_zone_offsets($from, $to, 'UTC'), 'UTC');

	$fallback = api_reports_sales_zone_offsets($from, $to, 'Nowhere/Atlantis');

	pg_assert_same($from, $fallback[0][0], 'still answers');
}

// The grouping is worked out from the offsets in plain arithmetic on a fixed
// date: no CONVERT_TZ (the named zone tables may be missing) and nothing the
// session's time_zone could move.
function test_api_reports_sales_period_sql_reads_every_stretch()
{
	$spring = gmmktime(1, 0, 0, 3, 29, 2026);

	$sql = api_reports_sales_period_sql(array(array(gmmktime(0, 0, 0, 3, 1, 2026), 3600), array($spring, 7200)), 'day');

	pg_assert_same("DATE_FORMAT(DATE_ADD(CAST('1970-01-01 00:00:00' AS DATETIME), INTERVAL (order_date + CASE WHEN order_date >= "
		. $spring . " THEN 7200 ELSE 3600 END) SECOND), '%Y-%m-%d')", $sql, 'day');

	pg_assert_same("DATE_FORMAT(DATE_ADD(CAST('1970-01-01 00:00:00' AS DATETIME), INTERVAL (order_date + 0) SECOND), '%Y-%m')",
		api_reports_sales_period_sql(array(array(0, 0)), 'month'), 'month, one stretch');

	pg_assert_true(stripos($sql, 'CONVERT_TZ') === false && stripos($sql, 'FROM_UNIXTIME') === false, 'no zone tables, no session zone');
}
