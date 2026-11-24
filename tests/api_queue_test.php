<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's walk-in queue resource
 * (includes/api/resources/queue.php) and the queue events: the QueuePlace
 * and QueueTicket objects (no link token, nulls for empty fields), the day
 * read, the route rows, the scope group and its ceiling, and the payload of
 * queue.joined, queue.called, queue.served and queue.no_show.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/queue.php');
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

function api_queue_test_ticket($changes = array())
{
	return array_merge(array(
		'id' => '41', 'place_id' => '2', 'service' => 's1', 'letter' => 'A', 'day' => '20261120', 'number' => '12',
		'state' => 'called', 'source' => 'kiosk', 'priority' => '', 'party_size' => '0', 'booking_id' => '0',
		'desk_id' => '3', 'staff_user_id' => '4', 'contact_id' => '0', 'moved_back' => '0', 'notices' => '0',
		'name' => 'Ayşe', 'phone' => '', 'email' => '', 'token_hash' => str_repeat('b', 64),
		'joined_at' => '1795676520', 'on_way_at' => '0', 'called_at' => '1795677600', 'done_at' => '0', 'cleared_at' => '0',
	), $changes);
}

function api_queue_test_place()
{
	return array(
		'id' => 2, 'name' => 'City office', 'address' => '', 'status' => 'open',
		'services' => array(array('key' => 's1', 'name' => 'Applications', 'letter' => 'A', 'minutes' => 10)),
		'settings' => array('grace_minutes' => 10),
		'waiting' => 4, 'today' => 19,
	);
}

function test_api_queue_ticket_present()
{
	$out = api_queue_ticket_present(api_queue_test_ticket(), api_queue_test_place(), 'Desk 3');

	pg_assert_same(array_keys(api_queue_ticket_schema()), array_keys($out), 'declared as built');
	pg_assert_same(41, $out['id']);
	pg_assert_same('A-012', $out['number'], 'the number the person sees');
	pg_assert_same('Applications', $out['service_name']);
	pg_assert_same('Desk 3', $out['desk_name']);
	pg_assert_same('2026-11-20', $out['day']);
	pg_assert_same('2026-11-26T07:02:00Z', $out['joined_at']);
	pg_assert_same(null, $out['done_at'], 'not ended');
	pg_assert_same(null, $out['phone'], 'empty is null');
	pg_assert_same(null, $out['priority']);
	pg_assert_same(null, $out['booking_id'], 'a walk-in');
	pg_assert_same(null, $out['party_size']);
	pg_assert_same(4, $out['staff_user_id']);
	pg_assert_same(false, $out['moved_back']);
	pg_assert_false(array_key_exists('token_hash', $out), 'the link stays on the site');
	pg_assert_false(array_key_exists('notices', $out));

	$gone = api_queue_ticket_present(api_queue_test_ticket(array('service' => 's9', 'desk_id' => '0', 'staff_user_id' => '0')), api_queue_test_place(), '');
	pg_assert_same(null, $gone['service_name'], 'a service removed since');
	pg_assert_same(null, $gone['desk_id']);
	pg_assert_same(null, $gone['desk_name']);
}

function test_api_queue_place_present()
{
	$desks = array(
		array('id' => 3, 'name' => 'Desk 3', 'services' => array('s1'), 'state' => 'open', 'user_id' => 4, 'person' => 'Bora'),
		array('id' => 5, 'name' => 'Desk 5', 'services' => array('s1'), 'state' => 'closed', 'user_id' => 0, 'person' => ''),
	);
	$out = api_queue_place_present(api_queue_test_place(), $desks);

	pg_assert_same(array_keys(api_queue_place_schema()), array_keys($out), 'declared as built');
	pg_assert_same(null, $out['address'], 'empty is null');
	pg_assert_same(array(array('key' => 's1', 'name' => 'Applications', 'letter' => 'A')), $out['services'], 'no starting minutes');
	pg_assert_same(4, $out['desks'][0]['user_id']);
	pg_assert_same(null, $out['desks'][1]['user_id'], 'a closed desk');
	pg_assert_false(array_key_exists('person', $out['desks'][0]), 'the name is not given');
	pg_assert_same(4, $out['waiting']);
	pg_assert_same(19, $out['today']);
}

function test_api_queue_day()
{
	$now = mktime(12, 0, 0, 11, 20, 2026);

	pg_assert_same(20261120, api_queue_day('', $now), 'today');
	pg_assert_same(20261031, api_queue_day('2026-10-31', $now));
	pg_assert_same(null, api_queue_day('2026-02-30', $now), 'no such day');
	pg_assert_same(null, api_queue_day('20261120', $now));
	pg_assert_same(null, api_queue_day('yesterday', $now));
}

function test_api_queue_event_payload()
{
	$out = pg_waiting_event_payload(api_queue_test_ticket(array('state' => 'served', 'done_at' => '1795678260')), api_queue_test_place(), 'Desk 3');

	pg_assert_same('A-012', $out['number']);
	pg_assert_same('City office', $out['place_name']);
	pg_assert_same('served', $out['state']);
	pg_assert_same('2026-11-26T07:31:00Z', $out['done_at']);
	pg_assert_same('', $out['phone'], 'empty stays empty in an event');
	pg_assert_false(array_key_exists('token_hash', $out));

	$waiting = pg_waiting_event_payload(api_queue_test_ticket(array('state' => 'waiting', 'called_at' => '0', 'desk_id' => '0')), api_queue_test_place(), '');
	pg_assert_same(null, $waiting['called_at'], 'no call yet');
	pg_assert_same(0, $waiting['desk_id']);
}

function test_api_queue_routes()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('queue.places', 'queue.tickets') as $id) {
		pg_assert_same('queue:read', $routes[$id]['scope'], $id);
		pg_assert_same('GET', $routes[$id]['method'], $id);
	}

	foreach (array('queue.tickets.create', 'queue.desks.call', 'queue.tickets.finish') as $id) {
		pg_assert_same('queue:write', $routes[$id]['scope'], $id);
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' is rehearsable');
		pg_assert_same('POST', $routes[$id]['method'], $id . ' never PATCH or DELETE');
		pg_assert_true(empty($routes[$id]['moves_money']), $id);
		pg_assert_true(!isset($routes[$id]['mcp']), $id . ' is an MCP tool');
	}

	pg_assert_same(201, $routes['queue.tickets.create']['status']);
	pg_assert_same(array('kiosk', 'staff'), $routes['queue.tickets.create']['params'][7]['values'], 'qr and site are the public page\'s own');

	$group = api_scope_groups();
	pg_assert_same('queue:read', $group['queue']['read']);
	pg_assert_same('queue:write', $group['queue']['write']);
}

function test_api_queue_owner_ceiling()
{
	foreach (array(0, 1, 2) as $role) {
		$scopes = api_owner_scopes(array('id' => 1, 'username' => 'staff', 'role' => $role, 'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => false));
		pg_assert_true(in_array('queue:read', $scopes, true), 'role ' . $role);
		pg_assert_true(in_array('queue:write', $scopes, true), 'role ' . $role);
	}

	// Without the database a user is on no place's staff list.
	if (!pg_waiting_ready()) {
		pg_assert_false(in_array('queue:read', api_owner_scopes(array('id' => 1, 'username' => 'u', 'role' => 3, 'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => false)), true), 'role 3 on no staff list');
	}
}
