<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Folder View system widget
 * (includes/fn/widgets_folder_view.php): the settings it reads, the rows of
 * the page's folder and of the folders below it, its flags and the places
 * the kind is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_folder_view_widget_settings()
{
	pg_assert_same(array('pages' => true, 'files' => false), pg_fv_settings(null, array()), 'the classic defaults');
	pg_assert_same(array('pages' => false, 'files' => true), pg_fv_settings(array('pages' => '0', 'files' => '1'), array()), 'a converted page keeps its row');
	pg_assert_same(array('pages' => true, 'files' => false),
		pg_fv_settings(array('pages' => '0', 'files' => '1'), array('include_pages' => true, 'include_files' => false)), 'the widget\'s setting wins');
	pg_assert_same(array('pages' => false, 'files' => true),
		pg_fv_settings(null, array('include_pages' => '0', 'include_files' => '1')), 'stored as text');
}

// Folder 1 (the page's) holds folder 2 (with a page) and folder 3 (empty),
// archived folder 4 (with a page) and folder 5, which the visitor may not
// view but whose folder 6 holds a file; and its own two pages, one of them
// the page shown, and a file.
function _folder_view_test_data()
{
	return array(
		'folders' => array(
			1 => array(
				array('id' => 2, 'name' => 'Guides', 'archived' => 0, 'access' => 'public'),
				array('id' => 3, 'name' => 'Empty', 'archived' => 0, 'access' => 'public'),
				array('id' => 4, 'name' => 'Old', 'archived' => 1, 'access' => 'public'),
				array('id' => 5, 'name' => 'Members', 'archived' => 0, 'access' => 'private'),
			),
			5 => array(array('id' => 6, 'name' => 'Open', 'archived' => 0, 'access' => 'public')),
		),
		'pages' => array(
			1 => array(
				array('id' => 10, 'name' => 'about', 'title' => 'About & us', 'meta_description' => 'Who we are'),
				array('id' => 11, 'name' => 'docs', 'title' => '', 'meta_description' => ''),
			),
			2 => array(array('id' => 12, 'name' => 'setup guide', 'title' => '', 'meta_description' => '')),
			4 => array(array('id' => 13, 'name' => 'old', 'title' => '', 'meta_description' => '')),
			5 => array(array('id' => 14, 'name' => 'secret', 'title' => '', 'meta_description' => '')),
		),
		'files' => array(
			1 => array(array('name' => 'logo.png', 'description' => 'Logo', 'design' => 1)),
			6 => array(array('name' => 'brochure.pdf', 'description' => '', 'design' => 0)),
		),
		'view' => array(1 => true, 2 => true, 3 => true, 4 => true, 5 => false, 6 => true),
	);
}

function test_folder_view_widget_rows()
{
	$rows = pg_sw_list_folder_view_rows(1, _folder_view_test_data(), 11, '/');
	$names = array_map(function ($r) { return $r['__folder_item_name']; }, $rows[1]);
	pg_assert_same(array('Guides', 'Members', 'About & us', 'logo.png'), $names, 'folders first, then pages, then files; empty, archived and the shown page left out');
	pg_assert_same('2', $rows[1][0]['__folder_item_key'], 'a folder row is keyed by its id');
	pg_assert_same('1', $rows[1][0]['__folder_item_is_folder'], 'a folder');
	pg_assert_same('', $rows[1][0]['__folder_item_url'], 'a folder has no address');
	pg_assert_same('', $rows[1][2]['__folder_item_key'], 'a page lists nothing under it');
	pg_assert_same('/about', $rows[1][2]['__folder_item_url'], 'the page\'s address');
	pg_assert_same('Who we are', $rows[1][2]['__folder_item_description'], 'its description');
	pg_assert_same('1', $rows[1][3]['__folder_item_is_design'], 'a design file');
	pg_assert_same('private', $rows[1][1]['__folder_item_access'], 'the folder\'s access');
	pg_assert_same('/setup%20guide', $rows[2][0]['__folder_item_url'], 'the name in the address is encoded');
	pg_assert_false(isset($rows[5][1]), 'a folder the visitor may not view shows only its sub-folders');
	pg_assert_same('Open', $rows[5][0]['__folder_item_name'], 'kept for the levels');
	pg_assert_same('brochure.pdf', $rows[6][0]['__folder_item_name'], 'the file below it');
	pg_assert_false(isset($rows[3]) || isset($rows[4]), 'no rows for an empty or archived folder');
	pg_assert_same(array(), pg_sw_list_folder_view_rows(9, _folder_view_test_data(), 0), 'a folder without entries');
}

function test_folder_view_widget_flags()
{
	$on = array('pages' => true, 'files' => false);
	$off = array('pages' => false, 'files' => false);
	pg_assert_same(array('has_folder_items' => true, 'no_folder_items' => false, 'nothing_included' => false, 'has_start_page' => false),
		pg_fv_flags($on, array(array()), false), 'entries');
	pg_assert_same(array('has_folder_items' => false, 'no_folder_items' => true, 'nothing_included' => false, 'has_start_page' => true),
		pg_fv_flags($on, array(), true), 'nothing the visitor may see');
	pg_assert_same(array('has_folder_items' => false, 'no_folder_items' => false, 'nothing_included' => true, 'has_start_page' => false),
		pg_fv_flags($off, array(), false), 'neither pages nor files');
}

function test_folder_view_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['folder_view']), 'panel label');
	$map = pg_sw_page_type_widgets();
	pg_assert_same('folder_view', $map['folder view'], 'stands in for the classic type');
	pg_assert_true(isset(pg_sw_kind_lists('folder_view')['folder_view.children']), 'offers the folder list');
	pg_assert_false(isset(pg_sw_kind_lists('menu_region')['folder_view.children']), 'only in its own kind');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_folder_view(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'folder_view'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'folder_view': {", $js, 'has a starter tree');
	pg_assert_contains("folder_view:       SW_FOLDER_VIEW_TOKEN_GROUPS", $js, 'has a token palette');
	pg_assert_contains("'folder_view.children': {", $js, 'the list source in the editor');
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_folder_view.php');
	pg_assert_contains("_pg_inject_messages_node(\$tree, 'folder_view')", $source, 'Messages named after the classic liveform');
}
