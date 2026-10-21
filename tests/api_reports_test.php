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
