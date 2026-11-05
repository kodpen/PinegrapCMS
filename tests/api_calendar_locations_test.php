<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the event locations over the external API (/calendars/locations
 * and its writes), the write path they share with
 * add_calendar_event_location.php and edit_calendar_event_location.php
 * (includes/fn/calendar_writes.php), GET /calendars/events/{id}/reservations
 * and the calendar_event.reserved webhook: the route rows and their scope,
 * the name limit against its column, the user-role refusal, the checks before
 * the dry-run stop, the presenters, the event's payload and where it is
 * announced.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/calendars.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function api_calendar_locations_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function api_calendar_locations_test_body($relative, $name)
{
	$source = api_calendar_locations_test_source($relative);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function api_calendar_locations_test_routes()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

function test_api_calendar_locations_routes()
{
	$expected = array(
		'calendars.locations'            => array('GET', '/calendars/locations', 'api_calendar_locations_list', 'calendars:read'),
		'calendars.location_get'         => array('GET', '/calendars/locations/{id}', 'api_calendar_locations_get', 'calendars:read'),
		'calendars.location_create'      => array('POST', '/calendars/locations', 'api_calendar_locations_create', 'calendars:write'),
		'calendars.location_update'      => array('POST', '/calendars/locations/{id}', 'api_calendar_locations_update', 'calendars:write'),
		'calendars.location_delete'      => array('DELETE', '/calendars/locations/{id}', 'api_calendar_locations_delete', 'calendars:write'),
		'calendars.location_delete_post' => array('POST', '/calendars/locations/{id}/delete', 'api_calendar_locations_delete', 'calendars:write'),
		'calendars.event_reservations'   => array('GET', '/calendars/events/{id}/reservations', 'api_calendar_event_reservations', 'calendars:read'),
	);

	$routes = api_calendar_locations_test_routes();

	foreach ($expected as $id => $row) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');

		if (!isset($routes[$id])) {
			continue;
		}

		$route = $routes[$id];

		pg_assert_same($row[0], $route['method'], $id . ' method');
		pg_assert_same($row[1], $route['path'], $id . ' path');
		pg_assert_same($row[2], $route['handler'], $id . ' handler');
		pg_assert_same($row[3], $route['scope'], $id . ' scope');
		pg_assert_true(function_exists($route['handler']), $route['handler'] . '() exists');
		pg_assert_same($row[3] === 'calendars:write', !empty($route['dry_run']), $id . ' takes X-Dry-Run when it writes');

		foreach ($route['params'] as $param) {
			if ($param['name'] === 'name') {
				// calendar_event_locations.name is varchar(100).
				pg_assert_same(100, $param['max_length'] ?? null, $id . ' name fits its column');
			}
		}
	}

	pg_assert_same(array('PATCH'), $routes['calendars.location_update']['also_accepts'] ?? null, 'the update also accepts PATCH');
}

// /calendars/locations must not be read as POST /calendars/{id}: the router
// matches an int placeholder against digits only (api_route_pattern()).
function test_api_calendar_locations_path_is_not_a_calendar_id()
{
	$routes = api_calendar_locations_test_routes();

	foreach (array('calendars.update', 'calendars.delete_post') as $id) {
		foreach ($routes[$id]['params'] as $param) {
			if ($param['name'] === 'id') {
				pg_assert_same('int', $param['type'], $id . ' takes a numeric id only');
			}
		}
	}
}

