<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stock location model (2026.11.0, 11.0.162,
 * includes/fn/stock_locations.php): the sum after each change, a decrease
 * past the row refused, the path of a site without locations, the guarded
 * statements and the upgrade step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function stock_locations_test_locations()
{
	return array(
		array('id' => 3, 'name' => 'Shop', 'enabled' => 1, 'is_default' => 0),
		array('id' => 7, 'name' => 'Warehouse', 'enabled' => 1, 'is_default' => 1),
	);
}

function test_stock_locations_sum_follows_every_change()
{
	$rows = array(7 => 10, 3 => 4);

	foreach (array(array(7, -3), array(3, 5), array(9, 2), array(7, -7), array(3, 0)) as $step) {
		$plan = pg_stock_plan($rows, $step[0], $step[1]);

		pg_assert_true($plan['ok'], 'change ' . $step[0] . ' by ' . $step[1]);
		pg_assert_same(array_sum($plan['rows']), $plan['total'], 'the total is the sum of the rows after ' . $step[0] . ' by ' . $step[1]);
		pg_assert_same(array_sum($rows) + $step[1], $plan['total'], 'the total moved by the delta');

		$rows = $plan['rows'];
	}

	pg_assert_same(array(7 => 0, 3 => 9, 9 => 2), $rows, 'each location kept its own units');
}

function test_stock_locations_decrease_past_the_row_is_refused()
{
	$rows = array(7 => 2, 3 => 50);
	$plan = pg_stock_plan($rows, 7, -3);

	pg_assert_false($plan['ok'], 'three units are not at a location holding two');
	pg_assert_same('not_enough', $plan['error'], 'the reason');
	pg_assert_same($rows, $plan['rows'], 'nothing is taken from another location');
	pg_assert_same(52, $plan['total'], 'the total is unchanged');

	pg_assert_same('not_enough', pg_stock_plan($rows, 9, -1)['error'], 'a location with no row holds nothing');
	pg_assert_true(pg_stock_plan($rows, 7, -2)['ok'], 'taking all of the row is allowed');
}

function test_stock_locations_bad_delta_and_overflow_are_refused()
{
	pg_assert_same('delta', pg_stock_plan(array(7 => 1), 7, '1.5')['error'], 'a fraction');
	pg_assert_same('delta', pg_stock_plan(array(7 => 1), 7, 'five')['error'], 'text');
	pg_assert_same('delta', pg_stock_plan(array(7 => 1), 7, true)['error'], 'a boolean');
	pg_assert_same('too_many', pg_stock_plan(array(7 => PG_STOCK_QUANTITY_MAX), 7, 1)['error'], 'past the column');
	pg_assert_same('too_many', pg_stock_plan(array(7 => 5, 3 => PG_STOCK_QUANTITY_MAX - 5), 7, 1)['error'], 'a total past the column');
	pg_assert_true(pg_stock_plan(array(7 => 1), 7, '-1')['ok'], 'a numeric string');
}

function test_stock_locations_no_locations_path()
{
	pg_assert_same(0, pg_stock_resolve_location(array(), 0), 'no locations: the total itself');
	pg_assert_same(0, pg_stock_resolve_location(array(), 5), 'a location asked for on a site without any is the total too');
	pg_assert_same(0, pg_stock_pick_default(array()), 'no default');

	$plan = pg_stock_plan(array(0 => 4), 0, -5);
	pg_assert_same('not_enough', $plan['error'], 'the total is guarded like a row');

	$sql = pg_stock_total_sql(12, -5);
	pg_assert_contains('inventory_quantity = inventory_quantity - 5', $sql, 'decrement in SQL');
	pg_assert_contains('inventory_quantity >= 5', $sql, 'the guard');
	pg_assert_true(strpos($sql, 'product_stock') === false, 'no row is touched');
}

