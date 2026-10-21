<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a page's own canonical address and robots directives in
 * includes/fn/seo.php: pg_page_canonical_url_check(), pg_page_canonical_href(),
 * pg_page_max_snippet_check(), pg_page_robots_content() and pg_page_own_url().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Nothing, or only white space, leaves the automatic canonical address.
function test_page_canonical_empty_is_none()
{
	pg_assert_same(array('ok' => true, 'value' => '', 'error' => ''), pg_page_canonical_url_check(''));
	pg_assert_same(array('ok' => true, 'value' => '', 'error' => ''), pg_page_canonical_url_check("  \t\n"));
	pg_assert_same(array('ok' => true, 'value' => '', 'error' => ''), pg_page_canonical_url_check(null));
}

// A full http(s) address, on this site or another, is kept trimmed as typed.
function test_page_canonical_full_addresses_are_kept()
{
	pg_assert_same('https://example.com/blog/post?id=4', pg_page_canonical_url_check("  https://example.com/blog/post?id=4 \n")['value']);
	pg_assert_true(pg_page_canonical_url_check('http://example.com')['ok'], 'http, no path');
	pg_assert_true(pg_page_canonical_url_check('HTTPS://Example.com/A')['ok'], 'upper case scheme');
	pg_assert_true(pg_page_canonical_url_check('https://örnek.com.tr/hakkımızda')['ok'], 'letters outside ASCII');
	pg_assert_true(pg_page_canonical_url_check('https://example.com:8443/x')['ok'], 'port');
}

// A path on this site starts with a single slash.
function test_page_canonical_site_paths_are_kept()
{
	pg_assert_same('/urunler/kalem', pg_page_canonical_url_check('/urunler/kalem')['value']);
	pg_assert_true(pg_page_canonical_url_check('/')['ok'], 'site root');
	pg_assert_same('scheme', pg_page_canonical_url_check('//example.com/x')['error'], 'scheme-less host');
}

// Relative addresses, other schemes and hosts missing are refused.
function test_page_canonical_other_forms_are_refused()
{
	pg_assert_same('scheme', pg_page_canonical_url_check('about-us')['error'], 'relative');
	pg_assert_same('scheme', pg_page_canonical_url_check('example.com/about')['error'], 'no scheme');
	pg_assert_same('scheme', pg_page_canonical_url_check('javascript:alert(1)')['error'], 'javascript');
	pg_assert_same('scheme', pg_page_canonical_url_check('ftp://example.com/x')['error'], 'ftp');
	pg_assert_same('scheme', pg_page_canonical_url_check('https:///x')['error'], 'no host');
	pg_assert_same('scheme', pg_page_canonical_url_check('https://')['error'], 'scheme only');
}

// White space inside, control characters, quotes and brackets are refused.
function test_page_canonical_unsafe_characters_are_refused()
{
	pg_assert_same('chars', pg_page_canonical_url_check('https://example.com/a b')['error'], 'space');
	pg_assert_same('chars', pg_page_canonical_url_check("https://example.com/a\tb")['error'], 'tab');
	pg_assert_same('chars', pg_page_canonical_url_check('https://example.com/"><script>')['error'], 'quote and bracket');
	pg_assert_same('chars', pg_page_canonical_url_check('/a\\b')['error'], 'backslash');
	pg_assert_same('chars', pg_page_canonical_url_check("/a\x00b")['error'], 'nul');
}

// More than 2000 bytes is refused; exactly 2000 passes.
function test_page_canonical_length_limit()
{
	$base = 'https://example.com/';
	pg_assert_true(pg_page_canonical_url_check($base . str_repeat('a', 2000 - strlen($base)))['ok'], '2000');
	pg_assert_same('size', pg_page_canonical_url_check($base . str_repeat('a', 2001 - strlen($base)))['error'], '2001');
}

