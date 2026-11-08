<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /inventory/moves (includes/api/resources/inventory.php): the
 * StockMove object, the WHERE built from the stock history's filters and the
 * route row the router, the OpenAPI document and the MCP tools read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function stock_moves_api_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function stock_moves_api_test_escape($value)
{
	return addslashes($value);
}

function test_stock_moves_api_present()
{
	$row = array(
		'id' => '41', 'product_id' => '12', 'product_name' => 'SKU-12', 'location_id' => '3',
		'delta' => '-2', 'quantity_after' => '8', 'reason' => 'checkout', 'ref' => 'O1001',
		'note' => '', 'user_id' => '0', 'created_at' => '1700000000',
	);

	$out = api_stock_move_present($row, array(3 => 'Shop', 7 => 'Warehouse'));

	pg_assert_same(41, $out['id']);
	pg_assert_same(12, $out['product_id']);
	pg_assert_same('SKU-12', $out['product_name']);
	pg_assert_same(3, $out['location_id']);
	pg_assert_same('Shop', $out['location_name']);
	pg_assert_same(-2, $out['delta']);
	pg_assert_same(8, $out['quantity_after']);
	pg_assert_same('checkout', $out['reason']);
	pg_assert_same('O1001', $out['ref']);
	pg_assert_same(null, $out['user_id'], 'no user (a visitor order) is null');
	pg_assert_same(api_time(1700000000), $out['created_at']);
	pg_assert_same(array_keys(api_stock_move_schema()), array_keys($out), 'presenter and schema list the same fields');

	$gone = api_stock_move_present(array('product_name' => null, 'location_id' => 9, 'user_id' => 5) + $row, array(3 => 'Shop'));
	pg_assert_same(null, $gone['product_name'], 'a deleted product has no name');
	pg_assert_same(null, $gone['location_name'], 'an unknown location has no name');
	pg_assert_same(5, $gone['user_id']);
}

function test_stock_moves_api_where()
{
	$all = api_inventory_moves_where(array(), array(3, 7), 0, 'stock_moves_api_test_escape');
	pg_assert_same('1 = 1', $all, 'no filter reads every move');

	$where = api_inventory_moves_where(array(
		'product_id' => 12, 'location' => 7, 'reason' => 'transfer', 'from' => 1700000000, 'to' => 1700086399,
	), array(3, 7), 55, 'stock_moves_api_test_escape');

	pg_assert_same("stock_moves.product_id = 12 AND stock_moves.location_id = 7 AND stock_moves.reason = 'transfer'"
		. " AND stock_moves.created_at >= 1700000000 AND stock_moves.created_at <= 1700086399 AND stock_moves.id < 55", $where);

	// Every reason the writer records is a filter value.
	foreach (pg_stock_reasons() as $reason) {
		$one = api_inventory_moves_where(array('reason' => $reason), array(), 0, 'stock_moves_api_test_escape');
		pg_assert_same("stock_moves.reason = '" . $reason . "'", $one);
	}
}

function test_stock_moves_api_route()
{
	$route = stock_moves_api_test_route('inventory.moves');

	pg_assert_true(is_array($route), 'the route is declared');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/inventory/moves', $route['path']);
	pg_assert_same('products:read', $route['scope']);
	pg_assert_same('api_inventory_moves', $route['handler']);
	pg_assert_same(array('list' => 'StockMove'), $route['returns']);
	pg_assert_true(!isset($route['mcp']) || $route['mcp'] !== false, 'offered as an MCP tool');

	$params = array();

	foreach ($route['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_same(array('product_id', 'location', 'reason', 'from', 'to', 'limit', 'cursor'), array_keys($params));
	pg_assert_same(pg_stock_reasons(), $params['reason']['values'], 'every reason of the history');
	pg_assert_same('datetime', $params['from']['type']);
	pg_assert_same('datetime', $params['to']['type']);
	pg_assert_true(api_param_is_end_bound('to'), 'a bare day as to counts to its end');
	pg_assert_true(function_exists('pg_stock_transfer_check'), 'the resource loads the stock history module');
}
