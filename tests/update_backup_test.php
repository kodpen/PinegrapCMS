<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the backup an update takes before it replaces the files, and the
 * rollback to it (includes/fn/update.php): the archive names that may be
 * read, pruned and rolled back to, the entry names that may be written under
 * the web root, and the bytecode cache helper the replace step and the
 * rollback share.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The name carries the installed version and the second it was taken, and
// is a name the gate accepts.
function test_update_backup_name_round_trip()
{
	$time = mktime(9, 30, 5, 10, 19, 2026);

	pg_assert_same('pre-update-2026.11.0-20261019093005.zip', pg_update_backup_name('2026.11.0', $time));
	pg_assert_true(pg_update_backup_is_name(pg_update_backup_name('2026.11.0', $time)));
	pg_assert_true(pg_update_backup_is_name(pg_update_backup_name('2026', $time)));
	pg_assert_same('pre-update-2026.4.10-20261019093005.zip', pg_update_backup_name('2026.4.10<script>', $time));
}

// Only the names pg_update_backup_name() writes pass the gate in front of
// every read, delete and rollback.
function test_update_backup_is_name_rejects_the_rest()
{
	foreach (array(
		'pre-update-2026.11.0-2026101909300.zip',
		'pre-update-2026.11.0-20261019093005.zip.php',
		'../pre-update-2026.11.0-20261019093005.zip',
		'pre-update-2026.11.0-20261019093005',
		'pre-update--20261019093005.zip',
		'auto_backup_2026-10-43.zip',
		'pre-update-2026.11.0-20261019093005.ZIP',
		'',
	) as $name) {
		pg_assert_false(pg_update_backup_is_name($name), $name);
	}

	pg_assert_false(pg_update_backup_is_name(null));
	pg_assert_false(pg_update_backup_is_name(array('pre-update-2026.11.0-20261019093005.zip')));
}

// A package entry is written under the web root only as a plain relative file
// name; backslashes are read as separators.
function test_update_backup_safe_entry()
{
	pg_assert_same('pinegrap/init.php', pg_update_safe_entry('pinegrap/init.php'));
	pg_assert_same('pinegrap/includes/fn/core.php', pg_update_safe_entry('pinegrap\\includes\\fn\\core.php'));
	pg_assert_same('index.php', pg_update_safe_entry('index.php'));

	foreach (array(
		'',
		'pinegrap/',
		'/etc/passwd',
		'C:/Windows/win.ini',
		'c:evil.php',
		'pinegrap/../../outside.php',
		'../outside.php',
		'pinegrap/./init.php',
		'pinegrap//init.php',
		"pinegrap/in\0it.php",
	) as $name) {
		pg_assert_same('', pg_update_safe_entry($name), $name);
	}
}

// The bytecode cache helper answers in one shape whatever the server has.
function test_update_clear_opcache_shape()
{
	$answer = pg_update_clear_opcache(array('/nowhere/file.php'));

	pg_assert_same(array('running', 'cleared'), array_keys($answer));
	pg_assert_true(is_bool($answer['cleared']));
	pg_assert_true(($answer['running'] === null) || is_bool($answer['running']));
}

// The update's step list and the replace step keep the backup between the
// download and the files being written.
function test_update_backup_step_is_before_replace()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/software.php');

	pg_assert_true(strpos($source, "array('check', 'download', 'backup', 'replace')") !== false);
	pg_assert_true(strpos($source, "case 'backup':") < strpos($source, "case 'replace':"));

	foreach (array('software_update.php', 'software_repair.php') as $screen) {
		$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);
		$backup = strpos($script, 'step: "backup"');
		$replace = strpos($script, 'step: "replace"');
		pg_assert_true(($backup !== false) && ($replace !== false) && ($backup < $replace), $screen);
	}
}
