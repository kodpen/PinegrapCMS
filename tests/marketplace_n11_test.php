<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the n11 marketplace connector
 * (includes/api/outbound/connectors/n11.php): how the order feed is paged and
 * how far a pull lets the cursor move, and the connection test.
 *
 * n11 is never called. The connector's functions take an optional transport
 * with the signature of api_http_request(); the ones here answer from a feed
 * built in memory, in the shape of their shipmentPackages answer, or from the
 * saved category tree in tests/fixtures/n11/ (built in the shape of their
 * /cdn/categories answer, not recorded from a live account).
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/n11.php');

function n11_test_account()
{
	return array(
		'id'          => 3,
		'provider'    => 'n11',
		'credentials' => array('app_key' => 'key-abc', 'app_secret' => 'secret-xyz'),
	);
}

// $count packages, the first modified at $first (unix seconds) and each next
// one $step seconds later, in the shape their feed answers with.
function n11_test_packages($count, $first, $step, $prefix = 'pkg')
{
	$packages = array();

	for ($i = 1; $i <= $count; $i++) {
		$packages[] = array(
			'id'                    => $prefix . $i,
			'orderNumber'           => 'ORD' . $i,
			'shipmentPackageStatus' => 'Created',
			'lastModifiedDate'      => ($first + ($i - 1) * $step) * 1000,
			'lines'                 => array(),
		);
	}

	return $packages;
}

