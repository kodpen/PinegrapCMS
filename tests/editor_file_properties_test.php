<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the CKEditor file settings of get_wysiwyg_editor_code()
 * (includes/fn/editor.php, _pg_wysiwyg_file_properties()): browsing and
 * uploading are offered only to someone the upload endpoint accepts, and the
 * upload address carries the token and the folder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A visitor gets no Browse button, no Upload tab and no token in the page.
function test_editor_file_properties_refused()
{
	pg_assert_same('', _pg_wysiwyg_file_properties(false, '/pinegrap', 'abc123', 7));
}

// An allowed user gets the image browser and both upload addresses.
function test_editor_file_properties_allowed()
{
	$out = _pg_wysiwyg_file_properties(true, '/pinegrap', 'abc123', 7);
	pg_assert_true(strpos($out, 'filebrowserBrowseUrl') === false, 'no link window: the Link dialog opens the picker');
	pg_assert_true(strpos($out, 'filebrowserImageBrowseUrl: "/pinegrap/editor_select_image.php"') !== false, 'image browser');
	pg_assert_true(strpos($out, 'filebrowserUploadUrl: "/pinegrap/editor_add_file.php?token=abc123&folder_id=7"') !== false, 'upload');
	pg_assert_true(strpos($out, 'filebrowserImageUploadUrl: "/pinegrap/editor_add_file.php?token=abc123&folder_id=7"') !== false, 'image upload');
	pg_assert_true(strpos($out, 'filebrowserUploadMethod: "form"') !== false, 'method');
}

// No folder leaves the folder out; a value that is not a folder id is dropped.
function test_editor_file_properties_folder()
{
	pg_assert_true(strpos(_pg_wysiwyg_file_properties(true, '/p', 't', 0), 'editor_add_file.php?token=t"') !== false, 'no folder');
	pg_assert_true(strpos(_pg_wysiwyg_file_properties(true, '/p', 't', '3"x'), '&folder_id=3"') !== false, 'folder as number');
}

// The upload addresses sit inside the gate in get_wysiwyg_editor_code().
function test_editor_file_properties_wired()
{
	$src = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/editor.php');
	pg_assert_same(1, substr_count($src, 'editor_add_file.php'), 'only the helper names the upload endpoint');
}
