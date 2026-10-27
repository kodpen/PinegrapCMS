<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's zip, Office preview and rotate sub-action handlers
 * (includes/explorer/handle_zip.php, includes/explorer/handle_files_media.php):
 * the handlers live there, behind the gate, pg_explorer_handle() calls each
 * one for its sub-action, and the pure parts (the archive's name, the file
 * name an archive entry is extracted under, the formats a picture can be
 * turned in) are unchanged by the move.
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

// Each sub-action has its own handler, loaded by the explorer's functions and
// called from pg_explorer_handle().
function test_explorer_zip_and_media_handlers_live_in_their_own_files()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	$files = array(
		'handle_zip.php' => array(
			"case 'explorer_zip_create':" => 'pg_explorer_handle_zip_create',
			"case 'explorer_zip_extract':" => 'pg_explorer_handle_zip_extract'),
		'handle_files_media.php' => array(
			"case 'explorer_office_preview':" => 'pg_explorer_handle_office_preview',
			"case 'explorer_rotate':" => 'pg_explorer_handle_rotate'));

	foreach ($files as $file => $actions) {

		$handlers = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/' . $file);

		foreach ($actions as $case => $function) {
			pg_assert_contains('function ' . $function . '(', $handlers, $function);
			pg_assert_true(function_exists($function), $function . ' loaded');
			pg_assert_contains($case . "\n            " . $function . '($type, $request, $user);' . "\n            break;", $explorer, $function . ' is called by its case');
		}

		pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handlers, $file . ' gate');
		pg_assert_false(strpos($handlers, '__FILE__') !== false, $file . ' paths built from PG_FUNCTIONS_DIR');
		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/" . $file . "');", $explorer, $file . ' loaded by the explorer');
	}
}

// The archive is named as asked, with ".zip" added when the name does not
// already end in it; with no name it is called "archive".
function test_explorer_zip_archive_name()
{
	pg_assert_same('photos.zip', pg_explorer_zip_archive_name('photos'), 'extension added');
	pg_assert_same('photos.zip', pg_explorer_zip_archive_name('  photos.zip '), 'trimmed, extension kept');
	pg_assert_same('photos.ZIP', pg_explorer_zip_archive_name('photos.ZIP'), 'any case of the extension');
	pg_assert_same('photos.tar.zip', pg_explorer_zip_archive_name('photos.tar'), 'another extension');
	pg_assert_same(lang('archive') . '.zip', pg_explorer_zip_archive_name(''), 'no name');
	pg_assert_same(lang('archive') . '.zip', pg_explorer_zip_archive_name('   '), 'only spaces');
}

// An archive entry is extracted under its last segment, either separator; a
// folder entry gives nothing.
function test_explorer_zip_entry_file_name()
{
	pg_assert_same('cat.jpg', pg_explorer_zip_entry_file_name('cat.jpg'), 'a loose file');
	pg_assert_same('cat.jpg', pg_explorer_zip_entry_file_name('photos/2026/cat.jpg'), 'folders flattened');
	pg_assert_same('cat.jpg', pg_explorer_zip_entry_file_name('photos\\cat.jpg'), 'backslashes are separators');
	pg_assert_same('', pg_explorer_zip_entry_file_name('photos/'), 'a folder entry');
	pg_assert_same('', pg_explorer_zip_entry_file_name(''), 'no name');
}

// Only the formats the image engine decodes and encodes can be turned; an SVG
// has no pixels to turn.
function test_explorer_rotatable()
{
	foreach (array('a.jpg', 'a.JPEG', 'a.png', 'a.gif', 'a.bmp', 'a.tiff', 'a.tif', 'a.webp') as $name) {
		pg_assert_true(pg_explorer_rotatable($name), $name);
	}

	foreach (array('a.svg', 'a.pdf', 'a', 'jpg') as $name) {
		pg_assert_false(pg_explorer_rotatable($name), $name);
	}
}
