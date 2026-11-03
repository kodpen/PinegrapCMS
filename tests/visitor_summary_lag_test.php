<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_visitor_summary_lag() in includes/fn/visitor_retention.php:
 * how many days the daily visitor summary is behind, which the System Status
 * line reports past PG_VISITOR_SUMMARY_LATE_DAYS.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Written through yesterday: one day back, the normal state.
function test_visitor_summary_lag_through_yesterday()
{
	pg_assert_same(1, pg_visitor_summary_lag('2026-11-02', '2026-01-01', '2026-11-03'));
}

// Written through four days ago: four days back.
function test_visitor_summary_lag_days_back()
{
	pg_assert_same(4, pg_visitor_summary_lag('2026-10-30', '2026-01-01', '2026-11-03'));
}

// No visit recorded: nothing to write, nothing late.
function test_visitor_summary_lag_without_visits()
{
	pg_assert_same(0, pg_visitor_summary_lag('', '', '2026-11-03'));
}

// Nothing written yet: counted from the day before the oldest visit.
function test_visitor_summary_lag_nothing_written()
{
	pg_assert_same(1, pg_visitor_summary_lag('', '2026-11-03', '2026-11-03'));
	pg_assert_same(5, pg_visitor_summary_lag('', '2026-10-30', '2026-11-03'));
}

// Across the end of a month and a clock change.
function test_visitor_summary_lag_across_month_end()
{
	pg_assert_same(3, pg_visitor_summary_lag('2026-10-29', '2026-01-01', '2026-11-01'));
	pg_assert_same(2, pg_visitor_summary_lag('2026-03-28', '2026-01-01', '2026-03-30'));
}

// A summary dated today or later (a clock set back) is not late.
function test_visitor_summary_lag_not_negative()
{
	pg_assert_same(0, pg_visitor_summary_lag('2026-11-04', '2026-01-01', '2026-11-03'));
}

// The status line threshold: more than two days back.
function test_visitor_summary_late_threshold()
{
	pg_assert_same(2, PG_VISITOR_SUMMARY_LATE_DAYS);
}
