<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_calendar_occurrence_date() and pg_calendar_event_rrule() in
 * includes/fn/calendar.php, and the removed dates (EXDATE) of
 * pg_calendar_event_ics(): the dates of a repeating event's occurrences and
 * the rule a calendar app reads to draw the same dates.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function calendar_event_repeat_test_days($on)
{
	$days = array();

	foreach (array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat') as $day) {
		$days[$day] = in_array($day, $on, true) ? '1' : '0';
	}

	return $days;
}

function calendar_event_repeat_test_event($fields = array())
{
	return array_merge(array(
		'id' => '7',
		'created_timestamp' => '1767225600',
		'all_day' => '0',
		'start_date' => '2026-11-09',
		'start_date_and_time' => '2026-11-09 10:00:00',
		'end_date_and_time' => '2026-11-09 12:00:00',
		'location' => '',
		'name' => 'Class',
		'short_description' => '',
		'recurrence' => '1',
		'recurrence_type' => 'week',
		'recurrence_month_type' => 'day_of_the_month',
		'total_recurrence_number' => '3',
		'recurrence_days' => calendar_event_repeat_test_days(array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat')),
	), $fields);
}

function test_calendar_occurrence_date_daily_skips_days_left_out()
{
	// Monday 9 November 2026, weekdays only: the fifth repeat is the next Monday.
	$weekdays = calendar_event_repeat_test_days(array('mon', 'tue', 'wed', 'thu', 'fri'));

	pg_assert_same('2026-11-13', pg_calendar_occurrence_date('2026-11-09', 'day', '', $weekdays, 4));
	pg_assert_same('2026-11-16', pg_calendar_occurrence_date('2026-11-09', 'day', '', $weekdays, 5));
}

function test_calendar_occurrence_date_weekly_monthly_yearly()
{
	$none = calendar_event_repeat_test_days(array());

	pg_assert_same('2026-11-09', pg_calendar_occurrence_date('2026-11-09', 'week', '', $none, 0));
	pg_assert_same('2026-11-30', pg_calendar_occurrence_date('2026-11-09', 'week', '', $none, 3));
	// The 31st lands on the last day of a shorter month.
	pg_assert_same('2027-02-28', pg_calendar_occurrence_date('2027-01-31', 'month', 'day_of_the_month', $none, 1));
	pg_assert_same('2027-03-31', pg_calendar_occurrence_date('2027-01-31', 'month', 'day_of_the_month', $none, 2));
	// Second Monday of November 2026, then of December and January.
	pg_assert_same('2026-12-14', pg_calendar_occurrence_date('2026-11-09', 'month', 'day_of_the_week', $none, 1));
	pg_assert_same('2027-01-11', pg_calendar_occurrence_date('2026-11-09', 'month', 'day_of_the_week', $none, 2));
	// Fifth Monday (30 November) is read as the last: 28 December.
	pg_assert_same('2026-12-28', pg_calendar_occurrence_date('2026-11-30', 'month', 'day_of_the_week', $none, 1));
	pg_assert_same('2029-02-28', pg_calendar_occurrence_date('2028-02-29', 'year', '', $none, 1));
	pg_assert_same('2032-02-29', pg_calendar_occurrence_date('2028-02-29', 'year', '', $none, 4));
}

function test_calendar_occurrence_date_unknown_kinds_stay_on_the_first_date()
{
	$none = calendar_event_repeat_test_days(array());

	pg_assert_same('2026-11-09', pg_calendar_occurrence_date('2026-11-09', 'hour', '', $none, 2));
	pg_assert_same('2026-11-09', pg_calendar_occurrence_date('2026-11-09', 'day', '', $none, 2));
}

function test_calendar_event_rrule_daily_names_its_days()
{
	// Before, every daily repeat was FREQ=DAILY: a weekday class showed on
	// weekends in the visitor's calendar and stopped days too early.
	$event = calendar_event_repeat_test_event(array(
		'recurrence_type' => 'day',
		'total_recurrence_number' => '9',
		'recurrence_days' => calendar_event_repeat_test_days(array('mon', 'wed', 'fri')),
	));

	pg_assert_same('FREQ=DAILY;BYDAY=MO,WE,FR;COUNT=10', pg_calendar_event_rrule($event));
	pg_assert_same('FREQ=DAILY;COUNT=4', pg_calendar_event_rrule(calendar_event_repeat_test_event(array('recurrence_type' => 'day'))));
}

function test_calendar_event_rrule_month_end_dates_fall_back_to_the_last_day()
{
	pg_assert_same('FREQ=MONTHLY;BYMONTHDAY=31,-1;BYSETPOS=1;COUNT=4', pg_calendar_event_rrule(calendar_event_repeat_test_event(array(
		'recurrence_type' => 'month',
		'start_date' => '2027-01-31',
	))));
	pg_assert_same('FREQ=MONTHLY;COUNT=4', pg_calendar_event_rrule(calendar_event_repeat_test_event(array('recurrence_type' => 'month'))));
	pg_assert_same('FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29,-1;BYSETPOS=1;COUNT=4', pg_calendar_event_rrule(calendar_event_repeat_test_event(array(
		'recurrence_type' => 'year',
		'start_date' => '2028-02-29',
	))));
}

function test_calendar_event_rrule_weekday_of_the_month()
{
	pg_assert_same('FREQ=MONTHLY;BYDAY=2MO;COUNT=4', pg_calendar_event_rrule(calendar_event_repeat_test_event(array(
		'recurrence_type' => 'month',
		'recurrence_month_type' => 'day_of_the_week',
	))));
	pg_assert_same('FREQ=MONTHLY;BYDAY=-1MO;COUNT=4', pg_calendar_event_rrule(calendar_event_repeat_test_event(array(
		'recurrence_type' => 'month',
		'recurrence_month_type' => 'day_of_the_week',
		'start_date' => '2026-11-30',
	))));
	pg_assert_same('', pg_calendar_event_rrule(calendar_event_repeat_test_event(array('recurrence' => '0'))));
}

function test_calendar_event_ics_leaves_out_removed_dates()
{
	// Before, an occurrence removed from the calendar stayed in the .ics.
	$event = calendar_event_repeat_test_event();
	$ics = pg_calendar_event_ics($event, 'example.com', 'Events', pg_calendar_event_rrule($event), array('2026-11-23', '2026-11-16', 'x'));

	pg_assert_contains("RRULE:FREQ=WEEKLY;COUNT=4\r\nEXDATE:20261116T100000,20261123T100000\r\n", $ics);

	$all_day = calendar_event_repeat_test_event(array(
		'all_day' => '1',
		'start_date_and_time' => '2026-11-09 00:00:00',
		'end_date_and_time' => '2026-11-09 23:59:00',
	));
	$ics = pg_calendar_event_ics($all_day, 'example.com', 'Events', pg_calendar_event_rrule($all_day), array('2026-11-16'));

	pg_assert_contains("EXDATE;VALUE=DATE:20261116\r\n", $ics);
	pg_assert_false(strpos(pg_calendar_event_ics($event, 'example.com', 'Events', ''), 'EXDATE') !== false, 'no EXDATE without a rule');
}
