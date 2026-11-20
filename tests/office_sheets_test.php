<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for adding, renaming, deleting and moving the sheets of a workbook
 * (includes/fn/office_sheets.php): the request, the name rules, sheet names
 * in formulas renamed, every refusal with its reason, and saves of whole
 * packages read back by the preview's reader with the parts they did not
 * change compared byte for byte.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A workbook of three sheets: Liste (a formula on Diğer and on itself),
// Diğer (a formula on its own cells), Boş (empty, with printer settings and
// a print area of its own, the open sheet). A defined name points to Liste.
function office_sheets_test_parts($workbook_extra = '', $sheet2 = null)
{
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . '" xmlns:r="' . $rns . '">';
	$sheet_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml';

	if ($sheet2 === null) {
		$sheet2 = $head . '<sheetData><row r="3"><c r="A3"><v>2</v></c><c r="B3"><f>A3*2</f><v>4</v></c></row></sheetData></worksheet>';
	}

	return array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="' . $sheet_type . '"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="' . $sheet_type . '"/>'
			. '<Override PartName="/xl/worksheets/sheet3.xml" ContentType="' . $sheet_type . '"/><Override PartName="/xl/calcChain.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.calcChain+xml"/></Types>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><bookViews><workbookView activeTab="2"/></bookViews>'
			. '<sheets><sheet name="Liste" sheetId="1" r:id="rId1"/><sheet name="Diğer" sheetId="2" r:id="rId2"/><sheet name="Boş" sheetId="5" r:id="rId3"/></sheets>'
			. '<definedNames><definedName name="Fiyat">Liste!$B$1</definedName><definedName name="_xlnm.Print_Area" localSheetId="2">\'Boş\'!$A$1:$B$2</definedName></definedNames>' . $workbook_extra . '</workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="' . $rns . '/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="' . $rns . '/worksheet" Target="worksheets/sheet3.xml"/>'
			. '<Relationship Id="rId7" Type="' . $rns . '/calcChain" Target="calcChain.xml"/></Relationships>',
		'xl/styles.xml' => '<?xml version="1.0"?><styleSheet xmlns="' . $ns . '"><cellXfs count="1"><xf numFmtId="0"/></cellXfs></styleSheet>',
		'xl/calcChain.xml' => '<?xml version="1.0"?><calcChain xmlns="' . $ns . '"><c r="A1" i="1"/><c r="B3" i="2"/></calcChain>',
		'xl/worksheets/sheet1.xml' => $head . '<sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetData><row r="1"><c r="A1"><f>Diğer!A3+Liste!B1+"Diğer!A1"</f><v>3</v></c><c r="B1"><v>1</v></c></row></sheetData>'
			. '<hyperlinks><hyperlink ref="B1" location="\'Diğer\'!A3"/></hyperlinks></worksheet>',
		'xl/worksheets/sheet2.xml' => $sheet2,
		'xl/worksheets/sheet3.xml' => $head . '<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews><sheetData/></worksheet>',
		'xl/worksheets/_rels/sheet3.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/printerSettings" Target="../printerSettings/printerSettings1.bin"/></Relationships>',
		'xl/printerSettings/printerSettings1.bin' => 'printer',
	);
}

function office_sheets_test_save($parts, $change, &$saved)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_sheets_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();
	$saved = pg_office_xlsx_save($path, array($change), pg_office_version($path), sys_get_temp_dir());
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');

	return $path;
}

function office_sheets_test_part($path, $name)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = $zip->getFromName($name);
	$zip->close();

	return $xml;
}

