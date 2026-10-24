<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order side of the Trendyol marketplace connector
 * (includes/api/outbound/connectors/trendyol.php): how an order package is
 * read into the importer's shape and what its money adds up to, what each
 * package status means here, how the order feed is paged and how far a pull
 * lets the cursor move, and the Picking update that accepts a package.
 *
 * Trendyol is never called. The connector's functions take a transport with
 * the signature of api_http_request(); the ones here answer from the saved
 * responses in tests/fixtures/trendyol/ (built in the shape of Trendyol's V2
 * order documentation, October 2026, not recorded from a live account) or from
 * a feed built in memory.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The connector files sit behind init.php's gate and only declare functions
// behind it, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function trendyol_orders_test_account($credentials = array())
{
	return array(
		'id'       => 7,
		'provider' => 'trendyol',
		'credentials' => array_merge(array(
			'seller_id'   => '123456',
			'api_key'     => 'key-abc',
			'api_secret'  => 'secret-xyz',
			'environment' => 'production',
		), $credentials),
	);
}

function trendyol_orders_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/' . $name);
}

// The entries of a saved order page, decoded.
function trendyol_orders_test_entries($name)
{
	$page = json_decode(trendyol_orders_test_fixture($name), true);

	return $page['content'];
}

// A transport that answers every call with $status and $body and writes each
// call it was given into $calls.
function trendyol_orders_test_fixed_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

// $count packages, the first modified at $first (unix seconds) and each next
// one $step seconds later, in the shape of their v2/orders entries.
function trendyol_orders_test_packages($count, $first, $step, $prefix = 1000000)
{
	$packages = array();

	for ($i = 1; $i <= $count; $i++) {
		$packages[] = array(
			'shipmentPackageId' => $prefix + $i,
			'orderNumber'       => (string) (2000000 + $i),
			'status'            => 'Created',
			'lastModifiedDate'  => ($first + ($i - 1) * $step) * 1000,
			'lines'             => array(),
		);
	}

	return $packages;
}

// A transport that answers the v2/orders feed out of $feed the way Trendyol
// does: only the packages modified inside startDate..endDate, in ascending
// order of modification, a page of the size asked for at a time. Every URL it
// is asked for is written into $calls. Request $fail_call (counted from 0),
// when given, answers 500.
function trendyol_orders_test_feed_transport($feed, &$calls, $fail_call = -1)
{
	return function ($method, $url, $options) use ($feed, &$calls, $fail_call) {

		$calls[] = $url;

		$query = array();
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

		if (count($calls) - 1 === $fail_call) {
			return array('ok' => false, 'status' => 500, 'body' => '{"errors":[{"message":"down"}]}',
				'error' => '', 'duration_ms' => 1);
		}

		$inside = array();

		foreach ($feed as $package) {
			if ($package['lastModifiedDate'] >= (float) $query['startDate']
				&& $package['lastModifiedDate'] <= (float) $query['endDate']) {
				$inside[] = $package;
			}
		}

		usort($inside, function ($a, $b) {
			return ($a['lastModifiedDate'] < $b['lastModifiedDate']) ? -1
				: (($a['lastModifiedDate'] > $b['lastModifiedDate']) ? 1 : 0);
		});

		$size = (int) $query['size'];

		$content = array_slice($inside, (int) $query['page'] * $size, $size);

		return array('ok' => true, 'status' => 200,
			'body' => json_encode(array('totalElements' => count($inside), 'page' => (int) $query['page'],
				'size' => $size, 'content' => $content)),
			'error' => '', 'duration_ms' => 1);
	};
}

function trendyol_orders_test_ids($packages)
{
	$ids = array();

	foreach ($packages as $package) {
		$ids[] = $package['remote_package_id'];
	}

	return $ids;
}

function trendyol_orders_test_query($url)
{
	$query = array();
	parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

	return $query;
}

/* ------------------------------------------------------------ the package */

