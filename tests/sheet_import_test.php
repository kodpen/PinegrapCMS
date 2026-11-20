<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the CSV importers' spreadsheet path (includes/fn/sheet_import.php):
 * the same rows as .csv, .xlsx, .ods and .xls read the same way by each
 * importer's own CSV reading; a two-sheet workbook names its sheets and
 * gives the chosen one; the sheet question's hidden fields and the refusal
 * texts.
 *
 * The fixtures (tests/fixtures/sheet_import/) were written by LibreOffice
 * 24.2 from one openpyxl workbook: a sheet "Rows" (numbers, text with a
 * comma and quotes, Turkish letters, dates, booleans, an empty row, empty
 * cells) and a sheet "Links" (short links); rows.csv and links.csv hold the
 * same rows as a person would save them as CSV.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/import.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipping_import.php');

/**
 * A fixture's path.
 */
function sheet_import_test_fixture($name)
{
	return dirname(PG_FUNCTIONS_DIR) . '/tests/fixtures/sheet_import/' . $name;
}

/**
 * One sheet of a workbook fixture as the CSV file the importers get, or the
 * error.
 */
function sheet_import_test_csv($kind, $sheet = 0)
{
	$target = tempnam(sys_get_temp_dir(), 'pg_sheet_import');
	$error = 'not run';
	$made = pg_sheet_import_csv(sheet_import_test_fixture('rows.' . $kind), $kind, $sheet, $target, $error);
	pg_sheet_import_made($target);

	return $made ? $target : $error;
}

/**
 * Rows as the contacts, users, submitted forms and campaign profiles
 * importers read them: pg_csv_open(), pg_csv_read_separator(), fgetcsv().
 */
function sheet_import_test_generic_rows($path)
{
	$handle = pg_csv_open($path);
	$separator = pg_csv_read_separator($handle);
	$rows = array();

	while (($row = fgetcsv($handle, 100000, $separator, '"', '\\')) !== false) {
		$rows[] = $row;
	}

	fclose($handle);

	return $rows;
}

/**
 * Rows as the key code importer reads them: its own separator guess on the
 * header line.
 */
function sheet_import_test_key_code_rows($path)
{
	$handle = pg_csv_open($path);
	$start = (int) ftell($handle);
	$separator = pg_key_code_import_separator((string) fgets($handle));
	fseek($handle, $start);
	$rows = array();

	while (($row = fgetcsv($handle, 100000, $separator, '"', '\\')) !== false) {
		$rows[] = $row;
	}

	fclose($handle);

	return $rows;
}

/**
 * Rows as the ERP accounts importer reads them.
 */
function sheet_import_test_erp_rows($path)
{
	$bytes = erp_import_to_utf8((string) file_get_contents($path));
	$first = strtok($bytes, "\n");

	return erp_import_read($path, erp_import_detect_delimiter(($first === false) ? '' : $first));
}

// -- tests -----------------------------------------------------------------

function test_sheet_import_names_the_worksheets_of_each_format()
{
	foreach (array('xlsx', 'ods', 'xls') as $kind) {
		$error = 'not run';
		pg_assert_same(array('Rows', 'Links'), pg_sheet_names(sheet_import_test_fixture('rows.' . $kind), $kind, $error), $kind);
		pg_assert_same('', $error, $kind . ' error');
	}
}

function test_sheet_import_each_importer_reads_a_workbook_as_its_csv()
{
	$csv = sheet_import_test_fixture('rows.csv');
	$expected = array(
		'generic'   => sheet_import_test_generic_rows($csv),
		'key codes' => sheet_import_test_key_code_rows($csv),
		'shipments' => pg_shipping_import_read(pg_csv_open($csv), 100),
		'erp'       => sheet_import_test_erp_rows($csv),
	);

	pg_assert_same(5, count($expected['generic']), 'the CSV fixture has a header, three rows and a blank line');
	pg_assert_same(array('10045', '1234567890', 'Yurtiçi Kargo', 'ayse@example.com', '2026-12-31', '1', 'Kapı 3, "arka"'), $expected['generic'][1]);

	foreach (array('xlsx', 'ods', 'xls') as $kind) {
		$path = sheet_import_test_csv($kind);
		pg_assert_true(is_file($path), $kind . ' converts');

		pg_assert_same($expected['generic'], sheet_import_test_generic_rows($path), $kind . ': contacts, users, forms, campaign profiles');
		pg_assert_same($expected['key codes'], sheet_import_test_key_code_rows($path), $kind . ': key codes');
		pg_assert_same($expected['shipments'], pg_shipping_import_read(pg_csv_open($path), 100), $kind . ': tracking numbers');
		pg_assert_same($expected['erp'], sheet_import_test_erp_rows($path), $kind . ': ERP accounts');
	}
}

