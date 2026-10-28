<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the list screens' period helpers in includes/fn/list_period.php
 * (the month and the week the period navigator's arrows move to, the week's
 * first day, the range heading in the site's date order) and the clock format helpers in includes/fn/core.php
 * (pg_time_format_code(), pg_picker_time_format() and the picker scripts
 * that carry them).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function list_period_test_range($start, $stop)
{
	return array(
		'start_month' => date('m', strtotime($start)), 'start_day' => date('d', strtotime($start)), 'start_year' => date('Y', strtotime($start)),
		'stop_month' => date('m', strtotime($stop)), 'stop_day' => date('d', strtotime($stop)), 'stop_year' => date('Y', strtotime($stop)),
	);
}

function test_list_period_month_steps_around_a_calendar_month()
{
	$steps = pg_list_period_month_steps(list_period_test_range('2026-10-01', '2026-10-31'));

	pg_assert_same(list_period_test_range('2026-09-01', '2026-09-30'), $steps['previous'], 'the month before');
	pg_assert_same(list_period_test_range('2026-11-01', '2026-11-30'), $steps['next'], 'the month after');
}

function test_list_period_month_steps_move_from_the_last_shown_month()
{
	// The default range of the orders list: Previous is September, not August.
	$steps = pg_list_period_month_steps(list_period_test_range('2026-09-26', '2026-10-27'));

	pg_assert_same(list_period_test_range('2026-09-01', '2026-09-30'), $steps['previous'], 'the month before the last shown month');
	pg_assert_same(list_period_test_range('2026-11-01', '2026-11-30'), $steps['next'], 'the month after the last shown month');

	// The contacts' all-time range: the arrows step from its last month, not
	// from the year 2020.
	$steps = pg_list_period_month_steps(list_period_test_range('2020-03-16', '2026-10-27'));

	pg_assert_same(list_period_test_range('2026-09-01', '2026-09-30'), $steps['previous'], 'all-time range, previous');
	pg_assert_same(list_period_test_range('2026-11-01', '2026-11-30'), $steps['next'], 'all-time range, next');
}

function test_list_period_month_steps_cross_the_year_and_know_february()
{
	$steps = pg_list_period_month_steps(list_period_test_range('2026-12-05', '2026-12-31'));

	pg_assert_same(list_period_test_range('2026-11-01', '2026-11-30'), $steps['previous'], 'December back');
	pg_assert_same(list_period_test_range('2027-01-01', '2027-01-31'), $steps['next'], 'December forward into the next year');

	$steps = pg_list_period_month_steps(list_period_test_range('2026-01-01', '2026-01-31'));

	pg_assert_same(list_period_test_range('2025-12-01', '2025-12-31'), $steps['previous'], 'January back into the previous year');

	$steps = pg_list_period_month_steps(list_period_test_range('2024-01-15', '2024-01-20'));

	pg_assert_same(list_period_test_range('2024-02-01', '2024-02-29'), $steps['next'], 'a leap February');
	pg_assert_same('29', $steps['next']['stop_day'], 'days are zero-padded strings, as the screens keep them');
	pg_assert_same('02', $steps['next']['start_month'], 'months are zero-padded strings');
}

function test_list_period_month_steps_without_a_range_step_around_this_month()
{
	$steps = pg_list_period_month_steps(array());
	$previous = mktime(0, 0, 0, (int) date('n') - 1, 1, (int) date('Y'));
	$next = mktime(0, 0, 0, (int) date('n') + 1, 1, (int) date('Y'));

	pg_assert_same(date('Y-m-01', $previous), $steps['previous']['start_year'] . '-' . $steps['previous']['start_month'] . '-' . $steps['previous']['start_day'], 'previous starts the month before this one');
	pg_assert_same(date('Y-m-t', $next), $steps['next']['stop_year'] . '-' . $steps['next']['stop_month'] . '-' . $steps['next']['stop_day'], 'next ends the month after this one');
}

function test_list_period_week_starts_on_monday()
{
	// Wednesday 28 October 2026.
	$steps = pg_list_period_week_steps(list_period_test_range('2026-10-26', '2026-11-01'), 1, mktime(12, 0, 0, 10, 28, 2026));

	pg_assert_same(list_period_test_range('2026-10-19', '2026-10-25'), $steps['previous'], 'the week before, Monday to Sunday');
	pg_assert_same(list_period_test_range('2026-10-26', '2026-11-01'), $steps['current'], 'this week, Monday to Sunday');
	pg_assert_same(list_period_test_range('2026-11-02', '2026-11-08'), $steps['next'], 'the week after, Monday to Sunday');
}

function test_list_period_week_starts_on_sunday()
{
	$steps = pg_list_period_week_steps(list_period_test_range('2026-10-25', '2026-10-31'), 0, mktime(12, 0, 0, 10, 28, 2026));

	pg_assert_same(list_period_test_range('2026-10-18', '2026-10-24'), $steps['previous'], 'the week before, Sunday to Saturday');
	pg_assert_same(list_period_test_range('2026-10-25', '2026-10-31'), $steps['current'], 'this week, Sunday to Saturday');
	pg_assert_same(list_period_test_range('2026-11-01', '2026-11-07'), $steps['next'], 'the week after, Sunday to Saturday');
}

function test_list_period_week_steps_from_any_start_day()
{
	// A Sunday start on a Monday-first site belongs to the week that began
	// the Monday before.
	$steps = pg_list_period_week_steps(list_period_test_range('2026-11-01', '2026-11-30'), 1, mktime(12, 0, 0, 11, 1, 2026));

	pg_assert_same(list_period_test_range('2026-10-19', '2026-10-25'), $steps['previous'], 'previous from a Sunday');
	pg_assert_same(list_period_test_range('2026-10-26', '2026-11-01'), $steps['current'], 'this week on a Sunday');
	pg_assert_same(list_period_test_range('2026-11-02', '2026-11-08'), $steps['next'], 'next from a Sunday');
}

function test_list_period_week_crosses_daylight_saving_and_the_year()
{
	$saved = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');

	// Clocks go back on Sunday 25 October 2026.
	$dst = pg_list_period_week_steps(list_period_test_range('2026-10-19', '2026-10-25'), 1, mktime(12, 0, 0, 10, 25, 2026));
	$year = pg_list_period_week_steps(list_period_test_range('2026-12-28', '2027-01-03'), 1, mktime(12, 0, 0, 12, 31, 2026));

	date_default_timezone_set($saved);

	pg_assert_same(list_period_test_range('2026-10-26', '2026-11-01'), $dst['next'], 'next week after the clock change');
	pg_assert_same(list_period_test_range('2026-10-19', '2026-10-25'), $dst['current'], 'the week of the clock change');
	pg_assert_same(list_period_test_range('2027-01-04', '2027-01-10'), $year['next'], 'into the new year');
	pg_assert_same(list_period_test_range('2026-12-21', '2026-12-27'), $year['previous'], 'back from a week across the year');
}

function test_list_period_navigator_recognises_its_own_weeks()
{
	foreach (array(0, 1) as $first_day) {
		$steps = pg_list_period_week_steps(list_period_test_range('2026-10-26', '2026-11-01'), $first_day, mktime(12, 0, 0, 10, 28, 2026));

		foreach ($steps as $key => $week) {
			pg_assert_same('week', pg_period_unit($week, $first_day), $key . ' week, first day ' . $first_day);
			pg_assert_same('month', pg_period_unit($week, 1 - $first_day), $key . ' week is no week from the other first day');
		}
	}

	pg_assert_true(in_array(pg_list_period_week_first_day(), array(0, 1), true), 'first day is Sunday or Monday');
	pg_assert_same((lang(array('info' => '')) === 'tr') ? 1 : 0, pg_list_period_week_first_day(), 'Monday in Turkish');
}

function test_list_period_heading_follows_the_date_order()
{
	$range = list_period_test_range('2026-09-26', '2026-10-27');

	pg_assert_same('26 September 2026 – 27 October 2026', pg_list_period_heading($range, 'day_month'), 'day first');
	pg_assert_same('September 26, 2026 – October 27, 2026', pg_list_period_heading($range, 'month_day'), 'month first');
	pg_assert_same('5 September 2026 – 7 October 2026', pg_list_period_heading(list_period_test_range('2026-09-05', '2026-10-07'), 'day_month'), 'no leading zero on the day');
	pg_assert_same('27 October 2026', pg_list_period_heading(list_period_test_range('2026-10-27', '2026-10-27'), 'day_month'), 'one day is written once');
}

function test_list_period_time_format_follows_the_clock_setting()
{
	pg_assert_same('H:i', pg_time_format_code('twenty_four_hours'), '24-hour date() code');
	pg_assert_same('g:i A', pg_time_format_code('twelve_hours'), '12-hour date() code');
	pg_assert_same('HH:mm', pg_picker_time_format('twenty_four_hours'), '24-hour picker format');
	pg_assert_same('h:mm TT', pg_picker_time_format('twelve_hours'), '12-hour picker format');
	pg_assert_same('03:00', date(pg_time_format_code('twenty_four_hours'), mktime(3, 0, 0, 10, 27, 2026)), '3 in the morning on the 24-hour clock');
	pg_assert_same('3:00 AM', date(pg_time_format_code('twelve_hours'), mktime(3, 0, 0, 10, 27, 2026)), 'the same on the 12-hour clock');
}

function test_list_period_picker_scripts_carry_the_clock_format()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}

	$expected = 'timeFormat: "' . pg_picker_time_format() . '"';

	pg_assert_contains($expected, get_date_picker_format(), 'date picker options');
	pg_assert_contains($expected, get_date_time_picker_format(), 'date and time picker options');
	pg_assert_contains($expected, get_time_picker_format(), 'time picker options');
	pg_assert_false(strpos(get_date_picker_format() . get_date_time_picker_format() . get_time_picker_format(), 'h:mm TT') !== false && pg_picker_time_format() !== 'h:mm TT', 'no 12-hour literal on the 24-hour clock');
}

