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

function content_lists_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_content_lists_login_region_previews_escaped()
{
	$source = content_lists_source('view_regions.php');

	foreach (array('not_logged_in_header', 'not_logged_in_footer', 'logged_in_header', 'logged_in_footer') as $field) {
		pg_assert_contains('<td class="align-middle">\' . h($' . $field . ') . \'</td>', $source, $field . ' cell');
		pg_assert_contains('if (mb_strlen($' . $field . ') > 50) {', $source, $field . ' limit');
	}

	pg_assert_false(strpos($source, '> 100') !== false, 'one limit for the test and the cut');
	pg_assert_false(strpos($source, 'tex-center') !== false, 'tex-center typo');
	pg_assert_contains('<td class="align-middle text-center">\' . $login_form . \'</td>', $source);
}

function test_content_lists_check_marks_named()
{
	$paths = array(
		'view_pages.php' => 4,
		'view_comments.php' => 1,
		'view_regions.php' => 3,
		'view_fields.php' => 2,
		'view_menu_items.php' => 1,
		'view_files.php' => 1,
		'view_design_files.php' => 1,
	);

	foreach ($paths as $path => $count) {
		$source = content_lists_source($path);
		pg_assert_false(strpos($source, '<span class="material-icons">task_alt</span>') !== false, $path . ': unnamed check mark');
		pg_assert_same($count, substr_count($source, '<span class="material-icons" role="img" aria-label="\' . h(lang(\'Yes\')) . \'">task_alt</span>'), $path . ': named check marks');
	}
}

function test_content_lists_folder_and_home_ligatures()
{
	foreach (array('view_pages.php', 'view_files.php', 'view_design_files.php') as $path) {
		$source = content_lists_source($path);
		pg_assert_false(strpos($source, '<span class="material-icons d-inline">folder</span>') !== false, $path . ': folder ligature read aloud');
		pg_assert_contains('<span class="material-icons d-inline" aria-hidden="true">folder</span>', $source, $path);
	}

	pg_assert_contains('<span class="material-icons" role="img" aria-label="\' . h(lang(\'Home Page\')) . \'">home</span>', content_lists_source('view_pages.php'));
}

function test_content_lists_row_checkboxes_named()
{
	$expect = array(
		'view_pages.php' => '$page[\'page_name\']',
		'view_files.php' => '$file[\'name\']',
		'view_design_files.php' => '$file[\'name\']',
		'view_fields.php' => '$name',
	);

	foreach ($expect as $path => $row_name) {
		pg_assert_contains('aria-label="\' . h(lang(array(\'string\' => \'Select {var:1}\', \'vars\' => array(' . $row_name . ')))) . \'"', content_lists_source($path), $path);
	}
}

function test_content_lists_file_download_and_thumbnail()
{
	foreach (array('view_files.php', 'view_design_files.php') as $path) {
		$source = content_lists_source($path);
		pg_assert_false(strpos($source, 'download="\' . h($file[\'type\']) . \'"') !== false, $path . ': file type as download name');
		pg_assert_contains('download="\' . h($file[\'name\']) . \'"', $source, $path);
		pg_assert_contains('style="width: 50px;height:50px;object-fit:cover;"', $source, $path);
		pg_assert_contains('data-src="\' . h(PATH . encode_url_path($file[\'name\'])) . \'"', $source, $path);
	}
}

function test_content_lists_comment_link_title_and_calendar_usernames()
{
	$comments = content_lists_source('view_comments.php');
	pg_assert_false(strpos($comments, '<i class="bi bi-link" title=') !== false, 'title on the icon');
	pg_assert_same(4, substr_count($comments, '" title="\' . lang(\'Go To Comment\') . \'"><i class="bi bi-link"></i></a>'));

	$calendars = content_lists_source('view_calendars.php');
	pg_assert_contains("'vars'=>array(h(\$calendar['created_username']))", $calendars);
	pg_assert_contains("'vars'=>array(h(\$calendar['last_modified_username']))", $calendars);
}
