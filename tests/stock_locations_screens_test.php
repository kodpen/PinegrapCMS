<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for stock per location on the product screens
 * (includes/fn/stock_location_screens.php): the quantities shown, the
 * changes the product screen posts, the bulk delta, and the stock columns of
 * the product import and export; source pins on the writers that now go
 * through pg_stock_change() / pg_stock_set().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function stock_screens_test_locations()
{
	return array(
		array('id' => 7, 'name' => 'Warehouse', 'enabled' => 1, 'is_default' => 1),
		array('id' => 3, 'name' => 'Shop', 'enabled' => 1, 'is_default' => 0),
	);
}

function test_stock_screens_quantities_follow_the_rows()
{
	$locations = stock_screens_test_locations();

	pg_assert_same(array(7 => 10, 3 => 4), pg_stock_screen_quantities($locations, array(7 => 10, 3 => 4), 14), 'each location shows its row');
	pg_assert_same(array(7 => 10, 3 => 0), pg_stock_screen_quantities($locations, array(7 => 10), 10), 'a location with no row holds none');
	pg_assert_same(array(7 => 12, 3 => 0), pg_stock_screen_quantities($locations, array(), 12), 'a product with no rows yet holds its total at the default location');
	pg_assert_same(array(7 => 0, 3 => 0), pg_stock_screen_quantities($locations, array(), -2), 'a negative total shows as none');
}

function test_stock_screens_posted_changes_are_deltas()
{
	$locations = stock_screens_test_locations();

	$read = pg_stock_posted_changes($locations, array(7 => '12', 3 => '4'), array(7 => '10', 3 => '4'));
	pg_assert_true($read['ok'], 'whole counts are read');
	pg_assert_same(array(7 => array('delta' => 2)), $read['changes'], 'only the changed location moves, by the difference');

	$read = pg_stock_posted_changes($locations, array(3 => ''), array(3 => '5'));
	pg_assert_same(array(3 => array('delta' => -5)), $read['changes'], 'an emptied box is 0');

	$read = pg_stock_posted_changes($locations, array(7 => '8'), array());
	pg_assert_same(array(7 => array('set' => 8)), $read['changes'], 'without the opened figure the quantity is set');

	$read = pg_stock_posted_changes($locations, array(99 => '8'), array(99 => '1'));
	pg_assert_same(array(), $read['changes'], 'a location that does not exist is ignored');
}

function test_stock_screens_posted_quantity_refused()
{
	$locations = stock_screens_test_locations();

	foreach (array('1.5', '-3', 'abc', array('2')) as $typed) {
		$read = pg_stock_posted_changes($locations, array(7 => $typed), array(7 => '1'));

		pg_assert_false($read['ok'], 'refused: ' . json_encode($typed));
		pg_assert_same('quantity', $read['error'], 'named: ' . json_encode($typed));
		pg_assert_same(array(), $read['changes'], 'nothing to write: ' . json_encode($typed));
	}
}

function test_stock_screens_bulk_delta()
{
	pg_assert_same(5, pg_stock_bulk_delta(3, 'value', 8), 'set to 8 from 3');
	pg_assert_same(-3, pg_stock_bulk_delta(3, 'value', 0), 'set to 0 from 3');
	pg_assert_same(4, pg_stock_bulk_delta(3, 'increase', 4), 'increase by 4');
	pg_assert_same(-2, pg_stock_bulk_delta(3, 'decrease', 2), 'decrease by 2');
	pg_assert_same(-3, pg_stock_bulk_delta(3, 'decrease', 9), 'a decrease stops at 0');
	pg_assert_same(0, pg_stock_bulk_delta(0, 'decrease', 9), 'an empty location stays empty');
}

