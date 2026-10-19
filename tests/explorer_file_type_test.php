<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the kind the file manager gives a file
 * (pg_explorer_file_type() in view_folder_and_files_f.php): files.type is
 * four characters wide, so the extension of the name decides, and the column
 * is only read for a name without one.
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

function test_explorer_file_type_comes_from_the_name()
{
	pg_assert_same('webmanifest', pg_explorer_file_type(array('name' => 'site.webmanifest', 'type' => 'webm')), 'a cut column does not make a video');
	pg_assert_same('markdown', pg_explorer_file_type(array('name' => 'README.markdown', 'type' => 'mark')), 'nor lose the editor');
	pg_assert_same('jpg', pg_explorer_file_type(array('name' => 'Photo.JPG', 'type' => 'JPG')), 'lower-cased');
	pg_assert_same('gz', pg_explorer_file_type(array('name' => 'backup.tar.gz', 'type' => 'gz')), 'the last part');
	pg_assert_same('pdf', pg_explorer_file_type(array('name' => 'noext', 'type' => 'pdf')), 'the column when the name has no extension');
	pg_assert_same('', pg_explorer_file_type(array('name' => '', 'type' => null)), 'nothing known');
}

function test_explorer_file_type_feeds_the_payload_and_the_editor_lists()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$start = strpos($source, 'function pg_explorer_file_payload(');
	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	pg_assert_contains('$type = pg_explorer_file_type($file);', $body, 'the payload asks the name');
	pg_assert_true(in_array('webmanifest', pg_explorer_editable_formats(), true), 'and a .webmanifest is editable text again');
	pg_assert_true(in_array('markdown', pg_explorer_editable_formats(), true), 'as is a .markdown');
}
