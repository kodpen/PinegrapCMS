<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the request to a time stamp authority
 * (includes/fn/signature.php): the operator types its address, so the
 * request is checked and pinned like every outgoing call to an operator's
 * address, with plain http still allowed; and for pg_curl_run() taking
 * check_address and allow_http together (includes/fn/update.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/update.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

// The address is checked and pinned, http is allowed, no redirect is
// followed and the reply is capped.
function test_signature_tsa_request_checks_the_address()
{
	$options = pg_signature_stamp_http_options('query', 'none', '', '');

	pg_assert_same(true, $options['check_address']);
	pg_assert_same(true, $options['allow_http']);
	pg_assert_same(PG_SIGNATURE_TSA_MAX_REPLY, $options['cap']);
	pg_assert_false(isset($options['follow']), 'no redirect is followed');
	pg_assert_same('query', $options['body']);
	pg_assert_false(isset($options['userpwd']), 'no credentials without basic authentication');
}

function test_signature_tsa_request_sends_basic_credentials_only_when_set()
{
	$options = pg_signature_stamp_http_options('query', 'basic', 'user', 'secret');
	pg_assert_same('user:secret', $options['userpwd']);

	$options = pg_signature_stamp_http_options('query', 'basic', '', 'secret');
	pg_assert_false(isset($options['userpwd']), 'no credentials without a user name');
}

// An http address inside the server's network is refused before cURL runs,
// with the address check's reason rather than the https rule.
function test_curl_run_checks_an_http_address_when_http_is_allowed()
{
	if (!function_exists('curl_init')) {
		return;
	}

	foreach (array('http://127.0.0.1:1/tsr', 'http://169.254.169.254/latest/meta-data/', 'http://10.0.0.5/tsr') as $url) {
		$answer = pg_curl_run('POST', $url, array('body' => 'x', 'allow_http' => true, 'check_address' => true, 'timeout' => 2));

		pg_assert_same(false, $answer['ok']);
		pg_assert_same(0, $answer['errno']);
		pg_assert_same('That address is inside this network and is not called.', $answer['error']);
	}
}

// Without allow_http the https rule still holds for a checked address.
function test_curl_run_checked_address_without_allow_http_stays_https_only()
{
	if (!function_exists('curl_init')) {
		return;
	}

	$answer = pg_curl_run('GET', 'http://127.0.0.1:1/', array('check_address' => true, 'timeout' => 2));

	pg_assert_same('Outgoing addresses must be https.', $answer['error']);
}
