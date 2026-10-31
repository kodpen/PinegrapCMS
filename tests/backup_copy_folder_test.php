<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_backup_copy_folder() and pg_backup_copy_failure_message() in
 * includes/fn/backup.php, and for the Backup Manager's copy steps using them:
 * a file that could not be copied is reported instead of being ignored.
 *
 * The tests run as root in cloud sessions, so a failing copy() is made by
 * putting a directory at the target file's path or by pointing at a target
 * folder that does not exist, never by chmod.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function backup_copy_folder_fixture()
{
	$base = sys_get_temp_dir() . '/pg_backup_copy_' . uniqid('', true);
	mkdir($base . '/source/sub', 0777, true);
	mkdir($base . '/target', 0777, true);
	file_put_contents($base . '/source/a.jpg', str_repeat('a', 100));
	file_put_contents($base . '/source/b.css', 'body{}');
	file_put_contents($base . '/source/.hidden', 'x');
	file_put_contents($base . '/source/sub/inner.txt', 'inner');
	return $base;
}

// Regular files are copied with their content; directories are skipped.
function test_backup_copy_folder_copies_regular_files()
{
	$base = backup_copy_folder_fixture();

	$failed = pg_backup_copy_folder($base . '/source', $base . '/target');

	pg_assert_same(array(), $failed, 'nothing failed');
	pg_assert_same(str_repeat('a', 100), file_get_contents($base . '/target/a.jpg'), 'content');
	pg_assert_true(is_file($base . '/target/.hidden'), 'dot file copied');
	pg_assert_false(file_exists($base . '/target/sub'), 'directory skipped');

	pg_backup_delete_tree($base);
}

// A file whose copy fails is named in the result.
function test_backup_copy_folder_names_a_failed_copy()
{
	$base = backup_copy_folder_fixture();
	mkdir($base . '/target/b.css');

	$failed = pg_backup_copy_folder($base . '/source', $base . '/target');

	pg_assert_same(array('b.css'), $failed, 'the blocked file');
	pg_assert_true(is_file($base . '/target/a.jpg'), 'the others still copied');

	pg_backup_delete_tree($base);
}

// A target folder that does not exist fails every file.
function test_backup_copy_folder_missing_target_fails_every_file()
{
	$base = backup_copy_folder_fixture();

	$failed = pg_backup_copy_folder($base . '/source', $base . '/missing');
	sort($failed);

	pg_assert_same(array('.hidden', 'a.jpg', 'b.css'), $failed, 'all three');

	pg_backup_delete_tree($base);
}

// A missing source has nothing to copy, so nothing fails.
function test_backup_copy_folder_missing_source_is_empty()
{
	pg_assert_same(array(), pg_backup_copy_folder(sys_get_temp_dir() . '/pg_backup_copy_none_' . uniqid(), sys_get_temp_dir()), 'empty');
}

// The message gives the count and only the first names.
function test_backup_copy_failure_message_counts_and_lists_first_names()
{
	$message = pg_backup_copy_failure_message(array('a.jpg', 'b.jpg', 'c.jpg', 'd.jpg', 'e.jpg'));

	pg_assert_true(strpos($message, '5 ') === 0, 'count first: ' . $message);
	pg_assert_true(strpos($message, 'a.jpg, b.jpg, c.jpg, ...') !== false, 'first three: ' . $message);
	pg_assert_true(strpos($message, 'd.jpg') === false, 'fourth not listed');
	pg_assert_true(strpos($message, 'Free some disk space') !== false, 'advice');

	$one = pg_backup_copy_failure_message(array('logo.png'));
	pg_assert_true(strpos($one, '(logo.png)') !== false, 'single name, no ellipsis: ' . $one);
}

// The Backup Manager's copy steps answer an error and remove the folder.
function test_backup_manager_copy_steps_report_failures()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/software.php');

	foreach (array('move_files' => 'FILE_DIRECTORY_PATH', 'move_layouts' => 'LAYOUT_DIRECTORY_PATH') as $step => $constant) {
		$start = strpos($source, "case '" . $step . "':");
		pg_assert_true($start !== false, $step);
		$body = substr($source, $start, strpos($source, 'break;', $start) - $start);

		pg_assert_true(strpos($body, 'pg_backup_copy_folder(' . $constant) !== false, $step . ' uses the helper');
		pg_assert_true(strpos($body, "'status' => 'error'") !== false, $step . ' answers an error');
		pg_assert_true(strpos($body, 'pg_backup_delete_tree(') !== false, $step . ' removes the folder');
		pg_assert_false((bool) preg_match('/(?<![_\w])copy\(/', $body), $step . ' has no bare copy()');
	}
}