function test_office_sheets_request()
{
	pg_assert_same(array('action' => 'add', 'sheet' => null, 'name' => null, 'to' => 'Yeni'), pg_office_sheets_request(array('op' => 'sheet_add', 'to' => 'Yeni')), 'an added sheet');
	pg_assert_same(array('action' => 'rename', 'sheet' => 1, 'name' => 'Diğer', 'to' => 'Gider'), pg_office_sheets_request(array('op' => 'sheet_rename', 'sheet' => 1, 'name' => 'Diğer', 'to' => 'Gider')), 'a rename');
	pg_assert_same(array('action' => 'move', 'sheet' => 0, 'name' => null, 'to' => 2), pg_office_sheets_request(array('op' => 'sheet_move', 'sheet' => '0', 'to' => 2)), 'a move');
	pg_assert_same(null, pg_office_sheets_request(array('op' => 'sheet_delete', 'sheet' => -1)), 'no sheet before the first');
	pg_assert_same(null, pg_office_sheets_request(array('op' => 'sheet_rename', 'sheet' => 0)), 'a rename names the new name');
	pg_assert_same(null, pg_office_sheets_request(array('op' => 'insert', 'sheet' => 0, 'row' => 2)), 'a row is not a sheet change');
}

function test_office_sheets_names()
{
	pg_assert_same('', pg_office_sheet_name_error('Satışlar 2026', array('Liste')), 'a plain name');
	pg_assert_same('sheets_taken', pg_office_sheet_name_error('LISTE', array('Liste')), 'names compare without case');
	pg_assert_same('sheets_taken', pg_office_sheet_name_error('diğer', array('Diğer')), 'also beyond ASCII');

	foreach (array('', '   ', 'a/b', 'a:b', 'a[1]', 'soru?', '\'tırnak', 'tırnak\'', 'History', str_repeat('x', 32), "a\tb") as $name) {
		pg_assert_same('sheets_name', pg_office_sheet_name_error($name, array()), 'refused: ' . json_encode($name));
	}

	pg_assert_same('', pg_office_sheet_name_error(str_repeat('ş', 31), array()), '31 characters, not bytes');

	pg_assert_same('Gider', pg_office_sheet_quote('Gider'), 'a plain name bare');
	pg_assert_same('Gelir_2', pg_office_sheet_quote('Gelir_2'), 'letters, digits, underscore bare');
	pg_assert_same('\'Gelir 2026\'', pg_office_sheet_quote('Gelir 2026'), 'a space quoted');
	pg_assert_same('\'Ali\'\'nin\'', pg_office_sheet_quote('Ali\'nin'), 'a quote doubled');
	pg_assert_same('\'A1\'', pg_office_sheet_quote('A1'), 'a name that reads as a cell quoted');
	pg_assert_same('\'R2C3\'', pg_office_sheet_quote('R2C3'), 'and as an R1C1 cell');
}

function test_office_sheets_formula()
{
	$cases = array(
		array('Liste!A1+liste!B2', 'Gider!A1+Gider!B2'),
		array('\'Liste\'!A1*2', 'Gider!A1*2'),
		array('SUM(Ocak:Liste!A1)', 'SUM(Ocak:Gider!A1)'),
		array('"Liste!A1"&Liste2!A1', '"Liste!A1"&Liste2!A1'),
		array('[1]Liste!A1+\'C:\\x\\[a.xlsx]Liste\'!A1', '[1]Liste!A1+\'C:\\x\\[a.xlsx]Liste\'!A1'),
		array('Tablo1[[#This Row],[Liste]]', 'Tablo1[[#This Row],[Liste]]'),
		array('#REF!+1', '#REF!+1'),
	);

	foreach ($cases as $case) {
		pg_assert_same($case[1], pg_office_sheets_formula($case[0], 'Liste', 'Gider'), $case[0]);
	}

	pg_assert_same('\'Yeni Liste\'!A1', pg_office_sheets_formula('Liste!A1', 'Liste', 'Yeni Liste'), 'a new name with a space is quoted');
	pg_assert_same('\'Ocak:Yeni Ay\'!A1', pg_office_sheets_formula('Ocak:Liste!A1', 'Liste', 'Yeni Ay'), 'a span quoted whole');

	pg_office_sheets_formula('Diğer!A3*2', 'Liste', null, $found, $span);
	pg_assert_false($found, 'another sheet is not this one');
	pg_office_sheets_formula('SUM(Ocak:Mart!B2)', null, null, $found, $span);
	pg_assert_true($span, 'a span across sheets is seen');
	pg_office_sheets_formula('"Ocak:Mart!B2"', null, null, $found, $span);
	pg_assert_false($span, 'not inside a text');
}

