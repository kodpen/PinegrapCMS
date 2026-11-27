<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the format of selected cells of a workbook
 * (includes/fn/office_format.php): the request, the styles written as new
 * fonts, fills, number formats and cell formats beside the existing ones,
 * the cells of the range restyled (new cells and rows made where the range
 * has none), every refusal with its reason, and saves of whole packages read
 * back with the parts they did not change compared byte for byte.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_format_test_styles()
{
	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
		. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
		. '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
		. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
		. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
		. '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
		. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}

// A workbook of two sheets: Liste (row 2 with a value in A and a wrapped
// cell in B, no row 3, row 4 with a formula; column D styled) and Diğer.
function office_format_test_parts($sheet1 = null, $styles = null)
{
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . '" xmlns:r="' . $rns . '">';

	if ($sheet1 === null) {
		$sheet1 = $head . '<dimension ref="A1:B4"/><cols><col min="4" max="4" width="12" style="1" customWidth="1"/></cols><sheetData>'
			. '<row r="1"><c r="A1" t="inlineStr"><is><t>Ad</t></is></c></row>'
			. '<row r="2" spans="1:2"><c r="A2"><v>46343</v></c><c r="B2" s="1"><v>2</v></c></row>'
			. '<row r="4"><c r="A4"><f>A2*2</f><v>20</v></c></row>'
			. '</sheetData><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';
	}

	return array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><sheets><sheet name="Liste" sheetId="1" r:id="rId1"/><sheet name="Diğer" sheetId="2" r:id="rId2"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $rns . '/worksheet" Target="worksheets/sheet2.xml"/>'
			. '<Relationship Id="rId3" Type="' . $rns . '/styles" Target="styles.xml"/></Relationships>',
		'xl/styles.xml' => ($styles === null) ? office_format_test_styles() : $styles,
		'xl/worksheets/sheet1.xml' => $sheet1,
		'xl/worksheets/sheet2.xml' => $head . '<sheetData><row r="1"><c r="A1"><f>Liste!A2</f><v>10</v></c></row></sheetData></worksheet>',
	);
}

function office_format_test_save($parts, $change, &$saved)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_format_test_');
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

function office_format_test_part($path, $name)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = $zip->getFromName($name);
	$zip->close();

	return $xml;
}

function office_format_test_change($range, $set)
{
	return array('op' => 'format', 'sheet' => 0, 'name' => 'Liste', 'range' => $range, 'set' => $set);
}

function test_office_format_request()
{
	pg_assert_same(
		array('sheet' => 0, 'name' => 'Liste', 'range' => array(2, 0, 4, 1), 'set' => array('bold' => true, 'color' => 'FFC00000', 'align' => 'center', 'number' => 'money', 'symbol' => '₺')),
		pg_office_format_request(array('op' => 'format', 'sheet' => '0', 'name' => 'Liste', 'range' => 'b4:a2', 'set' => array('bold' => true, 'color' => '#c00000', 'align' => 'center', 'number' => 'money', 'symbol' => '₺'))),
		'a range, its corners in order, a colour with its alpha'
	);
	pg_assert_same(array('fill' => '', 'italic' => false, 'align' => ''), pg_office_format_request(office_format_test_change('C3', array('fill' => '', 'italic' => false, 'align' => '')))['set'], 'a fill, italic and alignment taken off');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array())), 'nothing to set');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('color' => 'red'))), 'a colour by name');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('align' => 'justify'))), 'an alignment not offered');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('number' => 'fraction'))), 'a number format not offered');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('bold' => 'yes'))), 'bold is true or false');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1:ZZ9', array('bold' => true))), 'past the columns the grid shows');
	pg_assert_same(null, pg_office_format_request(array('op' => 'insert', 'sheet' => 0, 'row' => 2)), 'a row is not a format');
}

function test_office_format_money_code()
{
	pg_assert_same('#,##0.00\ "₺"', pg_office_format_money_code('₺'), 'the symbol after the amount');
	pg_assert_same('#,##0.00\ "TL"', pg_office_format_money_code(' T"L '), 'quotes taken out');
	pg_assert_same('#,##0.00', pg_office_format_money_code(''), 'no symbol');
}

