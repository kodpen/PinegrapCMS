<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the anonymous page cache gate in includes/fn/page_cache.php: the
 * lifetime setting, the anonymous session allowlist, taking the token out of
 * the page, reading Set-Cookie names, every reason the gate refuses, the
 * shared-cache headers and the marker cookie; then the file cache: when the
 * router may answer from a stored copy, the key, freshness, the stored file,
 * the visitor beacon and which writes make stored pages old.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A request the gate lets through; each refusal test changes one thing.
function page_cache_test_request(array $change = array())
{
	return array_merge(array(
		'ttl' => 120,
		'method' => 'GET',
		'status' => 200,
		'get' => array('page' => 'about-us'),
		'rendered_html_page' => true,
		'toolbar' => false,
		'access_control_type' => 'public',
		'mobile' => false,
		'cookies' => array('PHPSESSID' => 'abc'),
		'session' => array('software' => array('token' => str_repeat('a', 64), 'visitor_id' => '12')),
		'set_cookies' => array('PHPSESSID'),
		'session_name' => 'PHPSESSID',
		'token_left' => 0,
		'personal' => '',
	), $change);
}

// Missing, malformed and negative lifetimes are off; large ones are held at the maximum.
function test_page_cache_ttl_value_is_clamped()
{
	pg_assert_same(0, pg_page_cache_ttl_value(null), 'missing');
	pg_assert_same(0, pg_page_cache_ttl_value('abc'), 'not a number');
	pg_assert_same(0, pg_page_cache_ttl_value('-5'), 'negative string');
	pg_assert_same(0, pg_page_cache_ttl_value(-5), 'negative');
	pg_assert_same(0, pg_page_cache_ttl_value('1.5'), 'fraction');
	pg_assert_same(120, pg_page_cache_ttl_value('120'), 'string from config.php');
	pg_assert_same(120, pg_page_cache_ttl_value(120), 'integer');
	pg_assert_same(PG_PAGE_CACHE_TTL_MAX, pg_page_cache_ttl_value('999999'), 'held at the maximum');
	pg_assert_same(0, pg_page_cache_ttl(), 'off in the test runner');
}

// The switch off stores 0; a typed lifetime is held between the limits; nothing usable keeps or defaults.
function test_page_cache_ttl_from_form()
{
	pg_assert_same(0, pg_page_cache_ttl_from_form('', '300', 120), 'switch off');
	pg_assert_same(300, pg_page_cache_ttl_from_form('1', '300', 0), 'typed');
	pg_assert_same(PG_PAGE_CACHE_TTL_MIN, pg_page_cache_ttl_from_form('1', '5', 0), 'below the minimum');
	pg_assert_same(PG_PAGE_CACHE_TTL_MAX, pg_page_cache_ttl_from_form('1', '9999', 0), 'above the maximum');
	pg_assert_same(PG_PAGE_CACHE_TTL_DEFAULT, pg_page_cache_ttl_from_form('1', '', 0), 'empty when off: the default');
	pg_assert_same(600, pg_page_cache_ttl_from_form('1', 'ten', 600), 'garbage keeps the lifetime in force');
	pg_assert_same(PG_PAGE_CACHE_TTL_DEFAULT, pg_page_cache_ttl_from_form('1', null, 0), 'not posted');
}

// Only the keys every anonymous session has; anything else is personal.
function test_page_cache_session_allowlist()
{
	pg_assert_true(pg_page_cache_session_anonymous(array()), 'empty');
	pg_assert_true(pg_page_cache_session_anonymous(array('software' => array(
		'token' => 'x', 'device_type' => 'desktop', 'visitor_id' => '5', 'http_referer' => 'https://example.com/',
		'tracking_code' => 't', 'affiliate_code' => 'a', 'utm_source' => 's', 'utm_medium' => 'm',
		'utm_campaign' => 'c', 'utm_term' => 't', 'utm_content' => 'c', 'cookie_id' => '9'))), 'tracking keys');

	pg_assert_false(pg_page_cache_session_anonymous(array('sessionusername' => 'erdal')), 'signed in');
	pg_assert_false(pg_page_cache_session_anonymous(array('ecommerce' => array('order_id' => 4))), 'an order');
	pg_assert_false(pg_page_cache_session_anonymous(array('ecommerce' => array())), 'an empty ecommerce key');
	pg_assert_false(pg_page_cache_session_anonymous(array('software' => array('token' => 'x', 'guest' => true))), 'guest');
	pg_assert_false(pg_page_cache_session_anonymous(array('software' => array('urls' => array('/a')))), 'stored url ids');
	pg_assert_false(pg_page_cache_session_anonymous(array('software' => array('visit_start_timestamp' => 1))), 'auto dialogs');
	pg_assert_false(pg_page_cache_session_anonymous(array('software' => array('token' => array('x')))), 'an array where a value belongs');
	pg_assert_false(pg_page_cache_session_anonymous(array('software' => 'x')), 'software not an array');
	pg_assert_false(pg_page_cache_session_anonymous(array('software' => array('new_key_added_later' => 1))), 'an unknown key');
}

