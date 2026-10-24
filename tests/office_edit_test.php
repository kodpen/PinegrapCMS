<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for changing a workbook's cells (includes/fn/office_edit.php): the
 * addresses, what a typed text becomes (number, date, yes/no, text), the row
 * and sheet splice over sheets written here (only the changed rows differ,
 * byte for byte), the workbook's recalculation flag, what may not be
 * changed (formulas, merges, tables, array formulas, protected sheets), and
 * a save of a whole package read back through the preview's own reader.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_edit_test_ns()
{
	return 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
}

// A sheet part around the rows given (and whatever follows sheetData).
function office_edit_test_sheet($rows, $after = '', $before = '')
{
	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<worksheet xmlns="' . office_edit_test_ns() . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
		. $before . '<sheetData>' . $rows . '</sheetData>' . $after . '</worksheet>';
}

// The context the sheet splice works with: no styles, 1900 days, not protected.
function office_edit_test_context($styles = array())
{
	return array(
		'styles' => array_merge(array('date' => array(), 'text' => array(), 'open' => array()), $styles),
		'date1904' => false,
		'protected' => false,
	);
}

// A whole package: the parts given, in a zip on disk.
function office_edit_test_package($parts)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_edit_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return $path;
}

// A workbook of two sheets: Liste (values, a formula, a merge, a table over
// D1:E3) and Korumalı (protected, B1 unlocked by its style).
function office_edit_test_book_parts()
{
	$ns = office_edit_test_ns();
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';

	return array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><bookViews><workbookView xWindow="0"><extLst><ext uri="{x}"/></extLst></workbookView></bookViews><sheets><sheet name="Liste" sheetId="1" r:id="rId1"/><sheet name="Korumalı" sheetId="2" r:id="rId2"/></sheets><extLst><ext uri="{y}"/></extLst></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $rns . '/worksheet" Target="worksheets/sheet2.xml"/></Relationships>',
		'xl/sharedStrings.xml' => '<?xml version="1.0"?><sst xmlns="' . $ns . '" count="3" uniqueCount="3"><si><t>Ürün</t></si><si><t>Ad</t></si><si><t>Fiyat</t></si></sst>',
		'xl/styles.xml' => '<?xml version="1.0"?><styleSheet xmlns="' . $ns . '"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd.mm.yyyy"/></numFmts><cellXfs count="4"><xf numFmtId="0"/><xf numFmtId="164"/><xf numFmtId="49"/><xf numFmtId="0"><protection locked="0"/></xf></cellXfs></styleSheet>',
		'xl/worksheets/sheet1.xml' => office_edit_test_sheet(
			'<row r="1" spans="1:5"><c r="A1" t="s"><v>0</v></c><c r="B1"><v>10</v></c><c r="C1"><f>B1*2</f><v>20</v></c><c r="D1" t="s"><v>1</v></c><c r="E1" t="s"><v>2</v></c></row>'
			. '<row r="2" spans="1:5"><c r="A2" s="1"><v>46314</v></c><c r="B2" s="2" t="inlineStr"><is><t>007</t></is></c><c r="D2" t="inlineStr"><is><t>Kalem</t></is></c><c r="E2"><v>5</v></c></row>'
			. '<row r="3" spans="1:5"><c r="A3" t="b"><v>1</v></c><c r="D3" t="inlineStr"><is><t>Silgi</t></is></c><c r="E3"><v>3</v></c></row>'
			. '<row r="5"><c r="A5"><v>1</v></c></row>',
			'<mergeCells count="1"><mergeCell ref="A5:C5"/></mergeCells><tableParts count="1"><tablePart r:id="rId1"/></tableParts>'),
		'xl/worksheets/_rels/sheet1.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/table" Target="../tables/table1.xml"/></Relationships>',
		'xl/tables/table1.xml' => '<?xml version="1.0"?><table xmlns="' . $ns . '" id="1" name="Tablo1" displayName="Tablo1" ref="D1:E3"><tableColumns count="2"><tableColumn id="1" name="Ad"/><tableColumn id="2" name="Fiyat"/></tableColumns></table>',
		'xl/worksheets/sheet2.xml' => office_edit_test_sheet(
			'<row r="1"><c r="A1"><v>1</v></c><c r="B1" s="3"><v>2</v></c></row>',
			'<sheetProtection sheet="1" objects="1"/>',
			'<cols><col min="3" max="3" width="12" customWidth="1" style="3"/></cols>'),
	);
}

function test_office_edit_addresses()
{
	pg_assert_same('A', pg_office_xlsx_column_name(0), 'A');
	pg_assert_same('Z', pg_office_xlsx_column_name(25), 'Z');
	pg_assert_same('AA', pg_office_xlsx_column_name(26), 'AA');
	pg_assert_same('XFD', pg_office_xlsx_column_name(16383), 'the last column');
	pg_assert_same(array(7, 1), pg_office_xlsx_cell('B7'), 'a cell');
	pg_assert_same(array(7, 1), pg_office_xlsx_cell('$B$7'), 'an absolute address');
	pg_assert_same(array(1048576, 16383), pg_office_xlsx_cell('XFD1048576'), 'the last cell of a sheet');
	pg_assert_same(null, pg_office_xlsx_cell('XFE1'), 'past the last column');
	pg_assert_same(null, pg_office_xlsx_cell('A0'), 'row 0');
	pg_assert_same(null, pg_office_xlsx_cell('b7'), 'lower case is not an address here');
	pg_assert_same(null, pg_office_xlsx_cell('B7 '), 'nothing around it');
	pg_assert_same(array(1, 0, 3, 2), pg_office_xlsx_range('A1:C3'), 'a range');
	pg_assert_same(array(1, 0, 3, 2), pg_office_xlsx_range('C3:A1'), 'corners in any order');
	pg_assert_same(array(4, 1, 4, 1), pg_office_xlsx_range('B4'), 'a single cell');
	pg_assert_same(null, pg_office_xlsx_range('A1:B2:C3'), 'not a range');
	pg_assert_true(pg_office_xlsx_in_range(2, 1, array(1, 0, 3, 2)), 'inside');
	pg_assert_false(pg_office_xlsx_in_range(4, 1, array(1, 0, 3, 2)), 'below');
}

function test_office_edit_numbers()
{
	pg_assert_same('12', pg_office_xlsx_parse_number('12'), 'a whole number');
	pg_assert_same('-12.5', pg_office_xlsx_parse_number('-12.50'), 'a fraction, trailing zero dropped');
	pg_assert_same('12.5', pg_office_xlsx_parse_number('12,5'), 'a Turkish decimal comma');
	pg_assert_same('0.5', pg_office_xlsx_parse_number('.5'), 'no whole part');
	pg_assert_same('12', pg_office_xlsx_parse_number('+12'), 'a plus sign');
	pg_assert_same('1E5', pg_office_xlsx_parse_number('1e5'), 'an exponent');
	pg_assert_same('0', pg_office_xlsx_parse_number('-0'), 'zero has no sign');
	pg_assert_same('1', pg_office_xlsx_parse_number('1.0000000000000000000'), 'zeros after the mark do not count as digits');
	pg_assert_same(null, pg_office_xlsx_parse_number('007'), 'a leading zero stays text');
	pg_assert_same(null, pg_office_xlsx_parse_number('05321234567'), 'a phone number stays text');
	pg_assert_same(null, pg_office_xlsx_parse_number('1234567890123456'), 'more digits than a spreadsheet keeps');
	pg_assert_same(null, pg_office_xlsx_parse_number('1.234,5'), 'grouping marks are not read');
	pg_assert_same(null, pg_office_xlsx_parse_number('1e999'), 'out of range');
	pg_assert_same(null, pg_office_xlsx_parse_number('12 adet'), 'words');
	pg_assert_same(null, pg_office_xlsx_parse_number(''), 'nothing');
	pg_assert_same(null, pg_office_xlsx_parse_number('-'), 'a sign alone');
}

