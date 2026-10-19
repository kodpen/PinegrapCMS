<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for url_to_absolute.php, the RFC 3986 resolver the ZIP importer uses
 * to turn relative references in imported HTML into the URLs of the
 * imported items (import_zip.php, update_imported_items.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/url_to_absolute.php');

// The pattern compiles under PCRE2 (PHP 7.3+): an absolute URL is split
// into its parts instead of the whole call returning false.
function test_url_to_absolute_split_url_parses_absolute_url()
{
	pg_assert_same(
		array(
			'scheme' => 'https',
			'host' => 'example.com',
			'port' => '8080',
			'path' => '/a/b.html',
			'query' => 'x=1',
			'fragment' => 'top',
		),
		split_url('https://example.com:8080/a/b.html?x=1#top'));
}

// A host name may contain dots, digits, hyphens and percent signs.
function test_url_to_absolute_split_url_reads_hyphenated_host()
{
	$parts = split_url('http://my-site.example-2.com/');

	pg_assert_same('my-site.example-2.com', $parts['host']);
}

// A scheme takes letters, digits, "+", "-" and "." only (RFC 3986 3.1).
function test_url_to_absolute_split_url_scheme_characters()
{
	$parts = split_url('svn+ssh.x-y://host/p');

	pg_assert_same('svn+ssh.x-y', $parts['scheme']);
	pg_assert_same('host', $parts['host']);
}

// The normal examples of RFC 3986 section 5.4.1 against its base URL.
function test_url_to_absolute_rfc3986_normal_examples()
{
	$base = 'http://a/b/c/d;p?q';

	$cases = array(
		'g:h' => 'g:h',
		'g' => 'http://a/b/c/g',
		'./g' => 'http://a/b/c/g',
		'g/' => 'http://a/b/c/g/',
		'/g' => 'http://a/g',
		'//g' => 'http://g',
		'?y' => 'http://a/b/c/d;p?y',
		'g?y' => 'http://a/b/c/g?y',
		'#s' => 'http://a/b/c/d;p?q#s',
		'g#s' => 'http://a/b/c/g#s',
		'g?y#s' => 'http://a/b/c/g?y#s',
		';x' => 'http://a/b/c/;x',
		'g;x' => 'http://a/b/c/g;x',
		'g;x?y#s' => 'http://a/b/c/g;x?y#s',
		'' => 'http://a/b/c/d;p?q',
		'.' => 'http://a/b/c/',
		'./' => 'http://a/b/c/',
		'..' => 'http://a/b/',
		'../' => 'http://a/b/',
		'../g' => 'http://a/b/g',
		'../..' => 'http://a/',
		'../../' => 'http://a/',
		'../../g' => 'http://a/g',
	);

	foreach ($cases as $relative => $expected) {
		pg_assert_same($expected, url_to_absolute($base, $relative), "'" . $relative . "'");
	}
}

// The abnormal examples of RFC 3986 section 5.4.2: ".." above the root,
// dots that are part of a segment name, and dot segments in the query or
// fragment, which are left alone.
function test_url_to_absolute_rfc3986_abnormal_examples()
{
	$base = 'http://a/b/c/d;p?q';

	$cases = array(
		'../../../g' => 'http://a/g',
		'../../../../g' => 'http://a/g',
		'/./g' => 'http://a/g',
		'/../g' => 'http://a/g',
		'g.' => 'http://a/b/c/g.',
		'.g' => 'http://a/b/c/.g',
		'g..' => 'http://a/b/c/g..',
		'..g' => 'http://a/b/c/..g',
		'./../g' => 'http://a/b/g',
		'./g/.' => 'http://a/b/c/g/',
		'g/./h' => 'http://a/b/c/g/h',
		'g/../h' => 'http://a/b/c/h',
		'g;x=1/./y' => 'http://a/b/c/g;x=1/y',
		'g;x=1/../y' => 'http://a/b/c/y',
		'g?y/./x' => 'http://a/b/c/g?y/./x',
		'g?y/../x' => 'http://a/b/c/g?y/../x',
		'g#s/./x' => 'http://a/b/c/g#s/./x',
		'g#s/../x' => 'http://a/b/c/g#s/../x',
		'http:g' => 'http:g',
	);

	foreach ($cases as $relative => $expected) {
		pg_assert_same($expected, url_to_absolute($base, $relative), "'" . $relative . "'");
	}
}

// The ZIP importer's own shape: every archive entry gets a base of
// http://example.com/<entry name>, and its references resolve against it.
function test_url_to_absolute_resolves_import_zip_references()
{
	$base = 'http://example.com/pages/about.html';

	pg_assert_same('http://example.com/pages/images/logo.png', url_to_absolute($base, 'images/logo.png'));
	pg_assert_same('http://example.com/css/site.css?v=2', url_to_absolute($base, '../css/site.css?v=2'));
	pg_assert_same('http://example.com/js/app.js', url_to_absolute($base, '/js/app.js'));
	pg_assert_same('https://cdn.example.org/lib.js', url_to_absolute($base, 'https://cdn.example.org/lib.js'));
	pg_assert_same('http://cdn.example.org/lib.js', url_to_absolute($base, '//cdn.example.org/lib.js'));
}

// Entry names often carry spaces or Turkish letters as they are in the
// archive; the reference written the same way resolves to the same key.
function test_url_to_absolute_keeps_spaces_and_utf8_in_path()
{
	$base = 'http://example.com/index.html';

	pg_assert_same('http://example.com/görseller/ürün 1.jpg', url_to_absolute($base, 'görseller/ürün 1.jpg'));
	pg_assert_same('http://example.com/a/ç.png', url_to_absolute('http://example.com/a/b/ş.html', '../ç.png'));
}

// A name that is not valid UTF-8 (an archive made on a Latin-1 system)
// resolves byte for byte instead of stopping the import.
function test_url_to_absolute_keeps_non_utf8_bytes_in_path()
{
	pg_assert_same("http://example.com/g\xf6rsel.png", url_to_absolute('http://example.com/a/b.html', "../g\xf6rsel.png"));
}

// A base with no path (a <base href="http://example.com"> tag) resolves a
// relative path from the root, without an undefined-index warning.
function test_url_to_absolute_base_without_path()
{
	pg_assert_same('http://example.com/img/a.png', url_to_absolute('http://example.com', 'img/a.png'));
	pg_assert_same('http://example.com', url_to_absolute('http://example.com', ''));
}

// The base keeps its port and user info; IPv4 and IPv6 hosts are kept.
function test_url_to_absolute_keeps_base_authority()
{
	pg_assert_same('https://ex.com:8080/a/c', url_to_absolute('https://ex.com:8080/a/b', 'c'));
	pg_assert_same('http://user:pw@example.com/a/c', url_to_absolute('http://user:pw@example.com/a/b', 'c'));
	pg_assert_same('http://192.168.0.1/c', url_to_absolute('http://192.168.0.1/a/b', '../c'));
	pg_assert_same('http://[::1]/a/c', url_to_absolute('http://[::1]/a/b', 'c'));
}

// A base that is not absolute cannot anchor a relative reference.
function test_url_to_absolute_refuses_relative_base()
{
	pg_assert_false(url_to_absolute('/a/b.html', 'c.html'));
}

// The trailing slash is kept on PHP 8 (mb_strrpos() takes the encoding as
// its fourth argument; the third is the offset).
function test_url_to_absolute_remove_dot_segments_keeps_trailing_slash()
{
	pg_assert_same('/a/c/', url_remove_dot_segments('/a/b/../c/'));
	pg_assert_same('/a/c', url_remove_dot_segments('/a/b/../c'));
	pg_assert_same('/', url_remove_dot_segments('/'));
}
