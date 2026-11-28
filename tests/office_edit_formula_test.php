<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for simple formulas written by a workbook save
 * (includes/fn/office_edit.php): a typed "=" text that is a simple formula,
 * which stored formulas count as simple, a formula cell changed, and the
 * values worked out again on a save: a chain, a circle, a mixed sheet.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_office_edit_formula_typed()
{
	pg_assert_same('SUM(A1:A3)', pg_office_xlsx_typed_formula('=sum(a1:a3)', '', 'en'), 'stored as Excel stores it');
	pg_assert_same('SUM(A1,B2)', pg_office_xlsx_typed_formula('=TOPLA(A1;B2)', '', 'tr'), 'a Turkish name and a semicolon');
	pg_assert_same(null, pg_office_xlsx_typed_formula('=TOPLA(A1;B2)', '', 'en'), 'a Turkish name outside Turkish is not one');
	pg_assert_same('A1*2', pg_office_xlsx_typed_formula('=A1*2', 'date', 'en'), 'a date cell takes a formula');
	pg_assert_same(null, pg_office_xlsx_typed_formula('=A1*2', 'text', 'en'), 'a text cell keeps the text');
	pg_assert_same(null, pg_office_xlsx_typed_formula('A1*2', '', 'en'), 'no leading =');
	pg_assert_same(null, pg_office_xlsx_typed_formula('=VLOOKUP(A1,B1:C9,2)', '', 'en'), 'another function is not simple');
}

function test_office_edit_formula_simple()
{
	pg_assert_true(pg_office_xlsx_simple_formula('SUM(B1:C1)', '', false, false) !== null, 'a plain formula');
	pg_assert_true(pg_office_xlsx_simple_formula('B1*2', 'normal', false, false) !== null, 'written as normal');
	pg_assert_same(null, pg_office_xlsx_simple_formula('B1*2', 'shared', true, false), 'a shared formula');
	pg_assert_same(null, pg_office_xlsx_simple_formula('B1*2', 'array', true, false), 'an array formula');
	pg_assert_same(null, pg_office_xlsx_simple_formula('B1*2', '', false, true), 'a linked value');
	pg_assert_same(null, pg_office_xlsx_simple_formula('TOPLA(B1)', '', false, false), 'a stored formula is read in English');
	pg_assert_same(null, pg_office_xlsx_simple_formula('Sheet2!A1', '', false, false), 'another sheet');
}

function office_edit_formula_test_ns()
{
	return 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
}

function office_edit_formula_test_sheet($rows, $after = '')
{
	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<worksheet xmlns="' . office_edit_formula_test_ns() . '"><sheetData>' . $rows . '</sheetData>' . $after . '</worksheet>';
}

function office_edit_formula_test_context()
{
	return array(
		'styles' => array('date' => array(), 'text' => array(2 => true), 'open' => array()),
		'date1904' => false,
		'protected' => false,
		'language' => 'tr',
	);
}

// The sheet's cells worked out again, with a fixed list of shared strings.
function office_edit_formula_test_work($xml, $strings = array())
{
	return pg_office_xlsx_work_formulas($xml, function () use ($strings) {
		return $strings;
	});
}

// A package of one sheet (and a second one whose formula reads nothing that
// changed), on disk.
function office_edit_formula_test_package($sheet1, $sheet2 = '')
{
	$ns = office_edit_formula_test_ns();
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><sheets><sheet name="Hesap" sheetId="1" r:id="rId1"/><sheet name="Diğer" sheetId="2" r:id="rId2"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $rns . '/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="' . $rns . '/sharedStrings" Target="sharedStrings.xml"/></Relationships>',
		'xl/sharedStrings.xml' => '<?xml version="1.0"?><sst xmlns="' . $ns . '" count="2" uniqueCount="2"><si><t>Toplam</t></si><si><t>7</t></si></sst>',
		'xl/styles.xml' => '<?xml version="1.0"?><styleSheet xmlns="' . $ns . '"><cellXfs count="3"><xf numFmtId="0"/><xf numFmtId="0"/><xf numFmtId="49"/></cellXfs></styleSheet>',
		'xl/worksheets/sheet1.xml' => office_edit_formula_test_sheet($sheet1),
		'xl/worksheets/sheet2.xml' => office_edit_formula_test_sheet(($sheet2 !== '') ? $sheet2 : '<row r="1"><c r="A1"><v>2</v></c><c r="B1"><f>A1*3</f><v>6</v></c></row>'),
	);

	$path = tempnam(sys_get_temp_dir(), 'pg_office_formula_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return $path;
}

