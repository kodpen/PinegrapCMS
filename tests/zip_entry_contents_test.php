<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reading a zip entry whole without inflating it past what its
 * archive declares: pg_zip_entry_contents() and pg_zip_import_open()
 * (includes/fn/files.php), and the OpenDocument check in front of PHPExcel
 * (pg_products_import_ods_check(), import_products_f.php) as the product
 * importer and the bank statement importer use it. The archives are small
 * and written here; in some of them one entry's declared size is changed
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
function zip_entry_contents_test_dir()
{
	$dir = sys_get_temp_dir() . '/pg_zip_contents_test_' . bin2hex(random_bytes(4));
	mkdir($dir);

	return $dir;
}

function zip_entry_contents_test_clean($dir)
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
function zip_entry_contents_test_archive($path, array $entries, array $declared = array())
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

// A zip written byte by byte, for compressed streams this PHP's libzip does
// not write itself: name => array(method, compressed bytes, size, crc).
function zip_entry_contents_test_raw_archive($path, array $entries)
{
	$out = '';
	$central = '';

	foreach ($entries as $name => $entry) {
		list($method, $data, $size, $crc) = $entry;
		$offset = strlen($out);

		$out .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, $method, 0, 0, $crc, strlen($data), $size, strlen($name), 0) . $name . $data;
		$central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $method, 0, 0, $crc, strlen($data), $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
	}

	$out .= $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), strlen($out), 0);

	file_put_contents($path, $out);

	return $path;
}

// Whether this libzip, asked for one byte past an entry's declared size,
// hands it over (the overrun pg_zip_entry_contents() refuses). One that stops
// at the declared size on its own leaves nothing to tell apart.
function zip_entry_contents_test_libzip_reads_past($zip, $name)
{
	$stat = $zip->statName($name);
	$data = $zip->getFromIndex($stat['index'], $stat['size'] + 1);

	return is_string($data) && (strlen($data) > $stat['size']);
}

// The two parts PHPExcel's OpenDocument reader reads, around one sheet with a
// heading row and one transaction.
function zip_entry_contents_test_ods_parts()
{
	$cell = function ($text) {
		return '<table:table-cell office:value-type="string"><text:p>' . $text . '</text:p></table:table-cell>';
	};

	return array(
		'mimetype' => 'application/vnd.oasis.opendocument.spreadsheet',
		'meta.xml' => '<?xml version="1.0" encoding="UTF-8"?><office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:meta/></office:document-meta>',
		'content.xml' => '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:version="1.2"><office:body><office:spreadsheet><table:table table:name="Hareketler">'
			. '<table:table-row>' . $cell('Tarih') . $cell('Açıklama') . $cell('Tutar') . '</table:table-row>'
			. '<table:table-row>' . $cell('2026-09-23') . $cell('Kira') . $cell('-800.50') . '</table:table-row>'
			. '</table:table></office:spreadsheet></office:body></office:document-content>',
	);
}

// An honest entry comes back whole; one that declares more than the limit is
// refused before it is read; one that inflates past its declared size, or
// holds less than it, is refused as damaged.
function test_zip_entry_contents_reads_no_further_than_declared()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = zip_entry_contents_test_dir();
	$path = zip_entry_contents_test_archive($dir . '/a.zip', array(
		'ok.txt' => 'hello world',
		'empty.txt' => '',
		'bomb.txt' => str_repeat('x', 200000),
		'short.txt' => str_repeat('s', 300),
		'nothing.txt' => str_repeat('n', 5000),
	), array('bomb.txt' => 1000, 'short.txt' => 400, 'nothing.txt' => 0));

	$zip = new ZipArchive();
	pg_assert_true($zip->open($path) === true, 'archive opens');

	$error = 'unset';
	pg_assert_same('hello world', pg_zip_entry_contents($zip, 'ok.txt', 1000, $error), 'an honest entry, whole');
	pg_assert_same('', $error, 'no error for it');
	pg_assert_same('hello world', pg_zip_entry_contents($zip, $zip->locateName('ok.txt'), 1000, $error), 'the same entry by its index');
	pg_assert_same('hello world', pg_zip_entry_contents($zip, 'ok.txt', 11, $error), 'a limit of exactly its size');
	pg_assert_same('', pg_zip_entry_contents($zip, 'empty.txt', 1000, $error), 'an empty entry');
	pg_assert_same('', $error, 'an empty entry is no error');

	pg_assert_same(null, pg_zip_entry_contents($zip, 'ok.txt', 10, $error), 'an entry declaring more than the limit');
	pg_assert_same('large', $error, 'said to be too large');

	if (zip_entry_contents_test_libzip_reads_past($zip, 'bomb.txt')) {
		pg_assert_same(null, pg_zip_entry_contents($zip, 'bomb.txt', 1000000, $error), 'an entry inflating past its declared size');
		pg_assert_same('damaged', $error, 'said to be damaged');
	} else {
		$data = pg_zip_entry_contents($zip, 'bomb.txt', 1000000, $error);
		pg_assert_true(($data === null) || (strlen($data) <= 1000), 'this libzip stops at the declared size itself');
	}

	pg_assert_same(null, pg_zip_entry_contents($zip, 'nothing.txt', 1000000, $error), 'an entry declaring nothing over compressed data');
	pg_assert_same('damaged', $error, 'said to be damaged as well');

	pg_assert_same(null, pg_zip_entry_contents($zip, 'short.txt', 1000000, $error), 'an entry holding less than it declares');
	pg_assert_same('damaged', $error, 'said to be damaged too');

	pg_assert_same(null, pg_zip_entry_contents($zip, 'none.txt', 1000, $error), 'no such entry');
	pg_assert_same('missing', $error, 'said to be missing');
	pg_assert_same(null, pg_zip_entry_contents($zip, 99, 1000, $error), 'no such index');
	pg_assert_same('missing', $error, 'said to be missing by index');

	$zip->close();
	zip_entry_contents_test_clean($dir);
}