function test_office_edit_dates()
{
	pg_assert_same('46314', pg_office_xlsx_parse_date('2026-10-19', false), 'the form the preview shows');
	pg_assert_same('46314', pg_office_xlsx_parse_date('19.10.2026', false), 'the Turkish form');
	pg_assert_same('46314.6041666667', pg_office_xlsx_parse_date('2026-10-19 14:30', false), 'with a time');
	pg_assert_same('2026-10-19 14:30', pg_office_xlsx_date(pg_office_xlsx_parse_date('19.10.2026 14:30', false)), 'reads back as it was typed');
	pg_assert_same('44852', pg_office_xlsx_parse_date('2026-10-19', true), 'a workbook counting from 1904');
	pg_assert_same('2026-10-19', pg_office_xlsx_date('44852', true), 'and the reader agrees');
	pg_assert_same('61', pg_office_xlsx_parse_date('1900-03-01', false), 'the first day written');
	pg_assert_same(null, pg_office_xlsx_parse_date('1900-02-28', false), 'not before it');
	pg_assert_same(null, pg_office_xlsx_parse_date('2026-02-30', false), 'not a day');
	pg_assert_same(null, pg_office_xlsx_parse_date('2026-10-19 24:00', false), 'not a time');
	pg_assert_same(null, pg_office_xlsx_parse_date('10/19/2026', false), 'the American form is not guessed');

	// A time alone, as a time or duration cell shows it, is a fraction of a
	// day; over 24 hours (an [h] format) it runs on into whole days.
	pg_assert_same('0.3645833333', pg_office_xlsx_parse_date('08:45', false), 'a time of day');
	pg_assert_same('0.3020833333', pg_office_xlsx_parse_date('7:15', false), 'one digit of hours');
	pg_assert_same('0.3649305556', pg_office_xlsx_parse_date('8:45:30', false), 'with seconds');
	pg_assert_same('1.0625', pg_office_xlsx_parse_date('25:30', false), 'a duration over a day');
	pg_assert_same('1', pg_office_xlsx_parse_date('24:00', false), 'a whole day');
	pg_assert_same('0', pg_office_xlsx_parse_date('0:00', false), 'midnight');
	pg_assert_same('08:45', pg_office_xlsx_time(pg_office_xlsx_parse_date('08:45', false), 'time'), 'reads back as a time');
	pg_assert_same('25:30', pg_office_xlsx_time(pg_office_xlsx_parse_date('25:30', false), 'hours'), 'and as elapsed hours');
	pg_assert_same(null, pg_office_xlsx_parse_date('08:60', false), 'not minutes');
	pg_assert_same(null, pg_office_xlsx_parse_date('8:45:60', false), 'not seconds');
	pg_assert_same(null, pg_office_xlsx_parse_date('8.45', false), 'a number, not a time');
	pg_assert_same(null, pg_office_xlsx_parse_date('8:', false), 'nothing after the colon');

	// Day 0 with a time: what the preview shows for a time cell.
	pg_assert_same('0.3541666667', pg_office_xlsx_parse_date('1899-12-30 08:30', false), 'the preview\'s form of a time cell');
	pg_assert_same('0.3541666667', pg_office_xlsx_parse_date('30.12.1899 08:30', false), 'in the Turkish form too');
	pg_assert_same('0', pg_office_xlsx_parse_date('1899-12-30', false), 'day 0 alone');
	pg_assert_same('0.3541666667', pg_office_xlsx_parse_date('1904-01-01 08:30', true), 'day 0 of a workbook counting from 1904');
	pg_assert_same(null, pg_office_xlsx_parse_date('1899-12-29', false), 'nothing before day 0');
	pg_assert_same(null, pg_office_xlsx_parse_date('1899-12-31', false), 'still nothing between day 0 and the first day written');
	pg_assert_same(null, pg_office_xlsx_parse_date('1903-12-31 08:30', true), 'nor before 1904 there');
}

function test_office_edit_typed_values()
{
	pg_assert_same(array('kind' => 'empty', 'value' => ''), pg_office_xlsx_typed('', 'number', '', false), 'nothing empties the cell');
	pg_assert_same(array('kind' => 'number', 'value' => '42'), pg_office_xlsx_typed('42', 'number', '', false), 'a number stays a number');
	pg_assert_same(array('kind' => 'number', 'value' => '42'), pg_office_xlsx_typed('42', 'empty', '', false), 'an empty cell takes a number');
	pg_assert_same(array('kind' => 'string', 'value' => '42'), pg_office_xlsx_typed('42', 'string', '', false), 'a text cell stays text');
	pg_assert_same(array('kind' => 'string', 'value' => '42'), pg_office_xlsx_typed('42', 'empty', 'text', false), 'a cell formatted as text takes text');
	pg_assert_same(array('kind' => 'string', 'value' => 'kalem'), pg_office_xlsx_typed('kalem', 'number', '', false), 'words in a number cell are text');
	pg_assert_same(array('kind' => 'number', 'value' => '46314'), pg_office_xlsx_typed('2026-10-19', 'number', 'date', false), 'a date in a date cell');
	pg_assert_same(array('kind' => 'string', 'value' => '2026-10-19'), pg_office_xlsx_typed('2026-10-19', 'empty', '', false), 'a date without a date format stays as typed');
	pg_assert_same(array('kind' => 'number', 'value' => '0.3645833333'), pg_office_xlsx_typed('08:45', 'number', 'date', false), 'a time in a time cell');
	pg_assert_same(array('kind' => 'number', 'value' => '1.0625'), pg_office_xlsx_typed('25:30', 'empty', 'date', false), 'a duration in a duration cell');
	pg_assert_same(array('kind' => 'number', 'value' => '0.3020833333'), pg_office_xlsx_typed('1899-12-30 07:15', 'number', 'date', false), 'the preview\'s form of a time cell, with the time changed');
	pg_assert_same(array('kind' => 'string', 'value' => '08:45'), pg_office_xlsx_typed('08:45', 'empty', '', false), 'a time without a date format stays as typed');
	pg_assert_same(array('kind' => 'bool', 'value' => '0'), pg_office_xlsx_typed('yanlış', 'bool', '', false), 'a yes/no cell, in Turkish');
	pg_assert_same(array('kind' => 'bool', 'value' => '1'), pg_office_xlsx_typed('TRUE', 'bool', '', false), 'and in English');
	pg_assert_same(array('kind' => 'string', 'value' => 'TRUE'), pg_office_xlsx_typed('TRUE', 'empty', '', false), 'TRUE elsewhere is text');
	pg_assert_same(array('kind' => 'string', 'value' => '=A1+B1'), pg_office_xlsx_typed('=A1+B1', 'number', '', false), 'no formula is written');
	pg_assert_same("a\nb", pg_office_xlsx_clean_text("a\r\nb\x01"), 'line breaks as stored, control characters out');
	pg_assert_same(null, pg_office_xlsx_clean_text("\xC3\x28"), 'not UTF-8');
}

function test_office_edit_text_format_is_its_first_section()
{
	pg_assert_true(pg_office_xlsx_text_format('@'), 'the Text format');
	pg_assert_true(pg_office_xlsx_text_format('"No: "@'), 'text with a label');
	pg_assert_true(pg_office_xlsx_text_format('"a;b"@'), 'a semicolon inside quotes does not start a section');
	pg_assert_true(pg_office_xlsx_text_format('\;@'), 'nor an escaped one');
	pg_assert_true(pg_office_xlsx_text_format('[Red]@'), 'a colour before the text');
	pg_assert_false(pg_office_xlsx_text_format('_-* #,##0.00 "₺"_-;\-* #,##0.00 "₺"_-;_-* "-"?? "₺"_-;_-@_-'), 'the Turkish accounting format shows numbers');
	pg_assert_false(pg_office_xlsx_text_format('_("$"* #,##0.00_);_("$"* \(#,##0.00\);_("$"* "-"??_);_(@_)'), 'and the US one');
	pg_assert_false(pg_office_xlsx_text_format('d/m/yy;@'), 'a date with a text section');
	pg_assert_false(pg_office_xlsx_text_format('[$-409]mmmm\ d\,\ yyyy;@'), 'a locale date with a text section');
	pg_assert_false(pg_office_xlsx_text_format('"@";@'), 'an @ in quotes is a letter');
	pg_assert_false(pg_office_xlsx_text_format('\@0'), 'an escaped @ too');
	pg_assert_false(pg_office_xlsx_text_format('_@0'), 'a padding the width of @');
	pg_assert_false(pg_office_xlsx_text_format('[@]0'), 'an @ in brackets');
	pg_assert_false(pg_office_xlsx_text_format('0.00'), 'a plain number');
	pg_assert_false(pg_office_xlsx_text_format(';;;@'), 'numbers hidden, text shown');
	pg_assert_false(pg_office_xlsx_text_format(''), 'no format');
}

function test_office_edit_style_kinds_read_sectioned_formats()
{
	$ns = office_edit_test_ns();
	$path = office_edit_test_package(array(
		'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="' . $ns . '"><numFmts count="4">'
			. '<numFmt numFmtId="164" formatCode="' . htmlspecialchars('_-* #,##0.00 "₺"_-;\-* #,##0.00 "₺"_-;_-* "-"?? "₺"_-;_-@_-', ENT_QUOTES, 'UTF-8') . '"/>'
			. '<numFmt numFmtId="165" formatCode="d/m/yy;@"/>'
			. '<numFmt numFmtId="166" formatCode="' . htmlspecialchars('"No: "@', ENT_QUOTES, 'UTF-8') . '"/>'
			. '<numFmt numFmtId="167" formatCode="0.00"/>'
			. '</numFmts><cellXfs count="5"><xf numFmtId="164"/><xf numFmtId="165"/><xf numFmtId="166"/><xf numFmtId="49"/><xf numFmtId="167"/></cellXfs></styleSheet>',
	));
	$zip = new ZipArchive();
	$zip->open($path);
	$kinds = pg_office_xlsx_style_kinds($zip);
	$zip->close();
	@unlink($path);

	pg_assert_same(array(2 => true, 3 => true), $kinds['text'], 'only the Text format and a format whose numbers are text');
	pg_assert_same('date', isset($kinds['date'][1]) ? $kinds['date'][1] : '', 'd/m/yy;@ is a date');
	pg_assert_same(array('kind' => 'number', 'value' => '45.5'), pg_office_xlsx_typed('45,5', 'number', '', false), 'a number typed in an accounting cell');
	pg_assert_same(array('kind' => 'number', 'value' => '39046'), pg_office_xlsx_typed('2006-11-25', 'number', 'date', false), 'a date typed in a d/m/yy;@ cell');
}

