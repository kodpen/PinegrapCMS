<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the sheet question for the importers that read a workbook
 * themselves (includes/fn/sheet_import.php): Products > Import
 * (pg_products_import_to_csv(), import_products_f.php) and the bank
 * statement import (erp_bank_read_file(), includes/erp/bank_import.php).
 * A two-sheet workbook asks, the chosen sheet's rows are the ones read, a
 * one-sheet workbook does not ask, and an answer puts the kept workbook
 * back in the file field.
 *
 * The two-sheet fixtures are OS2a's (tests/fixtures/sheet_import/rows.*):
 * a sheet "Rows" and a sheet "Links" (from, to, code). The one-sheet
 * fixtures are the bank statement tests' (tests/fixtures/erp_bank/).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/import_products_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/bank_import.php');

/**
 * A fixture's path.
 */
function sheet_import_workbook_test_fixture($folder, $name)
{
	return dirname(PG_FUNCTIONS_DIR) . '/tests/fixtures/' . $folder . '/' . $name;
}

/**
 * The rows of a CSV file the product import wrote.
 */
function sheet_import_workbook_test_csv_rows($path)
{
	$rows = array();
	$handle = fopen($path, 'r');

	while (($row = fgetcsv($handle, 100000, ',', '"', '\\')) !== false) {
		$rows[] = $row;
	}

	fclose($handle);

	return $rows;
}

// -- tests -----------------------------------------------------------------

function test_sheet_import_workbook_two_sheets_ask_one_sheet_does_not()
{
	foreach (array('xlsx', 'ods', 'xls') as $kind) {
		pg_assert_same(array('Rows', 'Links'), pg_sheet_import_question_sheets(sheet_import_workbook_test_fixture('sheet_import', 'rows.' . $kind), $kind), $kind . ': two sheets ask');
		pg_assert_same(array(), pg_sheet_import_question_sheets(sheet_import_workbook_test_fixture('erp_bank', 'statement.' . $kind), $kind), $kind . ': one sheet does not ask');
	}

	pg_assert_same(array(), pg_sheet_import_question_sheets(__FILE__, 'xlsx'), 'a file that is no workbook is left to the importer');
}

function test_sheet_import_workbook_product_import_reads_the_chosen_sheet()
{
	$links = sheet_import_workbook_test_csv_rows(sheet_import_workbook_test_fixture('sheet_import', 'links.csv'));

	foreach (array('xlsx', 'ods', 'xls') as $kind) {
		$path = sheet_import_workbook_test_fixture('sheet_import', 'rows.' . $kind);

		$error = 'not run';
		$csv = pg_products_import_to_csv($path, 'rows.' . $kind, $error, 1);
		pg_assert_same('', $error, $kind . ': the second sheet is read');
		pg_assert_same($links, ($csv !== '') ? sheet_import_workbook_test_csv_rows($csv) : array(), $kind . ': the second sheet\'s rows');

		if ($csv !== '') {
			unlink($csv);
		}

		$csv = pg_products_import_to_csv($path, 'rows.' . $kind, $error);
		$first = ($csv !== '') ? sheet_import_workbook_test_csv_rows($csv) : array();
		pg_assert_same('order_number', $first[0][0] ?? '', $kind . ': with no sheet chosen the first is read');

		if ($csv !== '') {
			unlink($csv);
		}

		$csv = pg_products_import_to_csv($path, 'rows.' . $kind, $error, 2);
		pg_assert_same('', $csv, $kind . ': a sheet the workbook has not is refused');
		pg_assert_same(lang('The file could not be read.') === $error || lang('The file was empty.') === $error, true, $kind . ': with the importer\'s own text');
	}
}

function test_sheet_import_workbook_bank_import_reads_the_chosen_sheet()
{
	foreach (array('xlsx', 'ods', 'xls') as $kind) {
		$path = sheet_import_workbook_test_fixture('sheet_import', 'rows.' . $kind);

		$read = erp_bank_read_file($path, 'rows.' . $kind, 1);
		pg_assert_same('', $read['error'], $kind . ': the second sheet is read');
		pg_assert_same(array('from', 'to', 'code'), $read['rows'][0] ?? array(), $kind . ': the second sheet\'s heading');
		pg_assert_same(array('/eski', '/yeni'), array_slice($read['rows'][1] ?? array(), 0, 2), $kind . ': the second sheet\'s first row');
		pg_assert_same(3, count($read['rows']), $kind . ': the second sheet\'s rows only');

		$read = erp_bank_read_file($path, 'rows.' . $kind);
		pg_assert_same('order_number', $read['rows'][0][0] ?? '', $kind . ': with no sheet chosen the first is read');
	}
}

function test_sheet_import_workbook_answer_puts_the_workbook_back()
{
	$saved = array($_POST, $_FILES, $_SESSION['software']['sheet_import'] ?? null, $_SERVER['SCRIPT_NAME'] ?? null);
	$_SERVER['SCRIPT_NAME'] = '/pinegrap/erp_bank_statements.php';
	$_FILES = array();

	$token = str_repeat('ab', 16);
	$kept = pg_sheet_import_staged_path($token);
	pg_assert_same(true, $kept !== '' && copy(sheet_import_workbook_test_fixture('sheet_import', 'rows.ods'), $kept), 'the workbook is kept');
	$_SESSION['software']['sheet_import'] = array($token => array('name' => 'Statement.ods', 'kind' => 'ods', 'screen' => 'erp_bank_statements.php', 'time' => time()));

	$_POST = array();
	$error = 'not run';
	pg_assert_same(0, pg_sheet_import_workbook_answer('statement', $error), 'a first post reads the first sheet');
	pg_assert_same('', $error, 'a first post is no refusal');
	pg_assert_same(array(), $_FILES, 'a first post leaves the upload alone');

	$_POST = array('pg_sheet_token' => $token, 'pg_sheet' => '1', 'cash_account_id' => '4');
	pg_assert_same(1, pg_sheet_import_workbook_answer('statement', $error), 'the chosen sheet');
	pg_assert_same('', $error, 'the answer is taken');
	pg_assert_same('Statement.ods', $_FILES['statement']['name'] ?? '', 'the file keeps its name');
	pg_assert_same($kept, $_FILES['statement']['tmp_name'] ?? '', 'the kept workbook is the upload');
	pg_assert_same(UPLOAD_ERR_OK, $_FILES['statement']['error'] ?? null, 'an upload without error');
	pg_assert_same(true, pg_sheet_import_file($_FILES['statement']), 'the importer\'s upload check takes it');
	pg_assert_same('', pg_sheet_import_workbook_ask('statement', 'Bank statements'), 'the kept workbook does not ask again');
	pg_assert_same(false, isset($_SESSION['software']['sheet_import'][$token]), 'the token is spent');

	$_FILES = array();
	pg_assert_same(0, pg_sheet_import_workbook_answer('statement', $error), 'a spent token');
	pg_assert_same(lang('The upload has expired. Start again.'), $error, 'is refused as expired');
	pg_assert_same(array(), $_FILES, 'and puts nothing back');

	$_SESSION['software']['sheet_import'] = array($token => array('name' => 'Statement.ods', 'kind' => 'ods', 'screen' => 'import_products.php', 'time' => time()));
	pg_sheet_import_workbook_answer('statement', $error);
	pg_assert_same(lang('The upload has expired. Start again.'), $error, 'a token from another screen is refused');

	@unlink($kept);
	list($_POST, $_FILES, $session, $_SERVER['SCRIPT_NAME']) = $saved;
	$_SESSION['software']['sheet_import'] = $session;
}
