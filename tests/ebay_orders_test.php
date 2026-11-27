<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for eBay orders (includes/fn/ebay.php and the connector's
 * pull_orders, order_stage and ship_package): the window the feed is asked
 * on, an order as the importer's package in its own currency and minor
 * units, unpaid and cancelled orders, paging, and the shipment sent back
 * with carrier and tracking number.
 *
 * eBay is never called. The answers in tests/fixtures/ebay/orders*.json and
 * fulfillment-error.json follow the response shapes of eBay's public
 * Fulfillment API documentation (November 2026) and have not been recorded
 * from a live account.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/ebay.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ebay.php');

function ebay_orders_test_fixture($name)
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/ebay/' . $name), true);
}

// A call answering each GET from $answers in turn (array(status, data)) and
// writing each call into $calls.
function ebay_orders_test_call($answers, &$calls)
{
	return function ($method, $path, $body = null) use (&$answers, &$calls) {
		$calls[] = array('call' => $method . ' ' . $path, 'body' => $body);
		$answer = $answers ? array_shift($answers) : array(200, ebay_orders_test_fixture('orders-empty.json'));

		return array('ok' => ($answer[0] >= 200 && $answer[0] < 300), 'status' => $answer[0],
			'data' => is_array($answer[1]) ? $answer[1] : array(), 'error' => '');
	};
}

function ebay_orders_test_account()
{
	return array('id' => 7, 'provider' => 'ebay', 'credentials' => array('environment' => 'production', 'site' => 'EBAY_DE'));
}

function test_ebay_orders_connector_names_the_order_entries()
{
	pg_assert_true(mp_can('ebay', 'pull_orders'));
	pg_assert_true(mp_can('ebay', 'order_stage'));
	pg_assert_true(mp_can('ebay', 'ship_package'));
	pg_assert_false(mp_can('ebay', 'approve_lines'), 'eBay has no acceptance step');
	pg_assert_true(mp_account_ships_itself(ebay_orders_test_account()), 'an eBay seller always ships with a carrier of its own');
	pg_assert_false(mp_account_ships_itself(array('provider' => 'trendyol', 'credentials' => array())));
}

function test_ebay_orders_minor_units_round_half_away_from_zero()
{
	pg_assert_same(7980, pg_ebay_minor(array('value' => '79.80', 'currency' => 'EUR')));
	pg_assert_same(1999, pg_ebay_minor(array('value' => '19.99')));
	pg_assert_same(500, pg_ebay_minor(array('value' => '-5.00')), 'a discount given as a negative amount');
	pg_assert_same(1, pg_ebay_minor(array('value' => '0.005')));
	pg_assert_same(0, pg_ebay_minor(null));
	pg_assert_same(0, pg_ebay_minor(array('value' => 'abc')));
}

function test_ebay_orders_window()
{
	$now = 1795000000;

	pg_assert_same(array($now - 604800 - 300, $now), pg_ebay_order_window(0, $now), 'a first pull reads a week');
	pg_assert_same(array($now - 3600 - 300, $now), pg_ebay_order_window($now - 3600, $now), 'five minutes of overlap');

	$old = pg_ebay_order_window($now - 200 * 86400, $now);
	pg_assert_same($now - 90 * 86400 + 3600, $old[0], 'never older than the ninety days the feed keeps');
	pg_assert_same($old[0] + 604800, $old[1], 'a backlog is read a week a pass');
	pg_assert_same(array($now - 30 * 86400 - 300, $now - 23 * 86400), pg_ebay_order_window($now - 30 * 86400, $now));
}

function test_ebay_orders_path()
{
	$path = pg_ebay_orders_path(1795000000, 1795003600, 200);

	pg_assert_same('/sell/fulfillment/v1/order?filter=' . rawurlencode('lastmodifieddate:[' . gmdate('Y-m-d\TH:i:s', 1795000000) . '.000Z..'
		. gmdate('Y-m-d\TH:i:s', 1795003600) . '.000Z]') . '&limit=200&offset=200', $path);
}

