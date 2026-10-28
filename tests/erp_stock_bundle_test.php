<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of a bundle line's stock moves
 * (includes/erp/stock.php): a purchased bundle's cost shared over its
 * components by what each costs, and the moves a return takes back from the
 * moves its invoice line made, one per component.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/stock.php');

// The cost goes to each component in proportion to unit cost × quantity, and
// the shares add up to the line's cost exactly.
function test_erp_stock_split_by_cost_weights()
{
	$split = erp_stock_split_by_cost(1000, array(
		7 => array('quantity' => 2.0, 'unit_cost' => 300),
		9 => array('quantity' => 1.0, 'unit_cost' => 400),
	));

	pg_assert_same(array(7 => 600, 9 => 400), $split);

	$odd = erp_stock_split_by_cost(1001, array(
		1 => array('quantity' => 1.0, 'unit_cost' => 100),
		2 => array('quantity' => 1.0, 'unit_cost' => 100),
		3 => array('quantity' => 1.0, 'unit_cost' => 100),
	));

	pg_assert_same(1001, array_sum($odd), 'the shares add up to the cost');
}

// No component has a cost yet: the quantities share the cost out.
function test_erp_stock_split_by_cost_without_costs()
{
	pg_assert_same(
		array(4 => 750, 5 => 250),
		erp_stock_split_by_cost(1000, array(
			4 => array('quantity' => 3.0, 'unit_cost' => 0),
			5 => array('quantity' => 1.0, 'unit_cost' => 0),
		)));
}

// A plain line: one move, the returned share at the move's cost, the stock
// changed only when the original changed it.
function test_erp_stock_return_moves_plain_line()
{
	$moves = erp_stock_return_moves(array(
		array('product_id' => 3, 'kind' => 'sale', 'quantity' => '5.0000', 'cost_total' => 1000, 'stock_wanted' => 1),
	), 5, 2);

	pg_assert_same(1, count($moves));
	pg_assert_same('sales_return', $moves[0]['kind']);
	pg_assert_same('in', $moves[0]['direction']);
	pg_assert_same(2.0, $moves[0]['quantity']);
	pg_assert_same(400, $moves[0]['cost_total']);
	pg_assert_true($moves[0]['stock_wanted']);
	pg_assert_false($moves[0]['purchase_side']);
}

// A bundle line of 2 bundles (2 of product 7 and 1 of product 9 in each):
// returning one bundle takes back half of each component's move.
function test_erp_stock_return_moves_bundle_line()
{
	$moves = erp_stock_return_moves(array(
		array('product_id' => 7, 'kind' => 'purchase', 'quantity' => '4.0000', 'cost_total' => 1200, 'stock_wanted' => 1),
		array('product_id' => 9, 'kind' => 'purchase', 'quantity' => '2.0000', 'cost_total' => 801, 'stock_wanted' => 0),
	), 2, 1);

	pg_assert_same(array(7, 9), array($moves[0]['product_id'], $moves[1]['product_id']));
	pg_assert_same(array(2.0, 1.0), array($moves[0]['quantity'], $moves[1]['quantity']));
	pg_assert_same(array(600, 401), array($moves[0]['cost_total'], $moves[1]['cost_total']));
	pg_assert_same(array('purchase_return', 'out'), array($moves[0]['kind'], $moves[0]['direction']));
	pg_assert_true($moves[0]['purchase_side']);
	pg_assert_true($moves[0]['stock_wanted']);
	pg_assert_false($moves[1]['stock_wanted'], 'a move that changed no count is returned without one');
}

// A share that is not a whole unit changes no count; nothing to return from
// gives nothing back.
function test_erp_stock_return_moves_edges()
{
	$moves = erp_stock_return_moves(array(
		array('product_id' => 3, 'kind' => 'sale', 'quantity' => '3.0000', 'cost_total' => 900, 'stock_wanted' => 1),
	), 2, 1);

	pg_assert_same(1.5, $moves[0]['quantity']);
	pg_assert_false($moves[0]['stock_wanted']);

	pg_assert_same(array(), erp_stock_return_moves(array(
		array('product_id' => 3, 'kind' => 'sale', 'quantity' => '3.0000', 'cost_total' => 900, 'stock_wanted' => 1),
	), 0, 1));
}
