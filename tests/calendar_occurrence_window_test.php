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
