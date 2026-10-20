<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_safe_back_url() (includes/fn/output.php), the check behind the
 * Cancel and exit buttons that take an address from the request: what a
 * browser follows to another host must fall back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The forms the screens hand it keep working.
function test_back_url_keeps_own_addresses()
{
	$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
	$_SERVER['HTTP_HOST'] = 'site.example';

	pg_assert_same('view_pages.php?id=5#x', pg_safe_back_url('view_pages.php?id=5#x', 'F'), 'a screen of the panel');
	pg_assert_same('/dukkan/urun-1?q=a\\b', pg_safe_back_url('/dukkan/urun-1?q=a\\b', 'F'), 'a path; a backslash after the start is only a character');
	pg_assert_same('https://site.example/hesabim', pg_safe_back_url('https://site.example/hesabim', 'F'), 'the own host');

	if ($host === null) {
		unset($_SERVER['HTTP_HOST']);
	} else {
		$_SERVER['HTTP_HOST'] = $host;
	}
}

// A browser reads a backslash as a slash and drops tabs and line breaks, so
// these lead to evil.com and fall back.
function test_back_url_refuses_what_a_browser_reads_as_another_host()
{
	$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
	$_SERVER['HTTP_HOST'] = 'site.example';

	pg_assert_same('F', pg_safe_back_url('/\\evil.com', 'F'), '/\\host');
	pg_assert_same('F', pg_safe_back_url('/\\/evil.com', 'F'), '/\\/host');
	pg_assert_same('F', pg_safe_back_url('\\\\evil.com', 'F'), '\\\\host');
	pg_assert_same('F', pg_safe_back_url('https://evil.com\\@site.example/', 'F'), 'userinfo hiding the host behind a backslash');
	pg_assert_same('F', pg_safe_back_url("/\t/evil.com", 'F'), 'a tab inside //');
	pg_assert_same('F', pg_safe_back_url("java\tscript:alert(1)", 'F'), 'a tab inside the scheme');
	pg_assert_same('F', pg_safe_back_url('//evil.com', 'F'), 'protocol-relative');
	pg_assert_same('F', pg_safe_back_url('https://site.example@evil.com/', 'F'), 'userinfo');

	if ($host === null) {
		unset($_SERVER['HTTP_HOST']);
	} else {
		$_SERVER['HTTP_HOST'] = $host;
	}
}