function test_office_format_styles()
{
	$document = new DOMDocument();
	$document->loadXML(office_format_test_styles());
	$writer = pg_office_format_styles($document);

	pg_assert_same(2, $writer['restyle'](0, array('bold' => true)), 'bold on the default style: a new cell format');
	pg_assert_same(2, $writer['restyle'](0, array('bold' => true)), 'asked again: the same one');
	pg_assert_same(0, $writer['restyle'](0, array('bold' => false, 'fill' => '')), 'taking off what is not there: the style itself');
	pg_assert_same(3, $writer['restyle'](1, array('align' => 'right', 'number' => 'percent')), 'the wrapped style aligned and as percent');
	pg_assert_same(4, $writer['restyle'](0, array('fill' => 'FFFFFF00', 'number' => 'money', 'symbol' => '₺')), 'a fill and money');
	pg_assert_same(5, $writer['restyle'](0, array('number' => 'date')), 'a date');

	$xml = $document->saveXML();
	pg_assert_contains('<fonts count="2">', $xml, 'the bold font there already is taken');
	pg_assert_contains('<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFFF00"/><bgColor indexed="64"/></patternFill></fill></fills>', $xml, 'a solid fill added after the others');
	pg_assert_contains('<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00\ &quot;₺&quot;"/></numFmts><fonts', $xml, 'the money format first in the part');
	pg_assert_contains('<cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf>', $xml, 'the existing cell formats untouched');
	pg_assert_contains('<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>', $xml, 'bold');
	pg_assert_contains('<xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1" applyNumberFormat="1"><alignment vertical="top" wrapText="1" horizontal="right"/></xf>', $xml, 'the alignment keeps its wrap');
	pg_assert_contains('<xf numFmtId="164" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1" applyNumberFormat="1"/>', $xml, 'fill and money');
	pg_assert_contains('<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>', $xml, 'a date');

	// The colour and italic go into a font of their own.
	pg_assert_same(6, $writer['restyle'](2, array('italic' => true, 'color' => 'FFC00000')), 'bold italic red');
	$xml = $document->saveXML();
	pg_assert_contains('<font><b/><i/><sz val="11"/><color rgb="FFC00000"/><name val="Calibri"/></font></fonts>', $xml, 'a new font from the bold one');
	pg_assert_same(2, $writer['restyle'](6, array('color' => '', 'italic' => false)), 'colour and italic taken off again: the bold format there already is');
	pg_assert_same(null, $writer['restyle'](40, array('bold' => true)), 'a style the part does not have');
}

function test_office_format_sheet()
{
	$parts = office_format_test_parts();
	$map = array(0 => 2, 1 => 3);
	$restyle = function ($style) use ($map) {
		return isset($map[$style]) ? $map[$style] : $style;
	};

	$out = pg_office_xlsx_format_sheet($parts['xl/worksheets/sheet1.xml'], array(2, 0, 4, 3), $restyle);
	pg_assert_true($out['ok'], 'formatted: ' . $out['error']);
	pg_assert_contains('<row r="1"><c r="A1" t="inlineStr"><is><t>Ad</t></is></c></row>', $out['xml'], 'a row outside the range carried as it was');
	pg_assert_contains('<row r="2" spans="1:4"><c r="A2" s="2"><v>46343</v></c><c r="B2" s="3"><v>2</v></c><c r="C2" s="2"/><c r="D2" s="3"/></row>', $out['xml'], 'cells restyled, new ones made from the column\'s style');
	pg_assert_contains('<row r="3"><c r="A3" s="2"/><c r="B3" s="2"/><c r="C3" s="2"/><c r="D3" s="3"/></row><row r="4">', $out['xml'], 'a row the sheet did not have, in its place');
	pg_assert_contains('<c r="A4" s="2"><f>A2*2</f><v>20</v></c>', $out['xml'], 'a formula cell keeps its formula and its result');

	$empty = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData/></worksheet>';
	$out = pg_office_xlsx_format_sheet($empty, array(1, 0, 1, 0), $restyle);
	pg_assert_same('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" s="2"/></row></sheetData></worksheet>', $out['xml'], 'an empty sheet gets its row');

	$same = pg_office_xlsx_format_sheet($empty, array(1, 0, 1, 0), function ($style) {
		return $style;
	});
	pg_assert_same($empty, $same['xml'], 'a range left at the default style makes nothing');
}

