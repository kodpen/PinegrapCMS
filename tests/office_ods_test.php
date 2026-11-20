<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the .ods preview (includes/fn/office.php): the kind by the name,
 * a LibreOffice workbook read through the importers' sheet reader and shown
 * as the .xlsx preview shows one (every sheet in order, the hidden ones
 * flagged, numbers, booleans, dates and times as text, gaps kept), the
 * preview's row, column and cell limits, the reader's cell ceiling, the walk
 * the sheets share, the files refused, and the doors that offer it: view
 * only, never offered for editing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// An .ods on disk: content.xml holding the tables given, after the
// automatic styles given (and any padding), as LibreOffice lays it out.
function office_ods_test_file($tables, $styles = '', $padding = 0)
{
	$content = '<?xml version="1.0" encoding="UTF-8"?>'
		. '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
		. ' xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"'
		. ' xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"'
		. ' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">'
		. '<office:automatic-styles>' . $styles . (($padding > 0) ? '<!--' . str_repeat('x', $padding) . '-->' : '') . '</office:automatic-styles>'
		. '<office:body><office:spreadsheet>' . $tables . '</office:spreadsheet></office:body>'
		. '</office:document-content>';

	$path = tempnam(sys_get_temp_dir(), 'pg_office_ods_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);
	$zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
	$zip->addFromString('content.xml', $content);
	$zip->close();

	return $path;
}

// One table of the rows given, each a list of cells' XML.
function office_ods_test_table($name, $rows, $style = '')
{
	$xml = '<table:table table:name="' . $name . '"' . (($style !== '') ? ' table:style-name="' . $style . '"' : '') . '>';

	foreach ($rows as $row) {
		$xml .= '<table:table-row>' . implode('', $row) . '</table:table-row>';
	}

	return $xml . '</table:table>';
}

// A text cell, and a cell of one value type.
function office_ods_test_text($text)
{
	return '<table:table-cell office:value-type="string"><text:p>' . $text . '</text:p></table:table-cell>';
}

function office_ods_test_value($type, $attribute, $value)
{
	return '<table:table-cell office:value-type="' . $type . '" office:' . $attribute . '="' . $value . '"><text:p>shown</text:p></table:table-cell>';
}

// The cells of each row of a sheet.
function office_ods_test_cells($sheet)
{
	return array_map(function ($row) {
		return $row['cells'];
	}, $sheet['rows']);
}

function test_office_ods_kind_by_extension()
{
	pg_assert_same('ods', pg_office_preview_kind('Tablo.ODS'), 'an .ods, whatever the case');
	pg_assert_same('csv', pg_office_preview_kind('liste.csv'), 'a .csv as before');
	pg_assert_same('xlsx', pg_office_preview_kind('liste.xlsx'), 'a workbook as before');
	pg_assert_same('', pg_office_preview_kind('metin.odt'), 'an OpenDocument text has no preview');
	pg_assert_same('', pg_office_kind('tablo.ods'), 'the kind the edits ask for leaves it out: view only');
}

function test_office_ods_reads_a_libreoffice_workbook()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	// Written by LibreOffice 24.2 (the sheet reader's own fixture).
	$book = pg_office_read(dirname(__FILE__) . '/fixtures/sheet_read/book.ods', 'book.ods');

	pg_assert_true($book['ok'], 'read: ' . $book['error']);
	pg_assert_same('ods', $book['kind'], 'as a workbook of its own kind');
	pg_assert_same(array('Products', 'Other'), array_column($book['sheets'], 'name'), 'every sheet, by its name, in order');
	pg_assert_same(array(false, false), array_column($book['sheets'], 'hidden'), 'both shown');
	pg_assert_false($book['truncated'], 'nothing cut');
	pg_assert_false(isset($book['version']), 'no version: nothing is saved against it');

	$rows = $book['sheets'][0]['rows'];

	pg_assert_same(array(1, 2, 4, 5, 6, 7, 8, 9, 10, 11), array_column($rows, 'n'), 'the sheet\'s own row numbers, the empty row a gap');
	pg_assert_same(array('Name', 'Price', 'Active', 'Date', 'Stamp', 'Qty', 'Note'), $rows[0]['cells'], 'the heading; the empty run to column 16,384 left');
	pg_assert_same(array("\xC3\x87ay \xC4\x9F\xC3\xBC\xC5\x9F", '12.5', 'TRUE', '2026-11-17', '2026-11-17 10:30', '3'), $rows[1]['cells'], 'text, a number, a boolean, a date, a date and time');
	pg_assert_same(array('Kahve', '0', 'FALSE', '2024-02-29', '', '-7', '25'), $rows[2]['cells'], 'a gap inside a row');
	pg_assert_same(array('', '', '', '', '', '', 'far'), $rows[4]['cells'], 'a row whose first cells are empty');
	pg_assert_same(array('0.25', '1234.56', '00123', '0.0000001', '123456789012'), $rows[5]['cells'], 'a percent and a currency as their values, text kept as text, no exponent');
	pg_assert_same(array('AB', 'TRUE', '12:30', '#DIV/0!', '  spaced  '), $rows[6]['cells'], 'a time alone, an error, spaces kept');
	pg_assert_same(array("line1\nline2"), $rows[7]['cells'], 'two paragraphs, two lines');
	pg_assert_same(array(array('second', '42'), array('TRUE')), office_ods_test_cells($book['sheets'][1]), 'the second sheet');
}

