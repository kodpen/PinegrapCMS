<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pairing a team device with a code (includes/api/pairing.php,
 * POST /auth/pair): the code's shape, what the QR holds, when a
 * stored code may be exchanged, who may make one, and the route row.
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

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/keys.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/pairing.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

// A stored code as api_pairing_find() reads it.
function api_device_pairing_test_row($changes = array())
{
	return array_merge(array(
		'id'                => 31,
		'app_id'            => 4,
		'user_id'           => 7,
		'used_timestamp'    => 0,
		'expires_timestamp' => 1800000300
	), $changes);
}

// A fresh code is pgpc_ and 48 hex characters, and two are never the same.
function test_api_pairing_code_shape()
{
	$code = api_pairing_code_new();

	pg_assert_true(api_pairing_code_shape_ok($code), 'fresh code');
	pg_assert_same(53, strlen($code), 'length');
	pg_assert_true($code !== api_pairing_code_new(), 'random');

	pg_assert_false(api_pairing_code_shape_ok('pgpc_' . str_repeat('A', 48)), 'upper case');
	pg_assert_false(api_pairing_code_shape_ok('pgrt_' . str_repeat('a', 48)), 'refresh token prefix');
	pg_assert_false(api_pairing_code_shape_ok('pgpc_' . str_repeat('a', 47)), 'short');
	pg_assert_false(api_pairing_code_shape_ok(' pgpc_' . str_repeat('a', 48)), 'space');
	pg_assert_false(api_pairing_code_shape_ok(null), 'null');
}

// The QR holds JSON, not a URL, so a camera app opens nothing.
function test_api_pairing_payload()
{
	$code = 'pgpc_' . str_repeat('0f', 24);

	$payload = api_pairing_payload('https://shop.example/pinegrap/integration.php', $code);

	pg_assert_same('{"pinegrap":"pair","v":1,"api_base":"https://shop.example/pinegrap/integration.php","code":"' . $code . '"}', $payload);
	pg_assert_same(array('pinegrap' => 'pair', 'v' => 1, 'api_base' => 'https://shop.example/pinegrap/integration.php', 'code' => $code), json_decode($payload, true));
}

// A code works for its application, unused, until the second it runs out.
function test_api_pairing_usable()
{
	$now = 1800000000;

	pg_assert_true(api_pairing_usable(api_device_pairing_test_row(), 4, $now), 'fresh');
	pg_assert_true(api_pairing_usable(api_device_pairing_test_row(), 4, 1800000299), 'last second');
	pg_assert_false(api_pairing_usable(api_device_pairing_test_row(), 4, 1800000300), 'expiry edge');
	pg_assert_false(api_pairing_usable(api_device_pairing_test_row(array('used_timestamp' => $now - 5)), 4, $now), 'used');
	pg_assert_false(api_pairing_usable(api_device_pairing_test_row(), 5, $now), 'another application');
	pg_assert_false(api_pairing_usable(api_device_pairing_test_row(array('user_id' => 0)), 4, $now), 'no person');
	pg_assert_false(api_pairing_usable(null, 4, $now), 'unknown code');
}

// Who may make a code: not while signed in as someone else, not with device
// sign-in off or expired, not without any right a device could use.
function test_api_pairing_refusal()
{
	$now = 1800000000;
	$app = array('id' => 4, 'status' => 'active', 'expires_at' => 0);

	pg_assert_same('', api_pairing_refusal(false, $app, true, $now), 'allowed');
	pg_assert_same(lang('A device pairing code cannot be made while signed in as another user.'),
		api_pairing_refusal(true, $app, true, $now), 'impersonation');
	pg_assert_same(lang('Signing in from a device is not switched on for this site.'),
		api_pairing_refusal(false, null, true, $now), 'no device application');
	pg_assert_same(lang('Signing in from a device is not switched on for this site.'),
		api_pairing_refusal(false, array('id' => 4, 'status' => 'disabled', 'expires_at' => 0), true, $now), 'disabled');
	pg_assert_same(lang('Signing in from a device is not switched on for this site.'),
		api_pairing_refusal(false, array('id' => 4, 'status' => 'active', 'expires_at' => $now - 1), true, $now), 'expired');
	pg_assert_same(lang('This account has no rights that can be used from a device.'),
		api_pairing_refusal(false, $app, false, $now), 'no rights');
}

// The route is public, answers a session like /auth/login, and is no MCP tool.
function test_api_pairing_route_row()
{
	$route = null;

	foreach (api_schema() as $row) {
		if ($row['id'] === 'auth.pair') {
			$route = $row;
		}
	}

	pg_assert_true(is_array($route), 'declared');
	pg_assert_same(array('POST', '/auth/pair', '', true, 'AuthSession', 201, 'api_auth_pair'),
		array($route['method'], $route['path'], $route['scope'], $route['public'], $route['returns'], $route['status'], $route['handler']));
	pg_assert_false(pg_mcp_route_is_offered($route), 'no MCP tool');

	$catalogue = api_error_catalogue();

	pg_assert_same(401, $catalogue['pairing_invalid'][0], 'pairing_invalid is 401');
}