function test_office_sheets_add()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_sheets_test_parts();
	$path = office_sheets_test_save($parts, array('op' => 'sheet_add', 'to' => 'Yeni Sayfa'), $saved);
	pg_assert_true($saved['ok'], 'added: ' . $saved['error']);

	$book = pg_office_read($path, 'liste.xlsx', true);
	pg_assert_true($book['ok'], 'read back: ' . $book['error']);
	pg_assert_same(array('Liste', 'Diğer', 'Boş', 'Yeni Sayfa'), array_column($book['sheets'], 'name'), 'the new sheet comes last');
	pg_assert_same(array(), $book['sheets'][3]['rows'], 'empty');
	pg_assert_true($book['sheets'][3]['edit']['ok'], 'and its cells may be changed');
	pg_assert_contains('<sheet name="Yeni Sayfa" sheetId="6" r:id="rId8"/></sheets>', office_sheets_test_part($path, 'xl/workbook.xml'), 'a new sheet id and relationship id');
	pg_assert_contains('<Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>', office_sheets_test_part($path, '[Content_Types].xml'), 'its content type');

	foreach (array('xl/worksheets/sheet1.xml', 'xl/calcChain.xml', 'xl/styles.xml') as $name) {
		pg_assert_same($parts[$name], office_sheets_test_part($path, $name), $name . ' is carried across untouched');
	}

	unlink($path);

	foreach (array(array('liste', 'sheets_taken'), array('a/b', 'sheets_name')) as $case) {
		$path = office_sheets_test_save($parts, array('op' => 'sheet_add', 'to' => $case[0]), $saved);
		pg_assert_same($case[1], $saved['code'], 'refused: ' . $case[0]);
		pg_assert_same($parts['xl/workbook.xml'], office_sheets_test_part($path, 'xl/workbook.xml'), 'the file stays as it was');
		unlink($path);
	}
}

function test_office_sheets_rename()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_sheets_test_parts();
	$path = office_sheets_test_save($parts, array('op' => 'sheet_rename', 'sheet' => 1, 'name' => 'Diğer', 'to' => 'Gider Kalemi'), $saved);
	pg_assert_true($saved['ok'], 'renamed: ' . $saved['error']);

	$sheet1 = office_sheets_test_part($path, 'xl/worksheets/sheet1.xml');
	pg_assert_contains('<f>\'Gider Kalemi\'!A3+Liste!B1+"Diğer!A1"</f><v>3</v>', $sheet1, 'the formula names the new name, its text and result kept');
	pg_assert_contains('location="\'Gider Kalemi\'!A3"', $sheet1, 'and the link location');
	pg_assert_contains('<sheet name="Gider Kalemi" sheetId="2" r:id="rId2"/>', office_sheets_test_part($path, 'xl/workbook.xml'), 'the sheet has its new name');
	pg_assert_same($parts['xl/worksheets/sheet2.xml'], office_sheets_test_part($path, 'xl/worksheets/sheet2.xml'), 'a sheet not naming it is untouched');

	$book = pg_office_read($path, 'liste.xlsx', true);
	pg_assert_same(array('Liste', 'Gider Kalemi', 'Boş'), array_column($book['sheets'], 'name'), 'read back');
	unlink($path);

	// The defined name follows its sheet.
	$path = office_sheets_test_save($parts, array('op' => 'sheet_rename', 'sheet' => 0, 'name' => 'Liste', 'to' => 'Fiyatlar'), $saved);
	pg_assert_true($saved['ok'], 'renamed: ' . $saved['error']);
	pg_assert_contains('<definedName name="Fiyat">Fiyatlar!$B$1</definedName>', office_sheets_test_part($path, 'xl/workbook.xml'), 'the defined name renamed');
	pg_assert_contains('<f>Diğer!A3+Fiyatlar!B1+"Diğer!A1"</f>', office_sheets_test_part($path, 'xl/worksheets/sheet1.xml'), 'and its own formula');
	unlink($path);

	$path = office_sheets_test_save($parts, array('op' => 'sheet_rename', 'sheet' => 0, 'name' => 'Liste', 'to' => 'BOŞ'), $saved);
	pg_assert_same('sheets_taken', $saved['code'], 'a name another sheet has');
	pg_assert_contains('BOŞ', $saved['error'], 'the message names it');
	unlink($path);

	$path = office_sheets_test_save($parts, array('op' => 'sheet_rename', 'sheet' => 0, 'name' => 'Başka', 'to' => 'X'), $saved);
	pg_assert_same('changed', $saved['code'], 'only the sheet opened');
	unlink($path);
}