function office_edit_formula_test_part($path, $name)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = (string) $zip->getFromName($name);
	$zip->close();

	return $xml;
}

function test_office_edit_formula_patch()
{
	$xml = office_edit_formula_test_sheet('<row r="1"><c r="A1"><v>1</v></c><c r="B1"><f>A1*2</f><v>2</v></c><c r="C1"><f t="shared" si="0" ref="C1:C2">A1</f><v>1</v></c><c r="D1" s="2"/></row>');
	$context = office_edit_formula_test_context();

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(4 => '=topla(A1;B1)')), $context);
	pg_assert_true($result['ok'], 'a typed formula is written');
	pg_assert_contains('<c r="E1"><f>SUM(A1,B1)</f></c>', $result['xml'], 'as Excel stores it, its value left to the work-out');

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(1 => '5')), $context);
	pg_assert_true($result['ok'], 'a simple formula\'s cell is no longer locked');
	pg_assert_contains('<c r="B1"><v>5</v></c>', $result['xml'], 'the formula gives way to a number');

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(1 => '=A1+1')), $context);
	pg_assert_contains('<c r="B1"><f>A1+1</f></c>', $result['xml'], 'one formula for another');

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(1 => '')), $context);
	pg_assert_contains('<c r="A1"><v>1</v></c><c r="C1">', $result['xml'], 'emptied, the formula cell goes');

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(2 => '5')), $context);
	pg_assert_same(array(false, 'locked', 'C1'), array($result['ok'], $result['error'], $result['cell']), 'a shared formula stays locked');

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(3 => '=A1')), $context);
	pg_assert_contains('<c r="D1" s="2" t="inlineStr"><is><t>=A1</t></is></c>', $result['xml'], 'a text-formatted cell keeps the text');

	$result = pg_office_xlsx_patch_sheet($xml, array(1 => array(4 => '=VLOOKUP(A1,B1:C1,2)')), $context);
	pg_assert_contains('<c r="E1" t="inlineStr"><is><t>=VLOOKUP(A1,B1:C1,2)</t></is></c>', $result['xml'], 'a formula that is not simple is kept as text');
}

function test_office_edit_formula_scan()
{
	$xml = office_edit_formula_test_sheet(
		'<row r="1"><c r="A1"><f>SUM(B1:C1)</f><v>3</v></c><c r="B1"><f>VLOOKUP(1,C1:D2,2)</f><v>1</v></c>'
		. '<c r="C1"><f t="shared" si="0" ref="C1:C2">D1</f><v>1</v></c><c r="D1" vm="1"><f>A1</f><v>1</v></c></row>');

	$reader = new XMLReader();
	$reader->XML($xml);
	$scan = pg_office_xlsx_scan($reader, 1, array());

	pg_assert_same(array('B1' => 'formula', 'C1' => 'formula', 'D1' => 'formula'), $scan['locked'], 'only the simple formula is open');
	pg_assert_same('SUM(B1:C1)', $scan['formulas']['A1'], 'its text still goes to the grid');

	$reader = new XMLReader();
	$reader->XML(office_edit_formula_test_sheet('<row r="1"><c r="A1"><f>' . str_repeat('A2+', 2800) . 'A2</f></c></row>'));
	pg_assert_same(array('A1' => 'formula'), pg_office_xlsx_scan($reader, 1, array())['locked'], 'a formula longer than Excel allows stays locked');
}

