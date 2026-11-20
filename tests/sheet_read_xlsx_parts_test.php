<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the importers find a .xlsx workbook's shared strings, and the
 * product importer its styles, where the workbook's relationships name
 * them, and the sheet reader its styles, as PHPExcel found them, and not
 * only at xl/sharedStrings.xml and xl/styles.xml: the sheet reader (includes/fn/sheet_read.php) and the
 * product importer's own .xlsx walk (import_products_f.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/import_products_f.php');

/**
 * A workbook whose shared strings are xl/strings/Shared.xml and styles
 * xl/Styles2.xml, both named in its relationships: a heading row and one
 * product with a date. Its path.
 */
function sheet_read_xlsx_parts_test_file()
{
	$main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$relationships = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/strings/Shared.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
			. '<Override PartName="/xl/Styles2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
		'_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="' . $relationships . '/officeDocument" Target="xl/workbook.xml"/></Relationships>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="' . $main . '" xmlns:r="' . $relationships . '">'
			. '<sheets><sheet name="Ürünler" sheetId="1" r:id="rId1"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="' . $relationships . '/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="' . $relationships . '/sharedStrings" Target="strings/Shared.xml"/>'
			. '<Relationship Id="rId3" Type="' . $relationships . '/styles" Target="/xl/Styles2.xml"/></Relationships>',
		'xl/strings/Shared.xml' => '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="' . $main . '" count="4" uniqueCount="4">'
			. '<si><t>Ürün adı</t></si><si><t>Tarih</t></si><si><t>Çay bardağı</t></si><si><t>00123</t></si></sst>',
		'xl/Styles2.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="' . $main . '">'
			. '<cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14" applyNumberFormat="1"/></cellXfs></styleSheet>',
		'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="' . $main . '"><sheetData>'
			. '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>3</v></c></row>'
			. '<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2" s="1"><v>46343</v></c><c r="C2"><v>12.5</v></c></row>'
			. '</sheetData></worksheet>',
	);

	$path = tempnam(sys_get_temp_dir(), 'pg_sheet_xlsx') . '.xlsx';
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

	foreach ($parts as $name => $content) {
		$zip->addFromString($name, $content);
	}

	$zip->close();

	return $path;
}

// A workbook from a writer that names its shared strings part otherwise:
// every text cell read empty, while PHPExcel, which follows the
// relationships, read "Ürün adı", "Çay bardağı" and "00123" from this file.
function test_sheet_read_xlsx_shared_strings_from_the_relationships()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$path = sheet_read_xlsx_parts_test_file();
	$error = 'not run';
	$rows = array();

	foreach (pg_sheet_rows($path, 'xlsx', $error) as $number => $row) {
		$rows[$number] = $row;
	}

	unlink($path);

	pg_assert_same('', $error, 'the file is read');
	pg_assert_same(array('t' => 's', 'v' => 'Ürün adı'), $rows[1][0], 'heading A1');
	pg_assert_same(array('t' => 's', 'v' => '00123'), $rows[1][2], 'a code kept as text');
	pg_assert_same(array('t' => 's', 'v' => 'Çay bardağı'), $rows[2][0], 'a product name');
	pg_assert_same(array('t' => 'n', 'v' => '12.5'), $rows[2][2], 'a price');
}

// The styles part named in the relationships (xl/Styles2.xml): the date in
// B2 read as its day count while only xl/styles.xml was looked at, and the
// bank statement import then found no dated line.
function test_sheet_read_xlsx_styles_from_the_relationships()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$path = sheet_read_xlsx_parts_test_file();
	$error = 'not run';
	$rows = array();

	foreach (pg_sheet_rows($path, 'xlsx', $error) as $number => $row) {
		$rows[$number] = $row;
	}

	$zip = new ZipArchive();
	$zip->open($path);
	$date_styles = pg_office_xlsx_date_styles($zip, $refused);
	$zip->close();
	unlink($path);

	pg_assert_same('', $error, 'the file is read');
	pg_assert_same(array('t' => 'd', 'v' => '46343'), $rows[2][1], 'a date in a style of xl/Styles2.xml');
	pg_assert_same(array(1 => 'date'), $date_styles, 'the date styles of the named part');
	pg_assert_same('', $refused, 'the styles part read whole');
}

// The product importer's own .xlsx walk: the same file gave a product with
// no name, and the date as its day count.
function test_products_import_xlsx_parts_from_the_relationships()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$path = sheet_read_xlsx_parts_test_file();
	$error = '';
	$csv = pg_products_import_to_csv($path, 'products.xlsx', $error);
	unlink($path);
	$rows = array();

	if ($csv !== '') {
		$handle = fopen($csv, 'r');

		while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
			$rows[] = $row;
		}

		fclose($handle);
		unlink($csv);
	}

	pg_assert_same('', $error, 'the file is read');
	pg_assert_same(array(array('Ürün adı', 'Tarih', '00123'), array('Çay bardağı', '2026-11-17', '12.5')), $rows, 'the CSV');
}

/**
 * A workbook kept at $workbook (book/main.xml, xl/Workbook2.xml) as the
 * package's relationships name it, counting its days from 1904, with its
 * relationships beside it and its sheet and styles named relative to its
 * folder; with $root_rels false, a package without _rels/.rels keeping it
 * at xl/workbook.xml. A heading row and a date (44881 from 1904, 2026-11-17)
 * in B2, a text-formatted C2. Its path.
 *
 * @param string $workbook
 * @param bool   $root_rels
 * @return string
 */
