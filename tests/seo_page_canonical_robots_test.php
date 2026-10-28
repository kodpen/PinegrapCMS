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

// Large image previews (config.max_image_preview_large): last, on every
// indexed page, never on a page out of the index.
function test_seo_page_robots_large_image_preview()
{
	pg_assert_same('max-image-preview:large', pg_page_robots_content(0, 0, 0, null, true));
	pg_assert_same('noarchive, max-snippet:160, max-image-preview:large', pg_page_robots_content(0, 0, 1, 160, true));
	pg_assert_same('noindex, follow', pg_page_robots_content(1, 0, 0, null, true));
	pg_assert_same('noindex, nofollow', pg_page_robots_content(1, 1, 1, '50', true));
	pg_assert_same('', pg_page_robots_content(0, 0, 0, null, false));

	// Without the argument the site setting decides; the test runner has none.
	pg_assert_same(defined('MAX_IMAGE_PREVIEW_LARGE') && MAX_IMAGE_PREVIEW_LARGE ? 'max-image-preview:large' : '', pg_page_robots_content(0, 0, 0, null));
}

// The setting is read from config with a fallback, written by the SEO card
// only once its column is there, and switched on for a new starter site.
function test_seo_page_robots_large_image_preview_setting()
{
	$init = (string) file_get_contents(PG_FUNCTIONS_DIR . '/init.php');
	pg_assert_contains("define('MAX_IMAGE_PREVIEW_LARGE', (int) (\$row['max_image_preview_large'] ?? 0) == 1);", $init);

	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/seo.save.php');
	pg_assert_contains("pg_schema_has('config', 'max_image_preview_large')", $save);
	pg_assert_contains('$sql_seo_switch_fields', $save);

	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_contains("upgrade_2026_11_0_max_image_preview();", $migration);
	pg_assert_contains("install_add_column('config', 'max_image_preview_large', \"TINYINT(1) UNSIGNED NOT NULL DEFAULT 0\");", $migration);

	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_contains("if ((get_starter_site_language(\$install_directory_path) !== '') && (install_column_exists('config', 'max_image_preview_large'))) {", $install);
	pg_assert_contains('db("UPDATE config SET max_image_preview_large = 1");', $install);
}

// The page's own address is kept until the next page drawn replaces it.
function test_page_own_url_register()
{
	pg_assert_same('https://site.test/a', pg_page_own_url('https://site.test/a'));
	pg_assert_same('https://site.test/a', pg_page_own_url());
	pg_assert_same('', pg_page_own_url(''));
	pg_assert_same('', pg_page_own_url());
}

// Addresses pasted from a document or a web page bring Unicode white space
// and invisible characters along: at the ends they are trimmed, inside they
// are refused, by the server and by the designer's live check alike. A port
// is checked the same on both sides.
function seo_canonical_unicode_cases()
{
	return array(
		array("https://example.com/urun\u{00A0}", 'ok', 'https://example.com/urun'),
		array("\u{00A0}https://example.com/urun", 'ok', 'https://example.com/urun'),
		array("\u{FEFF}https://example.com/x", 'ok', 'https://example.com/x'),
		array("https://example.com/x\u{200B}", 'ok', 'https://example.com/x'),
		array("\u{3000}/urunler/kalem\u{2028}", 'ok', '/urunler/kalem'),
		array("\u{00A0}\u{FEFF}", '', ''),
		array("https://example.com/a\u{00A0}b", 'chars', ''),
		array("https://example.com/a\u{2028}b", 'chars', ''),
		array("https://example.com/a\u{3000}b", 'chars', ''),
		array("https://example.com/a\u{200B}b", 'chars', ''),
		array("https://exa\u{202E}mple.com/", 'chars', ''),
		array('https://örnek.com.tr/hakkımızda', 'ok', 'https://örnek.com.tr/hakkımızda'),
		array('https://host.com:99999/', 'scheme', ''),
		array('https://host.com:65536/', 'scheme', ''),
		array('https://a:b/', 'scheme', ''),
		array('https://host.com:65535/', 'ok', 'https://host.com:65535/'),
		array('https://host.com:/', 'ok', 'https://host.com:/'),
		array('https://[::1]:8080/', 'ok', 'https://[::1]:8080/'),
		array('https://u:p@host.com/', 'ok', 'https://u:p@host.com/'),
		array('/a b', 'chars', ''),
		array('//x', 'scheme', ''),
	);
}

function test_page_canonical_unicode_space_trimmed_or_refused()
{
	foreach (seo_canonical_unicode_cases() as $case) {
		$check = pg_page_canonical_url_check($case[0]);
		$got = $check['ok'] ? (($check['value'] === '') ? '' : 'ok') : $check['error'];

		pg_assert_same($case[1], $got, json_encode($case[0]));

		if ($case[1] === 'ok') {
			pg_assert_same($case[2], $check['value'], 'stored as ' . json_encode($case[2]));
		}
	}

	pg_assert_same('chars', pg_page_canonical_url_check("https://example.com/\xC3")['error'], 'not UTF-8');
}

// The designer's live check (_sdCanonicalCheck()) answers as the server does.
function test_page_canonical_designer_check_agrees()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, 'function _sdCanonicalCheck(');
	$to = strpos($source, 'function _sdMaxSnippetCheck(', $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the check');

	$inputs = array_map(function ($case) { return $case[0]; }, seo_canonical_unicode_cases());
	$file = tempnam(sys_get_temp_dir(), 'pgcanon');
	file_put_contents($file, substr($source, $from, $to - $from) . "\nconsole.log(JSON.stringify(" . json_encode($inputs) . ".map(_sdCanonicalCheck)));");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));
	$answers = json_decode(implode("\n", $out), true);

	foreach (seo_canonical_unicode_cases() as $i => $case) {
		pg_assert_same($case[1], $answers[$i] ?? null, 'designer: ' . json_encode($case[0]));
	}
}
