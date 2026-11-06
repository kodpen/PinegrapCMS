<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_clean_up_list_row() in includes/fn/files.php: one line of the
 * Cleanup tool's list of what it is about to delete.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A file left in data/files outside the uploader (FTP, an unpacked archive)
// keeps whatever name it was given; markup in it is printed as text.
function test_clean_up_list_row_escapes_the_name()
{
	$row = pg_clean_up_list_row('insert_drive_file', '<img src=x onerror=alert(1)>.jpg');

	pg_assert_false(strpos($row, '<img') !== false, 'no tag');
	pg_assert_true(strpos($row, '&lt;img src=x onerror=alert(1)&gt;.jpg') !== false, 'name as text');
}

// The icon names the Material icon; the row keeps its classes.
function test_clean_up_list_row_markup()
{
	pg_assert_same(
		'<li class="list-group-item bg-reset border-0 fg-danger"><span class="material-icons me-5">folder</span>assets/lazy</li>',
		pg_clean_up_list_row('folder', 'assets/lazy'),
		'row'
	);
}

// The server rules repair keeps the previous web.config / .htaccess under
// data/temp/server_config; nothing writes that copy again, so the sweep of
// data/temp leaves it, as it leaves the other records listed there.
function test_clean_up_temp_keep_holds_the_server_rules_backups()
{
	$keep = pg_clean_up_temp_keep();

	foreach (array('.', '..', '.htaccess', 'hash_reference.json', 'erp_packages', 'server_config') as $name) {
		pg_assert_true(in_array($name, $keep, true), $name . ' kept');
	}
}

// A folder is reported deleted only when it is gone; a file inside it that
// cannot be removed keeps the folder and makes the answer false.
function test_clean_up_delete_path_reports_what_was_removed()
{
	$root = sys_get_temp_dir() . '/pg_clean_up_' . uniqid();
	mkdir($root . '/sub/deeper', 0777, true);
	file_put_contents($root . '/sub/deeper/a.txt', 'a');
	file_put_contents($root . '/b.txt', 'b');

	pg_assert_true(pg_clean_up_delete_path($root . '/b.txt'), 'file removed');
	pg_assert_false(file_exists($root . '/b.txt'), 'file gone');
	pg_assert_true(pg_clean_up_delete_path($root), 'tree removed');
	pg_assert_false(file_exists($root), 'tree gone');
	pg_assert_false(pg_clean_up_delete_path($root . '/missing'), 'a missing path was not deleted');
}

// The counts sentence: an empty pair gives no text, so the caller can say
// that nothing was deleted instead of printing " that deleted.".
function test_clean_up_count_text()
{
	pg_assert_same('', pg_clean_up_count_text('{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted.', 0, 0), 'nothing');
	pg_assert_same(' 2 folders and 1 file that deleted.', pg_clean_up_count_text('{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted.', 2, 1), 'both');
	pg_assert_same(' 3 files that deleted.', pg_clean_up_count_text('{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted.', 0, 3), 'files');
}

// The activity line carries the deleted names after the counts.
function test_clean_up_count_text_more_values()
{
	pg_assert_same(
		' 1 folder that deleted with clean up tool. [data/temp/x]',
		pg_clean_up_count_text('{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted with clean up tool. [{var:4}]', 1, 0, array('data/temp/x')),
		'names'
	);
}

// The status column's Summary: folders, files and, with e-commerce on, the
// expired payment records, each counted once; a null record count drops
// that row instead of showing 0 for a feature that is off.
function test_clean_up_summary_counts()
{
	$with = pg_clean_up_summary(2, 1234, 5);
	pg_assert_true(strpos($with, lang('Folders')) !== false, 'folders row');
	pg_assert_true(strpos($with, lang('Expired payment records')) !== false, 'records row');
	pg_assert_true(strpos($with, '>' . pg_format_number(1234, 0) . '</dd>') !== false, 'file count');
	pg_assert_same(3, substr_count($with, '<dt '), 'three rows');
	pg_assert_same(2, substr_count($with, 'border-bottom'), 'no rule under the last row');

	$without = pg_clean_up_summary(0, 3, null);
	pg_assert_false(strpos($without, lang('Expired payment records')) !== false, 'no records row');
	pg_assert_same(2, substr_count($without, '<dt '), 'two rows');
	pg_assert_true(strpos($without, lang('Everything listed here is deleted permanently.')) !== false, 'warning');
}
