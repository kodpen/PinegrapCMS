<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a bundle sold through the checkout: which order line takes its
 * components' stock (pg_bundle_sale_takes_components()), that the order
 * completion's stock stage routes such a line to pg_bundle_take_stock()
 * before the plain decrement, and that the product list's quick edit
 * refuses a bundle's stock.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function bundle_checkout_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The body of one function in a source file, up to the next top-level
// function.
function bundle_checkout_test_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// A line of a bundle with an order item takes its components; a plain
// product, a bundle id given as text and a line without an order item do not.
function test_bundle_sale_takes_components()
{
	$bundles = array(12 => true, 30 => true);

	pg_assert_true(pg_bundle_sale_takes_components(12, 501, $bundles), 'a bundle line');
	pg_assert_true(pg_bundle_sale_takes_components('30', '502', $bundles), 'ids read as text');
	pg_assert_false(pg_bundle_sale_takes_components(7, 503, $bundles), 'a plain product');
	pg_assert_false(pg_bundle_sale_takes_components(12, 0, $bundles), 'no order item to keep the contents on');
	pg_assert_false(pg_bundle_sale_takes_components(12, '', $bundles), 'an empty order item id');
	pg_assert_false(pg_bundle_sale_takes_components(12, 501, array()), 'before the bundle schema there are none');
}

// The checkout's stock stage hands a bundle line to pg_bundle_take_stock()
// with source checkout and the shared $stock_left, and returns before the
// line's own decrement.
function test_bundle_checkout_stock_stage_routes_a_bundle()
{
	$body = bundle_checkout_test_function_body(bundle_checkout_test_source('includes/fn/order_completion.php'), 'pg_order_complete_line_stock');

	pg_assert_true($body !== '', 'pg_order_complete_line_stock() found');

	$route = strpos($body, 'pg_bundle_sale_takes_components(');
	$take = strpos($body, "pg_bundle_take_stock(\$line['order_item_id'], \$product_id, \$line['quantity'], 'checkout', \$stock_left);");
	$decrement = strpos($body, 'UPDATE products SET inventory_quantity');

	pg_assert_true($route !== false, 'the line is routed');
	pg_assert_true($take !== false, 'a bundle line takes its components with source checkout');
	pg_assert_true(($route !== false) && ($take !== false) && ($decrement !== false) && ($route < $take) && ($take < $decrement), 'before the plain decrement');
	pg_assert_contains('return;', substr($body, (int) $take, (int) $decrement - (int) $take), 'and the bundle\'s own stock is not decremented');
}

// The order lines the checkout reads carry the order item id the components
// are written against.
function test_bundle_checkout_lines_carry_the_order_item()
{
	pg_assert_contains('$query = pg_order_lines_sql();', bundle_checkout_test_source('submit_order.php'));
	pg_assert_contains('order_items.id as order_item_id', bundle_checkout_test_source('includes/fn/order_completion.php'));
}

// The list's quick edit refuses a bundle's stock with code bundle, before any
// write; other columns of a bundle stay editable.
function test_bundle_quick_edit_refuses_the_stock()
{
	$body = bundle_checkout_test_function_body(bundle_checkout_test_source('product_builder.php'), 'pg_pb_quick_update');

	pg_assert_true($body !== '', 'pg_pb_quick_update() found');

	$case = strpos($body, "case 'inventory_quantity':");
	$refusal = strpos($body, "'code' => 'bundle'");
	$write = strpos($body, 'pg_pb_update_row(');

	pg_assert_true($refusal !== false, 'a bundle is refused');
	pg_assert_true(($case !== false) && ($refusal !== false) && ($write !== false) && ($case < $refusal) && ($refusal < $write), 'in the stock column, before the write');
	pg_assert_contains('pg_bundle_ids_among(array($product_id))', $body, 'read from the bundle flag');
}
