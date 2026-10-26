<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the design importer's fetch (import_design.php) on
 * api_http_request() (includes/api/outbound/http.php): the request settings
 * an options array gives, defaults unchanged for the webhook, MCP and
 * marketplace callers; the importer's opt-in options; the answer read back
 * into the importer's shape; the redirect walk of at most five checked hops.
 *
 * import_design.php runs a request when it is loaded, so its fetch functions
 * are read out of the file by name and only they are declared here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
require_once(PG_FUNCTIONS_DIR . '/url_to_absolute.php');

// Declares the named functions of import_design.php, nothing else of it.
function _test_idf_load($names)
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/import_design.php'));
	$count = count($tokens);

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_FUNCTION)) {
			continue;
		}

		$j = $i + 1;
		while (($j < $count) && is_array($tokens[$j]) && ($tokens[$j][0] === T_WHITESPACE)) {
			$j++;
		}

		if (!is_array($tokens[$j]) || ($tokens[$j][0] !== T_STRING) || !in_array($tokens[$j][1], $names, true) || function_exists($tokens[$j][1])) {
			continue;
		}

		// From "function" to the brace that closes its body.
		$source = '';
		$depth = 0;
		$opened = false;

		for ($k = $i; $k < $count; $k++) {
			$text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
			$source .= $text;

			if (($text === '{') || (is_array($tokens[$k]) && in_array($tokens[$k][0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;
				$opened = true;
			} elseif ($text === '}') {
				$depth--;

				if ($opened && ($depth === 0)) {
					break;
				}
			}
		}

		eval($source);
	}
}

_test_idf_load(array(
	'import_design_user_agent',
	'import_design_fetch_options',
	'import_design_http_answer',
	'import_design_redirect_target',
	'import_design_fetch',
));

// The answers import_design_fetch_once() gives, one per call, and the
// addresses it was asked for.
$GLOBALS['_test_idf_answers'] = array();
$GLOBALS['_test_idf_asked'] = array();

if (!function_exists('import_design_fetch_once')) {
	function import_design_fetch_once($url, $head_only)
	{
		$GLOBALS['_test_idf_asked'][] = $url;

		return array_shift($GLOBALS['_test_idf_answers']);
	}
}

function _test_idf_redirect($location)
{
	return array('status' => 301, 'content' => '', 'content_type' => '', 'location' => $location);
}

function test_api_http_request_settings_defaults_unchanged()
{
	$settings = api_http_request_settings('post', array());

	pg_assert_same('POST', $settings['method'], 'method');
	pg_assert_same(10, $settings['timeout'], 'timeout');
	pg_assert_same(5, $settings['connect_timeout'], 'connect timeout');
	pg_assert_same(1000, $settings['max_body'], 'max body');
	pg_assert_same('Pinegrap/' . (defined('VERSION') ? VERSION : '') . ' (+outbound)', $settings['user_agent'], 'user agent');
	pg_assert_false($settings['allow_http'], 'allow_http');
	pg_assert_false($settings['decode'], 'decode');
	pg_assert_false($settings['head_get_on_405'], 'HEAD retry');
	pg_assert_same(array(), $settings['headers'], 'headers');
	pg_assert_same(null, $settings['body'], 'body');
}

function test_api_http_request_settings_agent_word_kept()
{
	$settings = api_http_request_settings('GET', array('agent' => 'webhook', 'timeout' => 15, 'max_body' => 65536));

	pg_assert_same('Pinegrap/' . (defined('VERSION') ? VERSION : '') . ' (+webhook)', $settings['user_agent']);
	pg_assert_same(15, $settings['timeout']);
	pg_assert_same(65536, $settings['max_body']);
}

function test_import_design_options_give_importer_settings()
{
	$settings = api_http_request_settings('HEAD', import_design_fetch_options());

	pg_assert_true($settings['allow_http'], 'plain http');
	pg_assert_true($settings['head_get_on_405'], 'HEAD retry');
	pg_assert_true($settings['decode'], 'decoding');
	pg_assert_same(30, $settings['timeout'], 'timeout');
	pg_assert_same(0, $settings['max_body'], 'whole body');
	pg_assert_same(import_design_user_agent(), $settings['user_agent'], 'user agent');
	pg_assert_contains('Mozilla/5.0', $settings['user_agent'], 'browser user agent');
	pg_assert_true(import_design_fetch_options()['response_headers'], 'response headers');
}

function test_api_http_retry_as_get_only_when_asked()
{
	$asked = array('head_get_on_405' => true);
	$plain = array('head_get_on_405' => false);

	pg_assert_true(api_http_retry_as_get('HEAD', 405, $asked), 'HEAD 405, asked');
	pg_assert_false(api_http_retry_as_get('HEAD', 405, $plain), 'HEAD 405, not asked');
	pg_assert_false(api_http_retry_as_get('HEAD', 200, $asked), 'HEAD 200');
	pg_assert_false(api_http_retry_as_get('GET', 405, $asked), 'GET 405');
}

function test_api_http_request_plain_http_only_when_allowed()
{
	if (defined('API_WEBHOOK_ALLOW_HTTP')) {
		return;
	}

	// Refused before cURL runs, so no request leaves the test.
	$refused = api_http_request('GET', 'http://93.184.216.34/');
	pg_assert_same('Outgoing addresses must be https.', $refused['error'], 'http refused by default');

	$inside = api_http_request('GET', 'http://127.0.0.1/', array('allow_http' => true));
	pg_assert_same('That address is inside this network and is not called.', $inside['error'], 'loopback refused with allow_http');
}

function test_import_design_http_answer_reads_headers()
{
	$answer = import_design_http_answer(array(
		'ok' => false, 'status' => 302, 'body' => '', 'error' => '', 'errno' => 0, 'duration_ms' => 3,
		'headers' => array('content-type' => 'text/html; charset=utf-8', 'location' => '/next'),
	));

	pg_assert_same(array('status' => 302, 'content' => '', 'content_type' => 'text/html; charset=utf-8', 'location' => '/next'), $answer);

	$bare = import_design_http_answer(array('ok' => true, 'status' => 200, 'body' => 'x', 'error' => '', 'errno' => 0, 'duration_ms' => 1, 'headers' => array()));
	pg_assert_same(array('status' => 200, 'content' => 'x', 'content_type' => '', 'location' => ''), $bare);
}

function test_import_design_http_answer_false_when_nothing_answered()
{
	pg_assert_false(import_design_http_answer(array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'Could not resolve host', 'errno' => 6, 'duration_ms' => 1, 'headers' => array())), 'cURL error');
	pg_assert_false(import_design_http_answer(array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'That address is inside this network and is not called.', 'errno' => 0, 'duration_ms' => 0)), 'refused address');
}

