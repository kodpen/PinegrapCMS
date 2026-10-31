<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the image editor's server (includes/fn/image_editor.php) keeps
 * out of the Recycle Bin: a binned file is neither opened nor saved (replace
 * or copy), and a new picture is never written into the bin folder, as the
 * File Manager's upload and move refuse it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function in includes/fn/image_editor.php.
function image_editor_recycle_body($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');
	$start = strpos($source, "\nfunction " . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) : $end - $start);
}

// open, replace and copy all go through pg_image_editor_file(): a file in
// the bin folder (or below it) is answered as not found before anything else
// reads it.
function test_image_editor_file_refuses_binned_file()
{
	$body = image_editor_recycle_body('pg_image_editor_file');

	pg_assert_true($body !== '', 'pg_image_editor_file found');
	pg_assert_contains("pg_folder_binned((int) \$row['folder'])", $body, 'binned folder checked');

	$binned = strpos($body, 'pg_folder_binned(');
	pg_assert_true($binned < strpos($body, 'check_edit_access('), 'checked before the access check');
	pg_assert_true($binned < strpos($body, 'getimagesize('), 'checked before the file is read');
	pg_assert_true(function_exists('pg_folder_binned'), 'pg_folder_binned loaded beside the editor');
}

// The 'new' mode writes into folder_id: the bin folder is refused there.
function test_image_editor_new_refuses_bin_folder()
{
	$body = image_editor_recycle_body('pg_image_editor_save');
	$branch = substr($body, 0, (int) strpos($body, 'pg_image_editor_take_png('));

	pg_assert_true($branch !== '', 'artboard branch found');
	pg_assert_contains('pg_folder_binned($folder)', $branch, 'bin folder refused for a new picture');
}
