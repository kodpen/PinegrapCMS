<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for POST /orders (includes/api/resources/orders_create.php): the
 * route row, how the lines are read (pg_local_order_items_input()), the split
 * payment's parts, the answer, and that the handler checks everything before
 * it writes and writes only through the counter sale's own functions.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders_create.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/local_order.php');

function api_orders_create_test_route()
{
	foreach (api_schema() as $route) {
		if ($route['id'] === 'orders.create') {
			return $route;
		}
	}

	return null;
}

function test_api_orders_create_route()
{
	$route = api_orders_create_test_route();

	pg_assert_true(is_array($route), 'declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/orders', $route['path']);
	pg_assert_same('orders:write', $route['scope']);
	pg_assert_true(!empty($route['dry_run']), 'rehearsable');
	pg_assert_same(201, $route['status']);
	pg_assert_same('LocalSale', $route['returns']);

	$names = array_column($route['params'], 'name');
	pg_assert_same(array('items', 'contact_id', 'account_id', 'payment_method', 'till_id', 'received', 'payment_parts'), $names);

	$methods = null;
	foreach ($route['params'] as $param) {
		if ($param['name'] === 'payment_method') {
			$methods = $param['values'];
		}
	}
	pg_assert_same(api_local_sale_methods(), $methods, 'the route takes the methods the counter offers');
	pg_assert_same(array_keys(local_sale_methods()), api_local_sale_methods(), 'the counter offers them');
}

function test_api_orders_create_items_input()
{
	$read = pg_local_order_items_input(array(
		array('product_id' => 7, 'quantity' => 2),
		array('product_id' => '9', 'quantity' => '1'),
		array('product_id' => 7, 'quantity' => 3),
	));

	pg_assert_same('', $read['error']);
	pg_assert_same(array(7 => 5, 9 => 1), $read['items'], 'one line per product, quantities added');
}

function test_api_orders_create_items_refused()
{
	$shape = array(
		null,
		array(),
		'7',
		array(array('product_id' => 7)),
		array(array('quantity' => 1)),
		array(array('product_id' => 0, 'quantity' => 1)),
		array(array('product_id' => 7, 'quantity' => 1.5)),
		array(array('product_id' => 7, 'quantity' => '2.0')),
		array(array('product_id' => 7, 'quantity' => true)),
		array(5),
	);

	foreach ($shape as $raw) {
		pg_assert_same('shape', pg_local_order_items_input($raw)['error'], var_export($raw, true));
	}

	pg_assert_same('quantity', pg_local_order_items_input(array(array('product_id' => 7, 'quantity' => 0)))['error'], 'zero');
	pg_assert_same('quantity', pg_local_order_items_input(array(array('product_id' => 7, 'quantity' => -1)))['error'], 'minus');
	pg_assert_same('quantity', pg_local_order_items_input(array(array('product_id' => 7, 'quantity' => 10000)))['error'], 'over 9999');

	$merged = pg_local_order_items_input(array(array('product_id' => 7, 'quantity' => 9000), array('product_id' => 8, 'quantity' => 1), array('product_id' => 7, 'quantity' => 1000)));
	pg_assert_same('quantity', $merged['error'], 'a line of one product past 9999');
	pg_assert_same(2, $merged['index'], 'the item that took it past');
}

function test_api_orders_create_parts()
{
	pg_assert_same(
		array(
			array('method' => 'cash', 'till' => 3, 'amount' => 500),
			array('method' => 'card', 'till' => 4, 'amount' => 0),
		),
		api_local_sale_parts(array(
			array('method' => 'cash', 'till_id' => 3, 'amount' => 500),
			array('method' => 'card', 'till_id' => 4, 'amount' => 12.5),
			'junk',
		)),
		'read as the counter reads its split; a decimal amount is no amount'
	);
	pg_assert_same(array(), api_local_sale_parts(null));
}

function test_api_orders_create_present()
{
	$result = array(
		'total' => 1200,
		'method' => 'card',
		'invoice' => array('id' => 5, 'number' => 'A-5', 'type' => 'e-Arşiv'),
		'receipt' => array('id' => 9, 'till' => 'Kasa'),
		'receipts' => array(),
		'warnings' => array(3 => 'later'),
	);

	$out = api_local_sale_present($result, array('id' => 1));

	pg_assert_same(array('order', 'invoice', 'receipts', 'warnings'), array_keys($out));
	pg_assert_same(array_keys(api_local_sale_schema()), array_keys($out), 'the schema names what is answered');
	pg_assert_same(array('id' => 5, 'number' => 'A-5'), $out['invoice']);
	pg_assert_same(array(array('id' => 9, 'till' => 'Kasa', 'method' => 'card', 'amount' => 1200)), $out['receipts']);
	pg_assert_same(array('later'), $out['warnings']);

	$split = api_local_sale_present(array('total' => 1200, 'method' => 'split', 'invoice' => null, 'receipt' => null, 'receipts' => array(array('id' => 1, 'till' => 'Kasa', 'method' => 'cash', 'amount' => 700)), 'warnings' => array()), null);
	pg_assert_same(null, $split['invoice']);
	pg_assert_same(700, $split['receipts'][0]['amount']);
}

// Every check runs before the first write, a refused completion leaves no
// basket, and the sale is the counter's own completion.
function test_api_orders_create_handler_order()
{
	$code = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/orders_create.php');

	$check = strpos($code, '$checked = pg_local_order_check($user, 0, $lines, $input);');
	$stop = strpos($code, "api_dry_run_stop('created', 'order'");
	$open = strpos($code, '$order_id = pg_local_order_open(');
	$complete = strpos($code, '$done = pg_local_order_complete($user, $order_id, $customer, $input);');
	$discard = strpos($code, 'pg_local_order_discard($order_id);');

	pg_assert_true(($check !== false) && ($stop !== false) && ($check < $stop), 'checked, then the rehearsal stops');
	pg_assert_true(($open !== false) && ($stop < $open), 'nothing written above the stop');
	pg_assert_true(($complete !== false) && ($open < $complete), 'opened, then completed');
	pg_assert_true(($discard !== false) && ($complete < $discard), 'a refused completion is discarded');
	pg_assert_same(0, substr_count($code, "pg_announce('order.created'"), 'order.created comes from the completion, once');
	pg_assert_same(0, substr_count($code, 'INSERT INTO'), 'no write of its own');
}
