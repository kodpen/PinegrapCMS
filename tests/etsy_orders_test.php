<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Etsy orders and shipment (includes/fn/etsy.php and the
 * connector's pull_orders, order_stage and ship_package): paid receipts as
 * packages in the receipt's currency, a receipt without an address imported
 * with its notice, the pull's window and pages, the parcel reported back.
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

function etsy_orders_test_fixture($name)
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/etsy/' . $name), true);
}

// A call answering in turn with $answers (array(status, fixture or data)),
// writing each request into $calls.
function etsy_orders_test_call($answers, &$calls)
{
	return function ($method, $path, $options = array()) use (&$answers, &$calls) {
		$calls[] = array($method, $path, $options);
		$answer = array_shift($answers);
		$data = is_string($answer[1]) ? etsy_orders_test_fixture($answer[1]) : (array) $answer[1];

		return array('ok' => ($answer[0] >= 200 && $answer[0] < 300), 'status' => $answer[0], 'data' => $data, 'error' => '', 'headers' => array());
	};
}

function etsy_orders_test_account()
{
	return array('id' => 9, 'provider' => 'etsy', 'credentials' => array('shop' => '87654321'));
}

function test_etsy_orders_connector_names_the_order_entries()
{
	$connector = mp_etsy_connector();

	pg_assert_same('mp_etsy_pull_orders', $connector['pull_orders']);
	pg_assert_same('mp_etsy_order_stage', $connector['order_stage']);
	pg_assert_same('mp_etsy_ship_package', $connector['ship_package']);
	pg_assert_true(mp_provider_ships_itself('etsy'));
	pg_assert_same(array('paid', 'partially refunded'), mp_ship_open_statuses('etsy'));
}

function test_etsy_orders_minor_units()
{
	pg_assert_same(2450, pg_etsy_minor(array('amount' => 2450, 'divisor' => 100, 'currency_code' => 'USD')));
	pg_assert_same(1235, pg_etsy_minor(array('amount' => 12345, 'divisor' => 1000)));
	pg_assert_same(1500, pg_etsy_minor(array('amount' => 15, 'divisor' => 1)));
	pg_assert_same(800, pg_etsy_minor(array('amount' => -800, 'divisor' => 100)));
	pg_assert_same(0, pg_etsy_minor(null));
	pg_assert_same(0, pg_etsy_minor(array('amount' => 'x')));
}

function test_etsy_orders_window_and_path()
{
	$now = 1764300000;

	pg_assert_same(array($now - 604800 - 300, $now), pg_etsy_order_window(0, $now));
	pg_assert_same(array($now - 3900, $now), pg_etsy_order_window($now - 3600, $now));
	pg_assert_same(array($now - 3000000 - 300, $now - 3000000 + 604800), pg_etsy_order_window($now - 3000000, $now));
	pg_assert_same(array($now - 300, $now), pg_etsy_order_window($now + 50, $now));

	pg_assert_same('/shops/87654321/receipts?min_last_modified=100&max_last_modified=200&was_paid=true&limit=100&offset=300',
		pg_etsy_receipts_path('87654321', 100, 200, 300));
}

// Lines in the receipt's currency, the receipt's discount shared over them,
// the shipping kept and Etsy's tax left out; the address and the buyer.
function test_etsy_orders_package_of_a_paid_receipt()
{
	$receipts = etsy_orders_test_fixture('receipts.json');
	$package = pg_etsy_receipt_package($receipts['results'][0], 'NO ADDRESS');

	pg_assert_same('3012345678', $package['remote_order_id']);
	pg_assert_same('3012345678', $package['remote_package_id']);
	pg_assert_same('paid', $package['remote_status']);
	pg_assert_same(1764200600, $package['placed_at']);
	pg_assert_same('USD', $package['currency']);
	pg_assert_same(850, $package['shipping_cost']);
	pg_assert_same(2, count($package['lines']));

	pg_assert_same(array('remote_line_id' => '4011111111', 'code' => 'APRON-M', 'sku' => 'APRON-M', 'name' => 'Linen apron, medium',
		'quantity' => 2, 'price' => 2450, 'tax' => 0, 'discount' => 327, 'discount_total' => 653, 'status' => ''), $package['lines'][0]);
	pg_assert_same(147, $package['lines'][1]['discount_total']);
	pg_assert_same(1100, $package['lines'][1]['price']);

	pg_assert_same('Jane Q', $package['customer']['first_name']);
	pg_assert_same('Doe', $package['customer']['last_name']);
	pg_assert_same('buyer@example.com', $package['customer']['email']);
	pg_assert_same('12 Elm Street', $package['shipping_address']['address_1']);
	pg_assert_same('US', $package['shipping_address']['country']);
	pg_assert_same('97201', $package['billing']['zip']);
	pg_assert_true(!isset($package['notice']));
}

