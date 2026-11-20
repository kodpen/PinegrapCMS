<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for event tickets over the external API, the webhook, the product's
 * mark and the personal data export: GET /calendars/events/{id}/tickets (its
 * route row, scope and refusal of a key without orders:read), the ticket's
 * presenter and counts, ticket.checked_in, gives_tickets on the product
 * object and the product screen, and the export's ticket rows. No code and
 * no hash in any of them.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/event_tickets.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhook_payloads.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/event_tickets.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/event_check_in.php');

// A ticket row as the table holds it, its secret columns included, so a
// presenter that passed them on would be caught.
function event_tickets_api_test_row($overrides = array())
{
	return array_merge(array(
		'id'                => 31,
		'order_id'          => 7,
		'order_item_id'     => 12,
		'unit'              => 2,
		'product_id'        => 5,
		'calendar_event_id' => 9,
		'recurrence_number' => 3,
		'state'             => 'used',
		'used_at'           => 1763650000,
		'used_by'           => 4,
		'voided_at'         => 0,
		'created_at'        => 1763600000,
		'code_salt'         => str_repeat('a', 32),
		'code_hash'         => str_repeat('b', 64),
		'code'              => 'ABCD-EFGH-JKMN',
		'order_number'      => 1001,
		'status'            => 'complete',
		'guest_name'        => 'Ayşe Yılmaz',
		'product_name'      => 'Concert',
	), $overrides);
}

// No key and no value of the output carries the code, its salt or its hash.
function event_tickets_api_test_no_secret($out, $label)
{
	$json = json_encode($out);

	foreach (array('code_salt', 'code_hash', '"code"', str_repeat('a', 32), str_repeat('b', 64), 'ABCD-EFGH-JKMN') as $secret) {
		pg_assert_false(strpos($json, $secret) !== false, $label . ': no ' . $secret);
	}
}

function event_tickets_api_test_routes()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

function test_event_tickets_api_route_row()
{
	$routes = event_tickets_api_test_routes();

	pg_assert_true(isset($routes['calendars.event_tickets']), 'the route is declared');

	$route = $routes['calendars.event_tickets'];

	pg_assert_same('GET', $route['method'], 'a read');
	pg_assert_same('/calendars/events/{id}/tickets', $route['path'], 'its path');
	pg_assert_same('orders:read', $route['scope'], 'under the orders scope');
	pg_assert_same(array('list' => 'EventTicket', 'counts' => 'EventTicketCounts'), $route['returns'], 'tickets and counts');
	pg_assert_true(function_exists($route['handler']), 'the handler exists');

	$names = array_map(function ($param) { return $param['name']; }, $route['params']);
	pg_assert_same(array('id', 'recurrence_number', 'state', 'limit', 'cursor', 'include_count'), $names, 'its parameters');
}

// A key without orders:read is refused before the handler, whatever
// calendar scope it holds; an owner without the e-commerce right cannot
// give a key orders:read.
function test_event_tickets_api_key_without_the_right_refused()
{
	$scope = event_tickets_api_test_routes()['calendars.event_tickets']['scope'];

	pg_assert_false(api_has_scope(array('calendars:read', 'calendars:write'), $scope), 'calendars:* is not enough');
	pg_assert_true(api_has_scope(array('orders:read'), $scope), 'orders:read is');

	$owner = array('id' => 2, 'role' => 3, 'manage_ecommerce' => '', 'manage_contacts' => '', 'manage_calendars' => 'yes', 'manage_emails' => '', 'manage_forms' => '', 'manage_visitors' => '');
	pg_assert_false(in_array('orders:read', api_owner_scopes($owner), true), 'a user without the e-commerce right cannot hold it');
}

function test_event_tickets_api_presenter_fields_and_no_code()
{
	$out = api_event_ticket_present(event_tickets_api_test_row());

	pg_assert_same(array_keys(api_event_ticket_schema()), array_keys($out), 'the declared fields, in order');
	pg_assert_same(31, $out['id'], 'id');
	pg_assert_same(1001, $out['order_number'], 'order number');
	pg_assert_same('complete', $out['order_status'], 'order status');
	pg_assert_same(2, $out['unit'], 'unit');
	pg_assert_same(5, $out['product_id'], 'product');
	pg_assert_same(9, $out['calendar_event_id'], 'event');
	pg_assert_same(3, $out['recurrence_number'], 'repeat');
	pg_assert_same('used', $out['state'], 'state');
	pg_assert_same('Ayşe Yılmaz', $out['guest_name'], 'guest');
	pg_assert_same(4, $out['checked_in_by'], 'checked in by');
	pg_assert_true(is_string($out['checked_in_at']), 'checked in at');
	pg_assert_same(null, $out['voided_at'], 'not void');
	event_tickets_api_test_no_secret($out, 'presenter');

	$valid = api_event_ticket_present(event_tickets_api_test_row(array('state' => 'valid', 'used_at' => 0, 'used_by' => 0, 'product_id' => 0, 'order_number' => 0)));
	pg_assert_same(null, $valid['checked_in_at'], 'not checked in');
	pg_assert_same(null, $valid['checked_in_by'], 'nobody');
	pg_assert_same(null, $valid['product_id'], 'no product');
	pg_assert_same(null, $valid['order_number'], 'no number yet');
}