// The inline script's token is blanked; a form field's copy stays and is counted.
function test_page_cache_strip_token()
{
	$token = str_repeat('f', 64);
	$page = '<script>var software_token = "' . $token . '";</script><p>Hello</p>';

	pg_assert_same(array('<script>var software_token = "";</script><p>Hello</p>', 0), pg_page_cache_strip_token($page, $token), 'script only');

	$form = $page . '<input type="hidden" name="token" value="' . $token . '">';
	list($html, $left) = pg_page_cache_strip_token($form, $token);
	pg_assert_same(1, $left, 'a form still carries it');
	pg_assert_contains('var software_token = "";', $html, 'the script is blanked anyway');

	pg_assert_same(array($page, 0), pg_page_cache_strip_token($page, ''), 'no token in the session');
}

// Cookie names from headers_list(), each once; other headers ignored.
function test_page_cache_set_cookie_names()
{
	pg_assert_same(
		array('PHPSESSID', 'software[number_of_visits]'),
		pg_page_cache_set_cookie_names(array(
			'X-Powered-By: PHP',
			'Set-Cookie: PHPSESSID=abc; path=/; HttpOnly',
			'set-cookie: software[number_of_visits]=1; expires=Thu, 01 Jan 2037 00:00:00 GMT; path=/',
			'Set-Cookie: PHPSESSID=def; path=/',
			'Cache-Control: no-store',
		)));
	pg_assert_same(array(), pg_page_cache_set_cookie_names(array()), 'none');
}

// The plain anonymous page is cacheable, a HEAD too.
function test_page_cache_decide_lets_an_anonymous_page_through()
{
	pg_assert_same(array('cacheable' => true, 'reason' => ''), pg_page_cache_decide(page_cache_test_request()));
	pg_assert_true(pg_page_cache_decide(page_cache_test_request(array('method' => 'HEAD')))['cacheable'], 'HEAD');
	pg_assert_true(pg_page_cache_decide(page_cache_test_request(array('get' => array(), 'set_cookies' => array(), 'cookies' => array())))['cacheable'], 'home, first visit');
}

// One refusal per rule, in the order the gate checks them.
function test_page_cache_decide_reasons()
{
	$cases = array(
		'off' => array('ttl' => 0),
		'method' => array('method' => 'POST'),
		'status' => array('status' => 404),
		'query' => array('get' => array('page' => 'x', 'utm_source' => 'mail')),
		'not_html' => array('rendered_html_page' => false),
		'toolbar' => array('toolbar' => true),
		'access' => array('access_control_type' => 'private'),
		'mobile' => array('mobile' => true),
		'marker' => array('cookies' => array('pg_nc' => '1')),
		'consent' => array('cookies' => array('pg_consent' => '1.a1.m0')),
		'signed_in' => array('cookies' => array('software' => array('auth' => 'x'))),
		'session' => array('session' => array('software' => array('token' => 'x'), 'ecommerce' => array('currency_id' => 2))),
		'set_cookie' => array('set_cookies' => array('PHPSESSID', 'software[number_of_visits]')),
		'token' => array('token_left' => 1),
		'personal_php_region' => array('personal' => 'php_region'),
	);

	foreach ($cases as $reason => $change) {
		pg_assert_same(array('cacheable' => false, 'reason' => $reason), pg_page_cache_decide(page_cache_test_request($change)), $reason);
	}

	pg_assert_same('access', pg_page_cache_decide(page_cache_test_request(array('access_control_type' => '')))['reason'], 'unknown access type');
	pg_assert_same('status', pg_page_cache_decide(page_cache_test_request(array('status' => false)))['reason'], 'no status');
	pg_assert_same('query', pg_page_cache_decide(page_cache_test_request(array('get' => array('t' => 'abc'))))['reason'], 'short link tracking code');
	pg_assert_same('query', pg_page_cache_decide(page_cache_test_request(array('query_string' => '?page=zzz')))['reason'], 'the router key typed in the address');
	pg_assert_same('query', pg_page_cache_decide(page_cache_test_request(array('query_string' => '?')))['reason'], 'a bare question mark');
	pg_assert_true(pg_page_cache_decide(page_cache_test_request(array('query_string' => '')))['cacheable'], 'no question mark');
}

// The request address from its first question mark on.
function test_page_cache_query_string()
{
	pg_assert_same('', pg_page_cache_query_string('/about'), 'none');
	pg_assert_same('', pg_page_cache_query_string(''), 'empty address');
	pg_assert_same('?', pg_page_cache_query_string('/about?'), 'a bare question mark');
	pg_assert_same('?page=zzz', pg_page_cache_query_string('/about?page=zzz'));
	pg_assert_same('?a=1?b=2', pg_page_cache_query_string('/x?a=1?b=2'), 'from the first one');
}