// No address from Etsy: imported all the same, with the notice.
function test_etsy_orders_receipt_without_address_carries_the_notice()
{
	$receipts = etsy_orders_test_fixture('receipts.json');
	$package = pg_etsy_receipt_package($receipts['results'][1], 'NO ADDRESS');

	pg_assert_same('EUR', $package['currency']);
	pg_assert_same(array(), $package['shipping_address']);
	pg_assert_same('', $package['billing']['address_1']);
	pg_assert_same('', $package['customer']['first_name']);
	pg_assert_same('NO ADDRESS', $package['notice']);
	pg_assert_same(1, count($package['lines']));

	// Only downloads: nothing to send, so no notice.
	$digital = $receipts['results'][1];
	$digital['transactions'][0]['is_digital'] = true;
	pg_assert_true(!isset(pg_etsy_receipt_package($digital, 'NO ADDRESS')['notice']));
}

function test_etsy_orders_unpaid_or_unnamed_receipt_is_no_package()
{
	pg_assert_same(null, pg_etsy_receipt_package(array('receipt_id' => 1, 'is_paid' => false), ''));
	pg_assert_same(null, pg_etsy_receipt_package(array('status' => 'paid'), ''));
}

function test_etsy_orders_status_and_stage()
{
	pg_assert_same('completed', pg_etsy_receipt_status(etsy_orders_test_fixture('receipt-shipped.json')));
	pg_assert_same('completed', pg_etsy_receipt_status(array('status' => 'Paid', 'is_shipped' => true)));
	pg_assert_same('canceled', pg_etsy_receipt_status(array('status' => 'Canceled')));
	pg_assert_same('fully refunded', pg_etsy_receipt_status(array('status' => 'Fully Refunded')));

	pg_assert_same('cancelled', mp_etsy_order_stage('canceled'));
	pg_assert_same('shipped', mp_etsy_order_stage('completed'));
	pg_assert_same('open', mp_etsy_order_stage('paid'));
	pg_assert_same('open', mp_etsy_order_stage('fully refunded'));
}

function test_etsy_orders_discount_shares()
{
	pg_assert_same(array(653, 147), pg_etsy_discount_shares(array(4900, 1100), 800));
	pg_assert_same(array(1, 1, 1), pg_etsy_discount_shares(array(1, 1, 1), 5));
	pg_assert_same(array(0, 0), pg_etsy_discount_shares(array(100, 200), 0));
	pg_assert_same(array(34, 33, 33), pg_etsy_discount_shares(array(100, 100, 100), 100));
}

function test_etsy_orders_pull_reads_one_page()
{
	$calls = array();
	$now = 1764300000;
	$result = mp_etsy_pull_orders(etsy_orders_test_account(), $now - 3600, etsy_orders_test_call(array(array(200, 'receipts.json')), $calls), $now);

	pg_assert_true($result['ok']);
	pg_assert_true($result['data']['complete']);
	pg_assert_same($now, $result['data']['read_to']);
	pg_assert_same(2, count($result['data']['packages']));
	pg_assert_same(1, count($calls));
	pg_assert_same(array('GET', pg_etsy_receipts_path('87654321', $now - 3900, $now, 0)), array($calls[0][0], $calls[0][1]));
}