function test_office_sheets_delete()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_sheets_test_parts();
	$path = office_sheets_test_save($parts, array('op' => 'sheet_delete', 'sheet' => 2, 'name' => 'Boş'), $saved);
	pg_assert_true($saved['ok'], 'deleted: ' . $saved['error']);

	$zip = new ZipArchive();
	$zip->open($path);

	foreach (array('xl/worksheets/sheet3.xml', 'xl/worksheets/_rels/sheet3.xml.rels', 'xl/printerSettings/printerSettings1.bin') as $name) {
		pg_assert_same(false, $zip->locateName($name), $name . ' goes with it');
	}

	$workbook = $zip->getFromName('xl/workbook.xml');
	pg_assert_contains('<sheets><sheet name="Liste" sheetId="1" r:id="rId1"/><sheet name="Diğer" sheetId="2" r:id="rId2"/></sheets>', $workbook, 'out of the sheets');
	pg_assert_false(strpos($workbook, 'Print_Area') !== false, 'its print area too');
	pg_assert_contains('<workbookView activeTab="1"/>', $workbook, 'the sheet before it opens');
	pg_assert_contains('<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews><sheetData>', $zip->getFromName('xl/worksheets/sheet2.xml'), 'and is selected, given a view when it has none');
	pg_assert_false(strpos($zip->getFromName('xl/_rels/workbook.xml.rels'), 'rId3') !== false, 'its relationship goes');
	pg_assert_false(strpos($zip->getFromName('[Content_Types].xml'), 'sheet3.xml') !== false, 'and its content type');
	pg_assert_same($parts['xl/calcChain.xml'], $zip->getFromName('xl/calcChain.xml'), 'a chain without its cells stays');
	pg_assert_same($parts['xl/worksheets/sheet1.xml'], $zip->getFromName('xl/worksheets/sheet1.xml'), 'the other sheets are untouched');
	$zip->close();

	$book = pg_office_read($path, 'liste.xlsx', true);
	pg_assert_same(array('Liste', 'Diğer'), array_column($book['sheets'], 'name'), 'read back');
	unlink($path);

	// Diğer with no formula pointing at it: its chain entry goes, and the
	// chain with it when it was the last.
	$free = office_sheets_test_parts();
	$free['xl/worksheets/sheet1.xml'] = str_replace(array('Diğer!A3+', ' location="\'Diğer\'!A3"'), array('', ''), $free['xl/worksheets/sheet1.xml']);
	$free['xl/calcChain.xml'] = '<?xml version="1.0"?><calcChain xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><c r="B3" i="2"/><c r="C3"/></calcChain>';
	$path = office_sheets_test_save($free, array('op' => 'sheet_delete', 'sheet' => 1, 'name' => 'Diğer'), $saved);
	pg_assert_true($saved['ok'], 'deleted: ' . $saved['error']);
	pg_assert_same(false, office_sheets_test_part($path, 'xl/calcChain.xml'), 'the empty chain goes');
	pg_assert_false(strpos(office_sheets_test_part($path, 'xl/_rels/workbook.xml.rels'), 'calcChain') !== false, 'with its relationship');
	pg_assert_contains('<definedName name="_xlnm.Print_Area" localSheetId="1">', office_sheets_test_part($path, 'xl/workbook.xml'), 'the print area of the sheet after it moves up a place');
	unlink($path);

	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$drawing = office_sheets_test_parts();
	$drawing['xl/worksheets/_rels/sheet3.xml.rels'] = '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
	$hidden = office_sheets_test_parts();
	$hidden['xl/workbook.xml'] = str_replace(array('name="Liste" sheetId="1"', 'name="Boş" sheetId="5"'), array('name="Liste" state="hidden" sheetId="1"', 'name="Boş" state="veryHidden" sheetId="5"'), $hidden['xl/workbook.xml']);
	$chart = office_sheets_test_parts();
	$chart['xl/charts/chart1.xml'] = '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:f>\'Boş\'!$A$1:$A$3</c:f></c:chartSpace>';

	$cases = array(
		array($parts, 1, 'Diğer', 'sheets_named', 'a formula of another sheet points to it'),
		array($parts, 0, 'Liste', 'sheets_named', 'a defined name points to it'),
		array($chart, 2, 'Boş', 'sheets_named', 'a chart points to it'),
		array($drawing, 2, 'Boş', 'sheets_parts', 'it holds a drawing'),
		array($hidden, 1, 'Diğer', 'sheets_last', 'the last visible sheet'),
		array(office_sheets_test_parts('<workbookProtection lockStructure="1"/>'), 2, 'Boş', 'sheets_protected', 'a protected structure'),
	);

	foreach ($cases as $case) {
		$path = office_sheets_test_save($case[0], array('op' => 'sheet_delete', 'sheet' => $case[1], 'name' => $case[2]), $saved);
		pg_assert_same($case[3], $saved['code'], $case[4]);
		pg_assert_true($saved['error'] !== '', $case[4] . ': with its reason');
		pg_assert_same($case[0]['xl/workbook.xml'], office_sheets_test_part($path, 'xl/workbook.xml'), $case[4] . ': the file stays as it was');
		unlink($path);
	}
}

