<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the list screens' period helpers in includes/fn/list_period.php
 * (the month the ‹ Month › arrows move to, the range heading in the site's
 * date order) and the clock format helpers in includes/fn/core.php
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
	}

	foreach (array('view_orders.php', 'view_contacts.php', 'view_submitted_forms.php') as $screen) {
		pg_assert_contains('pg_list_period_heading(', file_get_contents(dirname(__DIR__) . '/pinegrap/' . $screen), $screen . ' writes the range in the site date order');
	}
}