function test_office_format_save_and_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_format_test_parts();
	$path = office_format_test_save($parts, office_format_test_change('A2:B2', array('bold' => true, 'number' => 'date')), $saved);
	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(2, $saved['changed'], 'two cells');

	$book = pg_office_read($path, 'liste.xlsx', true);
	pg_assert_true($book['ok'], 'read back: ' . $book['error']);
	pg_assert_same('2026-11-17', $book['sheets'][0]['rows'][1]['cells'][0], 'the number now shows as a date');

	$zip = new ZipArchive();
	$zip->open($path);

	foreach (array('xl/workbook.xml', 'xl/worksheets/sheet2.xml', 'xl/_rels/workbook.xml.rels', '[Content_Types].xml') as $name) {
		pg_assert_same($parts[$name], $zip->getFromName($name), $name . ' is carried across untouched');
	}

	pg_assert_contains('<row r="4"><c r="A4"><f>A2*2</f><v>20</v></c></row>', (string) $zip->getFromName('xl/worksheets/sheet1.xml'), 'formula results stay: no value changed');
	pg_assert_contains('<c r="A2" s="2"><v>46343</v></c><c r="B2" s="3"><v>2</v></c>', (string) $zip->getFromName('xl/worksheets/sheet1.xml'), 'each style its own new one');
	pg_assert_same(count($parts), $zip->numFiles, 'no part added or lost');
	$zip->close();
	unlink($path);
	@array_map('unlink', (array) glob(sys_get_temp_dir() . '/office_edit_*.part'));
}

function test_office_format_save_refusals()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$bold = office_format_test_change('A1:B2', array('bold' => true));
	$protected = office_format_test_parts(str_replace('<pageMargins', '<sheetProtection sheet="1"/><pageMargins', office_format_test_parts()['xl/worksheets/sheet1.xml']));
	$cells_open = office_format_test_parts(str_replace('<pageMargins', '<sheetProtection sheet="1" formatCells="0"/><pageMargins', office_format_test_parts()['xl/worksheets/sheet1.xml']));
	$no_styles = office_format_test_parts();
	unset($no_styles['xl/styles.xml']);
	$signed = office_format_test_parts();
	$signed['_xmlsignatures/sig1.xml'] = '<Signature/>';

	$cases = array(
		array($protected, $bold, 'format_protected', 'a protected sheet'),
		array($no_styles, $bold, 'format_styles', 'no styles part'),
		array($signed, $bold, 'signed', 'a signed file'),
		array(office_format_test_parts(), office_format_test_change('A1:CV100', array('bold' => true)), 'many', 'more cells than a save takes'),
		array(office_format_test_parts(), array('op' => 'format', 'sheet' => 0, 'name' => 'Başka', 'range' => 'A1', 'set' => array('bold' => true)), 'changed', 'a sheet renamed since'),
	);

	foreach ($cases as $case) {
		$path = office_format_test_save($case[0], $case[1], $saved);
		pg_assert_same($case[2], $saved['code'], $case[3]);
		pg_assert_true($saved['error'] !== '', $case[3] . ': with its message');
		pg_assert_same($case[0]['xl/worksheets/sheet1.xml'], office_format_test_part($path, 'xl/worksheets/sheet1.xml'), $case[3] . ': the file stays as it was');
		unlink($path);
	}

	// Formatting allowed on a protected sheet (formatCells="0") goes ahead.
	$path = office_format_test_save($cells_open, $bold, $saved);
	pg_assert_true($saved['ok'], 'a protected sheet that allows formatting: ' . $saved['error']);
	unlink($path);
	@array_map('unlink', (array) glob(sys_get_temp_dir() . '/office_edit_*.part'));
}