// An empty file is empty whatever its writer put in its compressed stream:
// Go's archive/zip ends every deflate stream with an empty stored block (5
// bytes), a sync-flushing writer leaves 7, bzip2 14. Those used to be
// refused as damaged, and the imported page linked to a file never written.
// Data behind a size of nothing is still refused.
function test_zip_entry_contents_empty_entries_of_any_writer()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = zip_entry_contents_test_dir();
	$path = zip_entry_contents_test_raw_archive($dir . '/go.zip', array(
		'css/go-empty.css' => array(8, "\x01\x00\x00\xff\xff", 0, 0),
		'sync-empty.css' => array(8, "\x00\x00\x00\xff\xff\x03\x00", 0, 0),
		'zlib-empty.css' => array(8, "\x03\x00", 0, 0),
		'stored-empty.css' => array(0, '', 0, 0),
		'hidden.txt' => array(8, gzdeflate(str_repeat('n', 5000)), 0, crc32(str_repeat('n', 5000))),
		'two-letters.txt' => array(8, gzdeflate('ab'), 0, crc32('ab')),
		'garbage.txt' => array(8, "\xff\xff\xff\xff\xff\xff", 0, 0),
	));

	$zip = new ZipArchive();
	pg_assert_true($zip->open($path) === true, 'archive opens');

	foreach (array('css/go-empty.css', 'sync-empty.css', 'zlib-empty.css', 'stored-empty.css') as $name) {
		$error = 'unset';
		pg_assert_same('', pg_zip_entry_contents($zip, $name, 1000, $error), $name);
		pg_assert_same('', $error, $name . ': no error');
	}

	pg_assert_same('', pg_zip_entry_contents($zip, $zip->locateName('css/go-empty.css'), 1000, $error), 'by its index');

	foreach (array('hidden.txt', 'two-letters.txt', 'garbage.txt') as $name) {
		$error = 'unset';
		pg_assert_same(null, pg_zip_entry_contents($zip, $name, 1000, $error), $name);
		pg_assert_same('damaged', $error, $name . ': damaged');
	}

	$zip->close();

	if (method_exists('ZipArchive', 'isCompressionMethodSupported') && ZipArchive::isCompressionMethodSupported(ZipArchive::CM_BZIP2)) {
		$zip = new ZipArchive();
		$zip->open($dir . '/bz.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
		$zip->addFromString('empty.css', '');
		$zip->setCompressionName('empty.css', ZipArchive::CM_BZIP2);
		$zip->close();

		$zip->open($dir . '/bz.zip');
		pg_assert_same('', pg_zip_entry_contents($zip, 'empty.css', 1000, $error), 'an empty bzip2 entry');
		pg_assert_same('', $error, 'bzip2: no error');
		$zip->close();
	}

	zip_entry_contents_test_clean($dir);
}