function test_office_ods_sheets_hidden_and_values()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	$styles = '<style:style style:name="ta1" style:family="table"><style:table-properties table:display="true"/></style:style>'
		. '<style:style style:name="ta2" style:family="table"><style:table-properties table:display="false"/></style:style>'
		. '<style:style style:name="ce1" style:family="table-cell"><style:table-properties table:display="false"/></style:style>';
	$path = office_ods_test_file(
		office_ods_test_table('G&#xF6;r&#xFC;nen', array(
			array(office_ods_test_text('&lt;b&gt;x&lt;/b&gt;'), office_ods_test_value('time', 'time-value', 'PT08H05M30S'), office_ods_test_value('float', 'value', '1E-3')),
		), 'ta1')
		. office_ods_test_table('Gizli', array(array(office_ods_test_text('sakl&#x131;'))), 'ta2')
		. office_ods_test_table('Hücre stili', array(array(office_ods_test_text('a'))), 'ce1')
		. '<table:table table:name="Bo&#x15F;"/>',
		$styles
	);
	$book = pg_office_read($path, 'tablo.ods', true);
	@unlink($path);

	pg_assert_true($book['ok'], 'read: ' . $book['error']);
	pg_assert_same(array("G\xC3\xB6r\xC3\xBCnen", 'Gizli', "H\xC3\xBCcre stili", "Bo\xC5\x9F"), array_column($book['sheets'], 'name'), 'four sheets');
	pg_assert_same(array(false, true, false, false), array_column($book['sheets'], 'hidden'), 'hidden by its table style only');
	pg_assert_same(array(array('<b>x</b>', '08:05:30', '0.001')), office_ods_test_cells($book['sheets'][0]), 'markup as text, a time with its seconds, a number written out');
	pg_assert_same(array(array("sakl\xC4\xB1")), office_ods_test_cells($book['sheets'][1]), 'the hidden sheet is read all the same');
	pg_assert_same(array(), $book['sheets'][3]['rows'], 'an empty sheet');
	pg_assert_false($book['sheets'][3]['truncated'], 'is not cut');
	pg_assert_false(isset($book['version']), 'asked for editing: no version to save against');
	pg_assert_false(isset($book['sheets'][0]['edit']), 'and no cell is offered');
}