function test_event_tickets_api_counts()
{
	pg_assert_same(
		array('sold' => 5, 'checked_in' => 2, 'void' => 1),
		api_event_ticket_counts(array(
			array('state' => 'valid', 'n' => '3'),
			array('state' => 'used', 'n' => '2'),
			array('state' => 'void', 'n' => '1'),
		)),
		'sold is valid and used');
	pg_assert_same(array('sold' => 0, 'checked_in' => 0, 'void' => 0), api_event_ticket_counts(array()), 'none');
	pg_assert_same(array_keys(api_event_ticket_counts_schema()), array_keys(api_event_ticket_counts(array())), 'declared');
}

// The handler reads named columns, never the secret ones.
function test_event_tickets_api_handler_reads_no_secret_column()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/event_tickets.php');

	pg_assert_false(strpos($source, 'code_hash') !== false, 'no code_hash');
	pg_assert_false(strpos($source, 'code_salt') !== false, 'no code_salt');
	pg_assert_false(strpos($source, 'event_tickets.*') !== false, 'no star');
	pg_assert_contains('api_calendar_event_visible_or_fail(', $source, 'the calendar row rule');
}

function test_event_tickets_api_checked_in_event()
{
	pg_assert_true(isset(api_webhook_events()['ticket.checked_in']), 'in the catalogue');

	$payloads = api_webhook_event_payloads();
	pg_assert_true(isset($payloads['ticket.checked_in']), 'fields and sample');

	$data = pg_check_in_ticket_event(event_tickets_api_test_row());
	pg_assert_same(array_keys($payloads['ticket.checked_in']['fields']), array_keys($data), 'the fields');
	pg_assert_same('2025-11-20T14:46:40Z', $data['checked_in_at'], 'UTC');
	event_tickets_api_test_no_secret($data, 'event');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/event_check_in.php');
	pg_assert_contains("pg_announce('ticket.checked_in', pg_check_in_ticket_event(\$ticket))", $screen, 'announced from the door');
	pg_assert_contains("\$result['answer'] === 'valid'", $screen, 'only when this scan marked it');
}

function test_event_tickets_api_product_mark()
{
	pg_assert_same('boolean', api_product_schema()['gives_tickets'], 'declared on the product');

	$routes = event_tickets_api_test_routes();

	foreach (array('products.create', 'products.update') as $id) {
		$names = array_map(function ($param) { return $param['name']; }, $routes[$id]['params']);
		pg_assert_true(in_array('gives_tickets', $names, true), $id . ' takes it');
	}

	pg_assert_same(array(), api_products_tickets_param(array('name' => 'X')), 'not sent, not written');

	$builder = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	pg_assert_contains("'name'  => 'gives_tickets'", $builder, 'the switch on the product screen');
	pg_assert_contains("\$common['gives_tickets'] = \$switch('gives_tickets');", $builder, 'read from the screen');
	pg_assert_contains('if (pg_pb_tickets_mark_drawn()) {', $builder, 'only where it is drawn');
}

function test_event_tickets_export_row_has_no_code()
{
	$row = pg_event_tickets_export_row(event_tickets_api_test_row(), array('name' => 'Jazz Night', 'start_date_and_time' => '2026-12-01 20:00:00'));

	pg_assert_same(array('id', 'order_id', 'order_number', 'unit', 'event', 'event_date', 'product', 'state', 'checked_in_at'), array_keys($row), 'its fields');
	pg_assert_same('Jazz Night', $row['event'], 'event');
	pg_assert_same('2026-12-01 20:00:00', $row['event_date'], 'date');
	pg_assert_same('1001', $row['order_number'], 'order');
	pg_assert_same('used', $row['state'], 'state');
	pg_assert_same(1763650000, $row['checked_in_at'], 'checked in');
	event_tickets_api_test_no_secret($row, 'export');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/privacy_export.php');
	$start = strpos($source, "\$sections['event_tickets']");
	pg_assert_true($start !== false, 'the export collects tickets');
	$block = substr($source, $start, 900);
	pg_assert_false(strpos($block, 'code') !== false, 'the export reads no code column');
	pg_assert_false(strpos($block, 't.*') !== false, 'nor every column');
}
