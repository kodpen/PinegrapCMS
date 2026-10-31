<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's "Where used" sub-action handler
 * (includes/explorer/handle_file_usage.php): the handler lives there, behind
 * the gate, pg_explorer_handle() calls it, and the settings that name the
 * file outright are matched whole.
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

// The handler is loaded by the explorer's functions and called from
// pg_explorer_handle() with the folders the person may see.
function test_explorer_file_usage_handler_lives_in_its_own_file()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$handler = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_usage.php');

	pg_assert_contains('function pg_explorer_handle_file_usage(', $handler, 'handler');
	pg_assert_true(function_exists('pg_explorer_handle_file_usage'), 'handler loaded');
	pg_assert_contains("case 'explorer_file_usage':\n            pg_explorer_handle_file_usage(\$type, \$request, \$user, \$folders_that_user_has_access_to);\n            break;", $explorer, 'called by its case');
	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handler, 'gate');
	pg_assert_false(strpos($handler, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_usage.php');", $explorer, 'loaded by the explorer');
	pg_assert_contains('pg_explorer_folder_visible($usage_file[\'folder\'], $folders_that_user_has_access_to) == false', $handler, 'the folder check is kept');
}

// A setting counts only when it holds the file's name whole, and only for
// the columns the database has.
function test_explorer_file_usage_setting_hits()
{
	$labels = array('og_default_image' => 'Share', 'organization_logo' => 'Logo', 'app_icon' => 'Icon');
	$config = array('og_default_image' => 'logo.png', 'organization_logo' => 'big-logo.png', 'app_icon' => 'logo.png');

	pg_assert_same(
		array(
			array('name' => 'Share', 'note' => '', 'url' => ''),
			array('name' => 'Icon', 'note' => '', 'url' => '')),
		pg_explorer_file_usage_setting_hits($config, array('og_default_image', 'organization_logo', 'app_icon'), $labels, 'logo.png'),
		'whole matches only');

	pg_assert_same(
		array(array('name' => 'Icon', 'note' => '', 'url' => '')),
		pg_explorer_file_usage_setting_hits($config, array('app_icon'), $labels, 'logo.png'),
		'only the columns asked');

	pg_assert_same(array(), pg_explorer_file_usage_setting_hits($config, $labels ? array_keys($labels) : array(), $labels, 'other.png'), 'no match');
}

// A hit links only to a screen that admits the viewer: the page's folder
// for pages and page regions, Designer for common and designer regions and
// classic styles, Administrator for dynamic regions, the commerce right for
// products and product groups, and the ad's region for a role-3 user.
function test_explorer_file_usage_screen_open()
{
	pg_assert_true(pg_explorer_file_usage_screen_open('page', 3, true), 'page in an editable folder');
	pg_assert_false(pg_explorer_file_usage_screen_open('page', 3, false), 'page outside the folders');
	pg_assert_true(pg_explorer_file_usage_screen_open('designer', 1, false), 'designer screen for a Designer');
	pg_assert_false(pg_explorer_file_usage_screen_open('designer', 2, false), 'designer screen refuses a Manager');
	pg_assert_true(pg_explorer_file_usage_screen_open('administrator', 0, false), 'administrator screen');
	pg_assert_false(pg_explorer_file_usage_screen_open('administrator', 1, false), 'administrator screen refuses a Designer');
	pg_assert_true(pg_explorer_file_usage_screen_open('commerce', 2, false), 'commerce for a Manager');
	pg_assert_true(pg_explorer_file_usage_screen_open('commerce', 3, true), 'commerce for a user with the right');
	pg_assert_false(pg_explorer_file_usage_screen_open('commerce', 3, false), 'commerce refuses a user without the right');
	pg_assert_true(pg_explorer_file_usage_screen_open('ad', 2, false), 'ad for a Manager');
	pg_assert_true(pg_explorer_file_usage_screen_open('ad', 3, true), 'ad in an editable region');
	pg_assert_false(pg_explorer_file_usage_screen_open('ad', 3, false), 'ad outside the regions');
	pg_assert_false(pg_explorer_file_usage_screen_open('other', 0, true), 'unknown screen');
}

// Every edit-screen link of the handler passes through that check, and the
// selects carry what it needs.
function test_explorer_file_usage_links_are_gated()
{
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_usage.php');

	pg_assert_contains('pregion.pregion_id, pregion.pregion_name, page.page_id, page.page_name, page.page_folder', $handler, 'page region select');
	pg_assert_contains("'page_id, page_name, page_folder'", $handler, 'page select');
	pg_assert_contains("'id, name, ad_region_id'", $handler, 'ad select');
	pg_assert_contains("\$usage_open('designer', false) ? (((\$row['cregion_designer_type'] == 'yes')", $handler, 'common regions');
	pg_assert_contains("\$usage_open('administrator', false) ? ('edit_dynamic_region.php?id='", $handler, 'dynamic regions');
	pg_assert_contains("\$usage_open('designer', false) ? ('edit_custom_style.php?id='", $handler, 'classic styles');
	pg_assert_contains("'edit_system_style.php?id=' . (int) \$row['style_id']", $handler, 'visual designer styles stay linked');
	pg_assert_contains("\$usage_open('commerce', \$usage_commerce) ? ('edit_product.php?id='", $handler, 'products');
	pg_assert_contains("\$usage_open('commerce', \$usage_commerce) ? ('edit_product_group.php?id='", $handler, 'product groups');
	pg_assert_contains("\$usage_open('ad', in_array((int) \$row['ad_region_id'], \$usage_ad_regions, true)) ? ('edit_ad.php?id='", $handler, 'ads');
	pg_assert_same(2, substr_count($handler, "\$usage_open('page', \$usage_page_folder_editable(\$row['page_folder']))"), 'pages and page regions');
}
