<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for eBay stock and price sent in bulk (includes/fn/ebay.php and the
 * connector's push_stock_price): each SKU's request, the price converted to
 * the site's currency and rounded to the cent, the verdicts of a 207
 * answer, refusals, and what waits after a fault on eBay's side.
 *
 * eBay is never called. The answers in tests/fixtures/ebay/ follow the
 * response shapes of eBay's public Inventory API documentation (November
 * 2026) and have not been recorded from a live account.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/ebay.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ebay.php');

// A call answering "METHOD path" from $routes (=> array(status, fixture or
// null)) and writing each call into $calls; an unknown offer GET is 404.
function ebay_stock_test_call($routes, &$calls)
{
	return function ($method, $path, $body = null) use ($routes, &$calls) {
		$key = $method . ' ' . $path;
		$calls[] = array('call' => $key, 'body' => $body);

		if (isset($routes[$key])) {
			$status = $routes[$key][0];
			$data = ($routes[$key][1] !== null)
				? json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/ebay/' . $routes[$key][1]), true) : array();
		} elseif ($method === 'GET') {
			$status = 404;
			$data = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/ebay/offer-not-found.json'), true);
		} else {
			$status = 200;
			$data = array('responses' => array());
		}

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'data' => is_array($data) ? $data : array(), 'error' => '');
	};
}

function ebay_stock_test_account($push_price = true, $push_stock = true)
{
	return array('id' => 7, 'provider' => 'ebay', 'push_price' => $push_price, 'push_stock' => $push_stock,
		'credentials' => array('environment' => 'production', 'site' => 'EBAY_DE'));
}

function ebay_stock_test_row($product_id, $code, $overrides = array())
{
	return $overrides + array(
		'product_id'  => $product_id,
		'remote_code' => $code,
		'remote_id'   => '',
		'price'       => 3990,
		'price_major' => 39.9,
		'quantity'    => 3,
		'tracked'     => true,
	);
}

function ebay_stock_test_routes()
{
	return array(
		'GET /sell/inventory/v1/offer?sku=CHAIR-RED&marketplace_id=EBAY_DE'  => array(200, 'offers.json'),
		'GET /sell/inventory/v1/offer?sku=CHAIR-BLUE&marketplace_id=EBAY_DE' => array(200, 'offers-blue.json'),
	);
}

function test_ebay_connector_sends_stock_and_price()
{
	pg_assert_true(mp_can('ebay', 'push_stock_price'));
	pg_assert_same(25, pg_ebay_listing_limits()['bulk']);
}

function test_ebay_bulk_request_carries_quantity_and_price()
{
	pg_assert_same(array(
		'sku'                        => 'CHAIR-RED',
		'shipToLocationAvailability' => array('quantity' => 3),
		'offers'                     => array(array('offerId' => '5005317010', 'availableQuantity' => 3, 'price' => array('value' => '45.50', 'currency' => 'EUR'))),
	), pg_ebay_bulk_request(ebay_stock_test_row(11, 'CHAIR-RED'), '5005317010', true, '45.50', 'EUR'));

	pg_assert_same(array(
		'sku'                        => 'CHAIR-RED',
		'shipToLocationAvailability' => array('quantity' => 0),
		'offers'                     => array(array('offerId' => '1', 'availableQuantity' => 0)),
	), pg_ebay_bulk_request(ebay_stock_test_row(11, 'CHAIR-RED', array('quantity' => -2)), '1', true, '', 'EUR'), 'stock alone, never below zero');
}

function test_ebay_bulk_request_sends_no_quantity_for_untracked_stock()
{
	$row = ebay_stock_test_row(11, 'CHAIR-RED', array('tracked' => false));

	pg_assert_same(null, pg_ebay_bulk_request($row, '1', true, '', 'EUR'), 'nothing to say');
	pg_assert_same(array('sku' => 'CHAIR-RED', 'offers' => array(array('offerId' => '1', 'price' => array('value' => '9.99', 'currency' => 'USD')))),
		pg_ebay_bulk_request($row, '1', true, '9.99', 'USD'));
}

function test_ebay_bulk_verdicts_name_each_skus_errors()
{
	$json = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/ebay/bulk-update.json'), true);

	pg_assert_same(array('CHAIR-RED' => '', 'CHAIR-BLUE' => 'Invalid value for price. The price must be greater than 0.99.'), pg_ebay_bulk_verdicts($json));
	pg_assert_same(array('X' => 'HTTP 500'), pg_ebay_bulk_verdicts(array('responses' => array(array('sku' => 'X', 'statusCode' => 500)))));
	pg_assert_same(array(), pg_ebay_bulk_verdicts(null));
}

function test_ebay_push_converts_the_price_and_reads_verdicts()
{
	$calls = array();
	$routes = ebay_stock_test_routes() + array('POST /sell/inventory/v1/bulk_update_price_quantity' => array(207, 'bulk-update.json'));

	$result = mp_ebay_push_stock_price(ebay_stock_test_account(), array(
		ebay_stock_test_row(11, 'CHAIR-RED'),
		ebay_stock_test_row(12, 'CHAIR-BLUE', array('price' => 1999, 'price_major' => 19.99)),
	), ebay_stock_test_call($routes, $calls), 0.9235);

	pg_assert_true($result['ok']);
	pg_assert_same('', $result['data']['task_id']);
	pg_assert_same(array(11), $result['data']['pushed']);
	pg_assert_same(array(12 => 'eBay: Invalid value for price. The price must be greater than 0.99.'), $result['data']['refused']);
	pg_assert_same(array(), $result['data']['deferred']);
	pg_assert_same(3, count($calls), 'two offer look-ups and one bulk call');

	$requests = $calls[2]['body']['requests'];

	// 39.90 x 0.9235 = 36.84765 and 19.99 x 0.9235 = 18.460765: rounded, never cut.
	pg_assert_same('36.85', $requests[0]['offers'][0]['price']['value']);
	pg_assert_same('18.46', $requests[1]['offers'][0]['price']['value']);
	pg_assert_same('EUR', $requests[0]['offers'][0]['price']['currency']);
	pg_assert_same('5005317011', $requests[1]['offers'][0]['offerId']);
}