function test_office_format_request_more()
{
	pg_assert_same(
		array('underline' => true, 'strike' => false, 'size' => 14, 'border' => 'outline', 'wrap' => true),
		pg_office_format_request(office_format_test_change('A1:C3', array('underline' => true, 'strike' => false, 'size' => 14, 'border' => 'outline', 'wrap' => true)))['set'],
		'underline, strikethrough, a size, a border and wrap'
	);

	foreach (array('none', 'all', 'outline', 'bottom') as $border) {
		pg_assert_same($border, pg_office_format_request(office_format_test_change('A1', array('border' => $border)))['set']['border'], 'the border ' . $border);
	}

	pg_assert_same(8, pg_office_format_request(office_format_test_change('A1', array('size' => 8)))['set']['size'], 'the smallest size');
	pg_assert_same(72, pg_office_format_request(office_format_test_change('A1', array('size' => 72)))['set']['size'], 'the largest size');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('size' => 7))), 'a size under 8');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('size' => 73))), 'a size over 72');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('size' => '12'))), 'a size as text');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('size' => 10.5))), 'a size between points');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('border' => 'thick'))), 'a border not offered');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('underline' => 1))), 'underline is true or false');
	pg_assert_same(null, pg_office_format_request(office_format_test_change('A1', array('wrap' => 'yes'))), 'wrap is true or false');
}

function test_office_format_border_sides()
{
	$range = array(2, 1, 4, 3);
	$all = array('left' => true, 'right' => true, 'top' => true, 'bottom' => true);
	$none = array('left' => false, 'right' => false, 'top' => false, 'bottom' => false);

	pg_assert_same($all, pg_office_format_border_sides('all', $range, 3, 2), 'all sides: every cell');
	pg_assert_same($none, pg_office_format_border_sides('none', $range, 3, 2), 'none: every side taken off');
	pg_assert_same(array('left' => true, 'top' => true), pg_office_format_border_sides('outline', $range, 2, 1), 'outline: the top left corner');
	pg_assert_same(array('right' => true, 'bottom' => true), pg_office_format_border_sides('outline', $range, 4, 3), 'outline: the bottom right corner');
	pg_assert_same(array(), pg_office_format_border_sides('outline', $range, 3, 2), 'outline: an inner cell keeps its borders');
	pg_assert_same($all, pg_office_format_border_sides('outline', array(2, 1, 2, 1), 2, 1), 'outline of one cell: all four');
	pg_assert_same(array('bottom' => true), pg_office_format_border_sides('bottom', $range, 4, 2), 'bottom: the last row');
	pg_assert_same(array(), pg_office_format_border_sides('bottom', $range, 3, 2), 'bottom: a row above it keeps its borders');
}

function test_office_format_styles_more()
{
	$document = new DOMDocument();
	$document->loadXML(office_format_test_styles());
	$writer = pg_office_format_styles($document);

	pg_assert_same(2, $writer['restyle'](0, array('underline' => true, 'strike' => true, 'size' => 14)), 'underline, strikethrough and 14 points');
	pg_assert_same(3, $writer['restyle'](0, array('wrap' => true)), 'wrap text');
	pg_assert_same(4, $writer['restyle'](1, array('wrap' => false)), 'wrap taken off the wrapped style');
	pg_assert_same(0, $writer['restyle'](0, array('wrap' => false, 'underline' => false, 'strike' => false, 'size' => 11)), 'taking off what is not there, the size it has: the style itself');
	pg_assert_same(5, $writer['restyle'](0, array('sides' => array('left' => true, 'right' => true, 'top' => true, 'bottom' => true))), 'all sides');
	pg_assert_same(5, $writer['restyle'](5, array('sides' => array('top' => true))), 'a side already there: the same style');
	pg_assert_same(6, $writer['restyle'](5, array('sides' => array('top' => false, 'left' => false))), 'two sides taken off');
	pg_assert_same(7, $writer['restyle'](5, array('sides' => array('left' => false, 'right' => false, 'top' => false, 'bottom' => false))), 'every side taken off: the empty border there already is, the style marked as applying it');
	pg_assert_same(2, $writer['restyle'](2, array('underline' => true)), 'underline on an underlined font: nothing changes');

	$xml = $document->saveXML();
	pg_assert_contains('<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><strike/><u/><sz val="14"/><name val="Calibri"/></font></fonts>', $xml, 'a new font after the others, its parts in Excel\'s order');
	pg_assert_contains('<borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border>'
		. '<border><left style="thin"><color indexed="64"/></left><right style="thin"><color indexed="64"/></right><top style="thin"><color indexed="64"/></top><bottom style="thin"><color indexed="64"/></bottom><diagonal/></border>'
		. '<border><left/><right style="thin"><color indexed="64"/></right><top/><bottom style="thin"><color indexed="64"/></bottom><diagonal/></border></borders>', $xml, 'thin borders added after the existing one, the sides in place');
	pg_assert_contains('<cellXfs count="8"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf>', $xml, 'the existing cell formats untouched');
	pg_assert_contains('<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyBorder="1"/></cellXfs>', $xml, 'no border');
	pg_assert_contains('<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>', $xml, 'the font');
	pg_assert_contains('<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1"/></xf>', $xml, 'wrapped');
	pg_assert_contains('<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top"/></xf>', $xml, 'unwrapped, its vertical alignment kept');
	pg_assert_contains('<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>', $xml, 'all sides');

	// A part without a borders list cannot take a border.
	$bare = new DOMDocument();
	$bare->loadXML(str_replace('<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>', '', office_format_test_styles()));
	$writer = pg_office_format_styles($bare);
	pg_assert_same(null, $writer['restyle'](0, array('sides' => array('top' => true))), 'no borders list');
}

