<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's order returns (GET and POST
 * /orders/{id}/returns, includes/api/resources/orders.php): the shape of a
 * return against its declaration, a request nobody answered yet, the lines a
 * POST names, the two route rows (scopes, rehearsal, 201 or 200, the body
 * and its limits against the return model), the delivery each line of GET
 * /orders/{id} names, and the object in the OpenAPI registry.
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

// orders.php pulls in the outbound webhook module, whose gate asks for
// init.php. Behind the gate both files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');

// The presenter's helpers live in includes/api/response.php, which loads only
// behind the API's own entry. Stand-ins of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// A return as pg_return_load() and pg_returns_of_order() give it.
function api_order_returns_test_row($changes = array())
{
	return array_merge(array(
		'id' => '12', 'order_id' => '9007', 'ship_to_id' => '9008', 'status' => 'refunded', 'reason' => 'damaged',
		'customer_note' => 'Kutu ezik geldi', 'decision_note' => 'Kargo kodu ile gönderin.', 'carrier' => 'yurtici',
		'carrier_code' => '123456', 'tracking_number' => 'YK9876', 'source' => 'api', 'requested_by' => '118',
		'decided_by' => '118', 'requested_at' => '1792731600', 'decided_at' => '1792735200', 'shipped_at' => '1792821600',
		'received_at' => '1792908000', 'refunded_at' => '1792911600', 'closed_at' => '0',
		'refund_due_notified_at' => '0', 'refund_overdue_notified_at' => '0',
		'refund_amount' => '25000', 'refund_method' => 'driver', 'refund_reference' => 'R-77', 'refund_unclear' => '0',
		'items' => array(
			array('order_item_id' => 9009, 'quantity' => 2, 'product_id' => 71, 'name' => 'Yeşil Ofis Sandalyesi'),
			array('order_item_id' => 9010, 'quantity' => 1, 'product_id' => 70, 'name' => 'Mocha Ofis Sandalyesi'),
		),
	), $changes);
}

function api_order_returns_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function api_order_returns_test_param($route, $name)
{
	foreach ($route['params'] as $param) {
		if ($param['name'] === $name) {
			return $param;
		}
	}

	return null;
}

function test_api_order_returns_present_a_refunded_return()
{
	$return = api_order_return_present(api_order_returns_test_row());

	pg_assert_same(12, $return['id']);
	pg_assert_same(9007, $return['order_id']);
	pg_assert_same(9008, $return['ship_to_id']);
	pg_assert_same('refunded', $return['status']);
	pg_assert_same(pg_return_status_label('refunded'), $return['status_label']);
	pg_assert_same('damaged', $return['reason']);
	pg_assert_same(pg_return_reasons()['damaged'], $return['reason_label']);
	pg_assert_same('Kutu ezik geldi', $return['customer_note']);
	pg_assert_same('Kargo kodu ile gönderin.', $return['decision_note']);
	pg_assert_same('api', $return['source']);
	pg_assert_same('yurtici', $return['carrier']);
	pg_assert_same('123456', $return['carrier_code']);
	pg_assert_same('YK9876', $return['tracking_number']);
	pg_assert_same(array(array('order_item_id' => 9009, 'quantity' => 2), array('order_item_id' => 9010, 'quantity' => 1)), $return['items'], 'ids and quantities only');
	pg_assert_same('2026-10-23T05:00:00Z', $return['requested_at']);
	pg_assert_same('2026-10-25T07:00:00Z', $return['refunded_at']);
	pg_assert_same(null, $return['closed_at'], 'a status not reached');

	pg_assert_same(array_keys(api_order_return_schema()), array_keys($return), 'every field declared, in order');
}

// A request nobody answered yet: no carrier, no decision, the stamps of the
// statuses it has not reached are null.
function test_api_order_returns_present_a_new_request()
{
	$return = api_order_return_present(api_order_returns_test_row(array(
		'status' => 'requested', 'decision_note' => null, 'carrier' => '', 'carrier_code' => '', 'tracking_number' => '',
		'decided_at' => '0', 'shipped_at' => '0', 'received_at' => '0', 'refunded_at' => '0', 'reason' => 'gone', 'items' => array(),
	)));

	pg_assert_same(null, $return['carrier'], 'no carrier chosen yet');
	pg_assert_same('', $return['decision_note'], 'never null: declared as a string');
	pg_assert_same(null, $return['reason_label'], 'a code the product has no name for');
	pg_assert_same(null, $return['decided_at']);
	pg_assert_same('2026-10-23T05:00:00Z', $return['requested_at'], 'written by every create path');
	pg_assert_same(array(), $return['items']);

	$schema = api_order_return_schema();

	foreach (array('carrier', 'reason_label', 'decided_at', 'shipped_at', 'received_at', 'refunded_at', 'closed_at') as $field) {
		pg_assert_same('string?', $schema[$field], $field . ' may be null');
	}

	foreach (array('status_label', 'customer_note', 'decision_note', 'carrier_code', 'tracking_number', 'requested_at') as $field) {
		pg_assert_same('string', $schema[$field], $field . ' is never null');
	}
}

