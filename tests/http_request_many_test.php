<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for api_http_request_many() (includes/api/outbound/http.php): several
 * requests sent together keep the guard one request alone has. The runner
 * has no network, so only refused addresses, the answer's keys and order,
 * and the code's shape are checked here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function test_http_request_many_refuses_what_one_request_refuses()
{
	$requests = array(
		'local'   => array('method' => 'POST', 'url' => 'https://127.0.0.1/push', 'options' => array('response_headers' => true)),
		'plain'   => array('method' => 'POST', 'url' => 'http://203.0.113.9/push'),
		'private' => array('method' => 'POST', 'url' => 'https://10.0.0.5/push'),
	);

	$results = api_http_request_many($requests, 2);

	pg_assert_same(array('local', 'plain', 'private'), array_keys($results), 'every request answered under its own key, in order');

	foreach ($results as $key => $result) {
		$alone = api_http_request($requests[$key]['method'], $requests[$key]['url'], isset($requests[$key]['options']) ? $requests[$key]['options'] : array());

		pg_assert_false($result['ok'], $key . ' is refused');
		pg_assert_same(0, $result['status'], $key . ' never reached a server');
		pg_assert_same($alone['error'], $result['error'], $key . ' is refused as it is alone');
	}

	pg_assert_same(array(), $results['local']['headers'], 'asked for, the headers are there, empty');
	pg_assert_false(isset($results['plain']['headers']), 'not asked for, no headers');
	pg_assert_same(array(), api_http_request_many(array()), 'nothing to send');
}

function test_http_request_many_shares_the_handle()
{
	$handle = pg_test_function_source('api_http_curl_handle');
	pg_assert_contains('CURLOPT_RESOLVE', $handle, 'curl is pinned to the checked address');
	pg_assert_contains('CURLOPT_SSL_VERIFYPEER, 1', $handle, 'the certificate is verified');
	pg_assert_contains('CURLOPT_FOLLOWLOCATION, false', $handle, 'no redirect is followed');

	pg_assert_contains('api_http_curl_handle(', pg_test_function_source('api_http_request'), 'one request builds its handle there');

	$group = pg_test_function_source('api_http_request_group');
	pg_assert_contains('api_http_check_url(', $group, 'every address is checked');
	pg_assert_contains('api_http_curl_handle(', $group, 'a request sent with others is built like one alone');
	pg_assert_contains('curl_multi_info_read(', $group, 'a transfer\'s own error is read');

	$many = pg_test_function_source('api_http_request_many');
	pg_assert_contains("function_exists('curl_multi_init')", $many, 'without curl_multi the requests go one after another');
	pg_assert_contains('array_chunk($requests, max(1, (int)$concurrency), true)', $many, 'at most $concurrency at a time');
}
