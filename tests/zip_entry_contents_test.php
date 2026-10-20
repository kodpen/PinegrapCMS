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
	), array('bomb.txt' => 1000, 'short.txt' => 400));

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

	pg_assert_same(null, pg_zip_entry_contents($zip, 'bomb.txt', 1000000, $error), 'an entry inflating past its declared size');
	pg_assert_same('damaged', $error, 'said to be damaged');

	pg_assert_same(null, pg_zip_entry_contents($zip, 'short.txt', 1000000, $error), 'an entry holding less than it declares');
	pg_assert_same('damaged', $error, 'said to be damaged too');

	pg_assert_same(null, pg_zip_entry_contents($zip, 'none.txt', 1000, $error), 'no such entry');
	pg_assert_same('missing', $error, 'said to be missing');
	pg_assert_same(null, pg_zip_entry_contents($zip, 99, 1000, $error), 'no such index');
	pg_assert_same('missing', $error, 'said to be missing by index');

	$zip->close();
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

	file_put_contents($dir . '/not.zip', 'not an archive');
	pg_assert_same(null, pg_zip_import_open($dir . '/not.zip', $error), 'a file that is not a zip');
	pg_assert_same(lang('The zip file cannot be extracted, because it is not a valid zip file.'), $error, 'said to be no zip');

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

	$large = zip_entry_contents_test_archive($dir . '/large.ods', $parts, array('content.xml' => PG_OFFICE_MAX_PART + 1));
	pg_assert_same(lang('The file is too large.'), pg_products_import_ods_check($large), 'content.xml declaring more than an office part may');

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
