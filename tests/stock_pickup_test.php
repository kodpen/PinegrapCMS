<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pickup at a location (includes/fn/stock_pickup.php): which
 * location a pickup method names, whether it holds the cart, how the place
 * is written, the columns a pickup method saves, and the callers that name
 * the place.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function stock_pickup_test_locations()
{
	return array(
		array('id' => 7, 'name' => 'Warehouse', 'address' => '', 'enabled' => 1, 'is_default' => 1),
		array('id' => 3, 'name' => 'Shop', 'address' => "Main St 1\r\n34000 Istanbul", 'enabled' => 1, 'is_default' => 0),
		array('id' => 5, 'name' => 'Closed shop', 'address' => '', 'enabled' => 0, 'is_default' => 0),
	);
}

function test_stock_pickup_location_of()
{
	$locations = stock_pickup_test_locations();

	pg_assert_same(null, pg_stock_pickup_location_of(0, $locations), 'no location: not a pickup');
	pg_assert_same('Shop', pg_stock_pickup_location_of(3, $locations)['name'], 'an enabled location');
	pg_assert_same(null, pg_stock_pickup_location_of(5, $locations), 'a disabled location is not offered');
	pg_assert_same(null, pg_stock_pickup_location_of(99, $locations), 'an unknown location is not offered');
}

function test_stock_pickup_covers()
{
	$needs = array(10 => array('quantity' => 2, 'total' => 9), 11 => array('quantity' => 1, 'total' => 4));
	$rows = array(10 => array(7 => 5, 3 => 2), 11 => array());

	pg_assert_true(pg_stock_pickup_covers($needs, $rows, 7, 7), 'the default holds both, the second by its total');
	pg_assert_true(!pg_stock_pickup_covers($needs, $rows, 3, 7), 'the shop lacks the product with no rows');
	pg_assert_true(pg_stock_pickup_covers(array(10 => $needs[10]), $rows, 3, 7), 'the shop holds exactly two');
	pg_assert_true(!pg_stock_pickup_covers(array(10 => array('quantity' => 3, 'total' => 9)), $rows, 3, 7), 'one short is refused');
	pg_assert_true(pg_stock_pickup_covers(array(), $rows, 3, 7), 'a cart with nothing tracked fits anywhere');
}

function test_stock_pickup_place_and_description()
{
	$locations = stock_pickup_test_locations();

	pg_assert_same('Shop, Main St 1, 34000 Istanbul', pg_stock_pickup_place($locations[1]), 'name, then the address on one line');
	pg_assert_same('Warehouse', pg_stock_pickup_place($locations[0]), 'no address: the name');
	pg_assert_same('Fast', pg_stock_pickup_description('Fast', ''), 'no pickup: the description unchanged');
	pg_assert_same('Fast; Pickup at: A & B', pg_stock_pickup_description('Fast', 'A & B'), 'plain text');
	pg_assert_same('Fast; Pickup at: A &amp; B', pg_stock_pickup_description('Fast', 'A & B', true), 'escaped for HTML');
	pg_assert_same('Pickup at: Shop', pg_stock_pickup_description('', 'Shop'), 'no description: the place alone');
}

function test_stock_pickup_columns()
{
	$locations = stock_pickup_test_locations();

	pg_assert_same(array(), pg_stock_pickup_columns(array('stock_location_id' => '3'), array()), 'no locations: the column is left alone');
	pg_assert_same(array('stock_location_id' => '0'), pg_stock_pickup_columns(array(), $locations), 'nothing posted: no pickup');
	pg_assert_same(array('stock_location_id' => '0'), pg_stock_pickup_columns(array('stock_location_id' => '99'), $locations), 'a forged id: no pickup');
	pg_assert_same(array('stock_location_id' => '0'), pg_stock_pickup_columns(array('stock_location_id' => array(3)), $locations), 'a list: no pickup');

	$columns = pg_stock_pickup_columns(array('stock_location_id' => '3'), $locations);

	pg_assert_same('3', $columns['stock_location_id'], 'the location');
	pg_assert_same('0', $columns['base_rate'], 'no base rate');
	pg_assert_same('0', $columns['item_rate'], 'no item rate');
	pg_assert_same('0', $columns['realtime_rate'], 'no real-time rate');
	pg_assert_same('', $columns['service'], 'no carrier service');
	pg_assert_same('1', $columns['street_address'], 'any street address');
	pg_assert_same('1', $columns['po_box'], 'any PO box');
}

function test_stock_pickup_location_options()
{
	$html = pg_stock_pickup_location_options(stock_pickup_test_locations(), 3);

	pg_assert_true(strpos($html, '<option value="0">') === 0, 'no pickup first');
	pg_assert_true(strpos($html, '<option value="3" selected>Shop</option>') !== false, 'the stored one selected');
	pg_assert_true(strpos($html, 'Closed shop (Off)') !== false, 'a disabled one marked');
}

function test_stock_pickup_callers()
{
	$source = function ($relative) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
	};

	pg_assert_same(2, substr_count($source('get_shipping_method.php'), 'pg_stock_pickup_methods_filter('), 'both checkout layouts filter');
	pg_assert_same(2, substr_count($source('shipping.php'), 'pg_stock_pickup_methods_filter('), 'the method list and the method check filter');
	pg_assert_true(strpos($source('includes/forms/shipping_method_form.php'), 'pg_stock_pickup_columns($post') !== false, 'the form saves the pickup columns');
	pg_assert_true(strpos($source('view_order.php'), 'pg_stock_pickup_location(') !== false, 'the order screen names the place');
	pg_assert_true(strpos($source('update_order.php'), 'pg_stock_pickup_mail_fields($shipped_ids)') !== false, 'the shipped mail gets ^^pickup_place^^');

	foreach (array('get_order_receipt.php', 'get_order_preview.php', 'get_order_receipt_in_plain_text.php') as $file) {
		pg_assert_true(strpos($source($file), 'pg_stock_pickup_describe(') !== false, $file . ' names the place');
	}
}