function test_marketplace_trendyol_orders_package_has_the_importers_shape()
{
	$entries = trendyol_orders_test_entries('v2-orders-page0.json');

	$package = mp_trendyol_package($entries[0]);

	pg_assert_same('10654411111', $package['remote_order_id']);
	pg_assert_same('3330111111', $package['remote_package_id'], 'the package id is a string');
	pg_assert_same('Created', $package['remote_status']);
	pg_assert_same(1792700000, $package['placed_at'], 'the modification time in seconds, not the order date');
	pg_assert_same('TRY', $package['currency']);
	pg_assert_same(0, $package['shipping_cost']);

	pg_assert_same('Ayşe', $package['customer']['first_name']);
	pg_assert_same('Yılmaz', $package['customer']['last_name']);
	pg_assert_same('pf+k3j9x2@trendyolmail.com', $package['customer']['email']);
	pg_assert_same('5551112233', $package['customer']['phone'], 'the delivery phone first');
	pg_assert_same('', $package['customer']['company'], 'no company on an order that is not corporate');

	pg_assert_same(array('number' => '7280027504111111', 'carrier' => 'Trendyol Express',
		'link' => 'https://tracking.trendyol.com/?id=111111111-1111-1111-1111-11111111'), $package['tracking'],
		'the tracking number, a JSON number, as text');
}

function test_marketplace_trendyol_orders_package_addresses()
{
	$entries = trendyol_orders_test_entries('v2-orders-page0.json');

	$package = mp_trendyol_package($entries[0]);

	$shipping = $package['shipping_address'];

	pg_assert_same('Ayşe Yılmaz', $shipping['name']);
	pg_assert_same('Moda Cad. No: 12 D: 4', $shipping['address_1']);
	pg_assert_same('Caferağa Mahallesi', $shipping['address_2'], 'a copy of the first line is replaced by the neighbourhood');
	pg_assert_same('İstanbul', $shipping['city']);
	pg_assert_same('Kadıköy', $shipping['state'], 'the district');
	pg_assert_same('34710', $shipping['zip']);
	pg_assert_same('TR', $shipping['country']);
	pg_assert_same('5551112233', $shipping['phone']);

	$billing = $package['billing'];

	pg_assert_same('Bağdat Cad. No: 200', $billing['address_1']);
	pg_assert_same('', $billing['address_2'], 'no second line and no neighbourhood');
	pg_assert_same('Maltepe', $billing['state']);
	pg_assert_same('34840', $billing['zip']);

	// Without a first line, the full address stands in; without a full name,
	// the first and last names.
	$address = mp_trendyol_address(array('shipmentAddress' => array(
		'firstName' => 'Ali', 'lastName' => 'Kaya', 'fullAddress' => 'Tek satır adres', 'address2' => 'Kat 2')), 'shipmentAddress');

	pg_assert_same('Ali Kaya', $address['name']);
	pg_assert_same('Tek satır adres', $address['address_1']);
	pg_assert_same('Kat 2', $address['address_2']);

	pg_assert_same(array(), mp_trendyol_address(array(), 'shipmentAddress'));
}

function test_marketplace_trendyol_orders_package_lines_carry_barcode_and_stock_code()
{
	$entries = trendyol_orders_test_entries('v2-orders-page0.json');

	$lines = mp_trendyol_package($entries[0])['lines'];

	pg_assert_same(2, count($lines));

	pg_assert_same('4765111111', $lines[0]['remote_line_id']);
	pg_assert_same('8680000000011', $lines[0]['code'], 'the barcode is the provider key');
	pg_assert_same('TSHIRT-RED-M', $lines[0]['sku'], 'the stock code the listing was sent with');
	pg_assert_same('Pamuklu Tişört - Kırmızı - M', $lines[0]['name']);
	pg_assert_same(3, $lines[0]['quantity']);
	pg_assert_same('Created', $lines[0]['status']);

	pg_assert_same('4765111112', $lines[1]['remote_line_id']);
	pg_assert_same('8680000000028', $lines[1]['code']);
	pg_assert_same('MUG-01', $lines[1]['sku']);
}

// 25.00 of seller discount over three units is 8.34 + 8.33 + 8.33: their
// average per unit (8.33) times three would lose a kuruş. The 5.00 a unit that
// Trendyol pays is not this order's discount.
function test_marketplace_trendyol_orders_package_money_counts_only_the_sellers_discount()
{
	$entries = trendyol_orders_test_entries('v2-orders-page0.json');

	$lines = mp_trendyol_package($entries[0])['lines'];

	pg_assert_same(19999, $lines[0]['price'], 'the gross unit price in minor units');
	pg_assert_same(0, $lines[0]['tax'], 'VAT is inside their price');
	pg_assert_same(2500, $lines[0]['discount_total'], 'summed from discountDetails, without Trendyol\'s share');
	pg_assert_same(833, $lines[0]['discount'], 'the unit discount, rounded');

	pg_assert_same(8990, $lines[1]['price']);
	pg_assert_same(0, $lines[1]['discount_total']);
	pg_assert_same(0, $lines[1]['discount']);
}

