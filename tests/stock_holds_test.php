<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/stock_holds.php: the units free for an order (other
 * orders' live holds counted, expired rows and the order's own rows not), the
 * units an order needs held (plain lines, a bundle's components as one pool
 * with the same product bought alone, untracked and back-order products left
 * out), the sweep's statement, and the places outside the module that take an
 * order's or a product's rows along (delete_order.php, the product delete,
 * the general job, the migration step and get_tables()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A hold row as the module reads it.
function stock_holds_test_row($order_id, $quantity, $expires_at)
{
	return array('order_id' => (string) $order_id, 'quantity' => (string) $quantity, 'expires_at' => (string) $expires_at);
}

// Other orders' live holds come off the stock.
function test_stock_holds_units_free_other_orders()
{
	$now = 1000;
	$holds = array(stock_holds_test_row(7, 2, 1500), stock_holds_test_row(8, 1, 1200));

	pg_assert_same(2, pg_stock_hold_units_free(5, $holds, 9, $now), 'five less three held');
	pg_assert_same(0, pg_stock_hold_units_free(3, $holds, 9, $now), 'all held');
	pg_assert_same(0, pg_stock_hold_units_free(2, $holds, 9, $now), 'never below 0');
	pg_assert_same(4, pg_stock_hold_units_free(4, array(), 9, $now), 'no holds: the stock');
	pg_assert_same(0, pg_stock_hold_units_free(-3, array(), 9, $now), 'stock below 0 counts as 0');
}

// A row that ran out counts for nothing from that second.
function test_stock_holds_units_free_expired()
{
	$holds = array(stock_holds_test_row(7, 2, 1000), stock_holds_test_row(8, 1, 999), stock_holds_test_row(6, 1, 1001));

	pg_assert_same(4, pg_stock_hold_units_free(5, $holds, 9, 1000), 'only the row still running counts');
	pg_assert_same(5, pg_stock_hold_units_free(5, $holds, 9, 2000), 'all ran out');
}

// The order's own rows are not counted against it.
function test_stock_holds_units_free_own_rows()
{
	$holds = array(stock_holds_test_row(9, 3, 1500), stock_holds_test_row(7, 1, 1500));

	pg_assert_same(4, pg_stock_hold_units_free(5, $holds, 9, 1000), 'own three not counted');
	pg_assert_same(1, pg_stock_hold_units_free(5, $holds, 0, 1000), 'no order: every hold counts');
}

// Stock as pg_stock_hold_order_needs() reads it.
function stock_holds_test_stock()
{
	return array(
		1 => array('tracked' => true, 'backorder' => false),
		2 => array('tracked' => true, 'backorder' => false),
		3 => array('tracked' => false, 'backorder' => false),
		4 => array('tracked' => true, 'backorder' => true),
	);
}

// Plain lines: tracked products without back orders, one entry per product,
// in id order.
function test_stock_holds_order_needs_plain_lines()
{
	$lines = array(
		array('product_id' => 2, 'quantity' => 1),
		array('product_id' => 1, 'quantity' => 2),
		array('product_id' => 2, 'quantity' => 3),
		array('product_id' => 3, 'quantity' => 4),
		array('product_id' => 4, 'quantity' => 5),
		array('product_id' => 9, 'quantity' => 1),
		array('product_id' => 1, 'quantity' => 0),
	);

	$needs = pg_stock_hold_order_needs($lines, stock_holds_test_stock());

	pg_assert_same(array(1 => 2, 2 => 4), $needs, 'two lines of product 2 add up; untracked, back order and unknown left out');
	pg_assert_same(array(1, 2), array_keys($needs), 'id order');
	pg_assert_same(array(), pg_stock_hold_order_needs(array(), stock_holds_test_stock()), 'no lines');
}

// A bundle line holds each tracked component for its quantity per bundle
// times the line's quantity; a component also bought alone is one pool.
function test_stock_holds_order_needs_bundle_one_pool()
{
	$lines = array(
		array('product_id' => 50, 'quantity' => 2, 'components' => array(
			array('product_id' => 2, 'quantity' => 3),
			array('product_id' => 1, 'quantity' => 1),
			array('product_id' => 3, 'quantity' => 5),
			array('product_id' => 4, 'quantity' => 1),
		)),
		array('product_id' => 2, 'quantity' => 1),
	);

	pg_assert_same(array(1 => 2, 2 => 7), pg_stock_hold_order_needs($lines, stock_holds_test_stock()), 'bundle 2 x (3 of 2, 1 of 1) plus 1 of 2 alone');

	$empty = array(array('product_id' => 50, 'quantity' => 1, 'components' => array()));

	pg_assert_same(array(), pg_stock_hold_order_needs($empty, stock_holds_test_stock()), 'a bundle with no contents holds nothing');
}

// The units a location holds are the stock the free units come from.
function test_stock_holds_location()
{
	$holds = array(stock_holds_test_row(7, 2, 1500));

	pg_assert_same(1, pg_stock_hold_units_free(pg_stock_available_at(array(1 => 3, 2 => 10), 1, 1, 13), $holds, 9, 1000), 'location 1 holds 3, 2 held there');
	pg_assert_same(0, pg_stock_hold_units_free(pg_stock_available_at(array(1 => 3), 2, 1, 3), $holds, 9, 1000), 'nothing at location 2');
	pg_assert_same(3, pg_stock_hold_units_free(pg_stock_available_at(array(), 1, 1, 5), $holds, 9, 1000), 'no rows yet: the total at the default');
	pg_assert_same(0, pg_stock_hold_location(0), 'no location stays 0');
	pg_assert_same(4, pg_stock_hold_location('4'), 'a location id');
	pg_assert_same(0, pg_stock_hold_location(-2), 'never below 0');
}

// The sweep deletes rows a day past their end, in batches.
function test_stock_holds_sweep_query()
{
	$sql = pg_stock_hold_sweep_query(200000, 500);

	pg_assert_contains('DELETE FROM stock_holds', $sql, 'deletes holds');
	pg_assert_contains('expires_at < 113600', $sql, 'a day before now');
	pg_assert_contains('LIMIT 500', $sql, 'in batches');
}

// A hold lasts fifteen minutes.
function test_stock_holds_minutes()
{
	pg_assert_same(15, PG_STOCK_HOLD_MINUTES, 'fifteen minutes');
	pg_assert_same('pg_stock_hold_12', pg_stock_hold_lock_name(12), 'the lock of a product');
	pg_assert_same('pg_stock_hold_' . substr(md5('shop'), 0, 16) . '_12', pg_stock_hold_lock_name(12, 'shop'), 'the database is part of the name');
	pg_assert_true(strlen(pg_stock_hold_lock_name(4294967295, str_repeat('x', 200))) <= 64, 'within 64 characters');
}

// The source of a file under pinegrap/.
function stock_holds_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The places outside the module that know the holds.
function test_stock_holds_callers()
{
	$delete_order = stock_holds_test_source('delete_order.php');
	pg_assert_contains('pg_stock_hold_release(', $delete_order, 'a deleted order takes its rows');

	$builder = stock_holds_test_source('product_builder.php');
	pg_assert_contains('pg_stock_hold_forget_product($product_id)', $builder, 'a deleted product takes its rows');

	$job = stock_holds_test_source('job.php');
	pg_assert_contains('pg_stock_hold_sweep_daily()', $job, 'the general job sweeps');

	$functions = stock_holds_test_source('functions.php');
	pg_assert_contains("/includes/fn/stock_holds.php'", $functions, 'loaded with the modules');

	$migration = stock_holds_test_source('includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_stock_holds();', $migration, 'the step is called');
	pg_assert_contains("install_create_table('stock_holds'", $migration, 'created with the helper');
	pg_assert_contains('UNIQUE KEY uniq_order_product_location (order_id, product_id, location_id)', $migration, 'one row per order, product and location');

	$install = stock_holds_test_source('install/index.php');
	pg_assert_contains("'stock_holds',", $install, 'in get_tables()');

	// A hold is a claim, never a stock figure.
	$module = stock_holds_test_source('includes/fn/stock_holds.php');
	pg_assert_false(strpos($module, 'UPDATE products') !== false, 'no stock write');
	pg_assert_false(strpos($module, 'product_stock') !== false, 'no location stock read or write');
}

// Other orders' live holds summed per product; own and expired rows left out.
function test_stock_holds_held_by_others()
{
	$holds = array(
		array('product_id' => '4', 'order_id' => '7', 'quantity' => '2', 'expires_at' => '1500'),
		array('product_id' => '4', 'order_id' => '8', 'quantity' => '1', 'expires_at' => '1200'),
		array('product_id' => '5', 'order_id' => '9', 'quantity' => '3', 'expires_at' => '1500'),
		array('product_id' => '6', 'order_id' => '7', 'quantity' => '4', 'expires_at' => '1000'));

	pg_assert_same(array(4 => 3), pg_stock_hold_held_by_others($holds, 9, 1000), 'own and expired rows left out');
	pg_assert_same(array(4 => 3, 5 => 3), pg_stock_hold_held_by_others($holds, 0, 1000), 'no order: every live hold');
	pg_assert_same(array(), pg_stock_hold_held_by_others(array(), 9, 1000), 'no holds');
}

// The stock a check counts: unchanged with nothing held, else less the held units.
function test_stock_holds_less_held()
{
	pg_assert_same(5, pg_stock_hold_less_held(5, 0), 'nothing held: the stock');
	pg_assert_same(-2, pg_stock_hold_less_held(-2, 0), 'nothing held: the stock as it is');
	pg_assert_same(2, pg_stock_hold_less_held('5', 3), 'three held');
	pg_assert_same(0, pg_stock_hold_less_held(2, 3), 'never below 0');
}

// A bundle cart's stock pool less what other orders hold, tracked products only.
function test_stock_holds_bundle_stock_less_held()
{
	$stock = array(
		1 => array('tracked' => true, 'quantity' => 5, 'backorder' => false),
		2 => array('tracked' => false, 'quantity' => 0, 'backorder' => false),
		3 => array('tracked' => true, 'quantity' => 1, 'backorder' => false));

	$less = pg_bundle_stock_less_held($stock, array(1 => 2, 2 => 4, 3 => 3));

	pg_assert_same(3, $less[1]['quantity'], 'five less two held');
	pg_assert_same(0, $less[2]['quantity'], 'an untracked product is left as it is');
	pg_assert_same(0, $less[3]['quantity'], 'never below 0');
	pg_assert_same($stock, pg_bundle_stock_less_held($stock, array()), 'nothing held: the pool as it is');
}

// Both checks count the units free; the no-holds path keeps its statements.
function test_stock_holds_checks_read_units_free()
{
	$ecommerce = stock_holds_test_source('includes/fn/ecommerce.php');
	$start = strpos($ecommerce, 'function check_inventory(');
	$check = substr($ecommerce, $start, strpos($ecommerce, 'function check_reservations(') - $start);

	pg_assert_contains('$held = pg_stock_hold_held_for(array_column($products, \'id\'), $stock_location_id, $cart_order_id);', $check, 'other orders\' holds read once');
	pg_assert_contains('$product[\'inventory_quantity\'] = pg_stock_hold_less_held($product[\'inventory_quantity\'], isset($held[$product[\'id\']]) ? $held[$product[\'id\']] : 0);', $check, 'counted after the location');
	pg_assert_contains('$at_location = pg_stock_quantity_at($product[\'id\'], $stock_location_id, $product[\'inventory_quantity\']);', $check, 'the location read stays');
	pg_assert_contains('pg_bundle_check_inventory($liveform, $cart_order_id, $bundle_ids);', $check, 'a bundle cart goes to its check');
	pg_assert_contains('if ($product[\'inventory_quantity\'] == 0) {', $check, 'nothing left: the line goes');
	pg_assert_contains('if ($order_item[\'quantity\'] > $product[\'inventory_quantity\']) {', $check, 'too many: the line is cut');
	pg_assert_contains('$product[\'inventory_quantity\'] = $product[\'inventory_quantity\'] - $order_item[\'quantity\'];', $check, 'lines share the units');
	pg_assert_true(strpos($check, '$at_location = pg_stock_quantity_at(') < strpos($check, 'pg_stock_hold_less_held('), 'holds come off the location\'s stock');

	$bundles = stock_holds_test_source('includes/fn/bundles.php');
	$start = strpos($bundles, 'function pg_bundle_check_inventory(');
	$check = substr($bundles, $start, strpos($bundles, 'function pg_bundle_return_stock(') - $start);

	pg_assert_contains('$stock = pg_bundle_stock_at_location($stock, $at_location);', $check, 'the location read stays');
	pg_assert_contains('$stock = pg_bundle_stock_less_held($stock, pg_stock_hold_held_for(array_keys($stock), $location_id, $order_id));', $check, 'other orders\' holds come off the pool');
	pg_assert_contains('$allowance = pg_bundle_cart_allowance($lines, $stock);', $check, 'one pool for the lines');
	pg_assert_true(strpos($check, 'pg_bundle_stock_at_location(') < strpos($check, 'pg_bundle_stock_less_held(') && strpos($check, 'pg_bundle_stock_less_held(') < strpos($check, 'pg_bundle_cart_allowance('), 'location, then holds, then the allowance');
}
