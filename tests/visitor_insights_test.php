<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Speed and AI visibility sections' pure helpers
 * (includes/fn/visitor_insights.php): the period window, how a figure is
 * written and rated, the trend line's points, the AI fetch summary and the
 * dashboard perf card's page speed tile.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_visitor_insight_window_ends_today_and_counts_whole_days()
{
	$now = strtotime('2026-11-02 09:30:00');
	$window = pg_visitor_insight_window('7d', $now);

	pg_assert_same('7d', $window['key']);
	pg_assert_same(7, $window['days']);
	pg_assert_same('2026-10-27', $window['from'], 'seven days with today');
	pg_assert_same('2026-11-02', $window['to']);

	$window = pg_visitor_insight_window('90d', $now);
	pg_assert_same('2026-08-05', $window['from']);
}

function test_visitor_insight_window_falls_back_to_30_days()
{
	$now = strtotime('2026-03-31 23:50:00');

	foreach (array('', 'x', array('7d'), null, '365d') as $bad) {
		$window = pg_visitor_insight_window($bad, $now);
		pg_assert_same('30d', $window['key']);
		pg_assert_same('2026-03-02', $window['from'], 'across the March clock change');
	}
}

function test_vitals_display_writes_each_figure_in_its_unit()
{
	pg_assert_same('-', pg_vitals_display('lcp', null));
	pg_assert_same(pg_format_number(850, 0) . ' ms', pg_vitals_display('lcp', 850));
	pg_assert_same(pg_format_number(2.4, 1) . ' s', pg_vitals_display('lcp', 2440));
	pg_assert_same(pg_format_number(1200, 0) . ' ms', pg_vitals_display('inp', 1200), 'INP stays in ms');
	pg_assert_same(pg_format_number(0.12, 2), pg_vitals_display('cls', 120));
}

function test_vitals_tone_follows_the_thresholds()
{
	pg_assert_same('good', pg_vitals_tone('lcp', 2500));
	pg_assert_same('warn', pg_vitals_tone('lcp', 2501));
	pg_assert_same('bad', pg_vitals_tone('lcp', 4001));
	pg_assert_same('warn', pg_vitals_tone('inp', 300));
	pg_assert_same('good', pg_vitals_tone('cls', 100));
	pg_assert_same('', pg_vitals_tone('cls', null));
	pg_assert_same('', pg_vitals_tone('ttfb', 10));
}

function test_visitor_insight_percent()
{
	pg_assert_same('-', pg_visitor_insight_percent(null));
	pg_assert_same(pg_format_number(83, 0) . '%', pg_visitor_insight_percent(0.8274));
}

function test_visitor_spark_points_scale_and_skip_gaps()
{
	pg_assert_same('', pg_visitor_spark_points(array(), 100, 50));
	pg_assert_same('', pg_visitor_spark_points(array(null, null), 100, 50));
	pg_assert_same('0,50 50,25 100,0', pg_visitor_spark_points(array(0, 5, 10), 100, 50));
	pg_assert_same('0,25 100,0', pg_visitor_spark_points(array(5, null, 10), 100, 50), 'a gap is skipped');
	pg_assert_same('0,40 100,35', pg_visitor_spark_points(array(4, 6), 100, 50, 20), 'the ceiling sets the top');
	pg_assert_same('50,0', pg_visitor_spark_points(array(3), 100, 50), 'one value sits in the middle');
}

function test_ai_fetch_summary_keeps_unverified_apart()
{
	$summary = pg_ai_fetch_summary(array(
		array('bot' => 'ChatGPT-User', 'kind' => 'agent', 'fetches' => 12),
		array('bot' => 'OAI-SearchBot', 'kind' => 'search', 'fetches' => 3),
		array('bot' => 'Claude-User', 'kind' => 'agent', 'fetches' => 0),
		array('bot' => 'unverified', 'kind' => 'unverified', 'fetches' => 40),
	));

	pg_assert_same(array('fetches' => 15, 'bots' => 2, 'unverified' => 40), $summary);
}

function test_dashboard_perf_vitals_tile()
{
	if (!function_exists('pg_dashboard_v2_perf_vitals_tile')) {
		if (!defined('PG_DASHBOARD_V2')) {
			define('PG_DASHBOARD_V2', true);
		}

		require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/perf.php');
	}

	pg_assert_same(null, pg_dashboard_v2_perf_vitals_tile(pg_vitals_figures(array())), 'nothing measured, no tile');

	$tile = pg_dashboard_v2_perf_vitals_tile(pg_vitals_figures(array('views' => 10, 'lcp_good' => 6, 'lcp_needs' => 3, 'lcp_poor' => 1, 'lcp_sum' => 25000)));

	pg_assert_same('Page load (p75)', $tile['label']);
	pg_assert_same(pg_vitals_tone('lcp', pg_vitals_p75('lcp', 6, 3, 1, 25000)), $tile['tone']);
	pg_assert_true(strpos($tile['note'], pg_visitor_insight_percent(0.6)) === 0, 'the good share leads the note: ' . $tile['note']);
}