function test_office_format_save_more_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$parts = office_format_test_parts();
	$path = office_format_test_save($parts, office_format_test_change('A1:B2', array('underline' => true, 'strike' => true, 'size' => 16, 'border' => 'outline', 'wrap' => true)), $saved);
	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(4, $saved['changed'], 'four cells');

	$book = pg_office_xlsx_read($path, true);
	pg_assert_true($book['ok'], 'read back: ' . $book['error']);
	$rows = $book['sheets'][0]['rows'];
	$look = function ($row, $column) use ($rows, $book) {
		$style = $rows[$row]['s'][$column];

		return $book['looks'][$style];
	};

	pg_assert_same(array('u' => 1, 'st' => 1, 'sz' => 16, 'w' => 1, 'bd' => 'lt'), $look(0, 0), 'A1: its look with the top left border');
	pg_assert_same(array('u' => 1, 'st' => 1, 'sz' => 16, 'w' => 1, 'bd' => 'rt'), $look(0, 1), 'B1: the top right border');
	pg_assert_same(array('u' => 1, 'st' => 1, 'sz' => 16, 'w' => 1, 'bd' => 'lb'), $look(1, 0), 'A2: the bottom left border');
	pg_assert_same(array('u' => 1, 'st' => 1, 'sz' => 16, 'w' => 1, 'bd' => 'rb'), $look(1, 1), 'B2: the bottom right border, wrapped already');

	$zip = new ZipArchive();
	$zip->open($path);

	foreach (array('xl/workbook.xml', 'xl/worksheets/sheet2.xml', 'xl/_rels/workbook.xml.rels', '[Content_Types].xml') as $name) {
		pg_assert_same($parts[$name], $zip->getFromName($name), $name . ' is carried across untouched');
	}

	$sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
	pg_assert_contains('<row r="4"><c r="A4"><f>A2*2</f><v>20</v></c></row></sheetData><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>', $sheet, 'the rest of the sheet byte for byte');
	pg_assert_contains('<cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf>', (string) $zip->getFromName('xl/styles.xml'), 'the existing styles untouched, one new style a corner');
	pg_assert_same(count($parts), $zip->numFiles, 'no part added or lost');
	$zip->close();
	unlink($path);
	@array_map('unlink', (array) glob(sys_get_temp_dir() . '/office_edit_*.part'));
}

function test_office_format_looks_more()
{
	$document = new DOMDocument();
	$document->loadXML('<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
		. '<fonts count="4"><font><sz val="11"/></font><font><u val="double"/><strike/><sz val="11"/></font><font><u val="none"/><strike val="0"/><sz val="9.5"/></font><font><b/></font></fonts>'
		. '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
		. '<borders count="2"><border/><border><left style="none"/><right style="medium"/><top/><bottom style="thin"/><diagonal style="thin"/></border></borders>'
		. '<cellXfs count="4"><xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"><alignment wrapText="1"/></xf>'
		. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment wrapText="0"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>');
	$looks = pg_office_xlsx_style_looks($document);

	pg_assert_same(array('u' => 1, 'st' => 1, 'bd' => 'rb'), $looks[0], 'a double underline, strikethrough and the drawn sides; a diagonal left out');
	pg_assert_same(array('sz' => 9.5, 'w' => 1), $looks[1], 'underline none, strike off, a size of its own, wrapped');
	pg_assert_false(isset($looks[2]), 'wrap off and the default size look like nothing');
	pg_assert_same(array('b' => 1), $looks[3], 'a font without a size keeps the default');
}