// The address written: a site path after the scheme and host, letters
// outside ASCII after the host percent-encoded, the host left as typed.
function test_page_canonical_href_resolves_and_encodes()
{
	pg_assert_same('https://site.test/urunler', pg_page_canonical_href('/urunler', 'https://site.test'));
	pg_assert_same('https://site.test/', pg_page_canonical_href('/', 'https://site.test/'));
	pg_assert_same('https://example.com/a?b=1', pg_page_canonical_href('https://example.com/a?b=1', 'https://site.test'));
	pg_assert_same('https://site.test/hakk%C4%B1m%C4%B1zda', pg_page_canonical_href('/hakkımızda', 'https://site.test'));
	pg_assert_same('https://örnek.com/%C3%BCr%C3%BCn?q=%C3%A7', pg_page_canonical_href('https://örnek.com/ürün?q=ç', 'https://site.test'));
	pg_assert_same('https://example.com', pg_page_canonical_href('https://example.com', 'https://site.test'));
}

// Nothing stored, or something that no longer passes the check, gives ''.
function test_page_canonical_href_none_or_broken()
{
	pg_assert_same('', pg_page_canonical_href(null, 'https://site.test'));
	pg_assert_same('', pg_page_canonical_href('', 'https://site.test'));
	pg_assert_same('', pg_page_canonical_href('javascript:alert(1)', 'https://site.test'));
	pg_assert_same('', pg_page_canonical_href('about us', 'https://site.test'));
}

// Empty is no directive; -1, 0 and a positive number up to the limit pass.
function test_page_max_snippet_values()
{
	pg_assert_same(array('ok' => true, 'value' => null, 'error' => ''), pg_page_max_snippet_check(''));
	pg_assert_same(array('ok' => true, 'value' => null, 'error' => ''), pg_page_max_snippet_check(null));
	pg_assert_same(array('ok' => true, 'value' => null, 'error' => ''), pg_page_max_snippet_check('  '));
	pg_assert_same(-1, pg_page_max_snippet_check('-1')['value']);
	pg_assert_same(0, pg_page_max_snippet_check('0')['value']);
	pg_assert_same(160, pg_page_max_snippet_check(' 160 ')['value']);
	pg_assert_same(160, pg_page_max_snippet_check(160)['value'], 'an int, as a row may hold it');
	pg_assert_same(pg_page_max_snippet_limit(), pg_page_max_snippet_check((string) pg_page_max_snippet_limit())['value']);
}

// Anything else is refused with the one reason.
function test_page_max_snippet_refused()
{
	foreach (array('-2', '1.5', '1,5', '+5', '05', 'abc', '10001', '99999', '100000', '-0') as $raw) {
		pg_assert_same(array('ok' => false, 'value' => null, 'error' => 'number'), pg_page_max_snippet_check($raw), $raw);
	}

	pg_assert_false(pg_page_max_snippet_check(array('5'))['ok'], 'array');
	pg_assert_false(pg_page_max_snippet_check(1.5)['ok'], 'float');
}

// A page closed to search engines says only that.
function test_page_robots_content_noindex_wins()
{
	pg_assert_same('noindex, follow', pg_page_robots_content(true, false, true, '50'));
	pg_assert_same('noindex, nofollow', pg_page_robots_content(1, 1, 0, null));
}

// An indexed page writes the directives it asks for, and nothing otherwise.
function test_page_robots_content_directives()
{
	pg_assert_same('', pg_page_robots_content(false, false, false, null));
	pg_assert_same('', pg_page_robots_content(0, 1, 0, ''), 'nofollow alone is never written');
	pg_assert_same('noarchive', pg_page_robots_content(0, 0, 1, null));
	pg_assert_same('max-snippet:0', pg_page_robots_content(0, 0, 0, '0'));
	pg_assert_same('noarchive, max-snippet:-1', pg_page_robots_content(0, 0, 1, '-1'));
	pg_assert_same('max-snippet:160', pg_page_robots_content(0, 0, 0, 160));
	pg_assert_same('noarchive', pg_page_robots_content(0, 0, 1, '12345678'), 'a stored value out of range is left out');
}

// The page's own address is kept until the next page drawn replaces it.
function test_page_own_url_register()
{
	pg_assert_same('https://site.test/a', pg_page_own_url('https://site.test/a'));
	pg_assert_same('https://site.test/a', pg_page_own_url());
	pg_assert_same('', pg_page_own_url(''));
	pg_assert_same('', pg_page_own_url());
}
