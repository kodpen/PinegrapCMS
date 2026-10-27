<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog helpers (includes/explorer/catalog.php): the moved
 * functions live there, behind the gate, and the explorer loads them.
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
function test_explorer_catalog_lives_in_its_own_file()
{
	$catalog = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/catalog.php');
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	foreach (array('pg_catalog_access', 'pg_catalog_recycle_ready', 'pg_catalog_live_filter', 'pg_catalog_group_map', 'pg_catalog_child_groups', 'pg_catalog_subtree_ids', 'pg_catalog_recycled_subtree_ids', 'pg_catalog_group_stats', 'pg_catalog_direct_product_count', 'pg_catalog_breadcrumb', 'pg_catalog_breadcrumb_trail', 'pg_catalog_group_role', 'pg_catalog_group_item', 'pg_catalog_product_item', 'pg_catalog_attach_memberships', 'pg_catalog_seo_payload') as $function) {
		pg_assert_contains('function ' . $function . '(', $catalog, $function);
		pg_assert_false(strpos($explorer, 'function ' . $function . '(') !== false, $function . ' not defined twice');
		pg_assert_true(function_exists($function), $function . ' loaded');
	}

	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $catalog, 'gate');
	pg_assert_false(strpos($catalog, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/catalog.php');", $explorer, 'loaded by the explorer');
}