function test_office_edit_formula_chain_and_circle()
{
	// A chain of three: C1 reads B1, which reads A1, which reads a value.
	$xml = office_edit_formula_test_sheet(
		'<row r="1"><c r="A1"><f>D1+1</f><v>0</v></c><c r="B1"><f>A1*2</f></c><c r="C1" t="str"><f>SUM(A1:B1)</f><v>x</v></c><c r="D1"><v>4</v></c></row>'
		. '<row r="2"><c r="A2"><f>B2+1</f><v>1</v></c><c r="B2"><f>A2+1</f><v>1</v></c><c r="C2"><f>A2*1</f></c><c r="D2"><f>COUNT(A2:C2,D1)</f></c><c r="E2"><f>E2</f></c></row>');
	$result = office_edit_formula_test_work($xml);

	pg_assert_true($result['ok'], 'worked out');
	pg_assert_contains('<c r="A1"><f>D1+1</f><v>5</v></c><c r="B1"><f>A1*2</f><v>10</v></c><c r="C1"><f>SUM(A1:B1)</f><v>15</v></c>', $result['xml'], 'a chain of three, in order whatever the cells\' order');
	pg_assert_contains('<c r="A2" t="e"><f>B2+1</f><v>#REF!</v></c><c r="B2" t="e"><f>A2+1</f><v>#REF!</v></c>', $result['xml'], 'a circle of two');
	pg_assert_contains('<c r="C2" t="e"><f>A2*1</f><v>#REF!</v></c>', $result['xml'], 'a formula reading the circle gets its error');
	pg_assert_contains('<c r="D2"><f>COUNT(A2:C2,D1)</f><v>1</v></c>', $result['xml'], 'COUNT counts past it');
	pg_assert_contains('<c r="E2" t="e"><f>E2</f><v>#REF!</v></c>', $result['xml'], 'a formula reading its own cell');
	pg_assert_same(8, $result['worked'], 'every simple formula has a result');
	pg_assert_true(pg_office_xml_well_formed($result['xml']), 'still XML');
}

function test_office_edit_formula_mixed_sheet()
{
	$xml = office_edit_formula_test_sheet(
		'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="inlineStr"><is><t>3</t></is></c><c r="D1" t="b"><v>1</v></c><c r="E1" t="e"><v>#N/A</v></c></row>'
		. '<row r="2"><c r="A2"><f>VLOOKUP(1,A1:B1,2)</f><v>9</v></c><c r="B2"><f>A2+1</f><v>10</v></c><c r="C2"><f>B1*2</f></c><c r="D2"><f>A1+1</f></c><c r="E2"><f>SUM(A1:D1)</f></c>'
		. '<c r="F2"><f>E1+1</f></c><c r="G2"><f>A1</f></c><c r="H2"><f>D1</f></c><c r="I2"><f>1/0</f></c><c r="J2"><f>AVERAGE(Z9:Z10)</f></c><c r="K2"><f>0.1+0.2</f></c></row>'
		. '<row r="3"><c r="A3" vm="1"><f>B1</f><v>1</v></c><c r="B3"><f t="shared" si="0" ref="B3:B4">C1</f><v>3</v></c></row>');
	$result = office_edit_formula_test_work($xml, array('Toplam', '7'));

	pg_assert_contains('<c r="A2"><f>VLOOKUP(1,A1:B1,2)</f></c>', $result['xml'], 'a formula that is not simple loses its result as today');
	pg_assert_contains('<c r="B2"><f>A2+1</f></c>', $result['xml'], 'and so does a simple one reading it');
	pg_assert_contains('<c r="C2"><f>B1*2</f><v>14</v></c>', $result['xml'], 'a shared string reading as a number');
	pg_assert_contains('<c r="D2" t="e"><f>A1+1</f><v>#VALUE!</v></c>', $result['xml'], 'a word in arithmetic');
	pg_assert_contains('<c r="E2"><f>SUM(A1:D1)</f><v>0</v></c>', $result['xml'], 'SUM leaves text and logical values of a range out');
	pg_assert_contains('<c r="F2" t="e"><f>E1+1</f><v>#N/A</v></c>', $result['xml'], 'an error read is passed on');
	pg_assert_contains('<c r="G2" t="str"><f>A1</f><v>Toplam</v></c>', $result['xml'], 'a reference to a text is that text');
	pg_assert_contains('<c r="H2" t="b"><f>D1</f><v>1</v></c>', $result['xml'], 'and to a logical value that value');
	pg_assert_contains('<c r="I2" t="e"><f>1/0</f><v>#DIV/0!</v></c>', $result['xml'], 'a division by zero');
	pg_assert_contains('<c r="J2" t="e"><f>AVERAGE(Z9:Z10)</f><v>#DIV/0!</v></c>', $result['xml'], 'an average of nothing');
	pg_assert_contains('<c r="K2"><f>0.1+0.2</f><v>0.30000000000000004</v></c>', $result['xml'], 'a number read back the same');
	pg_assert_contains('<c r="A3" vm="1"><f>B1</f><v>1</v></c>', $result['xml'], 'a linked value is left alone');
	pg_assert_contains('<c r="B3"><f t="shared" si="0" ref="B3:B4">C1</f></c>', $result['xml'], 'a shared formula loses its result');

	// Shared strings that cannot be read: the formulas reading them wait.
	$result = pg_office_xlsx_work_formulas($xml, function () {
		return null;
	});
	pg_assert_contains('<c r="C2"><f>B1*2</f></c>', $result['xml'], 'no shared strings, no result');
	pg_assert_contains('<c r="I2" t="e"><f>1/0</f><v>#DIV/0!</v></c>', $result['xml'], 'one reading no shared string still has it');

	// A sheet without a simple formula is only stripped.
	$plain = office_edit_formula_test_sheet('<row r="1"><c r="A1"><f>NOW()</f><v>1</v></c></row>');
	$result = office_edit_formula_test_work($plain);
	pg_assert_same(pg_office_xlsx_strip_formula_values($plain), array('ok' => $result['ok'], 'xml' => $result['xml'], 'count' => $result['count']), 'as before');
}

