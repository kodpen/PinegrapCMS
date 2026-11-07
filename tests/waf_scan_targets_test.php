<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the values the signature scan inspects (waf_collect_targets(),
 * waf.php): the request line, the user agent and the referrer are scanned
 * whatever number of parameters and cookies came with them, so padding a
 * request up to the value cap cannot hide a payload in the path; and each of
 * the query string, the body and the cookies has its own cap, so padding one
 * cannot hide a payload in another.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// Collect the targets for a request with these globals, restoring them after.
function waf_scan_targets_test_collect(array $get, array $cookie, array $server)
{
	$saved = array($_GET, $_POST, $_COOKIE, $_SERVER);

	$_GET = $get;
	$_POST = array();
	$_COOKIE = $cookie;
	$_SERVER = array_merge($_SERVER, $server);

	$targets = waf_collect_targets();

	list($_GET, $_POST, $_COOKIE, $_SERVER) = $saved;

	return $targets;
}

// An ordinary request: the parameters and the fixed headers are all there.
function test_waf_collect_targets_scans_the_request_line()
{
	$targets = waf_scan_targets_test_collect(
		array('q' => 'shoes'),
		array(),
		array('REQUEST_URI' => '/search?q=shoes', 'HTTP_USER_AGENT' => 'Mozilla/5.0', 'HTTP_REFERER' => 'https://example.com/')
	);

	pg_assert_same('shoes', $targets['ARGS:q'] ?? null, 'the parameter');
	pg_assert_same('/search?q=shoes', $targets['REQUEST_URI'] ?? null, 'the request line');
	pg_assert_same('Mozilla/5.0', $targets['USER_AGENT'] ?? null, 'the user agent');
	pg_assert_same('https://example.com/', $targets['REFERER'] ?? null, 'the referrer');
}

// Cookies padded up to the value cap: the request line, the user agent and
// the referrer are still scanned.
function test_waf_collect_targets_keeps_the_request_line_past_the_cap()
{
	$cookie = array();

	for ($i = 0; $i < WAF_MAX_VALUES; $i++) {
		$cookie['c' . $i] = 'xyz';
	}

	$targets = waf_scan_targets_test_collect(
		array(),
		$cookie,
		array('REQUEST_URI' => '/x.php/../../etc/passwd', 'HTTP_USER_AGENT' => 'sqlmap/1.7', 'HTTP_REFERER' => 'https://example.com/')
	);

	pg_assert_same('/x.php/../../etc/passwd', $targets['REQUEST_URI'] ?? null, 'the request line');
	pg_assert_same('sqlmap/1.7', $targets['USER_AGENT'] ?? null, 'the user agent');
	pg_assert_same('https://example.com/', $targets['REFERER'] ?? null, 'the referrer');
}

// Each source has its own value cap: query parameters padded past the cap
// leave the body and the cookies to be scanned.
function test_waf_collect_targets_budget_per_source()
{
	$saved = array($_GET, $_POST, $_COOKIE);

	$_GET = array();

	for ($i = 0; $i < 300; $i++) {
		$_GET['aaa' . $i] = '1';
	}

	$_POST = array('comment' => '<script>alert(1)</script>');
	$_COOKIE = array('theme' => 'dark-mode');

	$targets = waf_collect_targets();

	list($_GET, $_POST, $_COOKIE) = $saved;

	pg_assert_same('<script>alert(1)</script>', $targets['BODY:comment'] ?? null, 'the body value past padded query parameters');
	pg_assert_same('dark-mode', $targets['COOKIE:theme'] ?? null, 'the cookie past padded query parameters');

	$args = 0;

	foreach (array_keys($targets) as $name) {
		if (strpos($name, 'ARGS:') === 0) {
			$args++;
		}
	}

	pg_assert_same(WAF_MAX_VALUES, $args, 'the query parameters still stop at the cap');
}
