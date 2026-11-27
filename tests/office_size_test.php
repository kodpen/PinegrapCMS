<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for column widths, row heights and a frozen top row of a workbook
 * (includes/fn/office_size.php): the request, the sheet written for each
 * (entries of <cols> split, a row's ht, a view's frozen pane), every refusal
 * with its reason, and saves of whole packages read back by the reader with
 * the parts they did not change compared byte for byte.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_size_test_head()
{
	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
}

// Liste: a view with a selection, default sizes, columns B to E in one
// entry with a style, rows 1, 2 (its own height) and 4.
function office_size_test_sheet($views = null, $cols = null, $protection = '')
{
	$views = ($views === null) ? '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><selection activeCell="C3" sqref="C3"/></sheetView></sheetViews>' : $views;
	$cols = ($cols === null) ? '<cols><col min="2" max="5" width="12" style="1" customWidth="1"/></cols>' : $cols;

	return office_size_test_head() . '<dimension ref="A1:B4"/>' . $views . '<sheetFormatPr defaultRowHeight="15" x14ac:dyDescent="0.25" xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac"/>' . $cols . '<sheetData>'
		. '<row r="1"><c r="A1" t="inlineStr"><is><t>Ad</t></is></c></row>'
		. '<row r="2" spans="1:2" ht="30" customHeight="1"><c r="A2"><v>5</v></c></row>'
		. '<row r="4"><c r="A4"><f>A2*2</f><v>10</v></c></row>'
		. '</sheetData>' . $protection . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';
}

function office_size_test_parts($sheet1 = null, $workbook_extra = '')
{
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';

	return array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '">' . $workbook_extra . '<sheets><sheet name="Liste" sheetId="1" r:id="rId1"/><sheet name="Diğer" sheetId="2" r:id="rId2"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $rns . '/worksheet" Target="worksheets/sheet2.xml"/>'
			. '<Relationship Id="rId3" Type="' . $rns . '/styles" Target="styles.xml"/></Relationships>',
		'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="' . $ns . '"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>',
		'xl/worksheets/sheet1.xml' => ($sheet1 === null) ? office_size_test_sheet() : $sheet1,
		'xl/worksheets/sheet2.xml' => office_size_test_head() . '<sheetData><row r="1"><c r="A1"><f>Liste!A2</f><v>5</v></c></row></sheetData></worksheet>',
	);
}

function office_size_test_save($parts, $changes, &$saved)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_size_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();
	$saved = pg_office_xlsx_save($path, $changes, pg_office_version($path), sys_get_temp_dir());
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');

	return $path;
}

function office_size_test_part($path, $name)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = $zip->getFromName($name);
	$zip->close();

	return $xml;
}

function test_office_size_request()
{
	pg_assert_same(array('kind' => 'width', 'sheet' => 0, 'name' => 'Liste', 'column' => 2, 'width' => 12.35), pg_office_size_request(array('op' => 'width', 'sheet' => '0', 'name' => 'Liste', 'column' => 'c', 'width' => 12.345)), 'a width to two decimals, its column 0-based');
	pg_assert_same(array('kind' => 'width', 'sheet' => 1, 'name' => null, 'column' => 0, 'width' => null), pg_office_size_request(array('op' => 'width', 'sheet' => 1, 'column' => 'A', 'width' => null)), 'a width taken off');
	pg_assert_same(array('kind' => 'height', 'sheet' => 0, 'name' => 'Liste', 'row' => 7, 'height' => 20.0), pg_office_size_request(array('op' => 'height', 'sheet' => 0, 'name' => 'Liste', 'row' => 7, 'height' => 20)), 'a height in points');
	pg_assert_same(array('kind' => 'freeze', 'sheet' => 0, 'name' => 'Liste', 'on' => false), pg_office_size_request(array('op' => 'freeze', 'sheet' => 0, 'name' => 'Liste', 'on' => false)), 'freeze off');

	$cases = array(
		array(array('op' => 'width', 'sheet' => 0, 'column' => 'C', 'width' => 0), 'a width of nothing'),
		array(array('op' => 'width', 'sheet' => 0, 'column' => 'C', 'width' => 256), 'wider than Excel takes'),
		array(array('op' => 'width', 'sheet' => 0, 'column' => 'C', 'width' => '12'), 'a width as text'),
		array(array('op' => 'width', 'sheet' => 0, 'column' => 'C'), 'no width given'),
		array(array('op' => 'width', 'sheet' => 0, 'column' => 'ZZ', 'width' => 10), 'past the grid\'s columns'),
		array(array('op' => 'width', 'sheet' => 0, 'column' => 'C3', 'width' => 10), 'a cell, not a column'),
		array(array('op' => 'height', 'sheet' => 0, 'row' => 0, 'height' => 10), 'row 0'),
		array(array('op' => 'height', 'sheet' => 0, 'row' => '7', 'height' => 10), 'a row as text'),
		array(array('op' => 'height', 'sheet' => 0, 'row' => 7, 'height' => 410), 'taller than Excel takes'),
		array(array('op' => 'freeze', 'sheet' => 0, 'on' => 1), 'freeze is true or false'),
		array(array('op' => 'insert', 'sheet' => 0, 'row' => 2), 'an insert is not a size'),
	);

	foreach ($cases as $case) {
		pg_assert_same(null, pg_office_size_request($case[0]), $case[1]);
	}
}

