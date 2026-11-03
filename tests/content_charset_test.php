<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the charset read from an HTML e-mail body (get_charset() in
 * includes/fn/content.php), which becomes the message's CharSet.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The usual declaration on its own line.
function test_content_charset_alone()
{
	pg_assert_same('utf-8', get_charset("<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n<title>x</title>"), 'alone');
	pg_assert_same('iso-8859-9', get_charset("<meta http-equiv='Content-Type' content='text/html; charset=ISO-8859-9' />"), 'single quotes');
}

// Further quoted attributes on the same line stay out of the charset.
function test_content_charset_rest_of_line_ignored()
{
	$html = '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><meta name="viewport" content="width=device-width"><title>Hi</title></head>';

	pg_assert_same('utf-8', get_charset($html), 'one-line head');
}

// No declaration, or one without a charset, gives ''.
function test_content_charset_missing()
{
	pg_assert_same('', get_charset('<html><body>No meta</body></html>'), 'none');
	pg_assert_same('', get_charset('<meta http-equiv="refresh" content="5"><p>"quoted"</p>'), 'other http-equiv');
}
