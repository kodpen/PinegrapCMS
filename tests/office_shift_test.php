<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for inserting and deleting a row or a column of a workbook
 * (includes/fn/office_shift.php): references, ranges and formulas moved, the
 * sheet's merges, dimension, views and column widths, the calculation chain,
 * every refusal with its reason, and a save of a whole package read back by
 * the preview's reader with the parts it did not change compared byte for
 * byte and the cell locks found where the cells went.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_shift_test_op($axis, $at, $insert)
{
	return array('sheet' => 0, 'name' => 'Liste', 'axis' => $axis, 'at' => $at, 'insert' => $insert);
}

// A workbook of two sheets: Liste (values, formulas, a merge, a selection,
// column widths, a page break) and Diğer (a formula on its own cells).
function office_shift_test_parts($sheet1 = null, $workbook_extra = '', $sheet2 = null)
{
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . '" xmlns:r="' . $rns . '">';

	if ($sheet1 === null) {
		$sheet1 = $head . '<dimension ref="A1:D6"/><sheetViews><sheetView workbookViewId="0"><selection activeCell="B5" sqref="B5"/></sheetView></sheetViews>'
			. '<cols><col min="2" max="3" width="20" customWidth="1"/></cols><sheetData>'
			. '<row r="1" spans="1:4"><c r="A1" t="inlineStr"><is><t>Ad</t></is></c><c r="B1"><v>10</v></c><c r="C1"><v>2</v></c><c r="D1"><f>B1*C1</f><v>20</v></c></row>'
			. '<row r="2" spans="1:4"><c r="A2" t="inlineStr"><is><t>Kalem</t></is></c><c r="B2"><v>5</v></c><c r="C2"><v>3</v></c><c r="D2"><f>SUM(B1:B5)+$C$6&amp;"B3"</f><v>18</v></c></row>'
			. '<row r="3"><c r="A3"><v>7</v></c></row>'
			. '<row r="5" ht="30" customHeight="1"><c r="A5"><f>Liste!A3+\'Liste\'!B2+Diğer!A3</f><v>12</v></c><c r="B5"><v>1</v></c></row>'
			. '<row r="6"><c r="A6" t="inlineStr"><is><t>Toplam</t></is></c><c r="C6"><v>4</v></c></row>'
			. '</sheetData><mergeCells count="1"><mergeCell ref="A6:B6"/></mergeCells><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
			. '<rowBreaks count="1" manualBreakCount="1"><brk id="4" max="16383" man="1"/></rowBreaks></worksheet>';
	}

	if ($sheet2 === null) {
		$sheet2 = $head . '<sheetData><row r="3"><c r="A3"><v>2</v></c><c r="B3"><f>A3*2</f><v>4</v></c></row></sheetData></worksheet>';
	}

	return array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><sheets><sheet name="Liste" sheetId="1" r:id="rId1"/><sheet name="Diğer" sheetId="2" r:id="rId2"/></sheets>' . $workbook_extra . '</workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $rns . '/worksheet" Target="worksheets/sheet2.xml"/></Relationships>',
		'xl/styles.xml' => '<?xml version="1.0"?><styleSheet xmlns="' . $ns . '"><cellXfs count="1"><xf numFmtId="0"/></cellXfs></styleSheet>',
		'xl/calcChain.xml' => '<?xml version="1.0"?><calcChain xmlns="' . $ns . '"><c r="D1" i="1"/><c r="D2"/><c r="A5"/><c r="B3" i="2"/></calcChain>',
		'xl/worksheets/sheet1.xml' => $sheet1,
		'xl/worksheets/sheet2.xml' => $sheet2,
	);
}

function office_shift_test_package($parts)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_shift_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return $path;
}

// Saves one change into a package of the parts given; the path, for the
// caller to read and remove.
function office_shift_test_save($parts, $change, &$saved)
{
	$path = office_shift_test_package($parts);
	$saved = pg_office_xlsx_save($path, array($change), pg_office_version($path), sys_get_temp_dir());
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');

	return $path;
}