// A transport that answers the shipmentPackages feed out of $feed the way n11
// does: only the packages modified inside startDate..endDate, in ascending
// order of modification, a page of the size asked for at a time. Every URL it
// is asked for is written into $calls. Request $fail_call (counted from 0),
// when given, answers 500.
function n11_test_feed_transport($feed, &$calls, $fail_call = -1)
{
	return function ($method, $url, $options) use ($feed, &$calls, $fail_call) {

		$calls[] = $url;

		$query = array();
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

		if (count($calls) - 1 === $fail_call) {
			return array('ok' => false, 'status' => 500, 'body' => '{"errors":[{"errorMessage":"down"}]}',
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
			'body' => json_encode(array('content' => $content, 'totalElements' => count($inside))),
			'error' => '', 'duration_ms' => 1);
	};
}

function n11_test_ids($packages)
{
	$ids = array();

	foreach ($packages as $package) {
		$ids[] = $package['remote_package_id'];
	}

	return $ids;
}

/* ------------------------------------------------------------ order feed */

function test_marketplace_n11_pull_reads_a_short_feed_to_the_end()
{
	$since = time() - 3600;
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since,
		n11_test_feed_transport(n11_test_packages(150, $since + 10, 1), $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(150, count($result['data']['packages']));
	pg_assert_true($result['data']['complete']);
	pg_assert_true($result['data']['read_to'] >= time() - 5, 'a feed read to its end moves the cursor to now');
	pg_assert_false(!empty($result['data']['more']));
	pg_assert_same(2, count($calls), 'one full page and one short one');
	pg_assert_contains('startDate=' . (($since + 10 + 99) * 1000 - 1) . '&', $calls[1],
		'the second request starts a millisecond before the last moment read');
	pg_assert_contains('&page=0&size=100&', $calls[1]);
	pg_assert_contains('startDate=' . (($since - 300) * 1000) . '&', $calls[0], 'the window starts five minutes early');
}

function test_marketplace_n11_pull_at_the_page_cap_moves_the_cursor_only_as_far_as_it_read()
{
	$since = time() - 86400;
	$feed = n11_test_packages(2500, $since + 60, 10);
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since, n11_test_feed_transport($feed, $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(20, count($calls), 'twenty pages, then it stops');
	pg_assert_same(100 + 19 * 99, count($result['data']['packages']),
		'every page after the first starts with the last package of the one before');
	pg_assert_same('pkg1981', $result['data']['packages'][1980]['remote_package_id']);
	pg_assert_true($result['data']['complete'], 'the cursor moves');
	pg_assert_true(!empty($result['data']['more']));
	pg_assert_same($since + 60 + 1980 * 10, $result['data']['read_to'],
		'to the modification time of the last package read, not to now');

	// The next pass starts there and reads the rest.
	$calls = array();

	$next = mp_n11_pull_orders(n11_test_account(), $result['data']['read_to'], n11_test_feed_transport($feed, $calls));

	pg_assert_true($next['ok']);
	pg_assert_true($next['data']['complete']);
	pg_assert_false(!empty($next['data']['more']), 'the second pass reaches the end of the feed');

	$seen = array_unique(array_merge(n11_test_ids($result['data']['packages']), n11_test_ids($next['data']['packages'])));

	pg_assert_same(2500, count($seen), 'every package of the window is read by one pass or the other');
	pg_assert_contains('pkg2500', $seen);
}

function test_marketplace_n11_pull_reads_past_the_cap_when_the_cap_would_not_move_the_cursor()
{
	$since = time() - 86400;

	// 2,050 packages modified inside the five-minute overlap before $since, then
	// newer ones. Twenty pages would end inside the overlap and hand the next
	// pass the same start.
	$feed = array_merge(
		n11_test_packages(2050, $since - 100, 0, 'old'),
		n11_test_packages(500, $since + 1000, 1, 'new'));
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since, n11_test_feed_transport($feed, $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(21, count($calls), 'one page past the cap reaches a newer package');
	pg_assert_same(2100, count($result['data']['packages']));
	pg_assert_true($result['data']['complete']);
	pg_assert_same($since + 1000 + 49, $result['data']['read_to']);
}

function test_marketplace_n11_pull_keeps_the_cursor_when_even_the_ceiling_does_not_move_it()
{
	$since = time() - 86400;
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since,
		n11_test_feed_transport(n11_test_packages(10100, $since - 100, 0), $calls));

	pg_assert_true($result['ok'], 'what was read is still handed over for import');
	pg_assert_same(100, count($calls), 'a hundred pages at most');
	pg_assert_same(10000, count($result['data']['packages']));
	pg_assert_false($result['data']['complete'], 'the cursor stays rather than jumping over the rest');
	pg_assert_false(isset($result['data']['read_to']));
}

function test_marketplace_n11_pull_failure_hands_over_what_was_read_and_keeps_the_cursor()
{
	$since = time() - 86400;
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since,
		n11_test_feed_transport(n11_test_packages(500, $since + 60, 1), $calls, 2));

	pg_assert_false($result['ok']);
	pg_assert_true(!empty($result['retry']), 'a 500 is worth trying again');
	pg_assert_same(100 + 99, count($result['data']['packages']));
	pg_assert_false($result['data']['complete']);
}

// A crowded overlap in bursts that each share one moment: only the new entries
// count toward the ceiling, so the pull gets through and the cursor moves on.
function test_marketplace_n11_pull_ceiling_counts_new_entries_not_requests()
{
	$since = time() - 86400;
	$feed = array();

	for ($group = 0; $group < 120; $group++) {
		$feed = array_merge($feed, n11_test_packages(50, $since - 290 + $group * 2, 0, 'g' . $group . '-'));
	}

	$feed = array_merge($feed, n11_test_packages(500, $since + 1000, 1, 'new'));
	$calls = array();

	$result = mp_n11_pull_orders(n11_test_account(), $since, n11_test_feed_transport($feed, $calls));

	pg_assert_true($result['ok']);
	pg_assert_true($result['data']['complete'], 'the overlap is read through');
	pg_assert_true($result['data']['read_to'] > $since, 'the cursor moves past where it started');
	pg_assert_true(count($calls) <= 300, 'at most three requests per page of the ceiling');
}

// A package read on the first page changes before the second page is asked
// for: no other package is lost because of it.
function test_marketplace_n11_pull_loses_no_package_when_one_already_read_changes()
{
	$since = time() - 3600;
	$feed = n11_test_packages(250, $since + 10, 10);
	$calls = array();
	$requests = 0;

	$transport = function ($method, $url, $options) use (&$feed, &$calls, &$requests) {

		$requests++;

		if ($requests === 2) {
			$feed[5]['lastModifiedDate'] = (time() + 120) * 1000;
		}

		$answer = n11_test_feed_transport($feed, $calls);

		return $answer($method, $url, $options);
	};

	$result = mp_n11_pull_orders(n11_test_account(), $since, $transport);

	pg_assert_true($result['ok']);
	pg_assert_same(250, count(array_unique(n11_test_ids($result['data']['packages']))),
		'every package of the window is read');
}

/* ------------------------------------------------------- connection test */

// A transport that answers every call with $status and $body, keeping only as
// much of the body as the call's max_body allows, the way api_http_request()
// does. Every call's options are written into $calls.
function n11_test_fixed_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		$max = isset($options['max_body']) ? (int) $options['max_body'] : 1000;

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status,
			'body' => ($max > 0) ? substr($body, 0, $max) : $body, 'error' => '', 'duration_ms' => 1);
	};
}

