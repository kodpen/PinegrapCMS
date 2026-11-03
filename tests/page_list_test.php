<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pages list (includes/fn/page_list.php): the style each
 * folder hands down to its pages, for both device types.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Behind their gates these files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_list.php');

function test_page_list_folder_takes_nearest_parent_style()
{
	$folders = array(
		array('folder_id' => '1', 'folder_parent' => '0', 'folder_level' => '0', 'folder_style' => '7', 'mobile_style_id' => '0'),
		array('folder_id' => '2', 'folder_parent' => '1', 'folder_level' => '1', 'folder_style' => '0', 'mobile_style_id' => '9'),
		array('folder_id' => '3', 'folder_parent' => '2', 'folder_level' => '2', 'folder_style' => '0', 'mobile_style_id' => '0'),
		array('folder_id' => '4', 'folder_parent' => '1', 'folder_level' => '1', 'folder_style' => '5', 'mobile_style_id' => '0'),
	);

	pg_assert_same(array(1 => 7, 2 => 7, 3 => 7, 4 => 5), pg_page_list_folder_styles($folders, 'desktop'));
	pg_assert_same(array(1 => 0, 2 => 9, 3 => 9, 4 => 0), pg_page_list_folder_styles($folders, 'mobile'));
}

function test_page_list_broken_folder_chain_has_no_style()
{
	$folders = array(
		// A parent that does not exist and a loop between two folders.
		array('folder_id' => '5', 'folder_parent' => '99', 'folder_level' => '1', 'folder_style' => '0', 'mobile_style_id' => '0'),
		array('folder_id' => '6', 'folder_parent' => '7', 'folder_level' => '1', 'folder_style' => '0', 'mobile_style_id' => '0'),
		array('folder_id' => '7', 'folder_parent' => '6', 'folder_level' => '1', 'folder_style' => '0', 'mobile_style_id' => '0'),
	);

	pg_assert_same(array(5 => 0, 6 => 0, 7 => 0), pg_page_list_folder_styles($folders, 'desktop'));
}