function test_office_shift_request()
{
	pg_assert_same(array('sheet' => 0, 'name' => 'Liste', 'axis' => 'row', 'at' => 3, 'insert' => true), pg_office_shift_request(array('sheet' => 0, 'name' => 'Liste', 'op' => 'insert', 'row' => 3)), 'a row');
	pg_assert_same(array('sheet' => 1, 'name' => null, 'axis' => 'column', 'at' => 28, 'insert' => false), pg_office_shift_request(array('sheet' => 1, 'op' => 'delete', 'column' => 'ab')), 'a column by its letters');
	pg_assert_same(null, pg_office_shift_request(array('sheet' => 0, 'op' => 'move', 'row' => 3)), 'no other operation');
	pg_assert_same(null, pg_office_shift_request(array('sheet' => 0, 'op' => 'insert', 'row' => 0)), 'no row 0');
	pg_assert_same(null, pg_office_shift_request(array('sheet' => 0, 'op' => 'insert', 'row' => '3.5')), 'a whole row');
	pg_assert_same(null, pg_office_shift_request(array('sheet' => 0, 'op' => 'insert', 'column' => 'XFE')), 'no column past the grid');
	pg_assert_same(null, pg_office_shift_request(array('sheet' => 0, 'op' => 'insert', 'row' => 2, 'column' => 'B')), 'a row or a column, not both');
}

function test_office_shift_references()
{
	$insert = office_shift_test_op('row', 3, true);
	$delete = office_shift_test_op('row', 3, false);
	$column = office_shift_test_op('column', 2, false);

	pg_assert_same('B4', pg_office_shift_ref('B3', $insert), 'a cell on the line moves down');
	pg_assert_same('B2', pg_office_shift_ref('B2', $insert), 'one above stays');
	pg_assert_same('$B$9', pg_office_shift_ref('$B$8', $insert), 'an absolute address moves too');
	pg_assert_same('A1:C6', pg_office_shift_ref('A1:C5', $insert), 'a range across the line widens');
	pg_assert_same('A:C', pg_office_shift_ref('A:C', $insert), 'whole columns stay for a row');
	pg_assert_same('$4:$7', pg_office_shift_ref('$3:$6', $insert), 'whole rows move');
	pg_assert_same(null, pg_office_shift_ref('B3', $delete), 'a deleted cell');
	pg_assert_same('B3', pg_office_shift_ref('B4', $delete), 'one below moves up');
	pg_assert_same('A1:C4', pg_office_shift_ref('A1:C5', $delete), 'a range across the line narrows');
	pg_assert_same('A3:C4', pg_office_shift_ref('A3:C5', $delete), 'from the line');
	pg_assert_same(null, pg_office_shift_ref('A3:C3', $delete), 'a range of the deleted row alone');
	pg_assert_same('A1:B4', pg_office_shift_ref('A1:C4', $column), 'a column deleted from a range');
	pg_assert_same(null, pg_office_shift_ref('B1', $column), 'a cell of the deleted column');
	pg_assert_same('B7', pg_office_shift_ref('C7', $column), 'a cell right of it');
	pg_assert_same('B:C', pg_office_shift_ref('C:D', $column), 'whole columns');
	pg_assert_same(null, pg_office_shift_ref('A1048576', office_shift_test_op('row', 2, true)), 'pushed off the grid');
	pg_assert_same(false, pg_office_shift_ref('LOG', $insert), 'not a reference');
	pg_assert_same('A1 C2:D2', pg_office_shift_ref_list('A1 B3 C2:D2', $delete), 'a list loses its deleted range');
}

function test_office_shift_formulas()
{
	$insert = office_shift_test_op('row', 3, true);
	$delete = office_shift_test_op('row', 3, false);

	pg_assert_same('SUM(B1:B6)+$C$7&"B3"', pg_office_shift_formula('SUM(B1:B5)+$C$6&"B3"', $insert, 'Liste'), 'quoted text is left alone');
	pg_assert_same('Liste!A4+\'Liste\'!B2+Diğer!A3', pg_office_shift_formula('Liste!A3+\'Liste\'!B2+Diğer!A3', $insert, 'Liste'), 'this sheet by its name, not the other');
	pg_assert_same('#REF!+Liste!#REF!+A3', pg_office_shift_formula('A3+Liste!B3+A4', $delete, 'Liste'), 'a deleted cell becomes #REF!');
	pg_assert_same('LOG10(A4)+Tablo1[[#This Row],[A3]]+[1]Liste!A3', pg_office_shift_formula('LOG10(A3)+Tablo1[[#This Row],[A3]]+[1]Liste!A3', $insert, 'Liste'), 'functions, structured and external references stay');
	pg_assert_same("'My ''Sheet'!B4", pg_office_shift_formula("'My ''Sheet'!B3", $insert, "My 'Sheet"), 'a quoted name with a quote in it');
	pg_assert_same('SUM(1E3,A4)', pg_office_shift_formula('SUM(1E3,A3)', $insert, 'Liste'), 'a number in E notation is no reference');
	pg_assert_same(null, pg_office_shift_formula('SUM(Liste:Diğer!A3)', $insert, 'Liste'), 'a 3D reference through this sheet is refused');
	pg_assert_same('SUM(Ocak:Şubat!A3)', pg_office_shift_formula('SUM(Ocak:Şubat!A3)', $insert, 'Liste'), 'one through other sheets stays');
	pg_assert_same('B6+$A$9', pg_office_shift_formula('B5+$A$8', $insert, 'Liste', 1), 'a filled formula whose rows stay on one side');
	pg_assert_same(null, pg_office_shift_formula('B2', $insert, 'Liste', 2), 'a filled formula whose rows would cross the line');
}

