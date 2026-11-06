<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The comment form's attachment (add_comment.php): a file PHP did not take in
 * whole (over upload_max_filesize, cut short) arrives with an empty tmp_name.
 * It is refused at validation, before the comment or a files row is written,
 * and the files row follows only a copy() that returned true.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comment_upload_refusal_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/add_comment.php');
}

function test_comment_upload_error_refused_before_the_error_redirect()
{
	$src = comment_upload_refusal_source();

	$refusal  = strpos($src, "pg_upload_file_refusal(\$_FILES['file']");
	$redirect = strpos($src, "if (\$liveform->check_form_errors() == true)");
	$copy     = strpos($src, 'copy($file_temp_name');

	pg_assert_true($refusal !== false, 'add_comment.php checks the upload error code');
	pg_assert_true($redirect !== false && $copy !== false, 'error redirect and copy found');
	pg_assert_true($refusal < $redirect, 'the refusal is marked before the form errors send the visitor back');
	pg_assert_true($refusal < $copy, 'the refusal comes before the file is copied');
}

function test_comment_upload_refusal_comes_before_the_review_photo_check()
{
	$src = comment_upload_refusal_source();

	$refusal = strpos($src, "pg_upload_file_refusal(\$_FILES['file']");
	$photo   = strpos($src, 'pg_review_photo_ok(');

	pg_assert_true($refusal !== false && $photo !== false, 'both checks found');
	pg_assert_true($refusal < $photo, 'an upload that did not arrive is not read as a photo');
}

function test_comment_files_row_follows_a_successful_copy()
{
	$src = comment_upload_refusal_source();

	$copy = strpos($src, 'if (!@copy($file_temp_name');
	pg_assert_true($copy !== false, 'the result of copy() is checked');

	$insert = strpos($src, 'INSERT INTO files', (int) $copy);
	pg_assert_true($insert !== false, 'the files row follows the copy');
	pg_assert_contains('output_error(', substr($src, (int) $copy, $insert - (int) $copy), 'a failed copy ends the request before the files row');
}

function test_comment_upload_refusal_answers_for_an_oversize_file()
{
	$file = array('name' => 'photo.jpg', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0);

	pg_assert_true(pg_upload_file_refusal($file, 2097152) !== '', 'an oversize file is refused');
	pg_assert_same('', pg_upload_file_refusal(array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0), 2097152), 'no file chosen is no refusal');
}