function test_api_calendar_locations_presenters()
{
	pg_assert_same(
		array('id' => 2, 'name' => 'Hall A', 'event_count' => 5, 'created_at' => '2026-11-01T00:00:00Z', 'updated_at' => null),
		api_calendar_location_present(array('id' => '2', 'name' => 'Hall A', 'event_count' => '5', 'created_timestamp' => (string) gmmktime(0, 0, 0, 11, 1, 2026), 'last_modified_timestamp' => '0')),
		'a location row on the wire'
	);

	pg_assert_same(array_keys(api_calendar_location_schema()), array_keys(api_calendar_location_present(array('id' => 1, 'name' => '', 'event_count' => 0, 'created_timestamp' => 0, 'last_modified_timestamp' => 0))), 'the location presenter answers the declared fields');

	$row = array(
		'id' => '31', 'order_id' => '9', 'order_number' => '1004', 'status' => 'complete',
		'recurrence_number' => '2', 'quantity' => '3', 'order_date' => '0', 'contact_id' => '7',
		'billing_first_name' => 'Ada', 'billing_last_name' => 'Lovelace', 'billing_email_address' => 'ada@example.com',
	);

	$with = api_calendar_reservation_present($row, true);

	pg_assert_same(array_keys(api_calendar_reservation_schema()), array_keys($with), 'the reservation presenter answers the declared fields');
	pg_assert_same(array('contact_id' => 7, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com'), $with['customer'], 'the customer of the order');
	pg_assert_same(array_keys(api_calendar_reservation_customer_schema()), array_keys($with['customer']), 'the customer answers the declared fields');
	pg_assert_same(1004, $with['order_number'], 'the order number');
	pg_assert_same(2, $with['recurrence_number'], 'the repeat');
	pg_assert_same(3, $with['quantity'], 'the spots');

	pg_assert_same(null, api_calendar_reservation_present($row, false)['customer'], 'no customer for an owner who may not open orders');
}

// The location screens open to roles 0 to 2, so every location route refuses
// the user role, before any read or write.
function test_api_calendar_locations_refuse_the_user_role()
{
	$file = 'includes/api/resources/calendars.php';
	$require = api_calendar_locations_test_body($file, 'api_calendar_locations_require');

	pg_assert_contains("(int)\$app['owner']['role'] >= 3", $require, 'the user role is refused');
	pg_assert_contains("api_fail(403, 'forbidden'", $require, 'with 403 forbidden');

	foreach (array('api_calendar_locations_list', 'api_calendar_locations_get', 'api_calendar_locations_create', 'api_calendar_locations_update', 'api_calendar_locations_delete') as $handler) {
		$body = api_calendar_locations_test_body($file, $handler);

		pg_assert_contains('api_calendar_locations_require();', $body, $handler . ' refuses the user role');

		$stop = strpos($body, 'api_dry_run_stop(');

		if ($stop !== false) {
			pg_assert_true(strpos($body, 'api_calendar_locations_require();') < $stop, $handler . ' refuses the role before the dry-run stop');
		}
	}
}

// Every check runs before the dry-run stop, and the write goes through the
// functions the screens call.
function test_api_calendar_locations_check_before_stop()
{
	$file = 'includes/api/resources/calendars.php';

	$create = api_calendar_locations_test_body($file, 'api_calendar_locations_create');
	pg_assert_true(strpos($create, 'api_calendar_location_name_in(') < strpos($create, 'api_dry_run_stop('), 'create checks the name first');
	pg_assert_true(strpos($create, 'api_dry_run_stop(') < strpos($create, 'pg_calendar_location_save(0,'), 'create writes after the stop');

	$update = api_calendar_locations_test_body($file, 'api_calendar_locations_update');
	pg_assert_true(strpos($update, "api_calendar_location_or_fail(\$params['id'])") < strpos($update, 'api_dry_run_stop('), 'update finds the location first');
	pg_assert_true(strpos($update, 'api_calendar_location_name_in(') < strpos($update, 'api_dry_run_stop('), 'update checks the name first');
	pg_assert_true(strpos($update, 'api_dry_run_stop(') < strpos($update, 'pg_calendar_location_save($id,'), 'update writes after the stop');

	$delete = api_calendar_locations_test_body($file, 'api_calendar_locations_delete');
	pg_assert_true(strpos($delete, "api_calendar_location_or_fail(\$params['id'])") < strpos($delete, 'api_dry_run_stop('), 'delete finds the location first');
	pg_assert_true(strpos($delete, 'api_dry_run_stop(') < strpos($delete, 'pg_calendar_location_delete($id)'), 'delete writes after the stop');

	$name = api_calendar_locations_test_body($file, 'api_calendar_location_name_in');
	pg_assert_contains('pg_calendar_location_name_taken($name, $except_id)', $name, 'the name check is the screens\' lookup');
	pg_assert_contains("api_fail(409, 'name_taken'", $name, 'a taken name is 409 name_taken');
}

function test_api_calendar_locations_screens_share_the_write_path()
{
	$add = api_calendar_locations_test_source('add_calendar_event_location.php');

	pg_assert_contains('pg_calendar_location_save(0, ', $add, 'add_calendar_event_location.php creates through the shared function');
	pg_assert_false(strpos($add, 'INSERT INTO') !== false, 'add_calendar_event_location.php holds no insert of its own');

	$edit = api_calendar_locations_test_source('edit_calendar_event_location.php');

	pg_assert_contains('pg_calendar_location_save($id, ', $edit, 'edit_calendar_event_location.php renames through the shared function');
	pg_assert_contains('pg_calendar_location_delete($id)', $edit, 'edit_calendar_event_location.php deletes through the shared function');
	pg_assert_false(strpos($edit, 'DELETE FROM') !== false, 'edit_calendar_event_location.php holds no delete of its own');
	pg_assert_false(strpos($edit, 'UPDATE calendar_event_locations') !== false, 'edit_calendar_event_location.php holds no update of its own');

	pg_assert_contains('pg_calendar_location_name_taken(', api_calendar_locations_test_body('includes/forms/calendar_event_location_form.php', 'pg_calendar_event_location_form_name_in_use'), 'the form\'s name check is the shared lookup');

	pg_assert_contains("'calendar_events_calendar_event_locations_xref'", api_calendar_locations_test_body('includes/fn/calendar_writes.php', 'pg_calendar_location_delete'), 'a deleted location leaves no bookings');
}

// The reservations of an event reach only an event on the owner's calendars
// and only placed orders.
function test_api_calendar_event_reservations_rules()
{
	$file = 'includes/api/resources/calendars.php';
	$body = api_calendar_locations_test_body($file, 'api_calendar_event_reservations');

	pg_assert_contains("api_calendar_event_visible_or_fail(\$params['id'])", $body, 'the event row rule applies');
	pg_assert_contains("orders.status != 'incomplete'", $body, 'a cart is not a reservation');
	pg_assert_contains('pg_order_item_active_sql()', $body, 'a line saved for later is not a reservation');
	pg_assert_contains('api_calendar_owner_reads_orders()', $body, 'the customer follows the order right');

	pg_assert_contains('api_calendar_ids_visible()', api_calendar_locations_test_body($file, 'api_calendar_event_visible_or_fail'), 'an event outside the assigned calendars is not found');
}

function test_api_calendar_event_reserved_is_listed()
{
	pg_assert_true(api_webhook_event_is_valid('calendar_event.reserved'), 'calendar_event.reserved can be subscribed to');
}

function test_api_calendar_event_reserved_payload()
{
	pg_assert_same(
		array('id' => 12, 'recurrence_number' => 3, 'quantity' => 2, 'order_item_id' => 81, 'order_id' => 40, 'order_number' => 1007, 'contact_id' => 5),
		pg_order_reservation_event(
			array('calendar_event_id' => '12', 'recurrence_number' => '3', 'quantity' => '2', 'order_item_id' => '81'),
			'5',
			array('order_id' => 40, 'order_number' => '1007')
		),
		'the event names the event, the repeat, the spots and the order'
	);

	pg_assert_same(null, pg_order_reservation_event(array('calendar_event_id' => 1), 0, array())['contact_id'], 'no contact is null');
}

// Announced where the spots are taken, after the reservations check, so every
// path that completes an order announces it.
function test_api_calendar_event_reserved_is_announced_at_completion()
{
	$file = 'includes/fn/order_completion.php';
	$body = api_calendar_locations_test_body($file, 'pg_order_complete_line_reservation');

	pg_assert_contains("pg_announce('calendar_event.reserved', pg_order_reservation_event(", $body, 'the reservation is announced');
	pg_assert_true(strpos($body, "(\$line['reservations'] != 1)") < strpos($body, 'pg_announce('), 'only for an event that takes reservations');

	pg_assert_contains("pg_order_complete_line_reservation(\$product, \$contact_id, array('order_id' => \$order_id", api_calendar_locations_test_body($file, 'pg_order_complete_lines'), 'the order is passed on');
}

// The buyer's name and e-mail on a reservation are order data: they need the
// owner's order right and the key's orders:read, never calendars:read alone.
function test_api_calendar_reservation_customer_needs_orders_scope()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

	$admin = array('role' => 0);

	pg_assert_false(api_calendar_reservation_customer_shown($admin, array('calendars:read')), 'an administrator\'s key holding calendars:read alone gets no customer');
	pg_assert_true(api_calendar_reservation_customer_shown($admin, array('calendars:read', 'orders:read')), 'a key holding orders:read gets the customer');
	pg_assert_false(api_calendar_reservation_customer_shown(array('role' => 3, 'manage_ecommerce' => ''), array('calendars:read', 'orders:read')), 'an owner without the order right gets no customer');
	pg_assert_true(api_calendar_reservation_customer_shown(array('role' => 3, 'manage_ecommerce' => 'yes'), array('calendars:read', 'orders:read')), 'the e-commerce right with orders:read gets the customer');

	pg_assert_contains('api_calendar_reservation_customer_shown($app[\'owner\'], api_current_scopes())', api_calendar_locations_test_body('includes/api/resources/calendars.php', 'api_calendar_owner_reads_orders'), 'the handler reads the caller\'s owner and scopes');
}