function test_office_shift_sheet_rows()
{
	$parts = office_shift_test_parts();
	$shifted = pg_office_xlsx_shift_sheet($parts['xl/worksheets/sheet1.xml'], office_shift_test_op('row', 3, true), 'Liste');
	pg_assert_true($shifted['ok'], 'inserted: ' . $shifted['error']);
	$xml = $shifted['xml'];

	pg_assert_contains('<dimension ref="A1:D7"/>', $xml, 'the dimension grows');
	pg_assert_contains('<selection activeCell="B6" sqref="B6"/>', $xml, 'the selection moves');
	pg_assert_contains('<row r="1" spans="1:4"><c r="A1" t="inlineStr"><is><t>Ad</t></is></c><c r="B1"><v>10</v></c><c r="C1"><v>2</v></c><c r="D1"><f>B1*C1</f><v>20</v></c></row>', $xml, 'a row above, byte for byte');
	pg_assert_contains('<c r="D2"><f>SUM(B1:B6)+$C$7&amp;"B3"</f>', $xml, 'a formula above the line points to the moved cells');
	pg_assert_contains('<row r="4"><c r="A4"><v>7</v></c></row>', $xml, 'row 3 is row 4');
	pg_assert_contains('<row r="6" ht="30" customHeight="1"><c r="A6"><f>Liste!A4+\'Liste\'!B2+Diğer!A3</f>', $xml, 'row 5 is row 6, its height kept');
	pg_assert_contains('<mergeCell ref="A7:B7"/>', $xml, 'the merge moves');
	pg_assert_contains('<brk id="5" max="16383" man="1"/>', $xml, 'the page break moves');
	pg_assert_contains('<col min="2" max="3" width="20" customWidth="1"/>', $xml, 'column widths stay for a row');
	pg_assert_true(pg_office_xml_well_formed($xml), 'well-formed');

	$shifted = pg_office_xlsx_shift_sheet($parts['xl/worksheets/sheet1.xml'], office_shift_test_op('row', 6, false), 'Liste');
	pg_assert_true($shifted['ok'], 'a row deleted: ' . $shifted['error']);
	pg_assert_false(strpos($shifted['xml'], 'Toplam') !== false, 'its cells go');
	pg_assert_false(strpos($shifted['xml'], '<mergeCells') !== false, 'a merge on it alone goes, with its empty list');
	pg_assert_contains('<f>SUM(B1:B5)+#REF!&amp;"B3"</f>', $shifted['xml'], 'a reference to it is #REF!');
	pg_assert_contains('<dimension ref="A1:D5"/>', $shifted['xml'], 'the dimension shrinks');
}

function test_office_shift_sheet_columns()
{
	$parts = office_shift_test_parts();
	$shifted = pg_office_xlsx_shift_sheet($parts['xl/worksheets/sheet1.xml'], office_shift_test_op('column', 3, true), 'Liste');
	pg_assert_true($shifted['ok'], 'inserted: ' . $shifted['error']);
	$xml = $shifted['xml'];

	pg_assert_contains('<row r="1"><c r="A1" t="inlineStr"><is><t>Ad</t></is></c><c r="B1"><v>10</v></c><c r="D1"><v>2</v></c><c r="E1"><f>B1*D1</f>', $xml, 'cells move right, the row\'s spans hint taken out');
	pg_assert_contains('<col min="2" max="4" width="20" customWidth="1"/>', $xml, 'a width span across the line widens');
	pg_assert_contains('<mergeCell ref="A6:B6"/>', $xml, 'a merge left of the line stays');
	pg_assert_contains('<dimension ref="A1:E6"/>', $xml, 'the dimension widens');
	pg_assert_contains('<row r="3"><c r="A3"><v>7</v></c></row>', $xml, 'a row with nothing to move stays byte for byte');

	$shifted = pg_office_xlsx_shift_sheet($parts['xl/worksheets/sheet1.xml'], office_shift_test_op('column', 3, false), 'Liste');
	pg_assert_true($shifted['ok'], 'a column deleted: ' . $shifted['error']);
	pg_assert_contains('<c r="B1"><v>10</v></c><c r="C1"><f>B1*#REF!</f><v>20</v></c>', $shifted['xml'], 'its cells go and a reference to them is #REF!');
	pg_assert_contains('<f>SUM(B1:B5)+#REF!&amp;"B3"</f>', $shifted['xml'], 'an absolute one too');
	pg_assert_contains('<col min="2" max="2" width="20" customWidth="1"/>', $shifted['xml'], 'the width span narrows');
}