function test_import_design_fetch_walks_redirects()
{
	$GLOBALS['_test_idf_asked'] = array();
	$GLOBALS['_test_idf_answers'] = array(
		_test_idf_redirect('https://b.example/start'),
		_test_idf_redirect('../other/page'),
		array('status' => 200, 'content' => 'done', 'content_type' => 'text/html', 'location' => ''),
	);

	$response = import_design_fetch('http://a.example/');

	pg_assert_same(200, $response['status'], 'final status');
	pg_assert_same('done', $response['content'], 'final body');
	pg_assert_same(array('http://a.example/', 'https://b.example/start', 'https://b.example/other/page'), $GLOBALS['_test_idf_asked'], 'hops');
}

function test_import_design_fetch_stops_after_five_redirects()
{
	$GLOBALS['_test_idf_asked'] = array();
	$GLOBALS['_test_idf_answers'] = array();

	for ($i = 0; $i < 7; $i++) {
		$GLOBALS['_test_idf_answers'][] = _test_idf_redirect('https://a.example/' . ($i + 1));
	}

	pg_assert_false(import_design_fetch('https://a.example/0'), 'too many redirects');
	pg_assert_same(6, count($GLOBALS['_test_idf_asked']), 'the first request and five hops');
}

function test_import_design_fetch_redirect_without_location_is_the_answer()
{
	$GLOBALS['_test_idf_answers'] = array(_test_idf_redirect(''));

	pg_assert_same(301, import_design_fetch('https://a.example/')['status']);

	$GLOBALS['_test_idf_answers'] = array(false);

	pg_assert_false(import_design_fetch('https://a.example/'), 'nothing answered');
}