// Only staff learn why a page was not cacheable.
function test_page_cache_bypass_header()
{
	pg_assert_same('X-Pinegrap-Cache: bypass', pg_page_cache_bypass_header('personal_php_region', false), 'visitor');
	pg_assert_same('X-Pinegrap-Cache: bypass; reason=personal_php_region', pg_page_cache_bypass_header('personal_php_region', true), 'staff');
	pg_assert_same('X-Pinegrap-Cache: bypass; reason=sessionx', pg_page_cache_bypass_header('Session ' . chr(13) . chr(10) . 'X', true), 'header-safe');
	pg_assert_same('X-Pinegrap-Cache: bypass', pg_page_cache_bypass_header('', true), 'no reason');
}

// The marker lives as long as the session cookie and on the same path.
function test_page_cache_marker_cookie_params()
{
	pg_assert_same(array(0, '/'), pg_page_cache_marker_cookie_params(array('lifetime' => 0, 'path' => '/'), 1000), 'browser session');
	pg_assert_same(array(1000 + 86400, '/'), pg_page_cache_marker_cookie_params(array('lifetime' => 86400, 'path' => '/'), 1000), 'outlives the browser');
	pg_assert_same(array(0, '/shop/'), pg_page_cache_marker_cookie_params(array('lifetime' => 0, 'path' => '/shop/'), 1000), 'site in a folder');
	pg_assert_same(array(0, '/'), pg_page_cache_marker_cookie_params(array(), 1000), 'nothing known');
	pg_assert_same(array(0, '/'), pg_page_cache_marker_cookie_params(array('path' => '/; domain=x'), 1000), 'unsafe path');
	pg_assert_same(array(0, '/'), pg_page_cache_marker_cookie_params(array('path' => 'shop'), 1000), 'relative path');
}

// Browsers ask again every time; a shared cache keeps it for the lifetime.
function test_page_cache_headers()
{
	pg_assert_same(array('Cache-Control: public, max-age=0, s-maxage=120', 'Vary: Accept-Encoding'), pg_page_cache_headers(120));
	pg_assert_same('Cache-Control: public, max-age=0, s-maxage=' . PG_PAGE_CACHE_TTL_MAX, pg_page_cache_headers(99999)[0], 'held at the maximum');
}

// The marker follows the session and never rides on a cacheable answer.
function test_page_cache_marker_action()
{
	pg_assert_same('set', pg_page_cache_marker_action(false, false, false), 'personal session without it');
	pg_assert_same('', pg_page_cache_marker_action(false, true, false), 'personal session with it');
	pg_assert_same('clear', pg_page_cache_marker_action(true, true, false), 'anonymous again');
	pg_assert_same('', pg_page_cache_marker_action(true, false, false), 'anonymous without it');
	pg_assert_same('', pg_page_cache_marker_action(false, false, true), 'cacheable answer');
	pg_assert_same('', pg_page_cache_marker_action(true, true, true), 'cacheable answer, stale marker');
}

// The first reason given is kept, cleaned to a header-safe word.
function test_page_cache_personal_keeps_the_first_reason()
{
	// The reason is kept for the whole process, so a test that ran earlier
	// may have given the first one already.
	$before = pg_page_cache_personal();
	pg_page_cache_personal('Language Redirect!');
	pg_page_cache_personal('php_region');
	pg_assert_same(($before !== '') ? $before : 'languageredirect', pg_page_cache_personal(), 'the first, cleaned');
}

// Callbacks are kept once each, in the order added.
function test_page_cache_header_callbacks_list()
{
	$before = pg_header_callbacks();
	pg_header_callbacks('page_cache_test_callback_a');
	pg_header_callbacks('page_cache_test_callback_b');
	pg_header_callbacks('page_cache_test_callback_a');

	pg_assert_same(array_merge($before, array('page_cache_test_callback_a', 'page_cache_test_callback_b')), pg_header_callbacks());
}

// Translated redirects and the marker share the one header callback slot.
function test_page_cache_translate_uses_the_shared_header_callback()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/translate.php');

	pg_assert_contains("pg_header_callback_add('pg_tr_redirect_hook')", $source);
}

// -- the file cache -----------------------------------------------------------

// A request the router may answer from a stored copy; each bypass test
// changes one thing.
function page_cache_test_serve(array $change = array())
{
	return array_merge(array(
		'ttl' => 120,
		'file' => true,
		'generation_known' => true,
		'method' => 'GET',
		'uri' => '/about-us',
		'host' => 'www.example.com',
		'hostname' => 'www.example.com',
		'authorization' => false,
		'cookies' => array('PHPSESSID' => 'abc', 'pg_language' => 'en'),
		'mobile' => false,
		'maintenance' => false,
	), $change);
}

