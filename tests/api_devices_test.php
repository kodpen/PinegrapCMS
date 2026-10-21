<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pair a device refresh token is exchanged for
 * (api_device_token_next(), includes/api/devices.php): the same token always
 * gives the same pair, so exchanges that run together agree on it.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/devices.php');

// Four exchanges of one refresh token, arriving together, hand out one pair:
// whichever answer the app keeps is the pair on the row.
function test_api_devices_same_refresh_token_gives_the_same_pair()
{
	$key = 'k-0123456789abcdef';
	$token = 'pgrt_' . str_repeat('ab', 24);

	pg_assert_same(api_device_token_next('refresh', $token, $key), api_device_token_next('refresh', $token, $key), 'refresh');
	pg_assert_same(api_device_token_next('access', $token, $key), api_device_token_next('access', $token, $key), 'access');
	pg_assert_same(api_device_token_next('refresh', $token, $key), api_device_token_next('refresh', ' ' . $token . "\n", $key), 'read trimmed, as its hash is');
}

// The derived tokens look like fresh ones: the prefix names the kind, then 48
// hex digits.
function test_api_devices_next_token_has_the_shape_of_a_fresh_one()
{
	$token = 'pgrt_' . str_repeat('01', 24);

	$refresh = api_device_token_next('refresh', $token, 'k');
	$access = api_device_token_next('access', $token, 'k');

	pg_assert_true((bool)preg_match('/^pgrt_[0-9a-f]{48}$/', $refresh), 'refresh: ' . $refresh);
	pg_assert_true((bool)preg_match('/^pgat_[0-9a-f]{48}$/', $access), 'access: ' . $access);
	pg_assert_same(strlen(api_device_token_new('refresh')), strlen($refresh), 'same length as a fresh refresh token');
	pg_assert_same(strlen(api_device_token_new('access')), strlen($access), 'same length as a fresh access token');
}

// The two tokens of a pair differ, the next token differs from the one
// presented, and another token or another site key gives another pair.
function test_api_devices_next_token_depends_on_kind_token_and_key()
{
	$token = 'pgrt_' . str_repeat('cd', 24);

	$refresh = api_device_token_next('refresh', $token, 'k1');

	pg_assert_true(substr($refresh, 5) !== substr(api_device_token_next('access', $token, 'k1'), 5), 'access and refresh of one pair');
	pg_assert_true($refresh !== $token, 'not the token presented');
	pg_assert_true($refresh !== api_device_token_next('refresh', 'pgrt_' . str_repeat('ce', 24), 'k1'), 'another token');
	pg_assert_true($refresh !== api_device_token_next('refresh', $token, 'k2'), 'another site key');
	pg_assert_true(api_device_token_next('refresh', $refresh, 'k1') !== $refresh, 'the next exchange moves on');
}
