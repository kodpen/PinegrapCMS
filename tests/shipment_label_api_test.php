<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for carrier labels in the external API: the check split out of
 * pg_shipment_label_create() (the same refusals and messages, nothing
 * written), POST /orders/{id}/shipments/label (its rehearsal stops after the
 * check and before the service), the labels list of GET /orders/{id}
 * against its declaration, the document route's headers and the scopes of
 * the three routes. No database and no service is reached.
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

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

function shipment_label_api_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

function shipment_label_api_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

// A shipment_labels row as the table holds it.
function shipment_label_api_test_row($changes = array())
{
	return array_merge(array(
		'id' => '42', 'order_id' => '7', 'ship_to_id' => '9', 'number' => '1234567890', 'driver' => 'geliver',
		'reference' => 'shp-77', 'barcode' => 'BC77', 'label_format' => 'pdf',
		'label_file' => '7_9_geliver_0123456789abcdef.pdf', 'state' => 'active', 'is_return' => '0',
		'user_id' => '3', 'created_at' => '1790000000', 'cancelled_at' => '0', 'label_removed_at' => '0',
		'order_return_id' => '0',
	), $changes);
}

// Each refusal in the order create() met them, with the order screen's text.
function test_shipment_label_create_refusal_codes_and_messages()
{
	$order = array('status' => 'complete');

	pg_assert_same('not_ready', pg_shipment_label_create_refusal(false, $order, 0, 'geliver')['code']);
	pg_assert_same(lang('Run the software update first: the table for shipping labels is missing.'), pg_shipment_label_create_refusal(false, null, 0, '')['error']);
	pg_assert_same('not_found', pg_shipment_label_create_refusal(true, null, 0, 'geliver')['code']);
	pg_assert_same(lang('The order could not be found.'), pg_shipment_label_create_refusal(true, null, 0, '')['error']);
	pg_assert_same('not_completed', pg_shipment_label_create_refusal(true, array('status' => 'incomplete'), 1, '')['code']);
	pg_assert_same('not_completed', pg_shipment_label_create_refusal(true, array('status' => 'cancelled'), 0, 'geliver')['code']);
	pg_assert_same(lang('A label is created only for a completed order.'), pg_shipment_label_create_refusal(true, array('status' => 'cancelled'), 0, '')['error']);
	pg_assert_same('label_exists', pg_shipment_label_create_refusal(true, $order, 1, '')['code'], 'an active label is named before a missing driver');
	pg_assert_same(lang('This address already has a label. Print it again, or cancel it before creating another.'), pg_shipment_label_create_refusal(true, $order, 1, 'geliver')['error']);
	pg_assert_same(lang('This address already has a return label. Print it again, or cancel it before creating another.'), pg_shipment_label_create_refusal(true, $order, 2, 'geliver', true)['error']);
	pg_assert_same('no_driver', pg_shipment_label_create_refusal(true, array('status' => 'exported'), 0, '')['code']);
	pg_assert_same(lang('No carrier label service that ships with this address\'s carrier is switched on.'), pg_shipment_label_create_refusal(true, $order, 0, '')['error']);
	pg_assert_same(array('code' => '', 'error' => ''), pg_shipment_label_create_refusal(true, $order, 0, 'geliver'));
}

// create() refuses only through the check, and the check writes nothing and
// calls no service, so a rehearsal that stops after it changes nothing.
function test_shipment_label_create_goes_through_the_check()
{
	$create = shipment_label_api_test_source('pg_shipment_label_create');
	$check = shipment_label_api_test_source('pg_shipment_label_create_check');

	$at_check = strpos($create, 'pg_shipment_label_create_check(');
	pg_assert_true($at_check !== false, 'create() runs the check');
	pg_assert_true($at_check < strpos($create, 'pg_shipment_label_open('), 'the check comes before the service');

	foreach (array('already has', 'completed order', 'could not be found', 'is switched on', 'Run the software update') as $text) {
		pg_assert_false(strpos($create, $text) !== false, 'create() keeps no refusal of its own: ' . $text);
	}

	foreach (array('INSERT', 'UPDATE ', 'DELETE', 'db("', 'pg_label_call', 'pg_shipment_record', 'pg_shipment_label_store', 'log_activity', 'file_put_contents') as $write) {
		pg_assert_false(strpos($check, $write) !== false, 'the check writes nothing: ' . $write);
	}

	pg_assert_true(strpos($check, 'pg_shipment_label_create_refusal(') !== false, 'the check refuses through the shared rule');
}

// The rehearsal stops after the check and before create(); a refusal of
// either is the same HTTP answer.
function test_shipment_label_api_create_rehearsal_stops_before_the_service()
{
	$handler = shipment_label_api_test_source('api_orders_label_create');

	$check = strpos($handler, 'pg_shipment_label_create_check(');
	$stop = strpos($handler, 'api_dry_run_stop(');
	$create = strpos($handler, 'pg_shipment_label_create(');

	pg_assert_true(($check !== false) && ($stop !== false) && ($create !== false));
	pg_assert_true($check < $stop, 'every check runs in a rehearsal');
	pg_assert_true($stop < $create, 'a rehearsal never reaches the service');
	pg_assert_false(strpos(substr($handler, 0, $stop), 'api_exec(') !== false, 'nothing is written above the stop');
}

