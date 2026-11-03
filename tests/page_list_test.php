<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pages list (includes/fn/page_list.php): the view names, the
 * view's condition, the columns it sorts by, a folder's style worked out
 * from one read of the folder table, and a row.
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

require_once(PG_FUNCTIONS_DIR . '/seo.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_list.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $page_list_test_name => $page_list_test_value) {
	if (!defined($page_list_test_name)) {
		define($page_list_test_name, $page_list_test_value);
	}
}

function test_page_list_filter_names()
{
	$filters = pg_page_list_filters(false, false, false);

	pg_assert_same('default', pg_page_list_filter_name('', $filters));
	pg_assert_same('default', pg_page_list_filter_name(array('my_home_pages'), $filters));
	pg_assert_same('my_home_pages', pg_page_list_filter_name('my_home_pages', $filters));
	pg_assert_same('default', pg_page_list_filter_name('seo_critical', $filters), 'an SEO view before its columns exist');
	pg_assert_same('seo_critical', pg_page_list_filter_name('seo_critical', pg_page_list_filters(true, false, false)));
	pg_assert_false(isset(pg_page_list_filters(true, true, false)['seo_orphan']), 'the link views need the link graph');
	pg_assert_same('public', pg_page_list_filters(true, true, true)['seo_orphan']['access']);
}

function test_page_list_where()
{
	$filters = pg_page_list_filters(true, true, true);

	pg_assert_same(
		"WHERE ((folder.folder_archived = '0' OR folder.folder_archived IS NULL))",
		pg_page_list_where('default', $filters, array(), null));

	pg_assert_same(
		"WHERE (page.page_home = 'yes') AND ((folder.folder_archived = '0' OR folder.folder_archived IS NULL) AND (page.page_folder NOT IN (7))) AND (page.page_folder IN (3, 4))",
		pg_page_list_where('my_home_pages', $filters, array(7), array('3', 4, 3)));

	pg_assert_same(
		"WHERE ((folder.folder_archived = '1') OR (page.page_folder IN (7)))",
		pg_page_list_where('all_my_archived_pages', $filters, array(7), null),
		'the Recycle Bin counts as archived');

	pg_assert_contains('(1 = 0)', pg_page_list_where('my_public_pages', $filters, array(), array()), 'no folder shows no page');
	pg_assert_contains("page.page_type IN ('custom form', 'custom form confirmation')", pg_page_list_where('my_custom_form_pages', $filters, array(), null));
}

function test_page_list_sort_columns()
{
	pg_assert_same(
		array('name', 'folder', 'style', 'page_type', 'searchable', 'comments', 'sitemap', 'seo', 'impact', 'last_modified'),
		array_keys(pg_page_list_sort_columns('default', false, 'Desktop Page Style')));
	pg_assert_same(
		array('name', 'form_name', 'form_enabled', 'folder', 'page_type'),
		array_slice(array_keys(pg_page_list_sort_columns('my_custom_form_pages', true, 'Style')), 0, 5),
		'no style sort while another theme is previewed');
}

function test_page_list_folder_styles()
{
	$folders = array(
		array('folder_id' => '1', 'folder_parent' => '0', 'folder_level' => '0', 'folder_style' => '5', 'mobile_style_id' => '0'),
		array('folder_id' => '2', 'folder_parent' => '1', 'folder_level' => '1', 'folder_style' => '0', 'mobile_style_id' => '9'),
		array('folder_id' => '3', 'folder_parent' => '2', 'folder_level' => '2', 'folder_style' => '0', 'mobile_style_id' => '0'),
		array('folder_id' => '4', 'folder_parent' => '3', 'folder_level' => '3', 'folder_style' => '8', 'mobile_style_id' => '0'),
		array('folder_id' => '5', 'folder_parent' => '99', 'folder_level' => '1', 'folder_style' => '0', 'mobile_style_id' => '0'),
		array('folder_id' => '6', 'folder_parent' => '7', 'folder_level' => '1', 'folder_style' => '0', 'mobile_style_id' => '0'),
		array('folder_id' => '7', 'folder_parent' => '6', 'folder_level' => '1', 'folder_style' => '0', 'mobile_style_id' => '0'),
	);

	pg_assert_same(array(1 => 5, 2 => 5, 3 => 5, 4 => 8, 5 => 0, 6 => 0, 7 => 0), pg_page_list_folder_styles($folders, 'folder_style'), 'own, inherited, missing parent, loop');

	$mobile = pg_page_list_folder_styles($folders, 'mobile_style_id');
	pg_assert_same(0, $mobile[1], 'the root folder has none');
	pg_assert_same(9, $mobile[2]);
	pg_assert_same(9, $mobile[3]);
	pg_assert_same(9, $mobile[4]);
	pg_assert_same(array(), pg_page_list_folder_styles(array(), 'folder_style'));
}

function test_page_list_row()
{
	$filters = pg_page_list_filters(false, false, false);
	$page = array(
		'page_id' => '12', 'page_name' => 'about <us>', 'page_folder' => '3', 'page_style' => '0', 'layout_type' => 'system', 'has_tree' => '0',
		'folder_name' => 'Site', 'folder_access_control_type' => 'public', 'style_name' => '', 'style_type' => '',
		'page_search' => '1', 'page_home' => 'yes', 'page_type' => 'standard', 'comments' => '0', 'seo_score' => '0',
		'seo_flags' => '0', 'seo_checked_at' => '0', 'seo_analysis_current' => '0', 'sitemap' => '1', 'user_username' => 'ali',
		'seo_impact' => null, 'seo_views' => '0', 'page_timestamp' => (string) time(), 'form_name' => 'Contact', 'enabled' => '1',
	);
	$style = array('html' => 'Default: Main', 'class' => ' fg-danger');

	$row = pg_page_list_row(array('filter' => 'default', 'filters' => $filters), $page, $style);
	pg_assert_same(12, count($row['cells']), 'one cell per heading');

	$html = pg_list_row_html($row);
	pg_assert_contains('value="12"', $html);
	pg_assert_contains('about &lt;us&gt;', $html);
	pg_assert_false(strpos($html, 'about <us>') !== false, 'the name is escaped');
	pg_assert_contains('edit_page.php?id=12', $html);
	pg_assert_contains('<td class="align-middle fg-danger">Default: Main</td>', $html);
	pg_assert_contains('data-pg-seo-badge="page:12"', $html);
	pg_assert_contains('ali', $row['cells'][11]['html']);

	pg_assert_same(14, count(pg_page_list_row(array('filter' => 'my_custom_form_pages', 'filters' => $filters), $page, $style)['cells']), 'the form name and enabled columns');
}
