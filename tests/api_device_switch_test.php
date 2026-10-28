<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Team devices switch (api_device_switch_plan() and its helpers,
 * includes/api/device_switch.php): which device application a save starts,
 * widens, makes or disables, and that a save that changes nothing writes
 * nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/device_switch.php');

// The ceiling with the Workspace on, as the switch asks for it.
function api_device_switch_test_ceiling()
{
	return array('customers:read', 'meta:read', 'orders:read', 'products:read', 'tasks:write', 'workspace:write');
}

// A device application row as api_device_switch_apps() reads it.
function api_device_switch_test_app($id, $status, $scopes, $expires_at = 0)
{
	return array(
		'id'         => $id,
		'name'       => 'App ' . $id,
		'status'     => $status,
		'expires_at' => $expires_at,
		'scopes'     => json_encode($scopes)
	);
}

// No device application yet: switching on makes one with the ceiling.
function test_api_device_switch_on_without_app_creates()
{
	$steps = api_device_switch_plan(array(), true, api_device_switch_test_ceiling(), 1800000000);

	pg_assert_same(array(array('op' => 'create', 'scopes' => api_device_switch_test_ceiling())), $steps);
}

// Off with no application: nothing to do.
function test_api_device_switch_off_without_app_does_nothing()
{
	pg_assert_same(array(), api_device_switch_plan(array(), false, api_device_switch_test_ceiling(), 1800000000));
}

// An active application keeps what the operator chose and gains the ceiling.
function test_api_device_switch_on_widens_active_app()
{
	$apps = array(api_device_switch_test_app(4, 'active', array('meta:read', 'calendars:read')));

	$steps = api_device_switch_plan($apps, true, api_device_switch_test_ceiling(), 1800000000);

	pg_assert_same(1, count($steps), 'one step');
	pg_assert_same('update', $steps[0]['op']);
	pg_assert_same(4, $steps[0]['id']);
	pg_assert_same('active', $steps[0]['status']);
	pg_assert_false($steps[0]['clear_expiry'], 'expiry untouched');
	pg_assert_same(array('calendars:read', 'customers:read', 'meta:read', 'orders:read', 'products:read', 'tasks:write', 'workspace:write'), $steps[0]['scopes']);
}

// Saving again with nothing new writes nothing, so no audit line either.
function test_api_device_switch_on_unchanged_writes_nothing()
{
	$apps = array(api_device_switch_test_app(4, 'active', api_device_switch_test_ceiling()));

	pg_assert_same(array(), api_device_switch_plan($apps, true, api_device_switch_test_ceiling(), 1800000000));
}

// A scope that becomes valid later (the Workspace turned on) is added by the
// next save.
function test_api_device_switch_later_save_adds_module_scopes()
{
	$before = array('customers:read', 'meta:read', 'orders:read', 'products:read');

	$apps = array(api_device_switch_test_app(4, 'active', $before));

	pg_assert_same(array(), api_device_switch_plan($apps, true, $before, 1800000000), 'Workspace off: nothing new');

	$steps = api_device_switch_plan($apps, true, api_device_switch_test_ceiling(), 1800000000);

	pg_assert_same(api_device_switch_test_ceiling(), $steps[0]['scopes'], 'Workspace on: its scopes added');
}

// The usable application is the one kept, even when an older one is stopped.
function test_api_device_switch_on_prefers_usable_app()
{
	$apps = array(
		api_device_switch_test_app(9, 'active', api_device_switch_test_ceiling()),
		api_device_switch_test_app(2, 'disabled', array())
	);

	pg_assert_same(array(), api_device_switch_plan($apps, true, api_device_switch_test_ceiling(), 1800000000));
}

// With every application stopped, the oldest is started again.
function test_api_device_switch_on_restarts_oldest_disabled()
{
	$apps = array(
		api_device_switch_test_app(9, 'disabled', array()),
		api_device_switch_test_app(2, 'disabled', array('meta:read'))
	);

	$steps = api_device_switch_plan($apps, true, api_device_switch_test_ceiling(), 1800000000);

	pg_assert_same(1, count($steps));
	pg_assert_same(2, $steps[0]['id'], 'oldest');
	pg_assert_same('active', $steps[0]['status']);
	pg_assert_false($steps[0]['clear_expiry']);
}

// An expired application is started again with its expiry cleared, or the
// switch would show off again after the save.
function test_api_device_switch_on_clears_past_expiry()
{
	$apps = array(api_device_switch_test_app(3, 'active', api_device_switch_test_ceiling(), 1700000000));

	$steps = api_device_switch_plan($apps, true, api_device_switch_test_ceiling(), 1800000000);

	pg_assert_same(1, count($steps));
	pg_assert_true($steps[0]['clear_expiry'], 'past expiry cleared');

	$future = array(api_device_switch_test_app(3, 'active', api_device_switch_test_ceiling(), 1900000000));

	pg_assert_same(array(), api_device_switch_plan($future, true, api_device_switch_test_ceiling(), 1800000000), 'future expiry kept');
}

// Off disables every active device application and keeps their scopes.
function test_api_device_switch_off_disables_every_active_app()
{
	$apps = array(
		api_device_switch_test_app(2, 'active', array('meta:read')),
		api_device_switch_test_app(5, 'disabled', array()),
		api_device_switch_test_app(7, 'active', array('orders:read'))
	);

	$steps = api_device_switch_plan($apps, false, api_device_switch_test_ceiling(), 1800000000);

	pg_assert_same(2, count($steps), 'two active');
	pg_assert_same(array(2, 'disabled', array('meta:read')), array($steps[0]['id'], $steps[0]['status'], $steps[0]['scopes']));
	pg_assert_same(array(7, 'disabled', array('orders:read')), array($steps[1]['id'], $steps[1]['status'], $steps[1]['scopes']));
}

// The switch shows on only while some device application lets devices in.
function test_api_device_switch_is_on()
{
	$now = 1800000000;

	pg_assert_false(api_device_switch_is_on(array(), $now), 'none');
	pg_assert_false(api_device_switch_is_on(array(api_device_switch_test_app(1, 'disabled', array())), $now), 'disabled');
	pg_assert_false(api_device_switch_is_on(array(api_device_switch_test_app(1, 'active', array(), $now - 1)), $now), 'expired');
	pg_assert_true(api_device_switch_is_on(array(api_device_switch_test_app(1, 'active', array(), $now + 60)), $now), 'expires later');
	pg_assert_true(api_device_switch_is_on(array(
		api_device_switch_test_app(1, 'disabled', array()),
		api_device_switch_test_app(2, 'active', array())
	), $now), 'one of two');
}

// The ceiling holds only scopes the catalogue knows, so a module that is off
// adds nothing unknown to the record.
function test_api_device_switch_ceiling_is_known_scopes()
{
	$ceiling = api_device_switch_ceiling();

	pg_assert_same($ceiling, api_scopes_normalise($ceiling), 'normalised');

	foreach (array('meta:read', 'orders:read', 'customers:read', 'products:read') as $scope) {
		pg_assert_contains($scope, $ceiling, $scope);
	}
}
