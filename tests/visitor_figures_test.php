<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the visitor figures module
 * (includes/fn/visitor_figures.php): the run of summarized days, a window cut
 * into summary and raw days, the two lists added up by value, and the
 * traffic series by grain.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The run is the contiguous days ending at the most recent one; an older
// island behind a gap is not part of it.
function test_visitor_summary_run_takes_the_latest_contiguous_days()
{
	pg_assert_same(null, pg_visitor_summary_run(array()), 'nothing summarized');

	pg_assert_same(
		array('first' => '2026-10-29', 'last' => '2026-11-01'),
		pg_visitor_summary_run(array('2026-11-01', '2026-10-31', '2026-10-30', '2026-10-29', '2026-10-20', '2026-10-19')),
		'gap before the run'
	);

	pg_assert_same(
		array('first' => '2026-10-30', 'last' => '2026-11-01'),
		pg_visitor_summary_run(array('2026-10-31', '2026-11-01', '2026-10-30', '2026-10-31')),
		'any order, a day twice'
	);

	pg_assert_same(
		array('first' => '2026-03-28', 'last' => '2026-03-30'),
		pg_visitor_summary_run(array('2026-03-30', '2026-03-29', '2026-03-28')),
		'across a clock change'
	);

	pg_assert_same(null, pg_visitor_summary_run(array('', '0000-00-00', 'nonsense')), 'no real day');
}

function test_visitor_day_split_with_the_run_in_the_middle()
{
	$from = strtotime('2026-10-20 00:00:00');
	$to = strtotime('2026-11-03 00:00:00') - 1;

	$parts = pg_visitor_day_split($from, $to, array('first' => '2026-10-24', 'last' => '2026-10-30'), '2026-11-03');

	pg_assert_same(3, count($parts));

	pg_assert_same('raw', $parts[0]['source']);
	pg_assert_same('2026-10-20', $parts[0]['first']);
	pg_assert_same('2026-10-23', $parts[0]['last']);
	pg_assert_same($from, $parts[0]['from']);
	pg_assert_same(strtotime('2026-10-24 00:00:00') - 1, $parts[0]['to']);

	pg_assert_same('summary', $parts[1]['source']);
	pg_assert_same('2026-10-24', $parts[1]['first']);
	pg_assert_same('2026-10-30', $parts[1]['last']);

	pg_assert_same('raw', $parts[2]['source']);
	pg_assert_same('2026-10-31', $parts[2]['first']);
	pg_assert_same('2026-11-02', $parts[2]['last']);
	pg_assert_same($to, $parts[2]['to']);

	pg_assert_same(array('summary' => 7, 'raw' => 7), pg_visitor_day_counts($parts));
}

// The summary reaching past the window's end; today is raw even when a row
// for it were there.
function test_visitor_day_split_with_the_run_at_the_end()
{
	$from = strtotime('2026-10-28 00:00:00');
	$to = strtotime('2026-11-03 15:20:00');

	$parts = pg_visitor_day_split($from, $to, array('first' => '2026-10-01', 'last' => '2026-11-03'), '2026-11-03');

	pg_assert_same(2, count($parts));
	pg_assert_same('summary', $parts[0]['source']);
	pg_assert_same('2026-10-28', $parts[0]['first']);
	pg_assert_same('2026-11-02', $parts[0]['last']);
	pg_assert_same('raw', $parts[1]['source']);
	pg_assert_same('2026-11-03', $parts[1]['first']);
	pg_assert_same(strtotime('2026-11-03 00:00:00'), $parts[1]['from']);
	pg_assert_same($to, $parts[1]['to'], 'today ends now');
}

function test_visitor_day_split_without_a_run()
{
	$from = strtotime('2026-10-28 00:00:00');
	$to = strtotime('2026-11-01 00:00:00') - 1;

	$parts = pg_visitor_day_split($from, $to, null, '2026-11-03');

	pg_assert_same(1, count($parts));
	pg_assert_same('raw', $parts[0]['source']);
	pg_assert_same('2026-10-28', $parts[0]['first']);
	pg_assert_same('2026-10-31', $parts[0]['last']);
	pg_assert_same(array('summary' => 0, 'raw' => 4), pg_visitor_day_counts($parts));

	pg_assert_same(array(), pg_visitor_day_split($to, $from, null, '2026-11-03'), 'from after to');
}

