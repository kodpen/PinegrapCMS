<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two widget scans of pages: the member widget check reads the
 * page's tree with its style's fallback (pg_page_tree_sql_expr()), so a
 * registration widget on a style-held tree counts, and the search_results
 * widget's classic pages query leaves out the region rows of pages whose
 * content moved into their designer tree (pg_page_region_read_sql()), as
 * the legacy search results page does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function test_member_widget_published_reads_the_style_held_tree()
{
	$source = pg_test_function_source('pg_member_widget_published');

	pg_assert_contains('LEFT JOIN style ON style.style_id = page.page_style', $source, 'the style is joined, a page with its own tree kept when its style row is gone');
	pg_assert_contains('pg_page_tree_sql_expr()', $source, 'the tree comes with its style fallback');
	pg_assert_false(strpos($source, 'page.page_tree_json LIKE') !== false, 'the page column alone is not scanned');
}

function test_search_results_classic_pages_skip_unread_regions()
{
	$source = pg_test_function_source('_pg_sw_search_collect_one');

	pg_assert_contains("AND pregion.pregion_content LIKE \$like\n           AND \" . pg_page_region_read_sql('pregion.pregion_page')", $source, 'stale region rows of a tree page are left out');
}