function test_sheet_import_gives_the_chosen_sheet()
{
	$expected = pg_short_link_csv_rows((string) file_get_contents(sheet_import_test_fixture('links.csv')));
	pg_assert_same(2, count($expected), 'the short links fixture has two links');

	foreach (array('xlsx', 'ods', 'xls') as $kind) {
		$path = sheet_import_test_csv($kind, 1);
		pg_assert_same($expected, pg_short_link_csv_rows((string) file_get_contents($path)), $kind . ': the second sheet, as short links');
		pg_assert_same('sheet', sheet_import_test_csv($kind, 2), $kind . ': no third sheet');
	}
}

function test_sheet_import_refuses_a_damaged_workbook()
{
	$copy = tempnam(sys_get_temp_dir(), 'pg_sheet_import');
	pg_sheet_import_made($copy);

	foreach (array('xlsx', 'ods', 'xls') as $kind) {
		file_put_contents($copy, substr((string) file_get_contents(sheet_import_test_fixture('rows.' . $kind)), 0, 600));
		$error = 'not run';
		pg_assert_same(array(), pg_sheet_names($copy, $kind, $error), $kind . ' cut short');
		pg_assert_same('read', $error, $kind . ' cut short');
	}
}

function test_sheet_import_csv_separator_avoids_the_headings()
{
	$target = tempnam(sys_get_temp_dir(), 'pg_sheet_import');
	pg_sheet_import_made($target);

	pg_assert_same('"a","b"' . "\n", pg_sheet_import_csv_line(array('a', 'b'), ','));
	pg_assert_same('"say ""hi"""' . ";\"\"\n", pg_sheet_import_csv_line(array('say "hi"', ''), ';'));

	// A heading with a comma: the semicolon separates, and the guesses that
	// leave quoted headings out find it. (The key code guess counts inside
	// quotes, but its headings are fixed names without a comma.)
	$line = pg_sheet_import_csv_line(array('Last, First', 'email'), ';');
	pg_assert_same(';', pg_csv_separator($line));
	pg_assert_same(';', erp_import_detect_delimiter($line));
}

function test_sheet_import_question_posts_the_form_back()
{
	$html = pg_sheet_import_hidden_fields(array(
		'token'          => 'old',
		'pg_sheet_token' => 'x',
		'send_to'        => '/a?b=1&c="d"',
		'contact_group'  => array(3 => '1', 'x' => array('y' => '2')),
	));

	pg_assert_false(strpos($html, 'name="token"') !== false, 'the old form token is not posted again');
	pg_assert_false(strpos($html, 'pg_sheet_token') !== false, 'nor the sheet fields');
	pg_assert_contains('<input type="hidden" name="send_to" value="/a?b=1&amp;c=&quot;d&quot;">', $html);
	pg_assert_contains('name="contact_group[3]" value="1"', $html);
	pg_assert_contains('name="contact_group[x][y]" value="2"', $html);
}

function test_sheet_import_errors_are_the_importers_texts()
{
	pg_assert_same(lang('The file could not be read.'), pg_sheet_import_error_text('read'));
	pg_assert_same(lang('The file is too large.'), pg_sheet_import_error_text('large'));
	pg_assert_same(lang('The file was empty.'), pg_sheet_import_error_text('sheet'));
	pg_assert_same(lang('The file was empty.'), pg_sheet_import_error_text('empty'));
	pg_assert_same(lang('The upload has expired. Start again.'), pg_sheet_import_error_text('expired'));
}

function test_sheet_import_a_csv_upload_is_left_to_the_importer()
{
	$saved = $_FILES;
	$_FILES = array('file' => array('name' => 'contacts.csv', 'tmp_name' => '/nowhere', 'error' => UPLOAD_ERR_OK, 'size' => 1));
	pg_assert_same('', pg_sheet_import_upload('file', 'Import'), 'a file PHP did not receive is not touched');
	pg_assert_same('/nowhere', $_FILES['file']['tmp_name']);

	$_FILES = array();
	pg_assert_same('', pg_sheet_import_upload('file', 'Import'), 'no file: the importer says so itself');
	pg_assert_false(pg_sheet_import_file(null));
	$_FILES = $saved;
}