function sheet_read_xlsx_workbook_test_file($workbook, $root_rels = true)
{
	$main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$relationships = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$package = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$slash = strrpos($workbook, '/');
	$folder = substr($workbook, 0, $slash + 1);
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/' . $workbook . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>',
		$workbook => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="' . $main . '" xmlns:r="' . $relationships . '">'
			. '<workbookPr date1904="1"/><sheets><sheet name="Hareketler" sheetId="1" r:id="rId1"/></sheets></workbook>',
		$folder . '_rels/' . substr($workbook, $slash + 1) . '.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="' . $package . '">'
			. '<Relationship Id="rId1" Type="' . $relationships . '/worksheet" Target="sheets/one.xml"/>'
			. '<Relationship Id="rId2" Type="' . $relationships . '/styles" Target="looks.xml"/></Relationships>',
		$folder . 'looks.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="' . $main . '">'
			. '<cellXfs count="3"><xf numFmtId="0"/><xf numFmtId="14" applyNumberFormat="1"/><xf numFmtId="49" applyNumberFormat="1"/></cellXfs></styleSheet>',
		$folder . 'sheets/one.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="' . $main . '"><sheetData>'
			. '<row r="1"><c r="A1" t="inlineStr"><is><t>Tarih</t></is></c></row>'
			. '<row r="2"><c r="A2" t="inlineStr"><is><t>Kira</t></is></c><c r="B2" s="1"><v>44881</v></c><c r="C2" s="2" t="inlineStr"><is><t>00123</t></is></c></row>'
			. '</sheetData></worksheet>',
	);

	if ($root_rels) {
		$parts['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="' . $package . '">'
			. '<Relationship Id="rId1" Type="' . $relationships . '/officeDocument" Target="/' . $workbook . '"/></Relationships>';
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_sheet_book') . '.xlsx';
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

	foreach ($parts as $name => $content) {
		$zip->addFromString($name, $content);
	}

	$zip->close();

	return $path;
}

/**
 * What the reader takes from a workbook file: its sheets, its rows, the
 * part and the styles the editor sees.
 *
 * @param string $path
 * @return array
 */
function sheet_read_xlsx_workbook_test_read($path)
{
	$error = 'not run';
	$rows = array();

	foreach (pg_sheet_rows($path, 'xlsx', $error) as $number => $row) {
		$rows[$number] = $row;
	}

	$zip = new ZipArchive();
	$zip->open($path);
	$sheets = pg_office_xlsx_sheets($zip, $refused);
	$kinds = pg_office_xlsx_style_kinds($zip, $kinds_refused);
	$workbook = pg_sheet_xlsx_workbook($zip);
	$zip->close();
	unlink($path);

	return array('error' => $error, 'rows' => $rows, 'sheets' => $sheets, 'refused' => $refused . $kinds_refused, 'kinds' => $kinds, 'workbook' => $workbook);
}

// A package naming its workbook otherwise than xl/workbook.xml in
// _rels/.rels, as PHPExcel found it: it read as having no sheets ("The
// file could not be read."), its 1904 dates were four years early and its
// styles were looked for at xl/styles.xml.
function test_sheet_read_xlsx_workbook_from_the_package_relationships()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	foreach (array('book/main.xml' => 'book/', 'xl/Workbook2.xml' => 'xl/') as $workbook => $folder) {
		$read = sheet_read_xlsx_workbook_test_read(sheet_read_xlsx_workbook_test_file($workbook));

		pg_assert_same(array('part' => $workbook, 'folder' => $folder, 'rels' => $folder . '_rels/' . basename($workbook) . '.rels'), $read['workbook'], $workbook . ': the workbook part');
		pg_assert_same('', $read['error'], $workbook . ': the file is read');
		pg_assert_same('', $read['refused'], $workbook . ': nothing refused');
		pg_assert_same(1, count($read['sheets']), $workbook . ': one sheet');
		pg_assert_same(array('Hareketler', $folder . 'sheets/one.xml'), array($read['sheets'][0]['name'], $read['sheets'][0]['part']), $workbook . ': the sheet beside the workbook');
		pg_assert_same(array('t' => 's', 'v' => 'Kira'), isset($read['rows'][2][0]) ? $read['rows'][2][0] : null, $workbook . ': A2');
		pg_assert_same(array('t' => 'd', 'v' => '46343'), isset($read['rows'][2][1]) ? $read['rows'][2][1] : null, $workbook . ': a 1904 date moved to the 1900 count');
		pg_assert_same(array(1 => 'date'), $read['kinds']['date'], $workbook . ': the date styles of the named styles part');
		pg_assert_same(array(2 => true), $read['kinds']['text'], $workbook . ': the text styles of the named styles part');
	}
}

// A package without _rels/.rels keeps its workbook at xl/workbook.xml and
// reads as before.
function test_sheet_read_xlsx_workbook_without_package_relationships()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$read = sheet_read_xlsx_workbook_test_read(sheet_read_xlsx_workbook_test_file('xl/workbook.xml', false));

	pg_assert_same(array('part' => 'xl/workbook.xml', 'folder' => 'xl/', 'rels' => 'xl/_rels/workbook.xml.rels'), $read['workbook'], 'the usual workbook part');
	pg_assert_same('', $read['error'], 'the file is read');
	pg_assert_same('xl/sheets/one.xml', $read['sheets'][0]['part'], 'the sheet');
	pg_assert_same(array('t' => 'd', 'v' => '46343'), isset($read['rows'][2][1]) ? $read['rows'][2][1] : null, 'the 1904 date');
	pg_assert_same(array(2 => true), $read['kinds']['text'], 'the text styles');
}
