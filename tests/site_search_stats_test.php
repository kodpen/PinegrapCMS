<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the site search rollup (includes/fn/site_search_stats.php): the
 * term a search is kept under, the row key, the once-per-visit list, the
 * upsert, the signed click and its script, the retention day, and the places
 * that count. The runner has no connection: the table reads as missing and
 * nothing is written, so the call sites are read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function site_search_stats_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_site_search_term_is_folded_to_one_form()
{
	pg_assert_same('kahve', pg_site_search_term('Kahve'));
	pg_assert_same('kahve menü', pg_site_search_term("  KAHVE \t  menü\n"), 'spaces folded, case lowered');
	pg_assert_same('istanbul', pg_site_search_term('İstanbul'), 'a dotted capital İ is a plain i');
	pg_assert_same('ılık', pg_site_search_term('ılık'), 'a dotless ı stays');
	pg_assert_same('', pg_site_search_term('   '), 'nothing searched');
	pg_assert_same('', pg_site_search_term('*** ?'), 'no letter or digit');
	pg_assert_same('', pg_site_search_term(array('x')), 'an array is no term');
	pg_assert_same('42', pg_site_search_term(42));
}

function test_site_search_term_is_cut_to_the_kept_length()
{
	$term = pg_site_search_term(str_repeat('ş', 150));
	pg_assert_same(PG_SITE_SEARCH_TERM_MAX, mb_strlen($term, 'UTF-8'));
	pg_assert_same('abc', pg_site_search_term(str_repeat(' ', 5) . 'abc'));
	pg_assert_same(str_repeat('a', 99), pg_site_search_term(str_repeat('a', 99) . ' b'), 'no space left at the cut');
}

function test_site_search_key_is_sha1_of_day_and_term()
{
	pg_assert_same(sha1('2026-11-02|kahve'), pg_site_search_key('2026-11-02', 'kahve'));
	pg_assert_same(40, strlen(pg_site_search_key('2026-11-02', str_repeat('ğ', 100))));
	pg_assert_true(pg_site_search_key('2026-11-02', 'kahve') !== pg_site_search_key('2026-11-03', 'kahve'), 'a day of its own');
}

function test_site_search_seen_counts_a_key_once()
{
	list($already, $seen) = pg_site_search_seen_add(null, 'a');
	pg_assert_false($already);
	pg_assert_same(array('a'), $seen);

	list($already, $seen) = pg_site_search_seen_add($seen, 'a');
	pg_assert_true($already, 'the second search of the visit is not counted');
	pg_assert_same(array('a'), $seen);

	list($already, $seen) = pg_site_search_seen_add(array('a', 'b', 'c'), 'd', 3);
	pg_assert_false($already);
	pg_assert_same(array('b', 'c', 'd'), $seen, 'past the cap the oldest go');

	list($already, $seen) = pg_site_search_seen_add(array('a', 7, array()), 'b');
	pg_assert_same(array('a', 'b'), $seen, 'what is no key is dropped');
}

function test_site_search_upsert_raises_the_counters_of_one_row()
{
	$sql = pg_site_search_upsert_sql(1, 1, 0);
	pg_assert_contains('INSERT INTO site_search_daily (id, day, term, searches, zero_results, clicks) VALUES (?, ?, ?, 1, 1, 0)', $sql);
	pg_assert_contains('ON DUPLICATE KEY UPDATE searches = searches + 1, zero_results = zero_results + 1, clicks = clicks + 0', $sql);

	$sql = pg_site_search_upsert_sql(0, '-3', 'x');
	pg_assert_contains('VALUES (?, ?, ?, 0, 0, 0)', $sql, 'counts are whole and never negative');
}

function test_site_search_click_signature()
{
	$sig = pg_site_search_click_sign('kahve', 'secret');
	pg_assert_same(hash_hmac('sha256', 'search-click:kahve', 'secret'), $sig);
	pg_assert_same('', pg_site_search_click_sign('kahve', ''), 'no key, no signature');
	pg_assert_same('', pg_site_search_click_sign('', 'secret'));

	pg_assert_true(pg_site_search_click_verify('kahve', $sig, 'secret'));
	pg_assert_false(pg_site_search_click_verify('çay', $sig, 'secret'), 'another term');
	pg_assert_false(pg_site_search_click_verify('kahve', '', 'secret'));
	pg_assert_false(pg_site_search_click_verify('kahve', $sig, ''), 'a site without a key counts nothing');
	pg_assert_false(pg_site_search_click_verify(array('kahve'), $sig, 'secret'));
	// The page-view beacon's signature is no click signature.
	pg_assert_false(pg_site_search_click_verify('kahve', pg_page_cache_beacon_sign('kahve', 'secret'), 'secret'));
}

function test_site_search_click_script()
{
	pg_assert_same('', pg_site_search_click_script('kahve', '', array('/a'), '/pinegrap/page_view.php'), 'no signature');
	pg_assert_same('', pg_site_search_click_script('', 'sig', array('/a'), '/pinegrap/page_view.php'), 'no term');
	pg_assert_same('', pg_site_search_click_script('kahve', 'sig', array('', '#'), '/pinegrap/page_view.php'), 'no result to click');

	$script = pg_site_search_click_script('</script><b>', 'sig', array('/a', '/a', '/b?x=1&y=2', '#'), '/pinegrap/page_view.php');
	pg_assert_same(0, substr_count($script, '</script><b>'), 'nothing in the term ends the script');
	pg_assert_same(1, substr_count($script, '</script>'));
	pg_assert_contains('l=["/a","/b?x=1\\u0026y=2"]', $script, 'each result once, & escaped');
	pg_assert_contains('"/pinegrap/page_view.php"', $script);
	pg_assert_contains('d.append("sc",t);d.append("ss",s);d.append("u",h)', $script);
	pg_assert_contains('navigator.sendBeacon', $script);
}

