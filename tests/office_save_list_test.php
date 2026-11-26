<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for one workbook save taking an ordered list of changes of every
 * kind (includes/fn/office_edit.php): the list read into steps, each step
 * applied to the workbook as the one before it left it, a mixed list read
 * back, and every refusal naming its place and leaving the file byte for
 * byte as it was.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A workbook of two sheets with a styles part: Liste (A1 text, A2 and B2
// numbers) and Diğer (A1 a number).
function office_save_list_test_parts()
{
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . '" xmlns:r="' . $rns . '">';
	$sheet_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml';

	return array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="' . $sheet_type . '"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="' . $sheet_type . '"/></Types>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><sheets><sheet name="Liste" sheetId="1" r:id="rId1"/><sheet name="Diğer" sheetId="2" r:id="rId2"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $rns . '/worksheet" Target="worksheets/sheet2.xml"/>'
			. '<Relationship Id="rId3" Type="' . $rns . '/styles" Target="styles.xml"/></Relationships>',
		'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="' . $ns . '">'
			. '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>',
		'xl/worksheets/sheet1.xml' => $head . '<sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Ad</t></is></c></row><row r="2"><c r="A2"><v>1</v></c><c r="B2"><v>2</v></c></row></sheetData></worksheet>',
		'xl/worksheets/sheet2.xml' => $head . '<sheetData><row r="1"><c r="A1"><v>7</v></c></row></sheetData></worksheet>',
	);
}

function office_save_list_test_file($parts)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_save_list_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return $path;
}

function office_save_list_test_save($path, $changes)
{
	$saved = pg_office_xlsx_save($path, $changes, pg_office_version($path), sys_get_temp_dir());
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');

	return $saved;
}

function office_save_list_test_part($path, $name)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = $zip->getFromName($name);
	$zip->close();

	return $xml;
}

function office_save_list_test_cells($book, $sheet)
{
	$out = array();

	foreach ($book['sheets'][$sheet]['rows'] as $row) {
		foreach ($row['cells'] as $column => $text) {
			if ((string) $text !== '') {
				$out[pg_office_xlsx_column_name($column) . $row['n']] = (string) $text;
			}
		}
	}

	return $out;
}

function test_office_save_list_steps()
{
	$steps = pg_office_xlsx_save_steps(array(
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'a1', 'value' => 'x'),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B1', 'value' => 3),
		array('op' => 'format', 'sheet' => 0, 'name' => 'Liste', 'range' => 'A1:B2', 'set' => array('bold' => true)),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'C1', 'value' => 'y'),
		array('op' => 'insert', 'sheet' => 0, 'name' => 'Liste', 'row' => 1),
		array('op' => 'sheet_add', 'to' => 'Yeni'),
	));

	pg_assert_true($steps['ok'], 'a mixed list is taken');
	pg_assert_same(array('cells', 'format', 'cells', 'shift', 'sheets'), array_column($steps['steps'], 'kind'), 'cells next to each other are one step, the rest one step each, in order');
	pg_assert_same(array(0, 2, 3, 4, 5), array_column($steps['steps'], 'at'), 'each step knows its place in the list');
	pg_assert_same(array(1 => array(0 => 'x', 1 => '3')), $steps['steps'][0]['request'][0]['rows'], 'the cells of the first step');

	$refused = pg_office_xlsx_save_steps(array(
		array('sheet' => 0, 'cell' => 'A1', 'value' => 'x'),
		array('op' => 'format', 'sheet' => 0, 'range' => 'A1', 'set' => array('size' => 9)),
	));
	pg_assert_same(array(false, 'none', 1), array($refused['ok'], $refused['code'], $refused['at']), 'a change that is not one is refused with its place');

	$refused = pg_office_xlsx_save_steps(array(array('op' => 'insert', 'sheet' => 0), array('sheet' => 0, 'cell' => 'A1')));
	pg_assert_same(array('none', 0), array($refused['code'], $refused['at']), 'also the first');

	$refused = pg_office_xlsx_save_steps(array(array('sheet' => 0, 'cell' => 'A1', 'value' => 'x'), array('sheet' => 0, 'cell' => 'A2', 'value' => str_repeat('a', PG_OFFICE_EDIT_MAX_TEXT + 1))));
	pg_assert_same(array('long', 1, 'A2'), array($refused['code'], $refused['at'], $refused['cell']), 'a text too long names its cell and place');

	$refused = pg_office_xlsx_save_steps(array(array('op' => 'format', 'sheet' => 0, 'range' => 'A1:Z1000', 'set' => array('bold' => true))));
	pg_assert_same('many', $refused['code'], 'a format past the cell limit');

	$many = array();

	for ($i = 0; $i <= PG_OFFICE_EDIT_MAX_STEPS; $i++) {
		$many[] = array('op' => 'insert', 'sheet' => 0, 'row' => 2);
	}

	pg_assert_same('steps', pg_office_xlsx_save_steps($many)['code'], 'too many steps in one save');
	pg_assert_same('none', pg_office_xlsx_save_steps(array())['code'], 'an empty list');
}

