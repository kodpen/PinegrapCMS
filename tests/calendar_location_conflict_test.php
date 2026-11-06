<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_calendar_single_event_conflicts() in includes/fn/calendar.php:
 * whether a one-off event already in a location overlaps a new booking. The
 * stored start and end are read as site time in PHP, so an event on the
 * other side of a clock change keeps its own wall-clock hours.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $fn with PHP in a daylight saving zone and puts the zone back.
function calendar_location_conflict_test_in_berlin($fn)
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');
	try {
		$fn();
	} finally {
		date_default_timezone_set($zone);
	}
}

function test_calendar_single_event_conflicts_with_an_overlapping_summer_booking()
{
	calendar_location_conflict_test_in_berlin(function () {
		// Booked in winter for a summer day: 10:00-11:00 is taken at 10:30.
		pg_assert_true(pg_calendar_single_event_conflicts(
			'2027-07-10 10:00:00', '2027-07-10 11:00:00',
			strtotime('2027-07-10 10:30:00'), strtotime('2027-07-10 11:30:00')
		));
	});
}

function test_calendar_single_event_leaves_the_next_summer_hour_free()
{
	calendar_location_conflict_test_in_berlin(function () {
		pg_assert_false(pg_calendar_single_event_conflicts(
			'2027-07-10 10:00:00', '2027-07-10 11:00:00',
			strtotime('2027-07-10 11:00:00'), strtotime('2027-07-10 12:00:00')
		));
	});
}

function test_calendar_single_event_conflicts_keeps_the_three_overlap_cases()
{
	$s = '2026-11-09 10:00:00';
	$e = '2026-11-09 12:00:00';
	// The new booking starts inside, ends inside, or covers the event.
	pg_assert_true(pg_calendar_single_event_conflicts($s, $e, strtotime('2026-11-09 11:00:00'), strtotime('2026-11-09 13:00:00')));
	pg_assert_true(pg_calendar_single_event_conflicts($s, $e, strtotime('2026-11-09 09:00:00'), strtotime('2026-11-09 11:00:00')));
	pg_assert_true(pg_calendar_single_event_conflicts($s, $e, strtotime('2026-11-09 09:00:00'), strtotime('2026-11-09 13:00:00')));
	// Back to back on either side is free.
	pg_assert_false(pg_calendar_single_event_conflicts($s, $e, strtotime('2026-11-09 08:00:00'), strtotime('2026-11-09 10:00:00')));
	pg_assert_false(pg_calendar_single_event_conflicts($s, $e, strtotime('2026-11-09 12:00:00'), strtotime('2026-11-09 13:00:00')));
}

function test_calendar_location_check_reads_stored_times_in_php()
{
	// MySQL's UNIX_TIMESTAMP() reads the column at the session's fixed
	// offset of the request day, an hour off for the other season.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/calendar.php');
	$start = strpos($source, 'function check_calendar_event_location_availability(');
	$body = substr($source, $start, strpos($source, 'function get_calendar_event(') - $start);
	pg_assert_false(strpos($body, 'UNIX_TIMESTAMP') !== false);
	pg_assert_contains('pg_calendar_single_event_conflicts(', $body);
}
