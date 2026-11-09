<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_upload_base64_decode() (includes/fn/files.php), the bytes of a
 * file posted base64, with a "data:...;base64," prefix or without one. The
 * panel's upload_file action and chat attachments split the text at every
 * "base64" and decoded only what followed the last one, so a file whose
 * base64 text holds those letters (about one in 10,000 at 5 MB) was stored
 * cut short, without an error.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A file whose base64 text holds the letters "base64" comes back whole,
// wherever they stand and however often.
function test_pg_upload_base64_decode_keeps_a_file_whose_text_holds_base64()
{
	// Texts without padding: every four letters are three bytes, so each
	// text is exactly the encoding of the bytes it decodes to.
	$texts = array(
		'QUJDbase64xyQUJD',
		'base64AAQUJD',
		// Nothing after the last "base64": the old split stored an empty file.
		'QUJDREbase64',
		'QQbase64base64QQ',
	);

	foreach ($texts as $text) {
		$bytes = base64_decode($text, true);

		pg_assert_same($text, base64_encode((string) $bytes), $text . ' is the encoding of its bytes');
		pg_assert_same($bytes, pg_upload_base64_decode('data:application/pdf;base64,' . $text), $text . ' as a data URL');
		pg_assert_same($bytes, pg_upload_base64_decode($text), $text . ' without a data: prefix');
	}
}

// A data URL as FileReader.readAsDataURL() writes it, media type parameters
// and padding included.
function test_pg_upload_base64_decode_reads_a_data_url_as_a_browser_writes_it()
{
	$bytes = "\x89PNG\r\n\x1a\n" . str_repeat("\x00\x01\x02\xfe\xff", 20) . 'A';

	pg_assert_same($bytes, pg_upload_base64_decode('data:image/png;base64,' . base64_encode($bytes)), 'a picture');
	pg_assert_same('A', pg_upload_base64_decode('data:text/plain;charset=utf-8;base64,QQ=='), 'parameters and padding');
}

// A body posted without encoding turns every "+" into a space; a space has
// always been read as "+", with or without the prefix.
function test_pg_upload_base64_decode_reads_a_space_as_plus()
{
	$bytes = "PDF\xfb\xef\xbe";

	pg_assert_same('UERG++++', base64_encode($bytes), 'the text holds plus signs');
	pg_assert_same($bytes, pg_upload_base64_decode('data:application/pdf;base64,UERG    '), 'as a data URL');
	pg_assert_same($bytes, pg_upload_base64_decode('UERG    '), 'without a data: prefix');
}

// Line breaks, as a MIME encoder writes them, are not part of the file.
function test_pg_upload_base64_decode_skips_line_breaks()
{
	$bytes = str_repeat("Pinegrap \x00\xff", 40);

	pg_assert_same($bytes, pg_upload_base64_decode(chunk_split(base64_encode($bytes))), 'CRLF every 76 letters');
	pg_assert_same($bytes, pg_upload_base64_decode('data:text/plain;base64,' . chunk_split(base64_encode($bytes), 64, "\n")), 'LF every 64 letters');
}

// Nothing after the prefix is an empty file, not an error: each door decides
// whether it takes one.
function test_pg_upload_base64_decode_reads_an_empty_body_as_no_bytes()
{
	pg_assert_same('', pg_upload_base64_decode('data:application/octet-stream;base64,'), 'an empty body');
	pg_assert_same('', pg_upload_base64_decode(''), 'an empty text');
}

// What is not base64 is refused, never stored as the bytes a lenient decoder
// makes of it.
function test_pg_upload_base64_decode_refuses_what_is_not_base64()
{
	pg_assert_false(pg_upload_base64_decode('data:text/plain,hello'), 'a data URL that is not base64');
	pg_assert_false(pg_upload_base64_decode('data:'), 'a data URL with nothing in it');
	pg_assert_false(pg_upload_base64_decode('data:text/plain;base64,%51%51%3D%3D'), 'percent-encoded');
	pg_assert_false(pg_upload_base64_decode('QUJD-_8A'), 'base64url letters');
	pg_assert_false(pg_upload_base64_decode('data:text/plain;base64,QUJD,QUJD'), 'a comma in the body');
	pg_assert_false(pg_upload_base64_decode(array('QUJD')), 'not a string');
	pg_assert_false(pg_upload_base64_decode(null), 'nothing');
}

// Both doors decode through the helper; neither splits at every "base64".
function test_pg_upload_base64_decode_is_what_both_doors_use()
{
	foreach (array('includes/panel/content.php', 'chat.php') as $path) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);

		pg_assert_true(strpos($source, 'pg_upload_base64_decode(') !== false, $path . ' decodes through the helper');
		pg_assert_false((bool) preg_match('/explode\(\s*[\'"]base64[\'"]\s*,/', $source), $path . ' no longer splits at every "base64"');
	}
}