function test_office_edit_formula_save_and_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$path = office_edit_formula_test_package(
		'<row r="1"><c r="A1"><v>10</v></c><c r="B1"><v>20</v></c><c r="C1"><f>SUM(A1:B1)</f><v>30</v></c><c r="D1"><f>C1*2</f><v>60</v></c></row>'
		. '<row r="2"><c r="A2" t="s"><v>1</v></c></row>');
	$directory = sys_get_temp_dir() . '/pg_office_formula_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
	mkdir($directory, 0700);

	$book = pg_office_read($path, 'hesap.xlsx', true);
	pg_assert_true($book['ok'], 'read for editing: ' . $book['error']);
	pg_assert_same(array(), $book['sheets'][0]['edit']['locked'], 'its simple formulas are open');

	$saved = pg_office_xlsx_save($path, array(
		array('sheet' => 0, 'name' => 'Hesap', 'cell' => 'A1', 'value' => '15'),
		array('sheet' => 0, 'name' => 'Hesap', 'cell' => 'E1', 'value' => '=D1-A2'),
		array('sheet' => 0, 'name' => 'Hesap', 'cell' => 'C1', 'value' => '=AVERAGE(A1:B1)'),
	), $book['version'], $directory);
	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(3, $saved['changed'], 'three cells');

	$sheet = office_edit_formula_test_part($path, 'xl/worksheets/sheet1.xml');
	pg_assert_contains('<c r="C1"><f>AVERAGE(A1:B1)</f><v>17.5</v></c><c r="D1"><f>C1*2</f><v>35</v></c><c r="E1"><f>D1-A2</f><v>28</v></c>', $sheet, 'the formulas worked out again in order, a shared string read as a number');
	pg_assert_contains('<c r="B1"><f>A1*3</f></c>', office_edit_formula_test_part($path, 'xl/worksheets/sheet2.xml'), 'a sheet not changed loses its results as before');
	pg_assert_contains('fullCalcOnLoad="1"', office_edit_formula_test_part($path, 'xl/workbook.xml'), 'the workbook is still worked out again on opening');

	$book = pg_office_read($path, 'hesap.xlsx', true);
	pg_assert_true($book['ok'], 'read back: ' . $book['error']);
	pg_assert_same(array('15', '20', '17.5', '35', '28'), $book['sheets'][0]['rows'][0]['cells'], 'the preview shows the results');
	pg_assert_same('D1-A2', $book['sheets'][0]['edit']['formulas']['E1'], 'and the new formula to the grid');

	foreach ((array) glob($directory . '/*') as $file) {
		@unlink($file);
	}

	@rmdir($directory);
	@unlink($path);
}
