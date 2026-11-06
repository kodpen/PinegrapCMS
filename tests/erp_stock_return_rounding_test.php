<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a line returned in parts takes back exactly the cost (and the
 * quantity) its invoice line moved: each part is the difference of the
 * rounded running totals (erp_stock_return_moves(), includes/erp/stock.php).
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

// Three units costed 200 returned one by one: 67 + 66 + 67, never 201.
function test_erp_stock_return_parts_add_up_to_the_cost()
{
	$parent = array(array('product_id' => 3, 'kind' => 'purchase', 'quantity' => '3.0000', 'cost_total' => 200, 'stock_wanted' => 1));
	$costs = array();

	for ($before = 0; $before < 3; $before++) {
		$moves = erp_stock_return_moves($parent, 3, 1, $before);
		$costs[] = $moves[0]['cost_total'];
		pg_assert_same(1.0, $moves[0]['quantity']);
	}

	pg_assert_same(200, array_sum($costs), 'the parts of a full return add up to the line\'s cost');
	pg_assert_same(array(67, 66, 67), $costs);
}

// A bundle component of 1/3 unit per bundle: three single returns give back
// the whole quantity, not 0.9999.
function test_erp_stock_return_parts_add_up_to_the_quantity()
{
	$parent = array(array('product_id' => 9, 'kind' => 'sale', 'quantity' => '1.0000', 'cost_total' => 100, 'stock_wanted' => 0));
	$quantity = 0.0;
	$cost = 0;

	for ($before = 0; $before < 3; $before++) {
		$moves = erp_stock_return_moves($parent, 3, 1, $before);
		$quantity += $moves[0]['quantity'];
		$cost += $moves[0]['cost_total'];
	}

	pg_assert_same('1.0000', number_format($quantity, 4, '.', ''));
	pg_assert_same(100, $cost);
}

// Without earlier parts the share is the plain proportion, as before.
function test_erp_stock_return_first_part_unchanged()
{
	$moves = erp_stock_return_moves(array(
		array('product_id' => 3, 'kind' => 'sale', 'quantity' => '5.0000', 'cost_total' => 1000, 'stock_wanted' => 1),
	), 5, 2);

	pg_assert_same(400, $moves[0]['cost_total']);
	pg_assert_same(2.0, $moves[0]['quantity']);
}