// What the importer adds up from the lines is what Trendyol says the package
// is worth to the seller: its gross amount less the seller's discount.
function test_marketplace_trendyol_orders_package_money_matches_their_package_totals()
{
	$entries = trendyol_orders_test_entries('v2-orders-page0.json');

	$package = mp_trendyol_package($entries[0]);

	$gross = 0;
	$discount = 0;

	foreach ($package['lines'] as $line) {
		$gross += $line['price'] * $line['quantity'];
		$discount += $line['discount_total'];
	}

	$expected = (int) round(($entries[0]['packageGrossAmount'] - $entries[0]['packageSellerDiscount']) * 100);

	pg_assert_same(66487, $expected, 'the fixture: 689.87 - 25.00');
	pg_assert_same($expected, $gross - $discount);

	$totals = mp_order_totals($package['lines']);

	pg_assert_same(68987, $totals['subtotal']);
	pg_assert_same(2500, $totals['discount']);
	pg_assert_same(0, $totals['tax']);
	pg_assert_same($expected, $totals['total']);
	pg_assert_true($totals['total'] > (int) round($entries[0]['packageTotalPrice'] * 100),
		'more than the buyer paid: Trendyol\'s discount is theirs');
}

function test_marketplace_trendyol_orders_package_without_discount_details_uses_the_line_average()
{
	$package = mp_trendyol_package(array(
		'shipmentPackageId' => 1,
		'status'            => 'Created',
		'lines'             => array(array(
			'lineId' => 9, 'barcode' => 'B1', 'quantity' => 3,
			'lineGrossAmount' => 199.99, 'lineSellerDiscount' => 8.33, 'lineTyDiscount' => 5.00,
		)),
	));

	pg_assert_same(2499, $package['lines'][0]['discount_total'], '8.33 x 3; the Trendyol discount is not added');
	pg_assert_same(833, $package['lines'][0]['discount']);

	$package = mp_trendyol_package(array(
		'shipmentPackageId' => 1,
		'status'            => 'Created',
		'lines'             => array(array('lineId' => 9, 'barcode' => 'B1', 'quantity' => 2, 'lineGrossAmount' => 50,
			'discountDetails' => array())),
	));

	pg_assert_same(0, $package['lines'][0]['discount_total'], 'no discount at all');
	pg_assert_same(5000, $package['lines'][0]['price']);
}

// The names Trendyol replaced, still read where the new ones are missing.
function test_marketplace_trendyol_orders_package_reads_the_older_field_names()
{
	$package = mp_trendyol_package(array(
		'id'                    => 555,
		'shipmentPackageStatus' => 'Picking',
		'lastModifiedDate'      => 1792700000000,
		'lines'                 => array(array(
			'id' => 77, 'barcode' => 'B1', 'merchantSku' => ' SKU-1 ', 'quantity' => 2,
			'amount' => 10.50, 'discount' => 1.25,
		), array(
			'lineId' => 78, 'barcode' => 'B2', 'quantity' => 2, 'lineGrossAmount' => 20,
			'discountDetails' => array(array('lineItemDiscount' => 0.75), array('lineItemDiscount' => 0.50, 'lineItemTyDiscount' => 9)),
		)),
	));

	pg_assert_same('555', $package['remote_package_id']);
	pg_assert_same('555', $package['remote_order_id'], 'no order number: the package id');
	pg_assert_same('Picking', $package['remote_status']);
	pg_assert_same('77', $package['lines'][0]['remote_line_id']);
	pg_assert_same('SKU-1', $package['lines'][0]['sku']);
	pg_assert_same(1050, $package['lines'][0]['price']);
	pg_assert_same(250, $package['lines'][0]['discount_total']);
	pg_assert_same(125, $package['lines'][1]['discount_total']);
}

function test_marketplace_trendyol_orders_package_company_only_on_a_corporate_order()
{
	$entries = trendyol_orders_test_entries('v2-orders-page0.json');

	$entry = $entries[0];
	$entry['commercial'] = true;

	pg_assert_same('Yılmaz Tekstil Ltd. Şti.', mp_trendyol_package($entry)['customer']['company']);
}