function test_shipment_label_api_refusal_answers()
{
	pg_assert_same(array(503, 'service_unavailable', 'x'), api_order_label_refusal(array('code' => 'not_ready', 'error' => 'x')));
	pg_assert_same(array(404, 'not_found', 'x'), api_order_label_refusal(array('code' => 'not_found', 'error' => 'x')));
	pg_assert_same(array(409, 'order_not_completed', 'x'), api_order_label_refusal(array('code' => 'not_completed', 'error' => 'x')));
	pg_assert_same(array(409, 'label_exists', 'x'), api_order_label_refusal(array('code' => 'label_exists', 'error' => 'x')));
	pg_assert_same(array(409, 'no_label_service', 'x'), api_order_label_refusal(array('code' => 'no_driver', 'error' => 'x')));
	pg_assert_same(array(409, 'label_refused', 'no'), api_order_label_refusal(array('code' => '', 'unclear' => false, 'error' => 'no')));
	pg_assert_same(array(409, 'label_unclear', '?'), api_order_label_refusal(array('code' => '', 'unclear' => true, 'error' => '?')));
}

// The list entry matches its declaration and carries no personal data and
// no path of the kept file.
function test_shipment_label_api_list_shape()
{
	$label = api_order_label_present(shipment_label_api_test_row(), 'https://shop.test/pinegrap/integration.php');

	pg_assert_same(array_keys(api_order_label_schema()), array_keys($label));
	pg_assert_same(42, $label['id']);
	pg_assert_same('geliver', $label['driver']);
	pg_assert_same('1234567890', $label['number']);
	pg_assert_same('shp-77', $label['reference']);
	pg_assert_same('BC77', $label['barcode']);
	pg_assert_same('pdf', $label['format']);
	pg_assert_same('active', $label['state']);
	pg_assert_false($label['is_return']);
	pg_assert_same(gmdate('Y-m-d\\TH:i:s\\Z', 1790000000), $label['created_at']);
	pg_assert_same(null, $label['cancelled_at']);
	pg_assert_true($label['file_kept']);
	pg_assert_same('https://shop.test/pinegrap/integration.php/orders/7/shipments/labels/42/document', $label['document_url']);

	$encoded = json_encode($label);
	pg_assert_false(strpos($encoded, '.pdf') !== false, 'the stored file name stays inside');
	pg_assert_false(array_key_exists('user_id', $label));

	$gone = api_order_label_present(shipment_label_api_test_row(array(
		'number' => '', 'barcode' => '', 'label_format' => '', 'state' => 'cancelled', 'is_return' => '1',
		'cancelled_at' => '1790003600', 'label_removed_at' => '1790100000',
	)), '');

	pg_assert_same(null, $gone['number']);
	pg_assert_same(null, $gone['barcode']);
	pg_assert_same(null, $gone['format']);
	pg_assert_true($gone['is_return']);
	pg_assert_same(gmdate('Y-m-d\\TH:i:s\\Z', 1790003600), $gone['cancelled_at']);
	pg_assert_false($gone['file_kept'], 'a swept file is not kept');

	$order = api_order_schema();
	pg_assert_same(array(api_order_label_schema()), $order['shipments'][0]['labels'], 'GET /orders/{id} declares the list');
	pg_assert_same('api_order_label_schema', api_openapi_objects()['OrderShipmentLabel']);
}

// An SVG goes only as a sandboxed attachment, never as a page.
function test_shipment_label_api_document_headers()
{
	$svg = api_order_label_headers('svg', shipment_label_api_test_row(array('label_format' => 'svg')));

	pg_assert_true(in_array('Content-Type: image/svg+xml', $svg, true));
	pg_assert_true(in_array('Content-Disposition: attachment; filename="label-1234567890.svg"', $svg, true));
	pg_assert_true(in_array('Content-Security-Policy: sandbox', $svg, true));
	pg_assert_true(in_array('X-Content-Type-Options: nosniff', $svg, true));
	pg_assert_true(in_array('Cache-Control: private, no-store', $svg, true));
	pg_assert_false(strpos(implode("\n", $svg), 'text/html') !== false, 'no print page over the API');

	$zpl = api_order_label_headers('zpl', shipment_label_api_test_row(array('number' => '')));
	pg_assert_true(in_array('Content-Disposition: attachment; filename="label-42.zpl"', $zpl, true), 'no number: the row id names it');

	$pdf = api_order_label_headers('pdf', shipment_label_api_test_row(array('number' => 'A/B "1"')));
	pg_assert_true(in_array('Content-Type: application/pdf', $pdf, true));
	pg_assert_true(in_array('Content-Disposition: inline; filename="label-AB1.pdf"', $pdf, true), 'nothing breaks out of the header');

	$handler = shipment_label_api_test_source('api_orders_label_document');
	pg_assert_true(strpos($handler, "AND order_id = '\" . \$id . \"'") !== false, 'only a label of the order in the path');
	pg_assert_true(strpos($handler, 'api_order_label_headers(') !== false);
}

function test_shipment_label_api_routes_and_rights()
{
	$create = shipment_label_api_test_route('orders.labels.create');
	pg_assert_same('POST', $create['method']);
	pg_assert_same('/orders/{id}/shipments/label', $create['path']);
	pg_assert_same('orders:write', $create['scope']);
	pg_assert_true($create['dry_run']);
	pg_assert_same(201, $create['status']);
	pg_assert_same('OrderShipmentLabel', $create['returns']);

	$document = shipment_label_api_test_route('orders.labels.document');
	pg_assert_same('GET', $document['method']);
	pg_assert_same('/orders/{id}/shipments/labels/{label_id}/document', $document['path']);
	pg_assert_same('orders:read', $document['scope'], 'the order rights, as GET /orders/{id}');
	pg_assert_true($document['binary']);
	pg_assert_false($document['mcp']);

	pg_assert_same(shipment_label_api_test_route('orders.get')['scope'], $document['scope']);
	pg_assert_same(shipment_label_api_test_route('orders.ship')['scope'], $create['scope']);
}