// An archive to import entry by entry is measured from its directory: an
// honest one opens, one that declares more than the byte ceiling or holds
// more entries than the entry ceiling is refused, as is a file that is not
// a zip.
function test_zip_import_open_measures_the_archive_first()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = zip_entry_contents_test_dir();
	$error = 'unset';

	$path = zip_entry_contents_test_archive($dir . '/site.zip', array('index.html' => '<p>Merhaba</p>', 'css/site.css' => 'p{color:red}'));
	$zip = pg_zip_import_open($path, $error);
	pg_assert_true($zip instanceof ZipArchive, 'an honest archive opens');
	pg_assert_same('', $error, 'with no error');
	pg_assert_same('<p>Merhaba</p>', pg_zip_entry_contents($zip, 'index.html', PG_ZIP_IMPORT_MAX_ENTRY, $error), 'and its entries read');
	$zip->close();

	// Four bytes that declare 3 GB: refused from the directory, never read.
	$path = zip_entry_contents_test_archive($dir . '/big.zip', array('index.html' => 'abcd'), array('index.html' => 3000000000));
	pg_assert_same(null, pg_zip_import_open($path, $error), 'an archive declaring more than the ceiling');
	pg_assert_same(lang('Sorry, that archive is too large to extract here.'), $error, 'said to be too large');

	$entries = array();

	for ($number = 0; $number <= PG_ZIP_IMPORT_MAX_ENTRIES; $number++) {
		$entries['f' . $number . '.txt'] = '';
	}

	$path = zip_entry_contents_test_archive($dir . '/many.zip', $entries);
	pg_assert_same(null, pg_zip_import_open($path, $error), 'an archive with one entry too many');
	pg_assert_same(lang('Sorry, that archive is too large to extract here.'), $error, 'said to be too large too');

	$invalid = lang('The zip file cannot be extracted, because it is not a valid zip file.');

	file_put_contents($dir . '/not.zip', 'not an archive');
	pg_assert_same(null, pg_zip_import_open($dir . '/not.zip', $error), 'a file that is not a zip');
	pg_assert_same($invalid, $error, 'said to be no zip');

	file_put_contents($dir . '/empty.zip', '');
	pg_assert_same(null, pg_zip_import_open($dir . '/empty.zip', $error), 'an empty file');
	pg_assert_same($invalid, $error, 'said to be no zip too');

	// The end record alone: a zip that holds nothing.
	file_put_contents($dir . '/hollow.zip', "PK\x05\x06" . str_repeat("\0", 18));
	pg_assert_same(null, pg_zip_import_open($dir . '/hollow.zip', $error), 'an archive with no entries');
	pg_assert_same($invalid, $error, 'nothing to import is no zip either');

	pg_assert_same(null, pg_zip_import_open($dir . '/none.zip', $error), 'no file at all');

	zip_entry_contents_test_clean($dir);
}

// The OpenDocument check passes an honest file and turns away one whose
// parts declare too much, inflate past what they declare, or are not there;
// the product importer and the bank statement importer answer with it
// before PHPExcel holds a part.
function test_zip_entry_contents_ods_check()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = zip_entry_contents_test_dir();
	$parts = zip_entry_contents_test_ods_parts();
	$unreadable = lang('The file could not be read.');

	$honest = zip_entry_contents_test_archive($dir . '/honest.ods', $parts);
	pg_assert_same('', pg_products_import_ods_check($honest), 'an honest file');

	$bomb = zip_entry_contents_test_archive($dir . '/bomb.ods', $parts, array('content.xml' => 100));
	pg_assert_same($unreadable, pg_products_import_ods_check($bomb), 'content.xml inflating past its declared size');

	$large = zip_entry_contents_test_archive($dir . '/large.ods', $parts, array('content.xml' => PG_PRODUCTS_IMPORT_ODS_MAX_PART + 1));
	pg_assert_same(lang('The file is too large.'), pg_products_import_ods_check($large), 'content.xml declaring more than the ceiling');

	$no_meta = $parts;
	unset($no_meta['meta.xml']);
	$no_meta = zip_entry_contents_test_archive($dir . '/no-meta.ods', $no_meta);
	pg_assert_same($unreadable, pg_products_import_ods_check($no_meta), 'no meta.xml');

	file_put_contents($dir . '/not.ods', 'not a workbook');
	pg_assert_same($unreadable, pg_products_import_ods_check($dir . '/not.ods'), 'not a zip');

	// The importers answer before the reader: nothing is taken in.
	$read = erp_bank_read_file($bomb, 'statement.ods');
	pg_assert_same(array(), $read['rows'], 'bank statement: no rows from the doctored file');
	pg_assert_same($unreadable, $read['error'], 'bank statement: said so');

	$read = erp_bank_read_file($large, 'statement.ods');
	pg_assert_same(lang('The file is too large.'), $read['error'], 'bank statement: too large');

	$error = '';
	pg_assert_same('', pg_products_import_to_csv($bomb, 'products.ods', $error), 'product import: no CSV from the doctored file');
	pg_assert_same($unreadable, $error, 'product import: said so');

	zip_entry_contents_test_clean($dir);
}

// An entry's name as the path its references resolve to.
function test_zip_entry_path()
{
	pg_assert_same('css/a.css', pg_zip_entry_path('css/a.css'), 'a clean name as it is');
	pg_assert_same('css/a.css', pg_zip_entry_path('./css//a.css'), 'dot segments and doubled slashes dropped');
	pg_assert_same('css/a.css', pg_zip_entry_path('x/../css/a.css'), 'parent segment resolved');
	pg_assert_same('a.css', pg_zip_entry_path('../../a.css'), 'never above the top');
	pg_assert_same('a.css', pg_zip_entry_path('/a.css'), 'no leading slash');
	pg_assert_same('site/img/', pg_zip_entry_path('site/./img/'), 'a directory keeps its slash');
	pg_assert_same('', pg_zip_entry_path('./'), 'nothing left');
	pg_assert_same("g\xF6rsel.png", pg_zip_entry_path("g\xF6rsel.png"), 'bytes of another encoding untouched');
}
