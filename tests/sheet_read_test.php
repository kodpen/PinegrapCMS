<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the importers' sheet reader (includes/fn/sheet_read.php): the same
 * fixture files read with the bundled PHPExcel, as the product and bank
 * statement importers read them today, and with the new reader, compared
 * cell by cell; then the reader's own rules: typed cells, the product
 * importer's text, no preview limits on a .xlsx, the .ods ceilings and a
 * damaged .xls refused without hanging.
 *
 * The fixtures (tests/fixtures/sheet_read/) were written by LibreOffice
 * 24.2 from one openpyxl workbook (book.*: text, numbers, booleans, dates,
 * a time, a formula of each result kind, empty and sparse rows, merged
 * cells, a second sheet), by PHPExcel's Excel5 writer from that workbook
 * (book_phpexcel.xls: BOOLERR booleans and UTF-16 number formats), and by
 * LibreOffice from a workbook counting its days from 1904 (mac1904.*).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// -- helpers ---------------------------------------------------------------

/**
 * Every row of a sheet as the new reader gives it, and the error.
 */
function sheet_read_test_all($path, $kind, $sheet = 0)
{
	$error = 'not run';
	$rows = array();

	foreach (pg_sheet_rows($path, $kind, $error, $sheet) as $number => $row) {
		$rows[$number] = $row;
	}

	return array($rows, $error);
}

/**
 * A sheet as PHPExcel reads it, in its own PHP process (it replaces the zip
 * stream wrapper for the rest of the request): row => column => [type,
 * value], type s, n, b or d (a date by its cell format, as a Unix time).
 */
function sheet_read_test_phpexcel($path, $sheet)
{
	$library = PG_FUNCTIONS_DIR . '/includes/phpexcel';
	$readers = array('xlsx' => 'Excel2007', 'ods' => 'OOCalc', 'xls' => 'Excel5');
	$script = tempnam(sys_get_temp_dir(), 'pg_sheet_read');
	$code = '<?php
error_reporting(0);
require ' . var_export($library . '/PHPExcel.php', true) . ';
$reader = PHPExcel_IOFactory::createReader(' . var_export($readers[pg_sheet_kind($path)], true) . ');
$book = $reader->load(' . var_export($path, true) . ');
$sheet = $book->getSheet(' . (int) $sheet . ');
$last_row = $sheet->getHighestDataRow();
$last_column = PHPExcel_Cell::columnIndexFromString($sheet->getHighestDataColumn()) - 1;
$out = array();
for ($row = 1; $row <= $last_row; $row++) {
	for ($column = 0; $column <= $last_column; $column++) {
		$cell = $sheet->getCellByColumnAndRow($column, $row, false);
		if ($cell === null) {
			continue;
		}
		try {
			$value = $cell->getCalculatedValue();
		} catch (Exception $exception) {
			$value = $cell->getValue();
		}
		if ($value instanceof PHPExcel_RichText) {
			$value = $value->getPlainText();
		}
		if (($value === null) || ($value === "")) {
			continue;
		}
		if (is_bool($value)) {
			$entry = array("b", $value ? "1" : "0");
		} elseif ((is_int($value) || is_float($value)) && PHPExcel_Shared_Date::isDateTime($cell)) {
			$entry = array("d", PHPExcel_Shared_Date::ExcelToPHP((float) $value));
		} elseif (is_int($value) || is_float($value)) {
			$entry = array("n", (float) $value);
		} else {
			$entry = array("s", (string) $value);
		}
		$out[$row][$column] = $entry;
	}
}
echo serialize($out);
';
	file_put_contents($script, $code);
	$output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>/dev/null');
	unlink($script);
	$read = @unserialize($output);

	return is_array($read) ? $read : null;
}

/**
 * Writes a zip of the given parts and returns its path.
 */
function sheet_read_test_zip($parts, $extension)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_sheet_read') . '.' . $extension;
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

	foreach ($parts as $name => $content) {
		$zip->addFromString($name, $content);
	}

	$zip->close();

	return $path;
}

/**
 * A .xlsx of one sheet, its rows' XML given, with a shared string table.
 */
