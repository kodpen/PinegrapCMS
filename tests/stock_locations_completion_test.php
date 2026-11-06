<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for stock per location where units are sold, scanned and changed by
 * the API (includes/fn/stock_sales.php): which location an order takes its
 * units from, what a location holds for the checkout's check, how much a
 * sale and an API call take, the location on inventory.changed and
 * stock.low, and that a site without locations keeps its old statements.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function stock_completion_test_locations()
{
	return array(
		array('id' => 7, 'name' => 'Warehouse', 'enabled' => 1, 'is_default' => 1),
		array('id' => 3, 'name' => 'Shop', 'enabled' => 1, 'is_default' => 0),
		array('id' => 5, 'name' => 'Closed shop', 'enabled' => 0, 'is_default' => 0),
	);
}

function stock_completion_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_stock_completion_order_location_pick()
{
	$locations = stock_completion_test_locations();

	pg_assert_same(0, pg_stock_pick_order_location(array(), 3, array(3)), 'no locations: 0');
	pg_assert_same(7, pg_stock_pick_order_location($locations, 0, array()), 'no method location: the default');
	pg_assert_same(7, pg_stock_pick_order_location($locations, 0, array(0, 0)), 'methods without a location: the default');
	pg_assert_same(3, pg_stock_pick_order_location($locations, 0, array(0, 3)), 'the first method that names a location');
	pg_assert_same(7, pg_stock_pick_order_location($locations, 0, array(5)), 'a disabled location is passed over');
	pg_assert_same(7, pg_stock_pick_order_location($locations, 0, array(99)), 'an unknown location is passed over');
	pg_assert_same(3, pg_stock_pick_order_location($locations, 3, array(7)), 'the location stored on the order wins');
	pg_assert_same(7, pg_stock_pick_order_location($locations, 5, array()), 'a stored location that was disabled is not used');
}

function test_stock_completion_available_at()
{
	pg_assert_same(4, pg_stock_available_at(array(7 => 10, 3 => 4), 3, 7, 14), 'the row at the location');
	pg_assert_same(0, pg_stock_available_at(array(7 => 10), 3, 7, 10), 'no row there: nothing, not the total');
	pg_assert_same(12, pg_stock_available_at(array(), 7, 7, 12), 'a product with no rows holds its total at the default');
	pg_assert_same(0, pg_stock_available_at(array(), 3, 7, 12), 'and nothing at another location');
	pg_assert_same(0, pg_stock_available_at(array(), 7, 7, -2), 'never below 0');
}

function test_stock_completion_sale_takes_only_what_the_location_holds()
{
	pg_assert_same(3, pg_stock_sale_amount(3, 10), 'the line quantity');
	pg_assert_same(4, pg_stock_sale_amount(6, 4), 'what is left when less: the location goes to 0');
	pg_assert_same(0, pg_stock_sale_amount(2, 0), 'an empty location gives nothing');
	pg_assert_same(0, pg_stock_sale_amount(-2, 5), 'a negative quantity takes nothing');
}

function test_stock_completion_api_delta()
{
	pg_assert_same(5, pg_stock_api_delta('set', 9, 4), 'set to 9 from 4');
	pg_assert_same(-4, pg_stock_api_delta('set', -3, 4), 'set below 0 is 0');
	pg_assert_same(-3, pg_stock_api_delta('adjust', -3, 4), 'adjust down');
	pg_assert_same(-4, pg_stock_api_delta('adjust', -10, 4), 'adjust stops at 0 at the location');
	pg_assert_same(6, pg_stock_api_delta('adjust', 6, 0), 'adjust up from nothing');
}