function test_page_cache_serve_check_lets_a_plain_visit_through()
{
	pg_assert_same('', pg_page_cache_serve_check(page_cache_test_serve()));
	pg_assert_same('', pg_page_cache_serve_check(page_cache_test_serve(array('method' => 'HEAD'))), 'HEAD');
	pg_assert_same('', pg_page_cache_serve_check(page_cache_test_serve(array('host' => 'WWW.Example.com'))), 'host case');
}

function test_page_cache_serve_check_bypass_reasons()
{
	$cases = array(
		'off' => array('ttl' => 0),
		'file_off' => array('file' => false),
		'generation' => array('generation_known' => false),
		'method' => array('method' => 'POST'),
		'query' => array('uri' => '/about-us?x=1'),
		'host' => array('host' => 'evil.example.net'),
		'authorization' => array('authorization' => true),
		'marker' => array('cookies' => array('pg_nc' => '1')),
		'signed_in' => array('cookies' => array('software' => array('auth' => 'x'))),
		'consent' => array('cookies' => array('pg_consent' => 'a')),
		'mobile' => array('mobile' => true),
		'maintenance' => array('maintenance' => true),
	);

	foreach ($cases as $reason => $change) {
		pg_assert_same($reason, pg_page_cache_serve_check(page_cache_test_serve($change)), $reason);
	}

	pg_assert_same('query', pg_page_cache_serve_check(page_cache_test_serve(array('uri' => '/about-us?'))), 'a bare ?');
	pg_assert_same('host', pg_page_cache_serve_check(page_cache_test_serve(array('host' => ''))), 'no host');
}

// The key follows the scheme, the proxy's word on it, the host, the address
// and the software version, and nothing else.
function test_page_cache_key_parts()
{
	$base = array('scheme' => 'https', 'forwarded' => '', 'host' => 'www.example.com', 'path' => '/about-us', 'version' => '2026.11.0');
	$key = pg_page_cache_key($base);

	pg_assert_true((bool) preg_match('/^[0-9a-f]{40}$/', $key), 'sha1');
	pg_assert_same($key, pg_page_cache_key(array_merge($base, array('host' => 'WWW.EXAMPLE.COM'))), 'host case');

	foreach (array('scheme' => 'http', 'forwarded' => 'https', 'host' => 'example.com', 'path' => '/About-us', 'version' => '2026.11.1') as $part => $value) {
		pg_assert_true($key !== pg_page_cache_key(array_merge($base, array($part => $value))), $part);
	}

	pg_assert_same($key, pg_page_cache_key(array_merge($base, array('path' => '/about-us?x=1'))), 'the query is never part of it');
}

function test_page_cache_request_scheme()
{
	pg_assert_same(array('https', ''), pg_page_cache_request_scheme(array('HTTPS' => 'on')));
	pg_assert_same(array('http', ''), pg_page_cache_request_scheme(array('HTTPS' => 'off')));
	pg_assert_same(array('https', ''), pg_page_cache_request_scheme(array('SERVER_PORT' => '443')));
	pg_assert_same(array('http', 'https'), pg_page_cache_request_scheme(array('HTTP_X_FORWARDED_PROTO' => 'HTTPS, http')));

	// Every header Secure Mode trusts (check_proxy_ssl_headers()) says https
	// the same way; anything else is no word at all, so a made-up header
	// value cannot make a copy of its own.
	$says_https = array(
		'cf' => array('HTTP_CF_VISITOR' => '{"scheme":"https"}'),
		'forwarded' => array('HTTP_FORWARDED' => 'for=192.0.2.1;proto=https'),
		'ssl' => array('HTTP_X_FORWARDED_SSL' => 'on'),
		'front_end' => array('HTTP_FRONT_END_HTTPS' => 'On'),
		'port' => array('HTTP_X_FORWARDED_PORT' => '443'),
	);

	foreach ($says_https as $name => $server) {
		pg_assert_same(array('http', 'https'), pg_page_cache_request_scheme($server), $name);
	}

	foreach (array('http', 'ftp', 'httpsx', 'abcdefghij', '') as $value) {
		pg_assert_same(array('http', ''), pg_page_cache_request_scheme(array('HTTP_X_FORWARDED_PROTO' => $value)), 'X-Forwarded-Proto: ' . $value);
	}

	pg_assert_same(array('http', ''), pg_page_cache_request_scheme(array('HTTP_X_FORWARDED_PORT' => '8080', 'HTTP_X_FORWARDED_SSL' => 'off')));
}

function test_page_cache_file_path()
{
	$key = sha1('x');
	pg_assert_same('/d/g7/' . substr($key, 0, 2) . '/' . $key . '.html', pg_page_cache_file_path('/d/', 7, $key));
	pg_assert_same('', pg_page_cache_file_path('/d', 7, '../../x'), 'not a key');
}

