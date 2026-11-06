<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the content lists (pages, files, design files, fields,
 * comments, menu items, regions, calendars): escaped login region previews,
 * download names and thumbnails, named row checkboxes and check marks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/region_list.php';

function content_lists_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_content_lists_login_region_previews_escaped()
{
	$source = content_lists_source('includes/fn/region_list.php');

	foreach (array('not_logged_in_header', 'not_logged_in_footer', 'logged_in_header', 'logged_in_footer') as $field) {
		pg_assert_contains("'" . $field . "' => pg_list_cell(pg_region_list_preview(\$row['" . $field . "'])),", $source, $field . ' cell');
	}

	pg_assert_contains("return h((mb_strlen(\$text) > 50) ? mb_substr(\$text, 0, 50) . '...' : \$text);", $source, 'cut to 50, then escaped');
	pg_assert_same('&lt;h3&gt;x', pg_region_list_preview('<h3>x'), 'stored markup is shown as text');
	pg_assert_same(str_repeat('a', 50) . '...', pg_region_list_preview(str_repeat('a', 60)));
}

function test_content_lists_check_marks_named()
{
	$paths = array(
		'includes/fn/list_table_edit.php' => 1,
		'includes/fn/field_list.php' => 1,
		'view_menu_items.php' => 1,
	);

	foreach ($paths as $path => $count) {
		$source = content_lists_source($path);
		pg_assert_false(strpos($source, '<span class="material-icons">task_alt</span>') !== false, $path . ': unnamed check mark');
		pg_assert_same($count, substr_count($source, '<span class="material-icons" role="img" aria-label="\' . h(lang(\'Yes\')) . \'">task_alt</span>'), $path . ': named check marks');
	}

	// Printed lists on the list table driver draw their marks with
	// pg_list_table_check_cell(), which names them.
	pg_assert_same(1, substr_count(content_lists_source('includes/fn/comment_list_edit.php'), 'pg_list_table_check_cell('), 'comment_list_edit.php: check cells');
	pg_assert_same(1, substr_count(content_lists_source('includes/fn/design_file_list_edit.php'), 'pg_list_table_check_cell('), 'view_design_files.php: check cells');
	pg_assert_same(4, substr_count(content_lists_source('includes/fn/region_list.php'), 'pg_list_table_check_cell('), 'region_list.php: check cells');
}

function test_content_lists_folder_and_home_ligatures()
{
	foreach (array('includes/fn/page_list.php', 'includes/fn/design_file_list_edit.php') as $path) {
		$source = content_lists_source($path);
		pg_assert_false(strpos($source, '<span class="material-icons d-inline">folder</span>') !== false, $path . ': folder ligature read aloud');
		pg_assert_contains('pg_folder_list_name($', $source, $path);
	}
	pg_assert_contains('<span class="material-icons d-inline" aria-hidden="true">folder</span>', pg_folder_list_name('Docs'));

	pg_assert_contains('<span class="material-icons" role="img" aria-label="\' . h(lang(\'Home Page\')) . \'">home</span>', content_lists_source('includes/fn/page_list.php'));
}

function test_content_lists_row_checkboxes_named()
{
	// The pages, design files and fields lists select by row on the list
	// table driver: no checkboxes. Delete Selected on the fields list gets
	// the selection as fields[].
	pg_assert_false(strpos(content_lists_source('view_fields.php'), 'type="checkbox"') !== false, 'view_fields.php: no row checkboxes');
	pg_assert_contains("'ids_field' => \$designer_form ? '' : 'fields[]',", content_lists_source('view_fields.php'));
}

function test_content_lists_file_download_and_thumbnail()
{
	foreach (array('includes/fn/design_file_list_edit.php') as $path) {
		$source = content_lists_source($path);
		pg_assert_false(strpos($source, 'download="\' . h($file[\'type\']) . \'"') !== false, $path . ': file type as download name');
		pg_assert_contains('download="\' . h($file[\'name\']) . \'"', $source, $path);
		pg_assert_contains('style="width: 50px;height:50px;object-fit:cover;"', $source, $path);
		pg_assert_contains('data-pg-thumb="\' . h(pg_image_panel_thumb_url(PATH . encode_url_path($file[\'name\']))) . \'"', $source, $path);
	}
}

function test_content_lists_comment_link_title_and_calendar_usernames()
{
	$comments = content_lists_source('includes/fn/comment_list_edit.php');
	pg_assert_false(strpos($comments, '<i class="bi bi-link" title=') !== false, 'title on the icon');
	pg_assert_same(1, substr_count($comments, '" title="\' . h(lang(\'Go To Comment\')) . \'" aria-label="\' . h(lang(\'Go To Comment\')) . \'"><i class="bi bi-link" aria-hidden="true"></i></a>'));

	$calendars = content_lists_source('view_calendars.php');
	pg_assert_contains("pg_list_table_when_cell(\$calendar['created_timestamp'], (string) \$calendar['created_username'])", $calendars);
	pg_assert_contains("pg_list_table_when_cell(\$calendar['last_modified_timestamp'], (string) \$calendar['last_modified_username'])", $calendars);
}
