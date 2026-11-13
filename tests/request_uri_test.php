<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_request_uri_from_server() (includes/fn/request_scheme.php):
 * the request's address as get_request_uri(), router_get_request_url() and
 * the prototype's Secure Mode redirect read it. X-Rewrite-URL counts only on
 * IIS, where ISAPI_Rewrite sets it; elsewhere it is a header any client may
 * send.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_request_uri_rewrite_header_only_on_iis()
{
	$header = array('HTTP_X_REWRITE_URL' => '/other-page', 'REQUEST_URI' => '/');

	pg_assert_same('/other-page', pg_request_uri_from_server($header + array('SERVER_SOFTWARE' => 'Microsoft-IIS/10.0')), 'IIS with ISAPI_Rewrite');
	pg_assert_same('/', pg_request_uri_from_server($header + array('SERVER_SOFTWARE' => 'Apache/2.4.62 (Debian)')), 'Apache');
	pg_assert_same('/', pg_request_uri_from_server($header + array('SERVER_SOFTWARE' => 'nginx/1.27.0')), 'nginx');
	pg_assert_same('/', pg_request_uri_from_server($header), 'no server software');
	pg_assert_same('/', pg_request_uri_from_server(array('HTTP_X_REWRITE_URL' => '', 'REQUEST_URI' => '/', 'SERVER_SOFTWARE' => 'Microsoft-IIS/10.0')), 'an empty header on IIS');
}

function test_request_uri_without_header()
{
	pg_assert_same('/about?x=1', pg_request_uri_from_server(array('REQUEST_URI' => '/about?x=1', 'SERVER_SOFTWARE' => 'Microsoft-IIS/10.0')), 'IIS');
	pg_assert_same('/about', pg_request_uri_from_server(array('REQUEST_URI' => '/about', 'SERVER_SOFTWARE' => 'Apache')), 'Apache');
	pg_assert_same('/p.php?a=1', pg_request_uri_from_server(array('PHP_SELF' => '/p.php', 'QUERY_STRING' => 'a=1')), 'no REQUEST_URI');
	pg_assert_same('/p.php', pg_request_uri_from_server(array('PHP_SELF' => '/p.php', 'QUERY_STRING' => '')), 'no query string');
	pg_assert_same('', pg_request_uri_from_server(array()), 'nothing at all');
}

// IIS hands the path over in its ANSI code page (Windows-1254 on a Turkish
// server): /pg-ÇĞİÖŞÜ arrives as the bytes C7 D0 DD D6 DE DC. Read back as
// UTF-8, a link built from the address (urlencode() of a send_to) names the
// page again instead of Latin-1 "ÇÐÝÖÞÜ", a 404.
function test_request_uri_ansi_path_read_as_utf8()
{
	$iis = array('SERVER_SOFTWARE' => 'Microsoft-IIS/10.0');

	pg_assert_same('/pg-test_ÇĞİÖŞÜ_çğıöşü', pg_request_uri_from_server($iis + array('REQUEST_URI' => "/pg-test_\xC7\xD0\xDD\xD6\xDE\xDC_\xE7\xF0\xFD\xF6\xFE\xFC")));
	pg_assert_same('/çay?x=%C4%9F&y=1', pg_request_uri_from_server($iis + array('REQUEST_URI' => "/\xE7ay?x=%C4%9F&y=1")), 'the query string is left as it came');
	pg_assert_same('/ğ?q=ş', pg_request_uri_from_server($iis + array('REQUEST_URI' => "/\xF0?q=ş")), 'each part read on its own: UTF-8 in the query stays');
	pg_assert_same('/çay', pg_request_uri_from_server($iis + array('HTTP_X_REWRITE_URL' => "/\xE7ay", 'REQUEST_URI' => '/')), 'the rewrite header too');
	pg_assert_same('/ğ.php?a=1', pg_request_uri_from_server(array('PHP_SELF' => "/\xF0.php", 'QUERY_STRING' => 'a=1')), 'PHP_SELF too');
	pg_assert_same('%2Fpg-%C4%9F', urlencode(pg_request_uri_from_server($iis + array('REQUEST_URI' => "/pg-\xF0"))), 'a send_to names the page in UTF-8');
}

function test_request_uri_utf8_keeps_what_is_utf8()
{
	pg_assert_same('/pg-ğış?a=b', pg_request_uri_utf8('/pg-ğış?a=b'), 'UTF-8 stays as it is');
	pg_assert_same('/pg-%C4%9F', pg_request_uri_utf8('/pg-%C4%9F'), 'a percent-encoded path stays as it is');
	pg_assert_same('', pg_request_uri_utf8(''));
	pg_assert_same('/a??b', pg_request_uri_utf8('/a??b'), 'only the first question mark splits');
	pg_assert_same('ğ', pg_request_text_utf8("\xF0"), 'a piece read from the Turkish code page');
	pg_assert_same('ğ', pg_request_text_utf8('ğ'));
}

function test_uri_ascii_encodes_only_what_is_not_printable_ascii()
{
	pg_assert_same('/pg-%C4%9F%C5%9F?x=%C4%B1%201#%C3%A7', pg_uri_ascii('/pg-ğş?x=ı 1#ç'));
	pg_assert_same('/pg-%C4%9F?a[]=1&b=%2F#c', pg_uri_ascii('/pg-%C4%9F?a[]=1&b=%2F#c'), 'an encoded address is kept');
	pg_assert_same('%0D%0A%09%7F', pg_uri_ascii("\r\n\t\x7F"), 'controls are encoded, never passed on');
	pg_assert_same('%F0', pg_uri_ascii("\xF0"), 'a byte that is not UTF-8 too');
	pg_assert_same('', pg_uri_ascii(''));
}

// The Secure Mode redirects send the request's own address, which is UTF-8
// (pg_request_uri_from_server()), so they write it for the header too.
function test_secure_mode_redirects_write_the_address_in_ascii()
{
	$init = (string) file_get_contents(PG_FUNCTIONS_DIR . '/init.php');
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');

	pg_assert_same(2, substr_count($init, ". pg_uri_ascii(defined('PG_SECURE_MODE_REDIRECT_URL') ? PG_SECURE_MODE_REDIRECT_URL : REQUEST_URL), true, 302);"), 'init.php, both directions');
	pg_assert_same(2, substr_count($file, '. pg_uri_ascii(get_request_uri()), true, 302);'), 'get_file.php, both directions');
}

// Every reader of the address goes through the one choice.
function test_request_uri_callers_share_the_choice()
{
	$root = PG_FUNCTIONS_DIR;

	foreach (array('includes/fn/forms.php', 'get_file.php', 'router.php', 'includes/fn/prototype_links.php') as $file) {
		$source = (string) file_get_contents($root . '/' . $file);
		pg_assert_contains('pg_request_uri_from_server(', $source, $file);
		pg_assert_true(strpos($source, "return \$_SERVER['HTTP_X_REWRITE_URL']") === false, $file . ' reads the header itself');
	}
}
