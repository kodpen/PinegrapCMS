<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Etsy stock and price sent from the queue (includes/fn/etsy.php
 * and the connector's push_stock_price): the inventory read in bulk, the
 * whole products array sent back with the batch's products changed, the
 * price converted to the shop's currency, the pace the answers' limits
 * ask for, refusals and what waits.
 *
 * Etsy is never called. The answers in tests/fixtures/etsy/ follow the
 * response shapes of Etsy's Open API v3 documentation (November 2026) and
 * have not been recorded from a live shop.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/etsy.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/etsy.php');

function etsy_stock_test_fixture($name)
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/etsy/' . $name), true);
}

// A call answering each "METHOD path" from $routes (=> array(status,
// fixture or null, headers)), writing each one and its options into $calls;
// an unknown one is 404.
function etsy_stock_test_call($routes, &$calls)
{
	return function ($method, $path, $options = array()) use ($routes, &$calls) {
		$key = $method . ' ' . $path;
		$calls[] = array($key, $options);

		if (!isset($routes[$key])) {
			return array('ok' => false, 'status' => 404, 'data' => array(), 'error' => '', 'headers' => array());
		}

		$status = $routes[$key][0];

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status,
			'data' => ($routes[$key][1] !== null) ? etsy_stock_test_fixture($routes[$key][1]) : array(), 'error' => '',
			'headers' => isset($routes[$key][2]) ? $routes[$key][2] : array());
	};
}

function etsy_stock_test_account($push_price = true, $push_stock = true)
{
	return array('id' => 9, 'provider' => 'etsy', 'push_price' => $push_price, 'push_stock' => $push_stock,
		'credentials' => array('shop' => '87654321'));
}

function etsy_stock_test_row($product_id, $code, $listing_id, $overrides = array())
{
	return $overrides + array(
		'product_id'  => $product_id,
		'remote_code' => $code,
		'remote_id'   => $listing_id,
		'price'       => 3990,
		'price_major' => 39.9,
		'quantity'    => 5,
		'tracked'     => true,
	);
}

function etsy_stock_test_batch_path($ids)
{
	return 'GET /listings/batch?listing_ids=' . implode(',', $ids) . '&includes=Inventory';
}

function etsy_stock_test_no_sleep(&$slept)
{
	return function ($microseconds) use (&$slept) {
		$slept[] = $microseconds;
	};
}

// The read inventory becomes a body with every live product, no ids, the
// price as a decimal; only the changed SKU's offering differs.
function test_etsy_inventory_update_keeps_other_products()
{
	$batch = pg_etsy_batch_inventories(etsy_stock_test_fixture('listings-batch-inventory.json'));

	pg_assert_same(array('5001'), array_map('strval', array_keys($batch)));

	$update = pg_etsy_inventory_update($batch['5001'], array('APRON-M' => array('quantity' => 1200, 'price' => '30.00')));

	pg_assert_same(array('APRON-M'), $update['found']);
	pg_assert_same(2, count($update['body']['products']));
	pg_assert_same(array('sku' => 'APRON-S', 'property_values' => array(array('property_id' => 100, 'value_ids' => array(11), 'values' => array('S'), 'property_name' => 'Size', 'scale_id' => 7)),
		'offerings' => array(array('price' => 24.5, 'quantity' => 3, 'is_enabled' => true, 'readiness_state_id' => 301))), $update['body']['products'][0]);
	pg_assert_same(array('price' => 30.0, 'quantity' => 999, 'is_enabled' => true, 'readiness_state_id' => 301), $update['body']['products'][1]['offerings'][0]);
	pg_assert_same(array(100), $update['body']['price_on_property']);
	pg_assert_same(array(), $update['body']['readiness_state_on_property']);
	pg_assert_same('USD', pg_etsy_inventory_currency($batch['5001']));
}

function test_etsy_inventory_update_disables_empty_and_skips_unchanged()
{
	$inventory = etsy_stock_test_fixture('listing-inventory.json');

	$update = pg_etsy_inventory_update($inventory, array('APRON-S' => array('quantity' => 0, 'price' => '')));
	pg_assert_same(array('price' => 24.5, 'quantity' => 0, 'is_enabled' => false), $update['body']['products'][0]['offerings'][0]);

	$same = pg_etsy_inventory_update($inventory, array('APRON-S' => array('quantity' => 3, 'price' => '24.50')));
	pg_assert_same(null, $same['body']);
	pg_assert_same(array('APRON-S'), $same['found']);

	$missing = pg_etsy_inventory_update($inventory, array('OTHER' => array('quantity' => 1, 'price' => '')));
	pg_assert_same(array(), $missing['found']);
	pg_assert_same(null, $missing['body']);
}

function test_etsy_pace_reads_the_limits()
{
	pg_assert_same(array('wait' => 0, 'stop' => 0), pg_etsy_pace(array('status' => 200, 'headers' => array('x-remaining-this-second' => '4', 'x-remaining-today' => '900'))));
	pg_assert_same(array('wait' => 1, 'stop' => 0), pg_etsy_pace(array('status' => 200, 'headers' => array('x-remaining-this-second' => '0', 'x-remaining-today' => '900'))));
	pg_assert_same(array('wait' => 0, 'stop' => 3600), pg_etsy_pace(array('status' => 200, 'headers' => array('x-remaining-today' => '0'))));
	pg_assert_same(array('wait' => 0, 'stop' => 5), pg_etsy_pace(array('status' => 429, 'headers' => array('retry-after' => '5'))));
	pg_assert_same(array('wait' => 0, 'stop' => 60), pg_etsy_pace(array('status' => 429)));
	pg_assert_same(array('wait' => 0, 'stop' => 0), pg_etsy_pace(array('status' => 0)));
}