function test_office_edit_patch_sheet_rewrites_only_changed_rows()
{
	$row1 = '<row r="1" spans="1:3"><c r="A1" t="s"><v>0</v></c><c r="C1"><v>7</v></c></row>';
	$row2 = '<row r="2" spans="1:3"><c r="A2" s="4"><v>1</v></c><c r="B2" t="inlineStr"><is><t>eski</t></is></c></row>';
	$row3 = '<row r="3"><c r="A3"><v>9</v></c></row>';
	$xml = office_edit_test_sheet($row1 . $row2 . $row3, '<pageMargins left="0.7"/>');

	$result = pg_office_xlsx_patch_sheet($xml, array(2 => array(0 => '', 1 => 'yeni & <iyi>', 2 => '3,25')), office_edit_test_context());

	pg_assert_true($result['ok'], 'patched: ' . $result['error']);
	pg_assert_contains($row1, $result['xml'], 'the row before is copied byte for byte');
	pg_assert_contains($row3 . '</sheetData><pageMargins left="0.7"/>', $result['xml'], 'and everything after it');
	pg_assert_contains('<c r="A2" s="4"/>', $result['xml'], 'an emptied cell keeps its style');
	pg_assert_contains('<c r="B2" t="inlineStr"><is><t>yeni &amp; &lt;iyi&gt;</t></is></c>', $result['xml'], 'text, escaped');
	pg_assert_contains('<c r="C2"><v>3.25</v></c></row>', $result['xml'], 'a new cell in its column\'s place, as a number');
	pg_assert_true(pg_office_xml_well_formed($result['xml']), 'still XML');
}

function test_office_edit_patch_row_cells()
{
	$context = office_edit_test_context(array('date' => array(1 => true), 'text' => array(2 => true)));
	$xml = office_edit_test_sheet(
		'<row r="4" spans="2:3" x14ac:dyDescent="0.25" xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac"><c r="B4" s="1"><v>46000</v></c><c r="C4" t="b"><v>1</v></c><c r="D4" s="2" t="s"><v>3</v></c><c r="F4"><v>1</v></c></row>'
		. '<row r="6" customFormat="1" s="1"/>');

	$result = pg_office_xlsx_patch_sheet($xml, array(
		4 => array(1 => '19.10.2026', 2 => 'Hayır', 3 => '00042', 0 => '  boşluk  ', 4 => "iki\nsatır"),
		6 => array(0 => '2026-01-02')), $context);

	pg_assert_true($result['ok'], 'patched: ' . $result['error']);
	pg_assert_contains('<c r="A4" t="inlineStr"><is><t xml:space="preserve">  boşluk  </t></is></c><c r="B4" s="1">', $result['xml'], 'a new first cell, spaces kept');
	pg_assert_contains('<c r="B4" s="1"><v>46314</v></c>', $result['xml'], 'a date cell takes the day count');
	pg_assert_contains('<c r="C4" t="inlineStr"><is><t>Hayır</t></is></c>', $result['xml'], 'a yes/no cell given words takes text');
	pg_assert_contains('<c r="D4" s="2" t="inlineStr"><is><t>00042</t></is></c>', $result['xml'], 'a text-formatted cell keeps the zeros');
	pg_assert_contains("<c r=\"E4\" t=\"inlineStr\"><is><t xml:space=\"preserve\">iki\nsatır</t></is></c><c r=\"F4\">", $result['xml'], 'two lines, between two cells');
	pg_assert_contains('spans="1:5"', $result['xml'], 'the span widened to the new cells');
	pg_assert_contains('x14ac:dyDescent="0.25"', $result['xml'], 'the row\'s own attributes stay');
	pg_assert_contains('<row r="6" customFormat="1" s="1"><c r="A6" s="1"><v>46024</v></c></row>', $result['xml'], 'a row with its own style gives it to a new cell, a date here');
	pg_assert_true(pg_office_xml_well_formed($result['xml']), 'still XML');
}

function test_office_edit_patch_refuses()
{
	$xml = office_edit_test_sheet('<row r="1"><c r="A1"><f>1+1</f><v>2</v></c><c r="B1" vm="1"><v>0</v></c><c r="C1" s="3"><v>1</v></c><c r="D1"><v>1</v></c></row>');
	$context = office_edit_test_context(array('open' => array(3 => true)));

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(0 => '5')), $context);
	pg_assert_same(array(false, 'locked', 'A1'), array($result['ok'], $result['error'], $result['cell']), 'a formula');

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(1 => '5')), $context);
	pg_assert_same(array(false, 'locked', 'B1'), array($result['ok'], $result['error'], $result['cell']), 'a linked value');

	$result = pg_office_xlsx_patch_sheet($xml, array(2 => array(0 => '5')), $context);
	pg_assert_same(array(false, 'missing', 'A2'), array($result['ok'], $result['error'], $result['cell']), 'a row the sheet does not have');

	$context['protected'] = true;
	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(3 => '5')), $context);
	pg_assert_same(array(false, 'locked', 'D1'), array($result['ok'], $result['error'], $result['cell']), 'a locked cell of a protected sheet');

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(2 => '5')), $context);
	pg_assert_true($result['ok'], 'an unlocked one is changed');

	$result = pg_office_xlsx_patch_sheet(str_replace('<sheetData>', '<!-- not here --><sheetData>', $xml), array(1 => array(3 => '5')), office_edit_test_context());
	pg_assert_same('unusual', $result['error'], 'a sheet with a comment is not spliced');

	$result = pg_office_xlsx_patch_sheet(office_edit_test_sheet('<row><c><v>1</v></c></row>'), array(1 => array(0 => '5')), office_edit_test_context());
	pg_assert_same('layout', $result['error'], 'rows without addresses');
}

function test_office_edit_patch_prefixed_sheet()
{
	$ns = office_edit_test_ns();
	$xml = '<?xml version="1.0" encoding="UTF-8"?><x:worksheet xmlns:x="' . $ns . '"><x:sheetData><x:row r="1"><x:c r="A1"><x:v>1</x:v></x:c></x:row></x:sheetData></x:worksheet>';

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(0 => '2', 1 => 'iki')), office_edit_test_context());

	pg_assert_true($result['ok'], 'patched: ' . $result['error']);
	pg_assert_contains('<x:row r="1"><x:c r="A1"><x:v>2</x:v></x:c><x:c r="B1" t="inlineStr"><x:is><x:t>iki</x:t></x:is></x:c></x:row>', $result['xml'], 'new elements take the sheet\'s prefix');
	pg_assert_true(pg_office_xml_well_formed($result['xml']), 'still XML');
}

function test_office_edit_strip_formula_values()
{
	$xml = office_edit_test_sheet(
		'<row r="1"><c r="A1" s="2"><f>SUM(B1:C1)</f><v>3</v></c><c r="B1"><v>1</v></c><c r="C1" t="str"><f>"a"&amp;"&lt;b"</f><v>a&lt;b</v></c></row>'
		. '<row r="2"><c r="A2" t="b"><f t="shared" si="0" ref="A2:A3"/><v>1</v></c><c r="B2" vm="1"><f>IMAGE("x")</f><v>#VALUE!</v></c><c r="C2"><f>1</f></c><c r="D2"><f>2</f><v/></c><c r="E2"/></row>');

	$result = pg_office_xlsx_strip_formula_values($xml);

	pg_assert_true($result['ok'], 'stripped');
	pg_assert_same(4, $result['count'], 'four cached results taken out');
	pg_assert_contains('<c r="C2"><f>1</f></c><c r="D2"><f>2</f></c><c r="E2"/>', $result['xml'], 'one without a result, an empty one, an empty cell');
	pg_assert_contains('<c r="A1" s="2"><f>SUM(B1:C1)</f></c><c r="B1"><v>1</v></c>', $result['xml'], 'the formula and the style stay, a plain value too');
	pg_assert_contains('<c r="C1"><f>"a"&amp;"&lt;b"</f></c>', $result['xml'], 'a text result goes with its type');
	pg_assert_contains('<c r="A2"><f t="shared" si="0" ref="A2:A3"/></c>', $result['xml'], 'a shared formula');
	pg_assert_contains('<c r="B2" vm="1"><f>IMAGE("x")</f><v>#VALUE!</v></c>', $result['xml'], 'a linked value is kept');
	pg_assert_true(pg_office_xml_well_formed($result['xml']), 'still XML');

	$result = pg_office_xlsx_strip_formula_values(str_replace('<sheetData>', '<!-- x --><sheetData>', $xml));
	pg_assert_false($result['ok'], 'a sheet with a comment is left as it was');
}

function test_office_edit_patch_workbook()
{
	$ns = office_edit_test_ns();
	$book = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '"><bookViews><workbookView><extLst><ext uri="{a}"/></extLst></workbookView></bookViews><sheets><sheet name="Sayfa1" sheetId="1"/></sheets><extLst><ext uri="{b}"/></extLst></workbook>';
	$patched = pg_office_xlsx_patch_workbook($book);

	pg_assert_contains('<sheets><sheet name="Sayfa1" sheetId="1"/></sheets><calcPr fullCalcOnLoad="1"/><extLst>', $patched, 'added before the workbook\'s own extension list, not a view\'s');
	pg_assert_contains('standalone="yes"', $patched, 'the declaration stays');

	$patched = pg_office_xlsx_patch_workbook('<workbook xmlns="' . $ns . '"><sheets/><calcPr calcId="191029"/></workbook>');
	pg_assert_contains('<calcPr calcId="191029" fullCalcOnLoad="1"/>', $patched, 'an existing element gains the flag');

	$patched = pg_office_xlsx_patch_workbook('<x:workbook xmlns:x="' . $ns . '"><x:sheets/></x:workbook>');
	pg_assert_contains('<x:sheets/><x:calcPr fullCalcOnLoad="1"/></x:workbook>', $patched, 'with the workbook\'s prefix, at the end');

	pg_assert_same(null, pg_office_xlsx_patch_workbook('<sheet/>'), 'not a workbook');
	pg_assert_true(pg_office_xlsx_date1904('<workbook><workbookPr date1904="1"/></workbook>'), '1904 days');
	pg_assert_false(pg_office_xlsx_date1904('<workbook><workbookPr defaultThemeVersion="1"/></workbook>'), '1900 days');
}

