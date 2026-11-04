<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the traffic section of the Visitors overview
 * (includes/visitors/traffic.php): pages a visit, the hours not drawn on
 * today's line, the series lists, the labels and bucket ranges, the tooltip
 * line, and the section's tiles, line and empty states.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_VISITORS_SCREEN')) {
	define('PG_VISITORS_SCREEN', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/traffic.php');

function visitors_traffic_test_figures(array $change = array())
{
	return $change + array(
		'now'             => array('visits' => 40, 'page_views' => 100),
		'before'          => array('visits' => 20, 'page_views' => 100),
		'online'          => 3,
		'series'          => array('2026-11-02' => array('visits' => 15, 'page_views' => 40), '2026-11-03' => array('visits' => 25, 'page_views' => 60)),
		'previous_series' => array('2026-10-31' => array('visits' => 10, 'page_views' => 50), '2026-11-01' => array('visits' => 10, 'page_views' => 50)),
		'notes'           => array('', 'Most viewed: Home (12)'),
		'backfill'        => false,
	);
}

function test_visitors_traffic_ratio()
{
	pg_assert_same(2.5, pg_visitors_traffic_ratio(100, 40));
	pg_assert_same(3.3, pg_visitors_traffic_ratio(10, 3), 'one decimal');
	pg_assert_same(null, pg_visitors_traffic_ratio(5, 0), 'no visit, no ratio');
}

function test_visitors_traffic_cut_only_for_a_day_ending_now()
{
	$now = strtotime('2026-11-04 14:25:00');
	$today = pg_visitor_period(array('p' => 'today'), $now);
	pg_assert_same(14, pg_visitors_traffic_cut($today), 'the hours after the present one are not drawn');

	$yesterday = pg_visitor_period(array('p' => 'yesterday'), $now);
	pg_assert_same(null, pg_visitors_traffic_cut($yesterday), 'a whole day is drawn whole');

	pg_assert_same(null, pg_visitors_traffic_cut(pg_visitor_period(array('p' => '7d'), $now)), 'a day grain is never cut');
}

function test_visitors_traffic_values_pad_and_cut()
{
	$series = array('a' => array('visits' => 1, 'page_views' => 2), 'b' => array('visits' => 3, 'page_views' => 4), 'c' => array('visits' => 5, 'page_views' => 6));

	pg_assert_same(array(1, 3, 5), pg_visitors_traffic_values($series, 'visits', 3, null));
	pg_assert_same(array(2, 4, null), pg_visitors_traffic_values($series, 'page_views', 3, 1), 'past the cut is null, not 0');
	pg_assert_same(array(1, 3, 5, null), pg_visitors_traffic_values($series, 'visits', 4, null), 'a shorter earlier period is padded');
	pg_assert_same(array(1, 3), pg_visitors_traffic_values($series, 'visits', 2, null), 'a longer earlier period is cut to the line');
}

function test_visitors_traffic_labels_and_ranges()
{
	pg_assert_same(array('00:00', '13:00'), pg_visitors_traffic_labels(array('2026-11-04 00:00', '2026-11-04 13:00'), 'hour'));
	pg_assert_same(array(lang('October') . ' 2026'), pg_visitors_traffic_labels(array('2026-10'), 'month'));

	$period = array('grain' => 'week', 'from' => '2026-10-08', 'to' => '2026-11-04');
	pg_assert_same(
		array(array('2026-10-08', '2026-10-11'), array('2026-11-02', '2026-11-04')),
		pg_visitors_traffic_ranges($period, array('2026-10-05', '2026-11-02')),
		'a week is cut to the period at both ends'
	);

	$period = array('grain' => 'month', 'from' => '2026-02-10', 'to' => '2026-06-30');
	pg_assert_same(array(array('2026-02-10', '2026-02-28')), pg_visitors_traffic_ranges($period, array('2026-02')));

	$period = array('grain' => 'day', 'from' => '2026-11-01', 'to' => '2026-11-04');
	pg_assert_same(array(array('2026-11-03', '2026-11-03')), pg_visitors_traffic_ranges($period, array('2026-11-03')));
}

function test_visitors_traffic_note()
{
	pg_assert_same('', pg_visitors_traffic_note('', 5));
	pg_assert_same('', pg_visitors_traffic_note('Home', 0));
	pg_assert_contains('Home', pg_visitors_traffic_note('Home', 12));
}

function test_visitors_traffic_render_tiles_line_and_change()
{
	$period = pg_visitor_period(array('p' => '7d'), strtotime('2026-11-04 14:25:00'));
	$html = pg_visitors_traffic_render($period, visitors_traffic_test_figures());

	pg_assert_contains('id="traffic"', $html);
	pg_assert_contains(h(lang('Pages a visit')), $html);
	pg_assert_contains(pg_format_number(2.5, 1), $html);
	pg_assert_contains('is-good', $html, 'visits doubled');
	pg_assert_contains('is-bad', $html, 'pages a visit fell from 5 to 2.5');
	pg_assert_contains('data-pg-chart', $html);
	pg_assert_contains('Most viewed: Home (12)', $html, 'the tooltip lines ride in the chart data');
	pg_assert_contains(h(lang('Visits, earlier period')), $html);
	pg_assert_true(strpos($html, h(lang('The visit history is still being added to the line.'))) === false, 'no backfill note when it is done');

	$off = pg_visitors_traffic_render($period, visitors_traffic_test_figures(array('before' => null, 'previous_series' => array())));
	pg_assert_true(strpos($off, 'pg-visitors-change') === false, 'no change with comparing off');
	pg_assert_true(strpos($off, h(lang('Visits, earlier period'))) === false, 'no dashed line with comparing off');
}

function test_visitors_traffic_render_empty_states()
{
	$period = pg_visitor_period(array('p' => 'today'), strtotime('2026-11-04 14:25:00'));
	$empty = array(
		'now'             => array('visits' => 0, 'page_views' => 0),
		'before'          => array('visits' => 0, 'page_views' => 0),
		'online'          => 0,
		'series'          => array('2026-11-04 00:00' => array('visits' => 0, 'page_views' => 0)),
		'previous_series' => array(),
		'notes'           => array(),
		'backfill'        => false,
	);

	$html = pg_visitors_traffic_render($period, $empty);
	pg_assert_contains(h(lang('Nothing in this period.')), $html);
	pg_assert_contains('–', $html, 'pages a visit without a visit');
	pg_assert_true(strpos($html, 'data-pg-chart') === false, 'no empty chart');

	$html = pg_visitors_traffic_render($period, array('backfill' => true) + $empty);
	pg_assert_contains(h(lang('The figures are still being built from the visit history; this section fills in as that finishes.')), $html);
	pg_assert_contains(h(lang('The visit history is still being added to the line.')), $html);
}
