<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/http_range.php: which part of a file a Range header
 * asks for, when the whole file is sent instead, when the range lies past the
 * end (416), what If-Range does to it and the Content-Range that goes out.
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

// If-Range sends the part only while the file is the one the client holds a
// piece of: the validator it names must be one the response carries (an ETag
// for the API, the Last-Modified date for get_file.php).
function test_pg_http_range_answer_if_range()
{
	$date = 'Sun, 25 Oct 2026 10:00:00 GMT';

	pg_assert_same(array(0, 99), pg_http_range_answer('bytes=0-99', '', array($date), 1000), 'no If-Range');
	pg_assert_same(array(0, 99), pg_http_range_answer('bytes=0-99', $date, array($date), 1000), 'the same date');
	pg_assert_same(array(0, 99), pg_http_range_answer('bytes=0-99', ' "abc" ', array('"abc"'), 1000), 'the same ETag');
	pg_assert_same(null, pg_http_range_answer('bytes=0-99', 'Sat, 24 Oct 2026 10:00:00 GMT', array($date), 1000), 'another date: the whole file');
	pg_assert_same(null, pg_http_range_answer('bytes=0-99', '"abc"', array($date), 1000), 'an ETag the response has none of');
	pg_assert_same(null, pg_http_range_answer('bytes=0-99', 'W/"abc"', array('"abc"'), 1000), 'a weak ETag never matches');
	pg_assert_same(null, pg_http_range_answer('bytes=5000-', $date . 'x', array($date), 1000), 'a changed file is sent whole, not refused');
}

// Without If-Range the answer is the Range header's own.
function test_pg_http_range_answer_range()
{
	pg_assert_same(null, pg_http_range_answer('', '', array(), 1000), 'no Range');
	pg_assert_same(array(900, 999), pg_http_range_answer('bytes=-100', '', array(), 1000), 'the last bytes');
	pg_assert_false(pg_http_range_answer('bytes=1000-', '', array(), 1000), 'past the end');
	pg_assert_same(null, pg_http_range_answer('bytes=0-1,5-9', '', array(), 1000), 'several ranges');
}

// The Content-Range of a part (206) and of a refused range (416).
function test_pg_http_content_range()
{
	pg_assert_same('bytes 0-99/1000', pg_http_content_range(array(0, 99), 1000), 'a part');
	pg_assert_same('bytes 999-999/1000', pg_http_content_range(array(999, 999), 1000), 'the last byte');
	pg_assert_same('bytes */1000', pg_http_content_range(false, 1000), 'unsatisfiable');
	pg_assert_same('bytes */0', pg_http_content_range(false, 0), 'an empty file');
}
