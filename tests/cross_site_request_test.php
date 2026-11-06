<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_request_cross_site() in includes/fn/auth.php: whether a
 * browser sent a request from a page of another site. api.php and
 * booking_slots.php refuse such a request, because their session-only
 * actions carry no form token and the session cookie may be SameSite=None
 * once a checkout has re-issued it for a payment gateway's return.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Sec-Fetch-Site decides when the browser sends it.
function test_cross_site_request_reads_sec_fetch_site()
{
	pg_assert_true(pg_request_cross_site(array('HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_HOST' => 'shop.example')), 'cross-site');
	pg_assert_true(pg_request_cross_site(array('HTTP_SEC_FETCH_SITE' => 'Cross-Site')), 'case');
	pg_assert_false(pg_request_cross_site(array('HTTP_SEC_FETCH_SITE' => 'same-origin')), 'same-origin');
	pg_assert_false(pg_request_cross_site(array('HTTP_SEC_FETCH_SITE' => 'same-site')), 'same-site');
	pg_assert_false(pg_request_cross_site(array('HTTP_SEC_FETCH_SITE' => 'none')), 'typed address');

	// The header wins over an Origin that would say otherwise.
	pg_assert_true(pg_request_cross_site(array('HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://shop.example', 'HTTP_HOST' => 'shop.example')), 'header first');
}

// Without Sec-Fetch-Site, an Origin naming another host is cross-site.
function test_cross_site_request_falls_back_to_origin()
{
	pg_assert_true(pg_request_cross_site(array('HTTP_ORIGIN' => 'https://evil.example', 'HTTP_HOST' => 'shop.example')), 'other host');
	pg_assert_true(pg_request_cross_site(array('HTTP_ORIGIN' => 'https://shop.example:8443', 'HTTP_HOST' => 'shop.example')), 'other port');
	pg_assert_true(pg_request_cross_site(array('HTTP_ORIGIN' => 'null', 'HTTP_HOST' => 'shop.example')), 'opaque origin');
	pg_assert_false(pg_request_cross_site(array('HTTP_ORIGIN' => 'https://Shop.Example', 'HTTP_HOST' => 'shop.example')), 'same host');
	pg_assert_false(pg_request_cross_site(array('HTTP_ORIGIN' => 'https://shop.example:8443', 'HTTP_HOST' => 'shop.example:8443')), 'same host and port');
}

// A request with neither header (a script on a server, an old client) is
// not a browser's cross-site request: nothing to refuse it for.
function test_cross_site_request_without_headers_is_not_cross_site()
{
	pg_assert_false(pg_request_cross_site(array()), 'no headers');
	pg_assert_false(pg_request_cross_site(array('HTTP_HOST' => 'shop.example')), 'host only');
	pg_assert_false(pg_request_cross_site(array('HTTP_SEC_FETCH_SITE' => array('cross-site'), 'HTTP_ORIGIN' => array('x'))), 'not strings');
}

// Both token-less JSON entries refuse a cross-site request before acting:
// api.php before any action runs (a name-and-password sign-in excepted),
// booking_slots.php before its JSON actions are read.
function test_cross_site_request_refused_by_token_less_json_endpoints()
{
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/api.php');
	$check = strpos($api, "if (!defined('API_USERNAME') && pg_request_cross_site(\$_SERVER))");
	pg_assert_true($check !== false, 'api.php checks');
	pg_assert_true(($check !== false) && ($check < strpos($api, 'pg_panel_dispatch($action, $request);')), 'api.php checks before dispatch');

	$slots = (string) file_get_contents(PG_FUNCTIONS_DIR . '/booking_slots.php');
	$check = strpos($slots, 'if (pg_request_cross_site($_SERVER))');
	pg_assert_true($check !== false, 'booking_slots.php checks');
	pg_assert_true(($check !== false) && ($check < strpos($slots, "\$request = json_decode((string) file_get_contents('php://input'")), 'booking_slots.php checks before reading the body');
}