function test_marketplace_trendyol_orders_package_name_falls_back_to_the_delivery_name()
{
	$package = mp_trendyol_package(array(
		'shipmentPackageId' => 1,
		'status'            => 'Created',
		'shipmentAddress'   => array('fullName' => 'Zeynep Nur Aydın', 'address1' => 'Sok. 1'),
		'invoiceAddress'    => array('fullName' => 'Zeynep Nur Aydın', 'phone' => '2120000000'),
		'lines'             => array(),
	));

	pg_assert_same('Zeynep Nur', $package['customer']['first_name']);
	pg_assert_same('Aydın', $package['customer']['last_name']);
	pg_assert_same('2120000000', $package['customer']['phone'], 'the invoice phone when the delivery has none');
	pg_assert_same('', $package['tracking']['number']);
	pg_assert_same(0, $package['placed_at']);
}

// Payment not confirmed yet: they come back as Created once it is.
function test_marketplace_trendyol_orders_awaiting_and_verified_are_not_imported()
{
	$entries = trendyol_orders_test_entries('v2-orders-page0.json');

	pg_assert_same('Awaiting', $entries[1]['status'], 'the fixture');
	pg_assert_same(null, mp_trendyol_package($entries[1]));

	$verified = $entries[1];
	$verified['status'] = 'Verified';
	$verified['shipmentPackageStatus'] = 'Verified';

	pg_assert_same(null, mp_trendyol_package($verified));
	pg_assert_same(null, mp_trendyol_package(array('status' => 'Created', 'lines' => array())), 'no package id');
	pg_assert_same(null, mp_trendyol_package('nope'));
}

// A partial cancellation: the old package turns Cancelled and what is left
// comes back as a new package of the same order.
function test_marketplace_trendyol_orders_partial_cancel_is_a_cancelled_package_and_a_new_one()
{
	$entries = trendyol_orders_test_entries('v2-orders-cancelled.json');

	$old = mp_trendyol_package($entries[0]);
	$new = mp_trendyol_package($entries[1]);

	pg_assert_same('3330111111', $old['remote_package_id']);
	pg_assert_same('cancelled', mp_trendyol_order_stage($old['remote_status']));

	pg_assert_same('3330111133', $new['remote_package_id']);
	pg_assert_same($old['remote_order_id'], $new['remote_order_id'], 'the same order number');
	pg_assert_same('open', mp_trendyol_order_stage($new['remote_status']));
	pg_assert_same('7280027504111133', $new['tracking']['number'], 'a new tracking number');
	pg_assert_same('4765111134', $new['lines'][0]['remote_line_id']);
}

/* ---------------------------------------------------------------- stages */

function test_marketplace_trendyol_orders_stage_narrows_their_statuses()
{
	$expected = array(
		'Created'           => 'open',
		'Picking'           => 'open',
		'Invoiced'          => 'open',
		'Awaiting'          => 'open',
		'Verified'          => 'open',
		''                  => 'open',
		'Shipped'           => 'shipped',
		'AtCollectionPoint' => 'shipped',
		'UnDelivered'       => 'shipped',
		'Returned'          => 'shipped',
		'Delivered'         => 'delivered',
		'Cancelled'         => 'cancelled',
		'UnSupplied'        => 'cancelled',
		'UnPacked'          => 'cancelled',
	);

	foreach ($expected as $status => $stage) {
		pg_assert_same($stage, mp_trendyol_order_stage($status), $status);
	}
}

/* ------------------------------------------------------------ order feed */

