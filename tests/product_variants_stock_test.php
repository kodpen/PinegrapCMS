<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a product's stock where a shopper adds it or an integrator
 * writes it: the rule the site's add-to-cart action (add_to_cart.php) uses to
 * refuse a sold-out product or a quantity past its stock
 * (pg_cart_add_stock_refusal()), and the one low-stock crossing rule
 * (pg_product_stock_low_crossed()) that the inventory API shares with the
 * site's other stock writers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_variants_stock_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// A tracked product with no stock and no back orders cannot be added.
function test_cart_add_stock_refusal_sold_out()
{
	$product = array('inventory' => '1', 'inventory_quantity' => '0', 'backorder' => '0', 'selection_type' => 'quantity');
	pg_assert_same('unavailable', pg_cart_add_stock_refusal($product, 1));
}

// More than the stock is refused as short; the stock itself is allowed.
function test_cart_add_stock_refusal_past_the_stock()
{
	$product = array('inventory' => '1', 'inventory_quantity' => '2', 'backorder' => '0', 'selection_type' => 'quantity');
	pg_assert_same('short', pg_cart_add_stock_refusal($product, 3));
	pg_assert_same('', pg_cart_add_stock_refusal($product, 2));
}

// No tracking, back orders on, or a donation: stock does not limit the add.
function test_cart_add_stock_refusal_unlimited()
{
	pg_assert_same('', pg_cart_add_stock_refusal(array('inventory' => '0', 'inventory_quantity' => '0', 'backorder' => '0', 'selection_type' => 'quantity'), 5));
	pg_assert_same('', pg_cart_add_stock_refusal(array('inventory' => '1', 'inventory_quantity' => '0', 'backorder' => '1', 'selection_type' => 'quantity'), 5));
	pg_assert_same('', pg_cart_add_stock_refusal(array('inventory' => '1', 'inventory_quantity' => '0', 'backorder' => '0', 'selection_type' => 'donation'), 1));
}

// The add-to-cart action asks the rule before it writes the line.
function test_add_to_cart_action_checks_stock_before_adding()
{
	$code = product_variants_stock_test_source('add_to_cart.php');
	$check = strpos($code, 'pg_cart_add_stock_refusal(');
	$add = strpos($code, 'add_order_item($product_id');

	pg_assert_true($check !== false, 'add_to_cart.php checks the stock');
	pg_assert_true(($check !== false) && ($add !== false) && ($check < $add), 'before add_order_item()');
}

// The low-stock line: crossed from above, or from no level at all (tracking
// switched on with a count at or under the line).
function test_product_stock_low_crossed()
{
	pg_assert_same(true, pg_product_stock_low_crossed(5, 8, 4), 'from above');
	pg_assert_same(true, pg_product_stock_low_crossed(5, 6, 5), 'at the line');
	pg_assert_same(false, pg_product_stock_low_crossed(5, 4, 3), 'already under');
	pg_assert_same(false, pg_product_stock_low_crossed(5, 8, 6), 'still above');
	pg_assert_same(true, pg_product_stock_low_crossed(5, null, 3), 'tracking switched on low');
	pg_assert_same(true, pg_product_stock_low_crossed(5, null, 0), 'tracking switched on at none');
	pg_assert_same(false, pg_product_stock_low_crossed(5, null, 9), 'tracking switched on with plenty');
	pg_assert_same(false, pg_product_stock_low_crossed(0, 8, 1), 'no threshold');
}

// The inventory API decides stock.low by the shared rule, so a set that
// switches tracking on under the line announces it as the other writers do.
function test_inventory_api_uses_the_shared_low_rule()
{
	$code = product_variants_stock_test_source('includes/api/resources/inventory.php');
	pg_assert_true(strpos($code, 'pg_product_stock_low_crossed(') !== false, 'inventory.php uses pg_product_stock_low_crossed()');
}