function test_office_shift_sheet_refusals()
{
	$parts = office_shift_test_parts();
	$sheet = $parts['xl/worksheets/sheet1.xml'];
	foreach (array(true, false) as $insert) {
		$result = pg_office_xlsx_shift_sheet($sheet, office_shift_test_op('column', 2, $insert), 'Liste');
		pg_assert_same(array('shift_cross', 'A6:B6'), array($result['error'], $result['cell']), 'a merge across the column, ' . ($insert ? 'insert' : 'delete'));
	}

	$cases = array(
		array(str_replace('<pageMargins', '<conditionalFormatting sqref="A1"><cfRule type="expression" priority="1"><formula>A1&gt;1</formula></cfRule></conditionalFormatting><pageMargins', $sheet), 'shift_format'),
		array(str_replace('<pageMargins', '<dataValidations count="1"><dataValidation sqref="A1"/></dataValidations><pageMargins', $sheet), 'shift_validation'),
		array(str_replace('<mergeCells', '<autoFilter ref="A1:D6"/><mergeCells', $sheet), 'shift_filter'),
		array(str_replace('</worksheet>', '<drawing r:id="rId1"/></worksheet>', $sheet), 'shift_drawing'),
		array(str_replace('<sheetData>', '<sheetProtection sheet="1"/><sheetData>', $sheet), 'shift_protected'),
		array(str_replace('<row r="6">', '<row r="6"><c r="A9"><f t="shared" ref="A9:A12" si="0">B1</f></c>', $sheet), 'shift_formula'),
		array(str_replace('<row r="1" spans="1:4">', '<row r="1" spans="1:4"><c r="E1"><f t="array" ref="E1:E4">B1:B4</f></c>', $sheet), 'shift_cross'),
		array(str_replace('<row r="6">', '<row r="1048576"><c r="A1048576"><v>1</v></c></row><row r="6">', $sheet), 'shift_full'),
	);

	foreach ($cases as $index => $case) {
		$result = pg_office_xlsx_shift_sheet($case[0], office_shift_test_op('row', 3, true), 'Liste');
		pg_assert_same($case[1], $result['error'], 'refusal ' . $index . ' (' . $case[1] . ')');
	}
}

function test_office_shift_save_and_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_shift_test_parts();
	$path = office_shift_test_save($parts, array('sheet' => 0, 'name' => 'Liste', 'op' => 'insert', 'row' => 3), $saved);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(1, $saved['changed'], 'one change');

	$book = pg_office_read($path, 'liste.xlsx', true);
	pg_assert_true($book['ok'], 'read back: ' . $book['error']);

	$numbers = array_map(function ($row) {
		return $row['n'];
	}, $book['sheets'][0]['rows']);

	pg_assert_same(array(1, 2, 4, 6, 7), $numbers, 'the rows past the line moved down');
	pg_assert_same(array('Toplam', '', '4'), $book['sheets'][0]['rows'][4]['cells'], 'with their cells');
	pg_assert_same(array('D1' => 'formula', 'D2' => 'formula', 'A6' => 'formula'), $book['sheets'][0]['edit']['locked'], 'the formula locks are where the formulas went');
	pg_assert_contains(array(7, 0, 7, 1, 'merged'), $book['sheets'][0]['edit']['ranges'], 'and the merge lock with its merge');
	pg_assert_same(array('2', ''), $book['sheets'][1]['rows'][0]['cells'], 'the other sheet\'s values stay, its formula without its stored result');
	pg_assert_same(array(1), $book['sheets'][1]['rows'][0]['calc'], 'waiting to be worked out again');

	$zip = new ZipArchive();
	$zip->open($path);

	foreach (array('xl/styles.xml', 'xl/_rels/workbook.xml.rels', '[Content_Types].xml') as $name) {
		pg_assert_same($parts[$name], $zip->getFromName($name), $name . ' is carried across untouched');
	}

	pg_assert_same('<?xml version="1.0"?><calcChain xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><c r="D1" i="1"/><c r="D2"/><c r="A6"/><c r="B3" i="2"/></calcChain>', $zip->getFromName('xl/calcChain.xml'), 'the chain\'s cells of this sheet moved, the other sheet\'s not');
	pg_assert_contains('<calcPr fullCalcOnLoad="1"/>', (string) $zip->getFromName('xl/workbook.xml'), 'the formulas are worked out again on opening');
	pg_assert_same(count($parts), $zip->numFiles, 'no part added or lost');
	$zip->close();
	unlink($path);

	// A deleted row takes its chain entry; the next one keeps the sheet id.
	$path = office_shift_test_save($parts, array('sheet' => 0, 'name' => 'Liste', 'op' => 'delete', 'row' => 1), $saved);
	pg_assert_true($saved['ok'], 'row 1 deleted: ' . $saved['error']);
	$zip = new ZipArchive();
	$zip->open($path);
	pg_assert_same('<?xml version="1.0"?><calcChain xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><c r="D1" i="1"/><c r="A4"/><c r="B3" i="2"/></calcChain>', $zip->getFromName('xl/calcChain.xml'), 'the entry that named the sheet is gone, the next names it');
	$zip->close();
	unlink($path);
	@array_map('unlink', (array) glob(sys_get_temp_dir() . '/office_edit_*.part'));
}