function test_office_size_width_sheet()
{
	// Inside an entry over several columns: split around it, style kept.
	$patched = pg_office_xlsx_size_width(office_size_test_sheet(), 2, 20.5);
	pg_assert_true($patched['ok'], 'written');
	pg_assert_contains('<cols><col min="2" max="2" width="12" style="1" customWidth="1"/><col min="3" max="3" width="20.5" style="1" customWidth="1"/><col min="4" max="5" width="12" style="1" customWidth="1"/></cols>', $patched['xml'], 'B and D:E keep theirs, C its new width');

	// A column no entry names: one of its own, in its place.
	$patched = pg_office_xlsx_size_width(office_size_test_sheet(), 0, 8);
	pg_assert_contains('<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="5"', $patched['xml'], 'A before B:E');
	$patched = pg_office_xlsx_size_width(office_size_test_sheet(), 9, 8);
	pg_assert_contains('customWidth="1"/><col min="10" max="10" width="8" customWidth="1"/></cols>', $patched['xml'], 'J after B:E');

	// No list at all: one right before sheetData.
	$patched = pg_office_xlsx_size_width(office_size_test_sheet(null, ''), 1, 15);
	pg_assert_contains('/><cols><col min="2" max="2" width="15" customWidth="1"/></cols><sheetData>', $patched['xml'], 'a new list before the rows');

	// Taken off: the style stays; an entry left with nothing is taken out,
	// and a list left empty.
	$patched = pg_office_xlsx_size_width(office_size_test_sheet(), 1, null);
	pg_assert_contains('<cols><col min="2" max="2" style="1"/><col min="3" max="5" width="12" style="1" customWidth="1"/></cols>', $patched['xml'], 'B keeps its style only');
	$plain = office_size_test_sheet(null, '<cols><col min="2" max="2" width="12" customWidth="1"/></cols>');
	$patched = pg_office_xlsx_size_width($plain, 1, null);
	pg_assert_same(false, strpos($patched['xml'], '<cols'), 'the only entry gone, and its list');
	$patched = pg_office_xlsx_size_width(office_size_test_sheet(null, ''), 1, null);
	pg_assert_same(office_size_test_sheet(null, ''), $patched['xml'], 'nothing to take off: byte for byte');

	// The rows are not touched.
	pg_assert_contains(substr(office_size_test_sheet(), strpos(office_size_test_sheet(), '<sheetData>')), pg_office_xlsx_size_width(office_size_test_sheet(), 2, 20)['xml'], 'rows byte for byte');
	pg_assert_same('unusual', pg_office_xlsx_size_width('<!-- x --><worksheet><sheetData/></worksheet>', 1, 9)['error'], 'a comment in the part');
}