function test_office_edit_scan()
{
	$xml = office_edit_test_sheet(
		'<row r="1"><c r="A1"><f>SUM(B1:C1)</f><v>3</v></c><c r="B1" s="3"><v>1</v></c><c r="C1" vm="2"/></row>'
		. '<row r="2"><c r="A2"><f t="array" ref="A2:A4">B1*2</f><v>2</v></c></row>'
		. '<row r="9"><c r="A9"><f>1</f><v>1</v></c></row>',
		'<sheetProtection sheet="1"/><mergeCells count="1"><mergeCell ref="B2:C3"/></mergeCells>');

	$reader = new XMLReader();
	$reader->XML($xml);
	$scan = pg_office_xlsx_scan($reader, 2, array(3 => true));

	pg_assert_true($scan['addressed'], 'every cell has its address');
	pg_assert_true($scan['protected'], 'protected');
	pg_assert_same(array('A1' => 'formula', 'C1' => 'value', 'A2' => 'formula'), $scan['locked'], 'formulas and linked values of the rows asked for');
	pg_assert_same('SUM(B1:C1)', $scan['formulas']['A1'], 'the formula, for the tooltip');
	pg_assert_same(array('B1' => true), $scan['open'], 'an unlocked cell');
	pg_assert_same(array(array(2, 1, 3, 2)), $scan['merges'], 'the merge');
	pg_assert_same(array(array(2, 0, 4, 0)), $scan['arrays'], 'the array formula\'s range');
	pg_assert_same('merged', pg_office_xlsx_range_lock(3, 2, $scan, array()), 'under a merge');
	pg_assert_same('', pg_office_xlsx_range_lock(2, 1, $scan, array()), 'the merge\'s top left cell');
	pg_assert_same('array', pg_office_xlsx_range_lock(3, 0, $scan, array()), 'inside an array formula');
	pg_assert_same('table', pg_office_xlsx_range_lock(1, 4, $scan, array(array(1, 3, 1, 5, 'table'))), 'a table header');

	$reader = new XMLReader();
	$reader->XML(office_edit_test_sheet('<row r="1"><c><v>1</v></c></row>'));
	pg_assert_false(pg_office_xlsx_scan($reader, 0, array())['addressed'], 'a cell without an address');

	// A form template: the lock opened for whole columns and rows, whose
	// empty cells are not in the file (a new cell takes the row's own style
	// first, else the column's).
	$xml = office_edit_test_sheet(
		'<row r="1" s="3" customFormat="1"><c r="A1"><v>1</v></c><c r="B1" s="3"/></row>'
		. '<row r="2" s="1" customFormat="1"/>'
		. '<row r="3"><c r="B3"><f>1</f><v>1</v></c><c r="C3" s="1"/><c r="D3" s="1"/></row>'
		. '<row r="9" s="3" customFormat="1"/>',
		'<sheetProtection sheet="1"/>',
		'<cols><col min="2" max="3" width="12" style="3"/><col min="4" max="4" style="1"/><col min="5" max="16384" style="3"/></cols>');

	$reader = new XMLReader();
	$reader->XML($xml);
	$scan = pg_office_xlsx_scan($reader, 3, array(3 => true), pg_office_xlsx_column_styles($xml, ''));

	pg_assert_same(array(array(1, 2), array(4, 199)), $scan['columns'], 'the columns whose style opens a new cell, 0-based and cut to the columns shown');
	pg_assert_same(array(1 => true, 2 => false), $scan['rows'], 'the rows with a style of their own, open or locked, up to the rows asked for');
	pg_assert_same(array('B1' => true), $scan['open'], 'a cell in the file with an unlocked style');
	pg_assert_same(array('A1' => true, 'C3' => true), $scan['closed'], 'cells in the file that keep their locked style although their row or column would open a new cell');
	pg_assert_same(array('B3' => 'formula'), $scan['locked'], 'a formula in an open column is a formula');
}

