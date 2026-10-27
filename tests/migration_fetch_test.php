<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_migration_request_options() in includes/fn/update.php: the
 * pg_curl_run() options of migration.php's request to the source site's API.
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

// The JSON body, its Content-Type and Content-Length, 60 s, a fresh
// connection, no redirect; http allowed because the operator types the
// source site's address, which may be plain http on the same host or LAN.
function test_migration_request_options()
{
	$data = '{"action":"test"}';

	pg_assert_same(array(
		'body'         => $data,
		'headers'      => array('Content-Type: application/json', 'Content-Length: 17'),
		'timeout'      => 60,
		'forbid_reuse' => true,
		'allow_http'   => true,
	), pg_migration_request_options($data));
}

function test_migration_request_curl_options()
{
	$data = '{"action":"test"}';
	$options = pg_curl_run_options('POST', 'http://localhost/pinegrap/api.php', pg_migration_request_options($data),
		array('user_agent' => 'Pinegrap/test', 'proxy' => '', 'resolve' => ''));

	pg_assert_same(true, $options['CURLOPT_POST']);
	pg_assert_same($data, $options['CURLOPT_POSTFIELDS']);
	pg_assert_same(60, $options['CURLOPT_TIMEOUT']);
	pg_assert_same(10, $options['CURLOPT_CONNECTTIMEOUT']);
	pg_assert_same(false, $options['CURLOPT_FOLLOWLOCATION']);
	pg_assert_same(true, $options['CURLOPT_FORBID_REUSE']);
	pg_assert_same('CURLPROTO_HTTP|CURLPROTO_HTTPS', $options['CURLOPT_PROTOCOLS']);
	pg_assert_false(isset($options['CURLOPT_RESOLVE']), 'no address pin');
}
