<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the .xls side of the importers' sheet reader (includes/fn/sheet_read.php,
 * includes/sheet_read_xls.php) on workbooks built here byte by byte: the
 * shapes Excel and other writers give that LibreOffice's own .xls files do
 * not, each read as PHPExcel read the same file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// -- building an .xls ------------------------------------------------------

/**
 * One BIFF record.
 */
function sheet_read_xls_test_record($code, $body)
{
	return pack('vv', $code, strlen($body)) . $body;
}

/**
 * A stream in a compound file (OLE2) of one FAT sector, a directory sector
 * and the stream's own sectors, as the reader and PHPExcel read it. The
 * stream is padded to 4,096 bytes so it lies in big blocks.
 */
function sheet_read_xls_test_ole($stream, $name = 'Workbook')
{
	$stream = str_pad($stream, max(4096, (int) ceil(strlen($stream) / 512) * 512), "\0");
	$sectors = (int) (strlen($stream) / 512);
	$end = 0xFFFFFFFE;
	$free = 0xFFFFFFFF;

	$header = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 16)
		. pack('vvvvv', 0x3E, 3, 0xFFFE, 9, 6) . str_repeat("\0", 6)
		. pack('VVVVVVVVV', 0, 1, 1, 0, 4096, $end, 0, $end, 0)
		. pack('V', 0) . str_repeat(pack('V', $free), 108);

	$fat = array(0xFFFFFFFD, $end);

	for ($index = 0; $index < $sectors; $index++) {
		$fat[] = ($index === $sectors - 1) ? $end : (3 + $index);
	}

	$fat = array_pad($fat, 128, $free);
	$fat_sector = '';

	foreach ($fat as $entry) {
		$fat_sector .= pack('V', $entry);
	}

	$entry = function ($name, $type, $child, $start, $size) {
		$utf16 = ($name === '') ? '' : iconv('UTF-8', 'UTF-16LE', $name) . "\0\0";

		return str_pad($utf16, 64, "\0") . pack('vCC', strlen($utf16), $type, 1)
			. pack('VVV', 0xFFFFFFFF, 0xFFFFFFFF, $child) . str_repeat("\0", 36)
			. pack('VVV', $start, $size, 0);
	};

	$directory = $entry('Root Entry', 5, 1, $end, 0) . $entry($name, 2, 0xFFFFFFFF, 2, strlen($stream))
		. $entry('', 0, 0xFFFFFFFF, 0, 0) . $entry('', 0, 0xFFFFFFFF, 0, 0);

	return $header . $fat_sector . $directory . $stream;
}

/**
 * The SST and its CONTINUE records for a list of strings, each record's
 * body at most $cap bytes. A string is array(text, formatting runs, phonetic
 * bytes, the characters after a split written as UTF-16). A split inside a
 * string's characters starts the next record with the option byte, as Excel
 * writes it; a split inside its runs or phonetic bytes does not.
 *
 * @return string[] the record bodies, the SST first
 */
function sheet_read_xls_test_sst($strings, $cap)
{
	$records = array(pack('VV', count($strings), count($strings)));
	$last = 0;

	foreach ($strings as $string) {
		list($text, $runs, $ext, $switch) = array_pad($string, 4, false);
		$chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
		$latin = @iconv('UTF-8', 'ISO-8859-1', $text);
		$compressed = ($latin !== false) && (iconv('ISO-8859-1', 'UTF-8', $latin) === $text);
		$flags = ($compressed ? 0 : 1) | ($runs ? 8 : 0) | (($ext !== '') && ($ext !== false) ? 4 : 0);
		$head = pack('vC', count($chars), $flags) . ($runs ? pack('v', $runs) : '') . (($flags & 4) ? pack('V', strlen($ext)) : '');

		if ($cap - strlen($records[$last]) < strlen($head) + 1) {
			$records[++$last] = '';
		}

		$records[$last] .= $head;

		while (!empty($chars)) {
			if ($cap - strlen($records[$last]) <= ($compressed ? 0 : 1)) {
				if ($switch) {
					$compressed = false;
				}

				$records[++$last] = chr($compressed ? 0 : 1);
			}

			$width = $compressed ? 1 : 2;
			$take = array_splice($chars, 0, min(count($chars), (int) floor(($cap - strlen($records[$last])) / $width)));
			$records[$last] .= iconv('UTF-8', $compressed ? 'ISO-8859-1' : 'UTF-16LE', implode('', $take));
		}

		$tail = '';

		for ($run = 0; $run < (int) $runs; $run++) {
			$tail .= pack('vv', $run * 2, 0);
		}

		foreach (str_split($tail . (string) $ext) as $byte) {
			if (($byte === '') || (strlen($records[$last]) >= $cap)) {
				if ($byte === '') {
					break;
				}

				$records[++$last] = '';
			}

			$records[$last] .= $byte;
		}
	}

	return $records;
}

/**
 * A BIFF8 workbook with one sheet; $globals are extra workbook records
 * (an SST and its CONTINUE records), $cells the sheet's cell records.
 */
