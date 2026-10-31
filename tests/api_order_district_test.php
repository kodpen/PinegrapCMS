<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the district in the shipment address of GET /orders/{id}: the
 * ship_tos column is read only once it exists, the address presenter places
 * it after line_2 and gives '' when there is none, and the declaration
 * matches the presenter. No database is reached.
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

function api_order_district_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

function api_order_district_test_row($changes = array())
{
	return array_merge(array(
		'address_1' => 'Moda Cd. 12', 'address_2' => 'Daire 4', 'city' => 'İstanbul',
		'state' => 'İstanbul', 'zip_code' => '34710', 'country' => 'TR',
	), $changes);
}

// Before the migration step the column is missing and naming it would fail
// the whole query.
function test_api_order_shipment_columns_name_district_only_when_ready()
{
	pg_assert_contains('ship_tos.district', api_order_shipment_columns(true));
	pg_assert_false(strpos(api_order_shipment_columns(false), 'district') !== false, 'no district before the step');
	pg_assert_contains('ship_tos.address_2', api_order_shipment_columns(false));
	pg_assert_contains('shipping_methods.name AS method_name', api_order_shipment_columns(false));
}

function test_api_order_shipment_address_keys_match_declaration()
{
	$schema = api_order_schema();

	pg_assert_same(array_keys($schema['shipments'][0]['address']), array_keys(api_order_shipment_address(api_order_district_test_row(array('district' => 'Kadıköy')))));
	pg_assert_same(array('line_1', 'line_2', 'district', 'city', 'state', 'zip', 'country'), array_keys($schema['shipments'][0]['address']));
}

function test_api_order_shipment_address_district_value()
{
	$address = api_order_shipment_address(api_order_district_test_row(array('district' => 'Kadıköy')));

	pg_assert_same('Kadıköy', $address['district']);
	pg_assert_same('Moda Cd. 12', $address['line_1']);
	pg_assert_same('Daire 4', $address['line_2']);
	pg_assert_same('İstanbul', $address['city']);
	pg_assert_same('34710', $address['zip']);
	pg_assert_same('TR', $address['country']);

	pg_assert_same('', api_order_shipment_address(api_order_district_test_row())['district'], 'no column, no value');
	pg_assert_same('', api_order_shipment_address(api_order_district_test_row(array('district' => null)))['district'], 'NULL reads as empty');
}

function test_api_order_shipment_district_declared_as_string()
{
	$schema = api_order_schema();

	pg_assert_same('string', $schema['shipments'][0]['address']['district']);
}

function test_api_order_shipments_uses_both_helpers()
{
	$source = api_order_district_test_source('api_order_shipments');

	pg_assert_contains('api_order_shipment_columns()', $source);
	pg_assert_contains('api_order_shipment_address($row)', $source);
}