function test_office_save_list_mixed_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$path = office_save_list_test_file(office_save_list_test_parts());
	$saved = office_save_list_test_save($path, array(
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A2', 'value' => '5'),
		array('op' => 'format', 'sheet' => 0, 'name' => 'Liste', 'range' => 'A2:B2', 'set' => array('bold' => true)),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B2', 'value' => '6'),
		array('op' => 'insert', 'sheet' => 0, 'name' => 'Liste', 'row' => 1),
		// The row put in above is typed into: row 1 is the new one now.
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A1', 'value' => 'Başlık'),
		array('op' => 'sheet_rename', 'sheet' => 1, 'name' => 'Diğer', 'to' => 'Gider'),
		// The renamed sheet is named by its new name from here on.
		array('sheet' => 1, 'name' => 'Gider', 'cell' => 'A1', 'value' => '8'),
		array('op' => 'sheet_add', 'to' => 'Yeni'),
		array('sheet' => 2, 'name' => 'Yeni', 'cell' => 'B3', 'value' => 'ilk'),
		array('op' => 'sheet_move', 'sheet' => 2, 'name' => 'Yeni', 'to' => 0),
	));
	pg_assert_true($saved['ok'], 'the mixed list is saved: ' . $saved['error']);
	pg_assert_same(pg_office_version($path), $saved['version'], 'the new version is the file\'s');

	$book = pg_office_read($path, 'liste.xlsx', true);
	pg_assert_true($book['ok'], 'read back: ' . $book['error']);
	pg_assert_same(array('Yeni', 'Liste', 'Gider'), array_column($book['sheets'], 'name'), 'added, renamed and moved');
	pg_assert_same(array('B3' => 'ilk'), office_save_list_test_cells($book, 0), 'the added sheet holds its typed cell');
	pg_assert_same(array('A1' => 'Başlık', 'A2' => 'Ad', 'A3' => '5', 'B3' => '6'), office_save_list_test_cells($book, 1), 'the cells moved down with the inserted row, the new row typed into');
	pg_assert_same(array('A1' => '8'), office_save_list_test_cells($book, 2), 'the renamed sheet\'s cell');

	$sheet = (string) office_save_list_test_part($path, 'xl/worksheets/sheet1.xml');
	pg_assert_true((bool) preg_match('/<c r="A3" s="([1-9]\d*)"/', $sheet), 'the format went with its cells into row 3');
	pg_assert_true((bool) preg_match('/<c r="B3" s="([1-9]\d*)"[^>]*><v>6<\/v>/', $sheet), 'a value typed after the format keeps the format');
	unlink($path);
}

function test_office_save_list_refusals_leave_the_file()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$cases = array(
		// A rename before, then a cell named by the old name.
		array(array(
			array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A2', 'value' => '9'),
			array('op' => 'sheet_rename', 'sheet' => 0, 'name' => 'Liste', 'to' => 'Gelir'),
			array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A2', 'value' => '10'),
		), 'changed', 2),
		// A name another sheet has, after a format and a cell.
		array(array(
			array('op' => 'format', 'sheet' => 0, 'name' => 'Liste', 'range' => 'A1', 'set' => array('italic' => true)),
			array('sheet' => 1, 'name' => 'Diğer', 'cell' => 'C1', 'value' => 'x'),
			array('op' => 'sheet_add', 'to' => 'liste'),
		), 'sheets_taken', 2),
		// The last visible sheet cannot go once the other has gone.
		array(array(
			array('op' => 'sheet_delete', 'sheet' => 1, 'name' => 'Diğer'),
			array('op' => 'sheet_delete', 'sheet' => 0, 'name' => 'Liste'),
		), 'sheets_last', 1),
		// A sheet deleted earlier in the list is not there for a cell.
		array(array(
			array('op' => 'insert', 'sheet' => 0, 'name' => 'Liste', 'column' => 'A'),
			array('op' => 'sheet_delete', 'sheet' => 1, 'name' => 'Diğer'),
			array('sheet' => 1, 'name' => 'Diğer', 'cell' => 'A1', 'value' => '1'),
		), 'changed', 2),
	);

	foreach ($cases as $number => $case) {
		$path = office_save_list_test_file(office_save_list_test_parts());
		$before = file_get_contents($path);
		$saved = office_save_list_test_save($path, $case[0]);
		pg_assert_same(array(false, $case[1], $case[2]), array($saved['ok'], $saved['code'], $saved['at']), 'case ' . $number . ' refused at its place: ' . $saved['error']);
		pg_assert_contains((string) ($case[2] + 1), $saved['error'], 'case ' . $number . ' the message names the change\'s place');
		pg_assert_same($before, file_get_contents($path), 'case ' . $number . ' the file stays byte for byte');
		unlink($path);
	}

	// A stale version is refused before anything is read.
	$path = office_save_list_test_file(office_save_list_test_parts());
	$before = file_get_contents($path);
	$saved = pg_office_xlsx_save($path, array(array('sheet' => 0, 'cell' => 'A1', 'value' => 'x'), array('op' => 'sheet_add', 'to' => 'Yeni')), sha1('another'), sys_get_temp_dir());
	pg_assert_same(array('changed', null), array($saved['code'], $saved['at']), 'a stale version belongs to no place');
	pg_assert_same($before, file_get_contents($path), 'and the file stays');
	unlink($path);
}
