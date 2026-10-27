<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar event form shared by add_calendar_event.php and
 * edit_calendar_event.php (includes/forms/calendar_event_form.php): the start
 * date of every repeating instance that the location check walks through, and
 * that the screens check a submitted event through the one validator.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');

function calendar_event_form_test_days($chosen)
{
	$days = array();

	foreach (array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat') as $day) {
		$days[$day] = in_array($day, $chosen) ? '1' : '0';
	}

	return $days;
}

function test_calendar_event_form_no_repeat_is_the_event_alone()
{
	pg_assert_same(array(0 => '2026-10-27'), pg_calendar_event_recurrence_dates('2026-10-27', '0', 'day', calendar_event_form_test_days(array()), ''));
}

function test_calendar_event_form_daily_repeat_runs_on_the_chosen_weekdays_only()
{
	// 2026-10-26 is a Monday.
	pg_assert_same(
		array(0 => '2026-10-26', 1 => '2026-10-28', 2 => '2026-11-02', 3 => '2026-11-04'),
		pg_calendar_event_recurrence_dates('2026-10-26', '3', 'day', calendar_event_form_test_days(array('mon', 'wed')), ''));
}

function test_calendar_event_form_daily_repeat_without_a_weekday_stops_at_the_event()
{
	pg_assert_same(array(0 => '2026-10-26'), pg_calendar_event_recurrence_dates('2026-10-26', '2', 'day', calendar_event_form_test_days(array()), ''));
}

function test_calendar_event_form_weekly_repeat_adds_seven_days()
{
	pg_assert_same(
		array(0 => '2026-12-24', 1 => '2026-12-31', 2 => '2027-01-07'),
		pg_calendar_event_recurrence_dates('2026-12-24', '2', 'week', calendar_event_form_test_days(array()), ''));
}

function test_calendar_event_form_monthly_day_of_the_month_falls_back_to_the_last_day()
{
	pg_assert_same(
		array(0 => '2026-01-31', 1 => '2026-02-28', 2 => '2026-03-31'),
		pg_calendar_event_recurrence_dates('2026-01-31', '2', 'month', calendar_event_form_test_days(array()), 'day_of_the_month'));
}

function test_calendar_event_form_monthly_day_of_the_week_keeps_the_week()
{
	// The fourth Tuesday of October 2026, then of November and December.
	pg_assert_same(
		array(0 => '2026-10-27', 1 => '2026-11-24', 2 => '2026-12-22'),
		pg_calendar_event_recurrence_dates('2026-10-27', '2', 'month', calendar_event_form_test_days(array()), 'day_of_the_week'));
}

function test_calendar_event_form_monthly_fifth_week_is_read_as_the_last()
{
	// The fifth Thursday of October 2026 repeats on the last Thursday, which
	// in December is the last day of the month.
	pg_assert_same(
		array(0 => '2026-10-29', 1 => '2026-11-26', 2 => '2026-12-31'),
		pg_calendar_event_recurrence_dates('2026-10-29', '2', 'month', calendar_event_form_test_days(array()), 'day_of_the_week'));
}

function test_calendar_event_form_yearly_repeat_moves_a_leap_day_to_the_month_end()
{
	pg_assert_same(
		array(0 => '2024-02-29', 1 => '2025-02-28', 2 => '2026-02-28'),
		pg_calendar_event_recurrence_dates('2024-02-29', '2', 'year', calendar_event_form_test_days(array()), ''));
}

function test_calendar_event_form_screens_validate_through_the_shared_form()
{
	foreach (array('add_calendar_event.php' => "pg_calendar_event_form_validate(\$liveform)") as $file => $call) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(strpos($source, "require_once(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_form.php');") !== false, $file . ' does not load the shared form');
		pg_assert_true(strpos($source, $call) !== false, $file . ' does not validate through the shared form');
		pg_assert_false(strpos($source, 'check_calendar_event_location_availability(') !== false, $file . ' still checks locations on its own');
	}
}
