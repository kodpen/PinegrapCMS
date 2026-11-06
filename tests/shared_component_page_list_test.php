<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page list of the editor's page pickers (shared_component
 * panel, list_all_pages): the system widgets a page carries are read from
 * the tree the page is drawn from, its own or else its design's, so a page
 * of a design whose tree places the sign-in widget is listed as a sign-in
 * page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function shared_component_page_list_test_case()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/panel/shared_component.php');
	$start = strpos($source, "case 'list_all_pages':");
	pg_assert_true($start !== false, 'the page list is found');

	return substr($source, $start, strpos($source, "case 'list_form_fields':") - $start);
}

function test_shared_component_page_list_reads_drawn_tree()
{
	$case = shared_component_page_list_test_case();

	pg_assert_true(strpos($case, 'pg_page_tree_sql_expr()') !== false, 'the widgets come from the tree the page is drawn from');
	pg_assert_true(strpos($case, 'LEFT JOIN style ON style.style_id = page.page_style') !== false, 'the design tree is joined, and a page without a design is still listed');
	pg_assert_true(strpos($case, "IF(page_tree_json LIKE") === false, 'the page\'s own column alone is not read');
}