function test_page_cache_freshness_boundaries()
{
	pg_assert_same('fresh', pg_page_cache_freshness(1000, 1000, 120));
	pg_assert_same('fresh', pg_page_cache_freshness(1000, 1119, 120));
	pg_assert_same('stale', pg_page_cache_freshness(1000, 1120, 120));
	pg_assert_same('stale', pg_page_cache_freshness(1000, 1239, 120));
	pg_assert_same('expired', pg_page_cache_freshness(1000, 1240, 120));
	pg_assert_same('expired', pg_page_cache_freshness(1000, 2000, 0), 'off');
	pg_assert_same('expired', pg_page_cache_freshness(1300, 1000, 120), 'from the future');
}

// A render the store check lets through; each refusal changes one thing.
function page_cache_test_store(array $change = array())
{
	return array_merge(array(
		'key' => sha1('k'),
		'cacheable' => true,
		'bot' => false,
		'automated' => false,
		'bytes' => 5000,
	), $change);
}

function test_page_cache_store_check_reasons()
{
	pg_assert_same('', pg_page_cache_store_check(page_cache_test_store()));

	$cases = array(
		'no_key' => array('key' => ''),
		'not_cacheable' => array('cacheable' => false),
		'bot' => array('bot' => true),
		'automated' => array('automated' => true),
		'size' => array('bytes' => PG_PAGE_CACHE_MAX_BYTES + 1),
		'empty' => array('bytes' => 0),
	);

	foreach ($cases as $reason => $change) {
		pg_assert_same($reason, pg_page_cache_store_check(page_cache_test_store($change)), $reason);
	}
}

function test_page_cache_pack_round_trip_and_corrupt_input()
{
	$meta = array('created' => 1700000000, 'headers' => array('Content-Type: text/html; charset=utf-8'));
	$body = "<html>\n<body>line one\nline two</body></html>";

	$raw = pg_page_cache_pack($meta, $body);
	$back = pg_page_cache_unpack($raw);

	pg_assert_true(is_array($back));
	pg_assert_same($body, $back['body']);
	pg_assert_same(1700000000, $back['meta']['created']);
	pg_assert_same($meta['headers'], $back['meta']['headers']);

	pg_assert_same(null, pg_page_cache_unpack(''), 'empty');
	pg_assert_same(null, pg_page_cache_unpack("not json\n<html>"), 'no meta');
	pg_assert_same(null, pg_page_cache_unpack('{"v":1,"created":5}'), 'no body line');
	pg_assert_same(null, pg_page_cache_unpack("{\"v\":99,\"created\":5,\"headers\":[]}\nx"), 'another format');
	pg_assert_same(null, pg_page_cache_unpack("{\"v\":1,\"headers\":[]}\nx"), 'no time');
}

// Only the headers a page answer carries are kept, and never a cookie or the
// cache's own lines.
function test_page_cache_kept_headers()
{
	$kept = pg_page_cache_kept_headers(array(
		'Content-Type: text/html; charset=UTF-8',
		'Set-Cookie: PHPSESSID=abc; path=/',
		'Cache-Control: public, max-age=0, s-maxage=120',
		'X-Robots-Tag: noindex',
		"Content-Security-Policy-Report-Only: default-src 'self'",
		'Strict-Transport-Security: max-age=31536000',
		'Link: <https://www.example.com/about>; rel="canonical"',
		'X-Pinegrap-Cache: cacheable',
		"X-Robots-Tag: x\r\nSet-Cookie: a=b",
	));

	pg_assert_same(array(
		'Content-Type: text/html; charset=UTF-8',
		'X-Robots-Tag: noindex',
		"Content-Security-Policy-Report-Only: default-src 'self'",
		'Strict-Transport-Security: max-age=31536000',
		'Link: <https://www.example.com/about>; rel="canonical"',
	), $kept);
}

function test_page_cache_beacon_sign_and_verify()
{
	$payload = pg_page_cache_beacon_payload(array('page_id' => 5, 'page_name' => 'about-us', 'pretty' => '', 'item_type' => 'product', 'item_id' => 9, 'form_id' => 0, 'record_page_id' => 0));
	$signature = pg_page_cache_beacon_sign($payload, 'secret-key');

	pg_assert_true(is_string($payload) && ($payload !== ''));
	pg_assert_true(pg_page_cache_beacon_verify($payload, $signature, 'secret-key'));
	pg_assert_false(pg_page_cache_beacon_verify($payload, $signature, 'other-key'), 'another key');
	pg_assert_false(pg_page_cache_beacon_verify(str_replace('"page_id":5', '"page_id":6', $payload), $signature, 'secret-key'), 'changed page');
	pg_assert_false(pg_page_cache_beacon_verify($payload, '', 'secret-key'), 'no signature');
	pg_assert_same('', pg_page_cache_beacon_sign($payload, ''), 'no key, no signature');
	pg_assert_false(pg_page_cache_beacon_verify($payload, pg_page_cache_beacon_sign($payload, ''), ''), 'an empty key never verifies');

	$read = pg_page_cache_beacon_read($payload);
	pg_assert_same(5, $read['page_id']);
	pg_assert_same('product', $read['item_type']);
	pg_assert_same(null, pg_page_cache_beacon_read('[1,2]'), 'not an object');
	pg_assert_same(null, pg_page_cache_beacon_read('{"page_id":0}'), 'no page');
}

