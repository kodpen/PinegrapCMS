<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's backup sub-action handlers
 * (includes/explorer/handle_backups.php): the handlers live there, behind the
 * gate, pg_explorer_handle() calls each one for its sub-action, and the pure
 * parts (the breadcrumb of a backup path, the segments an archive entry is
 * written under) are unchanged by the move.
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

// Each backup sub-action has its own handler, loaded by the explorer's
// functions and called from pg_explorer_handle(); rename, copy and delete
// share one body, as they shared one case.
function test_explorer_backup_handlers_live_in_their_own_file()
{
	$handlers = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_backups.php');
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	$actions = array(
		"case 'explorer_backups_list':" => 'pg_explorer_handle_backups_list',
		"case 'explorer_backup_chmod':" => 'pg_explorer_handle_backup_chmod',
		"case 'explorer_backup_extract':" => 'pg_explorer_handle_backup_extract',
		"case 'explorer_backup_upload':" => 'pg_explorer_handle_backup_upload',
		"case 'explorer_backup_rename':\n        case 'explorer_backup_copy':\n        case 'explorer_backup_delete':" => 'pg_explorer_handle_backup_rename_copy_delete',
		"case 'explorer_backup_zip':" => 'pg_explorer_handle_backup_zip');

	foreach ($actions as $case => $function) {
		pg_assert_contains('function ' . $function . '(', $handlers, $function);
		pg_assert_true(function_exists($function), $function . ' loaded');
		pg_assert_contains($case . "\n            " . $function . '($type, $request, $user);' . "\n            break;", $explorer, $function . ' is called by its case');
	}

	pg_assert_true(function_exists('pg_explorer_backup_breadcrumb'), 'breadcrumb helper loaded');
	pg_assert_true(function_exists('pg_explorer_backup_entry_segments'), 'entry segments helper loaded');
	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handlers, 'gate');
	pg_assert_false(strpos($handlers, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_backups.php');", $explorer, 'loaded by the explorer');
}

// The breadcrumb walks the path one folder at a time, each crumb carrying the
// path up to it; the backup root itself has none.
function test_explorer_backup_breadcrumb()
{
	pg_assert_same(array(), pg_explorer_backup_breadcrumb(''), 'the root');

	pg_assert_same(
		array(array('name' => '2026', 'path' => '2026')),
		pg_explorer_backup_breadcrumb('2026'),
		'one folder');

	pg_assert_same(
		array(
			array('name' => '2026', 'path' => '2026'),
			array('name' => 'site', 'path' => '2026/site'),
			array('name' => 'files', 'path' => '2026/site/files')),
		pg_explorer_backup_breadcrumb('2026/site/files'),
		'three folders');
}

// An archive entry is written under its non-empty segments; one that climbs
// out with ".." is dropped whole, and one with nothing left gives nothing.
function test_explorer_backup_entry_segments()
{
	pg_assert_same(array('site', 'sql.sql'), pg_explorer_backup_entry_segments('site/sql.sql'), 'a plain entry');
	pg_assert_same(array('site', 'files'), pg_explorer_backup_entry_segments('site/files/'), 'a folder entry');
	pg_assert_same(array('a', 'b.txt'), pg_explorer_backup_entry_segments('./a//b.txt'), 'empty and dot segments are left out');
	pg_assert_same(array('a', 'b.txt'), pg_explorer_backup_entry_segments('a\\b.txt'), 'backslashes are separators');
	pg_assert_same(array(), pg_explorer_backup_entry_segments('../etc/passwd'), 'zip-slip at the start');
	pg_assert_same(array(), pg_explorer_backup_entry_segments('a/../../b'), 'zip-slip in the middle');
	pg_assert_same(array(), pg_explorer_backup_entry_segments('./'), 'nothing left');
}