function n11_test_categories_fixture()
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/n11/categories.json');
}

function test_marketplace_n11_connection_test_reads_the_whole_category_tree()
{
	$calls = array();
	$tree = n11_test_categories_fixture();

	pg_assert_true(strlen($tree) > 4000, 'the fixture is larger than the old limit');

	$result = mp_n11_test_credentials(n11_test_account(), n11_test_fixed_transport(200, $tree, $calls));

	pg_assert_true($result['ok'], 'the right key passes the test');
	pg_assert_same(12, $result['data']['categories']);
	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same('https://api.n11.com/cdn/categories', $calls[0]['url']);
	pg_assert_same(mp_n11_category_tree_bytes(), $calls[0]['options']['max_body'], 'the same limit as the category sync');
	pg_assert_contains('appKey: key-abc', $calls[0]['options']['headers']);
}

function test_marketplace_n11_connection_test_names_a_refused_key()
{
	$calls = array();

	$result = mp_n11_test_credentials(n11_test_account(),
		n11_test_fixed_transport(401, '<html><body>Unauthorized</body></html>', $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('did not accept the API key and secret', $result['error']);
}

function test_marketplace_n11_cut_off_answer_is_named_as_such()
{
	$calls = array();

	$result = mp_n11_call(n11_test_account(), 'GET', '/cdn/categories', null, 4000,
		n11_test_fixed_transport(200, n11_test_categories_fixture(), $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('more than the 4000 bytes', $result['error']);
	pg_assert_false(!empty($result['retry']), 'asking again gets the same cut');

	// A short answer that is not JSON is still that.
	$result = mp_n11_call(n11_test_account(), 'GET', '/cdn/categories', null, 4000,
		n11_test_fixed_transport(200, '<html>maintenance</html>', $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('not JSON', $result['error']);
}

function test_marketplace_n11_long_error_page_is_still_retried()
{
	$calls = array();

	// A 503 page longer than the call's limit is an outage, not a cut answer:
	// the sync queue must still try it again.
	$result = mp_n11_call(n11_test_account(), 'POST', '/ms/product/tasks/price-stock-update',
		array('payload' => array()), 8000,
		n11_test_fixed_transport(503, '<html>' . str_repeat('x', 9000) . '</html>', $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('not JSON', $result['error']);
	pg_assert_true(!empty($result['retry']), 'a failed status keeps its retry flag');
}

function test_marketplace_n11_category_tree_flattens_the_fixture()
{
	$calls = array();

	$result = mp_n11_category_tree(n11_test_account(),
		n11_test_fixed_transport(200, n11_test_categories_fixture(), $calls));

	pg_assert_true($result['ok']);

	$rows = $result['data']['categories'];
	$leaves = array_filter($rows, function ($row) { return $row['is_leaf'] === 1; });

	pg_assert_same(372, count($rows));
	pg_assert_same(360, count($leaves));
	pg_assert_same('Elektronik > Elektronik Alt Kategori 1', $rows[1]['path']);
	pg_assert_same((string) $rows[0]['remote_id'], $rows[1]['parent_id']);
}

function test_marketplace_n11_vat_rate_rounds_down_to_theirs()
{
	pg_assert_same(20, mp_n11_vat_rate(array('tax_rate' => 20.0)));
	pg_assert_same(10, mp_n11_vat_rate(array('tax_rate' => 18.0)));
	pg_assert_same(1, mp_n11_vat_rate(array('tax_rate' => 8.5)));
	pg_assert_same(0, mp_n11_vat_rate(array('tax_rate' => 0.0)));
}

// The rate inside the price that is sent wins over the product's own: 0 for
// a product the shop does not tax (tests/marketplace_vat_rate_test.php has
// every tax state). Without it the product's own rate is declared.
function test_marketplace_n11_vat_rate_follows_the_applied_rate()
{
	pg_assert_same(0, mp_n11_vat_rate(array('taxable' => false, 'tax_rate' => 20.0, 'vat_rate' => 0.0)));
	pg_assert_same(10, mp_n11_vat_rate(array('taxable' => true, 'tax_rate' => null, 'vat_rate' => 18.0)));
	pg_assert_same(20, mp_n11_vat_rate(array('taxable' => true, 'tax_rate' => 20.0, 'vat_rate' => null)));
	pg_assert_same(20, mp_n11_vat_rate(array('tax_rate' => 20.0)));
}

/* ---------------------------------------------------------- their catalogue */

// One page of their product-query answer, in the shape of the documentation's
// example.
function n11_test_product_page($products, $page, $pages, $last)
{
	return json_encode(array(
		'content'          => $products,
		'totalElements'    => 250,
		'totalPages'       => $pages,
		'number'           => $page,
		'numberOfElements' => count($products),
		'last'             => $last,
	));
}

function test_marketplace_n11_remote_products_reads_one_page_of_the_catalogue()
{
	$calls = array();

	$body = n11_test_product_page(array(
		array(
			'n11ProductId' => 123456789, 'stockCode' => ' TestSKU123 ', 'productMainId' => 'GRP1',
			'title' => 'Test Ürünü Gri', 'barcode' => null, 'status' => 'Active', 'saleStatus' => 'On_Sale',
			'salePrice' => 249.9, 'quantity' => 2,
			'imageUrls' => array('http://plain.example/a.jpg', 'https://n11scdn.akamaized.net/a1/org/1.jpg'),
		),
		array('n11ProductId' => 5, 'stockCode' => '', 'title' => 'No code'),
		array('n11ProductId' => 6, 'stockCode' => 'B-2', 'title' => 'Second', 'salePrice' => '1.234,5'),
	), 1, 3, false);

	$result = mp_n11_remote_products(n11_test_account(), 1, n11_test_fixed_transport(200, $body, $calls));

	pg_assert_true($result['ok']);
	pg_assert_contains('/ms/product-query?page=1&size=100', $calls[0]['url']);
	pg_assert_same(8388608, $calls[0]['options']['max_body'], 'a page is read whole');
	pg_assert_same(2, count($result['data']['products']), 'a product without a stock code is left out');

	$first = $result['data']['products'][0];

	pg_assert_same('TestSKU123', $first['remote_code']);
	pg_assert_same('123456789', $first['remote_id']);
	pg_assert_same('GRP1', $first['group_code']);
	pg_assert_same('', $first['barcode']);
	pg_assert_same(24990, $first['price'], 'the sale price in minor units');
	pg_assert_same('https://n11scdn.akamaized.net/a1/org/1.jpg', $first['image_url'], 'the first https picture');
	pg_assert_same('Active/On_Sale', $first['status']);
	pg_assert_same(3, $result['data']['pages']);
	pg_assert_false($result['data']['last']);
}

function test_marketplace_n11_remote_products_knows_the_last_page()
{
	$calls = array();

	$last = mp_n11_remote_products(n11_test_account(), 2,
		n11_test_fixed_transport(200, n11_test_product_page(array(array('stockCode' => 'A')), 2, 3, false), $calls));

	pg_assert_true($last['data']['last'], 'the page count says so');

	$empty = mp_n11_remote_products(n11_test_account(), 0,
		n11_test_fixed_transport(200, n11_test_product_page(array(), 0, 0, false), $calls));

	pg_assert_true($empty['data']['last'], 'an empty page is the end');
}

function test_marketplace_n11_remote_products_reads_the_n11_warehouse_too()
{
	$calls = array();

	$body = n11_test_product_page(array(
		array('stockCode' => 'OWN-1', 'status' => 'Active', 'saleStatus' => 'On_Sale', 'sender' => 'SELLER'),
		array('stockCode' => 'DEPO-1', 'status' => 'Active', 'saleStatus' => 'On_Sale', 'sender' => 'N11'),
	), 0, 1, true);

	$result = mp_n11_remote_products(n11_test_account(), 0, n11_test_fixed_transport(200, $body, $calls));

	pg_assert_contains('&sender=ALL', $calls[0]['url'], 'the default (SELLER) leaves the warehouse out');
	pg_assert_same('Active/On_Sale', $result['data']['products'][0]['status']);
	pg_assert_same('Active/On_Sale/N11_Warehouse', $result['data']['products'][1]['status'], 'a warehouse product is marked');
}

function test_marketplace_n11_warehouse_products_get_their_price_but_not_stock()
{
	$rows = array(
		array('remote_code' => 'OWN-1', 'tracked' => 1, 'quantity' => 4, 'price_major' => 10),
		array('remote_code' => 'DEPO-1', 'tracked' => 1, 'quantity' => 9, 'price_major' => 20),
	);

	$both = mp_n11_stock_price_skus(array('push_stock' => 1, 'push_price' => 1), $rows, array('DEPO-1' => true));

	pg_assert_same(4, $both['skus'][0]['quantity']);
	pg_assert_false(isset($both['skus'][1]['quantity']), 'the n11 warehouse keeps that stock');
	pg_assert_same('20.00', $both['skus'][1]['salePrice'], 'its price is still the seller\'s');

	$stock = mp_n11_stock_price_skus(array('push_stock' => 1, 'push_price' => 0), $rows, array('DEPO-1' => true));

	pg_assert_same(array('OWN-1'), array_map(function ($sku) { return $sku['stockCode']; }, $stock['skus']),
		'nothing left to send for it on a stock-only account');
	pg_assert_same(1, $stock['warehouse']);

	$exact = mp_n11_stock_price_skus(array('push_stock' => 1, 'push_price' => 0), array(
		array('remote_code' => 'depo-1', 'tracked' => 1, 'quantity' => 2, 'price_major' => 0),
	), array('DEPO-1' => true));

	pg_assert_same(2, $exact['skus'][0]['quantity'], 'codes are compared byte for byte, as the catalogue keeps them');
}

// n11's city is the province and its district the town within it: the store
// keeps the town as the city and the province as the state, the way the
// checkout's Turkish address does.
function test_marketplace_n11_address_puts_the_district_in_city_and_the_province_in_state()
{
	$entry = array('shippingAddress' => array(
		'fullName' => 'Ayşe Yılmaz',
		'address'  => 'Atatürk Cad. No 5',
		'city'     => 'İstanbul',
		'district' => 'Kadıköy',
		'gsm'      => '5551234567',
	));

	$address = mp_n11_address($entry, 'shippingAddress');

	pg_assert_same('Kadıköy', $address['city']);
	pg_assert_same('İstanbul', $address['state']);

	// Their other spellings of the two fields.
	$other = mp_n11_address(array('billingAddress' => array('cityName' => 'Ankara', 'districtName' => 'Çankaya')), 'billingAddress');

	pg_assert_same('Çankaya', $other['city']);
	pg_assert_same('Ankara', $other['state']);
}
