<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the product import's spreadsheet door (pg_products_import_to_csv(),
 * import_products_f.php) reading .ods and .xls files through the sheet
 * reader (includes/fn/sheet_read.php): the fixtures of
 * tests/fixtures/sheet_read/ give the rows PHPExcel gave the importer
 * before, cell for cell, but where the reader reads otherwise on purpose
 * (docs/memory/platform/sheet-reader-differences.md); a .xlsx keeps the
 * importer's own walk; the reader's errors keep the importer's texts.
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
 * The CSV the product importer makes of a file, as rows, or its error.
 */
function products_import_sheet_test_rows($path, $file_name)
{
	$error = '';
	$csv = pg_products_import_to_csv($path, $file_name, $error);

	if ($csv === '') {
		return $error;
	}

	$rows = array();
	$handle = fopen($csv, 'r');

	while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
		$rows[] = $row;
	}

	fclose($handle);
	unlink($csv);

	return $rows;
}

/**
 * The rows the product importer made of a file with PHPExcel before it read
 * through the sheet reader: the first sheet, values only, every row squared
 * to the sheet's last data column, empty rows dropped. Run in its own PHP
 * process, as PHPExcel replaces the zip stream wrapper and switches libxml's
 * entity loader off for the rest of a request.
 */
function products_import_sheet_test_phpexcel($path)
{
	$readers = array('xlsx' => 'Excel2007', 'ods' => 'OOCalc', 'xls' => 'Excel5');
	$script = tempnam(sys_get_temp_dir(), 'pg_products_import_sheet');
	$code = '<?php
error_reporting(0);
require ' . var_export(PG_FUNCTIONS_DIR . '/includes/phpexcel/PHPExcel.php', true) . ';
$reader = PHPExcel_IOFactory::createReader(' . var_export($readers[pg_sheet_kind($path)], true) . ');
$reader->setReadDataOnly(true);
$book = $reader->load(' . var_export($path, true) . ');
$sheet = $book->getSheet(0);
$last_row = $sheet->getHighestDataRow();
$last_column = PHPExcel_Cell::columnIndexFromString($sheet->getHighestDataColumn()) - 1;
$out = array();
for ($row_number = 1; $row_number <= $last_row; $row_number++) {
	$row = array();
	$filled = false;
	for ($column = 0; $column <= $last_column; $column++) {
		$cell = $sheet->getCellByColumnAndRow($column, $row_number, false);
		$text = "";
		if ($cell !== null) {
			try {
				$value = $cell->getCalculatedValue();
			} catch (Exception $exception) {
				$value = $cell->getValue();
			}
			if ($value === null) {
				$text = "";
			} elseif (is_bool($value)) {
				$text = $value ? "1" : "0";
			} elseif (PHPExcel_Shared_Date::isDateTime($cell)) {
				$stamp = PHPExcel_Shared_Date::ExcelToPHP((float) $value);
				$text = gmdate((((float) $value) == floor((float) $value)) ? "Y-m-d" : "Y-m-d H:i:s", $stamp);
			} else {
				$text = (string) $value;
			}
		}
		if ($text !== "") {
			$filled = true;
		}
		$row[] = $text;
	}
	if ($filled) {
		$out[] = $row;
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

// The cells the importer now reads otherwise than PHPExcel did, on purpose,
// as file!row:column => array(PHPExcel's text, the reader's text): an .ods
// number is the file's own office:value text, as a .xlsx number is its <v>
// (PHPExcel cast it to a float, so a long code became 2.5E+15); a time alone
// is a fraction of a day from 30 December 1899, as in a .xlsx; .ods text
// keeps its text:s spaces; an .xls date is a date, as in a .xlsx (PHPExcel's
// values-only read gave the day count).
function products_import_sheet_test_known_differences()
{
	$xls = array(
		'1:3' => array('46343', '2026-11-17'),
		'1:4' => array('46343.4375', '2026-11-17 10:30:00'),
		'2:3' => array('45351', '2024-02-29'),
		'6:2' => array('0.52083333333333', '1899-12-30 12:30:00'),
	);
	$known = array(
		'book.ods!5:3' => array('1.0E-7', '0.0000001'),
		'book.ods!6:2' => array('1970-01-01 12:30:00', '1899-12-30 12:30:00'),
		'book.ods!6:4' => array('spaced ', '  spaced  '),
		'book.ods!8:3' => array('2.5E+15', '2500000000000000'),
		'mac1904.xls!0:0' => array('44881', '2026-11-17'),
		'mac1904.xls!0:1' => array('42428.999305556', '2020-02-29 23:59:00'),
	);

	foreach (array('book.xls', 'book_phpexcel.xls') as $file) {
		foreach ($xls as $where => $pair) {
			$known[$file . '!' . $where] = $pair;
		}
	}

	return $known;
}

function test_products_import_sheet_matches_phpexcel()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$directory = dirname(__FILE__) . '/fixtures/sheet_read';
	$files = array('book.ods', 'book.xls', 'book_phpexcel.xls', 'mac1904.xls');
	$known = products_import_sheet_test_known_differences();
	$compared = 0;

	foreach ($files as $file) {
		$path = $directory . '/' . $file;
		$expected = products_import_sheet_test_phpexcel($path);

		if (!pg_assert_true(is_array($expected) && !empty($expected), $file . ' read by PHPExcel')) {
			continue;
		}

		$actual = products_import_sheet_test_rows($path, $file);

		if (!pg_assert_true(is_array($actual), $file . ' read: ' . (is_string($actual) ? $actual : ''))) {
			continue;
		}

		pg_assert_same(count($expected), count($actual), $file . ' rows');

		foreach ($expected as $number => $row) {
			$got = isset($actual[$number]) ? $actual[$number] : array();
			pg_assert_same(count($row), count($got), $file . ' row ' . $number . ' width');

			foreach ($row as $column => $want) {
				$where = $file . '!' . $number . ':' . $column;
				$have = isset($got[$column]) ? $got[$column] : null;
				$compared++;

				if (isset($known[$where])) {
					pg_assert_same($known[$where], array($want, $have), $where);
					continue;
				}

				pg_assert_same($want, $have, $where);
			}
		}
	}

	pg_assert_true($compared > 100, 'cells compared: ' . $compared);
}

// The same workbook saved as .xlsx, .ods and .xls gives the importer the same
// rows: the .xlsx through the importer's own walk, the others through the
// sheet reader.
function test_products_import_sheet_formats_agree()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$directory = dirname(__FILE__) . '/fixtures/sheet_read';
	$xlsx = products_import_sheet_test_rows($directory . '/book.xlsx', 'Book.XLSX');

	if (!pg_assert_true(is_array($xlsx), 'book.xlsx read')) {
		return;
	}

	pg_assert_same(array('Name', 'Price', 'Active', 'Date', 'Stamp', 'Qty', 'Note'), $xlsx[0], 'heading');
	pg_assert_same(array('Çay ğüş', '12.5', '1', '2026-11-17', '2026-11-17 10:30:00', '3', ''), $xlsx[1], 'first product');

	foreach (array('book.ods', 'book.xls') as $file) {
		$rows = products_import_sheet_test_rows($directory . '/' . $file, $file);

		if (!pg_assert_true(is_array($rows), $file . ' read')) {
			continue;
		}

		pg_assert_same(count($xlsx), count($rows), $file . ' rows');

		foreach ($xlsx as $number => $row) {
			// A formula's text in .xls is the float PHP makes of the stored
			// result; the cells it differs in are numbers of the same value.
			foreach ($row as $column => $value) {
				$have = isset($rows[$number][$column]) ? $rows[$number][$column] : null;

				if (is_numeric($value) && is_numeric($have)) {
					pg_assert_true(abs((float) $value - (float) $have) <= 1e-9 * max(1, abs((float) $value)), $file . '!' . $number . ':' . $column . ' ' . $have . ' is ' . $value);
				} else {
					pg_assert_same($value, $have, $file . '!' . $number . ':' . $column);
				}
			}
		}
	}
}

// The reader's errors keep the importer's texts, and nothing read before an
// error is imported.
function test_products_import_sheet_refusals_keep_their_texts()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$dir = sys_get_temp_dir() . '/pg_products_import_sheet_' . bin2hex(random_bytes(4));
	mkdir($dir);

	// Not a workbook at all.
	file_put_contents($dir . '/not.xls', 'not a workbook');
	pg_assert_same(lang('The file could not be read.'), products_import_sheet_test_rows($dir . '/not.xls', 'not.xls'), '.xls not a workbook');
	file_put_contents($dir . '/not.ods', 'not a workbook');
	pg_assert_same(lang('The file could not be read.'), products_import_sheet_test_rows($dir . '/not.ods', 'not.ods'), '.ods not a workbook');

	// An extension no reader takes.
	pg_assert_same(lang('The file was empty.'), products_import_sheet_test_rows($dir . '/not.xls', 'products.numbers'), 'unknown extension');

	$cell = '<table:table-cell office:value-type="string"><text:p>x</text:p></table:table-cell>';
	$ods = function ($name, $tables) use ($dir) {
		$path = $dir . '/' . $name;
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		$zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
		$zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-content'
			. ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
			. ' xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"'
			. ' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:version="1.2">'
			. '<office:body><office:spreadsheet>' . $tables . '</office:spreadsheet></office:body></office:document-content>');
		$zip->close();

		return $path;
	};

	// Rows given before the ceiling is passed are dropped: the file is
	// refused whole.
	$large = $ods('large.ods', '<table:table table:name="S"><table:table-row>' . $cell . '</table:table-row>'
		. '<table:table-row table:number-rows-repeated="' . (PG_SHEET_ODS_MAX_CELLS + 1) . '">' . $cell . '</table:table-row></table:table>');
	pg_assert_same(lang('The file is too large.'), products_import_sheet_test_rows($large, 'large.ods'), '.ods past the cell ceiling');

	// A workbook without a sheet is no workbook.
	$none = $ods('none.ods', '');
	pg_assert_same(lang('The file could not be read.'), products_import_sheet_test_rows($none, 'none.ods'), 'no sheet');

	// A sheet of empty rows.
	$empty = $ods('empty.ods', '<table:table table:name="S"><table:table-row><table:table-cell/></table:table-row></table:table>');
	pg_assert_same(lang('The file was empty.'), products_import_sheet_test_rows($empty, 'empty.ods'), 'only empty rows');

	// A cut .xls is refused, not imported in part.
	$whole = (string) file_get_contents(dirname(__FILE__) . '/fixtures/sheet_read/book.xls');
	file_put_contents($dir . '/cut.xls', substr($whole, 0, (int) (strlen($whole) / 2)));
	pg_assert_same(lang('The file could not be read.'), products_import_sheet_test_rows($dir . '/cut.xls', 'cut.xls'), 'cut .xls');

	foreach (glob($dir . '/*') as $file) {
		unlink($file);
	}

	rmdir($dir);
}

// The product import no longer loads PHPExcel.
function test_products_import_sheet_loads_no_phpexcel()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_products_f.php');
	$start = strpos($source, 'function pg_products_import_to_csv(');
	$end = strpos($source, 'function pg_products_import_xlsx_sheet_part(');

	pg_assert_true(($start !== false) && ($end !== false), 'the conversion found');
	pg_assert_same(false, strpos(substr($source, (int) $start, (int) $end - (int) $start), 'PHPExcel'), 'no PHPExcel in the conversion');
}
