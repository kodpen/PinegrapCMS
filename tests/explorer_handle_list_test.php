<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's listing sub-action (includes/explorer/handle_list.php):
 * the handlers live there, behind the gate, pg_explorer_handle() calls the
 * listing, the flat view's filter and the view type are read as before, and
 * a recycle bin entry carries its days left and the folder it came from.
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

// The listing is loaded by the explorer's functions and called from its
// case in pg_explorer_handle(); the flat views are its own handler.
function test_explorer_list_handler_lives_in_its_own_file()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$handler = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_list.php');

	$call = 'pg_explorer_handle_list($type, $request, $user, $folders_that_user_has_access_to)';

	foreach (array('pg_explorer_handle_list', 'pg_explorer_handle_list_all_files') as $function) {
		pg_assert_true(function_exists($function), $function . ' loaded');
		pg_assert_false(strpos($explorer, 'function ' . $function . '(') !== false, $function . ' not defined twice');
	}

	pg_assert_contains('function ' . $call, $handler, 'pg_explorer_handle_list');
	pg_assert_contains("case 'explorer_list':\n            " . $call . ";\n            break;", $explorer, 'explorer_list calls it');
	pg_assert_contains("pg_explorer_handle_list_all_files(\$type, \$request, \$user, \$folders_that_user_has_access_to);", $handler, 'the flat views');
	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handler, 'gate');
	pg_assert_false(strpos($handler, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_same(2, substr_count($handler, "require_once(PG_FUNCTIONS_DIR . '/seo.php');"), 'seo.php from the product root');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_list.php');", $explorer, 'loaded by the explorer');
}

// Grid and list are kept; anything else is the grid.
function test_explorer_list_view_type()
{
	pg_assert_same('grid', pg_explorer_list_view_type('grid'), 'grid');
	pg_assert_same('list', pg_explorer_list_view_type('list'), 'list');
	pg_assert_same('grid', pg_explorer_list_view_type('table'), 'unknown');
	pg_assert_same('grid', pg_explorer_list_view_type(null), 'none');
}

// Pictures and pages drop the scope; a scope outside the known list is
// dropped too.
function test_explorer_list_filter()
{
	pg_assert_same(array('images_only' => false, 'pages_only' => false, 'scope' => ''), pg_explorer_list_filter(array()), 'nothing asked');
	pg_assert_same(array('images_only' => false, 'pages_only' => false, 'scope' => 'media'), pg_explorer_list_filter(array('file_scope' => 'media')), 'a scope');
	pg_assert_same(array('images_only' => false, 'pages_only' => false, 'scope' => ''), pg_explorer_list_filter(array('file_scope' => 'nope')), 'an unknown scope');
	pg_assert_same(array('images_only' => true, 'pages_only' => false, 'scope' => ''), pg_explorer_list_filter(array('file_filter' => 'images', 'file_scope' => 'media')), 'pictures');
	pg_assert_same(array('images_only' => false, 'pages_only' => true, 'scope' => ''), pg_explorer_list_filter(array('file_filter' => 'pages', 'file_scope' => 'media')), 'pages');
}

// Days left count whole days since the delete and never go below zero; the
// origin is named only while its folder exists.
function test_explorer_bin_entry()
{
	$map = array(7 => array('folder_id' => 7, 'folder_name' => 'Photos'));
	$now = 1700000000;

	pg_assert_same(
		array('deleted_at' => $now - 86400 * 3 - 5, 'days_left' => 27, 'origin_name' => 'Photos'),
		pg_explorer_bin_entry(array('deleted_at' => (string) ($now - 86400 * 3 - 5), 'original_parent_id' => '7'), 30, $map, $now),
		'three days in');

	pg_assert_same(
		array('deleted_at' => $now - 86400 * 40, 'days_left' => 0, 'origin_name' => ''),
		pg_explorer_bin_entry(array('deleted_at' => $now - 86400 * 40, 'original_parent_id' => 9), 30, $map, $now),
		'past retention, folder gone');
}
