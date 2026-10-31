<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the out-of-stock flag the product screens work out
 * (pg_pb_apply_stock_flag(), product_builder.php): a tracked product with
 * an empty quantity is stored with 0 in stock, so it is out of stock from
 * the moment it is created, as the first edit save marked it before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');

function test_product_stock_flag_on_create()
{
	foreach (array('', ' ', '0', '-3') as $quantity) {
		$product = array('inventory' => '1', 'inventory_quantity' => $quantity);
		$before = time();
		pg_pb_apply_stock_flag($product);

		pg_assert_same('1', $product['out_of_stock'], 'tracked, quantity "' . $quantity . '" is out of stock');
		pg_assert_true(isset($product['out_of_stock_timestamp']) && ($product['out_of_stock_timestamp'] >= $before), 'stamped at creation: "' . $quantity . '"');
	}

	$product = array('inventory' => '1', 'inventory_quantity' => '4');
	pg_pb_apply_stock_flag($product);
	pg_assert_same('0', $product['out_of_stock'], 'tracked with stock');
	pg_assert_same(false, isset($product['out_of_stock_timestamp']), 'no stamp with stock');

	$product = array('inventory' => '', 'inventory_quantity' => '');
	pg_pb_apply_stock_flag($product);
	pg_assert_same('0', $product['out_of_stock'], 'untracked is never out of stock');

	$product = array('name' => 'K');
	pg_pb_apply_stock_flag($product);
	pg_assert_same(false, isset($product['out_of_stock']), 'a write without stock columns is left alone');
}
