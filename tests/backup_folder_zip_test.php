<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_backup_folder_zip_current() in includes/fn/backup.php: whether
 * the archive the Backup Manager keeps inside a backup folder for its
 * Download button still holds that folder, or a later update of the backup
 * (same name, or the weekly automatic folder) left it behind; and for
 * pg_backup_folder_zip_write(), the one writer of a folder backup's archive:
 * "/" between folders on every server, and no archive of the folder packed
 * into it.
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

// Entry names use "/" between folders, also from a Windows path.
function test_backup_zip_entry_name_uses_slashes()
{
	pg_assert_same('files/3940.jpg', pg_backup_zip_entry_name('C:\\inetpub\\site\\pinegrap\\data\\backups\\b', 'C:\\inetpub\\site\\pinegrap\\data\\backups\\b\\files\\3940.jpg'), 'a Windows path');
	pg_assert_same('layouts/a/b.php', pg_backup_zip_entry_name('/srv/data/backups/b/', '/srv/data/backups/b/layouts/a/b.php'), 'a Unix path, root with a slash');
	pg_assert_same('sql.sql', pg_backup_zip_entry_name('/srv/data/backups/b', '/srv/data/backups/b/sql.sql'));
	pg_assert_same('', pg_backup_zip_entry_name('/srv/data/backups/b', '/srv/data/backups/b'), 'the folder itself');
	pg_assert_same('', pg_backup_zip_entry_name('/srv/data/backups/b', '/srv/data/backups/b2/sql.sql'), 'a sibling sharing the prefix');
	pg_assert_same('', pg_backup_zip_entry_name('', '/srv/x'), 'no root');
}

// The archive holds the backup's own files once: the download archive the
// Backup Manager keeps inside the folder is left out, and so is the archive
// being written; an empty folder is kept.
function test_backup_folder_zip_write_packs_the_backup_once()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$base = backup_folder_zip_fixture();
	mkdir($base . '/layouts/empty', 0777, true);
	file_put_contents($base . '/' . basename($base) . '.zip', str_repeat('old download ', 100));

	$beside = $base . '.zip';
	$added = pg_backup_folder_zip_write($base, $beside);

	pg_assert_same(2, $added, 'sql.sql and files/a.jpg');

	$names = array();
	$zip = new ZipArchive();
	pg_assert_true($zip->open($beside) === true, 'the archive opens');
	for ($i = 0; $i < $zip->numFiles; $i++) {
		$names[] = $zip->getNameIndex($i);
	}
	$zip->close();
	sort($names);

	pg_assert_same(array('files/', 'files/a.jpg', 'layouts/', 'layouts/empty/', 'sql.sql'), $names, 'no download archive inside, folders with "/"');

	// Written inside the folder, as the Backup Manager does, the old archive
	// there is not packed into the new one either.
	$inside = $base . '/' . basename($base) . '.zip';
	pg_assert_same(2, pg_backup_folder_zip_write($base, $inside), 'the old archive is left out');

	$zip = new ZipArchive();
	$zip->open($inside);
	pg_assert_same(false, $zip->locateName(basename($inside)), 'no zip inside the zip');
	$zip->close();

	pg_assert_same(false, pg_backup_folder_zip_write($base . '/none', $base . '/none.zip'), 'a missing folder writes nothing');
	pg_assert_false(is_file($base . '/none.zip'));

	@unlink($beside);
	pg_backup_delete_tree($base);
}

// Both writers go through it: the Backup Manager's Download and the File
// Manager's Zip.
function test_backup_folder_zip_writers_share_one_function()
{
	$manager = (string) file_get_contents(PG_FUNCTIONS_DIR . '/backups.php');
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_backups.php');

	pg_assert_contains('pg_backup_folder_zip_write($rootPath, $zip_file)', $manager);
	pg_assert_contains('pg_backup_folder_zip_write($path, $zip_path)', $explorer);
	pg_assert_false(strpos($manager, 'substr($filePath, strlen($rootPath) + 1)') !== false, 'no name built from a Windows path');
	pg_assert_false(strpos($explorer . $manager, '->addFile(') !== false, 'neither writes entries of its own');
}
