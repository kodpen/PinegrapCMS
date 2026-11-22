<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's browser origin allow-list
 * (includes/api/cors.php): which origins a list takes, the match of a
 * request's Origin, the headers a listed origin gets and the token routes'
 * refusal of an unlisted one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/cors.php');

// An https origin is kept in lower case, with a port only when it is not 443.
function test_api_cors_origin_normalize_https()
{
	pg_assert_same('https://app.example.com', api_cors_origin_normalize('https://app.example.com'), 'plain');
	pg_assert_same('https://app.example.com', api_cors_origin_normalize('  HTTPS://App.Example.COM  '), 'case and space');
	pg_assert_same('https://app.example.com', api_cors_origin_normalize('https://app.example.com:443'), 'default port');
	pg_assert_same('https://app.example.com:8443', api_cors_origin_normalize('https://app.example.com:8443'), 'own port');
	pg_assert_same('https://localhost', api_cors_origin_normalize('https://localhost'), 'the Android shell');
}

// The fixed origins of a native shell are taken as they are.
function test_api_cors_origin_normalize_native_shells()
{
	foreach (array('capacitor://localhost', 'ionic://localhost', 'tauri://localhost', 'http://localhost') as $origin) {
		pg_assert_same($origin, api_cors_origin_normalize($origin), $origin);
	}

	pg_assert_same('capacitor://localhost', api_cors_origin_normalize('Capacitor://LOCALHOST'), 'case');
}

// Anything else is not an origin a list takes.
function test_api_cors_origin_normalize_refuses()
{
	$bad = array(
		'', '*', 'null', 'http://app.example.com', 'http://localhost:3000',
		'https://', 'https://app.example.com/', 'https://app.example.com/path',
		'https://app.example.com?x=1', 'https://user@app.example.com',
		'https://app.example.com:0', 'https://app.example.com:99999',
		'https://*.example.com', 'ftp://app.example.com', 'app.example.com',
		'capacitor://other', 'https://app example.com', "https://app.example.com\r\nX: y"
	);

	foreach ($bad as $origin) {
		pg_assert_same('', api_cors_origin_normalize($origin), var_export($origin, true));
	}

	pg_assert_same('', api_cors_origin_normalize(null), 'null value');
	pg_assert_same('', api_cors_origin_normalize(array('https://a.example')), 'array');
}

// A text is read one origin per line; commas and spaces separate too,
// duplicates go and what is not an origin is reported.
function test_api_cors_origins_parse()
{
	$parsed = api_cors_origins_parse("https://a.example.com\n\nHTTPS://A.example.com, capacitor://localhost  http://b.example.com\r\n*");

	pg_assert_same(array('https://a.example.com', 'capacitor://localhost'), $parsed['origins'], 'origins');
	pg_assert_same(array('http://b.example.com', '*'), $parsed['invalid'], 'invalid');

	$empty = api_cors_origins_parse('   ');

	pg_assert_same(array(), $empty['origins'], 'empty origins');
	pg_assert_same(array(), $empty['invalid'], 'empty invalid');
}

// The list's text as it is stored and shown again.
function test_api_cors_origins_text()
{
	pg_assert_same("https://a.example.com\ncapacitor://localhost", api_cors_origins_text(array('https://a.example.com', 'capacitor://localhost')), 'joined');
	pg_assert_same('', api_cors_origins_text(array()), 'empty');
}

// A request's Origin matches only an origin on the list, compared normalised.
function test_api_cors_origin_match()
{
	$list = array('https://a.example.com', 'capacitor://localhost');

	pg_assert_same('https://a.example.com', api_cors_origin_match('https://A.example.com', $list), 'listed');
	pg_assert_same('https://a.example.com', api_cors_origin_match('https://a.example.com:443', $list), 'default port');
	pg_assert_same('capacitor://localhost', api_cors_origin_match('capacitor://localhost', $list), 'native shell');
	pg_assert_same('', api_cors_origin_match('https://b.example.com', $list), 'not listed');
	pg_assert_same('', api_cors_origin_match('https://a.example.com.evil.test', $list), 'suffix');
	pg_assert_same('', api_cors_origin_match('http://a.example.com', $list), 'plain http');
	pg_assert_same('', api_cors_origin_match('null', $list), 'null origin');
	pg_assert_same('', api_cors_origin_match('', $list), 'no origin');
	pg_assert_same('', api_cors_origin_match('https://a.example.com', array()), 'empty list');
	pg_assert_same('', api_cors_origin_match('*', array('*')), 'a star never matches');
}

// A listed origin gets itself back and Vary, never a star or credentials;
// an unlisted one gets nothing.
function test_api_cors_headers()
{
	$list = array('https://a.example.com');

	pg_assert_same(array(
		'Access-Control-Allow-Origin' => 'https://a.example.com',
		'Vary'                        => 'Origin'
	), api_cors_headers('https://a.example.com', $list, false), 'answer');

	$preflight = api_cors_headers('https://a.example.com', $list, true);

	pg_assert_same('https://a.example.com', $preflight['Access-Control-Allow-Origin'], 'preflight origin');
	pg_assert_same('Origin', $preflight['Vary'], 'preflight vary');
	pg_assert_true(strpos($preflight['Access-Control-Allow-Methods'], 'POST') !== false, 'methods');
	pg_assert_true(stripos($preflight['Access-Control-Allow-Headers'], 'Authorization') !== false, 'headers');
	pg_assert_true(stripos($preflight['Access-Control-Allow-Headers'], 'If-None-Match') !== false, 'if-none-match');
	pg_assert_true((int)$preflight['Access-Control-Max-Age'] > 0, 'max age');
	pg_assert_true(!isset($preflight['Access-Control-Allow-Credentials']), 'no credentials');

	pg_assert_same(array(), api_cors_headers('https://b.example.com', $list, false), 'unlisted answer');
	pg_assert_same(array(), api_cors_headers('https://b.example.com', $list, true), 'unlisted preflight');
	pg_assert_same(array(), api_cors_headers('', $list, true), 'no origin');
}

// The token routes refuse a browser origin that is not listed; a call with no
// Origin (a server, a native client) or a listed one passes, and every other
// route is left as it is.
function test_api_cors_token_route_refused()
{
	$list  = array('https://a.example.com');
	$token = array('id' => 'auth.login', 'public' => true);
	$other = array('id' => 'products.list', 'scope' => 'products:read');

	pg_assert_true(api_cors_token_route_refused($token, 'https://b.example.com', $list), 'unlisted');
	pg_assert_true(api_cors_token_route_refused($token, 'null', $list), 'null origin');
	pg_assert_true(api_cors_token_route_refused($token, 'https://b.example.com', array()), 'empty list');
	pg_assert_true(!api_cors_token_route_refused($token, 'https://a.example.com', $list), 'listed');
	pg_assert_true(!api_cors_token_route_refused($token, '', $list), 'no origin');
	pg_assert_true(!api_cors_token_route_refused($other, 'https://b.example.com', $list), 'not a token route');
}
