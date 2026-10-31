<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the products list's Change Selected (edit_products.php) and the
 * Catalog Manager's bulk edit (includes/explorer/handle_products_bulk.php):
 * the out-of-stock mark follows the shared rule, a refused price change is
 * not reported as a change, and a product in the Recycle Bin is left alone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// A tracked product whose count drops to zero is marked, stamped only on the
// way in; a count above zero or tracking switched off takes the mark off; an
// edit that moves neither tracking nor the count leaves the mark alone.
function test_products_bulk_out_of_stock_sql()
{
	$tracked_five = array('inventory' => '1', 'inventory_quantity' => '5', 'out_of_stock' => '0');
	$tracked_flagged = array('inventory' => '1', 'inventory_quantity' => '0', 'out_of_stock' => '1');
	$untracked_zero = array('inventory' => '0', 'inventory_quantity' => '0', 'out_of_stock' => '0');

	pg_assert_same("out_of_stock = '1', out_of_stock_timestamp = UNIX_TIMESTAMP()",
		pg_products_bulk_out_of_stock_sql($tracked_five, '', 0), 'tracked, down to 0: marked and stamped');
	pg_assert_same("out_of_stock = '1'",
		pg_products_bulk_out_of_stock_sql($tracked_flagged, '', 0), 'already marked: the day it ran out is kept');
	pg_assert_same("out_of_stock = '0'",
		pg_products_bulk_out_of_stock_sql($tracked_flagged, '', 3), 'back on the shelf');
	pg_assert_same("out_of_stock = '0'",
		pg_products_bulk_out_of_stock_sql($tracked_flagged, '0', null), 'tracking switched off');
	pg_assert_same("out_of_stock = '1', out_of_stock_timestamp = UNIX_TIMESTAMP()",
		pg_products_bulk_out_of_stock_sql($untracked_zero, '1', null), 'tracking switched on with nothing in stock');
	pg_assert_same("out_of_stock = '0'",
		pg_products_bulk_out_of_stock_sql($untracked_zero, '', 0), 'untracked stays unmarked');
	pg_assert_same('', pg_products_bulk_out_of_stock_sql($tracked_flagged, '', null), 'no stock change: left alone');
}

// Whether Change Selected was asked for anything beside a price change: a
// product whose only change is a refused price is not written or counted.
function test_products_mass_change_besides_price()
{
	pg_assert_false(pg_products_mass_change_besides_price(array('edit_change_price_method' => '1'), null), 'price only');
	pg_assert_true(pg_products_mass_change_besides_price(array('edit_enabled' => '0'), null), 'status');
	pg_assert_true(pg_products_mass_change_besides_price(array('edit_inventory' => '1'), null), 'tracking');
	pg_assert_true(pg_products_mass_change_besides_price(array(), 4), 'quantity');
	pg_assert_true(pg_products_mass_change_besides_price(array('edit_allowed_zones' => '3'), null), 'zones');
	pg_assert_true(pg_products_mass_change_besides_price(array('edit_disallowed_zones' => '3'), null), 'zones off');
	pg_assert_true(pg_products_mass_change_besides_price(array('edit_tax_rate_method' => 'zone'), null), 'tax rate');
	pg_assert_false(pg_products_mass_change_besides_price(array('edit_enabled' => '', 'edit_inventory' => ''), null), 'empty choices');
}

// Both bulk paths write the mark through the shared helper; the old half rule
// (only ever clearing it) is gone.
function test_products_bulk_paths_use_the_shared_stock_rule()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');
	$bulk = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');

	pg_assert_contains('pg_products_bulk_out_of_stock_sql(', $screen, 'Change Selected');
	pg_assert_contains('pg_products_bulk_out_of_stock_sql(', substr($bulk, (int) strpos($bulk, 'function pg_explorer_handle_products_bulk_edit(')), 'Catalog Manager bulk edit');
	pg_assert_same(false, strpos($screen, "\$sql_out_of_stock = \"out_of_stock = '0',\";"), 'no clear-only rule on the screen');
	pg_assert_same(false, strpos($bulk, "\$sql_parts[] = \"out_of_stock = '0'\";"), 'no clear-only rule in the Catalog Manager');
}

// Change Selected skips a binned product before any write, says how many it
// skipped, and reports a price change only for products whose price moved.
function test_products_mass_change_skips_binned_and_refused()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');
	$loop = substr($screen, (int) strpos($screen, 'foreach ($_POST[\'products\'] as $product_id)'));

	$binned = strpos($loop, 'pg_catalog_product_binned(');
	pg_assert_true($binned !== false, 'checks the bin');
	pg_assert_true($binned < strpos($loop, 'DELETE FROM tag_cloud_keywords'), 'before the first write');
	pg_assert_contains("'{var:1} product(s) were not changed because they are in the Recycle Bin.'", $screen, 'says so');

	pg_assert_contains('$number_of_price_changed_products > 0', $screen, 'price changed only when one moved');
	pg_assert_same(false, strpos($screen, "if (\$edit_change_price_method != '') {\n                            pg_price_history_queue("), 'no history queued for a refused price');
}