function test_ebay_orders_package_of_a_paid_order()
{
	$data = ebay_orders_test_fixture('orders.json');
	$package = pg_ebay_order_package($data['orders'][0]);

	pg_assert_same('12-11111-22222', $package['remote_order_id']);
	pg_assert_same('12-11111-22222', $package['remote_package_id']);
	pg_assert_same('NOT_STARTED', $package['remote_status']);
	pg_assert_same(pg_ebay_seconds('2026-11-26T09:15:00.000Z'), $package['placed_at'], 'the modification time, the feed\'s scale');
	pg_assert_same('EUR', $package['currency'], 'the order\'s own currency');
	pg_assert_same(499, $package['shipping_cost']);

	pg_assert_same('Anna Maria', $package['customer']['first_name']);
	pg_assert_same('Schmidt', $package['customer']['last_name']);
	pg_assert_same('a1b2c3d4e5@members.ebay.com', $package['customer']['email']);
	pg_assert_same('+49 30 1234567', $package['customer']['phone']);

	pg_assert_same('Hauptstr. 1', $package['shipping_address']['address_1']);
	pg_assert_same('Hinterhaus', $package['shipping_address']['address_2']);
	pg_assert_same('Berlin', $package['shipping_address']['city']);
	pg_assert_same('BE', $package['shipping_address']['state']);
	pg_assert_same('10115', $package['shipping_address']['zip']);
	pg_assert_same('DE', $package['shipping_address']['country']);
	pg_assert_same($package['shipping_address'], $package['billing'], 'eBay gives no billing address; the delivery one stands in');

	pg_assert_same(1, count($package['lines']));
	$line = $package['lines'][0];
	pg_assert_same('10000000001001', $line['remote_line_id']);
	pg_assert_same('CHAIR-RED', $line['code'], 'the SKU the listing was sent with, the product\'s name here');
	pg_assert_same(2, $line['quantity']);
	pg_assert_same(3990, $line['price'], 'per unit');
	pg_assert_same(0, $line['tax']);
	pg_assert_same(500, $line['discount_total']);
	pg_assert_same(250, $line['discount']);

	$totals = mp_order_totals($package['lines'], $package['shipping_cost']);
	pg_assert_same(7979, $totals['total'], 'the total eBay gives');
}

function test_ebay_orders_unpaid_order_is_not_a_package()
{
	$data = ebay_orders_test_fixture('orders.json');

	pg_assert_same(null, pg_ebay_order_package($data['orders'][1]), 'it comes back once paid: paying changes it');
	pg_assert_same(null, pg_ebay_order_package(array('lineItems' => array())), 'no order id');
}

function test_ebay_orders_status_and_stage()
{
	$data = ebay_orders_test_fixture('orders.json');

	pg_assert_same('CANCELED', pg_ebay_order_status($data['orders'][2]));
	pg_assert_same('cancelled', mp_ebay_order_stage('CANCELED'));
	pg_assert_same('open', mp_ebay_order_stage('NOT_STARTED'));
	pg_assert_same('open', mp_ebay_order_stage('IN_PROGRESS'), 'part of it shipped');
	pg_assert_same('shipped', mp_ebay_order_stage('FULFILLED'));
	pg_assert_same('open', mp_ebay_order_stage(''));

	// A cancellation asked for but not granted leaves the order open.
	pg_assert_same('NOT_STARTED', pg_ebay_order_status(array('orderFulfillmentStatus' => 'NOT_STARTED',
		'cancelStatus' => array('cancelState' => 'IN_PROGRESS'))));
}

function test_ebay_orders_pull_reads_one_page()
{
	$calls = array();
	$now = pg_ebay_seconds('2026-11-27T10:00:00.000Z');
	$call = ebay_orders_test_call(array(array(200, ebay_orders_test_fixture('orders.json'))), $calls);

	$result = mp_ebay_pull_orders(ebay_orders_test_account(), $now - 3600, $call, $now);

	pg_assert_true($result['ok']);
	pg_assert_true($result['data']['complete']);
	pg_assert_same($now, $result['data']['read_to']);
	pg_assert_same(1, count($calls), 'three orders of three: no second page');
	pg_assert_same(0, strpos($calls[0]['call'], 'GET /sell/fulfillment/v1/order?filter='));

	$ids = array_column($result['data']['packages'], 'remote_package_id');
	pg_assert_same(array('12-11111-22222', '12-55555-66666'), $ids, 'the unpaid order is left for later');
}

function test_ebay_orders_pull_walks_pages_and_keeps_what_it_read_on_a_fault()
{
	$calls = array();
	$now = pg_ebay_seconds('2026-11-27T10:00:00.000Z');
	$page = ebay_orders_test_fixture('orders.json');
	$page['total'] = 450;
	$page['orders'] = array_slice($page['orders'], 0, 1);

	$call = ebay_orders_test_call(array(array(200, $page), array(503, ebay_orders_test_fixture('error-system.json'))), $calls);

	$result = mp_ebay_pull_orders(ebay_orders_test_account(), $now - 3600, $call, $now);

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
	pg_assert_false($result['data']['complete'], 'the cursor stays');
	pg_assert_same(1, count($result['data']['packages']), 'the first page is still imported');
	pg_assert_same(2, count($calls));
	pg_assert_true(strpos($calls[1]['call'], '&offset=200') !== false);
}

function test_ebay_orders_pull_without_connection()
{
	$result = mp_ebay_pull_orders(ebay_orders_test_account(), 0, false);

	pg_assert_false($result['ok']);
}

