<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the archive helpers (includes/explorer/archives.php): the names an
 * extraction or a hand-placed backup file may not carry, and where the
 * explorer loads them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// The helpers live in their own file, loaded by the explorer's functions.
function test_explorer_archives_live_in_their_own_file()
{
	$archives = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/archives.php');

	foreach (array('pg_explorer_backup_root', 'pg_explorer_backup_path', 'pg_explorer_backup_copy_path', 'pg_explorer_backup_chmod_path', 'pg_explorer_backup_chmod_one', 'pg_explorer_backup_delete_path', 'pg_explorer_blocked_upload_names', 'pg_explorer_blocked_upload_extensions', 'pg_explorer_backup_upload_allowed_names', 'pg_explorer_backup_upload_blocked', 'pg_explorer_backup_entry_blocked', 'pg_explorer_archive_size', 'pg_explorer_unsafe_archive_name', 'pg_explorer_zip_add_folder') as $function) {
		pg_assert_contains('function ' . $function . '(', $archives, $function);
	}

	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $archives, 'gate');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/archives.php');", (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php'), 'loaded by the explorer');
}

// An extraction never writes a name that executes or reconfigures the server.
function test_explorer_archives_unsafe_names()
{
	pg_assert_true(pg_explorer_unsafe_archive_name('shell.php'), 'php');
	pg_assert_true(pg_explorer_unsafe_archive_name('a/b/shell.php.jpg'), 'php before the last extension');
	pg_assert_true(pg_explorer_unsafe_archive_name('.htaccess'), 'dotfile');
	pg_assert_true(pg_explorer_unsafe_archive_name('dir\\DKIM.KEY'), 'signing key, any case and separator');
	pg_assert_true(pg_explorer_unsafe_archive_name(''), 'no name');
	pg_assert_false(pg_explorer_unsafe_archive_name('photos/cat.jpg'), 'image');
}

// A backup comes out whole: PHP only under layouts/, no dotfile anywhere.
function test_explorer_archives_backup_entries()
{
	pg_assert_false(pg_explorer_backup_entry_blocked(array('layouts', 'home.php')), 'layout at the root');
	pg_assert_false(pg_explorer_backup_entry_blocked(array('site', 'layouts', 'home.php')), 'layout under one wrapper');
	pg_assert_true(pg_explorer_backup_entry_blocked(array('a', 'b', 'layouts', 'home.php')), 'layouts too deep');
	pg_assert_true(pg_explorer_backup_entry_blocked(array('files', 'x.php')), 'php outside layouts');
	pg_assert_true(pg_explorer_backup_entry_blocked(array('layouts', '.htaccess')), 'dotfile even in layouts');
	pg_assert_false(pg_explorer_backup_entry_blocked(array('sql.sql')), 'the dump');

	pg_assert_true(pg_explorer_backup_upload_blocked('.htaccess', array('sub')), '.htaccess in a sub-folder');
	pg_assert_true(pg_explorer_backup_upload_blocked('.htaccess'), '.htaccess without a known root');
	pg_assert_true(pg_explorer_backup_upload_blocked('x.php'), 'php by hand');
	pg_assert_false(pg_explorer_backup_upload_blocked('backup.zip'), 'an archive');
}