function test_office_sheets_move()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_sheets_test_parts();
	$path = office_sheets_test_save($parts, array('op' => 'sheet_move', 'sheet' => 2, 'name' => 'Boş', 'to' => 0), $saved);
	pg_assert_true($saved['ok'], 'moved: ' . $saved['error']);

	$workbook = office_sheets_test_part($path, 'xl/workbook.xml');
	pg_assert_contains('<sheets><sheet name="Boş" sheetId="5" r:id="rId3"/><sheet name="Liste" sheetId="1" r:id="rId1"/><sheet name="Diğer" sheetId="2" r:id="rId2"/></sheets>', $workbook, 'first now');
	pg_assert_contains('localSheetId="0"', $workbook, 'its print area follows it');
	pg_assert_contains('<workbookView activeTab="0"/>', $workbook, 'and the open sheet');

	$book = pg_office_read($path, 'liste.xlsx', true);
	pg_assert_same(array('Boş', 'Liste', 'Diğer'), array_column($book['sheets'], 'name'), 'read back');

	foreach ($parts as $name => $xml) {
		if ($name !== 'xl/workbook.xml') {
			pg_assert_same($xml, office_sheets_test_part($path, $name), $name . ' is carried across untouched');
		}
	}

	unlink($path);

	$span = office_sheets_test_parts('', str_replace('<f>A3*2</f>', '<f>SUM(Liste:Boş!B1)</f>', office_sheets_test_parts()['xl/worksheets/sheet2.xml']));
	$path = office_sheets_test_save($span, array('op' => 'sheet_move', 'sheet' => 0, 'name' => 'Liste', 'to' => 2), $saved);
	pg_assert_same('sheets_span', $saved['code'], 'a formula across sheets keeps the order');
	unlink($path);

	@array_map('unlink', (array) glob(sys_get_temp_dir() . '/office_edit_*.part'));
}