function test_stock_locations_resolve_location()
{
	$locations = stock_locations_test_locations();

	pg_assert_same(7, pg_stock_resolve_location($locations, 0), 'none asked: the default');
	pg_assert_same(3, pg_stock_resolve_location($locations, '3'), 'the one asked for');
	pg_assert_same(null, pg_stock_resolve_location($locations, 99), 'an unknown location');

	$no_default = array(
		array('id' => 4, 'enabled' => 0, 'is_default' => 0),
		array('id' => 5, 'enabled' => 1, 'is_default' => 0),
	);
	pg_assert_same(5, pg_stock_pick_default($no_default), 'no default marked: the first enabled one');
}

function test_stock_locations_statements_are_guarded()
{
	$down = pg_stock_change_sql(12, 7, -5, 1000);
	pg_assert_contains('quantity = quantity - 5', $down, 'decrement in SQL');
	pg_assert_contains('(quantity >= 5)', $down, 'only while the row holds enough');
	pg_assert_contains("(product_id = '12') AND (location_id = '7')", $down, 'one row');

	$up = pg_stock_change_sql(12, 7, 5, 1000);
	pg_assert_contains('quantity = quantity + 5', $up, 'increment in SQL');
	pg_assert_contains('(quantity <= ' . (PG_STOCK_QUANTITY_MAX - 5) . ')', $up, 'never past the column');

	$sum = pg_stock_sum_sql(12);
	pg_assert_contains('SET inventory_quantity = (', $sum, 'the total is written from the rows');
	pg_assert_contains("SUM(quantity), 0) FROM product_stock WHERE product_id = '12'", $sum, 'the sum of the product rows');
	pg_assert_contains("WHERE id = '12'", $sum, 'one product');
}

function test_stock_locations_clean_quantity()
{
	pg_assert_same(5, pg_stock_clean_quantity('5'), 'a numeric string');
	pg_assert_same(12, pg_stock_clean_quantity(12.0), 'a whole float');
	pg_assert_same(0, pg_stock_clean_quantity(0), 'zero');
	pg_assert_same(null, pg_stock_clean_quantity(-1), 'negative');
	pg_assert_same(null, pg_stock_clean_quantity('2.5'), 'a fraction');
	pg_assert_same(null, pg_stock_clean_quantity(PG_STOCK_QUANTITY_MAX + 1), 'past the column');
	pg_assert_same(null, pg_stock_clean_quantity(null), 'missing');
}

function test_stock_locations_reasons_are_unique_words()
{
	$reasons = pg_stock_reasons();

	pg_assert_same(count($reasons), count(array_unique($reasons)), 'no reason twice');

	foreach ($reasons as $reason) {
		pg_assert_true((preg_match('/^[a-z_]{1,32}$/', $reason) === 1), $reason . ' fits stock_moves.reason');
	}
}

function test_stock_locations_upgrade_step_is_called_and_idempotent()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/migrations/2026.11.0.php');

	pg_assert_true(strpos($source, "\tupgrade_2026_11_0_stock_locations();") !== false, 'the open version calls the step');

	$start = strpos($source, "\nfunction upgrade_2026_11_0_stock_locations()");
	pg_assert_true($start !== false, 'the step exists');

	$end = strpos($source, "\n}\n", $start);
	$body = substr($source, $start, $end - $start);

	foreach (array('stock_locations', 'product_stock', 'stock_moves') as $table) {
		pg_assert_contains("install_create_table('" . $table . "'", $body, $table . ' through install_create_table()');
	}

	pg_assert_contains("install_add_column('shipping_methods', 'stock_location_id'", $body, 'the shipping method location');
	pg_assert_contains("install_add_column('orders', 'stock_location_id'", $body, 'the order location');
	pg_assert_true(strpos($body, 'ALTER TABLE') === false, 'no blind ALTER');
	pg_assert_true(preg_match('/\b(INSERT|UPDATE|DELETE)\b/', $body) !== 1, 'no data moves');

	$install = file_get_contents(dirname(__DIR__) . '/pinegrap/install/index.php');

	foreach (array('stock_locations', 'product_stock', 'stock_moves') as $table) {
		pg_assert_contains("\t\t'" . $table . "',\n", $install, $table . ' in get_tables()');
	}
}
