<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reading a zip entry no further than the size its archive
 * declares for it: pg_zip_entry_uri(), pg_zip_entry_overran() and
 * pg_zip_entry_size() (includes/fn/files.php, the filter in
 * includes/zip_stream_cap.php), and the readers that stream an entry through
 * them: the office preview, the product importer, the bank statement
 * importer, and the restore's archive copy. The archives are small and
 * written here; in some of them one entry's declared size is lowered
 * afterwards, the data and its checksum left as they were.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/bank_import.php');

// An empty folder of its own for one test's archives.
function zip_entry_cap_test_dir()
{
	$dir = sys_get_temp_dir() . '/pg_zip_cap_test_' . bin2hex(random_bytes(4));
	mkdir($dir);

	return $dir;
}

function zip_entry_cap_test_clean($dir)
{
	foreach (glob($dir . '/*') as $path) {
		unlink($path);
	}

	rmdir($dir);
}

// A zip with these entries. For each name in $declared, the size the archive
// declares for that entry is set to the number given, in its local header
// (name length at 26, name at 30, size at 22) and in the central directory
// (name length at 28, name at 46, size at 24).
function zip_entry_cap_test_archive($path, array $entries, array $declared = array())
{
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

	foreach ($entries as $name => $data) {
		$zip->addFromString($name, $data);
	}

	$zip->close();

	$bytes = file_get_contents($path);

	foreach ($declared as $name => $size) {
		foreach (array(array("PK\x03\x04", 26, 30, 22), array("PK\x01\x02", 28, 46, 24)) as $header) {
			list($signature, $length_at, $name_at, $size_at) = $header;
			$position = 0;

			while (($position = strpos($bytes, $signature, $position)) !== false) {
				$length = unpack('v', substr($bytes, $position + $length_at, 2));

				if (substr($bytes, $position + $name_at, $length[1]) === $name) {
					$bytes = substr_replace($bytes, pack('V', $size), $position + $size_at, 4);
				}

				$position += 4;
			}
		}
	}

	file_put_contents($path, $bytes);

	return $path;
}

// Whether this libzip, unguarded, reads an entry past its declared size (the
// fault the guard is for). One that stops on its own leaves the reader tests
// nothing to show.
function zip_entry_cap_test_libzip_reads_past($path, $name)
{
	$declared = pg_zip_entry_size($path, $name);
	$data = @file_get_contents('zip://' . $path . '#' . $name);

	return is_string($data) && (strlen($data) > $declared);
}

// A workbook of one sheet: a product name from the shared strings and a
// price on each row.
function zip_entry_cap_test_workbook_parts($rows)
{
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$strings = '';
	$sheet = '';

	for ($number = 1; $number <= $rows; $number++) {
		$strings .= '<si><t>Ürün ' . $number . '</t></si>';
		$sheet .= '<row r="' . $number . '"><c r="A' . $number . '" t="s"><v>' . ($number - 1) . '</v></c><c r="B' . $number . '"><v>' . ($number * 10) . '</v></c></row>';
	}

	return array(
		'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><sheets><sheet name="Ürünler" sheetId="1" r:id="rId1"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
		'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="' . $ns . '">' . $strings . '</sst>',
		'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="' . $ns . '"><sheetData>' . $sheet . '</sheetData></worksheet>',
	);
}

function test_zip_entry_size()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = zip_entry_cap_test_dir();
	$path = zip_entry_cap_test_archive($dir . '/a.zip', array('a.txt' => 'hello world', 'b.txt' => str_repeat('b', 500)), array('b.txt' => 100));
	file_put_contents($dir . '/not.zip', 'not an archive');

	pg_assert_same(11, pg_zip_entry_size($path, 'a.txt'), 'what the entry unpacks to');
	pg_assert_same(100, pg_zip_entry_size($path, 'b.txt'), 'what the archive declares, not what the entry holds');
	pg_assert_same(-1, pg_zip_entry_size($path, 'c.txt'), 'no such entry');
	pg_assert_same(-1, pg_zip_entry_size($dir . '/none.zip', 'a.txt'), 'no such archive');
	pg_assert_same(-1, pg_zip_entry_size($dir . '/not.zip', 'a.txt'), 'not an archive');

	zip_entry_cap_test_clean($dir);
}