function test_page_cache_beacon_inject()
{
	$html = '<html><body><p>a</p><script>var s = "</body>";</script></body></html>';
	$out = pg_page_cache_beacon_inject($html, '{"page_id":5}', 'abc', '/pinegrap/page_view.php');

	$at = strrpos($out, '</body>');
	$script = strrpos($out, '<script>(function(){');
	pg_assert_true(($script !== false) && ($script < $at) && ($script > strpos($out, 'var s')), 'before the last </body>');
	pg_assert_contains('navigator.sendBeacon', $out);
	pg_assert_contains('keepalive', $out);
	pg_assert_true(substr($out, -14) === '</body></html>', 'the end of the page stays');

	pg_assert_same('<p>no body</p>', pg_page_cache_beacon_inject('<p>no body</p>', '{"page_id":5}', 'abc', '/x'), 'no </body>');
	pg_assert_same($html, pg_page_cache_beacon_inject($html, '{"page_id":5}', '', '/x'), 'no signature');

	// The payload is a JavaScript string that cannot end the script.
	$evil = pg_page_cache_beacon_inject($html, '{"page_name":"</script><b>"}', 'abc', '/x');
	pg_assert_false(strpos($evil, '</script><b>') !== false, 'escaped');
}

function test_page_cache_referer_value()
{
	pg_assert_same('https://www.google.com/search?q=x', pg_page_cache_referer('https://www.google.com/search?q=x'));
	pg_assert_same('', pg_page_cache_referer('javascript:alert(1)'));
	pg_assert_same('', pg_page_cache_referer('/relative'));
	pg_assert_same('', pg_page_cache_referer(array('x')));
	pg_assert_same(2048, strlen(pg_page_cache_referer('https://a.example/' . str_repeat('x', 3000))));
}

// Which requests make every stored page old.
function test_page_cache_request_dirties()
{
	pg_assert_true(pg_page_cache_request_dirties('POST', 'edit_page.php', true, false));
	pg_assert_true(pg_page_cache_request_dirties('PUT', 'integration.php', false, true), 'an API write');
	pg_assert_false(pg_page_cache_request_dirties('GET', 'edit_page.php', true, false), 'a read');
	pg_assert_false(pg_page_cache_request_dirties('HEAD', 'edit_page.php', true, false));
	pg_assert_false(pg_page_cache_request_dirties('OPTIONS', 'integration.php', false, true));
	pg_assert_false(pg_page_cache_request_dirties('POST', 'submit_form.php', false, false), 'a visitor');

	foreach (array('page_view.php', 'api.php', 'chat.php', 'workspace_tasks.php', 'workspace.php', 'software_update_check.php', 'wishlist.php') as $script) {
		pg_assert_false(pg_page_cache_request_dirties('POST', $script, true, false), $script);
	}
}

function test_page_cache_panel_action_dirties()
{
	pg_assert_true(pg_page_cache_panel_dirties('update_style', array('file' => 'designer.php', 'write' => true)));
	pg_assert_true(pg_page_cache_panel_dirties('sort_menu_items', array('file' => 'content.php', 'write' => true)));
	pg_assert_true(pg_page_cache_panel_dirties('update_product_status', array('file' => 'commerce.php', 'write' => true)));
	pg_assert_false(pg_page_cache_panel_dirties('add_to_cart', array('file' => 'commerce.php', 'write' => true)), 'a cart');
	pg_assert_false(pg_page_cache_panel_dirties('user_online_check', array('file' => 'dashboard.php', 'write' => true)), 'a poll');
	pg_assert_false(pg_page_cache_panel_dirties('get_widget_data', array('file' => 'designer.php', 'write' => false)), 'a read');
	pg_assert_false(pg_page_cache_panel_dirties('x', null));
}

