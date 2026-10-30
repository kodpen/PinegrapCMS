<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar grid helpers in includes/fn/calendar.php: the order
 * of the day columns and the inactive cells before and after a month for a
 * week that starts on Sunday or on Monday.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function calendar_week_start_test_padding($year, $month, $first_day)
{
	$start = mktime(0, 0, 0, $month, 1, $year);
	$stop = mktime(0, 0, 0, $month, (int) date('t', $start), $year);

	return pg_calendar_month_padding($start, $stop, $first_day);
}

function test_calendar_day_order_follows_the_first_day()
{
	pg_assert_same(array(1, 2, 3, 4, 5, 6, 0), pg_calendar_day_order(1), 'Monday first');
	pg_assert_same(array(0, 1, 2, 3, 4, 5, 6), pg_calendar_day_order(0), 'Sunday first');
}

function test_calendar_month_padding_for_a_month_from_thursday_to_saturday()
{
	// 1 October 2026 is a Thursday, 31 October a Saturday.
	pg_assert_same(array('before' => 3, 'after' => 1), calendar_week_start_test_padding(2026, 10, 1), 'Monday first');
	pg_assert_same(array('before' => 4, 'after' => 0), calendar_week_start_test_padding(2026, 10, 0), 'Sunday first');
}

function test_calendar_month_padding_for_a_month_that_starts_on_sunday()
{
	// 1 November 2026 is a Sunday: the last column of a Monday-first week.
	pg_assert_same(6, calendar_week_start_test_padding(2026, 11, 1)['before'], 'Monday first');
	pg_assert_same(0, calendar_week_start_test_padding(2026, 11, 0)['before'], 'Sunday first');
}

function test_calendar_month_padding_for_a_month_that_ends_on_the_last_day_of_the_week()
{
	// 31 May 2026 is a Sunday.
	pg_assert_same(0, calendar_week_start_test_padding(2026, 5, 1)['after'], 'Monday first');
	pg_assert_same(6, calendar_week_start_test_padding(2026, 5, 0)['after'], 'Sunday first');
}

function test_calendar_grid_keeps_no_sunday_week_of_its_own()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/calendar.php');

	pg_assert_same(false, strpos($source, "'last Sunday"), 'no last Sunday');
	pg_assert_same(false, strpos($source, "strtotime('Sunday'"), 'no strtotime Sunday');
	pg_assert_same(false, strpos($source, "'2 Sunday'"), 'no 2 Sunday');
	pg_assert_same(true, strpos($source, 'pg_list_period_week_first_day(') !== false, 'reads the week first day');
}