function test_office_size_height_sheet()
{
	$sheet = office_size_test_sheet();

	$patched = pg_office_xlsx_size_height($sheet, 1, 24.75);
	pg_assert_contains('<row r="1" ht="24.75" customHeight="1"><c r="A1"', $patched['xml'], 'a row given its height');
	$patched = pg_office_xlsx_size_height($sheet, 2, 18);
	pg_assert_contains('<row r="2" spans="1:2" ht="18" customHeight="1">', $patched['xml'], 'a height changed in place');
	$patched = pg_office_xlsx_size_height($sheet, 2, null);
	pg_assert_contains('<row r="2" spans="1:2"><c r="A2">', $patched['xml'], 'a height taken off');
	$patched = pg_office_xlsx_size_height($sheet, 3, 40);
	pg_assert_contains('</row><row r="3" ht="40" customHeight="1"/><row r="4">', $patched['xml'], 'a missing row made in its place');
	$patched = pg_office_xlsx_size_height($sheet, 9, 40);
	pg_assert_contains('</row><row r="9" ht="40" customHeight="1"/></sheetData>', $patched['xml'], 'a row after the last');
	pg_assert_same($sheet, pg_office_xlsx_size_height($sheet, 9, null)['xml'], 'no row to take a height off: byte for byte');

	$empty = office_size_test_head() . '<sheetData/></worksheet>';
	pg_assert_contains('<sheetData><row r="5" ht="20" customHeight="1"/></sheetData>', pg_office_xlsx_size_height($empty, 5, 20)['xml'], 'an empty sheet gets the row');
	pg_assert_same('layout', pg_office_xlsx_size_height(office_size_test_head() . '<sheetData><row><c><v>1</v></c></row></sheetData></worksheet>', 1, 20)['error'], 'rows without their number');
}

function test_office_size_freeze_sheet()
{
	$sheet = office_size_test_sheet();
	$on = pg_office_xlsx_size_freeze($sheet, true);
	pg_assert_contains('<sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection activeCell="C3" sqref="C3" pane="bottomLeft"/></sheetView>', $on['xml'], 'frozen under row 1, the selection in the lower pane');

	$off = pg_office_xlsx_size_freeze($on['xml'], false);
	pg_assert_contains('<sheetView tabSelected="1" workbookViewId="0"><selection activeCell="C3" sqref="C3"/></sheetView>', $off['xml'], 'unfrozen: back as it was');

	// Frozen columns before: Freeze top row takes their place.
	$columns = office_size_test_sheet('<sheetViews><sheetView workbookViewId="0" topLeftCell="C10"><pane xSplit="2" topLeftCell="C1" activePane="topRight" state="frozen"/><selection pane="topRight" activeCell="D4" sqref="D4"/></sheetView></sheetViews>');
	pg_assert_contains('<sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="D4" sqref="D4"/></sheetView>', pg_office_xlsx_size_freeze($columns, true)['xml'], 'the old pane replaced, the scroll back at the top');

	// A split that is not frozen is left by "off".
	$split = office_size_test_sheet('<sheetViews><sheetView workbookViewId="0"><pane ySplit="600" topLeftCell="A5" activePane="bottomLeft"/><selection pane="bottomLeft"/></sheetView></sheetViews>');
	pg_assert_same($split, pg_office_xlsx_size_freeze($split, false)['xml'], 'a plain split stays');
	pg_assert_same($sheet, pg_office_xlsx_size_freeze($sheet, false)['xml'], 'nothing frozen: byte for byte');

	// No views at all: one is made before the format.
	$none = office_size_test_sheet('');
	pg_assert_contains('<dimension ref="A1:B4"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr', pg_office_xlsx_size_freeze($none, true)['xml'], 'views made in their place');
	$self = office_size_test_sheet('<sheetViews><sheetView workbookViewId="0"/></sheetViews>');
	pg_assert_contains('<sheetView workbookViewId="0"><pane ySplit="1"', pg_office_xlsx_size_freeze($self, true)['xml'], 'an empty view opened');
}

