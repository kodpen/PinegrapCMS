<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for convert_text_to_html() (includes/fn/mail.php), which prints a
 * visitor's comment, a form answer or an e-mail's text as HTML: every piece
 * of text is escaped, each address becomes one link, and nothing a visitor
 * typed is read as a regular expression replacement or as a placeholder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function convert_text_to_html_test_link($url)
{
	return '<a href="' . $url . '" target="_blank" rel="nofollow">' . $url . '</a>';
}

// Plain text is escaped and keeps its line breaks.
function test_convert_text_to_html_plain_text()
{
	pg_assert_same("a &lt;b&gt; &amp; &quot;c&quot;<br />\nd", convert_text_to_html("a <b> & \"c\"\nd"), 'escaped with line breaks');
}

// Addresses become links, and the character that ended each stays after it.
function test_convert_text_to_html_links()
{
	pg_assert_same(
		'See ' . convert_text_to_html_test_link('https://example.com/a?b=1&amp;c=2') . ', then ' . convert_text_to_html_test_link('http://x.org/y') . '. Done',
		convert_text_to_html('See https://example.com/a?b=1&c=2, then http://x.org/y. Done'),
		'two links');
	pg_assert_same('(' . convert_text_to_html_test_link('ftp://files.example.com/f') . ')', convert_text_to_html('(ftp://files.example.com/f)'), 'link in brackets');
	pg_assert_same('no link here: www.example.com', convert_text_to_html('no link here: www.example.com'), 'no scheme, no link');
}

// A $0 or \0 in an address is text: read as a back reference it wrote the
// placeholder into the first link's href, and the next link was then put
// inside that attribute, which left the visitor's second address as bare
// attributes of the tag (a script handler).
function test_convert_text_to_html_back_reference_is_text()
{
	$html = convert_text_to_html('See http://x.co/$0 and http://a.b/onmouseover=alert`1`//');

	pg_assert_same(
		'See ' . convert_text_to_html_test_link('http://x.co/$0') . ' and ' . convert_text_to_html_test_link('http://a.b/onmouseover=alert`1`//'),
		$html,
		'each address in its own link');
	pg_assert_same(2, substr_count($html, '<a '), 'two tags');

	pg_assert_same(convert_text_to_html_test_link('http://x.co/\\0'), convert_text_to_html('http://x.co/\\0'), 'backslash reference kept');
}

// Text that looks like the old placeholder is text, and a link stays where
// its address was.
function test_convert_text_to_html_placeholder_text()
{
	pg_assert_same(
		'^^^placeholder^^^ hi ' . convert_text_to_html_test_link('http://a.b/c'),
		convert_text_to_html('^^^placeholder^^^ hi http://a.b/c'),
		'placeholder text kept');
}

// Text around a link keeps its multibyte characters.
function test_convert_text_to_html_multibyte()
{
	pg_assert_same('Şu adres ' . convert_text_to_html_test_link('https://örnek.com.tr/ğ') . ' çok iyi', convert_text_to_html('Şu adres https://örnek.com.tr/ğ çok iyi'), 'multibyte text');
	pg_assert_same('', convert_text_to_html(''), 'empty');
}