function test_site_search_keep_from_is_400_days_back()
{
	$now = mktime(12, 0, 0, 11, 2, 2026);
	pg_assert_same('2025-09-28', pg_site_search_keep_from($now));
	pg_assert_same(400, PG_SITE_SEARCH_DAYS);
}

function test_site_search_is_counted_where_searches_and_clicks_happen()
{
	$widgets = site_search_stats_test_source('includes/fn/widgets.php');
	pg_assert_contains("if (\$query !== '') pg_site_search_count(\$query, \$total);", $widgets, 'the widget counts its first results page');
	pg_assert_contains('pg_site_search_click_script_for($query,', $widgets, 'the widget carries the click script');

	pg_assert_contains('pg_site_search_count($query, count($featured_items) + count($catalog_items) + count($results));',
		site_search_stats_test_source('get_search_results.php'), 'the legacy page counts');

	$beacon = site_search_stats_test_source('page_view.php');
	pg_assert_contains("pg_site_search_click_verify(\$_POST['sc']", $beacon);
	pg_assert_contains("pg_site_search_click(\$_POST['sc']", $beacon);
	pg_assert_true(strpos($beacon, "isset(\$_POST['sc'])") < strpos($beacon, 'pg_page_cache_beacon_verify('), 'a click is handled before the page view');

	pg_assert_contains('pg_site_search_sweep()', site_search_stats_test_source('job.php'));
	pg_assert_contains("'site_search_daily',", site_search_stats_test_source('install/index.php'), 'a reinstall drops the table');

	$migration = site_search_stats_test_source('includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_site_search_daily();      // 11.0.147', $migration);
	pg_assert_contains('KEY idx_day (day, searches)', $migration);
}

function test_site_search_counts_nothing_without_the_table()
{
	// The runner has no connection: the table reads as missing.
	pg_assert_false(pg_site_search_stats_ready());
	pg_assert_false(pg_site_search_count('kahve', 3));
	pg_assert_false(pg_site_search_click('kahve', '/a'));
	pg_assert_same('', pg_site_search_click_script_for('kahve', array('/a')));
	pg_assert_false(pg_site_search_sweep_due());
	pg_assert_same(0, pg_site_search_sweep());
}

function test_site_search_report_period()
{
	pg_assert_same(30, pg_site_search_period(null));
	pg_assert_same(7, pg_site_search_period('7'));
	pg_assert_same(30, pg_site_search_period('8'), 'only the offered periods');
	pg_assert_same(30, pg_site_search_period(array(7)));

	$now = mktime(12, 0, 0, 11, 2, 2026);
	pg_assert_same('2026-11-02', pg_site_search_period_start(1, $now), 'one day is today');
	pg_assert_same('2026-10-27', pg_site_search_period_start(7, $now), 'seven days end today');
	// The DST change of 25 October lies inside: days are stepped, not seconds.
	pg_assert_same('2026-10-04', pg_site_search_period_start(30, mktime(0, 30, 0, 11, 2, 2026)));
}

function test_site_search_report_sql_sums_rows()
{
	$summary = pg_site_search_summary_sql();
	pg_assert_contains('COALESCE(SUM(searches), 0) AS searches', $summary);
	pg_assert_contains('COUNT(DISTINCT term) AS terms', $summary);
	pg_assert_contains('WHERE day >= ?', $summary);

	$top = pg_site_search_terms_sql('top', 100);
	pg_assert_contains('SUM(searches) AS searches', $top);
	pg_assert_contains('GROUP BY term ORDER BY searches DESC, term ASC LIMIT 100', $top);
	pg_assert_false(strpos($top, 'HAVING') !== false);

	$zero = pg_site_search_terms_sql('zero', 5000);
	pg_assert_contains('HAVING SUM(zero_results) > 0 ORDER BY zero_results DESC, term ASC LIMIT 1000', $zero, 'capped at 1000');
}

function test_site_search_click_rate()
{
	pg_assert_same(null, pg_site_search_click_rate(3, 0));
	pg_assert_same(33.3, pg_site_search_click_rate(1, 3));
	pg_assert_same(150.0, pg_site_search_click_rate(3, 2), 'several results of one search can be clicked');
	pg_assert_same(0.0, pg_site_search_click_rate(-1, 2));
}

function test_site_search_report_screen()
{
	$screen = site_search_stats_test_source('visitor_search.php');
	pg_assert_contains('validate_visitors_access($user);', $screen);
	pg_assert_contains("\$may_tune = ((int) \$user['role'] < 3);", $screen, 'only managers get the promote button');
	pg_assert_contains("settings_features.php?search_promote=", $screen);

	$menu = site_search_stats_test_source('includes/fn/output.php');
	pg_assert_contains("'site_search' => array('visitor_search.php', 'bi-search', pg_site_search_stats_ready())", $menu, 'the Visitors menu opens it until the overview has the section');
	pg_assert_contains("case 'visitor_search.php':", $menu);
}