// A day cut short (yesterday up to this hour) cannot come from the summary,
// which holds it whole.
function test_visitor_day_split_reads_a_cut_day_from_the_rows()
{
	$from = strtotime('2026-11-02 00:00:00');
	$to = strtotime('2026-11-02 14:00:00') - 1;

	$parts = pg_visitor_day_split($from, $to, array('first' => '2026-10-01', 'last' => '2026-11-02'), '2026-11-03');

	pg_assert_same(1, count($parts));
	pg_assert_same('raw', $parts[0]['source']);
	pg_assert_same($to, $parts[0]['to']);
}

function test_visitor_window_bounds_reads_days_and_moments()
{
	pg_assert_same(
		array(strtotime('2026-10-01 00:00:00'), strtotime('2026-10-08 00:00:00') - 1),
		pg_visitor_window_bounds('2026-10-01', '2026-10-07')
	);

	$now = strtotime('2026-11-03 10:00:00');
	pg_assert_same(array($now - 3600, $now), pg_visitor_window_bounds($now - 3600, $now));

	pg_assert_same(null, pg_visitor_window_bounds('2026-13-01', '2026-10-07'), 'no such day');
}

// The two lists added up by value without regard to case; the rest row is
// never listed; largest first, then by value.
function test_visitor_merge_rows_adds_up_by_value()
{
	$summary = array(
		array('value' => 'Google', 'visits' => 10, 'page_views' => 30, 'order_total' => 1000),
		array('value' => 'bing', 'visits' => 4, 'page_views' => 5),
		array('value' => '', 'is_rest' => 1, 'visits' => 50, 'page_views' => 80),
		array('value' => '', 'is_rest' => 0, 'visits' => 2, 'page_views' => 2),
	);

	$raw = array(
		array('value' => 'google', 'visits' => 3, 'page_views' => 4, 'order_total' => 250),
		array('value' => 'Yandex', 'visits' => 4, 'page_views' => 9),
		array('value' => '', 'visits' => 1, 'page_views' => 1),
	);

	$rows = pg_visitor_merge_rows(array($summary, $raw), 10);

	pg_assert_same(4, count($rows));

	pg_assert_same('Google', $rows[0]['value'], 'first spelling kept');
	pg_assert_same(13, $rows[0]['visits']);
	pg_assert_same(34, $rows[0]['page_views']);
	pg_assert_same(1250, $rows[0]['order_total']);
	pg_assert_same(0, $rows[0]['forms_submitted'], 'missing figure is 0');

	pg_assert_same('Yandex', $rows[1]['value'], 'ties by value');
	pg_assert_same(4, $rows[1]['visits']);
	pg_assert_same('bing', $rows[2]['value']);

	pg_assert_same('', $rows[3]['value'], 'no value is a value of its own');
	pg_assert_same(3, $rows[3]['visits'], 'the rest row is not in it');

	pg_assert_same(2, count(pg_visitor_merge_rows(array($summary, $raw), 2)), 'cut to the limit');
}

function test_visitor_series_by_grain()
{
	$stats = array(
		'2026-10-31' => array(9 => array('visitors' => 2, 'page_views' => 5)),
		'2026-11-01' => array(0 => array('visitors' => 1, 'page_views' => 1), 23 => array('visitors' => 3, 'page_views' => 4)),
		'2026-11-02' => array(10 => array('visitors' => 5, 'page_views' => 6)),
	);

	$days = pg_visitor_series($stats, '2026-10-30', '2026-11-02', 'day');
	pg_assert_same(array('2026-10-30', '2026-10-31', '2026-11-01', '2026-11-02'), array_keys($days), 'empty days kept');
	pg_assert_same(array('visits' => 4, 'page_views' => 5), $days['2026-11-01']);
	pg_assert_same(array('visits' => 0, 'page_views' => 0), $days['2026-10-30']);

	$hours = pg_visitor_series($stats, '2026-11-01', '2026-11-01', 'hour');
	pg_assert_same(24, count($hours));
	pg_assert_same(array('visits' => 3, 'page_views' => 4), $hours['2026-11-01 23:00']);

	// 2026-11-01 is a Sunday: its week began on Monday 2026-10-26.
	$weeks = pg_visitor_series($stats, '2026-10-30', '2026-11-02', 'week');
	pg_assert_same(array('2026-10-26', '2026-11-02'), array_keys($weeks));
	pg_assert_same(array('visits' => 6, 'page_views' => 10), $weeks['2026-10-26']);

	$months = pg_visitor_series($stats, '2026-10-30', '2026-11-02', 'month');
	pg_assert_same(array('2026-10', '2026-11'), array_keys($months));
	pg_assert_same(array('visits' => 9, 'page_views' => 11), $months['2026-11']);

	pg_assert_same(array_keys($days), array_keys(pg_visitor_series($stats, '2026-10-30', '2026-11-02', 'year')), 'unknown grain is day');
}
