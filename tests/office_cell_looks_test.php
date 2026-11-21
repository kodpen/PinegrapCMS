<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cell formats a workbook preview shows
 * (includes/fn/office.php): what each cell format of the styles part looks
 * like (bold, italic, text colour, fill, alignment, number format), a number
 * shown as its format writes it, and a workbook read with the looks of its
 * cells after a format save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_cell_looks_test_styles()
{
	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
		. '<numFmts count="4"><numFmt numFmtId="164" formatCode="#,##0.00\ &quot;₺&quot;"/><numFmt numFmtId="165" formatCode="0.0%"/>'
		. '<numFmt numFmtId="166" formatCode="dd/mm/yyyy"/><numFmt numFmtId="167" formatCode="[$€-407]#,##0"/></numFmts>'
		. '<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font>'
		. '<font><i/><color rgb="FFC00000"/><sz val="11"/></font><font><b val="0"/><color theme="1"/><sz val="11"/></font></fonts>'
		. '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
		. '<fill><patternFill patternType="solid"><fgColor rgb="FFFFFF00"/><bgColor indexed="64"/></patternFill></fill>'
		. '<fill><patternFill patternType="solid"><fgColor theme="4"/></patternFill></fill></fills>'
		. '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
		. '<cellXfs count="12">'
		. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
		. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
		. '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0"><alignment horizontal="center" vertical="top"/></xf>'
		. '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0"/>'
		. '<xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0"><alignment horizontal="right"/></xf>'
		. '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0"/>'
		. '<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0"/>'
		. '<xf numFmtId="165" fontId="3" fillId="3" borderId="0" xfId="0"><alignment horizontal="general"/></xf>'
		. '<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0"/>'
		. '<xf numFmtId="167" fontId="0" fillId="0" borderId="0" xfId="0"><alignment horizontal="left"/></xf>'
		. '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0"/>'
		. '<xf numFmtId="3" fontId="9" fillId="9" borderId="0" xfId="0"/>'
		. '</cellXfs></styleSheet>';
}

function test_office_cell_looks_from_the_styles_part()
{
	$document = new DOMDocument();
	$document->loadXML(office_cell_looks_test_styles());
	$looks = pg_office_xlsx_style_looks($document);

	pg_assert_false(isset($looks[0]), 'the default style looks like nothing');
	pg_assert_same(array('b' => 1), $looks[1], 'bold');
	pg_assert_same(array('i' => 1, 'c' => 'C00000', 'bg' => 'FFFF00', 'a' => 'center'), $looks[2], 'italic, red on yellow, centred');
	pg_assert_same(array('n' => 'number', 'd' => 2, 'g' => 1), $looks[3], 'the built-in #,##0.00');
	pg_assert_same(array('a' => 'right', 'n' => 'percent', 'd' => 2, 'g' => 0), $looks[4], 'the built-in 0.00% aligned right');
	pg_assert_same(array('n' => 'money', 'd' => 2, 'g' => 1, 'y' => '₺', 'p' => 'after'), $looks[5], 'the money format the editor writes');
	pg_assert_same(array('n' => 'date'), $looks[6], 'the built-in date');
	pg_assert_same(array('n' => 'percent', 'd' => 1, 'g' => 0), $looks[7], 'a theme colour and fill, general alignment and "not bold" draw nothing');
	pg_assert_same(array('n' => 'date'), $looks[8], 'a date of the workbook\'s own');
	pg_assert_same(array('a' => 'left', 'n' => 'money', 'd' => 0, 'g' => 1, 'y' => '€', 'p' => 'before'), $looks[9], 'a currency in brackets before the amount');
	pg_assert_same(array('n' => 'other'), $looks[10], 'text is a format the controls do not offer');
	pg_assert_same(array('n' => 'number', 'd' => 0, 'g' => 1), $looks[11], 'a font or fill that is not there is left out');
}

function test_office_cell_looks_shown_number()
{
	$tr = array('decimal' => ',', 'thousands' => '.');
	$en = array('decimal' => '.', 'thousands' => ',');

	pg_assert_same('1.234,50', pg_office_xlsx_shown_number('1234.5', array('n' => 'number', 'd' => 2, 'g' => 1), $tr), 'a number with the site\'s separators');
	pg_assert_same('1234,5', pg_office_xlsx_shown_number('1234.5', array('n' => 'number', 'd' => 1, 'g' => 0), $tr), 'no grouping');
	pg_assert_same('-1,235', pg_office_xlsx_shown_number('-1234.5', array('n' => 'number', 'd' => 0, 'g' => 1), $en), 'rounded, the sign kept');
	pg_assert_same('12,50%', pg_office_xlsx_shown_number('0.125', array('n' => 'percent', 'd' => 2, 'g' => 0), $tr), 'a percent');
	pg_assert_same('2.015,50 ₺', pg_office_xlsx_shown_number('2015.5', array('n' => 'money', 'd' => 2, 'g' => 1, 'y' => '₺', 'p' => 'after'), $tr), 'money, the symbol after');
	pg_assert_same('€1,234', pg_office_xlsx_shown_number('1234', array('n' => 'money', 'd' => 0, 'g' => 1, 'y' => '€', 'p' => 'before'), $en), 'money, the symbol before');
	pg_assert_same('', pg_office_xlsx_shown_number('1234', array('b' => 1), $en), 'no number format: as before');
	pg_assert_same('', pg_office_xlsx_shown_number('abc', array('n' => 'number', 'd' => 2, 'g' => 1), $en), 'not a number');
}

function test_office_cell_looks_in_the_read_workbook()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><sheets><sheet name="Liste" sheetId="1" r:id="rId1"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="' . $rns . '/styles" Target="styles.xml"/></Relationships>',
		'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="' . $ns . '"><fonts count="1"><font><sz val="11"/></font></fonts>'
			. '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>',
		'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="' . $ns . '"><sheetData>'
			. '<row r="1"><c r="A1" t="inlineStr"><is><t>Ad</t></is></c><c r="B1"><v>1234.5</v></c></row></sheetData></worksheet>',
	);
	$path = tempnam(sys_get_temp_dir(), 'pg_office_looks_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	$saved = pg_office_xlsx_save($path, array(array('op' => 'format', 'sheet' => 0, 'name' => 'Liste', 'range' => 'A1:B1', 'set' => array('bold' => true, 'number' => 'money', 'symbol' => '₺'))), pg_office_version($path), sys_get_temp_dir());
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');
	pg_assert_true(!empty($saved['ok']), 'the format is saved');

	$book = pg_office_xlsx_read($path, true);
	@unlink($path);
	$row = $book['sheets'][0]['rows'][0];
	$style = $row['s'][1];

	pg_assert_same(array('Ad', '1234.5'), $row['cells'], 'the values stay what the editor changes');
	pg_assert_same($style, $row['s'][0], 'both cells take the one new style');
	pg_assert_same(array('b' => 1, 'n' => 'money', 'd' => 2, 'g' => 1, 'y' => '₺', 'p' => 'after'), $book['looks'][$style], 'the look of the new style');
	pg_assert_same(array(1), array_keys($row['show']), 'only the number is shown otherwise');
	pg_assert_contains('₺', $row['show'][1], 'the number shown as money');
	pg_assert_same(array($style), array_keys($book['looks']), 'only the looks the shown cells use are sent');
}
