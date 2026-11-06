<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_restore_place_folder() in includes/fn/backup_restore.php: the
 * restore puts a backup's files/ and layouts/ in place of the site's only
 * when the disk holds them, and names a file it could not write instead of
 * reporting the folder as copied.
 *
 * The tests run as root in cloud sessions, so a failing copy() is made by
 * putting a directory at the target file's path, never by chmod.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function restore_place_folder_fixture()
{
	$base = sys_get_temp_dir() . '/pg_restore_place_' . uniqid('', true);
	mkdir($base . '/backup/sub', 0777, true);
	mkdir($base . '/site/keep', 0777, true);
	file_put_contents($base . '/backup/a.jpg', str_repeat('a', 1000));
	file_put_contents($base . '/backup/b.css', 'body{}');
	file_put_contents($base . '/backup/sub/inner.txt', 'inner');
	file_put_contents($base . '/site/a.jpg', 'old');
	file_put_contents($base . '/site/gone.png', str_repeat('g', 500));
	return $base;
}

// With room on the disk the site's files are replaced by the backup's.
function test_restore_place_folder_replaces_the_site_files()
{
	$base = restore_place_folder_fixture();

	$placed = pg_restore_place_folder($base . '/backup', $base . '/site', 1073741824.0);

	pg_assert_true($placed['ok'], 'placed');
	pg_assert_same(2, $placed['copied'], 'two files copied');
	pg_assert_same(array(), $placed['failed'], 'nothing failed');
	pg_assert_same(str_repeat('a', 1000), file_get_contents($base . '/site/a.jpg'), 'backup content');
	pg_assert_false(file_exists($base . '/site/gone.png'), 'a file the backup lacks is removed');
	pg_assert_true(is_dir($base . '/site/keep'), 'a subfolder of the site is left alone');
	pg_assert_false(file_exists($base . '/site/sub'), 'a subfolder of the backup is not copied');

	pg_backup_delete_tree($base);
}

// Without room for the backup's files the site's own are kept untouched.
function test_restore_place_folder_without_room_keeps_the_site_files()
{
	$base = restore_place_folder_fixture();

	$placed = pg_restore_place_folder($base . '/backup', $base . '/site', 1024.0);

	pg_assert_false($placed['ok'], 'refused');
	pg_assert_true($placed['error'] !== '', 'says why');
	pg_assert_same('old', file_get_contents($base . '/site/a.jpg'), 'old file kept');
	pg_assert_true(is_file($base . '/site/gone.png'), 'nothing deleted');

	pg_backup_delete_tree($base);
}

// The site's own files count as room: they are deleted before the copy.
function test_restore_place_folder_counts_the_site_files_as_room()
{
	$base = restore_place_folder_fixture();

	// 1006 bytes needed + 16 MB margin; 503 bytes held by the site.
	$placed = pg_restore_place_folder($base . '/backup', $base . '/site', 16777216.0 + 1006 - 503);

	pg_assert_true($placed['ok'], 'exactly enough');

	pg_backup_delete_tree($base);
}

// A file that cannot be written is named, not counted as copied.
function test_restore_place_folder_names_a_failed_copy()
{
	$base = restore_place_folder_fixture();
	mkdir($base . '/site/b.css');

	$placed = pg_restore_place_folder($base . '/backup', $base . '/site', 1073741824.0);

	pg_assert_true($placed['ok'], 'placed');
	pg_assert_same(array('b.css'), $placed['failed'], 'the blocked file');
	pg_assert_same(1, $placed['copied'], 'only the other file');

	pg_backup_delete_tree($base);
}

// A backup folder that cannot be read leaves the site as it is.
function test_restore_place_folder_unreadable_backup_keeps_the_site_files()
{
	$base = restore_place_folder_fixture();

	$placed = pg_restore_place_folder($base . '/missing', $base . '/site', 1073741824.0);

	pg_assert_false($placed['ok'], 'refused');
	pg_assert_true(is_file($base . '/site/gone.png'), 'nothing deleted');

	pg_backup_delete_tree($base);
}

// The installation screen places both folders through the function and
// reports what it could not write.
function test_restore_place_folder_used_by_the_installation()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');

	pg_assert_same(2, substr_count($source, 'pg_restore_place_folder('), 'files and layouts');
	pg_assert_same(0, substr_count($source, "unlink(FILE_DIRECTORY_PATH . '/' . \$file)"), 'no delete before the room check');
	pg_assert_same(0, substr_count($source, "unlink(LAYOUT_DIRECTORY_PATH . '/' . \$file)"), 'no delete before the room check');
}
