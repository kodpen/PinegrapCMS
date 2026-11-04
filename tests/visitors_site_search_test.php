<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Site Search section of the Visitors overview
 * (includes/visitors/site_search.php) and its drawing
 * (pg_site_search_report()): tiles, tables, escaping, the empty period, the
 * promote button for managers only, and the old screen's period kept.
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
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/site_search.php');

function test_visitors_site_search_report_draws_figures()
{
	$html = pg_site_search_report(array(
		'summary' => array('searches' => 1200, 'zero_results' => 30, 'clicks' => 600, 'terms' => 80),
		'top'     => array(array('term' => '<b>kahve</b>', 'searches' => 40, 'zero_results' => 0, 'clicks' => 20)),
		'zero'    => array(array('term' => 'çay', 'searches' => 5, 'zero_results' => 5, 'clicks' => 0)),
	), false);

	pg_assert_contains('&lt;b&gt;kahve&lt;/b&gt;', $html, 'a term is escaped');
	pg_assert_false(strpos($html, '<b>kahve') !== false);
	pg_assert_contains('data-sort="40"', $html);
	pg_assert_contains('çay', $html);
	pg_assert_contains('<td class="text-end">' . h(pg_visitors_percent(50, 1)) . '</td>', $html, 'through the language\'s percent form');
	pg_assert_contains(h(lang('Most searched terms')), $html);
	pg_assert_false(strpos($html, 'search_promote') !== false, 'no promote button without the right');
	pg_assert_false(strpos($html, h(lang('Nothing was searched for in this period.'))) !== false);
	pg_assert_false(strpos($html, 'pg-visitors-more') !== false, 'a short list shows whole');
}

function test_visitors_site_search_report_folds_long_lists()
{
	$top = array();

	for ($i = 1; $i <= 40; $i++) {
		$top[] = array('term' => 'term ' . $i, 'searches' => 100 - $i, 'zero_results' => 0, 'clicks' => 1);
	}

	$html = pg_site_search_report(array('summary' => array('searches' => 4000), 'top' => $top, 'zero' => array()), true);

	pg_assert_same(40 - PG_VISITORS_LIST_ROWS, substr_count($html, 'class="pg-visitors-extra"'), 'only the first rows show at first');
	pg_assert_contains(h(lang(array('string' => 'Show all ({var:1})', 'vars' => array(pg_format_number(40, 0))))), $html);
}

function test_visitors_site_search_report_empty_period()
{
	$html = pg_site_search_report(array('summary' => array('searches' => 0, 'zero_results' => 0, 'clicks' => 0, 'terms' => 0), 'top' => array(), 'zero' => array()), false);

	pg_assert_contains(h(lang('Nothing was searched for in this period.')), $html);
	pg_assert_contains(h(lang('Every search in this period found something.')), $html);
	pg_assert_false(strpos($html, '<table') !== false);

	// Clicks per search reads '-' when nothing was searched, never 0 %.
	pg_assert_contains('<div class="pg-visitors-tile-value">-</div>', pg_site_search_report(array(), false));
}

function test_visitors_site_search_old_link_keeps_period()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	pg_assert_same(array('p' => '7d'), pg_site_search_overview_query('7', $now));
	pg_assert_same(array('p' => '30d'), pg_site_search_overview_query(null, $now), 'the old default');
	pg_assert_same(array('p' => '90d'), pg_site_search_overview_query('90', $now));
	pg_assert_same(array('p' => 'custom', 'from' => '2025-11-05', 'to' => '2026-11-04'), pg_site_search_overview_query('365', $now), 'a year has no preset');
}

function test_visitors_site_search_section_source()
{
	$section = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/visitors/site_search.php');
	pg_assert_contains("if (!pg_site_search_stats_ready()) {\n        return '';", $section, 'nothing before the table exists');
	pg_assert_contains("\$may_tune = ((int) \$user['role'] < 3);", $section, 'only managers get the promote button');
	pg_assert_contains("pg_site_search_figures(\$period['from'], \$period['to'])", $section);

	$fn = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/site_search_stats.php');
	pg_assert_contains("settings_features.php?search_promote=' . rawurlencode((string) \$row['term'])", $fn);
}
