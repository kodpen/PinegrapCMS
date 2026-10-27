<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for one page of a seller's Trendyol catalogue, read for the bulk
 * matching screen (mp_trendyol_remote_products() in
 * includes/api/outbound/connectors/trendyol.php).
 *
 * Trendyol is never called: the connector takes a transport with the signature
 * of api_http_request(), and the one here answers with a page built in the
 * shape of their V2 approved products documentation, not recorded from a live
 * account.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The outbound files sit behind init.php's gate and only declare functions
// behind it, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function trendyol_remote_test_account()
{
	return array(
		'id'          => 7,
		'provider'    => 'trendyol',
		'credentials' => array(
			'seller_id'   => '123456',
			'api_key'     => 'key-abc',
			'api_secret'  => 'secret-xyz',
			'environment' => 'production',
		),
	);
}

// A transport that answers every call with $status and $body and writes the
// call into $calls.
function trendyol_remote_test_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

function trendyol_remote_test_page($content, $page, $pages, $total)
{
	return json_encode(array(
		'totalElements' => $total,
		'totalPages'    => $pages,
		'page'          => $page,
		'size'          => 100,
		'content'       => $content,
	));
}

function trendyol_remote_test_content()
{
	return array(
		array(
			'contentId'     => 1239111111,
			'productMainId' => ' PG-TSHIRT ',
			'title'         => ' Pamuklu Tişört ',
			'images'        => array(array('url' => 'http://plain.example/a.jpg'), array('url' => 'https://cdn.example.com/a.jpg')),
			'variants'      => array(
				array(
					'variantId' => 9001, 'barcode' => ' 8680000000011 ', 'stockCode' => 'TSHIRT-RED-M',
					'onSale' => true, 'stock' => array('quantity' => 12),
					'price' => array('salePrice' => 199.99, 'listPrice' => 249.99),
					'locked' => false, 'archived' => false, 'blacklisted' => false,
				),
				array(
					'variantId' => 9002, 'barcode' => 'ty-red-l', 'onSale' => false,
					'stock' => array('quantity' => 0), 'price' => array('salePrice' => '0.295'),
					'locked' => true, 'archived' => true, 'blacklisted' => false,
					'images' => array(array('url' => 'https://cdn.example.com/red-l.jpg')),
				),
				array('variantId' => 9003, 'barcode' => '', 'onSale' => true),
			),
		),
		array(
			'contentId'     => 1239222222,
			'productMainId' => 'PG-MUG',
			'title'         => 'Seramik Kupa',
			'variants'      => array(
				array('variantId' => 9101, 'barcode' => 'MUG-01', 'blacklisted' => true),
			),
		),
		'not a content',
	);
}

function test_marketplace_trendyol_remote_products_is_in_the_connector_table()
{
	$table = mp_trendyol_connector();

	pg_assert_same('mp_trendyol_remote_products', $table['remote_products']);
}

function test_marketplace_trendyol_remote_products_asks_the_approved_products_a_page_at_a_time()
{
	$calls = array();

	$result = mp_trendyol_remote_products(trendyol_remote_test_account(), 2,
		trendyol_remote_test_transport(200, trendyol_remote_test_page(trendyol_remote_test_content(), 2, 5, 430), $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(1, count($calls), 'one call a page');
	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/product/sellers/123456/products/approved?page=2&size=100',
		$calls[0]['url']);
	pg_assert_same(8388608, $calls[0]['options']['max_body'], 'a page is read whole');
	pg_assert_same(2, $result['data']['page']);
	pg_assert_same(5, $result['data']['pages']);
	pg_assert_same(430, $result['data']['total']);
	pg_assert_false($result['data']['last']);
}

function test_marketplace_trendyol_remote_products_gives_one_row_per_variant_keyed_by_barcode()
{
	$calls = array();

	$result = mp_trendyol_remote_products(trendyol_remote_test_account(), 0,
		trendyol_remote_test_transport(200, trendyol_remote_test_page(trendyol_remote_test_content(), 0, 1, 4), $calls));

	$products = $result['data']['products'];

	pg_assert_same(3, count($products), 'a variant without a barcode is left out');

	pg_assert_same(array(
		'remote_code' => '8680000000011',
		'remote_id'   => '1239111111',
		'group_code'  => 'PG-TSHIRT',
		'barcode'     => '8680000000011',
		'title'       => 'Pamuklu Tişört',
		'image_url'   => 'https://cdn.example.com/a.jpg',
		'price'       => 19999,
		'quantity'    => 12,
		'status'      => 'onSale',
	), $products[0]);

	pg_assert_same('ty-red-l', $products[1]['remote_code']);
	pg_assert_same('PG-TSHIRT', $products[1]['group_code'], 'variants of one content share its group');
	pg_assert_same('https://cdn.example.com/red-l.jpg', $products[1]['image_url'], 'the variant\'s own picture first');
	pg_assert_same(30, $products[1]['price'], 'rounded to minor units, not cut');
	pg_assert_same(0, $products[1]['quantity']);
	pg_assert_same('locked/archived', $products[1]['status']);

	pg_assert_same('MUG-01', $products[2]['remote_code']);
	pg_assert_same('1239222222', $products[2]['remote_id']);
	pg_assert_same('', $products[2]['image_url'], 'no https picture, none kept');
	pg_assert_same(0, $products[2]['price'], 'no price, zero');
	pg_assert_same('blacklisted', $products[2]['status']);

	pg_assert_true($result['data']['last'], 'the page count says so');
}

function test_marketplace_trendyol_remote_products_knows_the_end_without_a_count()
{
	$calls = array();

	$empty = mp_trendyol_remote_products(trendyol_remote_test_account(), 3,
		trendyol_remote_test_transport(200, trendyol_remote_test_page(array(), 3, 0, 0), $calls));

	pg_assert_true($empty['ok']);
	pg_assert_same(array(), $empty['data']['products']);
	pg_assert_true($empty['data']['last'], 'an empty page is the end');

	$negative = mp_trendyol_remote_products(trendyol_remote_test_account(), -4,
		trendyol_remote_test_transport(200, trendyol_remote_test_page(array(), 0, 0, 0), $calls));

	pg_assert_true($negative['ok']);
	pg_assert_contains('approved?page=0&size=100', $calls[1]['url'], 'a page below zero is the first');
}

function test_marketplace_trendyol_remote_products_passes_their_refusal_on()
{
	$calls = array();

	$result = mp_trendyol_remote_products(trendyol_remote_test_account(), 0,
		trendyol_remote_test_transport(401, '{"errors":[{"message":"unauthorized"}]}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['error'] !== '', 'the refusal is named');

	$account = trendyol_remote_test_account();
	$account['credentials']['seller_id'] = '';

	$calls = array();

	$missing = mp_trendyol_remote_products($account, 0, trendyol_remote_test_transport(200, '{}', $calls));

	pg_assert_false($missing['ok']);
	pg_assert_same(0, count($calls), 'nothing is asked without a seller id');
}