// The refund the return keeps: null until it was refunded, then what the
// order.return.refunded event carried.
function test_api_order_returns_present_the_refund()
{
	pg_assert_same(array('amount' => 25000, 'method' => 'driver', 'reference' => 'R-77', 'unclear' => false),
		api_order_return_present(api_order_returns_test_row())['refund'], 'a refund through the payment service');

	pg_assert_same(array('amount' => 0, 'method' => 'manual', 'reference' => null, 'unclear' => false),
		api_order_return_present(api_order_returns_test_row(array('refund_amount' => '0', 'refund_method' => 'manual', 'refund_reference' => '')))['refund'],
		'0 recorded, no reference');

	pg_assert_same(array('amount' => 25000, 'method' => 'driver', 'reference' => null, 'unclear' => true),
		api_order_return_present(api_order_returns_test_row(array('refund_reference' => '', 'refund_unclear' => '1')))['refund'],
		'no clear answer from the service');

	pg_assert_same(array('amount' => null, 'method' => null, 'reference' => null, 'unclear' => false),
		api_order_return_present(api_order_returns_test_row(array('refund_amount' => '0', 'refund_method' => '', 'refund_reference' => '')))['refund'],
		'refunded before the columns, nothing found to fill them: the amount is not known either');

	pg_assert_same(array('amount' => 25000, 'method' => 'driver', 'reference' => null, 'unclear' => true),
		api_order_return_present(api_order_returns_test_row(array('refund_reference' => '', 'refund_unclear' => '1', 'status' => 'refunded')))['refund'],
		'while the payment service is being asked');

	pg_assert_same(array('amount' => 25000, 'method' => 'driver', 'reference' => 'R-77', 'unclear' => false),
		api_order_return_present(api_order_returns_test_row(array('status' => 'closed', 'closed_at' => '1792998000')))['refund'],
		'closed after the refund');

	pg_assert_same(null, api_order_return_present(api_order_returns_test_row(array('status' => 'received', 'refunded_at' => '0', 'refund_method' => '', 'refund_amount' => '0', 'refund_reference' => '')))['refund'], 'not refunded');

	$before = api_order_returns_test_row();
	unset($before['refund_amount'], $before['refund_method'], $before['refund_reference'], $before['refund_unclear']);

	pg_assert_same(null, api_order_return_present($before)['refund'], 'the columns are not there yet');

	pg_assert_same('OrderReturnRefund?', api_order_return_schema()['refund'], 'a nested object that may be null');
	pg_assert_same(array('amount' => 'integer?', 'method' => 'string?', 'reference' => 'string?', 'unclear' => 'boolean'), api_order_return_refund_schema());
	pg_assert_same(array_keys(api_order_return_refund_schema()), array_keys(api_order_return_present(api_order_returns_test_row())['refund']), 'every refund field declared, in order');
}

// The lines of a POST, as pg_return_create() takes them.
function test_api_order_returns_lines_of_a_request()
{
	$lines = api_order_returns_lines(array(array('order_item_id' => 9009, 'quantity' => 2), array('order_item_id' => '9010', 'quantity' => 1)));

	pg_assert_true($lines['ok']);
	pg_assert_same(array(9009 => 2, 9010 => 1), $lines['quantities'], 'order item id => quantity, the quantity as sent');

	$kept = api_order_returns_lines(array(array('order_item_id' => 9009, 'quantity' => 2.5)));

	pg_assert_true($kept['ok'], 'the quantity is judged by the return model');
	pg_assert_same(array(9009 => 2.5), $kept['quantities']);

	foreach (array(
		'not an object'      => array(9009),
		'no quantity'        => array(array('order_item_id' => 9009)),
		'no line'            => array(array('quantity' => 1)),
		'line not a number'  => array(array('order_item_id' => 'abc', 'quantity' => 1)),
		'line below one'     => array(array('order_item_id' => 0, 'quantity' => 1)),
		'line as a list'     => array(array('order_item_id' => array(9009), 'quantity' => 1)),
		'quantity as a list' => array(array('order_item_id' => 9009, 'quantity' => array(1))),
	) as $label => $items) {
		$refused = api_order_returns_lines($items);

		pg_assert_false($refused['ok'], $label);
		pg_assert_same(array(), $refused['quantities'], $label);
		pg_assert_true($refused['error'] !== '', $label . ' says why');
	}

	$twice = api_order_returns_lines(array(array('order_item_id' => 9009, 'quantity' => 1), array('order_item_id' => 9009, 'quantity' => 1)));

	pg_assert_false($twice['ok'], 'a line named twice');

	$null_first = api_order_returns_lines(array(array('order_item_id' => 9009, 'quantity' => null), array('order_item_id' => 9009, 'quantity' => 1)));

	pg_assert_false($null_first['ok'], 'a line named twice, the first time with no quantity');
	pg_assert_same(array(9009 => null), api_order_returns_lines(array(array('order_item_id' => 9009, 'quantity' => null)))['quantities'], 'a lone null goes on to the return model, which refuses it');
	pg_assert_same(true, api_order_returns_lines(array())['ok'], 'an empty list is the return model\'s to refuse');
}