function sheet_read_test_xlsx($sheet_data, $strings = array())
{
	$string_xml = '';

	foreach ($strings as $string) {
		$string_xml .= '<si><t>' . htmlspecialchars($string, ENT_XML1) . '</t></si>';
	}

	return sheet_read_test_zip(array(
		'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="S" sheetId="1" r:id="rId1"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
		'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheet_data . '</sheetData></worksheet>',
		'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $string_xml . '</sst>',
	), 'xlsx');
}

/**
 * An .ods whose first sheet holds the given rows' XML (and a second sheet).
 */
function sheet_read_test_ods($rows_xml)
{
	$content = '<?xml version="1.0" encoding="UTF-8"?><office:document-content'
		. ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
		. ' xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"'
		. ' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"'
		. ' xmlns:dc="http://purl.org/dc/elements/1.1/" office:version="1.2">'
		. '<office:body><office:spreadsheet>'
		. '<table:table table:name="One"><table:table-column table:number-columns-repeated="3"/>' . $rows_xml . '</table:table>'
		. '<table:table table:name="Two"><table:table-row><table:table-cell office:value-type="string"><text:p>two</text:p></table:table-cell></table:table-row></table:table>'
		. '</office:spreadsheet></office:body></office:document-content>';

	return sheet_read_test_zip(array(
		'mimetype' => 'application/vnd.oasis.opendocument.spreadsheet',
		'content.xml' => $content,
		'meta.xml' => '<?xml version="1.0" encoding="UTF-8"?><office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:meta/></office:document-meta>',
	), 'ods');
}

/**
 * A row's cells as [type, value] pairs, gaps as null.
 */
function sheet_read_test_pairs($row)
{
	$out = array();

	foreach ($row as $column => $cell) {
		$out[$column] = ($cell === null) ? null : array($cell['t'], $cell['v']);
	}

	return $out;
}

// -- the same files, PHPExcel and the new reader ---------------------------

// The cells PHPExcel reads wrong, and the new reader right: an .ods
// paragraph's text:s spaces are dropped by PHPExcel (it reads only the
// paragraph's own text nodes).
function sheet_read_test_known_differences()
{
	return array(
		'book.ods#0!8:4' => array(array('s', 'spaced '), array('s', '  spaced  ')),
	);
}

function test_sheet_read_matches_phpexcel_cell_by_cell()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$directory = dirname(__FILE__) . '/fixtures/sheet_read';
	$files = array(
		'book.xlsx' => 2, 'book.ods' => 2, 'book.xls' => 2, 'book_phpexcel.xls' => 2,
		'mac1904.xlsx' => 1, 'mac1904.xls' => 1,
	);

	// The search index's fixture, written by PHPExcel's Excel5 writer.
	if (is_file(dirname(__FILE__) . '/fixtures/excel_reader2/products.xls')) {
		$files['../excel_reader2/products.xls'] = 2;
	}

	$known = sheet_read_test_known_differences();
	$compared = 0;

	foreach ($files as $file => $sheets) {
		$path = $directory . '/' . $file;

		for ($sheet = 0; $sheet < $sheets; $sheet++) {
			$label = basename($file) . '#' . $sheet;
			$expected = sheet_read_test_phpexcel($path, $sheet);

			if (!pg_assert_true(is_array($expected) && !empty($expected), $label . ' read by PHPExcel')) {
				continue;
			}

			list($rows, $error) = sheet_read_test_all($path, pg_sheet_kind($path), $sheet);
			pg_assert_same('', $error, $label . ' error');
			$actual = array();

			foreach ($rows as $number => $row) {
				foreach ($row as $column => $cell) {
					if (($cell !== null) && !(($cell['t'] === 's') && ($cell['v'] === ''))) {
						$actual[$number][$column] = $cell;
					}
				}
			}

			pg_assert_same(array_keys($expected), array_keys($actual), $label . ' rows');

			foreach ($expected as $number => $columns) {
				pg_assert_same(array_keys($columns), isset($actual[$number]) ? array_keys($actual[$number]) : array(), $label . ' row ' . $number . ' columns');

				foreach ($columns as $column => $want) {
					$where = $label . '!' . $number . ':' . $column;
					$cell = isset($actual[$number][$column]) ? $actual[$number][$column] : null;

					if ($cell === null) {
						continue;
					}

					$compared++;

					if (isset($known[$where])) {
						pg_assert_same($known[$where][0], $want, $where . ' PHPExcel');
						pg_assert_same($known[$where][1], array($cell['t'], $cell['v']), $where . ' reader');
						continue;
					}

					switch ($want[0]) {
						case 'n':
							pg_assert_same('n', $cell['t'], $where . ' type');
							pg_assert_true(abs((float) $cell['v'] - $want[1]) <= 1e-9 * max(1, abs($want[1])), $where . ' ' . $cell['v'] . ' is ' . $want[1]);
							break;

						case 'd':
							pg_assert_same('d', $cell['t'], $where . ' type');
							$stamp = (int) round(((float) $cell['v'] - 25569) * 86400);

							// A time alone: PHPExcel gave it today's date (or
							// 1 January 1970 from an .ods); the reader keeps it
							// a fraction of a day. The time of day is the same.
							if (((float) $cell['v'] < 1) && ((float) $cell['v'] >= 0)) {
								pg_assert_same((($want[1] % 86400) + 86400) % 86400, (($stamp % 86400) + 86400) % 86400, $where . ' time');
							} else {
								pg_assert_same($want[1], $stamp, $where . ' date');
							}
							break;

						case 'b':
							pg_assert_same(array('b', $want[1]), array($cell['t'], $cell['v']), $where);
							break;

						default:
							// An error result is text to PHPExcel.
							pg_assert_same($want[1], $cell['v'], $where);
							pg_assert_true(in_array($cell['t'], array('s', 'e'), true), $where . ' type');
					}
				}
			}
		}
	}

	pg_assert_true($compared > 150, 'cells compared: ' . $compared);
}