// A full page asks for the next; a fault keeps what was read, with the
// cursor kept and the fault retryable.
function test_etsy_orders_pull_walks_pages_and_keeps_what_it_read_on_a_fault()
{
	$receipt = etsy_orders_test_fixture('receipts.json')['results'][0];
	$page = array('count' => 150, 'results' => array());

	for ($i = 0; $i < 100; $i++) {
		$receipt['receipt_id'] = 1000 + $i;
		$page['results'][] = $receipt;
	}

	$calls = array();
	$result = mp_etsy_pull_orders(etsy_orders_test_account(), 0,
		etsy_orders_test_call(array(array(200, $page), array(503, array('error' => 'down'))), $calls), 1764300000);

	pg_assert_true(!$result['ok']);
	pg_assert_true($result['retry']);
	pg_assert_true(!$result['data']['complete']);
	pg_assert_same(100, count($result['data']['packages']));
	pg_assert_same(2, count($calls));
	pg_assert_true(strpos($calls[1][1], '&offset=100') !== false);
}

function test_etsy_orders_pull_without_shop_or_connection()
{
	$calls = array();
	$result = mp_etsy_pull_orders(array('id' => 9, 'provider' => 'etsy', 'credentials' => array()), 0, etsy_orders_test_call(array(), $calls), 1);
	pg_assert_true(!$result['ok']);
	pg_assert_same(0, count($calls));

	$result = mp_etsy_pull_orders(etsy_orders_test_account(), 0, false, 1);
	pg_assert_true(!$result['ok']);
}

function test_etsy_orders_carrier_and_fields()
{
	pg_assert_same('ups', pg_etsy_carrier_name('ups', 'UPS'));
	pg_assert_same('royal-mail', pg_etsy_carrier_name('royalmail', 'Royal Mail'));
	pg_assert_same('Yurtiçi Kargo', pg_etsy_carrier_name('yurtici', 'Yurtiçi Kargo'));
	pg_assert_same('', pg_etsy_carrier_name('', ''));

	pg_assert_same(array('tracking_code' => '1Z9', 'carrier_name' => 'ups'), pg_etsy_shipment_fields(' 1Z9 ', 'ups'));
	pg_assert_same(null, pg_etsy_shipment_fields('', 'ups'));
	pg_assert_same(null, pg_etsy_shipment_fields('1Z9', ''));
}

function test_etsy_orders_ship_package()
{
	$package = array('remote_package_id' => '3012345678', 'lines' => array(),
		'parcel' => array('number' => '1Z999AA10123456784', 'url' => 'https://www.ups.com/track?tracknum=1Z999AA10123456784', 'carrier' => 'ups', 'carrier_name' => 'UPS'));

	$calls = array();
	$result = mp_etsy_ship_package(etsy_orders_test_account(), $package, '', etsy_orders_test_call(array(array(200, 'receipt-shipped.json')), $calls));

	pg_assert_true($result['ok']);
	pg_assert_same('completed', $result['data']['remote_status']);
	pg_assert_same('POST', $calls[0][0]);
	pg_assert_same('/shops/87654321/receipts/3012345678/tracking', $calls[0][1]);
	pg_assert_same('tracking_code=1Z999AA10123456784&carrier_name=ups', $calls[0][2]['body']);
	pg_assert_same(array('Content-Type: application/x-www-form-urlencoded'), $calls[0][2]['headers']);

	// Etsy keeps the call from this application: a refusal, not retried.
	$calls = array();
	$refused = mp_etsy_ship_package(etsy_orders_test_account(), $package, '', etsy_orders_test_call(array(array(403, 'error-shipment.json')), $calls));
	pg_assert_true(!$refused['ok']);
	pg_assert_true(!$refused['retry']);
	pg_assert_true(strpos($refused['error'], 'createReceiptShipment') !== false);

	// No carrier named: no call.
	$calls = array();
	$package['parcel'] = array('number' => '123', 'carrier' => '', 'carrier_name' => '');
	pg_assert_true(!mp_etsy_ship_package(etsy_orders_test_account(), $package, '', etsy_orders_test_call(array(), $calls))['ok']);
	pg_assert_same(0, count($calls));
}
