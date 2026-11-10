<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workflow table's Excel download: what each kind of cell
 * becomes (ws_wtable_export_cell()), the heading and the rows
 * (ws_wtable_export_sheet()), the file name, and the shared workbook
 * writer's dates, cells and file (includes/fn/xlsx_write.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen.php');

function test_wtable_export_cells_keep_numbers_as_numbers()
{
	$money = array('type' => 'money', 'options' => array('currency' => 'TRY', 'decimals' => 2));

	pg_assert_same(1250.5, ws_wtable_export_cell($money, 125050), 'money as its figure');
	pg_assert_same(-0.07, ws_wtable_export_cell($money, -7), 'small negative money');
	pg_assert_same(300, ws_wtable_export_cell(array('type' => 'money', 'options' => array('decimals' => 0)), 300), 'money without decimals');
	pg_assert_same(12, ws_wtable_export_cell(array('type' => 'number'), 12), 'a whole number');
	pg_assert_same(2.5, ws_wtable_export_cell(array('type' => 'number'), '2.5'), 'a figure kept as text');
	pg_assert_same('abc', ws_wtable_export_cell(array('type' => 'number'), 'abc'), 'text the store did not check');
	pg_assert_same(0.1, ws_wtable_export_cell(array('type' => 'formula'), 0.1), 'a formula result');
	pg_assert_same('#DIV0', ws_wtable_export_cell(array('type' => 'formula'), null, array(), 'div0'), 'a failed formula');
	pg_assert_same('', ws_wtable_export_cell(array('type' => 'number'), null), 'an empty cell');
}

function test_wtable_export_cells_word_the_rest()
{
	$status = array('type' => 'status', 'options' => array('choices' => array(array('key' => 'a1', 'label' => 'Open'))));
	$people = array(7 => array('name' => 'Ayşe Kaya'));

	pg_assert_same(array('date' => '2026-11-09'), ws_wtable_export_cell(array('type' => 'date'), '2026-11-09'), 'a date');
	pg_assert_same(array('date' => '2026-11-09 14:30'), ws_wtable_export_cell(array('type' => 'date'), '2026-11-09 14:30'), 'a date and time');
	pg_assert_same('soon', ws_wtable_export_cell(array('type' => 'date'), 'soon'), 'a date that is no date');
	pg_assert_same('Ayşe Kaya', ws_wtable_export_cell(array('type' => 'person'), 7, $people), 'a person by name');
	pg_assert_same('Open', ws_wtable_export_cell($status, 'a1'), 'a status by its label');
	pg_assert_same(lang('Yes'), ws_wtable_export_cell(array('type' => 'checkbox'), true), 'a ticked box');
	pg_assert_same(lang('No'), ws_wtable_export_cell(array('type' => 'checkbox'), null), 'an empty box');
	pg_assert_same('order #12', ws_wtable_export_cell(array('type' => 'link'), array('kind' => 'order', 'id' => 12)), 'a record link as the screen shows it');
	pg_assert_same("two\nlines", ws_wtable_export_cell(array('type' => 'text'), "two\nlines"), 'text as it is');
}

function test_wtable_export_sheet_heads_and_orders_the_rows()
{
	$columns = array(
		3 => array('name' => 'Item', 'type' => 'text', 'options' => array()),
		5 => array('name' => 'Price', 'type' => 'money', 'options' => array('currency' => 'EUR', 'decimals' => 2)),
		9 => array('name' => 'Total', 'type' => 'formula', 'options' => array()),
	);
	$rows = array(
		array('cells' => array(3 => 'Pen', 5 => 150, 9 => 3.0), 'formula_errors' => array()),
		array('cells' => array(5 => 200), 'formula_errors' => array(9 => 'ref')),
	);

	pg_assert_same(array(
		'headings' => array('Item', 'Price (EUR)', 'Total'),
		'rows' => array(array('Pen', 1.5, 3.0), array('', 2.0, '#REF')),
	), ws_wtable_export_sheet($columns, $rows));
}

function test_wtable_export_file_name_is_ascii()
{
	pg_assert_same('Musteri_listesi_2026.xlsx', ws_wtable_export_file_name('Müşteri listesi / 2026', 4));
	pg_assert_same('table-4.xlsx', ws_wtable_export_file_name('ДДД', 4));
}

function test_xlsx_date_serials()
{
	pg_assert_same(1, pg_xlsx_date_serial('1899-12-31'));
	pg_assert_same(46335, pg_xlsx_date_serial('2026-11-09'));
	pg_assert_same(46335.5, pg_xlsx_date_serial('2026-11-09 12:00'));
	pg_assert_same(null, pg_xlsx_date_serial('2026-02-30'));
	pg_assert_same(null, pg_xlsx_date_serial('tomorrow'));
}

function test_xlsx_sheet_rows_type_the_cells()
{
	pg_assert_same(
		'<row r="2"><c r="A2"><v>3</v></c><c r="B2" t="inlineStr"><is><t xml:space="preserve">a &amp; b</t></is></c><c r="D2" s="2"><v>46335</v></c><c r="E2" s="3"><v>46335.25</v></c><c r="F2" t="inlineStr"><is><t xml:space="preserve">soon</t></is></c></row>',
		pg_xlsx_sheet_rows(array(array(3, 'a & b', '', array('date' => '2026-11-09'), array('date' => '2026-11-09 06:00'), array('date' => 'soon'))), 2));
	pg_assert_same('AA', pg_xlsx_column_letter(26));
	pg_assert_same('Sheet1', pg_xlsx_sheet_title(' / '));
}

function test_xlsx_write_plain_makes_a_workbook()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pgxlsx');
	pg_assert_true(pg_xlsx_write_plain(array('Name', 'When'), array(array('Pen', array('date' => '2026-11-09'))), $path, 'Stock: [main]'), 'written');

	$zip = new ZipArchive();
	pg_assert_true($zip->open($path) === true, 'a zip');
	pg_assert_true(strpos((string) $zip->getFromName('xl/workbook.xml'), 'name="Stock   main"') !== false, 'the sheet name has no forbidden letter');
	pg_assert_true(strpos((string) $zip->getFromName('xl/worksheets/sheet1.xml'), '<c r="A1" s="1" t="inlineStr">') !== false, 'a bold heading');
	pg_assert_true(strpos((string) $zip->getFromName('xl/styles.xml'), 'numFmtId="14"') !== false, 'a date format');
	$zip->close();
	@unlink($path);
}
