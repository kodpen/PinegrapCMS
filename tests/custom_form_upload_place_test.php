<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A custom form's file upload (custom_form.php): pg_cf_place_upload() copies
 * only a whole upload and answers false, leaving nothing behind, for an error
 * code, an empty temporary name or a copy that fails; the script refuses an
 * upload error code at validation and places every file, with its files row,
 * before the forms row is written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function custom_form_upload_place_dir()
{
	$dir = sys_get_temp_dir() . '/pg_cf_place_' . getmypid() . '_' . mt_rand();
	mkdir($dir);

	return $dir;
}

function custom_form_upload_place_clean($dir)
{
	foreach ((array) glob($dir . '/*') as $file) {
		@unlink($file);
	}
	@rmdir($dir);
}

function test_custom_form_place_upload_copies_a_whole_upload()
{
	$dir = custom_form_upload_place_dir();
	$tmp = $dir . '/upload.tmp';
	file_put_contents($tmp, 'cv');

	$ok = pg_cf_place_upload(array('error' => UPLOAD_ERR_OK, 'tmp_name' => $tmp), $dir . '/cv.pdf');

	pg_assert_true($ok === true, 'a whole upload was not placed');
	pg_assert_same('cv', (string) @file_get_contents($dir . '/cv.pdf'), 'placed file differs');

	custom_form_upload_place_clean($dir);
}

function test_custom_form_place_upload_refuses_error_codes_and_empty_names()
{
	$dir = custom_form_upload_place_dir();

	foreach (array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE) as $code) {
		$ok = pg_cf_place_upload(array('error' => $code, 'tmp_name' => ''), $dir . '/a.pdf');
		pg_assert_true($ok === false, 'error code ' . $code . ' placed');
	}

	pg_assert_true(pg_cf_place_upload(array('error' => UPLOAD_ERR_OK, 'tmp_name' => ''), $dir . '/a.pdf') === false, 'empty tmp_name placed');
	pg_assert_true(pg_cf_place_upload(array('error' => UPLOAD_ERR_OK, 'tmp_name' => $dir . '/missing'), $dir . '/a.pdf') === false, 'missing temp file placed');
	pg_assert_true(pg_cf_place_upload('x', $dir . '/a.pdf') === false, 'non-array placed');
	pg_assert_true(!file_exists($dir . '/a.pdf'), 'a refused upload left a file');

	custom_form_upload_place_clean($dir);
}

function test_custom_form_place_upload_answers_false_when_the_copy_fails()
{
	$dir = custom_form_upload_place_dir();
	$tmp = $dir . '/upload.tmp';
	file_put_contents($tmp, 'cv');

	// A folder that does not exist stands for a full disk or a folder the
	// web server cannot write.
	$ok = pg_cf_place_upload(array('error' => UPLOAD_ERR_OK, 'tmp_name' => $tmp), $dir . '/no_such_folder/cv.pdf');

	pg_assert_true($ok === false, 'a failed copy answered true');

	custom_form_upload_place_clean($dir);
}

function custom_form_upload_place_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/custom_form.php');
}

function test_custom_form_refuses_an_upload_error_code_at_validation()
{
	$src = custom_form_upload_place_source();

	$refusal = strpos($src, 'pg_upload_file_refusal($_FILES[$field[\'id\']]');
	$insert  = strpos($src, 'INSERT INTO forms');

	pg_assert_true($refusal !== false, 'upload error code not refused');
	pg_assert_true($refusal < $insert, 'upload error code read after the forms row');
}

function test_custom_form_places_files_before_the_forms_row()
{
	$src = custom_form_upload_place_source();

	$place  = strpos($src, 'pg_cf_place_upload(');
	$insert = strpos($src, 'INSERT INTO forms');

	pg_assert_true($place !== false, 'uploads not placed through pg_cf_place_upload()');
	pg_assert_true($place < $insert, 'uploads placed after the forms row');
	pg_assert_true(strpos($src, "\ncopy(\$file_temp_name") === false && preg_match('/^\s*copy\(\$file_temp_name/m', $src) === 0, 'an unchecked copy() is left');

	// A failed copy marks the field and takes back the files placed before it.
	$failure = substr($src, (int) $place, 900);
	pg_assert_contains('mark_error(', $failure, 'a failed copy does not send the form back');
	pg_assert_contains('pg_cf_remove_placed_uploads(', $failure, 'files placed before a failed copy are kept');
}
