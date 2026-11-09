<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a comma in a file name. A name is never stored with one: every
 * door goes through prepare_file_name(), whose pg_ascii_file_name() turns it
 * into an underscore. A file stored before that rule (sites older than
 * 2026.4.1) keeps its comma, and get_file.php sends the name in its
 * Content-Disposition header. Unquoted, Chrome reads "filename=a,b.jpg" as
 * two values of that header and refuses the answer
 * (ERR_RESPONSE_HEADERS_MULTIPLE_CONTENT_DISPOSITION): the picture stays
 * empty and its address opens an error page. pg_http_content_disposition()
 * (includes/http_range.php) builds the header for both of get_file.php's
 * answers, with the name in quotes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/file_store_serve.php');

/**
 * The values a browser reads from a header that may hold a list: cut at
 * every comma outside a quoted string, as Chrome cuts Content-Disposition
 * before it checks that the header has one value.
 *
 * @param string $value
 * @return array
 */
function file_name_comma_header_values($value)
{
	$values = array();
	$current = '';
	$quoted = false;
	$length = strlen($value);

	for ($i = 0; $i < $length; $i++) {
		$character = $value[$i];

		if ($quoted && ($character === '\\') && ($i + 1 < $length)) {
			$current .= $character . $value[++$i];
			continue;
		}

		if ($character === '"') {
			$quoted = !$quoted;
		}

		if (($character === ',') && !$quoted) {
			$values[] = trim($current);
			$current = '';
			continue;
		}

		$current .= $character;
	}

	$values[] = trim($current);

	return $values;
}

// The upload rule: a comma becomes an underscore, a run of them one.
function test_file_name_comma_is_not_stored()
{
	pg_assert_same('a_b.jpg', prepare_file_name('a,b.jpg'), 'one comma');
	pg_assert_same('1_5_kg.jpg', prepare_file_name('1,5 kg.jpg'), 'a comma and a space');
	pg_assert_same('a_b.jpg', prepare_file_name('a,,b.jpg'), 'a run of commas');
	pg_assert_same('urun_kirmizi.png', prepare_file_name('ürün, kırmızı.png'), 'beside Turkish letters');
	pg_assert_same('photo.jpg', prepare_file_name(',photo.jpg'), 'in front of the name');
}

function test_file_name_comma_stays_inside_the_quotes()
{
	pg_assert_same('inline; filename="a,b.jpg"', pg_http_content_disposition('a,b.jpg', true));
	pg_assert_same('attachment; filename="1,5 kg.zip"', pg_http_content_disposition('1,5 kg.zip', false));
}

// Whatever the name, the header is one value for a browser.
function test_file_name_comma_header_is_one_value()
{
	$names = array('a,b.jpg', ',a.jpg', 'a,.jpg', 'a, b,c.pdf', 'a",b.jpg', 'a\\",b.jpg', "a,\r\nb.jpg", 'Özet, 2026.pdf', '"', ',');

	foreach ($names as $name) {
		foreach (array(true, false) as $inline) {
			$header = pg_http_content_disposition($name, $inline);

			pg_assert_same(1, count(file_name_comma_header_values($header)), $header);
		}
	}
}

// A quote, a backslash or a line break would end the quoted name or the
// header, so it is removed.
function test_file_name_comma_quote_and_line_break_removed()
{
	pg_assert_same('attachment; filename="ab.zip"', pg_http_content_disposition("a\"\r\nb.zip", false));
	pg_assert_same('inline; filename="ab.jpg"', pg_http_content_disposition('a\\b.jpg', true));
}

// A name that is not plain ASCII (stored before 2026.4.1) goes beside the
// quoted one in UTF-8 (RFC 8187), which browsers prefer for Save as.
function test_file_name_comma_unicode_name_beside_the_ascii_one()
{
	pg_assert_same("inline; filename=\"_zet, 2026.pdf\"; filename*=UTF-8''%C3%96zet%2C%202026.pdf", pg_http_content_disposition('Özet, 2026.pdf', true));
	pg_assert_same('attachment; filename="report.pdf"', pg_http_content_disposition('report.pdf', false), 'a plain name has no second form');
}

// get_file.php builds no Content-Disposition of its own: the comma rule is in
// one place for the local answer and the object store's.
function test_file_name_comma_get_file_uses_the_one_builder()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');
	$headers = array();

	foreach (preg_split('/\R/', $source) as $line) {
		if ((stripos($line, 'header(') !== false) && (stripos($line, 'content-disposition') !== false)) {
			$headers[] = trim($line);
		}
	}

	pg_assert_true(count($headers) >= 2, 'the inline and the attachment answer');

	foreach ($headers as $line) {
		pg_assert_contains('pg_http_content_disposition(', $line, $line);
	}

	pg_assert_same(pg_http_content_disposition('a,b.jpg', true), pg_file_serve_disposition('a,b.jpg', true), 'the object store answer');
	pg_assert_same('attachment; filename="a,b.svg"', pg_file_serve_disposition('a,b.svg', true), 'an active type still downloads from the store');
}
