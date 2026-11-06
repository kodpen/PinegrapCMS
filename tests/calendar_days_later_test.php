<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_calendar_days_later() (includes/fn/core.php): a timestamp
 * moved by whole calendar days keeps the local date right across a
 * daylight saving change, where adding 86400 seconds lands an hour off and
 * on the wrong day near midnight.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $check with the default time zone set to $zone, then restores it.
function _calendar_days_later_test_in_zone($zone, $check)
{
	$before = date_default_timezone_get();
	date_default_timezone_set($zone);

	try {
		$check();
	} finally {
		date_default_timezone_set($before);
	}
}

function test_calendar_days_later_yesterday_after_spring_change()
{
	_calendar_days_later_test_in_zone('Europe/Berlin', function () {
		// 00:30 on the day after the clock went forward (2026-03-29).
		$now = mktime(0, 30, 0, 3, 30, 2026);

		pg_assert_same('2026-03-28', date('Y-m-d', $now - 86400), 'the old arithmetic lands on the day before yesterday');
		pg_assert_same('2026-03-29', date('Y-m-d', pg_calendar_days_later(-1, $now)), 'yesterday is the calendar day before');
		pg_assert_same('00:30:00', date('H:i:s', pg_calendar_days_later(-1, $now)), 'the local time of day is kept');
	});
}

function test_calendar_days_later_forward_across_changes()
{
	_calendar_days_later_test_in_zone('Europe/Berlin', function () {
		// 23:30 the evening before the spring change.
		$spring = mktime(23, 30, 0, 3, 28, 2026);
		pg_assert_same('2026-03-29', date('Y-m-d', pg_calendar_days_later(1, $spring)), 'one day after the spring evening');
		pg_assert_same('2026-04-27', date('Y-m-d', pg_calendar_days_later(30, $spring)), 'thirty days, month boundary included');

		// 00:30 on the autumn change day (2026-10-25): that day lasts 25 hours.
		$autumn = mktime(0, 30, 0, 10, 25, 2026);
		pg_assert_same('2026-10-26', date('Y-m-d', pg_calendar_days_later(1, $autumn)), 'one day after the autumn change night');
		pg_assert_same('2027-10-25', date('Y-m-d', pg_calendar_days_later(365, $autumn)), 'a year of days');
	});
}

function test_calendar_days_later_plain_days()
{
	_calendar_days_later_test_in_zone('UTC', function () {
		$now = mktime(12, 0, 0, 12, 31, 2026);

		pg_assert_same($now, pg_calendar_days_later(0, $now), 'zero days is the same moment');
		pg_assert_same('2027-01-01', date('Y-m-d', pg_calendar_days_later(1, $now)), 'over the year end');
		pg_assert_same('2026-12-30', date('Y-m-d', pg_calendar_days_later('-1', $now)), 'a numeric string counts as days');
	});
}