// -- typed cells and the importer's text -----------------------------------

function test_sheet_read_typed_cells_of_each_format()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$directory = dirname(__FILE__) . '/fixtures/sheet_read';

	foreach (array('book.xlsx', 'book.ods', 'book.xls', 'book_phpexcel.xls') as $file) {
		list($rows, $error) = sheet_read_test_all($directory . '/' . $file, pg_sheet_kind($file));
		pg_assert_same('', $error, $file);

		// Row 3 is empty, so it is not given; row 6 holds only column G.
		pg_assert_same(array(1, 2, 4, 5, 6, 7, 8, 9, 10, 11), array_keys($rows), $file . ' rows');
		pg_assert_same(array(null, null, null, null, null, null, array('s', 'far')), sheet_read_test_pairs($rows[6]), $file . ' sparse row');

		$texts = array();

		foreach (array($rows[2], $rows[4], $rows[8], $rows[10]) as $row) {
			$texts[] = implode('|', array_map('pg_sheet_cell_text', $row));
		}

		pg_assert_same(array(
			'Çay ğüş|12.5|1|2026-11-17|2026-11-17 10:30:00|3',
			'Kahve|0|0|2024-02-29||-7|25',
			'AB|1|1899-12-30 12:30:00|#DIV/0!|  spaced  ',
		), array_slice($texts, 0, 3), $file . ' text');

		pg_assert_same(array('b', '1'), array($rows[2][2]['t'], $rows[2][2]['v']), $file . ' boolean');
		pg_assert_same('e', $rows[8][3]['t'], $file . ' error');
		pg_assert_same(-0.01, (float) $rows[10][0]['v'], $file . ' minus a cent');
		pg_assert_same(-1234.5, (float) $rows[10][1]['v'], $file . ' a negative amount');
		pg_assert_same('2026-11-17 10:30', pg_sheet_cell_text($rows[2][4], 'Y-m-d H:i'), $file . ' bank time format');
		pg_assert_same("line1\nline2", $rows[9][0]['v'], $file . ' two paragraphs');

		// The merged title stands in its first cell only.
		pg_assert_same(array('s', 'Merged title'), array($rows[5][0]['t'], $rows[5][0]['v']), $file . ' merged');
		pg_assert_same(null, $rows[5][1], $file . ' merged covered');

		list($second, $error) = sheet_read_test_all($directory . '/' . $file, pg_sheet_kind($file), 1);
		pg_assert_same('', $error, $file . ' second sheet');
		pg_assert_same(array(1 => array(array('s', 'second'), array('n', '42')), 3 => array(array('b', '1'))), array_map('sheet_read_test_pairs', $second), $file . ' second sheet');

		list($none, $error) = sheet_read_test_all($directory . '/' . $file, pg_sheet_kind($file), 2);
		pg_assert_same(array('sheet', array()), array($error, $none), $file . ' no third sheet');
	}

	// A workbook counting from 1904 gives the same dates.
	foreach (array('mac1904.xlsx', 'mac1904.xls') as $file) {
		list($rows, $error) = sheet_read_test_all($directory . '/' . $file, pg_sheet_kind($file));
		pg_assert_same(array('2026-11-17', '2020-02-29 23:59:00', '7'), array_map('pg_sheet_cell_text', $rows[1]), $file);
	}
}

