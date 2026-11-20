<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application key and its secret as one token
 * (Authorization: Bearer pgkt_<key>.<secret>, includes/api/keys.php and
 * includes/api/auth.php): how the token is made and read, that it gives the
 * same key and secret HTTP Basic gives and so walks Basic's path (the wrong
 * secret counted by the firewall, the previous secret's grace), that the API
 * and the MCP address both take it, and that it is never read from the body
 * or the query string.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/router.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/keys.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/auth.php');

function api_app_token_test_key()
{
	return 'pg_live_' . str_repeat('a1', 16);
}

function api_app_token_test_secret()
{
	return 'pgsk_' . str_repeat('Xy9', 14) . 'Qz';
}

// The credentials read with only this Authorization header on the request.
function api_app_token_test_read($authorization, array $input = array())
{
	api_header('Authorization', $authorization);

	$read = api_read_credentials($input);

	api_header('Authorization', '');

	return $read;
}

// The token is its prefix, the key, a dot and the secret, and reads back.
function test_api_app_token_round_trip()
{
	$token = api_app_token(api_app_token_test_key(), api_app_token_test_secret());

	pg_assert_same('pgkt_' . api_app_token_test_key() . '.' . api_app_token_test_secret(), $token);
	pg_assert_true(api_app_token_is($token), 'carries the prefix');
	pg_assert_same(array('key' => api_app_token_test_key(), 'secret' => api_app_token_test_secret()), api_app_token_parse($token));
}

// A key without a secret has no token, as it has no way in.
function test_api_app_token_needs_both_halves()
{
	pg_assert_same('', api_app_token(api_app_token_test_key(), ''));
	pg_assert_same('', api_app_token('', api_app_token_test_secret()));
}

// Anything that is not a whole token is not read as one; a device or OAuth
// access token is not an application token.
function test_api_app_token_parse_refuses_malformed()
{
	foreach (array(
		'',
		'pgat_' . str_repeat('ab', 24),
		api_app_token_test_key() . '.' . api_app_token_test_secret(),
		'pgkt_' . api_app_token_test_key(),
		'pgkt_.' . api_app_token_test_secret(),
		'pgkt_' . api_app_token_test_key() . '.',
		'pgkt_' . api_app_token_test_key() . '.sec ret',
		'pgkt_' . api_app_token_test_key() . '.a.b',
		'pgkt_key:' . api_app_token_test_secret(),
	) as $bad) {
		pg_assert_same(null, api_app_token_parse($bad), 'refused: ' . $bad);
	}

	pg_assert_false(api_app_token_is('pgat_' . str_repeat('ab', 24)), 'an access token');
}

// A token in the Authorization header gives the very key and secret Basic
// gives for the same pair, so everything after the read is Basic's path.
function test_api_app_token_reads_like_basic()
{
	$key    = api_app_token_test_key();
	$secret = api_app_token_test_secret();

	$basic = api_app_token_test_read('Basic ' . base64_encode($key . ':' . $secret));
	$token = api_app_token_test_read('Bearer ' . api_app_token($key, $secret));

	pg_assert_same(array('key' => $key, 'secret' => $secret), $basic);
	pg_assert_same($basic, $token);

	// A device or OAuth access token gives no key or secret.
	pg_assert_same(array('key' => '', 'secret' => ''), api_app_token_test_read('Bearer pgat_' . str_repeat('ab', 24)));
}

// Never from the body or the query string, which both arrive as $input.
function test_api_app_token_never_from_the_query_string()
{
	$token = api_app_token(api_app_token_test_key(), api_app_token_test_secret());

	foreach (array('api_key', 'access_token', 'token', 'api_token') as $name) {
		pg_assert_same(array('key' => '', 'secret' => ''), api_app_token_test_read('', array($name => $token)), $name);
	}
}

// The current secret opens; the previous one only while its grace (a day,
// from Application Access) runs. A token carries the secret, so it is
// asked the same way.
function test_api_app_token_grace_of_the_previous_secret()
{
	$now     = 1800000000;
	$current = str_repeat('ab', 32);
	$prev    = str_repeat('cd', 32);

	$app = array(
		'api_secret_hash'         => $current,
		'api_secret_prev_hash'    => $prev,
		'api_secret_prev_expires' => $now + 86400,
	);

	pg_assert_true(api_secret_hash_matches_app($app, $current, $now), 'the current secret');
	pg_assert_true(api_secret_hash_matches_app($app, $prev, $now), 'the previous secret inside its day');
	pg_assert_false(api_secret_hash_matches_app($app, $prev, $now + 86400), 'the previous secret once its day is over');
	pg_assert_false(api_secret_hash_matches_app($app, str_repeat('ef', 32), $now), 'another secret');
	pg_assert_false(api_secret_hash_matches_app(array('api_secret_hash' => ''), '', $now), 'a key without a secret');
}

// The token never reaches the device lookup, and a wrong secret behind it
// meets Basic's own check and firewall count; a token with the prefix but
// not a key and a secret is counted too.
function test_api_app_token_authenticates_on_the_basic_path()
{
	$auth = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/auth.php');

	pg_assert_contains("if ((\$bearer !== '') && !api_app_token_is(\$bearer)) {\n\n\t\treturn api_device_authenticate(\$bearer);", $auth, 'the device path skips a token');
	pg_assert_contains("if ((\$bearer !== '') && (api_app_token_parse(\$bearer) === null)) {\n\n\t\tapi_offence();", $auth, 'a malformed token is an offence');
	pg_assert_contains("if (!api_secret_matches(\$app, \$credentials['secret'])) {\n\n\t\tapi_offence();", $auth, 'a wrong secret is an offence');
	pg_assert_contains("return api_secret_hash_matches_app(\$app, api_secret_hash(\$supplied), time());", $auth, 'the grace is the shared one');
}

// The API's router and the MCP address both authenticate through
// api_authenticate(), so both take the token.
function test_api_app_token_on_the_api_and_the_mcp_address()
{
	pg_assert_contains('api_authenticate($input)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/bootstrap.php'), 'the API');
	pg_assert_contains('api_authenticate(array())', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php'), 'the MCP address');
	pg_assert_contains("require_once(dirname(__FILE__) . '/includes/api/bootstrap.php');", (string) file_get_contents(PG_FUNCTIONS_DIR . '/mcp.php'), 'the MCP entry loads the API');
}