// An honest entry comes through whole at its own size; one that holds more
// than it declares stops at the declared size and says so, and the next
// address starts over.
function test_zip_entry_uri_reads_no_further_than_the_limit()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = zip_entry_cap_test_dir();
	$text = str_repeat("Satır, virgül ve noktalı virgül;\n", 300);
	$path = zip_entry_cap_test_archive($dir . '/a.zip', array('honest.txt' => $text, 'empty.txt' => '', 'more.txt' => $text), array('more.txt' => 2000));

	$handle = fopen(pg_zip_entry_uri($path, 'honest.txt', pg_zip_entry_size($path, 'honest.txt')), 'rb');
	$read = stream_get_contents($handle);
	fclose($handle);

	pg_assert_same($text, $read, 'an honest entry, whole');
	pg_assert_false(pg_zip_entry_overran(), 'within its limit');

	$handle = fopen(pg_zip_entry_uri($path, 'empty.txt', 0), 'rb');
	pg_assert_same('', stream_get_contents($handle), 'an empty entry at a limit of nothing');
	fclose($handle);
	pg_assert_false(pg_zip_entry_overran(), 'nothing to stop');

	$handle = fopen(pg_zip_entry_uri($path, 'more.txt', pg_zip_entry_size($path, 'more.txt')), 'rb');
	$read = '';

	while (!feof($handle)) {
		$chunk = fread($handle, 1024);

		if (($chunk === false) || ($chunk === '')) {
			break;
		}

		$read .= $chunk;
	}

	fclose($handle);

	pg_assert_true(strlen($read) <= 2000, 'no more than the declared 2000 bytes: ' . strlen($read));
	pg_assert_true(pg_zip_entry_overran(), 'the entry held more, and that is known');

	$handle = fopen(pg_zip_entry_uri($path, 'honest.txt', strlen($text)), 'rb');
	stream_get_contents($handle);
	fclose($handle);
	pg_assert_false(pg_zip_entry_overran(), 'the next address starts over');

	// The filter on its own, on a stream that is not a zip at all.
	$memory = fopen('php://memory', 'w+b');
	fwrite($memory, str_repeat('x', 100));
	rewind($memory);
	stream_filter_append($memory, 'pg_zip_cap.40', STREAM_FILTER_READ);
	$read = stream_get_contents($memory);
	fclose($memory);

	pg_assert_true(strlen($read) <= 40, 'a hundred bytes behind a limit of forty: ' . strlen($read));
	pg_assert_true(pg_zip_entry_overran(), 'stopped');

	zip_entry_cap_test_clean($dir);
}

// The preview: a shared string table that holds more than it declares is not
// read at all ("could not be read"); a sheet that does is shown up to where
// its declared size ends, marked as cut.
function test_zip_entry_cap_office_preview()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$dir = zip_entry_cap_test_dir();
	$parts = zip_entry_cap_test_workbook_parts(300);

	$honest = zip_entry_cap_test_archive($dir . '/honest.xlsx', $parts);
	$book = pg_office_read($honest, 'liste.xlsx');

	pg_assert_true($book['ok'], 'an honest workbook reads: ' . $book['error']);
	pg_assert_same(300, count($book['sheets'][0]['rows']), 'every row');
	pg_assert_same(array('Ürün 300', '3000'), $book['sheets'][0]['rows'][299]['cells'], 'the last row, with its shared string');
	pg_assert_false($book['truncated'], 'nothing cut');

	$strings = zip_entry_cap_test_archive($dir . '/strings.xlsx', $parts, array('xl/sharedStrings.xml' => 1000));

	if (!zip_entry_cap_test_libzip_reads_past($strings, 'xl/sharedStrings.xml')) {
		pg_assert_true(true, 'this libzip keeps to the declared size itself');
		zip_entry_cap_test_clean($dir);
		return;
	}

	$book = pg_office_read($strings, 'liste.xlsx');

	pg_assert_false($book['ok'], 'a string table past its declared size');
	pg_assert_same(lang('The file could not be read.'), $book['error'], 'is a file that could not be read');

	$sheet = zip_entry_cap_test_archive($dir . '/sheet.xlsx', $parts, array('xl/worksheets/sheet1.xml' => 12000));
	$book = pg_office_read($sheet, 'liste.xlsx');
	$rows = $book['ok'] ? $book['sheets'][0]['rows'] : array();

	pg_assert_true($book['ok'], 'a sheet past its declared size still opens: ' . $book['error']);
	pg_assert_true((count($rows) > 0) && (count($rows) < 300), 'with the rows within its declared size: ' . count($rows));
	pg_assert_same(array('Ürün 1', '10'), $rows ? $rows[0]['cells'] : array(), 'from the first');
	pg_assert_true($book['truncated'] && $book['sheets'][0]['truncated'], 'and says it was cut');

	zip_entry_cap_test_clean($dir);
}