function test_ebay_orders_carrier_code()
{
	pg_assert_same('DHL', pg_ebay_carrier_code('dhl', 'DHL'));
	pg_assert_same('DeutschePost', pg_ebay_carrier_code('deutschepost', 'Deutsche Post'));
	pg_assert_same('RoyalMail', pg_ebay_carrier_code('royalmail', 'Royal Mail'));
	pg_assert_same('FedEx', pg_ebay_carrier_code('fedex', 'FedEx'));
	pg_assert_same('Hermes', pg_ebay_carrier_code('', 'Hermes'), 'a carrier typed on the row is sent as typed');
	pg_assert_same('Yurtiçi Kargo', pg_ebay_carrier_code('yurtici', 'Yurtiçi Kargo'));
	pg_assert_same('', pg_ebay_carrier_code('', ''));
}

function test_ebay_orders_fulfillment_body()
{
	$package = array('remote_package_id' => '12-11111-22222', 'lines' => array(
		array('remote_line_id' => '10000000001001', 'quantity' => 2),
		array('remote_line_id' => '', 'quantity' => 1),
	));

	$body = pg_ebay_fulfillment_body($package, ' 00340434161234567890 ', 'DHL', 1795000000);

	pg_assert_same(array(array('lineItemId' => '10000000001001', 'quantity' => 2)), $body['lineItems']);
	pg_assert_same('00340434161234567890', $body['trackingNumber']);
	pg_assert_same('DHL', $body['shippingCarrierCode']);
	pg_assert_same(gmdate('Y-m-d\TH:i:s', 1795000000) . '.000Z', $body['shippedDate']);

	pg_assert_same(null, pg_ebay_fulfillment_body($package, '', 'DHL', 1795000000), 'no number');
	pg_assert_same(null, pg_ebay_fulfillment_body($package, '123', '', 1795000000), 'no carrier');
	pg_assert_same(null, pg_ebay_fulfillment_body(array('remote_package_id' => 'x', 'lines' => array()), '123', 'DHL', 1795000000), 'no line');
}

function test_ebay_orders_ship_package()
{
	$parcel = array('number' => '00340434161234567890', 'carrier' => 'dhl', 'carrier_name' => 'DHL', 'url' => 'https://example.com/t');
	$package = array('remote_package_id' => '12-11111-22222', 'remote_status' => 'NOT_STARTED',
		'lines' => array(array('remote_line_id' => '10000000001001', 'quantity' => 2)), 'parcel' => $parcel);

	$calls = array();
	$result = mp_ebay_ship_package(ebay_orders_test_account(), $package, $parcel['url'],
		ebay_orders_test_call(array(array(201, array())), $calls), 1795000000);

	pg_assert_true($result['ok']);
	pg_assert_same('FULFILLED', $result['data']['remote_status']);
	pg_assert_same('POST /sell/fulfillment/v1/order/12-11111-22222/shipping_fulfillment', $calls[0]['call']);
	pg_assert_same('DHL', $calls[0]['body']['shippingCarrierCode']);

	// eBay's refusal is written on the package; a fault waits for the next pass.
	$calls = array();
	$refused = mp_ebay_ship_package(ebay_orders_test_account(), $package, $parcel['url'],
		ebay_orders_test_call(array(array(400, ebay_orders_test_fixture('fulfillment-error.json'))), $calls), 1795000000);
	pg_assert_false($refused['ok']);
	pg_assert_false($refused['retry']);
	pg_assert_true(strpos($refused['error'], 'Invalid shipping carrier code.') !== false);

	$calls = array();
	$later = mp_ebay_ship_package(ebay_orders_test_account(), $package, $parcel['url'],
		ebay_orders_test_call(array(array(503, array())), $calls), 1795000000);
	pg_assert_true($later['retry']);

	// No carrier, no call.
	$calls = array();
	$none = mp_ebay_ship_package(ebay_orders_test_account(), array('parcel' => array('number' => '1', 'carrier' => '', 'carrier_name' => '')) + $package, $parcel['url'],
		ebay_orders_test_call(array(), $calls), 1795000000);
	pg_assert_false($none['ok']);
	pg_assert_same(0, count($calls));
}

function test_ebay_orders_ship_statuses()
{
	pg_assert_same(array('NOT_STARTED', 'IN_PROGRESS'), mp_ship_open_statuses('ebay'));
	pg_assert_same(array('Created', 'Picking', 'Invoiced'), mp_ship_open_statuses('trendyol'));
}

function test_ebay_orders_relay_address_matches_no_customer()
{
	pg_assert_false(mp_order_email_is_personal('a1b2c3d4e5@members.ebay.com'));
	pg_assert_true(mp_order_email_is_personal('anna@example.de'));
}