function test_office_edit_save_and_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_edit_test_book_parts();
	$path = office_edit_test_package($parts);
	$directory = sys_get_temp_dir();
	$book = pg_office_read($path, 'liste.xlsx', true);

	pg_assert_true($book['ok'], 'read for editing: ' . $book['error']);
	pg_assert_same(pg_office_version($path), $book['version'], 'the version is the file\'s hash');
	pg_assert_true($book['sheets'][0]['edit']['ok'], 'the first sheet may be edited');
	pg_assert_same(array('C1' => 'formula'), $book['sheets'][0]['edit']['locked'], 'its formula is not');
	pg_assert_contains(array(1, 3, 1, 4, 'table'), $book['sheets'][0]['edit']['ranges'], 'nor its table\'s header');
	pg_assert_contains(array(5, 0, 5, 2, 'merged'), $book['sheets'][0]['edit']['ranges'], 'the merge is listed');
	pg_assert_true($book['sheets'][1]['edit']['protected'], 'the second sheet is protected');
	pg_assert_same(array('B1' => true), $book['sheets'][1]['edit']['open'], 'with one unlocked cell');
	pg_assert_same(array(array(2, 2)), $book['sheets'][1]['edit']['columns'], 'and a column whose style opens a new cell');
	pg_assert_same(array(array(), array()), array($book['sheets'][1]['edit']['rows'], $book['sheets'][1]['edit']['closed']), 'no row of its own style, no cell kept locked by its own');
	pg_assert_same(array(array(), array(), array()), array($book['sheets'][0]['edit']['open'], $book['sheets'][0]['edit']['columns'], $book['sheets'][0]['edit']['rows']), 'an unprotected sheet lists none of them');

	$version = $book['version'];
	$changes = array(
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B1', 'value' => '12,5'),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A1', 'value' => 'Ürün adı'),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A2', 'value' => '20.10.2026'),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'D2', 'value' => 'Dolma kalem'),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B3', 'value' => '4'),
		array('sheet' => 1, 'name' => 'Korumalı', 'cell' => 'B1', 'value' => '8'),
		array('sheet' => 1, 'name' => 'Korumalı', 'cell' => 'C1', 'value' => 'yeni'),
	);

	$refused = pg_office_xlsx_save($path, array(array('sheet' => 0, 'name' => 'Liste', 'cell' => 'C1', 'value' => '1')), $version, $directory);
	pg_assert_same('locked', $refused['code'], 'a formula cell is refused');

	$refused = pg_office_xlsx_save($path, array(array('sheet' => 0, 'name' => 'Liste', 'cell' => 'E1', 'value' => 'Tutar')), $version, $directory);
	pg_assert_same('locked', $refused['code'], 'a table header is refused');

	$refused = pg_office_xlsx_save($path, array(array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B5', 'value' => '1')), $version, $directory);
	pg_assert_same('locked', $refused['code'], 'a cell under a merge is refused');

	$refused = pg_office_xlsx_save($path, array(array('sheet' => 1, 'name' => 'Korumalı', 'cell' => 'A1', 'value' => '1')), $version, $directory);
	pg_assert_same('locked', $refused['code'], 'a locked cell of a protected sheet is refused');

	$refused = pg_office_xlsx_save($path, array(array('sheet' => 0, 'name' => 'Başka', 'cell' => 'B1', 'value' => '1')), $version, $directory);
	pg_assert_same('changed', $refused['code'], 'a sheet that is not the one opened');

	$refused = pg_office_xlsx_save($path, $changes, sha1('another version'), $directory);
	pg_assert_same('changed', $refused['code'], 'a file changed since it was opened');
	pg_assert_same($version, pg_office_version($path), 'nothing was written by the refusals');

	$saved = pg_office_xlsx_save($path, $changes, $version, $directory);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(7, $saved['changed'], 'seven cells');
	pg_assert_same(pg_office_version($path), $saved['version'], 'the new version');
	pg_assert_false($saved['version'] === $version, 'which is not the old one');

	$again = pg_office_xlsx_save($path, $changes, $version, $directory);
	pg_assert_same('changed', $again['code'], 'the old version cannot save twice');

	$book = pg_office_read($path, 'liste.xlsx');
	pg_assert_true($book['ok'], 'read back: ' . $book['error']);

	$rows = $book['sheets'][0]['rows'];
	pg_assert_same(array('Ürün adı', '12.5', '', 'Ad', 'Fiyat'), $rows[0]['cells'], 'the first row, the formula\'s stale result taken out');
	pg_assert_same(array(2), $rows[0]['calc'], 'and the formula marked as waiting to be worked out');
	pg_assert_same(array('2026-10-20', '007', '', 'Dolma kalem', '5'), $rows[1]['cells'], 'a date, a text cell left alone, a changed table value');
	pg_assert_same(array('TRUE', '4', '', 'Silgi', '3'), $rows[2]['cells'], 'a new cell');
	pg_assert_same(array('1', '8', 'yeni'), $book['sheets'][1]['rows'][0]['cells'], 'the unlocked cell of the protected sheet, and a new cell in its unlocked column');

	$zip = new ZipArchive();
	$zip->open($path);

	foreach (array('xl/styles.xml', 'xl/sharedStrings.xml', 'xl/tables/table1.xml', 'xl/_rels/workbook.xml.rels', '[Content_Types].xml') as $name) {
		pg_assert_same($parts[$name], $zip->getFromName($name), $name . ' is carried across untouched');
	}

	pg_assert_contains('<calcPr fullCalcOnLoad="1"/><extLst>', (string) $zip->getFromName('xl/workbook.xml'), 'the formulas are worked out again on opening');
	pg_assert_contains('<row r="5"><c r="A5"><v>1</v></c></row></sheetData><mergeCells', (string) $zip->getFromName('xl/worksheets/sheet1.xml'), 'an unchanged row stays as it was');
	pg_assert_contains('<c r="C1"><f>B1*2</f></c>', (string) $zip->getFromName('xl/worksheets/sheet1.xml'), 'the formula stays, without its result');
	pg_assert_same(count($parts), $zip->numFiles, 'no part added or lost');
	$zip->close();

	$leftovers = array_merge((array) glob($directory . '/office_edit_*.tmp'), (array) glob($directory . '/office_edit_*.part'));
	pg_assert_same(array(), $leftovers, 'no temporary package or part is left behind');

	@unlink($directory . '/office_edit_' . sha1($path) . '.lock');
	unlink($path);
}

function test_office_edit_save_refuses_signed_and_bad_requests()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_edit_test_book_parts();
	$parts['_xmlsignatures/sig1.xml'] = '<Signature/>';
	$path = office_edit_test_package($parts);
	$directory = sys_get_temp_dir();
	$version = pg_office_version($path);

	$book = pg_office_read($path, 'liste.xlsx', true);
	pg_assert_same(array('ok' => false, 'why' => 'signed'), $book['sheets'][0]['edit'], 'the preview offers no cell of a signed file');

	$result = pg_office_xlsx_save($path, array(array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B1', 'value' => '1')), $version, $directory);
	pg_assert_same('signed', $result['code'], 'a signed file is not changed');

	$result = pg_office_xlsx_save($path, array(array('sheet' => 0, 'cell' => 'b1; DROP', 'value' => '1')), $version, $directory);
	pg_assert_same('none', $result['code'], 'not an address');

	$result = pg_office_xlsx_save($path, array(array('sheet' => 0, 'cell' => 'B1', 'value' => str_repeat('a', 32768))), $version, $directory);
	pg_assert_same('long', $result['code'], 'more than a cell holds');

	$result = pg_office_xlsx_save($path, array_fill(0, 5001, array('sheet' => 0, 'cell' => 'B1', 'value' => '1')), $version, $directory);
	pg_assert_same('many', $result['code'], 'too many at once');

	$result = pg_office_xlsx_save($path, array(), $version, $directory);
	pg_assert_same('none', $result['code'], 'nothing asked');

	pg_assert_same($version, pg_office_version($path), 'the file is untouched');

	@unlink($directory . '/office_edit_' . sha1($path) . '.lock');
	unlink($path);

	// A password to modify (Excel's "Tools › General Options"): the workbook
	// part carries a fileSharing reservation, and nothing may be changed.
	$parts = office_edit_test_book_parts();
	$parts['xl/workbook.xml'] = str_replace('<bookViews>', '<fileSharing readOnlyRecommended="1" userName="Erdal" algorithmName="SHA-512" hashValue="q1w2e3==" saltValue="r4t5==" spinCount="100000"/><bookViews>', $parts['xl/workbook.xml']);
	$path = office_edit_test_package($parts);
	$version = pg_office_version($path);

	$book = pg_office_read($path, 'liste.xlsx', true);
	pg_assert_same(array('ok' => false, 'why' => 'password'), $book['sheets'][0]['edit'], 'the preview offers no cell of a workbook with a password to modify');
	pg_assert_same(array('ok' => false, 'why' => 'password'), $book['sheets'][1]['edit'], 'none of its sheets');

	$result = pg_office_xlsx_save($path, array(array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B1', 'value' => '1')), $version, $directory);
	pg_assert_same('password', $result['code'], 'such a workbook is not changed');
	pg_assert_same($version, pg_office_version($path), 'and is untouched');

	@unlink($directory . '/office_edit_' . sha1($path) . '.lock');
	unlink($path);
}

function test_office_edit_write_reservation()
{
	$book = function ($sharing) {
		return '<?xml version="1.0"?><workbook xmlns="' . office_edit_test_ns() . '"><fileVersion appName="xl"/>' . $sharing . '<workbookPr/><sheets/></workbook>';
	};

	pg_assert_true(pg_office_xlsx_write_reserved($book('<fileSharing readOnlyRecommended="1" userName="Erdal" algorithmName="SHA-512" hashValue="q1w2e3==" saltValue="r4t5==" spinCount="100000"/>')), 'a hashed password to modify');
	pg_assert_true(pg_office_xlsx_write_reserved($book('<fileSharing reservationPassword="83AF" userName="Erdal"/>')), 'the old 16-bit password');
	pg_assert_true(pg_office_xlsx_write_reserved(str_replace(array('<workbook ', '<fileSharing ', '</workbook>'), array('<x:workbook ', '<x:fileSharing ', '</x:workbook>'), $book('<fileSharing hashValue="q1w2e3=="/>'))), 'with a prefix');
	pg_assert_false(pg_office_xlsx_write_reserved($book('<fileSharing readOnlyRecommended="1" userName="Erdal"/>')), 'read-only recommended is advice, not a password');
	pg_assert_false(pg_office_xlsx_write_reserved($book('<fileSharing hashValue="" reservationPassword=""/>')), 'an empty password is none');
	pg_assert_false(pg_office_xlsx_write_reserved($book('')), 'no sharing settings');
	pg_assert_false(pg_office_xlsx_write_reserved($book('<fileSharingExt hashValue="q1w2e3=="/>')), 'another element');
	pg_assert_true(pg_office_xlsx_write_reserved($book('<fileSharing readOnlyRecommended="1" userName="R&amp;D >Team" algorithmName="SHA-512" hashValue="q1w2"/>')), 'a raw > in an earlier attribute does not hide the hash');
	pg_assert_true(pg_office_xlsx_write_reserved($book('<fileSharing userName="a>b" reservationPassword="83AF"/>')), 'a raw > in an earlier attribute does not hide the old password');
	pg_assert_true(pg_office_xlsx_write_reserved($book("<fileSharing userName='a>b' hashValue='q1w2'/>")), 'nor in single quotes');
	pg_assert_false(pg_office_xlsx_write_reserved($book("<fileSharing userName=\"x hashValue='q1w2'\"/>")), 'a password name inside another value is no password');
	pg_assert_true(pg_office_xlsx_write_reserved($book('<fileSharing' . str_repeat(' x="1"', 100000) . ' hashValue="q1w2"/>')), 'a start tag too long to walk still keeps its password');
}

// A zip whose entry says it unpacks to fewer bytes than it does: the central
// directory's and the local header's sizes are rewritten after the fact.
function office_edit_test_declare_size($path, $entry, $size)
{
	$bytes = file_get_contents($path);
	$name = strlen($entry);

	foreach (array("PK\x01\x02" => 24, "PK\x03\x04" => 22) as $signature => $field) {
		$offset = 0;

		while (($offset = strpos($bytes, $signature, $offset)) !== false) {
			$name_at = ($signature === "PK\x01\x02") ? 46 : 30;
			$length = unpack('v', substr($bytes, $offset + (($signature === "PK\x01\x02") ? 28 : 26), 2))[1];

			if (($length === $name) && (substr($bytes, $offset + $name_at, $name) === $entry)) {
				$bytes = substr_replace($bytes, pack('V', $size), $offset + $field, 4);
			}

			$offset += 4;
		}
	}

	file_put_contents($path, $bytes);
}

function test_office_edit_scan_limits()
{
	$reader = new XMLReader();
	$reader->XML(office_edit_test_sheet('<row r="1"><c r="A1"><v>1</v></c></row><?pi <row r="1"><c r="B1"/></row>?>'));
	pg_assert_true(pg_office_xlsx_scan($reader, 1, array())['unusual'], 'a processing instruction in the sheet: shown, not changed');

	$merges = '';

	for ($row = 1; $row <= PG_OFFICE_EDIT_MAX_RANGES + 1; $row++) {
		$merges .= '<mergeCell ref="A' . $row . ':B' . $row . '"/>';
	}

	$reader = new XMLReader();
	$reader->XML(office_edit_test_sheet('<row r="1"><c r="A1"><v>1</v></c></row>', '<mergeCells>' . $merges . '</mergeCells>'));
	$scan = pg_office_xlsx_scan($reader, 1, array());
	pg_assert_true($scan['crowded'], 'more merges than are kept');
	pg_assert_same(PG_OFFICE_EDIT_MAX_RANGES, count($scan['merges']), 'and no more are kept');

	if (!pg_office_ready()) {
		return;
	}

	$parts = office_edit_test_book_parts();
	$parts['xl/worksheets/sheet2.xml'] = office_edit_test_sheet('<row r="1"><c r="A1"><v>1</v></c></row>', '<mergeCells>' . $merges . '</mergeCells>');
	$path = office_edit_test_package($parts);
	$book = pg_office_read($path, 'x.xlsx', true);
	pg_assert_same(array('ok' => false, 'why' => 'large'), array_intersect_key($book['sheets'][1]['edit'], array('ok' => 1, 'why' => 1)), 'such a sheet is not offered');

	$refused = pg_office_xlsx_save($path, array(array('sheet' => 1, 'cell' => 'C1', 'value' => '2')), $book['version'], sys_get_temp_dir());
	pg_assert_same('large', $refused['code'], 'nor saved');
	unlink($path);

	// A merge reaching far below and beside what is shown goes to the
	// preview cut to it.
	$parts = office_edit_test_book_parts();
	$parts['xl/worksheets/sheet2.xml'] = office_edit_test_sheet('<row r="1"><c r="A1"><v>1</v></c></row><row r="2"><c r="A2"><v>2</v></c></row>', '<mergeCells><mergeCell ref="B1:ZZ90000"/><mergeCell ref="A70000:B70001"/></mergeCells>');
	$path = office_edit_test_package($parts);
	pg_assert_same(array(array(1, 1, 2, PG_OFFICE_MAX_COLUMNS - 1, 'merged')), pg_office_read($path, 'x.xlsx', true)['sheets'][1]['edit']['ranges'], 'cut to the rows and columns shown, the rest left out');
	unlink($path);

	// A sheet that unpacks to more than its entry declares is read only as
	// far as declared: cut short, it is not offered.
	$parts = office_edit_test_book_parts();
	$parts['xl/worksheets/sheet2.xml'] = office_edit_test_sheet('<row r="1"><c r="A1"><v>1</v></c></row>', '<mergeCells>' . $merges . '</mergeCells>');
	$path = office_edit_test_package($parts);
	office_edit_test_declare_size($path, 'xl/worksheets/sheet2.xml', 1000);
	$zip = new ZipArchive();
	$zip->open($path);
	pg_assert_same(1000, (int) $zip->statName('xl/worksheets/sheet2.xml')['size'], 'the entry declares 1000 bytes');
	$zip->close();
	$book = pg_office_read($path, 'x.xlsx', true);
	pg_assert_false($book['sheets'][1]['edit']['ok'], 'only the declared bytes are read, and they are not a whole sheet');
	unlink($path);
}

function test_office_edit_lock_file_goes()
{
	$directory = sys_get_temp_dir();
	$path = $directory . '/pg_office_edit_lock_' . bin2hex(random_bytes(4)) . '.xlsx';
	$file = $directory . '/office_edit_' . sha1($path) . '.lock';

	$lock = pg_office_edit_lock($path, $directory);
	pg_assert_true(is_array($lock) && is_file($file), 'held, with its file');
	pg_office_edit_unlock($lock);
	pg_assert_false(is_file($file), 'let go: the file is gone');

	$again = pg_office_edit_lock($path, $directory);
	pg_assert_true(is_array($again), 'and the lock can be taken again');
	pg_office_edit_unlock($again);
	pg_assert_false(is_file($file), 'leaving nothing behind');
}

/* ---------------------------------------------------------------------------
   Documents
   --------------------------------------------------------------------------- */

function office_edit_test_w()
{
	return 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
}

// One paragraph's element, parsed on its own inside a document element.
function office_edit_test_paragraph($inner)
{
	$document = new DOMDocument('1.0', 'UTF-8');
	$document->loadXML('<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="' . office_edit_test_w() . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p>' . $inner . '</w:p></w:body></w:document>', LIBXML_NONET);

	return $document->getElementsByTagNameNS(office_edit_test_w(), 'p')->item(0);
}

// The paragraph back as text, without the namespace declaration it borrows.
function office_edit_test_paragraph_xml($paragraph)
{
	return $paragraph->ownerDocument->saveXML($paragraph);
}

function test_office_edit_text_hunks()
{
	pg_assert_same(array(), pg_office_text_hunks('aynı', 'aynı'), 'nothing changed');
	pg_assert_same(array(array(6, 13, 'iyi günler')), pg_office_text_hunks('Sayın merhaba', 'Sayın iyi günler'), 'one change at the end');
	pg_assert_same(array(array(0, 5, 'Bugün'), array(15, 20, 'yarın')), pg_office_text_hunks('Dünkü toplantı bitti', 'Bugün toplantı yarın'), 'two changes, the word between left alone');
	pg_assert_same(array(array(6, 6, 'çok ')), pg_office_text_hunks('Kalem güzel', 'Kalem çok güzel'), 'an addition');
	pg_assert_same(array(array(6, 10, '')), pg_office_text_hunks('Kalem çok güzel', 'Kalem güzel'), 'a removal');
	pg_assert_same(array(array(0, 13, 'M')), pg_office_text_hunks('Sayın Ayşe, merhaba.', 'Merhaba.'), 'the letters kept at the ends of a change stay out of it');
	pg_assert_same(array(array(14, 14, 'BIG ')), pg_office_text_hunks('This contains BOLD, ITALIC', 'This contains BIG BOLD, ITALIC'), 'a word added before one starting with the same letter goes before it, not into it');
	pg_assert_same(array(array(10, 10, 'helpful ')), pg_office_text_hunks('We have a hyperlink here', 'We have a helpful hyperlink here'), 'the same before a link');
	pg_assert_same(array(array(8, 8, 'dost ')), pg_office_text_hunks('Merhaba dünya', 'Merhaba dost dünya'), 'and in Turkish');
	pg_assert_same(array(array(11, 11, 'ler')), pg_office_text_hunks('Mavi  kalem', 'Mavi  kalemler'), 'a word made longer: only the letters added');
	pg_assert_same(array(array(2, 2, 'p')), pg_office_text_hunks('aple pie', 'apple pie'), 'a letter added inside a word is only added');
	pg_assert_same(array(array(3, 3, 'o')), pg_office_text_hunks('book', 'boook'), 'a letter repeated: the first place it can go after the shared start');
	pg_assert_same(array(array(5, 5, 'r')), pg_office_text_hunks('occured', 'occurred'), 'a typo fixed by one letter');
	pg_assert_same(array(array(3, 3, '2')), pg_office_text_hunks('H2O molecule', 'H2O2 molecule'), 'a digit added at the end of a word');
	pg_assert_same(array(array(13, 14, '')), pg_office_text_hunks('Sayın merhabaa', 'Sayın merhaba'), 'a letter taken out of a word');
	pg_assert_same(array(array(2, 2, 'p'), array(14, 14, 'r')), pg_office_text_hunks('aple and occured', 'apple and occurred'), 'two words fixed, each by its letter');
	pg_assert_same(array(array(16, 16, ' adresinden ulaşabilirsiniz.')), pg_office_text_hunks('Site: kodpen.com', 'Site: kodpen.com adresinden ulaşabilirsiniz.'), 'words added after the last word');
	pg_assert_same(array(array(0, 0, 'Yeni')), pg_office_text_hunks('', 'Yeni'), 'into an empty paragraph');
	pg_assert_same(array(array(0, 4, '')), pg_office_text_hunks('Eski', ''), 'everything taken out');
	pg_assert_same(array(array(0, 20, 'Bugün toplantı yarın')), pg_office_text_hunks('Dünkü toplantı bitti', 'Bugün toplantı yarın', 0), 'past the limit: one change');
}

function test_office_edit_docx_paragraph_kinds()
{
	$simple = pg_office_docx_paragraph_edit(office_edit_test_paragraph('<w:pPr><w:jc w:val="center"/></w:pPr><w:bookmarkStart w:id="0" w:name="_Toc1"/><w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>Ayşe</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:tab/><w:t>x</w:t><w:br/><w:t>y</w:t><w:noBreakHyphen/></w:r><w:bookmarkEnd w:id="0"/>'));
	pg_assert_same('', $simple['lock'], 'text, bookmarks and proofing marks may change');
	pg_assert_same("Sayın Ayşe\tx\ny\u{2011}", $simple['text'], 'tabs, line breaks and hyphens as typed characters');
	pg_assert_same(3, count($simple['runs']), 'three runs');

	$wrapped = pg_office_docx_paragraph_edit(office_edit_test_paragraph("<w:r><w:t>iki\nsatır</w:t></w:r>"));
	pg_assert_same('iki satır', $wrapped['text'], 'a line end inside the text is a space, as a word processor shows it');

	$link = pg_office_docx_paragraph_edit(office_edit_test_paragraph('<w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r></w:hyperlink>'));
	pg_assert_same(array('', 'Bkz. site'), array($link['lock'], $link['text']), 'a link may change');

	$cases = array(
		'<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>PAGE</w:instrText></w:r>' => 'field',
		'<w:fldSimple w:instr="DATE"><w:r><w:t>1</w:t></w:r></w:fldSimple>' => 'field',
		'<w:r><w:drawing/></w:r>' => 'object',
		'<w:ins w:id="1" w:author="a"><w:r><w:t>yeni</w:t></w:r></w:ins>' => 'tracked',
		'<w:r><w:rPr><w:b/><w:rPrChange w:id="2" w:author="a"><w:rPr/></w:rPrChange></w:rPr><w:t>x</w:t></w:r>' => 'tracked',
		'<w:pPr><w:rPr><w:ins w:id="3" w:author="a"/></w:rPr></w:pPr><w:r><w:t>x</w:t></w:r>' => 'tracked',
		'<w:r><w:br w:type="page"/></w:r>' => 'break',
		'<w:commentRangeStart w:id="0"/><w:r><w:t>x</w:t></w:r>' => 'comment',
		'<w:r><w:footnoteReference w:id="1"/></w:r>' => 'note',
		'<w:sdt><w:sdtContent><w:r><w:t>x</w:t></w:r></w:sdtContent></w:sdt>' => 'control',
		'<w:r><w:sym w:font="Wingdings" w:char="F0FC"/></w:r>' => 'object',
	);

	foreach ($cases as $inner => $reason) {
		pg_assert_same($reason, pg_office_docx_paragraph_edit(office_edit_test_paragraph($inner))['lock'], $inner);
	}

	$empty = pg_office_docx_paragraph_edit(office_edit_test_paragraph('<w:pPr><w:pStyle w:val="Normal"/></w:pPr>'));
	pg_assert_same(array('', '', 0), array($empty['lock'], $empty['text'], count($empty['runs'])), 'an empty paragraph may be given text');

	$formatting = pg_office_docx_paragraph_edit(office_edit_test_paragraph('<w:pPr><w:jc w:val="left"/><w:pPrChange w:id="4" w:author="a"><w:pPr/></w:pPrChange></w:pPr><w:r><w:t>x</w:t></w:r>'));
	pg_assert_same('tracked', $formatting['lock'], 'a paragraph whose formatting change waits for review');
}

// A paragraph's place: in a content control, a text box or a cell merged
// into the one above, it stays as it is however plain its text.
function test_office_edit_docx_paragraph_places()
{
	$w = office_edit_test_w();
	$document = new DOMDocument('1.0', 'UTF-8');
	$document->loadXML('<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="' . $w . '"><w:body>'
		. '<w:sdt><w:sdtPr><w:lock w:val="contentLocked"/></w:sdtPr><w:sdtContent><w:p><w:r><w:t>Form</w:t></w:r></w:p></w:sdtContent></w:sdt>'
		. '<w:p><w:r><w:drawing><w:txbxContent><w:p><w:r><w:t>Kutu</w:t></w:r></w:p></w:txbxContent></w:drawing></w:r></w:p>'
		. '<w:tbl><w:tr><w:tc><w:tcPr><w:vMerge w:val="restart"/></w:tcPr><w:p><w:r><w:t>Üst</w:t></w:r></w:p></w:tc></w:tr>'
		. '<w:tr><w:tc><w:tcPr><w:vMerge/></w:tcPr><w:p/></w:tc></w:tr></w:tbl>'
		. '</w:body></w:document>', LIBXML_NONET);

	$locks = array();

	foreach (pg_office_docx_paragraphs($document) as $paragraph) {
		$locks[] = pg_office_docx_paragraph_edit($paragraph)['lock'];
	}

	pg_assert_same(array('control', 'object', 'object', '', 'merged'), $locks, 'in order: the control, the drawing, its text box, the first merged cell, the cell merged into it');
}

function test_office_edit_docx_paragraph_write_keeps_formatting()
{
	$write = function ($inner, $text) {
		$paragraph = office_edit_test_paragraph($inner);
		pg_office_docx_paragraph_write($paragraph, pg_office_docx_paragraph_edit($paragraph), $text);

		return str_replace(' xmlns:w="' . office_edit_test_w() . '"', '', office_edit_test_paragraph_xml($paragraph));
	};

	$runs = '<w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>Ayşe</w:t></w:r><w:r><w:t>, merhaba.</w:t></w:r>';

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>Ayşe</w:t></w:r><w:r><w:t>, iyi günler.</w:t></w:r></w:p>',
		$write($runs, 'Sayın Ayşe, iyi günler.'), 'a change in the last run leaves the bold one alone');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>Fatma Hanım</w:t></w:r><w:r><w:t>, merhaba.</w:t></w:r></w:p>',
		$write($runs, 'Sayın Fatma Hanım, merhaba.'), 'the bold name stays bold');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>Ayşe</w:t></w:r><w:r><w:t>!</w:t></w:r></w:p>',
		$write($runs, 'Sayın Ayşe!'), 'a removal inside one run');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:t>merhaba.</w:t></w:r></w:p>',
		$write($runs, 'Sayın merhaba.'), 'a removal across runs: the run left empty goes');

	pg_assert_same('<w:p><w:r><w:t>M</w:t></w:r><w:r><w:t>erhaba.</w:t></w:r></w:p>',
		$write($runs, 'Merhaba.'), 'what replaces a selection takes the formatting of its first letter, as in Word');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Sayın dost </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>Ayşe</w:t></w:r><w:r><w:t>, merhaba.</w:t></w:r></w:p>',
		$write($runs, 'Sayın dost Ayşe, merhaba.'), 'a word added before a bold word is plain, as the text before it');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">This contains BIG </w:t></w:r><w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t>BOLD</w:t></w:r><w:r><w:t>, ITALIC</w:t></w:r></w:p>',
		$write('<w:r><w:t xml:space="preserve">This contains </w:t></w:r><w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t>BOLD</w:t></w:r><w:r><w:t>, ITALIC</w:t></w:r>', 'This contains BIG BOLD, ITALIC'),
		'even when it starts with the same letter as the bold word');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>Ayşe Yılmaz</w:t></w:r><w:r><w:t>, merhaba.</w:t></w:r></w:p>',
		$write($runs, 'Sayın Ayşe Yılmaz, merhaba.'), 'text added after a bold word carries its formatting, as when typing');

	pg_assert_same('<w:p><w:r><w:t>Kalem</w:t><w:tab/><w:t>5</w:t><w:br/><w:t>Silgi</w:t></w:r></w:p>',
		$write('<w:r><w:t>Kalem 5</w:t></w:r>', "Kalem\t5\nSilgi"), 'a tab and a line break');

	pg_assert_same('<w:p><w:pPr><w:pStyle w:val="Baslik"/><w:rPr><w:i/></w:rPr></w:pPr><w:r><w:rPr><w:i/></w:rPr><w:t>Yeni</w:t></w:r></w:p>',
		$write('<w:pPr><w:pStyle w:val="Baslik"/><w:rPr><w:i/></w:rPr></w:pPr>', 'Yeni'), 'an empty paragraph gets a run formatted like its mark');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>mağaza</w:t></w:r></w:hyperlink></w:p>',
		str_replace(' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', '', $write('<w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r></w:hyperlink>', 'Bkz. mağaza')),
		'the words of a link change inside the link');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r></w:hyperlink><w:r><w:t xml:space="preserve"> hemen ve sayfa.</w:t></w:r></w:p>',
		str_replace(' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', '', $write('<w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r></w:hyperlink><w:r><w:t xml:space="preserve"> ve sayfa.</w:t></w:r>', 'Bkz. site hemen ve sayfa.')),
		'words typed right after a link stay out of it');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">We have a helpful </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>hyperlink</w:t></w:r></w:hyperlink><w:r><w:t xml:space="preserve"> here.</w:t></w:r></w:p>',
		str_replace(' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', '', $write('<w:r><w:t xml:space="preserve">We have a </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>hyperlink</w:t></w:r></w:hyperlink><w:r><w:t xml:space="preserve"> here.</w:t></w:r>', 'We have a helpful hyperlink here.')),
		'a word typed before a link starting with the same letter stays out of it');

	pg_assert_same('<w:p><w:pPr><w:rPr><w:sz w:val="20"/></w:rPr></w:pPr><w:r><w:t xml:space="preserve">Web sitemiz: </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>kodpen.com</w:t></w:r></w:hyperlink><w:r><w:rPr><w:sz w:val="20"/></w:rPr><w:t xml:space="preserve"> adresinden ulaşabilirsiniz.</w:t></w:r></w:p>',
		str_replace(' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', '', $write('<w:pPr><w:rPr><w:sz w:val="20"/></w:rPr></w:pPr><w:r><w:t xml:space="preserve">Web sitemiz: </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>kodpen.com</w:t></w:r></w:hyperlink>', 'Web sitemiz: kodpen.com adresinden ulaşabilirsiniz.')),
		'words typed after a link that ends the paragraph go into a new run formatted like the paragraph mark');

	pg_assert_same('<w:p><w:r><w:rPr><w:b/></w:rPr><w:t>app</w:t></w:r><w:r><w:t>le pie</w:t></w:r></w:p>',
		$write('<w:r><w:rPr><w:b/></w:rPr><w:t>ap</w:t></w:r><w:r><w:t>le pie</w:t></w:r>', 'apple pie'), 'a letter added inside a word split across runs leaves the rest of the word as it was');

	pg_assert_same('<w:p><w:r><w:t>H</w:t></w:r><w:r><w:rPr><w:vertAlign w:val="subscript"/></w:rPr><w:t>2</w:t></w:r><w:r><w:t>O2 molecule</w:t></w:r></w:p>',
		$write('<w:r><w:t>H</w:t></w:r><w:r><w:rPr><w:vertAlign w:val="subscript"/></w:rPr><w:t>2</w:t></w:r><w:r><w:t>O molecule</w:t></w:r>', 'H2O2 molecule'), 'the subscript inside the word stays');

	pg_assert_same('<w:p><w:r><w:t>Sayın m</w:t></w:r><w:r><w:rPr><w:i/></w:rPr><w:t>erhaba</w:t></w:r></w:p>',
		$write('<w:r><w:t>Sayın m</w:t></w:r><w:r><w:rPr><w:i/></w:rPr><w:t>erhabaa</w:t></w:r>', 'Sayın merhaba'), 'a letter taken out of the italic part of a word');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>sayfa</w:t></w:r></w:hyperlink><w:r><w:t>mızda</w:t></w:r></w:p>',
		str_replace(' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', '', $write('<w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>sayfa</w:t></w:r></w:hyperlink><w:r><w:t>mız</w:t></w:r>', 'Bkz. sayfamızda')),
		'a suffix added after a link over the stem stays out of the link');

	pg_assert_same('<w:p><w:pPr><w:rPr><w:rStyle w:val="Hyperlink"/><w:sz w:val="20"/></w:rPr></w:pPr><w:r><w:t xml:space="preserve">Web sitemiz: </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>kodpen.com</w:t></w:r></w:hyperlink><w:r><w:rPr><w:sz w:val="20"/></w:rPr><w:t xml:space="preserve"> adresinden.</w:t></w:r></w:p>',
		str_replace(' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', '', $write('<w:pPr><w:rPr><w:rStyle w:val="Hyperlink"/><w:sz w:val="20"/></w:rPr></w:pPr><w:r><w:t xml:space="preserve">Web sitemiz: </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>kodpen.com</w:t></w:r></w:hyperlink>', 'Web sitemiz: kodpen.com adresinden.')),
		'the new run after a link does not take the link style of the paragraph mark');

	pg_assert_same('<w:p><w:pPr><w:rPr><w:vanish/><w:specVanish/></w:rPr></w:pPr><w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r></w:hyperlink><w:r><w:t xml:space="preserve"> sonra.</w:t></w:r></w:p>',
		str_replace(' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', '', $write('<w:pPr><w:rPr><w:vanish/><w:specVanish/></w:rPr></w:pPr><w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r></w:hyperlink>', 'Bkz. site sonra.')),
		'nor is it hidden when the mark is');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Web sitemiz: </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>kodpen.com</w:t></w:r><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr></w:r></w:hyperlink><w:r><w:t xml:space="preserve"> adresinden.</w:t></w:r></w:p>',
		str_replace(' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', '', $write('<w:r><w:t xml:space="preserve">Web sitemiz: </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>kodpen.com</w:t></w:r><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr></w:r></w:hyperlink>', 'Web sitemiz: kodpen.com adresinden.')),
		'a run with no text at the end of the link is still the link: the text goes after it');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r><w:r><w:lastRenderedPageBreak/></w:r></w:hyperlink><w:r><w:t>!.</w:t></w:r></w:p>',
		str_replace(' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', '', $write('<w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r><w:r><w:lastRenderedPageBreak/></w:r></w:hyperlink><w:r><w:t>.</w:t></w:r>', 'Bkz. site!.')),
		'and into the run after the link when there is one');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r></w:hyperlink><w:r><w:t>?</w:t></w:r><w:hyperlink r:id="rId2"><w:r><w:t>!</w:t></w:r></w:hyperlink></w:p>',
		str_replace(' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', '', $write('<w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r></w:hyperlink><w:hyperlink r:id="rId2"><w:r><w:t>!</w:t></w:r></w:hyperlink>', 'Bkz. site?!')),
		'text typed between two links goes into a new run between them, in neither link');

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">a  b </w:t></w:r></w:p>',
		$write('<w:r><w:t>a</w:t></w:r>', 'a  b '), 'spaces kept');

	pg_assert_same('<w:p><w:r><w:t>Ali</w:t><w:noBreakHyphen/><w:t>Veli bilgi</w:t><w:softHyphen/><w:t>sayar</w:t></w:r></w:p>',
		$write('<w:r><w:t>Ali Veli</w:t></w:r>', "Ali\u{2011}Veli bilgi\u{00AD}sayar"), 'hyphens as their own marks');
}