function test_stock_completion_events_carry_the_location()
{
	$location = array('id' => 3, 'name' => 'Shop', 'quantity' => 1);
	$events = pg_product_stock_events(12, 'SKU-12', true, 8, 4, 5, 'checkout', $location);

	pg_assert_same('inventory.changed', $events[0][0]);
	pg_assert_same(4, $events[0][1]['quantity'], 'the total stays the quantity');
	pg_assert_same(3, $events[0][1]['location_id']);
	pg_assert_same('Shop', $events[0][1]['location_name']);
	pg_assert_same(1, $events[0][1]['location_quantity']);
	pg_assert_same('stock.low', $events[1][0]);
	pg_assert_same(3, $events[1][1]['location_id'], 'stock.low carries it too');

	// Parity: without a location the payloads are the ones sent before.
	$plain = pg_product_stock_events(12, 'SKU-12', true, 8, 4, 5, 'checkout');
	pg_assert_same(array('product_id', 'quantity', 'out_of_stock', 'source'), array_keys($plain[0][1]));
	pg_assert_same(array('product_id', 'name', 'quantity', 'threshold'), array_keys($plain[1][1]));
	pg_assert_same(array(), pg_stock_event_location_fields(array('id' => 0)), 'location 0 adds nothing');
}

function test_stock_completion_site_without_locations_keeps_its_statements()
{
	$completion = stock_completion_test_source('includes/fn/order_completion.php');
	pg_assert_contains('$sale = pg_stock_sale_take($product_id, $line[\'quantity\'], $location_id, \'checkout\', $ref);', $completion);
	pg_assert_contains("if (\$sale === null) {\n        db_q(\"UPDATE products SET inventory_quantity = (inventory_quantity - ?) WHERE id = ?\"", $completion, 'the checkout keeps its decrement without locations');
	pg_assert_contains('$stock_location_id = pg_stock_order_location_save($order_id);', $completion);

	$local = stock_completion_test_source('includes/fn/local_order.php');
	pg_assert_contains("if (\$sale === null) {\n                db(\"UPDATE products SET inventory_quantity = (inventory_quantity - '\"", $local, 'the local sale keeps its decrement');

	$bundles = stock_completion_test_source('includes/fn/bundles.php');
	pg_assert_contains("if (\$sale === null) {\n            db(\"UPDATE products SET inventory_quantity = (inventory_quantity - '\"", $bundles, 'a bundle keeps its decrement');

	foreach (array('barcode_decrease_inventory.php', 'barcode_increase_inventory.php') as $screen) {
		$source = stock_completion_test_source($screen);
		$branch = strpos($source, 'if ($scan === null) {');
		pg_assert_true($branch !== false, $screen . ' writes the total only without locations');
		pg_assert_true(strpos($source, 'inventory_quantity = ', (int) $branch) < strpos($source, '} else {', (int) $branch), $screen . ' the total write sits in that branch');
		pg_assert_contains('pg_stock_requested_location($request[\'location_id\'] ?? 0)', $source, $screen . ' reads the location');
		pg_assert_contains('pg_stock_scan_location_field()', $source, $screen . ' shows the picker');
	}

	$api = stock_completion_test_source('includes/api/resources/inventory.php');
	pg_assert_contains('if (pg_stock_has_locations()) {', $api);
	pg_assert_contains('inventory_quantity = " . $quantity_sql . ",', $api, 'the API keeps its one-statement write without locations');
	pg_assert_contains('$change = pg_stock_change_from_row($id, $location_id,', $api);

	$check = stock_completion_test_source('includes/fn/ecommerce.php');
	pg_assert_contains('$at_location = pg_stock_quantity_at($product[\'id\'], $stock_location_id, $product[\'inventory_quantity\']);', $check, 'the checkout counts the order location');
}

function test_stock_completion_api_declares_the_location()
{
	$found = 0;

	foreach (api_schema() as $route) {
		if (in_array($route['id'], array('inventory.write', 'inventory.bulk'), true)) {
			$names = array_column($route['params'], 'name');

			if ($route['id'] === 'inventory.write') {
				pg_assert_true(in_array('location', $names, true), 'inventory.write takes location');
			} else {
				pg_assert_true(isset($route['params'][0]['of']['location']), 'a batch item takes location');
			}

			$found++;
		}
	}

	pg_assert_same(2, $found, 'both stock routes found');
	pg_assert_same('integer?', api_inventory_schema()['location_quantity']);
}
