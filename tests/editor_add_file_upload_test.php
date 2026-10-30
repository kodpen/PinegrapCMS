<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the rich text editor's Image > Upload (editor_add_file.php):
 * a body PHP discarded for being over post_max_size, a missing file and any
 * upload error code are refused through the editor's error answer before the
 * file is copied, and the files row and the success answer follow only a
 * copy() that returned true.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function editor_add_file_upload_source()
{
	$src = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/editor_add_file.php');

	// Only the script's top level; the folder helper below exit() reads nothing.
	$end = strpos($src, 'function get_first_folder_id(');

	return ($end === false) ? $src : substr($src, 0, $end);
}

function test_editor_add_file_refusals_come_before_the_file_is_read()
{
	$src = editor_add_file_upload_source();

	$discarded = strpos($src, 'if (pg_post_body_discarded())');
	$no_file   = strpos($src, 'UPLOAD_ERR_NO_FILE');
	$refusal   = strpos($src, "pg_upload_file_refusal(\$_FILES['upload']");
	$name      = strpos($src, "pg_upload_name_blocked(\$_FILES['upload']['name'])");
	$copy      = strpos($src, 'copy($file_temp_name');

	pg_assert_true($discarded !== false, 'no discarded-body check');
	pg_assert_contains("pg_upload_body_discarded_message(\$upload_limits['post_max'])", $src, 'discarded body not named with post_max_size');
	pg_assert_true($no_file !== false, 'no missing-file refusal');
	pg_assert_true($refusal !== false, 'upload error code not read');
	pg_assert_true($name !== false && $copy !== false, 'name check or copy() missing');

	foreach (array('discarded-body check' => $discarded, 'missing-file refusal' => $no_file, 'error code refusal' => $refusal) as $label => $at) {
		pg_assert_true($at < $name, $label . ' runs after $_FILES is read');
		pg_assert_true($at < $copy, $label . ' runs after copy()');
	}
}

function test_editor_add_file_row_and_success_follow_a_successful_copy()
{
	$src = editor_add_file_upload_source();

	$copy = strpos($src, 'if (!@copy($file_temp_name');
	pg_assert_true($copy !== false, 'copy() result not checked');

	$failure = substr($src, (int) $copy, 400);
	pg_assert_contains('editor_add_file_refuse(', $failure, 'failed copy not refused');
	pg_assert_contains("'The server could not accept {var:1}. Please try again.'", $failure, 'failed copy not answered');

	// The refusal answer ends the script, so nothing after a failed copy runs.
	$helper = strpos($src, 'function editor_add_file_refuse(');
	pg_assert_true($helper !== false, 'no refusal helper');
	pg_assert_contains('exit();', substr($src, (int) $helper, 500), 'refusal does not stop the script');

	$insert  = strpos($src, 'INSERT INTO files');
	$success = strpos($src, 'escape_javascript(OUTPUT_PATH . encode_url_path($file_name))');
	pg_assert_true($insert !== false && $insert > $copy, 'files row written before the copy is checked');
	pg_assert_true($success !== false && $success > $copy, 'success answered before the copy is checked');
}

function test_editor_add_file_refusal_messages_are_escaped_for_javascript()
{
	$src = editor_add_file_upload_source();

	pg_assert_contains('function editor_add_file_refuse($message)', $src, 'no shared refusal answer');
	pg_assert_contains('escape_javascript($message)', $src, 'refusal message not escaped for JavaScript');
	pg_assert_contains('log_activity($message', $src, 'refusal not logged');
}
