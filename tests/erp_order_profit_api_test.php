<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order profit routes of the external API (includes/erp/api.php,
 * includes/erp/api_order_profit.php), with no database: the two route rows,
 * their scope and filters, the answers' shape, and the product filter of the
 * profitability report.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');

function erp_order_profit_api_test_routes()
{
	$routes = array();

	foreach (erp_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

function erp_order_profit_api_test_group($product_id, $channel, $revenue, $net)
{
	return array(
		'product_id' => $product_id, 'channel' => $channel, 'orders' => 1, 'units' => 2.0,
		'revenue' => $revenue, 'cost_product' => $revenue - $net, 'cost_payment' => 0, 'cost_marketplace' => 0,
		'cost_shipping' => 0, 'cost_other' => 0, 'net' => $net, 'margin' => erp_order_profit_margin($net, $revenue),
		'estimate' => 0, 'incomplete' => 1,
	);
}

function test_erp_order_profit_api_routes()
{
	$routes = erp_order_profit_api_test_routes();

	pg_assert_true(isset($routes['erp.orders.profit']), 'order profit route');
	pg_assert_true(isset($routes['erp.profitability']), 'profitability route');

	$order = $routes['erp.orders.profit'];
	pg_assert_same('GET', $order['method'], 'read only');
	pg_assert_same('/erp/orders/{id}/profit', $order['path'], 'path');
	pg_assert_same('erp:read', $order['scope'], 'behind the ERP read scope');
	pg_assert_true(function_exists($order['handler']), 'handler exists');

	$report = $routes['erp.profitability'];
	pg_assert_same('GET', $report['method'], 'read only');
	pg_assert_same('/erp/profitability', $report['path'], 'path');
	pg_assert_same('erp:read', $report['scope'], 'behind the ERP read scope');
	pg_assert_true(function_exists($report['handler']), 'handler exists');

	$names = array();
	foreach ($report['params'] as $param) {
		$names[] = $param['name'];
	}

	foreach (array('month', 'from', 'to', 'channel', 'product_id', 'limit') as $name) {
		pg_assert_true(in_array($name, $names, true), 'filter ' . $name);
	}

	$objects = erp_openapi_objects();
	pg_assert_same('erp_api_order_profit_schema', $objects[$order['returns']], 'order profit object declared');
	pg_assert_same('erp_api_profitability_schema', $objects[$report['returns']], 'profitability object declared');
}

function test_erp_order_profit_api_order_present()
{
	$profit = array(
		'order_id' => '7', 'channel' => 'marketplace:3', 'state' => 'estimate', 'stale' => '0', 'revenue' => '10000',
		'cost_product' => '6000', 'cost_payment' => '0', 'cost_marketplace' => '1500', 'cost_shipping' => '500',
		'cost_other' => '0', 'net' => '2000', 'margin' => '20.0', 'computed_at' => '0',
	);
	$costs = array(array('kind' => 'marketplace', 'source' => 'estimate', 'source_ref' => '', 'amount' => '1500'));

	$out = erp_api_order_profit_present(7, $profit, $costs);
	$schema = erp_api_order_profit_schema();

	pg_assert_same(array_keys($schema), array_keys($out), 'fields as declared');
	pg_assert_true($out['computed'], 'computed');
	pg_assert_same(2000, $out['net'], 'net as an integer');
	pg_assert_same(20.0, $out['margin'], 'margin as a number');
	pg_assert_same('estimate', $out['state'], 'state');
	pg_assert_same(array('kind' => 'marketplace', 'source' => 'estimate', 'source_ref' => '', 'amount' => 1500), $out['costs'][0], 'cost row');

	$none = erp_api_order_profit_present(8, null, array());
	pg_assert_same(array_keys($schema), array_keys($none), 'same fields when not computed');
	pg_assert_false($none['computed'], 'not computed');
	pg_assert_same(null, $none['net'], 'no figures');
	pg_assert_same(array(), $none['costs'], 'no cost rows');
}

function test_erp_order_profit_api_profitability_filters()
{
	$groups = array(
		erp_order_profit_api_test_group(10, 'web', 1000, 300),
		erp_order_profit_api_test_group(11, 'web', 500, -50),
		erp_order_profit_api_test_group(10, 'marketplace:3', 2000, 100),
	);
	$report = array('from' => '2026-11-01', 'to' => '2026-11-30', 'channel' => '', 'groups' => $groups,
		'totals' => erp_profitability_totals($groups), 'orders' => 3, 'capped' => false, 'products' => array(), 'uncomputed' => 0);

	$only = erp_profitability_for_product($report, 10);
	pg_assert_same(2, count($only['groups']), 'one product on two channels');
	pg_assert_same(400, $only['totals']['all']['net'], 'totals of the product only');
	pg_assert_same(array('web', 'marketplace:3'), array_keys($only['totals']['channels']), 'its channels');
	pg_assert_same($report, erp_profitability_for_product($report, 0), 'no product: the whole report');

	$evens = array(erp_profitability_key(10, 'web') => array('price' => 350, 'target_price' => null, 'average_price' => 500));
	$out = erp_api_profitability_present($only, $evens, 1, null, array(), 10);
	$schema = erp_api_profitability_schema();

	pg_assert_same(array_keys($schema), array_keys($out), 'fields as declared');
	pg_assert_same(1, count($out['products']), 'limit');
	pg_assert_same(2, $out['products_count'], 'count before the limit');
	pg_assert_same(array_keys($schema['products'][0]), array_keys($out['products'][0]), 'product row as declared');
	pg_assert_same(350, $out['products'][0]['break_even']['price'], 'break-even of the product on its channel');
	pg_assert_same(array_keys($schema['channels'][0]), array_keys($out['channels'][0]), 'channel row as declared');
	pg_assert_same(10, $out['product_id'], 'product filter echoed');
	pg_assert_same(null, $out['channel'], 'no channel filter');

	$out = erp_api_profitability_present($report, array(), 50, 15.0, array(), 0);
	pg_assert_same(null, $out['products'][1]['break_even']['price'], 'no 90-day sales: no break-even');
	pg_assert_same(15.0, $out['target_margin'], 'target margin');
}
