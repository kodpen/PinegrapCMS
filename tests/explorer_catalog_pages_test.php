<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog page and copy helpers
 * (includes/explorer/catalog_pages.php): the moved functions live there,
 * behind the gate, and the explorer loads them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// The helpers live in their own file, loaded by the explorer's functions.
function test_explorer_catalog_pages_live_in_their_own_file()
{
	$pages = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/catalog_pages.php');
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	foreach (array('pg_catalog_widget_pages', 'pg_catalog_pages_for_group', 'pg_catalog_pages_for_product', 'pg_catalog_search_pages_for_groups', 'pg_catalog_rebuild_search_tag_clouds', 'pg_catalog_copy_group') as $function) {
		pg_assert_contains('function ' . $function . '(', $pages, $function);
		pg_assert_false(strpos($explorer, 'function ' . $function . '(') !== false, $function . ' not defined twice');
		pg_assert_true(function_exists($function), $function . ' loaded');
	}

	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $pages, 'gate');
	pg_assert_false(strpos($pages, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/catalog_pages.php');", $explorer, 'loaded by the explorer');
}