function test_marketplace_trendyol_orders_pull_reads_a_short_page_to_the_end()
{
	$calls = array();
	$before = time();

	$result = mp_trendyol_pull_orders(trendyol_orders_test_account(), 1792690000,
		trendyol_orders_test_fixed_transport(200, trendyol_orders_test_fixture('v2-orders-page0.json'), $calls));

	$after = time();

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same(1, count($calls), 'one short page');
	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same(array('3330111111'), trendyol_orders_test_ids($result['data']['packages']), 'the Awaiting package is left out');
	pg_assert_true($result['data']['complete']);
	pg_assert_true($result['data']['read_to'] >= $before && $result['data']['read_to'] <= $after,
		'a feed read to its end moves the cursor to the end of the window');
	pg_assert_false(!empty($result['data']['more']));

	$url = $calls[0]['url'];

	pg_assert_same(0, strpos($url, 'https://apigw.trendyol.com/integration/order/sellers/123456/v2/orders?'), $url);

	$query = trendyol_orders_test_query($url);

	pg_assert_same('PackageLastModifiedDate', $query['orderByField']);
	pg_assert_same('ASC', $query['orderByDirection']);
	pg_assert_same('0', $query['page']);
	pg_assert_same('200', $query['size']);
	pg_assert_false(isset($query['status']), 'no status filter');
	pg_assert_same((string) ((1792690000 - 300) * 1000), $query['startDate'], 'five minutes before the cursor, in milliseconds');
	pg_assert_true((int) $query['endDate'] >= $before * 1000 && (int) $query['endDate'] <= $after * 1000, 'the window ends now, not later');
	pg_assert_same(0, ((int) $query['endDate']) % 1000);
}

// Their window is two weeks at most: an older cursor starts at the oldest
// moment the window still reaches, an hour inside it.
function test_marketplace_trendyol_orders_pull_window_is_at_most_two_weeks()
{
	$calls = array();
	$before = time();

	mp_trendyol_pull_orders(trendyol_orders_test_account(), $before - 30 * 86400,
		trendyol_orders_test_fixed_transport(200, '{"content":[]}', $calls));

	$after = time();

	$query = trendyol_orders_test_query($calls[0]['url']);

	pg_assert_true((int) $query['startDate'] >= ($before - 14 * 86400 + 3600) * 1000
		&& (int) $query['startDate'] <= ($after - 14 * 86400 + 3600) * 1000, 'capped: ' . $query['startDate']);
	pg_assert_true((int) $query['endDate'] - (int) $query['startDate'] < 14 * 86400 * 1000, 'shorter than two weeks');

	// A first pull reads a week.
	$calls = array();
	$before = time();

	mp_trendyol_pull_orders(trendyol_orders_test_account(), 0, trendyol_orders_test_fixed_transport(200, '{"content":[]}', $calls));

	$after = time();

	$query = trendyol_orders_test_query($calls[0]['url']);

	pg_assert_true((int) $query['startDate'] >= ($before - 604800 - 300) * 1000
		&& (int) $query['startDate'] <= ($after - 604800 - 300) * 1000, 'a week: ' . $query['startDate']);
}

