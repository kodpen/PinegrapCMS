<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the renewal series of store-run subscriptions across a clock
 * change (pg_subscription_renewal_next_at(), pg_subscription_next_charge_at()):
 * a series date that falls in the hour the clocks skip does not move the time
 * of every later charge.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $test in a zone with daylight saving time, then puts the zone back.
function subscription_series_dst_test_in_zone($zone, $test)
{
	$before = date_default_timezone_get();
	date_default_timezone_set($zone);

	try {
		$test();
	} finally {
		date_default_timezone_set($before);
	}
}

function test_subscription_series_keeps_the_first_charge_time_after_a_skipped_hour()
{
	subscription_series_dst_test_in_zone('America/New_York', function () {
		// Weekly from Sunday 2027-03-07 02:30; the clocks skip 02:00-03:00 on
		// 2027-03-14, so that one charge is at 03:30.
		$first = mktime(2, 30, 0, 3, 7, 2027);

		$next = pg_subscription_renewal_next_at($first, 'weekly', mktime(12, 0, 0, 3, 7, 2027));
		pg_assert_same('2027-03-14 03:30', date('Y-m-d H:i', $next), 'the skipped hour itself moves forward');

		$next = pg_subscription_renewal_next_at($first, 'weekly', mktime(12, 0, 0, 3, 14, 2027));
		pg_assert_same('2027-03-21 02:30', date('Y-m-d H:i', $next), 'the week after is at the first charge time again');

		$next = pg_subscription_renewal_next_at($first, 'weekly', mktime(12, 0, 0, 4, 1, 2027));
		pg_assert_same('2027-04-04 02:30', date('Y-m-d H:i', $next));
	});
}

function test_subscription_monthly_series_keeps_the_first_charge_time_after_a_skipped_hour()
{
	subscription_series_dst_test_in_zone('Europe/Berlin', function () {
		// Monthly from 2027-02-28 02:15, anchor 28; 2027-03-28 02:15 does not
		// exist in Berlin.
		$first = mktime(2, 15, 0, 2, 28, 2027);

		$next = pg_subscription_renewal_next_at($first, 'monthly', mktime(12, 0, 0, 3, 28, 2027));
		pg_assert_same('2027-04-28 02:15', date('Y-m-d H:i', $next));

		$next = pg_subscription_renewal_next_at($first, 'monthly', mktime(12, 0, 0, 1, 1, 2028));
		pg_assert_same('2028-01-28 02:15', date('Y-m-d H:i', $next));
	});
}

function test_subscription_next_charge_at_takes_several_periods_at_once()
{
	subscription_series_dst_test_in_zone('America/New_York', function () {
		$jan31 = mktime(9, 0, 0, 1, 31, 2027);

		pg_assert_same('2027-02-28 09:00', date('Y-m-d H:i', pg_subscription_next_charge_at($jan31, 'monthly', 31, 1)));
		pg_assert_same('2027-03-31 09:00', date('Y-m-d H:i', pg_subscription_next_charge_at($jan31, 'monthly', 31, 2)));
		pg_assert_same('2028-01-31 09:00', date('Y-m-d H:i', pg_subscription_next_charge_at($jan31, 'monthly', 31, 12)));
		pg_assert_same('2028-02-29 09:00', date('Y-m-d H:i', pg_subscription_next_charge_at($jan31, 'monthly', 31, 13)));
		pg_assert_same('2027-03-14 09:00', date('Y-m-d H:i', pg_subscription_next_charge_at(mktime(9, 0, 0, 2, 28, 2027), 'weekly', 0, 2)));
		pg_assert_same(0, pg_subscription_next_charge_at($jan31, 'monthly', 31, 0), 'no step, no date');
	});
}