// The editor, the shared components and the file explorer each carry reads,
// polls and writes on one write row; only a sub-action that changes what a
// visitor may see makes stored pages old. The presence heartbeat runs every
// twenty seconds in every open editor.
function test_page_cache_panel_sub_action_dirties()
{
	$designer = array('file' => 'designer.php', 'write' => true);

	foreach (array('presence', 'leave', 'lock_release', 'notes_fetch', 'note_save', 'page_load', 'preview_widgets', 'widget_ghosts', 'seo_check', 'revisions_list', 'revision_get', 'tab_layout', 'ai_status', '', 'nope') as $sub) {
		pg_assert_false(pg_page_cache_panel_dirties('designer', $designer, array('sub_action' => $sub)), 'designer ' . $sub);
	}

	foreach (array('page_delete', 'page_detach', 'design_publish', 'design_delete', 'form_adopt', 'theme_look_save') as $sub) {
		pg_assert_true(pg_page_cache_panel_dirties('designer', $designer, array('sub_action' => $sub)), 'designer ' . $sub);
	}

	pg_assert_false(pg_page_cache_panel_dirties('designer', $designer), 'no sub-action');
	pg_assert_false(pg_page_cache_panel_dirties('designer', $designer, array('sub_action' => array('page_delete'))), 'not a string');

	$file = array('file' => 'designer.php', 'write' => true);
	pg_assert_false(pg_page_cache_panel_dirties('designer_file', $file, array('sub_action' => 'read')));
	pg_assert_false(pg_page_cache_panel_dirties('designer_file', $file, array('sub_action' => 'list_folders')));
	pg_assert_true(pg_page_cache_panel_dirties('designer_file', $file, array('sub_action' => 'save')));

	$shared = array('file' => 'shared_component.php', 'write' => true);
	pg_assert_false(pg_page_cache_panel_dirties('shared_component', $shared, array('sub_action' => 'get')));
	pg_assert_false(pg_page_cache_panel_dirties('shared_component', $shared, array('sub_action' => 'list_all_pages')));
	pg_assert_true(pg_page_cache_panel_dirties('shared_component', $shared, array('sub_action' => 'content_update')));

	$explorer = array('file' => 'explorer.php', 'write' => true);

	foreach (array('explorer_list', 'explorer_tree', 'get_breadcrumb', 'get_tables', 'explorer_file_get', 'explorer_catalog_list') as $type) {
		pg_assert_false(pg_page_cache_panel_dirties('file_explorer', $explorer, array('type' => $type)), $type);
	}

	foreach (array('explorer_upload', 'explorer_file_save', 'delete_file', 'explorer_catalog_quick_edit', 'explorer_short_link_update') as $type) {
		pg_assert_true(pg_page_cache_panel_dirties('file_explorer', $explorer, array('type' => $type)), $type);
	}
}

// Every sub-action those handlers answer is sorted into one of the two lists,
// so a new one is decided on rather than left out.
function test_page_cache_panel_sub_actions_are_all_sorted()
{
	$handlers = array(
		'designer' => array('includes/panel/designer.php', 'pg_panel_designer'),
		'designer_file' => array('includes/panel/designer.php', 'pg_panel_designer_file'),
		'shared_component' => array('includes/panel/shared_component.php', 'pg_panel_shared_component'),
		'file_explorer' => array('includes/panel/explorer.php', 'pg_panel_file_explorer'),
	);

	$lists = pg_page_cache_panel_sub_actions();

	foreach ($handlers as $action => $where) {
		$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $where[0]));
		$start = strpos($source, 'function ' . $where[1] . '(');
		pg_assert_true($start !== false, $where[1]);
		$next = strpos($source, "\nfunction ", (int) $start + 10);
		$body = ($next === false) ? (string) substr($source, (int) $start) : (string) substr($source, (int) $start, $next - (int) $start);

		// The handler's own switch is indented once inside the function;
		// nested switches are indented deeper.
		preg_match_all('/^        case \'([a-z0-9_]+)\':/m', $body, $found);
		$cases = $found[1];

		// The assistant's sub-actions are answered before the switch.
		if ($action === 'designer') {
			preg_match('/array\(\'ai_state\'[^)]*\)/', $body, $ai);
			preg_match_all('/\'([a-z_]+)\'/', isset($ai[0]) ? $ai[0] : '', $ai_names);
			pg_assert_true(count($ai_names[1]) > 0, 'the assistant sub-actions are found');
			$cases = array_merge($cases, $ai_names[1]);
		}

		pg_assert_true(count($cases) > 3, $action . ' has sub-actions');

		$sorted = array_merge($lists[$action]['dirty'], $lists[$action]['clean']);

		pg_assert_same(array(), array_values(array_diff(array_unique($cases), $sorted)), $action . ': sub-actions in neither list');
		pg_assert_same(array(), array_values(array_intersect($lists[$action]['dirty'], $lists[$action]['clean'])), $action . ': in both lists');
	}
}

// A tool call of the MCP server runs an API route inside one POST; only a
// write that went through makes stored pages old.
function test_page_cache_mcp_dirties()
{
	pg_assert_true(pg_page_cache_mcp_dirties('PUT', false, 200));
	pg_assert_true(pg_page_cache_mcp_dirties('post', false, 201));
	pg_assert_true(pg_page_cache_mcp_dirties('DELETE', false, 204));
	pg_assert_false(pg_page_cache_mcp_dirties('GET', false, 200), 'a read');
	pg_assert_false(pg_page_cache_mcp_dirties('PATCH', true, 200), 'a rehearsal');
	pg_assert_false(pg_page_cache_mcp_dirties('PATCH', null, 200), 'an invalid rehearsal flag');
	pg_assert_false(pg_page_cache_mcp_dirties('DELETE', false, 404), 'a refusal');
	pg_assert_false(pg_page_cache_mcp_dirties('POST', false, 422));
}

