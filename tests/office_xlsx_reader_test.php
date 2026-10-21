<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Excel preview's reader (includes/fn/office.php) on valid
 * workbooks written by programs other than Excel: elements with a namespace
 * prefix (x:row, x:c), cells and rows without their r attribute, and cells
 * formatted as a time of day or as a duration.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// office_test_package(): a package of the given parts in a zip on disk.
require_once(__DIR__ . '/office_test.php');

// A one-sheet workbook with the given sheet data, shared strings and styles.
function office_xlsx_reader_test_book($sheet, $strings = '', $styles = '', $prefix = '')
{
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$p = ($prefix !== '') ? $prefix . ':' : '';
	$declare = ($prefix !== '') ? ' xmlns:' . $prefix . '="' . $ns . '"' : ' xmlns="' . $ns . '"';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'xl/workbook.xml' => '<?xml version="1.0"?><' . $p . 'workbook' . $declare . ' xmlns:r="' . $rns . '"><' . $p . 'sheets><' . $p . 'sheet name="S" sheetId="1" r:id="rId1"/></' . $p . 'sheets></' . $p . 'workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
		'xl/worksheets/sheet1.xml' => '<?xml version="1.0"?><' . $p . 'worksheet' . $declare . '><' . $p . 'sheetData>' . $sheet . '</' . $p . 'sheetData></' . $p . 'worksheet>',
	);

	if ($strings !== '') {
		$parts['xl/sharedStrings.xml'] = '<?xml version="1.0"?><' . $p . 'sst' . $declare . '>' . $strings . '</' . $p . 'sst>';
	}

	if ($styles !== '') {
		$parts['xl/styles.xml'] = '<?xml version="1.0"?><' . $p . 'styleSheet' . $declare . '>' . $styles . '</' . $p . 'styleSheet>';
	}

	$path = office_test_package($parts);
	$book = pg_office_read($path, 'test.xlsx');
	unlink($path);

	return $book;
}

// Open XML SDK writes every SpreadsheetML element with the x: prefix.
function test_office_xlsx_reader_prefixed_elements()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$book = office_xlsx_reader_test_book(
		'<x:row r="1"><x:c r="A1" t="s"><x:v>0</x:v></x:c><x:c r="B1"><x:v>42</x:v></x:c><x:c r="C1" t="inlineStr"><x:is><x:t>iç</x:t></x:is></x:c></x:row>',
		'<x:si><x:t>Merhaba</x:t></x:si>',
		'',
		'x'
	);

	pg_assert_true($book['ok'], 'read: ' . ($book['error'] ?? ''));
	pg_assert_same(1, count($book['sheets'][0]['rows']), 'the row is found');
	pg_assert_same(array('Merhaba', '42', 'iç'), $book['sheets'][0]['rows'][0]['cells'], 'a shared string, a number, an inline string');
}

// The r attribute is optional on rows and cells: a cell without one is the
// next column, a row without one the next row.
function test_office_xlsx_reader_cells_without_a_reference()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$book = office_xlsx_reader_test_book(
		'<row><c t="s"><v>0</v></c><c t="s"><v>1</v></c><c><v>3</v></c></row>'
		. '<row><c><v>10</v></c><c><v>20</v></c></row>'
		. '<row r="5"><c r="B5"><v>7</v></c><c><v>8</v></c></row>'
		. '<row><c><v>9</v></c></row>',
		'<si><t>Ad</t></si><si><t>Soyad</t></si>'
	);

	$rows = $book['sheets'][0]['rows'];

	pg_assert_same(array(1, 2, 5, 6), array_column($rows, 'n'), 'row numbers counted on, from the last one given');
	pg_assert_same(array('Ad', 'Soyad', '3'), $rows[0]['cells'], 'the first row, column after column');
	pg_assert_same(array('10', '20'), $rows[1]['cells'], 'the second row');
	pg_assert_same(array('', '7', '8'), $rows[2]['cells'], 'after a referenced cell, the next column');
	pg_assert_same(array('9'), $rows[3]['cells'], 'a row after a numbered one');
}

// A time of day and a duration are shown as such, not as a date in 1899.
function test_office_xlsx_reader_time_formats()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$styles = '<numFmts count="3"><numFmt numFmtId="164" formatCode="hh:mm"/><numFmt numFmtId="165" formatCode="[h]:mm:ss"/><numFmt numFmtId="166" formatCode="dd.mm.yyyy hh:mm"/></numFmts>'
		. '<cellXfs count="7"><xf numFmtId="0"/><xf numFmtId="20"/><xf numFmtId="21"/><xf numFmtId="165"/><xf numFmtId="164"/><xf numFmtId="166"/><xf numFmtId="14"/></cellXfs>';

	$book = office_xlsx_reader_test_book(
		'<row r="1"><c r="A1" s="1"><v>0.3541</v></c><c r="B1" s="2"><v>0.5</v></c><c r="C1" s="3"><v>1.0625</v></c><c r="D1" s="4"><v>0.75</v></c><c r="E1" s="1"><v>0</v></c>'
		. '<c r="F1" s="5"><v>46314.5</v></c><c r="G1" s="6"><v>46314</v></c><c r="H1" s="2"><v>0.999999</v></c></row>',
		'',
		$styles
	);

	pg_assert_same(array('08:30', '12:00:00', '25:30:00', '18:00', '00:00', '2026-10-19 12:00', '2026-10-19', '00:00:00'), $book['sheets'][0]['rows'][0]['cells'], 'h:mm, h:mm:ss, [h]:mm:ss, hh:mm, zero, a date and time, a date, a second before midnight');
}

// The time of a serial in the format's unit, rounded as the program shows it.
function test_office_xlsx_time()
{
	pg_assert_same('08:30', pg_office_xlsx_time(0.3541, 'time'), 'to the minute');
	pg_assert_same('08:29:54', pg_office_xlsx_time(0.3541, 'time_s'), 'to the second');
	pg_assert_same('25:30', pg_office_xlsx_time(1.0625, 'hours'), 'elapsed hours');
	pg_assert_same('25:30:00', pg_office_xlsx_time(1.0625, 'hours_s'), 'elapsed hours and seconds');
	pg_assert_same('01:30', pg_office_xlsx_time(1 / 960, 'minutes'), 'mm:ss');
	pg_assert_same('-01:00', pg_office_xlsx_time(-1 / 24, 'hours'), 'a negative duration');
	pg_assert_same('06:00', pg_office_xlsx_time(2.25, 'time'), 'a time of day leaves the days out');
}

// What a number format shows: an m beside an hour or a second is minutes.
function test_office_xlsx_format_kind()
{
	$cases = array(
		'dd.mm.yyyy' => 'date',
		'mmm-yy' => 'date',
		'dd.mm.yyyy hh:mm' => 'date',
		'hh:mm' => 'time',
		'h:mm AM/PM' => 'time',
		'[$-409]h:mm:ss AM/PM' => 'time_s',
		'[h]:mm' => 'hours',
		'[h]:mm:ss' => 'hours_s',
		'mm:ss' => 'minutes',
		'[mm]:ss' => 'minutes',
		'#,##0.00 "TL"' => '',
		'_-* #,##0.00\\ _m_-' => '',
		'0.00%' => '',
		'General' => '',
	);

	foreach ($cases as $code => $kind) {
		pg_assert_same($kind, pg_office_xlsx_format_kind($code), $code);
	}
}
