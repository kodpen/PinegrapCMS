<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page speed figures (includes/fn/page_vitals.php): the p75
 * estimate from buckets and sums against known distributions, the figures of
 * a summed row, the page order and the window's WHERE. The runner has no
 * connection, so the reading functions answer empty and the route is read
 * from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * Buckets and sum of a list of values, as the rollup would hold them, and the
 * true 75th percentile (nearest rank).
 */
function vitals_figures_test_rollup($metric, array $values)
{
	$row = array('good' => 0, 'needs' => 0, 'poor' => 0, 'sum' => 0);

	foreach ($values as $value) {
		$row[pg_vitals_bucket($metric, $value)]++;
		$row['sum'] += $value;
	}

	sort($values);
	$row['true'] = $values[(int) ceil(0.75 * count($values)) - 1];

	return $row;
}

function vitals_figures_test_estimate($metric, array $values)
{
	$row = vitals_figures_test_rollup($metric, $values);

	return array(pg_vitals_p75($metric, $row['good'], $row['needs'], $row['poor'], $row['sum']), $row['true']);
}

function test_vitals_p75_is_close_on_a_spread_lcp()
{
	// LCP evenly from 500 ms to 6500 ms: p75 is 5000.
	$values = array();

	for ($v = 500; $v <= 6500; $v += 10) {
		$values[] = $v;
	}

	list($estimate, $true) = vitals_figures_test_estimate('lcp', $values);

	pg_assert_true(abs($estimate - $true) <= 0.1 * $true, 'within 10% of ' . $true . ', got ' . $estimate);
	pg_assert_same(pg_vitals_bucket('lcp', $true), pg_vitals_bucket('lcp', $estimate), 'the same rating as the truth');
}

function test_vitals_p75_is_close_on_a_skewed_lcp()
{
	// A long-tailed distribution: most pages fast, a few very slow.
	$values = array();

	for ($i = 0; $i < 1000; $i++) {
		$u = ($i + 0.5) / 1000;
		$values[] = (int) round(800 + 1200 * (-log(1 - $u)));
	}

	list($estimate, $true) = vitals_figures_test_estimate('lcp', $values);

	pg_assert_true(abs($estimate - $true) <= 0.15 * $true, 'within 15% of ' . $true . ', got ' . $estimate);
	pg_assert_same(pg_vitals_bucket('lcp', $true), pg_vitals_bucket('lcp', $estimate), 'the same rating as the truth');
}

function test_vitals_p75_inside_one_bucket_reads_the_mean()
{
	// Every INP good and around 60 ms: not the 150 a blind spread over 0-200 gives.
	$values = array();

	for ($v = 20; $v <= 100; $v++) {
		$values[] = $v;
	}

	list($estimate, $true) = vitals_figures_test_estimate('inp', $values);

	pg_assert_true(abs($estimate - $true) <= 10, 'within 10 ms of ' . $true . ', got ' . $estimate);

	// Every CLS needing work, around 0.17.
	list($estimate, $true) = vitals_figures_test_estimate('cls', range(120, 220));
	pg_assert_true(abs($estimate - $true) <= 15, 'within 0.015 of ' . $true . ', got ' . $estimate);
}

function test_vitals_p75_in_the_poor_bucket_uses_the_sum()
{
	// Mostly very slow pages: 8-12 s.
	$values = array_merge(array_fill(0, 10, 1500), range(8000, 12000, 50));

	list($estimate, $true) = vitals_figures_test_estimate('lcp', $values);

	pg_assert_true(abs($estimate - $true) <= 800, 'within 800 ms of ' . $true . ', got ' . $estimate);
	pg_assert_true($estimate > 4000, 'poor');
}

