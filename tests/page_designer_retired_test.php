<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the legacy page designer is gone: its screen, script and
 * stylesheet, the toolbar link and menu case that opened it, its help row,
 * the api.php actions only its script called and its session key. Cleanup
 * deletes the three files on an updated site. What replaced it stays: the
 * style designer button Ctrl+G clicks, the layout editor screen and the two
 * actions the LiveSite migration reads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
require_once(PG_FUNCTIONS_DIR . '/get_help_url.php');

function page_designer_retired_files()
{
	return array('page_designer.php', 'assets/js/page_designer.js', 'assets/css/page_designer.css');
}

// The api.php actions only page_designer.js called.
function page_designer_retired_actions()
{
	return array(
		'get_page', 'get_style', 'update_style', 'get_layout', 'update_layout',
		'get_file', 'update_file', 'get_items_in_style', 'get_design_files',
		'get_designer_region', 'get_dynamic_region', 'get_dynamic_regions',
		'update_designer_region', 'update_dynamic_region', 'create_design_region',
		'create_dynamic_region', 'update_page_designer_properties',
	);
}

function test_page_designer_retired_files_are_gone_and_cleaned_up()
{
	$clean_up = (string) file_get_contents(PG_FUNCTIONS_DIR . '/clean_up.php');

	foreach (page_designer_retired_files() as $path) {
		pg_assert_false(is_file(PG_FUNCTIONS_DIR . '/' . $path), $path . ' is not shipped');
		pg_assert_contains("    '" . $path . "',\n", $clean_up, 'Cleanup deletes ' . $path . ' on an updated site');
	}
}

// No link, menu case or help row names the screen; the button Ctrl+G clicks
// keeps its class on a page whose style is a visual design.
function test_page_designer_retired_no_way_in()
{
	$output = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	pg_assert_false(strpos($output, 'page_designer.php') !== false, 'no toolbar link or menu case');
	pg_assert_false(strpos($output, 'page_designer.js') !== false, 'no comment pointing at the script');
	pg_assert_same(1, substr_count($output, "'class' => 'page_designer_button'"), 'one style designer button for Ctrl+G');
	pg_assert_false(strpos($output, "lang('Open Page Designer')") !== false, 'no classic branch');

	pg_assert_same('https://pinegrap.com/introduction', get_help_url(array('screen' => 'page_designer')), 'no help row');
	pg_assert_same('https://pinegrap.com/design#page_designer', get_help_url(array('screen' => 'edit_page_layout')), 'the layout editor keeps its row');
}

// The actions only the page designer called are unlisted, so api.php answers
// them "Invalid action." after its general gate; their handlers are gone.
function test_page_designer_retired_actions_are_unlisted()
{
	$actions = pg_panel_actions();

	require_once(PG_FUNCTIONS_DIR . '/includes/panel/content.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	foreach (page_designer_retired_actions() as $action) {
		pg_assert_false(isset($actions[$action]), $action . ' is not listed');
		pg_assert_same('role', pg_panel_gate($action), $action . ' meets the general gate');
		pg_assert_false(function_exists('pg_panel_' . $action), 'pg_panel_' . $action . ' is gone');
	}

	// The LiveSite migration reads these from another site; the layout
	// editor screen and the designer's layout tab use layout_code.
	foreach (array('get_styles', 'get_designer_regions', 'get_common_regions', 'get_folders', 'get_pages', 'test', 'layout_code') as $action) {
		pg_assert_true(isset($actions[$action]), $action . ' stays');
	}

	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	pg_assert_false(strpos($designer, "['page_designer']") !== false, 'no session key of its own');
}
