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
			'<sheetProtection sheet="1" objects="1"/>'),
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
	pg_assert_same(array('kind' => 'bool', 'value' => '0'), pg_office_xlsx_typed('yanlış', 'bool', '', false), 'a yes/no cell, in Turkish');
	pg_assert_same(array('kind' => 'bool', 'value' => '1'), pg_office_xlsx_typed('TRUE', 'bool', '', false), 'and in English');
	pg_assert_same(array('kind' => 'string', 'value' => 'TRUE'), pg_office_xlsx_typed('TRUE', 'empty', '', false), 'TRUE elsewhere is text');
	pg_assert_same(array('kind' => 'string', 'value' => '=A1+B1'), pg_office_xlsx_typed('=A1+B1', 'number', '', false), 'no formula is written');
	pg_assert_same("a\nb", pg_office_xlsx_clean_text("a\r\nb\x01"), 'line breaks as stored, control characters out');
	pg_assert_same(null, pg_office_xlsx_clean_text("\xC3\x28"), 'not UTF-8');
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

	$version = $book['version'];
	$changes = array(
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B1', 'value' => '12,5'),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A1', 'value' => 'Ürün adı'),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A2', 'value' => '20.10.2026'),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'D2', 'value' => 'Dolma kalem'),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B3', 'value' => '4'),
		array('sheet' => 1, 'name' => 'Korumalı', 'cell' => 'B1', 'value' => '8'),
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
	pg_assert_same(6, $saved['changed'], 'six cells');
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
	pg_assert_same(array('1', '8'), $book['sheets'][1]['rows'][0]['cells'], 'the unlocked cell of the protected sheet');

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
}
