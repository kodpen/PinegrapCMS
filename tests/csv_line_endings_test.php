<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * pg_csv_open() hands the CSV importers a handle whose lines fgetcsv() splits
 * the same way for LF, CRLF and classic Mac (CR-only) files, without the
 * auto_detect_line_endings setting deprecated in PHP 8.1.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The rows fgetcsv() reads from $bytes through pg_csv_open().
function csv_line_endings_test_rows($bytes)
{
	$path = tempnam(sys_get_temp_dir(), 'pgcsv');
	file_put_contents($path, $bytes);
	$handle = pg_csv_open($path);
	$rows = array();

	while (($row = fgetcsv($handle, 100000, ',', '"', '\\')) !== false) {
		$rows[] = $row;
	}

	fclose($handle);
	unlink($path);

	return $rows;
}

function test_csv_line_endings_lf_crlf_and_cr_read_alike()
{
	$expected = array(array('name', 'email'), array('Ada', 'ada@example.com'), array('Bob', 'bob@example.com'));

	pg_assert_same($expected, csv_line_endings_test_rows("name,email\nAda,ada@example.com\nBob,bob@example.com\n"), 'LF');
	pg_assert_same($expected, csv_line_endings_test_rows("name,email\r\nAda,ada@example.com\r\nBob,bob@example.com\r\n"), 'CRLF');
	pg_assert_same($expected, csv_line_endings_test_rows("name,email\rAda,ada@example.com\rBob,bob@example.com\r"), 'CR');
	pg_assert_same($expected, csv_line_endings_test_rows("name,email\rAda,ada@example.com\rBob,bob@example.com"), 'CR, no final break');
}

function test_csv_line_endings_cr_file_longer_than_a_chunk()
{
	$lines = array();
	for ($i = 0; $i < 20000; $i++) {
		$lines[] = 'row' . $i . ',' . str_repeat('x', $i % 7);
	}

	$rows = csv_line_endings_test_rows(implode("\r", $lines) . "\r");

	pg_assert_same(20000, count($rows), 'row count');
	pg_assert_same(array('row19999', str_repeat('x', 19999 % 7)), $rows[19999], 'last row');
}

function test_csv_line_endings_crlf_split_at_the_first_look_is_not_cr()
{
	// The first break's CR is the last byte of the first 8 KB read.
	$first = str_repeat('a', 8191);
	$rows = csv_line_endings_test_rows($first . "\r\nb\r\n");

	pg_assert_same(array(array($first), array('b')), $rows);
}

function test_csv_line_endings_missing_file_is_false()
{
	pg_assert_false(pg_csv_open(sys_get_temp_dir() . '/pg-csv-missing-' . uniqid() . '.csv'));
}