// api.php hands the request to the dirty rule; the MCP server asks once the
// route has answered, before the result leaves.
function test_page_cache_panel_and_mcp_wiring()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	pg_assert_contains('pg_page_cache_panel_dirties($action, $entry, is_array($request) ? $request : array())', $actions);

	$tools = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
	$hook = strpos($tools, 'api_send_hook(function');
	$dirty = strpos($tools, 'pg_page_cache_mcp_dirties($route[\'method\'], $split[\'dry_run\'], $status_code)');
	$result = strpos($tools, 'pg_mcp_tool_result($id, $status_code, $body, $modern);');
	pg_assert_true(($hook !== false) && ($dirty !== false) && ($result !== false) && ($hook < $dirty) && ($dirty < $result), 'in the send hook');
}

function test_page_cache_announce_dirties()
{
	foreach (array('page.updated', 'page.created', 'product.updated', 'product.deleted', 'product_group.updated', 'file.created') as $event) {
		pg_assert_true(pg_page_cache_announce_dirties($event), $event);
	}

	foreach (array('order.created', 'inventory.changed', 'stock.low', 'customer.created', 'workspace.task.created') as $event) {
		pg_assert_false(pg_page_cache_announce_dirties($event), $event);
	}
}

// The router answers from the file cache before any page or file lookup and
// lets get_page.php store only on the two paths
// where the address it was asked for is the page it draws.
function test_page_cache_router_wiring()
{
	$router = (string) file_get_contents(PG_FUNCTIONS_DIR . '/router.php');

	$serve = strpos($router, 'router_page_cache_serve();');
	$home = strpos($router, "|| (\$item_name == 'index.php')");
	$files = strpos($router, 'SELECT id FROM files WHERE name');

	pg_assert_true(($serve !== false) && ($home !== false) && ($files !== false) && ($serve < $home) && ($home < $files), 'before the home page and the file lookup');
	pg_assert_same(2, substr_count($router, 'router_page_cache_store_here();'), 'the home page and the page fall-through only');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');
	$apply = strpos($page, '$content = pg_page_cache_apply(');
	$store = strpos($page, 'pg_page_cache_store_page(');
	$echo = strpos($page, 'echo $content;');
	pg_assert_true(($apply !== false) && ($store !== false) && ($echo !== false) && ($apply < $store) && ($store < $echo));
}

// A stored file is written whole, read back, locked once at a time and
// deleted by age.
function test_page_cache_write_lock_and_remove()
{
	$root = rtrim(sys_get_temp_dir(), '/\\') . '/pg_page_cache_test_' . getmypid() . '_' . mt_rand(1000, 9999);
	$key = sha1('page');
	$file = pg_page_cache_file_path($root, 3, $key);

	pg_assert_true(pg_page_cache_write($file, pg_page_cache_pack(array('created' => 100, 'headers' => array()), '<p>x</p>')), 'written');
	pg_assert_same('<p>x</p>', pg_page_cache_unpack((string) file_get_contents($file))['body']);
	pg_assert_true(pg_page_cache_write($file, pg_page_cache_pack(array('created' => 200, 'headers' => array()), '<p>y</p>')), 'over the old copy');
	pg_assert_same(200, pg_page_cache_unpack((string) file_get_contents($file))['meta']['created']);
	pg_assert_same(array(), glob(dirname($file) . '/*.tmp'), 'no half-written file left');
	pg_assert_false(pg_page_cache_write($file, ''), 'nothing to write');

	pg_assert_true(pg_page_cache_lock($file), 'the first takes the lock');
	pg_page_cache_held_lock('');
	pg_assert_false(pg_page_cache_lock($file), 'the second does not');
	touch($file . '.lock', time() - PG_PAGE_CACHE_LOCK_SECONDS - 5);
	pg_assert_true(pg_page_cache_lock($file), 'a dead request\'s lock is taken over');
	pg_page_cache_unlock();
	pg_assert_false(is_file($file . '.lock'), 'given back');

	touch($file, time() - 1000);
	$kept = pg_page_cache_file_path($root, 3, sha1('other'));
	pg_page_cache_write($kept, 'x');
	$removed = pg_page_cache_remove_tree($root, microtime(true) + 5, time() - 500);
	pg_assert_same(1, $removed['files'], 'only the old one');
	pg_assert_true(is_file($kept) && !is_file($file));

	pg_page_cache_remove_tree($root, microtime(true) + 5);
	@rmdir($root);
	pg_assert_false(is_dir($root), 'all gone');
}