function test_vitals_p75_edges()
{
	pg_assert_same(null, pg_vitals_p75('lcp', 0, 0, 0, 0), 'no samples');
	pg_assert_same(null, pg_vitals_p75('fid', 1, 0, 0, 10), 'an unknown figure');
	pg_assert_same(0, pg_vitals_p75('cls', 10, 0, 0, 0), 'no shift at all');
	pg_assert_same(2500, pg_vitals_p75('lcp', 75, 25, 0, 75 * 1250 + 25 * 3250), 'the rank on the good edge is the threshold');
}

function test_vitals_figures_of_a_row()
{
	$figures = pg_vitals_figures(array(
		'views' => 10,
		'lcp_sum' => 20000, 'lcp_good' => 8, 'lcp_needs' => 2, 'lcp_poor' => 0,
		'inp_sum' => 0, 'inp_good' => 0, 'inp_needs' => 0, 'inp_poor' => 0,
		'cls_sum' => 500, 'cls_good' => 10, 'cls_needs' => 0, 'cls_poor' => 0,
	));

	pg_assert_same(10, $figures['views']);
	pg_assert_same(10, $figures['lcp']['samples']);
	pg_assert_same(2000, $figures['lcp']['mean']);
	pg_assert_same(0.8, $figures['lcp']['good_share']);
	pg_assert_same(0.2, $figures['lcp']['needs_share']);
	pg_assert_same(0.0, $figures['lcp']['poor_share']);
	pg_assert_same(array('samples' => 0, 'p75' => null, 'mean' => null, 'good_share' => null, 'needs_share' => null, 'poor_share' => null), $figures['inp'], 'nobody interacted');
	pg_assert_same(1.0, $figures['cls']['good_share']);
	pg_assert_same(0, pg_vitals_figures(array())['views'], 'an empty window');
}

function test_vitals_pages_sort_slowest_first()
{
	$page = function ($id, $views, $lcp) {
		return array('page_id' => $id, 'page_name' => 'p' . $id, 'views' => $views, 'lcp' => array('p75' => $lcp), 'inp' => array('p75' => null), 'cls' => array('p75' => 0));
	};

	$sorted = pg_vitals_sort_pages(array($page(1, 5, 1200), $page(2, 9, null), $page(3, 2, 4800), $page(4, 7, 1200)), 'lcp');
	pg_assert_same(array(3, 4, 1, 2), array_column($sorted, 'page_id'), 'slowest first, a tie to more beacons, no LCP last');

	$sorted = pg_vitals_sort_pages(array($page(1, 5, 1200), $page(2, 9, null), $page(3, 2, 4800)), 'views');
	pg_assert_same(array(2, 1, 3), array_column($sorted, 'page_id'), 'by beacons');

	$sorted = pg_vitals_sort_pages(array($page(1, 5, 1200), $page(3, 2, 4800)), 'bogus');
	pg_assert_same(array(3, 1), array_column($sorted, 'page_id'), 'an unknown order is LCP');
}

function test_vitals_where_of_a_window()
{
	pg_assert_same("day BETWEEN '2026-10-01' AND '2026-10-31'", pg_vitals_where_sql('2026-10-01', '2026-10-31'));
	pg_assert_same("day BETWEEN '2026-10-01' AND '2026-10-31' AND device = 'mobile'", pg_vitals_where_sql('2026-10-01', '2026-10-31', 'mobile'));
	pg_assert_same("day BETWEEN '2026-10-01' AND '2026-10-31'", pg_vitals_where_sql('2026-10-01', '2026-10-31', "x' OR 1"), 'an unknown device is every device');
	pg_assert_same('', pg_vitals_where_sql("2026-10-01' OR 1=1 --", '2026-10-31'), 'not a date');
}

function test_vitals_route_is_declared()
{
	$route = null;

	foreach (api_schema() as $row) {
		if (($row['id'] ?? '') === 'reports.vitals') {
			$route = $row;
		}
	}

	pg_assert_true(is_array($route), 'reports.vitals is a route');
	pg_assert_same('/reports/vitals', $route['path']);
	pg_assert_same('visitors:read', $route['scope']);
	pg_assert_same('VitalsReport', $route['returns']);
}