function test_sheet_read_cell_text_and_dates()
{
	pg_assert_same('', pg_sheet_cell_text(null));
	pg_assert_same('1', pg_sheet_cell_text(array('t' => 'b', 'v' => '1')));
	pg_assert_same('0', pg_sheet_cell_text(array('t' => 'b', 'v' => '0')));
	pg_assert_same('1.5', pg_sheet_cell_text(array('t' => 'n', 'v' => '1.5')));
	pg_assert_same('2026-11-17', pg_sheet_cell_text(array('t' => 'd', 'v' => '46343')));
	pg_assert_same('2026-11-17 06:00:00', pg_sheet_cell_text(array('t' => 'd', 'v' => '46343.25')));
	pg_assert_same('2026-11-17 06:00', pg_sheet_date_text('46343.25', 'Y-m-d H:i'));

	pg_assert_same('46343', pg_sheet_iso_serial('2026-11-17'));
	pg_assert_same('46343.4375', pg_sheet_iso_serial('2026-11-17T10:30:00'));
	pg_assert_same(null, pg_sheet_iso_serial('17.11.2026'));
	pg_assert_same('0.5', pg_sheet_ods_time_serial('PT12H00M00S'));
	pg_assert_same('1.5', pg_sheet_ods_time_serial('P1DT12H'));
	pg_assert_same('-0.25', pg_sheet_ods_time_serial('-PT6H'));
	pg_assert_same(null, pg_sheet_ods_time_serial('12:00'));
	pg_assert_same(null, pg_sheet_ods_time_serial('P'));

	pg_assert_same('0.1', pg_sheet_float_text(0.1));
	pg_assert_same('0.30000000000000004', pg_sheet_float_text(0.1 + 0.2));

	pg_assert_same('xlsx', pg_sheet_kind('Ürünler.XLSM'));
	pg_assert_same('ods', pg_sheet_kind('a.ods'));
	pg_assert_same('xls', pg_sheet_kind('a.xls'));
	pg_assert_same('', pg_sheet_kind('a.csv'));
}

function test_sheet_read_unknown_or_unreadable_files()
{
	list($rows, $error) = sheet_read_test_all(__FILE__, 'csv');
	pg_assert_same(array('read', array()), array($error, $rows), 'unknown kind');

	foreach (array('xlsx', 'ods', 'xls') as $kind) {
		list($rows, $error) = sheet_read_test_all(__FILE__, $kind);
		pg_assert_same(array('read', array()), array($error, $rows), 'not a workbook: ' . $kind);
		list($rows, $error) = sheet_read_test_all('/nonexistent/file.' . $kind, $kind);
		pg_assert_same(array('read', array()), array($error, $rows), 'missing: ' . $kind);
	}
}

// -- .xlsx: no preview limits ------------------------------------------------

function test_sheet_read_xlsx_has_no_preview_limits()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	// More rows than the preview shows, a cell past its column limit,
	// cells without references, an inline string in runs, a strict date.
	$data = '';

	for ($row = 1; $row <= PG_OFFICE_MAX_ROWS + 500; $row++) {
		$data .= '<row r="' . $row . '"><c r="A' . $row . '"><v>' . $row . '</v></c></row>';
	}

	$data .= '<row><c t="s"><v>0</v></c><c t="inlineStr"><is><r><t>a</t></r><r><t>b</t></r></is></c><c t="d"><v>2026-11-17T10:30:00</v></c><c r="KN' . (PG_OFFICE_MAX_ROWS + 501) . '" t="b"><v>1</v></c></row>';
	$path = sheet_read_test_xlsx($data, array('first'));
	list($rows, $error) = sheet_read_test_all($path, 'xlsx');
	unlink($path);

	pg_assert_same('', $error);
	pg_assert_same(PG_OFFICE_MAX_ROWS + 501, count($rows), 'every row');
	$last = $rows[PG_OFFICE_MAX_ROWS + 501];
	pg_assert_same(300, count($last), 'past the preview column limit');
	pg_assert_same(array(array('s', 'first'), array('s', 'ab'), array('d', '46343.4375')), array_slice(sheet_read_test_pairs($last), 0, 3));
	pg_assert_same(array('b', '1'), array($last[299]['t'], $last[299]['v']));

	// A reference past column ZZZ is a damaged file, not a row to pad out.
	$path = sheet_read_test_xlsx('<row r="1"><c r="A1"><v>1</v></c></row><row r="2"><c r="ZZZZ2"><v>1</v></c></row>');
	list($rows, $error) = sheet_read_test_all($path, 'xlsx');
	unlink($path);
	pg_assert_same('read', $error, 'past ZZZ');
}

