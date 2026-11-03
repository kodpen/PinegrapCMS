<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the day order of the daily visitor summary in
 * includes/fn/visitor_retention.php: which days a run writes and in what
 * order, when the last two days are written again, and up to which day the
 * summary covers the raw rows without a gap.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Nothing summarized, retention off: yesterday first, then every day back
// to the oldest visit.
function test_visitor_summary_days_due_from_nothing_without_retention()
{
	pg_assert_same(
		array('2026-11-02', '2026-11-01', '2026-10-31', '2026-10-30', '2026-10-29'),
		pg_visitor_summary_days_due(array(), '2026-10-29', '2026-11-02', '', false)
	);
}

// Summarized days are skipped; a hole in the middle is filled on the way back.
function test_visitor_summary_days_due_skips_written_days()
{
	pg_assert_same(
		array('2026-11-02', '2026-10-30'),
		pg_visitor_summary_days_due(array('2026-10-29', '2026-10-31', '2026-11-01'), '2026-10-29', '2026-11-02', '', false)
	);
}

// The last two days are written again when that is due, whether they are
// summarized or not, and first.
function test_visitor_summary_days_due_rewrites_the_last_two_days()
{
	$summarized = array('2026-10-29', '2026-10-30', '2026-10-31', '2026-11-01', '2026-11-02');

	pg_assert_same(array(), pg_visitor_summary_days_due($summarized, '2026-10-29', '2026-11-02', '', false));
	pg_assert_same(
		array('2026-11-02', '2026-11-01'),
		pg_visitor_summary_days_due($summarized, '2026-10-29', '2026-11-02', '', true)
	);
	pg_assert_same(
		array('2026-11-02', '2026-11-01', '2026-10-30'),
		pg_visitor_summary_days_due(array('2026-10-29', '2026-11-01', '2026-11-02', '2026-10-31'), '2026-10-29', '2026-11-02', '', true)
	);
}

// With a retention period, the days the removal waits for come right after
// the recent ones, oldest first; the rest then backwards from yesterday.
function test_visitor_summary_days_due_puts_the_removal_days_first()
{
	pg_assert_same(
		array('2026-11-02', '2026-11-01', '2026-10-25', '2026-10-26', '2026-10-31', '2026-10-30', '2026-10-29', '2026-10-28', '2026-10-27'),
		pg_visitor_summary_days_due(array('2026-10-24'), '2026-10-24', '2026-11-02', '2026-10-27', true)
	);
}

// A site whose first visit is today has no day to write; one whose first
// visit was yesterday writes yesterday alone.
function test_visitor_summary_days_due_on_a_new_site()
{
	pg_assert_same(array(), pg_visitor_summary_days_due(array(), '2026-11-03', '2026-11-02', '', true));
	pg_assert_same(array('2026-11-02'), pg_visitor_summary_days_due(array(), '2026-11-02', '2026-11-02', '', true));
	pg_assert_same(array(), pg_visitor_summary_days_due(array(), '', '2026-11-02', '', true));
}

// The month and year turn, and a daylight saving change, step one calendar day.
function test_visitor_summary_days_due_across_month_and_dst()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');

	try {
		pg_assert_same(
			array('2026-03-30', '2026-03-29', '2026-03-28'),
			pg_visitor_summary_days_due(array(), '2026-03-28', '2026-03-30', '', false)
		);
		pg_assert_same(
			array('2026-01-01', '2025-12-31'),
			pg_visitor_summary_days_due(array(), '2025-12-31', '2026-01-01', '', false)
		);
	} finally {
		date_default_timezone_set($zone);
	}
}

// The removal may go up to the first day from the oldest visit on that is
// not summarized, and never past the retention boundary.
function test_visitor_summary_covered_until()
{
	pg_assert_same('2026-10-27', pg_visitor_summary_covered_until(array('2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27'), '2026-10-24', '2026-10-27'));
	pg_assert_same('2026-10-25', pg_visitor_summary_covered_until(array('2026-10-24', '2026-10-26'), '2026-10-24', '2026-10-27'));
	pg_assert_same('2026-10-24', pg_visitor_summary_covered_until(array(), '2026-10-24', '2026-10-27'));
	pg_assert_same('2026-10-27', pg_visitor_summary_covered_until(array(), '2026-10-28', '2026-10-27'), 'no visit before the boundary');
	pg_assert_same('2026-10-27', pg_visitor_summary_covered_until(array(), '', '2026-10-27'), 'no visit at all');
}

// The last two days are written again on the first run of a day and then at
// most once an hour.
function test_visitor_summary_recent_due()
{
	$now = 1793000000;

	pg_assert_true(pg_visitor_summary_recent_due(array(), '2026-11-02', $now), 'never written');
	pg_assert_true(pg_visitor_summary_recent_due(array('recent_day' => '2026-11-01', 'recent_at' => $now - 60), '2026-11-02', $now), 'a new day');
	pg_assert_false(pg_visitor_summary_recent_due(array('recent_day' => '2026-11-02', 'recent_at' => $now - 60), '2026-11-02', $now), 'a minute ago');
	pg_assert_true(pg_visitor_summary_recent_due(array('recent_day' => '2026-11-02', 'recent_at' => $now - 3600), '2026-11-02', $now), 'an hour ago');
}
