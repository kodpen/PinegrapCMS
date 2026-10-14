<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_http_byte_range() in includes/fn/files.php: which part of a
 * file a Range header asks for, when the whole file is sent instead, and
 * when the range lies past the end (416).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The forms a video player sends: from a byte to the end, a closed range,
// and the last bytes of the file.
function test_pg_http_byte_range_reads_one_range()
{
	pg_assert_same(array(0, 999), pg_http_byte_range('bytes=0-', 1000), 'from the start to the end');
	pg_assert_same(array(500, 999), pg_http_byte_range('bytes=500-', 1000), 'from a byte to the end');
	pg_assert_same(array(0, 0), pg_http_byte_range('bytes=0-0', 1000), 'the first byte');
	pg_assert_same(array(100, 199), pg_http_byte_range('bytes=100-199', 1000), 'a closed range');
	pg_assert_same(array(900, 999), pg_http_byte_range('bytes=-100', 1000), 'the last hundred bytes');
	pg_assert_same(array(100, 199), pg_http_byte_range(' Bytes = 100 - 199 ', 1000), 'spaces and case');
}

// A last byte past the end, or a suffix longer than the file, is cut to the
// file rather than refused.
function test_pg_http_byte_range_cuts_to_the_file()
{
	pg_assert_same(array(900, 999), pg_http_byte_range('bytes=900-5000', 1000), 'last byte past the end');
	pg_assert_same(array(0, 999), pg_http_byte_range('bytes=-5000', 1000), 'suffix longer than the file');
	pg_assert_same(array(10, 999), pg_http_byte_range('bytes=10-99999999999999999999999', 1000), 'a last byte too long for an int');
	pg_assert_same(array(0, 999), pg_http_byte_range('bytes=-99999999999999999999999', 1000), 'a suffix too long for an int');
}

// A range that starts past the end cannot be served at all.
function test_pg_http_byte_range_past_the_end()
{
	pg_assert_false(pg_http_byte_range('bytes=1000-', 1000), 'first byte is the size');
	pg_assert_false(pg_http_byte_range('bytes=2000-3000', 1000), 'wholly past the end');
	pg_assert_false(pg_http_byte_range('bytes=99999999999999999999999-', 1000), 'a first byte too long for an int');
	pg_assert_false(pg_http_byte_range('bytes=-0', 1000), 'an empty suffix');
	pg_assert_false(pg_http_byte_range('bytes=0-', 0), 'an empty file');
}

// Anything else is ignored and the whole file goes out: no header, another
// unit, several ranges, a range written backwards, or nothing that parses.
function test_pg_http_byte_range_ignored()
{
	foreach (array('', 'bytes=', 'bytes=-', 'items=0-10', 'bytes=0-10,20-30', 'bytes=200-100', 'bytes=a-b', 'bytes 0-10', 'bytes=0-10;x') as $header) {
		pg_assert_same(null, pg_http_byte_range($header, 1000), var_export($header, true));
	}
}
