<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stock location endpoints of the external API
 * (includes/api/resources/inventory.php): the location and level objects,
 * how a refused transfer answers, and the route rows the router, the
 * OpenAPI document and the MCP tools read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function stock_api_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function test_stock_api_location_present()
{
	$out = api_inventory_location_present(array(
		'id' => '7', 'name' => 'Warehouse', 'address' => 'Dock 2', 'enabled' => '1', 'is_default' => '1', 'sort_order' => '3',
	));

	pg_assert_same(array(
		'id' => 7, 'name' => 'Warehouse', 'address' => 'Dock 2', 'enabled' => true, 'is_default' => true, 'sort_order' => 3,
	), $out);

	$off = api_inventory_location_present(array('id' => 5, 'name' => 'Closed', 'address' => '', 'enabled' => '0', 'is_default' => '0', 'sort_order' => '0'));
	pg_assert_same(false, $off['enabled']);
	pg_assert_same(false, $off['is_default']);
}

function test_stock_api_level_present()
{
	$product = array('id' => '12', 'name' => 'SKU-12', 'inventory' => '1', 'inventory_quantity' => '14', 'out_of_stock' => '0');

	$all = api_inventory_level_present($product, array(7 => 10, 3 => 4), array(7, 3, 5), 7, 0);
	pg_assert_same(12, $all['product_id']);
	pg_assert_same('SKU-12', $all['name']);
	pg_assert_same(14, $all['quantity']);
	pg_assert_same(false, $all['out_of_stock']);
	pg_assert_same(array(
		array('location_id' => 7, 'quantity' => 10),
		array('location_id' => 3, 'quantity' => 4),
		array('location_id' => 5, 'quantity' => 0),
	), $all['locations'], 'every location, 0 where the product has no row');

	$one = api_inventory_level_present($product, array(7 => 10, 3 => 4), array(7, 3, 5), 7, 3);
	pg_assert_same(array(array('location_id' => 3, 'quantity' => 4)), $one['locations'], 'only the asked location');

	$seedless = api_inventory_level_present(array('id' => 9, 'name' => 'S', 'inventory' => '1', 'inventory_quantity' => '6', 'out_of_stock' => '0'), array(), array(7, 3), 7, 0);
	pg_assert_same(array(
		array('location_id' => 7, 'quantity' => 6),
		array('location_id' => 3, 'quantity' => 0),
	), $seedless['locations'], 'a product with no rows holds its total at the default');

	$flat = api_inventory_level_present(array('id' => 4, 'name' => 'F', 'inventory' => '1', 'inventory_quantity' => '0', 'out_of_stock' => '1'), array(), array(), 0, 0);
	pg_assert_same(array(), $flat['locations'], 'a site without locations: no locations');
	pg_assert_same(true, $flat['out_of_stock']);
}

function test_stock_api_transfer_refusals()
{
	pg_assert_same(array(422, 'validation_failed', 'quantity'), api_inventory_transfer_refusal('quantity'));
	pg_assert_same(array(422, 'location', 'from'), api_inventory_transfer_refusal('from'));
	pg_assert_same(array(422, 'location', 'to'), api_inventory_transfer_refusal('to'));
	pg_assert_same(array(422, 'validation_failed', 'to'), api_inventory_transfer_refusal('same'));
	pg_assert_same(array(409, 'location_disabled', 'to'), api_inventory_transfer_refusal('to_disabled'));
	pg_assert_same(array(409, 'not_enough', 'quantity'), api_inventory_transfer_refusal('not_enough'));
	pg_assert_same(array(409, 'not_tracked', null), api_inventory_transfer_refusal('untracked'));
	pg_assert_same(array(409, 'no_locations', null), api_inventory_transfer_refusal('no_locations'));
	pg_assert_same(array(409, 'refused', null), api_inventory_transfer_refusal('too_many'));
	pg_assert_same(array(409, 'refused', null), api_inventory_transfer_refusal('write'));
}

function test_stock_api_routes()
{
	$locations = stock_api_test_route('inventory.locations');
	pg_assert_true($locations !== null, 'GET /inventory/locations is declared');
	pg_assert_same('GET', $locations['method']);
	pg_assert_same('/inventory/locations', $locations['path']);
	pg_assert_same('products:read', $locations['scope']);

	$levels = stock_api_test_route('inventory.list');
	pg_assert_same('/inventory', $levels['path']);
	pg_assert_same('products:read', $levels['scope']);
	$names = array_column($levels['params'], 'name');
	pg_assert_true(in_array('location', $names, true), 'GET /inventory takes location');
	pg_assert_true(in_array('cursor', $names, true), 'GET /inventory is cursor paged');

	$transfer = stock_api_test_route('inventory.transfer');
	pg_assert_same('POST', $transfer['method']);
	pg_assert_same('/inventory/transfer', $transfer['path']);
	pg_assert_same('inventory:write', $transfer['scope']);
	pg_assert_true(!empty($transfer['dry_run']), 'a transfer can be rehearsed');

	foreach ($transfer['params'] as $param) {
		if ($param['name'] === 'note') {
			pg_assert_true($param['max_length'] <= PG_STOCK_NOTE_LENGTH, 'the note fits its column');
		}
	}

	pg_assert_true(function_exists('pg_mcp_route_is_offered') ? pg_mcp_route_is_offered($transfer) : true, 'the transfer is an MCP tool');
}
