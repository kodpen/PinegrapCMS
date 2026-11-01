<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard Performance card: the error-response buckets of perf_stats
 * ("[404]", "[410]") are not pages, so the grade and the slowest page come
 * from real pages only; the list names a bucket as error responses.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_dashboard_perf_status_label()
{
	pg_assert_true(pg_perf_is_status_label('[410]'), 'a status bucket is recognised');
	pg_assert_true(pg_perf_is_status_label('[404]'), 'a 404 bucket is recognised');
	pg_assert_false(pg_perf_is_status_label('[home]'), 'the home page label is a page');
	pg_assert_false(pg_perf_is_status_label('/blog/[410]'), 'a URL holding brackets is a page');
	pg_assert_false(pg_perf_is_status_label('/'), 'a URL is a page');
}

function test_dashboard_perf_label_display()
{
	pg_assert_same('/shop', pg_perf_label_display('/shop'), 'a page label is shown as it is');
	pg_assert_contains('410', pg_perf_label_display('[410]'), 'a bucket keeps its status code');
	pg_assert_false(strpos(pg_perf_label_display('[410]'), '[410]') !== false, 'a bucket is not shown as a bare [410]');
}

function test_dashboard_perf_worst_page_skips_buckets()
{
	$widget = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/dashboard/widgets/widget_23.php');
	preg_match_all('/SELECT label, SUM\(hits\) AS hits, MIN\(min_ms\) AS floor_ms.*?LIMIT 1/s', $widget, $queries);

	pg_assert_same(2, count($queries[0]), 'both slowest-page queries are found');

	foreach ($queries[0] as $query) {
		pg_assert_contains("label NOT LIKE '[___]'", $query, 'the slowest-page query leaves the status buckets out');
	}
}