function test_stock_screens_import_columns()
{
	$headings = array('name', 'inventory_quantity', 'stock:Warehouse', ' STOCK: shop ', 'stock:Depot', 'stock:warehouse', 'price');
	$read = pg_stock_import_columns($headings, stock_screens_test_locations());

	pg_assert_same(array(2 => 7, 3 => 3), $read['positions'], 'each location by its position, without case or spaces');
	pg_assert_same(array('stock:Depot', 'stock:warehouse'), $read['unknown'], 'a name that is no location, and a location named twice, are unknown');

	$read = pg_stock_import_columns($headings, array());
	pg_assert_same(array(), $read['positions'], 'a site without locations reads no stock column');
	pg_assert_same(4, count($read['unknown']), 'and names every one of them');

	pg_assert_true(pg_stock_is_column_heading(' Stock:Shop'), 'a stock heading');
	pg_assert_false(pg_stock_is_column_heading('stock_quantity'), 'not a stock heading');
}

function test_stock_screens_import_targets()
{
	pg_assert_same(array(7 => 4, 3 => 0), pg_stock_import_targets(array(7 => 4, 3 => 0), 9, 7), 'the stock columns win over the old column');
	pg_assert_same(array(3 => 2), pg_stock_import_targets(array(7 => null, 3 => 2), 9, 7), 'an empty cell leaves its location');
	pg_assert_same(array(), pg_stock_import_targets(array(7 => null), 9, 7), 'a file with stock columns ignores the old one even when its cells are empty');
	pg_assert_same(array(7 => 9), pg_stock_import_targets(array(), 9, 7), 'the old column alone fills the default location');
	pg_assert_same(array(), pg_stock_import_targets(array(), null, 7), 'an empty old cell sets nothing');
}

function test_stock_screens_export_columns()
{
	$locations = stock_screens_test_locations();

	pg_assert_same(array('stock:Warehouse', 'stock:Shop'), pg_stock_export_headings($locations), 'one heading per location, the importer\'s form');
	pg_assert_same(array('6', '1'), pg_stock_export_cells($locations, array(7 => 6, 3 => 1), array('inventory' => '1', 'inventory_quantity' => '7')), 'a tracked product by location');
	pg_assert_same(array('5', '0'), pg_stock_export_cells($locations, array(), array('inventory' => '1', 'inventory_quantity' => '5')), 'a product with no rows holds its total at the default');
	pg_assert_same(array('', ''), pg_stock_export_cells($locations, array(7 => 6), array('inventory' => '0', 'inventory_quantity' => '6')), 'a product that does not track stock has empty cells');

	$headings = pg_stock_export_headings($locations);
	$read = pg_stock_import_columns($headings, $locations);
	pg_assert_same(array(0 => 7, 1 => 3), $read['positions'], 'an exported file is read back');
}

function test_stock_screens_writers_go_through_the_writer()
{
	$builder = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	$bulk = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');
	$catalog = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog_edit.php');
	$import = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php');
	$export = (string) file_get_contents(PG_FUNCTIONS_DIR . '/export_products_f.php');

	pg_assert_contains("\$stock_action = 'keep';", $builder, 'the product screen keeps the stored total when it posts by location');
	pg_assert_contains("pg_stock_apply_changes(\$product_id, \$stock_posted['changes'], 'panel')", $builder, 'and writes the locations through the writer');
	pg_assert_contains("'code' => 'locations'", $builder, 'the list\'s stock cell refuses a total');
	pg_assert_contains('pg_stock_screen_locations()', $catalog, 'the catalogue\'s stock cell refuses a total');
	pg_assert_contains("pg_stock_bulk_write(\$product_id, \$bulk_quantity_location, \$bulk_quantity_mode, \$bulk_quantity_value, 'bulk_edit')", $bulk, 'the Catalog Manager bulk edit writes one location');
	pg_assert_contains("pg_stock_bulk_write(\$product_id, \$quantity_location, \$quantity_process, \$quantity_value, 'bulk_edit')", $screen, 'Change Selected writes one location');
	pg_assert_contains('if (($row_quantity === null) || $stock_locations) {', $import, 'the import leaves the total out of its row with locations');
	pg_assert_same(2, substr_count($import, 'pg_stock_import_write($product_id, $row_stock, $row_quantity)'), 'and writes by location on update and insert');
	pg_assert_contains('pg_stock_export_cells($stock_locations, $product_rows, $product)', $export, 'the export writes one cell per location');
}
