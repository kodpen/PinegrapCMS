<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the CSV, zip and theme import screens refuse a file the
 * server did not take in whole (includes/fn/files.php): the sentence names
 * the file and the limit, the limit line under each box takes the lower of
 * the server's and the screen's own limit, and every import screen reads the
 * upload error code and a body PHP threw away before it opens the file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_upload_error_message_names_the_file_and_limit()
{
	pg_assert_same('', pg_upload_error_message('kişiler.csv', UPLOAD_ERR_OK, 2097152), 'nothing to say');

	$large = pg_upload_error_message('kişiler.csv', UPLOAD_ERR_INI_SIZE, 2097152);
	pg_assert_contains('kişiler.csv', $large, 'the file is named');
	pg_assert_contains(convert_bytes_to_string(2097152, 1), $large, 'the limit is named');
	pg_assert_same($large, pg_upload_error_message('kişiler.csv', UPLOAD_ERR_FORM_SIZE, 2097152), 'the form limit reads as the server one');

	pg_assert_contains('kişiler.csv', pg_upload_error_message('kişiler.csv', UPLOAD_ERR_PARTIAL, 0), 'a partial file is named');
	pg_assert_same(pg_upload_error_message('a.csv', UPLOAD_ERR_CANT_WRITE, 0), pg_upload_error_message('a.csv', UPLOAD_ERR_NO_TMP_DIR, 0), 'server faults read alike');
}

function test_upload_file_refusal_reads_the_entry()
{
	pg_assert_same('', pg_upload_file_refusal(null, 2097152), 'no entry: the screen says choose a file');
	pg_assert_same('', pg_upload_file_refusal(array('name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE), 2097152), 'no file chosen: the screen says choose a file');
	pg_assert_same(
		pg_upload_error_message('ürünler.csv', UPLOAD_ERR_INI_SIZE, 2097152),
		pg_upload_file_refusal(array('name' => 'ürünler.csv', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_INI_SIZE), 2097152),
		'an oversize file is refused with the limit');
	pg_assert_same(
		pg_upload_error_message('site.zip', UPLOAD_ERR_PARTIAL, 0),
		pg_upload_file_refusal(array('name' => '../x/site.zip', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_PARTIAL), 0),
		'only the last segment of the name is shown');
}

function test_upload_limit_sentence_takes_the_lower_limit()
{
	pg_assert_same('', pg_upload_limit_sentence(0, 0), 'no limit, no line');
	pg_assert_contains(convert_bytes_to_string(2097152, 1), pg_upload_limit_sentence(0, 2097152), 'the server limit');
	pg_assert_contains(convert_bytes_to_string(1048576, 1), pg_upload_limit_sentence(1048576, 2097152), 'the screen limit when lower');
	pg_assert_contains(convert_bytes_to_string(2097152, 1), pg_upload_limit_sentence(5242880, 2097152), 'the server limit when lower');
	pg_assert_contains(convert_bytes_to_string(5242880, 1), pg_upload_limit_sentence(5242880, 0), 'the screen limit on an unlimited server');
}

function test_upload_body_discarded_message_names_the_limit()
{
	$sentence = pg_upload_body_discarded_message(8388608);
	pg_assert_contains(lang('The request was too large for this server and arrived empty.'), $sentence);
	pg_assert_contains(convert_bytes_to_string(8388608, 1), $sentence, 'the limit is named');
	pg_assert_same(lang('The request was too large for this server and arrived empty.'), pg_upload_body_discarded_message(0), 'no limit known');
}

// Each import screen: the discarded body is refused before the form branch,
// the error code is read before the file is opened, and the box states its
// limit.
function upload_error_message_screens()
{
	return array(
		'import_contacts.php'                 => array("if (!\$_POST)", 'pg_csv_open('),
		'import_key_codes.php'                => array("if (!\$_POST)", 'pg_csv_open('),
		'import_email_campaign_profiles.php'  => array("if (!\$_POST)", 'pg_csv_open('),
		'import_submitted_forms.php'          => array("if (!\$_POST)", 'pg_csv_open('),
		'import_users.php'                    => array("if (!\$_POST)", 'pg_csv_open('),
		'import_products.php'                 => array("if (!\$_POST)", 'pg_csv_open('),
		'import_zip.php'                      => array("if (!\$_POST)", 'pg_zip_import_open('),
		'import_shipments.php'                => array("if (\$_SERVER['REQUEST_METHOD'] === 'POST')", 'pg_shipping_import_run('),
		'import_short_links.php'              => array("if (\$_SERVER['REQUEST_METHOD'] === 'POST')", 'Choose a CSV file to import.'),
		'workspace_wtables.php'               => array("\$user = validate_user();", 'Choose a file to import.'),
		'add_theme_file.php'                  => array("if (!\$_POST)", 'copy($file_temp_name'),
	);
}

function test_upload_error_message_import_screens_refuse_before_opening()
{
	foreach (upload_error_message_screens() as $file => $marks) {
		$src = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);

		$discarded = strpos($src, 'pg_upload_refuse_discarded_body();');
		$form = strpos($src, $marks[0]);
		pg_assert_true(($discarded !== false) && ($form !== false) && ($discarded < $form), $file . ': discarded body not refused before the form branch');

		$refusal = strpos($src, 'pg_upload_file_refusal(');
		$open = strpos($src, $marks[1]);
		pg_assert_true(($refusal !== false) && ($open !== false) && ($refusal < $open), $file . ': upload error code not read before the file is opened');

		pg_assert_contains('pg_upload_limit_hint(', $src, $file . ': no limit line under the box');
	}
}

function test_upload_error_message_translations_import()
{
	$action = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/translations_action.php');

	$discarded = strpos($action, 'pg_upload_refuse_discarded_body();');
	$json = strpos($action, "json_decode((string) @file_get_contents('php://input')");
	pg_assert_true(($discarded !== false) && ($discarded < $json), 'discarded form body not refused before the JSON branch');
	pg_assert_contains("multipart/form-data", substr($action, 0, $json), 'a JSON call is not mistaken for a discarded form');

	$refusal = strpos($action, "pg_upload_file_refusal(\$_FILES['csv']");
	$read = strpos($action, "file_get_contents(\$_FILES['csv']['tmp_name'])");
	pg_assert_true(($refusal !== false) && ($refusal < $read), 'CSV upload error code not read before the file');

	pg_assert_contains('pg_upload_limit_hint(', (string) file_get_contents(dirname(__DIR__) . '/pinegrap/translations.php'), 'no limit line under the CSV box');
}

function test_upload_error_message_theme_file_required_is_translated()
{
	$src = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/add_theme_file.php');
	pg_assert_false(strpos($src, "mark_error('custom_css_file', 'File is required.')") !== false, 'untranslated File is required.');
}