function test_sheet_read_xlsx_strings_without_the_preview_limit()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$strings = array();

	for ($index = 0; $index <= PG_OFFICE_MAX_STRINGS; $index++) {
		$strings[] = 's';
	}

	$path = sheet_read_test_xlsx('<row r="1"><c r="A1" t="s"><v>' . PG_OFFICE_MAX_STRINGS . '</v></c></row>', $strings);

	// The preview keeps its limit; the importers' read has none.
	pg_assert_same(null, pg_office_xlsx_strings($path, 'xl/sharedStrings.xml'), 'preview limit');
	pg_assert_same(PG_OFFICE_MAX_STRINGS + 1, count((array) pg_office_xlsx_strings($path, 'xl/sharedStrings.xml', false)), 'no limit');

	list($rows, $error) = sheet_read_test_all($path, 'xlsx');
	unlink($path);
	pg_assert_same(array('', array(1 => array(array('s', 's')))), array($error, array_map('sheet_read_test_pairs', $rows)));
}

function test_sheet_read_rows_come_one_at_a_time()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$path = sheet_read_test_xlsx('<row r="1"><c r="A1"><v>1</v></c></row><row r="2"><c r="A2"><v>2</v></c></row>');
	$error = 'not run';
	$rows = pg_sheet_rows($path, 'xlsx', $error);

	pg_assert_true($rows instanceof Generator);
	pg_assert_same('not run', $error, 'nothing read before the first row is asked for');
	pg_assert_same(1, $rows->key());
	pg_assert_same('1', $rows->current()[0]['v']);
	$rows->next();
	pg_assert_same(2, $rows->key());
	$rows->next();
	pg_assert_false($rows->valid());
	pg_assert_same('', $error);
	unlink($path);
}

// -- .ods: text, repeats and the ceilings ----------------------------------

function test_sheet_read_ods_text_and_repeats()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$rows = '<table:table-header-rows><table:table-row>'
		. '<table:table-cell office:value-type="string"><text:p>a<text:s text:c="3"/>b<text:tab/>c<text:line-break/>d <text:span>e</text:span> <text:a>link</text:a></text:p>'
		. '<office:annotation><dc:creator>x</dc:creator><text:p>comment</text:p></office:annotation></table:table-cell>'
		. '<table:table-cell office:value-type="float" office:value="2"/>'
		. '<table:table-cell office:value-type="boolean" office:boolean-value="true"><text:p>DOĞRU</text:p></table:table-cell>'
		. '</table:table-row></table:table-header-rows>'
		. '<table:table-row table:number-rows-repeated="100000"/>'
		. '<table:table-row table:number-rows-repeated="2">'
		. '<table:table-cell table:number-columns-repeated="1073741824"/>'
		. '</table:table-row>'
		. '<table:table-row table:number-rows-repeated="2"><table:table-cell table:number-columns-repeated="2"/>'
		. '<table:table-cell office:value-type="currency" office:currency="TRY" office:value="-0.01" table:number-columns-repeated="2"><text:p>-0,01 ₺</text:p></table:table-cell>'
		. '<table:covered-table-cell/><table:table-cell office:value-type="date" office:date-value="2026-11-17T10:30:00"><text:p>x</text:p></table:table-cell>'
		. '</table:table-row>';
	$path = sheet_read_test_ods($rows);
	list($read, $error) = sheet_read_test_all($path, 'ods');
	list($second, $second_error) = sheet_read_test_all($path, 'ods', 1);
	list($none, $none_error) = sheet_read_test_all($path, 'ods', 2);
	unlink($path);

	pg_assert_same('', $error);
	pg_assert_same(array(1, 100004, 100005), array_keys($read), 'empty runs stepped over');
	pg_assert_same(array(array('s', "a   b\tc\nd e link"), array('n', '2'), array('b', '1')), sheet_read_test_pairs($read[1]), 'text, a value without paragraphs, a boolean by its value');
	pg_assert_same(array(null, null, array('n', '-0.01'), array('n', '-0.01'), null, array('d', '46343.4375')), sheet_read_test_pairs($read[100004]), 'repeats');
	pg_assert_same($read[100004], $read[100005], 'repeated row');
	pg_assert_same(array('', array(1 => array(array('s', 'two')))), array($second_error, array_map('sheet_read_test_pairs', $second)), 'second sheet');
	pg_assert_same('sheet', $none_error, 'no third sheet');
}

