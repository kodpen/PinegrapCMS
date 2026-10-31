<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the paging of the panel search box (pg_panel_search_page(),
 * pg_panel_search_compare()): every result has one fixed place (score,
 * then type, then id), so two Load More pages never share a result and
 * together are the best results, whatever order the queries returned.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The file only defines the panel actions; its gate is the dispatcher's.
if (!defined('PG_PANEL_ACTIONS')) {
	define('PG_PANEL_ACTIONS', true);
}
require_once(PG_FUNCTIONS_DIR . '/includes/panel/search.php');

function panel_search_paging_rows()
{
	$rows = array();
	// 30 pages that contain the word, the exact one 25th by id; equal
	// scores across types so only the tie-break decides their order.
	for ($id = 1; $id <= 30; $id++) {
		$rows[] = array('type' => 'page', 'id' => $id, 'score' => $id === 25 ? 100 : 30);
	}
	for ($id = 1; $id <= 15; $id++) {
		$rows[] = array('type' => 'file', 'id' => $id, 'score' => $id % 3 === 0 ? 60 : 30);
		$rows[] = array('type' => 'product', 'id' => $id, 'score' => 30);
	}
	return $rows;
}

function panel_search_paging_keys($rows)
{
	return array_map(function ($r) { return $r['type'] . ':' . $r['id']; }, $rows);
}

function test_panel_search_page_slices_are_disjoint_and_complete()
{
	$rows = panel_search_paging_rows();
	mt_srand(7);
	shuffle($rows);
	$first = pg_panel_search_page($rows, 0, 20);
	$reversed = pg_panel_search_page(array_reverse($rows), 20, 20);
	pg_assert_true($first['has_more'], 'more after the first page');
	pg_assert_same('page:25', $first['results'][0]['type'] . ':' . $first['results'][0]['id'], 'exact match first');
	$a = panel_search_paging_keys($first['results']);
	$b = panel_search_paging_keys($reversed['results']);
	pg_assert_same(array(), array_values(array_intersect($a, $b)), 'no result on two pages');
	$top = pg_panel_search_page($rows, 0, 40);
	pg_assert_same(panel_search_paging_keys($top['results']), array_merge($a, $b), 'two pages are the top 40');
	$last = pg_panel_search_page($rows, 40, 20);
	pg_assert_same(20, count($last['results']), 'the rest');
	pg_assert_true(!$last['has_more'], 'nothing after the last page');
}

function test_panel_search_compare_breaks_ties_by_type_then_id()
{
	$rows = array(
		array('type' => 'product', 'id' => 2, 'score' => 30),
		array('type' => 'page', 'id' => 9, 'score' => 30),
		array('type' => 'product', 'id' => 1, 'score' => 30),
		array('type' => 'page', 'id' => 3, 'score' => 30),
		array('type' => 'file', 'id' => 1, 'score' => 60),
	);
	usort($rows, 'pg_panel_search_compare');
	pg_assert_same(array('file:1', 'page:3', 'page:9', 'product:1', 'product:2'), panel_search_paging_keys($rows), 'score, type, id');
	pg_assert_same(0, pg_panel_search_compare($rows[1], $rows[1]), 'equal to itself');
}

function test_panel_search_compare_ranks_unknown_type_last()
{
	$a = array('type' => 'zzz', 'id' => 1, 'score' => 30);
	$b = array('type' => 'short_link', 'id' => 5, 'score' => 30);
	pg_assert_true(pg_panel_search_compare($a, $b) > 0, 'unknown type after the known ones');
}

function test_panel_search_reads_a_fixed_cap_best_first()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/search.php');
	pg_assert_contains('$per_limit = PG_PANEL_SEARCH_PER_TYPE;', $php, 'limit does not grow with the offset');
	pg_assert_same(17, substr_count($php, 'LIMIT $per_limit'), 'every type reads the cap');
	pg_assert_same(16, substr_count($php, '" . $rank('), 'every type but orders ordered by $rank');
	pg_assert_contains("ORDER BY CASE WHEN orders.order_number = '\$s_order_exact'", $php, 'orders ranked by number first');
}