// One bulk read, the listing missing its inventory read on its own, one
// PUT a listing with the whole products array, the price converted.
function test_etsy_push_sends_whole_products_array_a_listing()
{
	$calls = array();
	$slept = array();
	$routes = array(
		etsy_stock_test_batch_path(array('5001', '5002')) => array(200, 'listings-batch-inventory.json', array('x-remaining-this-second' => '0')),
		'GET /listings/5002/inventory' => array(200, 'listing-inventory.json'),
		'PUT /listings/5001/inventory' => array(200, 'listing-inventory.json'),
		'PUT /listings/5002/inventory' => array(400, 'error-listing.json'),
	);
	$rows = array(
		etsy_stock_test_row(1, 'APRON-M', '5001', array('quantity' => 2)),
		etsy_stock_test_row(2, 'APRON-S', '5002', array('price_major' => 10)),
		etsy_stock_test_row(3, 'NEW-1', ''),
		etsy_stock_test_row(4, 'GONE', '5001'),
	);

	$result = mp_etsy_push_stock_price(etsy_stock_test_account(), $rows, etsy_stock_test_call($routes, $calls), 1.25, etsy_stock_test_no_sleep($slept), 60);

	pg_assert_true($result['ok']);
	pg_assert_same(array(1), $result['data']['pushed']);
	$refused = $result['data']['refused'];
	ksort($refused);
	pg_assert_same(array(2, 3, 4), array_keys($refused));
	pg_assert_contains('taxonomy_id', $result['data']['refused'][2]);
	pg_assert_same(array(), $result['data']['deferred']);
	pg_assert_same(array(1000000), $slept);
	pg_assert_same(4, count($calls));

	pg_assert_same('GET /listings/5002/inventory', $calls[2][0], 'the listing without its inventory is read on its own');
	$body = json_decode($calls[1][1]['body'], true);
	pg_assert_same('PUT /listings/5001/inventory', $calls[1][0]);
	pg_assert_same(array('APRON-S', 'APRON-M'), array($body['products'][0]['sku'], $body['products'][1]['sku']));
	pg_assert_same(array('price' => 49.88, 'quantity' => 2, 'is_enabled' => true, 'readiness_state_id' => 301), $body['products'][1]['offerings'][0]);
	pg_assert_same(24.5, $body['products'][0]['offerings'][0]['price']);
}

function test_etsy_push_defers_after_a_rate_limit()
{
	$calls = array();
	$slept = array();
	$routes = array(
		etsy_stock_test_batch_path(array('5001')) => array(200, 'listings-batch-inventory.json'),
		'PUT /listings/5001/inventory' => array(429, 'error-rate-limit.json', array('retry-after' => '7')),
	);
	$rows = array(etsy_stock_test_row(1, 'APRON-M', '5001'), etsy_stock_test_row(2, 'APRON-S', '5001'));

	$result = mp_etsy_push_stock_price(etsy_stock_test_account(), $rows, etsy_stock_test_call($routes, $calls), 1, etsy_stock_test_no_sleep($slept), 60);

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
	pg_assert_same(7, $result['data']['retry_after']);

	// A refusal beside it keeps the batch: the rest is deferred.
	$rows[] = etsy_stock_test_row(3, 'NEW-1', '');
	$calls = array();
	$result = mp_etsy_push_stock_price(etsy_stock_test_account(), $rows, etsy_stock_test_call($routes, $calls), 1, etsy_stock_test_no_sleep($slept), 60);

	pg_assert_true($result['ok']);
	pg_assert_same(array(1, 2), $result['data']['deferred']);
	pg_assert_same(7, $result['data']['retry_after']);
}

function test_etsy_push_rate_stock_only_and_untracked()
{
	$calls = array();
	$slept = array();
	$routes = array(
		etsy_stock_test_batch_path(array('5001')) => array(200, 'listings-batch-inventory.json'),
		'PUT /listings/5001/inventory' => array(200, 'listing-inventory.json'),
	);
	$rows = array(etsy_stock_test_row(1, 'APRON-M', '5001', array('quantity' => 9)), etsy_stock_test_row(2, 'APRON-S', '5001', array('tracked' => false)));

	$result = mp_etsy_push_stock_price(etsy_stock_test_account(), $rows, etsy_stock_test_call($routes, $calls), false, etsy_stock_test_no_sleep($slept), 60);
	pg_assert_same(array(1, 2), array_keys($result['data']['refused']));
	pg_assert_contains('exchange rate', $result['data']['refused'][1]);
	pg_assert_same(1, count($calls));

	$calls = array();
	$result = mp_etsy_push_stock_price(etsy_stock_test_account(false, true), $rows, etsy_stock_test_call($routes, $calls), null, etsy_stock_test_no_sleep($slept), 60);
	pg_assert_same(array(1), $result['data']['pushed']);
	$body = json_decode($calls[1][1]['body'], true);
	pg_assert_same(array('price' => 24.5, 'quantity' => 9, 'is_enabled' => true, 'readiness_state_id' => 301), $body['products'][1]['offerings'][0]);

	$calls = array();
	$result = mp_etsy_push_stock_price(etsy_stock_test_account(false, false), $rows, etsy_stock_test_call($routes, $calls));
	pg_assert_same(array('task_id' => ''), $result['data']);
	pg_assert_same(array(), $calls);
}

function test_etsy_push_past_budget_defers()
{
	$calls = array();
	$slept = array();
	$result = mp_etsy_push_stock_price(etsy_stock_test_account(), array(etsy_stock_test_row(1, 'APRON-M', '5001')),
		etsy_stock_test_call(array(), $calls), 1, etsy_stock_test_no_sleep($slept), 0);

	pg_assert_true($result['ok']);
	pg_assert_same(array(1), $result['data']['deferred']);
	pg_assert_same(array(), $calls);
}