function test_office_size_protection()
{
	$tag = function ($attributes) {
		return office_size_test_sheet(null, null, '<sheetProtection ' . $attributes . '/>');
	};

	pg_assert_true(pg_office_size_protected($tag('sheet="1"'), 'formatColumns'), 'protected: columns closed by default');
	pg_assert_true(!pg_office_size_protected($tag('sheet="1" formatColumns="0"'), 'formatColumns'), 'columns allowed');
	pg_assert_true(pg_office_size_protected($tag('sheet="1" formatColumns="0"'), 'formatRows'), 'rows still closed');
	pg_assert_true(!pg_office_size_protected($tag('sheet="0"'), 'formatRows'), 'protection off');
	pg_assert_true(pg_office_size_windows_locked('<workbook><workbookProtection lockWindows="1"/></workbook>'), 'windows locked');
	pg_assert_true(!pg_office_size_windows_locked('<workbook><workbookProtection lockStructure="1"/></workbook>'), 'only the structure locked');
}

function test_office_size_reader_notes()
{
	$sizes = pg_office_size_empty();
	pg_office_size_note($sizes, 'sheetFormatPr', array('defaultRowHeight' => '15', 'baseColWidth' => '10'));
	pg_office_size_note($sizes, 'col', array('min' => '2', 'max' => '3', 'width' => '12.7109375'));
	pg_office_size_note($sizes, 'col', array('min' => '3', 'max' => '3', 'style' => '1'));
	pg_office_size_note($sizes, 'col', array('min' => '1', 'max' => '16384', 'width' => '9'));
	pg_office_size_note($sizes, 'sheetView', array());
	pg_office_size_note($sizes, 'pane', array('ySplit' => '1', 'state' => 'frozen'));
	pg_office_size_note($sizes, 'sheetView', array());
	pg_office_size_note($sizes, 'pane', array('ySplit' => '3', 'state' => 'frozen'));
	$out = pg_office_size_out($sizes);

	pg_assert_same(PG_OFFICE_MAX_COLUMNS, count($out['widths']), 'a run over the sheet cut to the grid\'s columns');
	pg_assert_same(9.0, $out['widths'][1], 'a later entry wins');
	pg_assert_same(array('width' => 10.0, 'height' => 15.0, 'frozen' => 1), array('width' => $out['width'], 'height' => $out['height'], 'frozen' => $out['frozen']), 'defaults, and the first view\'s frozen row');

	$sizes = pg_office_size_empty();
	pg_office_size_note($sizes, 'sheetView', array());
	pg_office_size_note($sizes, 'pane', array('ySplit' => '600', 'state' => 'split'));
	pg_assert_same(0, pg_office_size_out($sizes)['frozen'], 'a split is not frozen');
}

