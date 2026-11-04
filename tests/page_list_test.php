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

function test_page_list_filter_name_falls_back_to_default()
{
	$filters = array('all_my_pages' => array(), 'my_home_pages' => array());

	pg_assert_same('my_home_pages', pg_page_list_filter_name('my_home_pages', $filters));
	pg_assert_same('default', pg_page_list_filter_name('seo_orphan', $filters), 'a view the upgrade has not made available');
	pg_assert_same('default', pg_page_list_filter_name(array('my_home_pages'), $filters), 'a view sent as an array');
	pg_assert_same('default', pg_page_list_filter_name('', $filters));
}

function test_page_list_where_keeps_archive_bin_and_folders()
{
	pg_assert_same(
		"(page.page_home = 'yes') AND ((folder.folder_archived = '0' OR folder.folder_archived IS NULL) AND (page.page_folder NOT IN (9)))",
		pg_page_list_where("(page.page_home = 'yes')", false, array(9), null));

	pg_assert_same(
		"((folder.folder_archived = '1') OR (page.page_folder IN (9))) AND (page.page_folder IN (3, 4))",
		pg_page_list_where('', true, array(9), array('3', 4)));

	pg_assert_same(
		"((folder.folder_archived = '0' OR folder.folder_archived IS NULL)) AND (1 = 0)",
		pg_page_list_where('', false, array(), array()),
		'no folder the user can reach lists nothing');
}

function test_page_list_filters_keep_every_view_of_the_screen()
{
	$filters = pg_page_list_filters();

	foreach (array('all_my_pages', 'all_my_archived_pages', 'my_home_pages', 'my_rss_enabled_pages', 'my_custom_form_pages', 'my_private_pages') as $name) {
		pg_assert_true(isset($filters[$name]), $name);
	}

	pg_assert_same('public', $filters['my_sitemap_pages']['access']);
	pg_assert_same(true, $filters['my_rss_enabled_pages']['group']);
	pg_assert_contains('custom_form_pages.form_name', $filters['my_custom_form_pages']['select']);
	pg_assert_same("(page.page_type = 'standard')", $filters['my_standard_pages']['where']);
}

function test_page_list_columns_follow_the_view()
{
	require_once(PG_FUNCTIONS_DIR . '/seo.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

	$view = array('custom_form' => false, 'previewing_other_theme' => false, 'style_heading' => 'Desktop Page Style');
	$columns = pg_list_table_columns(pg_page_list_columns($view));

	pg_assert_same(array('name', 'seo', 'folder', 'style', 'page_type', 'searchable', 'comments', 'sitemap', 'impact', 'last_modified', 'actions'), array_keys($columns));
	pg_assert_same(false, $columns['name']['hideable'], 'the name is never hidden');
	pg_assert_same('style.style_name', $columns['style']['sort']);
	pg_assert_same('page.seo_score', $columns['seo']['sort'], 'the score bar sorts by the score');
	pg_assert_same(false, $columns['actions']['sortable']);
	pg_assert_same('center', $columns['sitemap']['align']);
	pg_assert_true(strpos($columns['impact']['sort'], ' AS ') === false, 'the impact sort is an expression, no alias');

	$columns = pg_list_table_columns(pg_page_list_columns(array('custom_form' => true, 'previewing_other_theme' => true, 'style_heading' => 'Mobile Page Style')));

	pg_assert_same(array('name', 'seo', 'form_name', 'form_enabled', 'folder', 'style'), array_slice(array_keys($columns), 0, 6));
	pg_assert_same(false, $columns['style']['sortable'], 'a previewed theme\'s style column does not sort');
	pg_assert_same('Mobile Page Style', $columns['style']['heading']);
}

function test_page_list_is_a_driver_list()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

	$definition = pg_list_table_definition('view_pages');

	pg_assert_same('includes/fn/page_list.php', $definition['file']);
	pg_assert_true(function_exists($definition['source']), 'the source function is in its file');
}
