<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the anonymous page cache gate in includes/fn/page_cache.php: the
 * lifetime setting, the anonymous session allowlist, taking the token out of
 * the page, reading Set-Cookie names, every reason the gate refuses, the
 * shared-cache headers and the marker cookie.
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
	pg_assert_same('', pg_page_cache_personal(), 'none yet');
	pg_page_cache_personal('Language Redirect!');
	pg_page_cache_personal('php_region');
	pg_assert_same('languageredirect', pg_page_cache_personal(), 'the first, cleaned');
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