function test_office_size_save_and_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_size_test_parts();
	$changes = array(
		array('op' => 'width', 'sheet' => 0, 'name' => 'Liste', 'column' => 'C', 'width' => 20),
		array('op' => 'height', 'sheet' => 0, 'name' => 'Liste', 'row' => 1, 'height' => 25.5),
		array('op' => 'freeze', 'sheet' => 0, 'name' => 'Liste', 'on' => true),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B1', 'value' => 'x'),
	);
	$path = office_size_test_save($parts, $changes, $saved);
	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);

	$book = pg_office_read($path, 'liste.xlsx', true);
	pg_assert_true($book['ok'], 'read back: ' . $book['error']);
	$sheet = $book['sheets'][0];
	pg_assert_same(array(1 => 12.0, 2 => 20.0, 3 => 12.0, 4 => 12.0), $sheet['sizes']['widths'], 'the widths');
	pg_assert_same(1, $sheet['sizes']['frozen'], 'the top row frozen');
	pg_assert_same(15.0, $sheet['sizes']['height'], 'the default row height');
	pg_assert_same(25.5, $sheet['rows'][0]['h'], 'row 1\'s height');
	pg_assert_same(30.0, $sheet['rows'][1]['h'], 'row 2 keeps its own');
	pg_assert_true(!isset($sheet['rows'][2]['h']), 'row 4 has none');
	pg_assert_same('x', $sheet['rows'][0]['cells'][1], 'the typed cell after them');
	pg_assert_same(0, $book['sheets'][1]['sizes']['frozen'], 'the other sheet untouched');

	$zip = new ZipArchive();
	$zip->open($path);

	foreach (array('xl/styles.xml', 'xl/_rels/workbook.xml.rels', '[Content_Types].xml') as $name) {
		pg_assert_same($parts[$name], $zip->getFromName($name), $name . ' is carried across untouched');
	}

	pg_assert_same(count($parts), $zip->numFiles, 'no part added or lost');
	$zip->close();
	unlink($path);

	// Only a size (the typed cell above asked a recalculation): the formula
	// results and every other part stay.
	$path = office_size_test_save($parts, array($changes[0]), $saved);
	pg_assert_true($saved['ok'], 'one width saved: ' . $saved['error']);
	pg_assert_contains('<row r="4"><c r="A4"><f>A2*2</f><v>10</v></c></row>', (string) office_size_test_part($path, 'xl/worksheets/sheet1.xml'), 'formula result kept');
	foreach (array('xl/workbook.xml', 'xl/worksheets/sheet2.xml', 'xl/styles.xml') as $name) {
		pg_assert_same($parts[$name], office_size_test_part($path, $name), $name . ' untouched: no full calculation asked');
	}
	unlink($path);
	@array_map('unlink', (array) glob(sys_get_temp_dir() . '/office_edit_*.part'));
}

function test_office_size_save_refusals()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$width = array('op' => 'width', 'sheet' => 0, 'name' => 'Liste', 'column' => 'C', 'width' => 20);
	$height = array('op' => 'height', 'sheet' => 0, 'name' => 'Liste', 'row' => 1, 'height' => 20);
	$freeze = array('op' => 'freeze', 'sheet' => 0, 'name' => 'Liste', 'on' => true);
	$protected = office_size_test_parts(office_size_test_sheet(null, null, '<sheetProtection sheet="1" formatColumns="0"/>'));
	$signed = office_size_test_parts();
	$signed['_xmlsignatures/sig1.xml'] = '<Signature/>';
	$windows = office_size_test_parts(null, '<workbookProtection lockWindows="1"/>');

	$cases = array(
		array($protected, $height, 'size_rows', 'a protected sheet closed to row heights'),
		array(office_size_test_parts(office_size_test_sheet(null, null, '<sheetProtection sheet="1"/>')), $width, 'size_columns', 'a protected sheet closed to column widths'),
		array($windows, $freeze, 'size_windows', 'the workbook\'s windows locked'),
		array($signed, $width, 'signed', 'a signed file'),
		array(office_size_test_parts(), array('op' => 'width', 'sheet' => 0, 'name' => 'Başka', 'column' => 'C', 'width' => 20), 'changed', 'a sheet renamed since'),
	);

	foreach ($cases as $case) {
		$path = office_size_test_save($case[0], array($case[1]), $saved);
		pg_assert_same($case[2], $saved['code'], $case[3]);
		pg_assert_true($saved['error'] !== '', $case[3] . ': with its message');
		pg_assert_same($case[0]['xl/worksheets/sheet1.xml'], office_size_test_part($path, 'xl/worksheets/sheet1.xml'), $case[3] . ': the file stays as it was');
		unlink($path);
	}

	// What the protection allows goes ahead: a width with formatColumns="0",
	// a frozen row on a protected sheet.
	foreach (array($width, $freeze) as $change) {
		$path = office_size_test_save($protected, array($change), $saved);
		pg_assert_true($saved['ok'], $change['op'] . ' on a protected sheet that allows it: ' . $saved['error']);
		unlink($path);
	}

	// A refusal later in a list names its place.
	$path = office_size_test_save($protected, array($width, $height), $saved);
	pg_assert_same(1, $saved['at'], 'the second change refused');
	pg_assert_contains('Change 2 of 2', $saved['error'], 'its place in the message');
	unlink($path);
	@array_map('unlink', (array) glob(sys_get_temp_dir() . '/office_edit_*.part'));
}