function test_sheet_read_ods_ceilings()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$value = '<table:table-cell office:value-type="float" office:value="1"><text:p>1</text:p></table:table-cell>';

	// A repeated value: one cell past the ceiling, and exactly at it.
	$side = (int) sqrt(PG_SHEET_ODS_MAX_CELLS);
	$over = '<table:table-row table:number-rows-repeated="' . ($side + 1) . '">' . str_replace('<table:table-cell ', '<table:table-cell table:number-columns-repeated="' . $side . '" ', $value) . '</table:table-row>';
	$path = sheet_read_test_ods($over);
	list($rows, $error) = sheet_read_test_all($path, 'ods');
	unlink($path);
	pg_assert_same('large', $error, 'cell ceiling');

	$at = '<table:table-row table:number-rows-repeated="' . $side . '">' . str_replace('<table:table-cell ', '<table:table-cell table:number-columns-repeated="' . $side . '" ', $value) . '</table:table-row>';
	$path = sheet_read_test_ods($at);
	list($rows, $error) = sheet_read_test_all($path, 'ods');
	unlink($path);
	pg_assert_same(array('', $side, $side), array($error, count($rows), count($rows[1])), 'at the cell ceiling');

	// One value far down and one far right: the used range is too large.
	$range = '<table:table-row>' . $value . '</table:table-row><table:table-row table:number-rows-repeated="2000"/>'
		. '<table:table-row><table:table-cell table:number-columns-repeated="600"/>' . $value . '</table:table-row>';
	$path = sheet_read_test_ods($range);
	list($rows, $error) = sheet_read_test_all($path, 'ods');
	unlink($path);
	pg_assert_same('large', $error, 'range ceiling');

	// A content.xml declaring more than the ceiling is not opened.
	$path = sheet_read_test_zip(array('content.xml' => str_repeat(' ', PG_SHEET_ODS_MAX_PART + 1)), 'ods');
	list($rows, $error) = sheet_read_test_all($path, 'ods');
	unlink($path);
	pg_assert_same('large', $error, 'part ceiling');
}

// -- .xls: damaged files ---------------------------------------------------

function test_sheet_read_xls_damaged_files_are_refused()
{
	$source = (string) file_get_contents(dirname(__FILE__) . '/fixtures/sheet_read/book.xls');
	$path = tempnam(sys_get_temp_dir(), 'pg_sheet_read') . '.xls';
	$started = microtime(true);

	// Cut short at several points, and with its block chains broken.
	foreach (array(600, 1600, 3000, 5000, strlen($source) - 600) as $length) {
		file_put_contents($path, substr($source, 0, $length));
		list($rows, $error) = sheet_read_test_all($path, 'xls');
		pg_assert_same('read', $error, 'cut at ' . $length);
	}

	$looping = $source;

	// Every chain table entry pointing at block 0: a chain that loops.
	for ($pos = 512; $pos < 1024; $pos += 4) {
		$looping = substr_replace($looping, "\0\0\0\0", $pos, 4);
	}

	file_put_contents($path, $looping);
	list($rows, $error) = sheet_read_test_all($path, 'xls');
	pg_assert_same('read', $error, 'looping chain');

	// A header claiming more chain blocks than the file holds.
	file_put_contents($path, substr_replace($source, "\xff\xff\xff\x7f", 0x2c, 4));
	list($rows, $error) = sheet_read_test_all($path, 'xls');
	pg_assert_same('read', $error, 'made-up count');

	unlink($path);
	pg_assert_true(microtime(true) - $started < 10, 'refused without hanging');
}