// The product importer and the bank statement importer take nothing from a
// workbook whose strings or sheet hold more than they declare.
function test_zip_entry_cap_importers()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$dir = zip_entry_cap_test_dir();
	$parts = zip_entry_cap_test_workbook_parts(300);

	$honest = zip_entry_cap_test_archive($dir . '/honest.xlsx', $parts);
	$error = '';
	$csv = pg_products_import_xlsx_to_csv($honest, $error);

	pg_assert_same('', $error, 'an honest workbook is taken in');
	pg_assert_same(300, ($csv !== '') ? count(file($csv)) : 0, 'every row');

	if ($csv !== '') {
		unlink($csv);
	}

	$files = array(
		'strings' => zip_entry_cap_test_archive($dir . '/strings.xlsx', $parts, array('xl/sharedStrings.xml' => 1000)),
		'sheet' => zip_entry_cap_test_archive($dir . '/sheet.xlsx', $parts, array('xl/worksheets/sheet1.xml' => 12000)),
	);

	if (!zip_entry_cap_test_libzip_reads_past($files['sheet'], 'xl/worksheets/sheet1.xml')) {
		pg_assert_true(true, 'this libzip keeps to the declared size itself');
		zip_entry_cap_test_clean($dir);
		return;
	}

	foreach ($files as $label => $path) {
		$error = '';

		pg_assert_same('', pg_products_import_xlsx_to_csv($path, $error), 'product importer, ' . $label . ': nothing taken');
		pg_assert_same(lang('The file could not be read.'), $error, 'product importer, ' . $label . ': could not be read');

		$read = erp_bank_read_file($path, 'statement.xlsx');

		pg_assert_same(array(), $read['rows'], 'bank statement, ' . $label . ': no rows');
		pg_assert_same(lang('The file could not be read.'), $read['error'], 'bank statement, ' . $label . ': could not be read');
	}

	zip_entry_cap_test_clean($dir);
}

// The restore's copy keeps an honest file and deletes one that holds more
// than its declared size, after reading no further than one byte past it.
function test_zip_entry_cap_archive_copy()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = zip_entry_cap_test_dir();
	$dump = str_repeat("INSERT INTO `log` VALUES (1,'kayıt');\n", 200);
	$path = zip_entry_cap_test_archive($dir . '/backup.zip', array('sql.sql' => $dump, 'more.sql' => $dump), array('more.sql' => 1000));

	$zip = new ZipArchive();
	$zip->open($path);

	pg_assert_same('', pg_restore_archive_copy($zip, 0, $dir . '/sql.sql'), 'an honest file is copied');
	pg_assert_same($dump, file_get_contents($dir . '/sql.sql'), 'whole');

	pg_assert_contains('more.sql', pg_restore_archive_copy($zip, 1, $dir . '/more.out'), 'a file past its declared size is damaged');
	pg_assert_false(file_exists($dir . '/more.out'), 'and is not left behind');

	$zip->close();

	zip_entry_cap_test_clean($dir);
}
