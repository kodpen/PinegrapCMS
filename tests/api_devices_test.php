<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for device sessions (includes/api/devices.php): the scope adjustments
 * on top of a device's ceiling (api_device_scopes_adjust()), and the pair a
 * device refresh token is exchanged for (api_device_token_next()): the same
 * token always gives the same pair, so exchanges that run together agree on it.
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

// A device session's scope adjustments (api_device_scopes_adjust()): never
// webhooks:manage, always the person's own account, and with workspace:write
// the mail and personal plans scopes, each within the owner's rights.
function test_api_devices_scopes_adjust_adds_workspace_scopes_with_write()
{
	$owner = array('workspace:read', 'workspace:write', 'workspace:mail_send', 'workspace:personal_plans', 'account:read', 'account:write', 'meta:read');

	pg_assert_same(
		array('account:read', 'account:write', 'meta:read', 'workspace:mail_send', 'workspace:personal_plans', 'workspace:read', 'workspace:write'),
		api_device_scopes_adjust(array('workspace:write', 'workspace:read', 'meta:read', 'webhooks:manage'), $owner),
		'write brings mail and personal plans; webhooks:manage is removed'
	);

	pg_assert_same(
		array('account:read', 'account:write', 'meta:read', 'workspace:read'),
		api_device_scopes_adjust(array('workspace:read', 'meta:read'), $owner),
		'read alone brings neither'
	);
}

function test_api_devices_scopes_adjust_stays_within_the_owner_rights()
{
	pg_assert_same(
		array('account:read', 'account:write', 'workspace:write'),
		api_device_scopes_adjust(array('workspace:write'), array('workspace:write', 'account:read', 'account:write')),
		'an owner without the two scopes gets neither'
	);

	pg_assert_same(
		array('account:read', 'account:write', 'workspace:personal_plans', 'workspace:write'),
		api_device_scopes_adjust(array('workspace:write', 'workspace:personal_plans'), array('workspace:write', 'workspace:personal_plans')),
		'a scope already held is not listed twice'
	);
}
