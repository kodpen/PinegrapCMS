<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for All Design Files (view_design_files.php) and Edit Design File
 * (edit_design_file.php): the type read from the name, the image editor
 * configuration printed once outside the table, the Recycle Bin left out,
 * the POST branch, the blocked-name refusal, the optimize way back, the
 * dimensions label and the empty upload queue.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function design_files_screens_source($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
}

function test_file_name_extension_reads_the_name()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');

	pg_assert_same('woff2', pg_file_name_extension('glyphicons-halflings-regular.woff2'), 'not cut to four letters');
	pg_assert_same('png', pg_file_name_extension('Logo.PNG'), 'lower-cased');
	pg_assert_same('gz', pg_file_name_extension('backup.tar.gz'), 'the last part');
	pg_assert_same('', pg_file_name_extension('pg-test-x_php'), 'no dot, no extension');
	pg_assert_same('', pg_file_name_extension('name.'), 'a trailing dot is no extension');
	pg_assert_same('', pg_file_name_extension(''), 'nothing');
}

function test_design_files_list_prints_the_editor_once_outside_the_table()
{
	$source = design_files_screens_source('view_design_files.php');
	$rows_start = strpos($source, 'foreach ($files as $file) {');
	$rows_end = strpos($source, 'print', $rows_start);
	$rows = substr($source, $rows_start, $rows_end - $rows_start);

	pg_assert_true(strpos($rows, 'pg_image_editor_includes()') === false, 'no editor script in a row');
	pg_assert_contains("\$has_image ? pg_image_editor_includes() : ''", $source, 'printed once when a row has an image');
	pg_assert_true(strpos($source, "\$has_image ? pg_image_editor_includes() : ''") > strpos($source, '</table>'), 'after the table');
}

function test_design_files_list_leaves_the_recycle_bin_out()
{
	$source = design_files_screens_source('includes/fn/design_file_list_edit.php');

	pg_assert_contains('pg_design_files_bin_where()', $source, 'the list query skips the bin');
	pg_assert_contains("pg_file_name_extension(\$file['name'])", $source, 'the type comes from the name');
	pg_assert_contains('pg_image_dimensions_label(', $source, 'one dimensions format');
	pg_assert_contains("&send_to=' . urlencode(PATH . SOFTWARE_DIRECTORY . '/view_design_files.php')", $source, 'optimize comes back here');
}

function test_edit_design_file_posts_on_method_and_reads_the_checked_id()
{
	$source = design_files_screens_source('edit_design_file.php');
	$form = design_files_screens_source('includes/forms/design_file_form.php');

	pg_assert_contains("if (\$_SERVER['REQUEST_METHOD'] !== 'POST') {", $source, 'the branch follows the method, not the name');
	pg_assert_true(strpos($source, "empty(\$_POST['name'])") === false, 'an empty name no longer draws the form');
	pg_assert_true(strpos($source, "\$_GET['id']") === false, 'the checked $id is used everywhere');
	pg_assert_contains('pg_design_files_bin_where()', $source, 'a file in the bin is not found');
	pg_assert_contains('pg_upload_name_blocked($name_posted)', $form, 'a blocked new name is refused');
	pg_assert_contains('pg_upload_blocked_message($name_posted)', $form, 'with the upload message');
	pg_assert_contains('$file_extension = pg_file_name_extension($name);', $form, 'no dot, no type');
	pg_assert_contains('pg_image_dimensions_label(', $source, 'one dimensions format');
	pg_assert_contains("'webp'", substr($source, strpos($source, '$preview_types'), 200), 'WebP gets a preview');
	pg_assert_contains("&send_to=' . urlencode(PATH . SOFTWARE_DIRECTORY . '/view_design_files.php')", $source, 'optimize comes back to the design list');

	$delete = strpos($source, "if (!empty(\$_POST['delete']))");
	$blank = strpos($source, 'pg_design_file_form_refusal($name_posted, $stored_name, $id)');
	pg_assert_true(($delete !== false) && ($blank !== false) && ($delete < $blank), 'Delete works with an empty name box');
}

function test_add_design_file_refuses_an_empty_queue()
{
	$source = design_files_screens_source('add_design_file.php');

	pg_assert_contains('myDropzone.getQueuedFiles().length === 0', $source, 'the queue is looked at');
	pg_assert_contains("lang('Please select a file.')", $source, 'and an empty one says so');
}

function test_design_files_bin_where_lists_the_bin_folders()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');

	pg_assert_same(' AND (files.folder NOT IN (300, 358))', pg_design_files_bin_where(array('300', 358, 300)), 'the bin and its sub-folders, once each');
	pg_assert_same('', pg_design_files_bin_where(array()), 'no bin, no condition');
}