function test_office_ods_preview_limits()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	// One row with a value, repeated: the reader gives each repeat.
	$row = '<table:table-row table:number-rows-repeated="' . (PG_OFFICE_MAX_ROWS + 5) . '">' . office_ods_test_text('x') . '</table:table-row>';
	$path = office_ods_test_file('<table:table table:name="Uzun">' . $row . '</table:table>');
	$book = pg_office_read($path, 'uzun.ods');
	@unlink($path);

	pg_assert_true($book['ok'], 'read: ' . $book['error']);
	pg_assert_same(PG_OFFICE_MAX_ROWS, count($book['sheets'][0]['rows']), 'the rows a sheet shows');
	pg_assert_true($book['sheets'][0]['truncated'], 'more rows: cut');
	pg_assert_true($book['truncated'], 'and the workbook says so');

	$wide = '<table:table-cell office:value-type="float" office:value="7" table:number-columns-repeated="' . (PG_OFFICE_MAX_COLUMNS + 10) . '"/>';
	$path = office_ods_test_file(office_ods_test_table('Geni&#x15F;', array(array($wide))));
	$book = pg_office_read($path, 'genis.ods');
	@unlink($path);

	pg_assert_same(PG_OFFICE_MAX_COLUMNS, count($book['sheets'][0]['rows'][0]['cells']), 'the columns a sheet shows');
	pg_assert_true($book['sheets'][0]['truncated'], 'more columns: cut');

	// 700 rows of the widest row: 140,000 cells against 120,000. Each row is
	// written out, as one row repeated 700 times would pass the reader's
	// ceiling at once.
	$full = str_repeat('<table:table-row><table:table-cell office:value-type="float" office:value="1" table:number-columns-repeated="' . PG_OFFICE_MAX_COLUMNS . '"/></table:table-row>', 700);
	$path = office_ods_test_file('<table:table table:name="A">' . $full . '</table:table>' . office_ods_test_table('B', array(array(office_ods_test_text('b')))));
	$book = pg_office_read($path, 'hucre.ods');
	@unlink($path);

	pg_assert_true($book['ok'], 'read: ' . $book['error']);
	pg_assert_same((int) (PG_OFFICE_MAX_CELLS / PG_OFFICE_MAX_COLUMNS), count($book['sheets'][0]['rows']), 'the cells a workbook shows');
	pg_assert_same(array(), $book['sheets'][1]['rows'], 'the next sheet is not read');
	pg_assert_true($book['sheets'][1]['truncated'], 'and says it is cut');
}

function test_office_ods_cell_ceiling_refuses()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	// A few hundred bytes asking for 1,000 x 1,000 value cells.
	$bomb = '<table:table-row table:number-rows-repeated="1000"><table:table-cell office:value-type="float" office:value="1" table:number-columns-repeated="1000"/></table:table-row>';
	$path = office_ods_test_file('<table:table table:name="A">' . $bomb . '</table:table>');
	$book = pg_office_read($path, 'bomba.ods');
	@unlink($path);

	pg_assert_false($book['ok'], 'past the reader\'s cell ceiling: refused');
	pg_assert_same(lang('This file is too large to show here. Download it to open it.'), $book['error'], 'as too large');

	// One value far down a column: a used range past the reader's ceiling.
	$far = '<table:table-row table:number-rows-repeated="2000000"><table:table-cell table:number-columns-repeated="3"/></table:table-row><table:table-row><table:table-cell office:value-type="float" office:value="1"/></table:table-row>';
	$path = office_ods_test_file(office_ods_test_table('A', array(array(office_ods_test_text('a')))) . '<table:table table:name="B">' . $far . '</table:table>');
	$book = pg_office_read($path, 'uzak.ods');
	@unlink($path);

	pg_assert_false($book['ok'], 'past the used range\'s ceiling on its second sheet: refused');
	pg_assert_same(lang('This file is too large to show here. Download it to open it.'), $book['error'], 'as too large too');
}

