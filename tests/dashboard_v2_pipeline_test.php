<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's pipeline card
 * (includes/dashboard/v2/cards/pipeline.php): the preset's line, the period
 * and its eight spans, the span sums and the step boxes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_DASHBOARD_V2')) {
	define('PG_DASHBOARD_V2', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/pipeline.php');

function test_dashboard_v2_pipeline_preset_falls_back_to_the_first_default()
{
	pg_assert_same('blog', pg_dashboard_v2_pipeline_preset('blog', true), 'a preset with a line keeps it');
	pg_assert_same('ecommerce', pg_dashboard_v2_pipeline_preset('simple', true), 'Simple takes the shop line with the shop on');
	pg_assert_same('corporate', pg_dashboard_v2_pipeline_preset('simple', false), 'Simple takes the request line without the shop');
	pg_assert_same('corporate', pg_dashboard_v2_pipeline_preset(null, false), 'nothing stored');

	foreach (pg_dashboard_v2_pipeline_presets() as $key => $line) {
		pg_assert_same(4, count($line['steps']), $key . ' has four steps');
		foreach ($line['steps'] as $step) {
			pg_assert_true(isset(pg_dashboard_v2_pipeline_gates()[$step]), $key . ' step ' . $step . ' has a gate');
		}
	}
}

function test_dashboard_v2_pipeline_window_cuts_the_period_in_eight()
{
	$now = mktime(15, 0, 0, 3, 10, 2026);
	$today = pg_dashboard_v2_pipeline_window(0, 0, $now);

	pg_assert_same(mktime(0, 0, 0, 3, 10, 2026), $today['start'], 'today starts at midnight');
	pg_assert_same(9, count($today['edges']), 'nine edges');
	pg_assert_same($now, $today['edges'][8], 'the last edge is now');
	pg_assert_same($today['start'] + 6750, $today['edges'][1], 'equal spans: 15 hours in eight');
	pg_assert_same(array($today['start'] - 15 * 3600, $today['start']), $today['before'], 'the previous period is as long');

	$month = pg_dashboard_v2_pipeline_window(2, 1, $now);
	pg_assert_same(mktime(0, 0, 0, 2, 9, 2026), $month['start'], '30 days count today');
	pg_assert_same(array(mktime(0, 0, 0, 2, 9, 2025), mktime(15, 0, 0, 3, 10, 2025)), $month['before'], 'last year');

	pg_assert_same(null, pg_dashboard_v2_pipeline_window(1, 2, $now)['before'], 'compare off');
}

function test_dashboard_v2_pipeline_sums_sql_leaves_the_last_span_open()
{
	$sql = pg_dashboard_v2_pipeline_sums_sql('t.c', array(10, 20, 30));

	pg_assert_same('SUM(CASE WHEN t.c >= 10 AND t.c < 20 THEN 1 ELSE 0 END) AS s0, SUM(CASE WHEN t.c >= 20 THEN 1 ELSE 0 END) AS s1', $sql, 'two spans');
}

function test_dashboard_v2_pipeline_hours_fall_in_their_span()
{
	$start = mktime(0, 0, 0, 3, 10, 2026);
	$edges = array();
	for ($i = 0; $i <= 8; $i++) {
		$edges[] = $start + $i * 3 * 3600;
	}

	$stats = array(
		'2026-03-09' => array(23 => array('visitors' => 50)),
		'2026-03-10' => array(
			0  => array('visitors' => 1, 'page_views' => 4),
			2  => array('visitors' => 2),
			3  => array('visitors' => 5),
			23 => array('visitors' => 7),
		),
	);

	pg_assert_same(array(3, 5, 0, 0, 0, 0, 0, 7), pg_dashboard_v2_pipeline_hours($stats, $edges, 'visitors'), 'the day before is left out');
	pg_assert_same(array(4, 0, 0, 0, 0, 0, 0, 0), pg_dashboard_v2_pipeline_hours($stats, $edges, 'page_views'), 'a missing column counts nothing');
}

function test_dashboard_v2_pipeline_box_and_flag()
{
	$box = pg_dashboard_v2_pipeline_box('forms', 'Forms', array(1, '2', 0, 4), 'note', 'list.php',
		pg_dashboard_v2_pipeline_flag(1, '{var:1} waiting for approval', 'warn'));

	pg_assert_same('7', $box['value'], 'the figure is the sum of the points');
	pg_assert_same(array(1, 2, 0, 4), $box['points'], 'points are numbers');
	pg_assert_same(array('text' => '1 waiting for approval', 'tone' => 'warn'), $box['flag'], 'the flag');

	pg_assert_same(null, pg_dashboard_v2_pipeline_flag(0, '{var:1} opened', 'warn'), 'no flag for nothing');
	pg_assert_same('2 refund requests', pg_dashboard_v2_pipeline_flag(2, '{var:1} refund request{suffix:1}', 'bad')['text'], 'plural');
	pg_assert_same('12', pg_dashboard_v2_pipeline_box('x', 'X', array(1), '', '', null, 12)['value'], 'a figure of its own');
}