function sheet_read_xls_test_book($globals, $cells)
{
	$xf = function ($format, $style) {
		return sheet_read_xls_test_record(0xE0, pack('vvvCCCCVVv', 0, $format, $style ? 0xFFF5 : 0x0001, 0x20, 0, 0, 0, 0, 0, 0x20C0));
	};

	$book = sheet_read_xls_test_record(0x809, pack('vvvvVV', 0x600, 0x5, 0x0DBB, 0x7CC, 0, 6))
		. sheet_read_xls_test_record(0x42, pack('v', 1200))
		. sheet_read_xls_test_record(0x31, pack('vvvvvCCCCCC', 200, 0, 0x7FFF, 400, 0, 0, 0, 0, 0, 5, 0) . 'Arial');

	for ($index = 0; $index < 16; $index++) {
		$book .= $xf(0, true);
	}

	$book .= $xf(0, false) . $xf(14, false);
	$sheet_record = strlen($book);
	$book .= sheet_read_xls_test_record(0x85, pack('VvCC', 0, 0, 6, 0) . 'Sayfa1') . $globals . sheet_read_xls_test_record(0x0A, '');
	$book = substr_replace($book, pack('V', strlen($book)), $sheet_record + 4, 4);

	return $book . sheet_read_xls_test_record(0x809, pack('vvvvVV', 0x600, 0x10, 0x0DBB, 0x7CC, 0, 6))
		. implode('', $cells) . sheet_read_xls_test_record(0x0A, '');
}

/**
 * A workbook whose column A holds the given shared strings, one a row,
 * written to a temporary file; its path.
 */
function sheet_read_xls_test_strings_file($strings, $cap)
{
	$globals = '';

	foreach (sheet_read_xls_test_sst($strings, $cap) as $index => $body) {
		$globals .= sheet_read_xls_test_record(($index === 0) ? 0xFC : 0x3C, $body);
	}

	$cells = array();

	foreach (array_keys($strings) as $index) {
		$cells[] = sheet_read_xls_test_record(0xFD, pack('vvvV', $index, 0, 16, $index));
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_sheet_xls') . '.xls';
	file_put_contents($path, sheet_read_xls_test_ole(sheet_read_xls_test_book($globals, $cells)));

	return $path;
}

/**
 * Column A of the first sheet as the reader gives it, and its error.
 */
function sheet_read_xls_test_column($path)
{
	$error = 'not run';
	$out = array();

	foreach (pg_sheet_rows($path, 'xls', $error) as $number => $row) {
		$out[$number] = isset($row[0]['v']) ? $row[0]['v'] : null;
	}

	return array($out, $error);
}

// -- shared strings across CONTINUE records ---------------------------------

// Excel and LibreOffice split the shared string table into CONTINUE records
// every 8,224 bytes; a workbook of a few thousand styled descriptions has a
// string's formatting runs cut by such a split. The library walked past the
// CONTINUE header there and refused the whole file; PHPExcel read every
// string (checked on this file before PHPExcel was removed).
function test_sheet_read_xls_shared_strings_with_runs_across_records()
{
	$strings = array();
	$want = array();

	for ($index = 0; $index < 12; $index++) {
		$text = ($index % 3) ? 'Satır ' . $index . ' açıklama Ödeme' : 'Row ' . $index . ' plain text';
		$strings[] = array($text, ($index % 4 === 1) ? 2 : 0, ($index === 5) ? "\1\2\3\4\5\6" : '');
		$want[$index + 1] = $text;
	}

	$path = sheet_read_xls_test_strings_file($strings, 40);
	list($read, $error) = sheet_read_xls_test_column($path);
	unlink($path);

	pg_assert_same('', $error, 'the file is read');
	pg_assert_same($want, $read, 'every string, its runs and phonetic bytes skipped across records');
}

// A string written one byte a character can go on two bytes a character in
// the next CONTINUE record (its option byte says so). The library kept only
// the last character of the part before the split.
function test_sheet_read_xls_shared_string_changing_width_across_records()
{
	$strings = array();
	$want = array();

	for ($index = 0; $index < 10; $index++) {
		$text = ($index % 2) ? 'Havale gönderen şirket ' . $index : 'Plain Latin text number ' . $index . ' continues';
		$strings[] = array($text, 0, '', ($index % 2) === 0);
		$want[$index + 1] = $text;
	}

	$path = sheet_read_xls_test_strings_file($strings, 30);
	list($read, $error) = sheet_read_xls_test_column($path);
	unlink($path);

	pg_assert_same('', $error, 'the file is read');
	pg_assert_same($want, $read, 'each string whole');
}

// A string longer than a record, over several CONTINUE records, and one
// whose head starts a record.
function test_sheet_read_xls_long_shared_string()
{
	$long = str_repeat('Uzun açıklama ğüşiöç ', 40);
	$path = sheet_read_xls_test_strings_file(array(array($long), array('İkinci'), array(str_repeat('x', 29)), array('Son')), 30);
	list($read, $error) = sheet_read_xls_test_column($path);
	unlink($path);

	pg_assert_same('', $error, 'the file is read');
	pg_assert_same(array(1 => $long, 2 => 'İkinci', 3 => str_repeat('x', 29), 4 => 'Son'), $read, 'every string');
}
