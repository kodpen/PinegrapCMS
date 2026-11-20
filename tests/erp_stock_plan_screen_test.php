<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Stock Planning screen (erp_stock_plan.php): its filters, the three
 * settings, the per-supplier purchase orders, and the quantities a plan
 * sends to New purchase order (includes/erp/purchase_orders.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/purchase_orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/stock_plan.php');

// A state and a supplier are read from the address; anything else is all.
function test_erp_stock_plan_screen_filters()
{
	pg_assert_same(array('state' => 'order_now', 'supplier' => 12), erp_stock_plan_filters(array('state' => 'order_now', 'supplier' => '12')));
	pg_assert_same(array('state' => 'dead', 'supplier' => 0), erp_stock_plan_filters(array('state' => 'dead')));
	pg_assert_same(array('state' => 'all', 'supplier' => 0), erp_stock_plan_filters(array('state' => 'x', 'supplier' => '-3')));
	pg_assert_same(array('state' => 'all', 'supplier' => 0), erp_stock_plan_filters(array('state' => array('soon'), 'supplier' => array(4))));
}

// A row matches the state filter by its state, dead stock by its own reading.
function test_erp_stock_plan_screen_row_matches()
{
	$row = array('state' => 'soon', 'supplier_id' => 5, 'dead' => false);

	pg_assert_same(true, erp_stock_plan_row_matches($row, array('state' => 'all', 'supplier' => 0)));
	pg_assert_same(true, erp_stock_plan_row_matches($row, array('state' => 'soon', 'supplier' => 5)));
	pg_assert_same(false, erp_stock_plan_row_matches($row, array('state' => 'soon', 'supplier' => 6)));
	pg_assert_same(false, erp_stock_plan_row_matches($row, array('state' => 'dead', 'supplier' => 0)));
	pg_assert_same(true, erp_stock_plan_row_matches(array('state' => 'fine', 'supplier_id' => 0, 'dead' => true), array('state' => 'dead', 'supplier' => 0)));
}

// Stored settings are read with their defaults and kept in range.
function test_erp_stock_plan_screen_settings_parse()
{
	pg_assert_same(array('safety' => 7, 'cover' => 30, 'dead' => 90), erp_stock_plan_settings_parse(''));
	pg_assert_same(array('safety' => 7, 'cover' => 30, 'dead' => 90), erp_stock_plan_settings_parse('not json'));
	pg_assert_same(array('safety' => 3, 'cover' => 45, 'dead' => 120), erp_stock_plan_settings_parse('{"safety":3,"cover":45,"dead":120}'));
	pg_assert_same(array('safety' => 0, 'cover' => 365, 'dead' => 1), erp_stock_plan_settings_parse('{"safety":-2,"cover":9999,"dead":0}'));
	pg_assert_same(array('safety' => 7, 'cover' => 30, 'dead' => 60), erp_stock_plan_settings_parse('{"dead":"60"}'));
}

// The card refuses what is not a whole number in range; empty is the default.
function test_erp_stock_plan_screen_settings_refusals()
{
	pg_assert_same(array(), erp_stock_plan_settings_refusals(array('stock_plan_safety_days' => '7', 'stock_plan_cover_days' => '', 'stock_plan_dead_days' => '90')));

	$refused = erp_stock_plan_settings_refusals(array('stock_plan_safety_days' => '1.5', 'stock_plan_cover_days' => '366', 'stock_plan_dead_days' => '0'));
	pg_assert_same(array('stock_plan_safety_days', 'stock_plan_cover_days', 'stock_plan_dead_days'), array_keys($refused));

	pg_assert_same(array('stock_plan_safety_days'), array_keys(erp_stock_plan_settings_refusals(array('stock_plan_safety_days' => array('3')))));
}

// The posted card becomes the stored value; empty boxes take the defaults.
function test_erp_stock_plan_screen_settings_value()
{
	pg_assert_same('{"safety":3,"cover":30,"dead":120}', erp_stock_plan_settings_value(array('stock_plan_safety_days' => '3', 'stock_plan_cover_days' => '', 'stock_plan_dead_days' => '120')));
}

// One order per supplier, with the products that have something to order.
function test_erp_stock_plan_screen_supplier_orders()
{
	$rows = array(
		array('product_id' => 4, 'supplier_id' => 2, 'supplier_name' => 'B Ltd', 'quantity' => 10),
		array('product_id' => 5, 'supplier_id' => 1, 'supplier_name' => 'A Ltd', 'quantity' => 3),
		array('product_id' => 6, 'supplier_id' => 2, 'supplier_name' => 'B Ltd', 'quantity' => 0),
		array('product_id' => 7, 'supplier_id' => 0, 'supplier_name' => '', 'quantity' => 8),
		array('product_id' => 8, 'supplier_id' => 2, 'supplier_name' => 'B Ltd', 'quantity' => 1),
	);

	pg_assert_same(array(
		1 => array('name' => 'A Ltd', 'quantities' => array(5 => 3)),
		2 => array('name' => 'B Ltd', 'quantities' => array(4 => 10, 8 => 1)),
	), erp_stock_plan_supplier_orders($rows));
}

// The plan's quantities arrive by POST: product id => whole units above 0.
function test_erp_stock_plan_screen_posted_quantities()
{
	pg_assert_same(array(4 => 10, 8 => 1), erp_purchase_order_plan_quantities(array('4' => '10', '8' => '1')));
	pg_assert_same(array(), erp_purchase_order_plan_quantities('5'));
	pg_assert_same(array(9 => 2), erp_purchase_order_plan_quantities(array('0' => '3', '-1' => '2', 'x' => '1', '7' => '0', '6' => array('5'), '9' => '2', '3' => '1.5')));
	pg_assert_same(1000000, erp_purchase_order_plan_quantities(array('2' => '99999999'))[2]);
	pg_assert_same(ERP_MANUAL_MAX_LINES, count(erp_purchase_order_plan_quantities(array_fill(1, ERP_MANUAL_MAX_LINES + 5, '1'))));
}

// A product with a plan quantity takes it; the others keep the minimum's.
function test_erp_stock_plan_screen_line_quantity()
{
	pg_assert_same(12, erp_purchase_order_product_quantity(array(4 => 12), 4, 10, 2));
	pg_assert_same(8, erp_purchase_order_product_quantity(array(4 => 12), 5, 10, 2));
	pg_assert_same(1, erp_purchase_order_product_quantity(array(), 5, 0, 2));
}