function test_ebay_push_refuses_a_sku_not_listed_and_one_with_no_price()
{
	$calls = array();
	$result = mp_ebay_push_stock_price(ebay_stock_test_account(), array(
		ebay_stock_test_row(11, 'CHAIR-GREEN'),
		ebay_stock_test_row(12, 'CHAIR-RED', array('price' => 0, 'price_major' => 0)),
	), ebay_stock_test_call(array(), $calls), 1.0);

	pg_assert_true($result['ok']);
	pg_assert_same(array(11, 12), array_keys($result['data']['refused']));
	pg_assert_contains('CHAIR-GREEN', $result['data']['refused'][11]);
	pg_assert_same(1, count($calls), 'no price is refused before any call, and nothing left to send');
}

function test_ebay_push_with_no_rate_refuses_every_product_before_any_call()
{
	$calls = array();
	$result = mp_ebay_push_stock_price(ebay_stock_test_account(), array(ebay_stock_test_row(11, 'CHAIR-RED')),
		ebay_stock_test_call(array(), $calls), false);

	pg_assert_true($result['ok']);
	pg_assert_same(array(11), array_keys($result['data']['refused']));
	pg_assert_same(0, count($calls));
}

function test_ebay_push_stock_alone_needs_no_rate_and_sends_no_price()
{
	$calls = array();
	$result = mp_ebay_push_stock_price(ebay_stock_test_account(false, true), array(ebay_stock_test_row(11, 'CHAIR-RED')),
		ebay_stock_test_call(ebay_stock_test_routes(), $calls), false);

	pg_assert_same(array(11), $result['data']['pushed']);
	pg_assert_same(array(array('offerId' => '5005317010', 'availableQuantity' => 3)), $calls[1]['body']['requests'][0]['offers']);
}

function test_ebay_push_sends_nothing_when_the_account_sends_neither()
{
	$calls = array();
	$result = mp_ebay_push_stock_price(ebay_stock_test_account(false, false), array(ebay_stock_test_row(11, 'CHAIR-RED')),
		ebay_stock_test_call(array(), $calls), 1.0);

	pg_assert_same(array('task_id' => ''), $result['data']);
	pg_assert_same(0, count($calls));
}

function test_ebay_push_sends_at_most_25_skus_a_call()
{
	$rows = array();
	$routes = array();

	for ($i = 1; $i <= 30; $i++) {
		$rows[] = ebay_stock_test_row($i, 'CHAIR-RED');
	}

	$calls = array();
	$result = mp_ebay_push_stock_price(ebay_stock_test_account(), $rows, ebay_stock_test_call(ebay_stock_test_routes(), $calls), 1.0);

	$bulk = array_values(array_filter($calls, function ($call) {
		return $call['call'] === 'POST /sell/inventory/v1/bulk_update_price_quantity';
	}));

	pg_assert_same(2, count($bulk));
	pg_assert_same(25, count($bulk[0]['body']['requests']));
	pg_assert_same(5, count($bulk[1]['body']['requests']));
	pg_assert_same(30, count($result['data']['pushed']));
}

function test_ebay_fault_on_their_side_defers_the_rest()
{
	$calls = array();
	$routes = ebay_stock_test_routes();
	$routes['GET /sell/inventory/v1/offer?sku=CHAIR-BLUE&marketplace_id=EBAY_DE'] = array(429, null);

	$result = mp_ebay_push_stock_price(ebay_stock_test_account(), array(
		ebay_stock_test_row(11, 'CHAIR-RED'),
		ebay_stock_test_row(12, 'CHAIR-BLUE'),
		ebay_stock_test_row(13, 'CHAIR-RED'),
	), ebay_stock_test_call($routes, $calls), 1.0);

	pg_assert_true($result['ok']);
	pg_assert_same(array(11), $result['data']['pushed'], 'what was looked up before the fault still goes');
	pg_assert_same(array(12, 13), $result['data']['deferred']);
	pg_assert_same(60, $result['data']['retry_after']);
}

function test_ebay_fault_on_the_first_call_fails_the_batch_to_try_again()
{
	$calls = array();
	$routes = ebay_stock_test_routes() + array('POST /sell/inventory/v1/bulk_update_price_quantity' => array(500, 'error-system.json'));

	$result = mp_ebay_push_stock_price(ebay_stock_test_account(), array(ebay_stock_test_row(11, 'CHAIR-RED')),
		ebay_stock_test_call($routes, $calls), 1.0);

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
	pg_assert_contains('A system error has occurred.', $result['error']);
}

function test_ebay_push_past_its_time_budget_defers_the_rest()
{
	$calls = array();
	$result = mp_ebay_push_stock_price(ebay_stock_test_account(), array(ebay_stock_test_row(11, 'CHAIR-RED')),
		ebay_stock_test_call(ebay_stock_test_routes(), $calls), 1.0, 0);

	pg_assert_same(array(11), $result['data']['deferred']);
	pg_assert_same(0, count($calls));
}