function test_api_order_returns_list_route()
{
	$route = api_order_returns_test_route('orders.returns');

	pg_assert_true($route !== null, 'the route exists');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/orders/{id}/returns', $route['path']);
	pg_assert_same('orders:read', $route['scope']);
	pg_assert_same('api_orders_returns', $route['handler']);
	pg_assert_true(function_exists($route['handler']), 'the handler is declared');
	pg_assert_same(array('list' => 'OrderReturn'), $route['returns']);
	pg_assert_same(1, count($route['params']), 'the order id and nothing else: one page, no cursor');
	pg_assert_contains('but no reference', $route['description'], 'says what the event carries of the refund the return keeps');
	pg_assert_contains('a closed return keeps it', $route['description']);
	pg_assert_contains('while it is being asked', $route['description'], 'names the unconfirmed state of a refund in flight');
	pg_assert_false(strpos($route['description'], 'come only with') !== false, 'the refund is no longer only in the event');
}

function test_api_order_returns_create_route()
{
	$route = api_order_returns_test_route('orders.returns.create');

	pg_assert_true($route !== null, 'the route exists');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/orders/{id}/returns', $route['path']);
	pg_assert_same('orders:write', $route['scope']);
	pg_assert_same('api_orders_returns_create', $route['handler']);
	pg_assert_true(function_exists($route['handler']), 'the handler is declared');
	pg_assert_same(true, $route['dry_run'], 'rehearsable');
	pg_assert_same('OrderReturn', $route['returns']);
	pg_assert_same(array(201, 200), api_openapi_success_codes($route), '201 for a new return, 200 for the same request sent again');
	pg_assert_false(isset($route['mcp']), 'an MCP tool like any other write');

	pg_assert_same(array('id', 'ship_to_id', 'items', 'reason', 'note', 'notify'), array_map(function ($param) { return $param['name']; }, $route['params']));

	$items = api_order_returns_test_param($route, 'items');

	pg_assert_same('list', $items['type']);
	pg_assert_same(array('order_item_id' => 'integer', 'quantity' => 'integer'), $items['of']);
	pg_assert_same(PG_RETURN_MAX_LINES, $items['max_items'], 'as many lines as one return may list');
	pg_assert_true(!empty($items['required']));

	$reason = api_order_returns_test_param($route, 'reason');

	pg_assert_same('enum', $reason['type']);
	pg_assert_same(array_keys(pg_return_reasons()), $reason['values'], 'the return model\'s reason codes');
	pg_assert_true(!empty($reason['required']));

	$note = api_order_returns_test_param($route, 'note');

	pg_assert_same(PG_RETURN_NOTE_MAX, $note['max_length'], 'the longest note the model keeps');
	pg_assert_true(empty($note['required']), 'needed only with reason other');

	pg_assert_same('int', api_order_returns_test_param($route, 'ship_to_id')['type']);
	pg_assert_true(empty(api_order_returns_test_param($route, 'ship_to_id')['required']), 'optional while the goods went to one address');
	pg_assert_same('bool', api_order_returns_test_param($route, 'notify')['type']);

	foreach (array('order_not_returnable', 'no_shipping_address', 'return_in_progress', 'order.return.requested') as $named) {
		pg_assert_contains($named, $route['description'], 'the description names ' . $named);
	}

	foreach (array('withdrawal period', 'goods have left', 'delivery date is known') as $skipped) {
		pg_assert_contains($skipped, $route['description'], 'the description says the customer\'s rule is not checked: ' . $skipped);
	}

	pg_assert_contains('ship_to_id', $items['description'], 'lines are matched to the delivery by their ship_to_id');
}

// A return names lines of one delivery, so GET /orders/{id} says which
// delivery each line went in; a line sent to no address has none.
function test_api_order_returns_order_lines_name_their_delivery()
{
	$schema = api_order_schema();

	pg_assert_same('integer?', $schema['items'][0]['ship_to_id'] ?? null, 'declared, null for a line sent to no address');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');
	$start = strpos($source, "\nfunction api_orders_get(");
	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	pg_assert_contains('order_items.ship_to_id', $body, 'read with the line');
	pg_assert_contains("'ship_to_id'     => ((int)\$item['ship_to_id'] > 0) ? (int)\$item['ship_to_id'] : null,", $body, 'null for 0');
}

// The customer's words fit their column: a TEXT, wider than the limit.
function test_api_order_returns_note_fits_its_column()
{
	$step = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_true(preg_match('/^\s*customer_note\s+TEXT NULL,\s*$/m', $step) === 1, 'order_returns.customer_note is TEXT (65535 bytes, 2000 characters of four bytes fit)');
}

function test_api_order_returns_object_is_registered()
{
	$objects = api_openapi_objects();

	pg_assert_same('api_order_return_schema', $objects['OrderReturn'] ?? null);
	pg_assert_same('api_order_return_refund_schema', $objects['OrderReturnRefund'] ?? null, 'the refund is an object of its own, so it can be null');
	pg_assert_true(function_exists('api_order_return_schema'));

	$catalogue = api_error_catalogue();

	pg_assert_same(503, $catalogue['service_unavailable'][0], 'the answer before the update is a catalogued code');
}