// A document package: the parts given, plus what every document has.
function office_edit_test_docx($body, $settings = '')
{
	$w = office_edit_test_w();
	$rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

	return office_edit_test_package(array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'word/_rels/document.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="' . $rel . '/hyperlink" Target="https://example.test/" TargetMode="External"/>'
			. '<Relationship Id="rId9" Type="' . $rel . '/settings" Target="settings.xml"/></Relationships>',
		'word/settings.xml' => '<?xml version="1.0"?><w:settings xmlns:w="' . $w . '">' . $settings . '</w:settings>',
		'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="' . $w . '"/>',
		'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="' . $w . '" xmlns:r="' . $rel . '"><w:body>' . $body . '<w:sectPr/></w:body></w:document>',
	));
}

function test_office_edit_docx_save_and_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$body = '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Teklif</w:t></w:r></w:p>'
		. '<w:p><w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>Ayşe</w:t></w:r><w:r><w:t>, merhaba.</w:t></w:r></w:p>'
		. '<w:p><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>PAGE</w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>'
		. '<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Hücre</w:t></w:r></w:p></w:tc></w:tr></w:tbl>';
	$path = office_edit_test_docx($body);
	$directory = sys_get_temp_dir();

	$read = pg_office_read($path, 'teklif.docx', true);
	pg_assert_true($read['ok'], 'read for editing: ' . $read['error']);
	pg_assert_same(array('ok' => true, 'why' => '', 'paragraphs' => array('0' => 'Teklif', '1' => 'Sayın Ayşe, merhaba.', '3' => 'Hücre')), array('ok' => $read['edit']['ok'], 'why' => $read['edit']['why'], 'paragraphs' => (array) $read['edit']['paragraphs']), 'the texts of the paragraphs that may change, by position');
	pg_assert_contains('<h1 data-p="0">Teklif</h1>', $read['html'], 'a heading carries its position');
	pg_assert_contains('<p data-p="2" data-lock="field">', $read['html'], 'a field is marked as one that cannot change');
	pg_assert_contains('<td><p data-p="3">Hücre</p></td>', $read['html'], 'a table cell\'s paragraph too');
	pg_assert_same(pg_office_version($path), $read['version'], 'the version');

	$plain = pg_office_read($path, 'teklif.docx');
	pg_assert_false(strpos($plain['html'], 'data-p') !== false, 'a plain preview carries no marks');

	$version = $read['version'];

	$refused = pg_office_docx_save($path, array(array('p' => 2, 'text' => '5')), $version, $directory);
	pg_assert_same('locked', $refused['code'], 'a field paragraph is refused');

	$refused = pg_office_docx_save($path, array(array('p' => 9, 'text' => 'x')), $version, $directory);
	pg_assert_same('missing', $refused['code'], 'a paragraph that is not there');

	$refused = pg_office_docx_save($path, array(array('p' => 'bir', 'text' => 'x')), $version, $directory);
	pg_assert_same('none', $refused['code'], 'not a position');

	$refused = pg_office_docx_save($path, array(array('p' => 1, 'text' => 'x')), sha1('başka'), $directory);
	pg_assert_same('changed', $refused['code'], 'another version');

	$saved = pg_office_docx_save($path, array(
		array('p' => 0, 'text' => 'Teklif (Ekim)'),
		array('p' => 1, 'text' => 'Sayın Ayşe, iyi günler.'),
		array('p' => 3, 'text' => "Kalem\t5"),
	), $version, $directory);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(3, $saved['changed'], 'three paragraphs');

	$back = pg_office_read($path, 'teklif.docx');
	pg_assert_contains('<h1>Teklif (Ekim)</h1>', $back['html'], 'the heading, still a heading');
	pg_assert_contains('<p>Sayın <strong>Ayşe</strong>, iyi günler.</p>', $back['html'], 'the bold name kept');
	pg_assert_contains('<td><p>Kalem<span class="office-tabchar"> </span>5</p></td>', $back['html'], 'a tab in a cell');

	$zip = new ZipArchive();
	$zip->open($path);
	$xml = (string) $zip->getFromName('word/document.xml');
	$zip->close();

	pg_assert_contains('<w:instrText>PAGE</w:instrText>', $xml, 'the field untouched');
	pg_assert_contains('<w:sectPr/>', $xml, 'the section properties untouched');
	pg_assert_contains('standalone="yes"', $xml, 'the declaration kept');

	@unlink($directory . '/office_edit_' . sha1($path) . '.lock');
	unlink($path);
}

