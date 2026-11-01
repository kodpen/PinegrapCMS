<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_calendar_view_date() in includes/fn/calendar.php: the
 * MM-DD-YYYY date a calendar view is asked for, checked and written with
 * two-digit months and days so the date-range comparisons keep working.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_calendar_view_date_keeps_a_padded_date()
{
	pg_assert_same('03-01-2026', pg_calendar_view_date('03-01-2026'));
}

function test_calendar_view_date_pads_the_month_and_day()
{
	pg_assert_same('03-01-2026', pg_calendar_view_date('3-1-2026'));
	pg_assert_same('11-05-2026', pg_calendar_view_date('011-005-02026'));
}

function test_calendar_view_date_drops_what_is_not_a_date()
{
	pg_assert_same('', pg_calendar_view_date(''));
	pg_assert_same('', pg_calendar_view_date('02-30-2026'), 'no 30 February');
	pg_assert_same('', pg_calendar_view_date('2026-03-01'), 'year first');
	pg_assert_same('', pg_calendar_view_date('03-01'), 'two parts');
	pg_assert_same('', pg_calendar_view_date('03-x1-2026'));
	pg_assert_same('', pg_calendar_view_date(array('03-01-2026')), 'not text');
	pg_assert_same('', pg_calendar_view_date(null));
}
