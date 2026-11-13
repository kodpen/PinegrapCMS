<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The installer's list of backups on the server (get_site_backups() in
 * install/index.php) is sorted by when each backup was taken: a folder by its
 * dump's time (pg_backup_taken_time()), never the folder's own time, which
 * moves whenever anything is written into the folder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_backup_install_list_reads_when_a_folder_was_taken()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	$start = strpos($source, 'function get_site_backups() {');
	$list = substr($source, (int) $start, (int) strpos($source, 'function get_site_backup(', (int) $start) - (int) $start);

	pg_assert_true($start !== false, 'the installer lists the backups');
	pg_assert_contains("'modified' => pg_backup_taken_time(\$directory_path . \$entry),", $list, 'a folder at its dump\'s time');
	pg_assert_contains("'modified' => @filemtime(\$directory_path . \$entry),", $list, 'an archive at its own time');
	pg_assert_same(1, substr_count($list, '@filemtime('), 'and only an archive');

	// The time it reads: the dump's, whatever the folder's own says.
	$folder = sys_get_temp_dir() . '/pg_backup_install_list_' . uniqid('', true);
	mkdir($folder);
	file_put_contents($folder . '/sql.sql', 'x');
	touch($folder . '/sql.sql', 1700000000);
	touch($folder, 1800000000);

	pg_assert_same(1700000000, pg_backup_taken_time($folder));

	pg_backup_delete_tree($folder);
}