function test_office_edit_docx_refusals()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$directory = sys_get_temp_dir();
	$body = '<w:p><w:r><w:t>Metin</w:t></w:r></w:p>';
	$cases = array(
		'protected' => '<w:documentProtection w:edit="readOnly" w:enforcement="1"/>',
		'tracking'  => '<w:trackRevisions/>',
	);

	foreach ($cases as $reason => $settings) {
		$path = office_edit_test_docx($body, $settings);
		$read = pg_office_read($path, 'x.docx', true);

		pg_assert_same(array('ok' => false, 'why' => $reason), $read['edit'], $reason . ': the preview offers no paragraph');
		pg_assert_false(strpos($read['html'], 'data-p') !== false, $reason . ': and marks none');

		$result = pg_office_docx_save($path, array(array('p' => 0, 'text' => 'Yeni')), pg_office_version($path), $directory);
		pg_assert_same($reason, $result['code'], $reason . ': a save is refused');

		@unlink($directory . '/office_edit_' . sha1($path) . '.lock');
		unlink($path);
	}

	$path = office_edit_test_docx($body, '<w:documentProtection w:edit="none" w:enforcement="0"/><w:trackRevisions w:val="false"/>');
	pg_assert_true(pg_office_read($path, 'x.docx', true)['edit']['ok'], 'protection switched off and tracking off are no obstacle');
	unlink($path);

	$path = office_edit_test_docx($body, '<w:documentProtection w:edit="readOnly"/>');
	pg_assert_true(pg_office_read($path, 'x.docx', true)['edit']['ok'], 'protection set up but not enforced is no obstacle');
	unlink($path);

	$path = office_edit_test_package(array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE w:document [<!ENTITY x "y">]><w:document xmlns:w="' . office_edit_test_w() . '"><w:body>' . $body . '</w:body></w:document>',
	));
	$read = pg_office_read($path, 'x.docx', true);
	pg_assert_same(array('ok' => false, 'why' => 'unusual'), $read['edit'], 'a DOCTYPE: nothing offered');
	pg_assert_same('unusual', pg_office_docx_save($path, array(array('p' => 0, 'text' => 'Yeni')), pg_office_version($path), $directory)['code'], 'and nothing saved');
	@unlink($directory . '/office_edit_' . sha1($path) . '.lock');
	unlink($path);
}

function test_office_edit_docx_hyphens_drawn()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: nothing is read');
		return;
	}

	$path = office_edit_test_docx('<w:p><w:r><w:t>Ali</w:t><w:noBreakHyphen/><w:t>Veli bilgi</w:t><w:softHyphen/><w:t>sayar</w:t></w:r></w:p>');
	$read = pg_office_read($path, 'x.docx', true);

	pg_assert_contains('<p data-p="0">Ali&#8209;Veli bilgi&shy;sayar</p>', $read['html'], 'the hyphens drawn as their own characters');
	// Read as an array: before PHP 7.2 an object cast from an array keeps
	// integer keys, which ->{'0'} cannot reach.
	pg_assert_same(array('0' => "Ali\u{2011}Veli bilgi\u{00AD}sayar"), (array) $read['edit']['paragraphs'], 'and in the text the same');
	unlink($path);
}
