<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's visitor reports in
 * includes/api/resources/visitors.php: the window a report covers, and the
 * paid / organic split.
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