function test_office_shift_save_refusals()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$insert = array('sheet' => 0, 'name' => 'Liste', 'op' => 'insert', 'row' => 3);
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';

	$table = office_shift_test_parts();
	$table['xl/worksheets/_rels/sheet1.xml.rels'] = '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/table" Target="../tables/table1.xml"/></Relationships>';
	$table['xl/tables/table1.xml'] = '<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ref="A1:B2"/>';

	$other = office_shift_test_parts(null, '', str_replace('<f>A3*2</f>', '<f>Liste!B2*2</f>', office_shift_test_parts()['xl/worksheets/sheet2.xml']));
	$quoted = office_shift_test_parts(null, '', str_replace('<f>A3*2</f>', '<f>\'Liste\'!B2*2</f>', office_shift_test_parts()['xl/worksheets/sheet2.xml']));
	$named = office_shift_test_parts(null, '<definedNames><definedName name="Fiyat">Liste!$B$2</definedName></definedNames>');
	$local = office_shift_test_parts(null, '<definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">$A$1:$D$6</definedName></definedNames>');
	$chart = office_shift_test_parts();
	$chart['xl/charts/chart1.xml'] = '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:f>Liste!$B$1:$B$5</c:f></c:chartSpace>';
	$words = office_shift_test_parts(null, '', str_replace('<c r="A3"><v>2</v></c>', '<c r="A3" t="inlineStr"><is><t>Liste: toplam</t></is></c>', office_shift_test_parts()['xl/worksheets/sheet2.xml']));

	$cases = array(
		array($table, 'shift_table', 'a table on the sheet'),
		array($other, 'shift_other', 'another sheet\'s formula'),
		array($quoted, 'shift_other', 'another sheet\'s formula, the name in quotes'),
		array($named, 'shift_name', 'a defined name'),
		array($local, 'shift_name', 'a print area of the sheet'),
		array($chart, 'shift_other', 'a chart'),
	);

	foreach ($cases as $case) {
		$path = office_shift_test_save($case[0], $insert, $saved);
		pg_assert_same($case[1], $saved['code'], $case[2]);
		pg_assert_contains('Liste', $saved['error'], $case[2] . ': the message names the sheet');
		$zip = new ZipArchive();
		$zip->open($path);
		pg_assert_same($case[0]['xl/worksheets/sheet1.xml'], $zip->getFromName('xl/worksheets/sheet1.xml'), $case[2] . ': the file stays as it was');
		$zip->close();
		unlink($path);
	}

	$path = office_shift_test_save($words, $insert, $saved);
	pg_assert_true($saved['ok'], 'the sheet\'s name in another sheet\'s text is no reference: ' . $saved['error']);
	unlink($path);

	$path = office_shift_test_package(office_shift_test_parts());
	$mixed = pg_office_xlsx_save($path, array($insert, array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A1', 'value' => 'x')), pg_office_version($path), sys_get_temp_dir());
	pg_assert_same('none', $mixed['code'], 'an insert is a save of its own');
	$stale = pg_office_xlsx_save($path, array($insert), sha1('another version'), sys_get_temp_dir());
	pg_assert_same('changed', $stale['code'], 'only into the version opened');
	$renamed = pg_office_xlsx_save($path, array(array('sheet' => 0, 'name' => 'Başka', 'op' => 'insert', 'row' => 3)), pg_office_version($path), sys_get_temp_dir());
	pg_assert_same('changed', $renamed['code'], 'only into the sheet opened');
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');
	unlink($path);
}
