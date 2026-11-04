<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Visitors overview's Google search section
 * (includes/visitors/google.php): the window that ends on the latest day
 * with figures, the median, the two opportunity rules and the link to a
 * search in Search Console.
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
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/google.php');

function visitors_google_test_query($name, $clicks, $impressions, $position)
{
	return array(
		'name'        => $name,
		'clicks'      => $clicks,
		'impressions' => $impressions,
		'ctr'         => pg_gsc_ctr($clicks, $impressions),
		'position'    => (float) $position,
	);
}

function test_visitors_google_window_ends_on_the_latest_day()
{
	$period = array('from' => '2026-10-29', 'to' => '2026-11-04', 'days' => 7);

	$window = pg_visitors_google_window($period, '2026-11-01');
	pg_assert_same('2026-10-26', $window['from'], 'seven days ending on the latest day');
	pg_assert_same('2026-11-01', $window['to']);
	pg_assert_same('2026-10-19', $window['previous_from']);
	pg_assert_same('2026-10-25', $window['previous_to']);
	pg_assert_true($window['early']);

	$past = pg_visitors_google_window(array('from' => '2026-09-01', 'to' => '2026-09-30', 'days' => 30), '2026-11-01');
	pg_assert_same('2026-09-01', $past['from'], 'a period before the latest day is read as it is');
	pg_assert_same('2026-09-30', $past['to']);
	pg_assert_same('2026-08-02', $past['previous_from']);
	pg_assert_true(!$past['early']);

	$today = pg_visitors_google_window(array('from' => '2026-11-04', 'to' => '2026-11-04', 'days' => 1), '2026-11-01');
	pg_assert_same('2026-11-01', $today['from'], 'today reads the latest day alone');
	pg_assert_same('2026-11-01', $today['to']);
	pg_assert_same('2026-10-31', $today['previous_to']);
}

function test_visitors_google_median()
{
	pg_assert_same(0.0, pg_visitors_google_median(array()));
	pg_assert_same(5.0, pg_visitors_google_median(array(9, 5, 1)));
	pg_assert_same(4.0, pg_visitors_google_median(array(10, 2, 6, 1)));
}

function test_visitors_google_opportunities_low_click_rate()
{
	// The site clicks at 10 %; half of it is 5 %. Median of the impressions: 300.
	$queries = array(
		visitors_google_test_query('shoes', 80, 1000, 3),       // 8 %: not low
		visitors_google_test_query('red shoes', 10, 900, 4),    // 1.1 %: low
		visitors_google_test_query('boots', 2, 500, 5),         // 0.4 %: low
		visitors_google_test_query('sandals', 1, 300, 6),       // at the median: not above it
		visitors_google_test_query('laces', 0, 100, 2),         // under the median
		visitors_google_test_query('socks', 0, 50, 2),
	);

	$chances = pg_visitors_google_opportunities($queries, 0.10);
	pg_assert_same(array('red shoes', 'boots'), array_column($chances['low_ctr'], 'name'), 'most impressions first');

	$none = pg_visitors_google_opportunities($queries, 0.0);
	pg_assert_same(array(), $none['low_ctr'], 'no click rate on the site: nothing is under half of it');
}

function test_visitors_google_opportunities_close_to_the_first_page()
{
	$queries = array(
		visitors_google_test_query('a', 5, 100, 7.9),
		visitors_google_test_query('b', 1, 200, 8),
		visitors_google_test_query('c', 0, 400, 14.5),
		visitors_google_test_query('d', 0, 300, 20),
		visitors_google_test_query('e', 0, 900, 20.1),
		visitors_google_test_query('f', 0, 0, 12),
	);

	$chances = pg_visitors_google_opportunities($queries, 0.5);
	pg_assert_same(array('c', 'd', 'b'), array_column($chances['near'], 'name'), 'positions 8 to 20 with impressions, most impressions first');

	$limited = pg_visitors_google_opportunities($queries, 0.5, 2);
	pg_assert_same(2, count($limited['near']));
}

function test_visitors_google_query_url()
{
	$url = pg_visitors_google_query_url('sc-domain:example.com', 'kırmızı ayakkabı & çanta', '2026-10-26', '2026-11-01');

	pg_assert_contains('https://search.google.com/search-console/performance/search-analytics?', $url);
	pg_assert_contains('resource_id=sc-domain%3Aexample.com', $url);
	pg_assert_contains('query=%2Ak%C4%B1rm%C4%B1z%C4%B1%20ayakkab%C4%B1%20%26%20%C3%A7anta', $url, 'the query encoded, a space as %20');
	pg_assert_contains('start_date=20261026', $url);
	pg_assert_contains('end_date=20261101', $url);
}

function test_visitors_google_query_cell_escapes()
{
	$cell = pg_visitors_google_query_cell(visitors_google_test_query('<b>x</b>', 1, 2, 3), 'https://example.com/', array('from' => '2026-11-01', 'to' => '2026-11-01'));

	pg_assert_contains('&lt;b&gt;x&lt;/b&gt;', $cell['html']);
	pg_assert_true(strpos($cell['html'], '<b>') === false, 'the query is never printed raw');
	pg_assert_contains('rel="noopener"', $cell['html']);
}
