<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_calendar_occurrence_window() in includes/fn/calendar.php: the
 * start and end of one occurrence of a recurring event, which the event
 * location availability check compares against a new booking.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_calendar_occurrence_window_keeps_a_same_day_event()
{
	pg_assert_same(
		array(strtotime('2026-11-09 10:00:00'), strtotime('2026-11-09 12:00:00')),
		pg_calendar_occurrence_window('2026-11-09', '2026-11-02 10:00:00', '2026-11-02 12:00:00')
	);
}

function test_calendar_occurrence_window_ends_an_overnight_event_the_next_day()
{
	// 22:00 to 02:00: the end is on the day after the occurrence.
	pg_assert_same(
		array(strtotime('2026-11-09 22:00:00'), strtotime('2026-11-10 02:00:00')),
		pg_calendar_occurrence_window('2026-11-09', '2026-11-02 22:00:00', '2026-11-03 02:00:00')
	);
}

function test_calendar_occurrence_window_keeps_a_multi_day_length()
{
	pg_assert_same(
		array(strtotime('2026-12-07 09:00:00'), strtotime('2026-12-09 17:00:00')),
		pg_calendar_occurrence_window('2026-12-07', '2026-11-02 09:00:00', '2026-11-04 17:00:00')
	);
}

// Runs $check with the default time zone set to $zone, then restores it.
function _calendar_occurrence_window_test_in_zone($zone, $check)
{
	$before = date_default_timezone_get();
	date_default_timezone_set($zone);

	try {
		$check();
	} finally {
		date_default_timezone_set($before);
	}
}

function test_calendar_occurrence_window_all_day_keeps_its_last_day_across_a_clock_change()
{
	// Berlin moves to summer time on 29 March 2026. A two-day all-day event
	// stored on 21-22 March ended at 00:59 on 30 March in its 28 March week.
	_calendar_occurrence_window_test_in_zone('Europe/Berlin', function () {
		pg_assert_same(
			array(strtotime('2026-03-28 00:00:00'), strtotime('2026-03-29 23:59:00')),
			pg_calendar_occurrence_window('2026-03-28', '2026-03-21 00:00:00', '2026-03-22 23:59:00')
		);
	});
}

function test_calendar_occurrence_window_overnight_event_stored_across_a_clock_change()
{
	// Stored 28-29 March 22:00 to 06:00 (seven hours that night); a week
	// later it still ends at 06:00, not 05:00.
	_calendar_occurrence_window_test_in_zone('Europe/Berlin', function () {
		pg_assert_same(
			array(strtotime('2026-04-04 22:00:00'), strtotime('2026-04-05 06:00:00')),
			pg_calendar_occurrence_window('2026-04-04', '2026-03-28 22:00:00', '2026-03-29 06:00:00')
		);
	});
}