function test_office_ods_sheets_share_one_walk()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	// The reader walks content.xml from its beginning for each sheet: a
	// part of about 4 MB has as many sheets read as fit in the walk.
	$tables = '';

	for ($index = 1; $index <= 20; $index++) {
		$tables .= office_ods_test_table('S' . $index, array(array(office_ods_test_text('s' . $index))));
	}

	$path = office_ods_test_file($tables, '', 4 * 1024 * 1024);
	$walks = intdiv(PG_OFFICE_MAX_ODS_WALK, pg_zip_entry_size($path, 'content.xml'));
	$book = pg_office_read($path, 'cok.ods');
	@unlink($path);

	pg_assert_true($book['ok'], 'read: ' . $book['error']);
	pg_assert_true(($walks > 1) && ($walks < 20), 'the walk holds some of the sheets: ' . $walks);
	pg_assert_same(20, count($book['sheets']), 'every sheet is listed');
	pg_assert_same(array(array('s' . $walks)), office_ods_test_cells($book['sheets'][$walks - 1]), 'the last one walked is read');
	pg_assert_same(array(), $book['sheets'][$walks]['rows'], 'the next is not');
	pg_assert_true($book['sheets'][$walks]['truncated'], 'and says it is cut');
	pg_assert_true($book['truncated'], 'as the workbook does');
}

function test_office_ods_refuses_what_it_cannot_read()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_office_ods_');
	file_put_contents($path, 'not a zip at all');
	$book = pg_office_read($path, 'x.ods');
	@unlink($path);
	pg_assert_false($book['ok'], 'not a zip');

	$zip = new ZipArchive();
	$path = tempnam(sys_get_temp_dir(), 'pg_office_ods_');
	$zip->open($path, ZipArchive::OVERWRITE);
	$zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
	$zip->close();
	$book = pg_office_read($path, 'x.ods');
	@unlink($path);
	pg_assert_false($book['ok'], 'no content.xml');
	pg_assert_same(lang('The file could not be read.'), $book['error'], 'cannot be read');

	$path = office_ods_test_file('');
	$book = pg_office_read($path, 'x.ods');
	@unlink($path);
	pg_assert_false($book['ok'], 'no sheet at all');
	pg_assert_same(lang('The file could not be read.'), $book['error'], 'cannot be read either');

	$path = office_ods_test_file(office_ods_test_table('A', array(array(office_ods_test_text('a')))), '', PG_SHEET_ODS_MAX_PART);
	$book = pg_office_read($path, 'x.ods');
	@unlink($path);
	pg_assert_false($book['ok'], 'content.xml past the reader\'s part limit');
	pg_assert_same(lang('This file is too large to show here. Download it to open it.'), $book['error'], 'too large');

	$path = office_ods_test_file(str_repeat('<table:table/>', PG_OFFICE_MAX_ODS_SHEETS));
	$book = pg_office_read($path, 'x.ods');
	@unlink($path);
	pg_assert_true($book['ok'], 'as many sheets as may be shown: read');
	pg_assert_same(PG_OFFICE_MAX_ODS_SHEETS, count($book['sheets']), 'every one listed');

	$path = office_ods_test_file(str_repeat('<table:table/>', PG_OFFICE_MAX_ODS_SHEETS + 1));
	$book = pg_office_read($path, 'x.ods');
	@unlink($path);
	pg_assert_false($book['ok'], 'one sheet more');
	pg_assert_same(lang('This file is too large to show here. Download it to open it.'), $book['error'], 'is too large');
}

function test_office_ods_doors_offer_the_preview()
{
	$view = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');
	pg_assert_contains("office.kind === 'ods'", $view, 'the view draws an .ods');

	$folders = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	pg_assert_contains("'xlsx', 'xlsm', 'xltx', 'xltm', 'ods'", $folders, 'Quick Look and the file window open it');
}
