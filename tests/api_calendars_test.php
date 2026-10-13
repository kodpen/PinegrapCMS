<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's calendar presenters in
 * includes/api/resources/calendars.php: the repeat rule read from the event
 * row's columns, and the stored wall-clock times written with the site offset.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/calendars.php');

// An event row with every repeat column at the event screen's defaults.
function api_calendars_test_row($overrides = array())
{
	return array_merge(array(
		'recurrence' => '0', 'recurrence_number' => '0', 'recurrence_type' => '',
		'recurrence_month_type' => 'day_of_the_month',
		'recurrence_day_sun' => '0', 'recurrence_day_mon' => '0', 'recurrence_day_tue' => '0',
		'recurrence_day_wed' => '0', 'recurrence_day_thu' => '0', 'recurrence_day_fri' => '0',
		'recurrence_day_sat' => '0',
	), $overrides);
}

function test_api_calendars_one_off_event_has_no_rule()
{
	pg_assert_same(null, api_calendar_event_recurrence(api_calendars_test_row()), 'no repeat');
}

function test_api_calendars_weekly_rule_lists_its_days()
{
	$rule = api_calendar_event_recurrence(api_calendars_test_row(array(
		'recurrence' => '1', 'recurrence_number' => '4', 'recurrence_type' => 'week',
		'recurrence_day_mon' => '1', 'recurrence_day_thu' => '1',
	)));

	pg_assert_same('week', $rule['unit'], 'unit');
	pg_assert_same(4, $rule['times'], 'times');
	pg_assert_same(array('mon', 'thu'), $rule['days'], 'days');
	pg_assert_same(null, $rule['month_by'], 'month_by only for a monthly repeat');
}

function test_api_calendars_monthly_rule_says_how_it_lands()
{
	$by_date = api_calendar_event_recurrence(api_calendars_test_row(array(
		'recurrence' => '1', 'recurrence_number' => '2', 'recurrence_type' => 'month',
	)));

	$by_weekday = api_calendar_event_recurrence(api_calendars_test_row(array(
		'recurrence' => '1', 'recurrence_number' => '2', 'recurrence_type' => 'month',
		'recurrence_month_type' => 'day_of_the_week',
	)));

	pg_assert_same('date', $by_date['month_by'], 'same date each month');
	pg_assert_same('weekday', $by_weekday['month_by'], 'same weekday of the same week');
	pg_assert_same(null, $by_date['days'], 'days only for a daily or weekly repeat');
}

function test_api_calendars_time_carries_the_site_offset()
{
	$zone = date_default_timezone_get();

	date_default_timezone_set('Europe/Istanbul');

	pg_assert_same('2026-10-13T09:30:00+03:00', api_calendar_time('2026-10-13 09:30:00'), 'wall clock with offset');
	pg_assert_same(null, api_calendar_time('0000-00-00 00:00:00'), 'the table default is no time');
	pg_assert_same(null, api_calendar_time(''), 'empty');

	date_default_timezone_set($zone);
}

function test_api_calendars_written_times_follow_the_event_screen()
{
	$zone = date_default_timezone_get();

	date_default_timezone_set('Europe/Istanbul');

	$start = strtotime('2026-11-01 10:00:00');
	$end = strtotime('2026-11-01 12:00:00');

	pg_assert_same(array('2026-11-01 10:00:00', '2026-11-01 12:00:00'), api_calendar_event_times($start, $end, false), 'timed event as sent');
	pg_assert_same(null, api_calendar_event_times($start, $start, false), 'a timed event ends after it starts');
	pg_assert_same(array('2026-11-01 00:00:00', '2026-11-01 23:59:00'), api_calendar_event_times($start, $end, true), 'all day covers the whole day');
	pg_assert_same(array('2026-11-01 00:00:00', '2026-11-03 23:59:00'), api_calendar_event_times($start, strtotime('2026-11-03'), true), 'all day runs to the end of its last day');
	pg_assert_same(null, api_calendar_event_times($start, strtotime('2026-10-31 23:00:00'), true), 'an all-day event cannot end the day before');

	date_default_timezone_set($zone);
}