// After a full page the window starts again just before the last moment read,
// from page 0; the last package comes back and is not handed over twice.
function test_marketplace_trendyol_orders_pull_asks_again_from_the_last_moment_after_a_full_page()
{
	$since = time() - 3600;
	$calls = array();

	$result = mp_trendyol_pull_orders(trendyol_orders_test_account(), $since,
		trendyol_orders_test_feed_transport(trendyol_orders_test_packages(200, $since + 10, 1), $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(2, count($calls), 'a full page, then one that holds only the last package again');
	pg_assert_same('0', trendyol_orders_test_query($calls[1])['page']);
	pg_assert_same((string) (($since + 10 + 199) * 1000 - 1), trendyol_orders_test_query($calls[1])['startDate'],
		'a millisecond before the last package read');
	pg_assert_same(200, count($result['data']['packages']));
	pg_assert_true($result['data']['complete']);
	pg_assert_false(!empty($result['data']['more']));
}

function test_marketplace_trendyol_orders_pull_at_the_page_cap_moves_the_cursor_only_as_far_as_it_read()
{
	$since = time() - 86400;
	$feed = trendyol_orders_test_packages(4500, $since + 60, 10);
	$calls = array();

	$result = mp_trendyol_pull_orders(trendyol_orders_test_account(), $since, trendyol_orders_test_feed_transport($feed, $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(20, count($calls), 'twenty pages, then it stops');
	pg_assert_same(200 + 19 * 199, count($result['data']['packages']),
		'every page after the first starts with the last package of the one before');
	pg_assert_true($result['data']['complete'], 'the cursor moves');
	pg_assert_true(!empty($result['data']['more']));
	pg_assert_same($since + 60 + 3980 * 10, $result['data']['read_to'],
		'to the modification time of the last package read, not to now');

	// The next pass starts there and reads the rest.
	$calls = array();

	$next = mp_trendyol_pull_orders(trendyol_orders_test_account(), $result['data']['read_to'],
		trendyol_orders_test_feed_transport($feed, $calls));

	pg_assert_true($next['ok']);
	pg_assert_true($next['data']['complete']);
	pg_assert_false(!empty($next['data']['more']), 'the second pass reaches the end of the feed');

	$seen = array_unique(array_merge(trendyol_orders_test_ids($result['data']['packages']),
		trendyol_orders_test_ids($next['data']['packages'])));

	pg_assert_same(4500, count($seen), 'every package of the window is read by one pass or the other');
}

// A page that ends in packages awaiting payment has still been read: the
// cursor moves past them although none of them is handed to the importer.
function test_marketplace_trendyol_orders_pull_cursor_counts_the_entries_it_skips()
{
	$since = time() - 86400;
	$feed = trendyol_orders_test_packages(4100, $since + 60, 10);

	for ($i = 3900; $i < 4000; $i++) {
		$feed[$i]['status'] = 'Awaiting';
	}

	$calls = array();

	$result = mp_trendyol_pull_orders(trendyol_orders_test_account(), $since, trendyol_orders_test_feed_transport($feed, $calls));

	pg_assert_same(20, count($calls));
	pg_assert_same(3981 - 81, count($result['data']['packages']), 'the awaiting ones read are not imported');
	pg_assert_same($since + 60 + 3980 * 10, $result['data']['read_to'], 'the last entry read, which was skipped');
}

function test_marketplace_trendyol_orders_pull_reads_past_the_cap_when_the_cap_would_not_move_the_cursor()
{
	$since = time() - 86400;

	// 4,100 packages modified inside the five-minute overlap before $since,
	// then newer ones. Twenty pages would end inside the overlap and hand the
	// next pass the same start.
	$feed = array_merge(
		trendyol_orders_test_packages(4100, $since - 100, 0, 1000000),
		trendyol_orders_test_packages(500, $since + 1000, 1, 2000000));
	$calls = array();

	$result = mp_trendyol_pull_orders(trendyol_orders_test_account(), $since, trendyol_orders_test_feed_transport($feed, $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(21, count($calls), 'one page past the cap reaches a newer package');
	pg_assert_same(4200, count($result['data']['packages']));
	pg_assert_true($result['data']['complete']);
	pg_assert_same($since + 1000 + 99, $result['data']['read_to']);
}

// Fifty pages of two hundred is the ten thousand packages they answer for one
// window; page 49 is the last there is.
function test_marketplace_trendyol_orders_pull_keeps_the_cursor_when_even_the_ceiling_does_not_move_it()
{
	$since = time() - 86400;
	$calls = array();

	$result = mp_trendyol_pull_orders(trendyol_orders_test_account(), $since,
		trendyol_orders_test_feed_transport(trendyol_orders_test_packages(10100, $since - 100, 0), $calls));

	pg_assert_true($result['ok'], 'what was read is still handed over for import');
	pg_assert_same(50, count($calls), 'fifty pages at most');
	pg_assert_same('49', trendyol_orders_test_query($calls[49])['page']);
	pg_assert_same(10000, count($result['data']['packages']));
	pg_assert_false($result['data']['complete'], 'the cursor stays rather than jumping over the rest');
	pg_assert_false(isset($result['data']['read_to']));
}

function test_marketplace_trendyol_orders_pull_failure_hands_over_what_was_read_and_keeps_the_cursor()
{
	$since = time() - 86400;
	$calls = array();

	$result = mp_trendyol_pull_orders(trendyol_orders_test_account(), $since,
		trendyol_orders_test_feed_transport(trendyol_orders_test_packages(1000, $since + 60, 1), $calls, 2));

	pg_assert_false($result['ok']);
	pg_assert_true(!empty($result['retry']), 'a 500 is worth trying again');
	pg_assert_same(200 + 199, count($result['data']['packages']));
	pg_assert_false($result['data']['complete']);
}

// A package read on the first page changes before the second page is asked
// for. Asked by page number inside the same window, every later package would
// move up one place and the one that moved onto the first page would never be
// read; the cursor would then pass it for good.
function test_marketplace_trendyol_orders_pull_loses_no_package_when_one_already_read_changes()
{
	$since = time() - 3600;
	$feed = trendyol_orders_test_packages(300, $since + 10, 10);
	$calls = array();
	$requests = 0;

	$transport = function ($method, $url, $options) use (&$feed, &$calls, &$requests) {

		$requests++;

		if ($requests === 2) {
			$feed[5]['lastModifiedDate'] = (time() + 60) * 1000;
		}

		$answer = trendyol_orders_test_feed_transport($feed, $calls);

		return $answer($method, $url, $options);
	};

	$result = mp_trendyol_pull_orders(trendyol_orders_test_account(), $since, $transport);

	pg_assert_true($result['ok']);
	pg_assert_same(300, count(array_unique(trendyol_orders_test_ids($result['data']['packages']))),
		'every package of the window is read');
}

function test_marketplace_trendyol_orders_pull_with_bad_credentials_makes_no_call()
{
	$calls = array();

	$result = mp_trendyol_pull_orders(trendyol_orders_test_account(array('seller_id' => 'x')), 0,
		trendyol_orders_test_fixed_transport(200, '{"content":[]}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('seller ID is a number', $result['error']);
	pg_assert_false($result['data']['complete']);
	pg_assert_same(0, count($calls));
}

/* ------------------------------------------------------------ accepting */

function trendyol_orders_test_imported_package()
{
	$entries = trendyol_orders_test_entries('v2-orders-page0.json');

	return mp_trendyol_package($entries[0]);
}

function test_marketplace_trendyol_orders_approve_puts_picking_with_each_lines_quantity()
{
	$calls = array();
	$package = trendyol_orders_test_imported_package();

	$result = mp_trendyol_approve_lines(trendyol_orders_test_account(), array('4765111111', '4765111112'), $package,
		trendyol_orders_test_fixed_transport(200, trendyol_orders_test_fixture('shipment-package-picking.json'), $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same(2, $result['data']['accepted']);

	pg_assert_same(1, count($calls));
	pg_assert_same('PUT', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/order/sellers/123456/shipment-packages/3330111111', $calls[0]['url']);
	pg_assert_same('{"lines":[{"lineId":4765111111,"quantity":3},{"lineId":4765111112,"quantity":1}],"params":{},"status":"Picking"}',
		$calls[0]['options']['body'], 'params is an object, not a list');
	pg_assert_contains('Content-Type: application/json', $calls[0]['options']['headers']);
	pg_assert_contains('User-Agent: 123456 - SelfIntegration', $calls[0]['options']['headers']);
}

function test_marketplace_trendyol_orders_approve_sends_only_the_lines_asked_for()
{
	$calls = array();

	$result = mp_trendyol_approve_lines(trendyol_orders_test_account(), array('4765111112'), trendyol_orders_test_imported_package(),
		trendyol_orders_test_fixed_transport(200, '', $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(1, $result['data']['accepted']);
	pg_assert_same('{"lines":[{"lineId":4765111112,"quantity":1}],"params":{},"status":"Picking"}', $calls[0]['options']['body']);
}

function test_marketplace_trendyol_orders_approve_refuses_before_any_call()
{
	$calls = array();
	$transport = trendyol_orders_test_fixed_transport(200, '', $calls);
	$package = trendyol_orders_test_imported_package();

	$unknown = $package;
	$unknown['remote_package_id'] = '../3330111111';

	$result = mp_trendyol_approve_lines(trendyol_orders_test_account(), array('4765111111'), $unknown, $transport);

	pg_assert_false($result['ok']);
	pg_assert_contains('no package number', $result['error']);

	$result = mp_trendyol_approve_lines(trendyol_orders_test_account(), array('4765111111'), array(), $transport);

	pg_assert_false($result['ok'], 'no package given at all');

	$result = mp_trendyol_approve_lines(trendyol_orders_test_account(), array('999'), $package, $transport);

	pg_assert_false($result['ok']);
	pg_assert_contains('no lines to accept', $result['error']);

	pg_assert_same(0, count($calls), 'nothing was sent');
}

// A line id goes out as a JSON number. Nineteen digits can pass PHP_INT_MAX,
// where (int) saturates and would send another line's id, so ids are held to
// eighteen digits; eighteen go out exactly.
function test_marketplace_trendyol_orders_approve_holds_ids_to_eighteen_digits()
{
	$calls = array();
	$transport = trendyol_orders_test_fixed_transport(200, '', $calls);

	$package = array(
		'remote_package_id' => '3330111111',
		'lines'             => array(
			array('remote_line_id' => '99999999999999999999', 'quantity' => 1),
			array('remote_line_id' => '9999999999999999999', 'quantity' => 1),
		),
	);

	$result = mp_trendyol_approve_lines(trendyol_orders_test_account(), array('99999999999999999999', '9999999999999999999'),
		$package, $transport);

	pg_assert_false($result['ok'], 'twenty and nineteen digits are refused');
	pg_assert_contains('no lines to accept', $result['error']);

	$long_package = array('remote_package_id' => '1234567890123456789', 'lines' => array(array('remote_line_id' => '1', 'quantity' => 1)));

	$result = mp_trendyol_approve_lines(trendyol_orders_test_account(), array('1'), $long_package, $transport);

	pg_assert_false($result['ok']);
	pg_assert_contains('no package number', $result['error'], 'a nineteen-digit package id');

	pg_assert_same(0, count($calls), 'nothing was sent');

	$package = array('remote_package_id' => '123456789012345678',
		'lines' => array(array('remote_line_id' => '999999999999999999', 'quantity' => 2)));

	$result = mp_trendyol_approve_lines(trendyol_orders_test_account(), array('999999999999999999'), $package, $transport);

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('https://apigw.trendyol.com/integration/order/sellers/123456/shipment-packages/123456789012345678', $calls[0]['url']);
	pg_assert_same('{"lines":[{"lineId":999999999999999999,"quantity":2}],"params":{},"status":"Picking"}', $calls[0]['options']['body']);
}

function test_marketplace_trendyol_orders_approve_passes_their_refusal_on()
{
	$calls = array();

	$result = mp_trendyol_approve_lines(trendyol_orders_test_account(), array('4765111111'), trendyol_orders_test_imported_package(),
		trendyol_orders_test_fixed_transport(400, trendyol_orders_test_fixture('error-400.json'), $calls));

	pg_assert_false($result['ok']);
	pg_assert_false($result['retry']);
	pg_assert_same('Kategori bulunamadı', $result['error']);

	$result = mp_trendyol_approve_lines(trendyol_orders_test_account(), array('4765111111'), trendyol_orders_test_imported_package(),
		trendyol_orders_test_fixed_transport(502, '<html>bad gateway</html>', $calls));

	pg_assert_false($result['ok'], 'an error page is not a success');
	pg_assert_true($result['retry']);
}

/* ------------------------------------------------------------- the call */

// Only a call that asks for it reads an empty or non-JSON success as success.
function test_marketplace_trendyol_orders_read_allows_an_empty_success_only_when_asked()
{
	$empty = array('status' => 200, 'body' => '', 'error' => '');

	pg_assert_false(mp_trendyol_read($empty)['ok'], 'the default is unchanged');
	pg_assert_true(mp_trendyol_read($empty, 'production', true)['ok']);
	pg_assert_same(array(), mp_trendyol_read($empty, 'production', true)['data']);
	pg_assert_true(mp_trendyol_read(array('status' => 204, 'body' => 'OK', 'error' => ''), 'production', true)['ok']);
	pg_assert_false(mp_trendyol_read(array('status' => 500, 'body' => '', 'error' => ''), 'production', true)['ok']);
	pg_assert_false(mp_trendyol_read(array('status' => 200, 'body' => '{"errors":[{"message":"x"}]}', 'error' => ''), 'production', true)['ok'],
		'an error envelope is still an error');
}

function test_marketplace_trendyol_orders_call_sends_the_method_it_is_given()
{
	$calls = array();
	$transport = trendyol_orders_test_fixed_transport(200, '{}', $calls);

	mp_trendyol_call(trendyol_orders_test_account(), '/x', 1000, $transport, null, 'DELETE');
	mp_trendyol_call(trendyol_orders_test_account(), '/x', 1000, $transport, array('a' => 1), 'put');
	mp_trendyol_call(trendyol_orders_test_account(), '/x', 1000, $transport, array('a' => 1));
	mp_trendyol_call(trendyol_orders_test_account(), '/x', 1000, $transport);

	pg_assert_same('DELETE', $calls[0]['method']);
	pg_assert_false(isset($calls[0]['options']['body']), 'a DELETE without a body sends none');
	pg_assert_same('PUT', $calls[1]['method']);
	pg_assert_same('{"a":1}', $calls[1]['options']['body']);
	pg_assert_same('POST', $calls[2]['method'], 'a body without a method is a POST, as before');
	pg_assert_same('GET', $calls[3]['method']);
}
