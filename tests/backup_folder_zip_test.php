<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_backup_folder_zip_current() in includes/fn/backup.php: whether
 * the archive the Backup Manager keeps inside a backup folder for its
 * Download button still holds that folder, or a later update of the backup
 * (same name, or the weekly automatic folder) left it behind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function backup_folder_zip_fixture()
{
	$base = sys_get_temp_dir() . '/pg_backup_folder_zip_' . uniqid('', true);
	mkdir($base . '/files', 0777, true);
	file_put_contents($base . '/sql.sql', 'dump');
	file_put_contents($base . '/files/a.jpg', 'a');
	touch($base . '/sql.sql', 1000);
	touch($base . '/files/a.jpg', 1000);
	return $base;
}

// No archive yet: it has to be written.
function test_backup_folder_zip_missing_is_not_current()
{
	$base = backup_folder_zip_fixture();

	pg_assert_false(pg_backup_folder_zip_current($base . '/x.zip', $base), 'missing');

	pg_backup_delete_tree($base);
}

// An archive written after every file of the folder is served as it is.
function test_backup_folder_zip_newer_than_every_file_is_current()
{
	$base = backup_folder_zip_fixture();
	file_put_contents($base . '/x.zip', 'PK');
	touch($base . '/x.zip', 2000);

	pg_assert_true(pg_backup_folder_zip_current($base . '/x.zip', $base), 'current');

	pg_backup_delete_tree($base);
}

// The backup was updated after the download: the new dump is newer.
function test_backup_folder_zip_older_than_the_dump_is_stale()
{
	$base = backup_folder_zip_fixture();
	file_put_contents($base . '/x.zip', 'PK');
	touch($base . '/x.zip', 2000);
	touch($base . '/sql.sql', 3000);

	pg_assert_false(pg_backup_folder_zip_current($base . '/x.zip', $base), 'dump newer');

	pg_backup_delete_tree($base);
}

// A file copied into a subfolder later counts as well.
function test_backup_folder_zip_older_than_a_nested_file_is_stale()
{
	$base = backup_folder_zip_fixture();
	file_put_contents($base . '/x.zip', 'PK');
	touch($base . '/x.zip', 2000);
	touch($base . '/files/a.jpg', 3000);

	pg_assert_false(pg_backup_folder_zip_current($base . '/x.zip', $base), 'nested newer');

	pg_backup_delete_tree($base);
}

// A file written in the same second as the archive may have come after it.
function test_backup_folder_zip_same_second_is_stale()
{
	$base = backup_folder_zip_fixture();
	file_put_contents($base . '/x.zip', 'PK');
	touch($base . '/x.zip', 2000);
	touch($base . '/sql.sql', 2000);

	pg_assert_false(pg_backup_folder_zip_current($base . '/x.zip', $base), 'same second');

	pg_backup_delete_tree($base);
}

// A folder that cannot be read is never taken as current.
function test_backup_folder_zip_missing_folder_is_not_current()
{
	$base = backup_folder_zip_fixture();
	file_put_contents($base . '/x.zip', 'PK');

	pg_assert_false(pg_backup_folder_zip_current($base . '/x.zip', $base . '/none'), 'no folder');

	pg_backup_delete_tree($base);
}