function test_list_period_every_list_screen_steps_from_the_last_shown_month()
{
	// Each screen once computed "start month - 1" itself; from "26 September -
	// 27 October" that showed August.
	foreach (array('view_orders.php', 'view_contacts.php', 'view_visitor_report.php', 'view_submitted_forms.php', 'view_order_report.php') as $screen) {
		$source = file_get_contents(dirname(__DIR__) . '/pinegrap/' . $screen);

		pg_assert_contains('pg_list_period_month_steps(', $source, $screen . ' reads the month steps');
		pg_assert_false(strpos($source, "\$decrease_month['new_time']") !== false, $screen . ' has no month arithmetic of its own');
		pg_assert_contains('pg_list_period_week_steps(', $source, $screen . ' reads the week steps');
		pg_assert_false(stripos($source, "'last sunday") !== false, $screen . ' has no Sunday week of its own');
	}

	foreach (array('view_orders.php', 'view_contacts.php', 'view_submitted_forms.php') as $screen) {
		pg_assert_contains('pg_list_period_heading(', file_get_contents(dirname(__DIR__) . '/pinegrap/' . $screen), $screen . ' writes the range in the site date order');
	}
}

function test_list_period_date_filters_say_start_and_end()
{
	// "From" / "To" also name e-mail senders and recipients; one Turkish
	// value cannot fit both.
	foreach (array('view_orders.php', 'view_log.php', 'view_audit_log.php', 'erp_profit.php', 'erp_expenses.php', 'erp_accountant.php', 'erp_export.php', 'view_submitted_forms.php', 'view_contacts.php', 'view_commissions.php') as $screen) {
		$source = file_get_contents(dirname(__DIR__) . '/pinegrap/' . $screen);

		pg_assert_false(strpos($source, "lang('From')") !== false || strpos($source, "lang('To')") !== false, $screen . ' labels its date range Start / End');
	}
}

function test_list_period_date_filter_selects_are_named()
{
	// A label without for= names nothing; the month/day/year selects of each
	// date sit in a fieldset named Start / End and carry their own name.
	foreach (array('view_orders.php', 'view_submitted_forms.php', 'view_contacts.php', 'view_commissions.php') as $screen) {
		$source = file_get_contents(dirname(__DIR__) . '/pinegrap/' . $screen);

		pg_assert_contains("<legend class=\"form-label\">' . lang('Start') . '</legend>", $source, $screen . ' names the start date');
		pg_assert_contains("<legend class=\"form-label\">' . lang('End') . '</legend>", $source, $screen . ' names the end date');

		foreach (array('start_month' => 'Month', 'start_day' => 'Day', 'start_year' => 'Year', 'stop_month' => 'Month', 'stop_day' => 'Day', 'stop_year' => 'Year') as $field => $name) {
			pg_assert_contains('name="' . $field . "\" aria-label=\"' . h(lang('" . $name . "')) . '\"", $source, $screen . ' names ' . $field);
		}
	}
}
