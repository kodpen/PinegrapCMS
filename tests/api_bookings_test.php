<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's bookings resource
 * (includes/api/resources/bookings.php): the Booking object (no manage
 * token, nulls for empty fields, hold_until only on a hold), the day's free
 * times by start then staff member, the status filter, the events a move
 * announces, the route rows and the scope ceiling.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/bookings.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// The time helper lives in includes/api/response.php, which loads only behind
// the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

function api_bookings_test_row($changes = array())
{
	return array_merge(array(
		'id' => '12', 'service_id' => '3', 'staff_id' => '5', 'user_id' => '0',
		'name' => 'Ada', 'email' => 'ada@example.com', 'phone' => '', 'notes' => '',
		'start_at' => '1793440800', 'end_at' => '1793442600', 'slot_at' => '1793440800',
		'status' => 'confirmed', 'source' => 'api', 'manage_token' => str_repeat('a', 64),
		'hold_until' => '0', 'created_at' => '1793000000', 'updated_at' => '1793000100',
	), $changes);
}

function test_api_bookings_present()
{
	$services = array(3 => array('name' => 'Haircut', 'length_minutes' => 30));
	$staff = array(5 => array('name' => 'Bora'));
	$out = api_booking_present(api_bookings_test_row(), $services, $staff);

	pg_assert_same(12, $out['id']);
	pg_assert_same('Haircut', $out['service_name']);
	pg_assert_same('Bora', $out['staff_name']);
	pg_assert_same(null, $out['user_id'], 'no account');
	pg_assert_same(null, $out['phone'], 'empty is null');
	pg_assert_same(null, $out['notes']);
	pg_assert_same('2026-10-31T10:00:00Z', $out['start_at']);
	pg_assert_same(null, $out['hold_until'], 'not a hold');
	pg_assert_false(array_key_exists('manage_token', $out), 'the token stays on the site');
	pg_assert_false(array_key_exists('slot_at', $out));
	pg_assert_same(array_keys(api_booking_schema()), array_keys($out), 'declared as built');

	$gone = api_booking_present(api_bookings_test_row(array('service_id' => '9', 'staff_id' => '8')), $services, $staff);
	pg_assert_same(null, $gone['service_name'], 'a removed service');
	pg_assert_same(null, $gone['staff_name']);

	$held = api_booking_present(api_bookings_test_row(array('status' => 'held', 'hold_until' => '1793441400')), $services, $staff);
	pg_assert_same('2026-10-31T10:10:00Z', $held['hold_until']);
}

function test_api_bookings_slots_present()
{
	$staff = array(5 => array('name' => 'Bora'), 2 => array('name' => 'Cem'));
	$out = api_booking_slots_present(array(5 => array(5400, 1800), 2 => array(1800)), array('length_minutes' => 30), $staff);

	pg_assert_same(3, count($out));
	pg_assert_same(array(2, 5, 5), array($out[0]['staff_id'], $out[1]['staff_id'], $out[2]['staff_id']), 'by start, then staff');
	pg_assert_same('1970-01-01T00:30:00Z', $out[0]['start_at']);
	pg_assert_same('1970-01-01T01:00:00Z', $out[0]['end_at'], 'start plus the length');
	pg_assert_same('1970-01-01T01:30:00Z', $out[2]['start_at']);
	pg_assert_same('Cem', $out[0]['staff_name']);
	pg_assert_same(array_keys(api_booking_slot_schema()), array_keys($out[0]), 'declared as built');
	pg_assert_same(array(), api_booking_slots_present(array(), array('length_minutes' => 30), $staff));
}

function test_api_bookings_list_statuses()
{
	$default = api_booking_list_statuses('');
	pg_assert_false(in_array('held', $default, true), 'holds only when asked');
	pg_assert_true(in_array('cancelled', $default, true));
	pg_assert_same(array('held', 'confirmed'), api_booking_list_statuses('held, confirmed,held'));
	pg_assert_same(null, api_booking_list_statuses('confirmed,canceled'), 'an unknown status');
}

function test_api_bookings_move_event()
{
	pg_assert_same('confirmed', api_booking_move_event('requested', 'confirmed'));
	pg_assert_same('cancelled', api_booking_move_event('confirmed', 'cancelled'));
	pg_assert_same('', api_booking_move_event('held', 'confirmed'), 'pg_booking_confirm() announces it');
	pg_assert_same('', api_booking_move_event('held', 'cancelled'), 'a hold was never announced');
	pg_assert_same('', api_booking_move_event('confirmed', 'done'));
	pg_assert_same('', api_booking_move_event('confirmed', 'no_show'));
}

function test_api_bookings_routes()
{
	$routes = array();
	$order = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
		$order[] = $route['id'];
	}

	foreach (array('bookings.list', 'bookings.slots', 'bookings.get') as $id) {
		pg_assert_same('bookings:read', $routes[$id]['scope'], $id);
	}

	foreach (array('bookings.create', 'bookings.status', 'bookings.cancel') as $id) {
		pg_assert_same('bookings:write', $routes[$id]['scope'], $id);
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' is rehearsable');
		pg_assert_same('POST', $routes[$id]['method'], $id . ' never PATCH or DELETE');
	}

	pg_assert_true(array_search('bookings.slots', $order, true) < array_search('bookings.get', $order, true), '/bookings/slots is matched before /bookings/{id}');
	pg_assert_same(201, $routes['bookings.create']['status']);

	$group = api_scope_groups();
	pg_assert_same('bookings:read', $group['bookings']['read']);
	pg_assert_same('bookings:write', $group['bookings']['write']);
}

function api_bookings_test_owner($role)
{
	return array(
		'id' => 1, 'username' => 'staff', 'role' => $role,
		'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => false,
	);
}

function test_api_bookings_owner_ceiling()
{
	foreach (array(0, 1, 2) as $role) {
		$scopes = api_owner_scopes(api_bookings_test_owner($role));
		pg_assert_true(in_array('bookings:read', $scopes, true), 'role ' . $role);
		pg_assert_true(in_array('bookings:write', $scopes, true), 'role ' . $role);
	}

	// Without the database a user is no staff member.
	if (!pg_booking_ready()) {
		pg_assert_false(in_array('bookings:read', api_owner_scopes(api_bookings_test_owner(3)), true), 'role 3 without a staff row');
	}
}
