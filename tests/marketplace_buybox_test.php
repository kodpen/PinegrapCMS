<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the buybox read: what the Trendyol connector sends to and reads
 * from their buybox service (includes/api/outbound/connectors/trendyol.php),
 * and what the marketplace job keeps per listing in marketplace_price_state
 * (includes/api/outbound/sync.php).
 *
 * Trendyol is never called: the connector takes a transport with the
 * signature of api_http_request(), and the one here answers from
 * tests/fixtures/trendyol/buybox-information.json (built in the shape of
 * their "Product Buybox Check" documentation, November 2026, not recorded
 * from a live account).
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function buybox_test_account($credentials = array())
{
	return array(
		'id'          => 7,
		'provider'    => 'trendyol',
		'credentials' => array_merge(array(
			'seller_id'   => '123456',
			'api_key'     => 'key-abc',
			'api_secret'  => 'secret-xyz',
			'environment' => 'production',
		), $credentials),
	);
}

function buybox_test_fixture()
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/buybox-information.json');
}

function buybox_test_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

/* ----------------------------------------------------------- connector */

function test_marketplace_buybox_connector_names_the_ability()
{
	pg_assert_true(mp_can('trendyol', 'buybox'));
	pg_assert_false(mp_can('n11', 'buybox'), 'n11 has no buybox read');
}

function test_marketplace_buybox_limit_is_ten_barcodes()
{
	pg_assert_same(10, mp_trendyol_buybox_size());

	$calls = array();
	$barcodes = array();

	for ($i = 0; $i < 11; $i++) {
		$barcodes[] = '86800000001' . sprintf('%02d', $i);
	}

	$result = mp_trendyol_buybox(buybox_test_account(), $barcodes,
		buybox_test_transport(200, buybox_test_fixture(), $calls));

	pg_assert_false($result['ok'], 'eleven barcodes are refused');
	pg_assert_same(0, count($calls), 'and nothing is sent');
}

// The documented call: POST with the barcodes in the body and the storefront
// in a header.
function test_marketplace_buybox_sends_the_documented_body_and_header()
{
	$calls = array();

	$result = mp_trendyol_buybox(buybox_test_account(),
		array('8680000000011', ' 8680000000028 ', '8680000000011', 'TY-CAP-1', 'bad barcode!'),
		buybox_test_transport(200, buybox_test_fixture(), $calls));

	pg_assert_true($result['ok'], isset($result['error']) ? $result['error'] : '');
	pg_assert_same(1, count($calls));
	pg_assert_same('POST', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/product/sellers/123456/products/buybox-information', $calls[0]['url']);
	pg_assert_same('{"barcodes":["8680000000011","8680000000028","TY-CAP-1"]}', $calls[0]['options']['body'],
		'trimmed, once each, the refused one left out');
	pg_assert_true(in_array('storeFrontCode: TR', $calls[0]['options']['headers'], true));
	pg_assert_true(in_array('Content-Type: application/json', $calls[0]['options']['headers'], true));

	pg_assert_same(array('bad barcode!'), array_map('strval', array_keys($result['data']['refused'])));
	pg_assert_same(array('8680000000011', '8680000000028', 'TY-CAP-1'), array_map('strval', array_keys($result['data']['buybox'])));
}

function test_marketplace_buybox_storefront_from_the_credentials()
{
	pg_assert_same('TR', mp_trendyol_storefront(buybox_test_account()));
	pg_assert_same('DE', mp_trendyol_storefront(buybox_test_account(array('storefront' => 'de'))));
	pg_assert_same('TR', mp_trendyol_storefront(buybox_test_account(array('storefront' => 'TR: x'))), 'a bad code falls back');

	$calls = array();
	mp_trendyol_buybox(buybox_test_account(array('environment' => 'stage')), array('8680000000011'),
		buybox_test_transport(200, buybox_test_fixture(), $calls));

	pg_assert_same('https://stageapigw.trendyol.com/integration/product/sellers/123456/products/buybox-information', $calls[0]['url']);
}

// The fixture shapes: rank, the buybox price in minor units, other sellers.
function test_marketplace_buybox_reads_the_fixture_shape()
{
	$wanted = array('8680000000011' => '8680000000011', '8680000000028' => '8680000000028',
		'ty-cap-1' => 'TY-CAP-1', '8680000000035' => '8680000000035', '8680000000042' => '8680000000042');

	$rows = mp_trendyol_buybox_rows(json_decode(buybox_test_fixture(), true), $wanted);

	pg_assert_same(array('rank' => 1, 'price' => 60000, 'others' => false), $rows['8680000000011'], 'ours, alone');
	pg_assert_same(array('rank' => 3, 'price' => 29990, 'others' => true), $rows['8680000000028'], 'a decimal price');
	pg_assert_same(array('rank' => 1, 'price' => 14999, 'others' => true), $rows['TY-CAP-1'],
		'a barcode answered in other capitals keeps the spelling asked; a price sent as text');
	pg_assert_same(470, $rows['8680000000035']['rank']);
	pg_assert_false(isset($rows['8680000000099']), 'a barcode not asked for is left out');
	pg_assert_false(isset($rows['8680000000042']), 'an entry with no rank or a negative price is left out');

	pg_assert_same(array(), mp_trendyol_buybox_rows(array('buyboxInfo' => 'nope'), $wanted));
	pg_assert_same(array(), mp_trendyol_buybox_rows(null, $wanted));
}

// A second seller ahead of us means there are other sellers, whatever the
// flag says.
function test_marketplace_buybox_rank_over_one_has_other_sellers()
{
	$rows = mp_trendyol_buybox_rows(array('buyboxInfo' => array(
		array('barcode' => 'A1', 'buyboxOrder' => 2, 'buyboxPrice' => 10, 'hasMultipleSeller' => false),
	)), array('a1' => 'A1'));

	pg_assert_true($rows['A1']['others']);
}

function test_marketplace_buybox_errors_pass_through()
{
	$calls = array();

	$result = mp_trendyol_buybox(buybox_test_account(), array('8680000000011'),
		buybox_test_transport(429, '', $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry'], 'a rate limit is tried again later');

	$calls = array();
	$result = mp_trendyol_buybox(buybox_test_account(), array('bad barcode!'),
		buybox_test_transport(200, buybox_test_fixture(), $calls));

	pg_assert_true($result['ok']);
	pg_assert_same(0, count($calls), 'nothing to ask: no call');
}

/* -------------------------------------------------------------- the job */

function test_marketplace_buybox_pass_limits()
{
	pg_assert_same(3600, mp_sync_buybox_interval(), 'each listing is read once an hour');
	pg_assert_same(10, mp_sync_buybox_chunk(), 'ten barcodes a call');
	pg_assert_true(mp_sync_buybox_calls() * 12 < 1000, 'a pass every five minutes stays far under 1000 calls a minute');
	pg_assert_same(10, mp_sync_buybox_chunk(10), 'never over the connector\'s size');
	pg_assert_same(5, mp_sync_buybox_chunk(5));
}

function test_marketplace_buybox_ours_packs_rank_and_other_sellers()
{
	pg_assert_same(0, mp_buybox_ours_value(0, true), 'not read');
	pg_assert_same(1, mp_buybox_ours_value(1, false), 'ours, alone');
	pg_assert_same(129, mp_buybox_ours_value(1, true), 'ours, with other sellers');
	pg_assert_same(131, mp_buybox_ours_value(3, false), 'third: there are other sellers');
	pg_assert_same(255, mp_buybox_ours_value(470, true), 'the rank is capped at 127');

	pg_assert_same(array('rank' => 0, 'others' => false), mp_buybox_ours_read(0));
	pg_assert_same(array('rank' => 1, 'others' => false), mp_buybox_ours_read(1));
	pg_assert_same(array('rank' => 1, 'others' => true), mp_buybox_ours_read(129));
	pg_assert_same(array('rank' => 127, 'others' => true), mp_buybox_ours_read(255));
}

// The stored state: a listing answered gets its read; one asked but not
// answered (or refused) gets the read time with nothing held, so it waits an
// hour like the rest.
function test_marketplace_buybox_stored_state()
{
	$rows = array(
		array('product_id' => 501, 'remote_code' => '8680000000011'),
		array('product_id' => 502, 'remote_code' => '8680000000028'),
		array('product_id' => 503, 'remote_code' => '8680000000077'),
	);

	$answer = array(
		'buybox'  => array(
			'8680000000011' => array('rank' => 1, 'price' => 60000, 'others' => false),
			'8680000000028' => array('rank' => 3, 'price' => 29990, 'others' => true),
		),
		'refused' => array(),
	);

	$values = mp_sync_buybox_values($rows, $answer, 1800000000);

	pg_assert_same(array(
		501 => array('buybox_price' => 60000, 'buybox_ours' => 1, 'buybox_at' => 1800000000),
		502 => array('buybox_price' => 29990, 'buybox_ours' => 131, 'buybox_at' => 1800000000),
		503 => array('buybox_price' => 0, 'buybox_ours' => 0, 'buybox_at' => 1800000000),
	), $values);

	$sql = mp_sync_buybox_state_sql(7, $values, 1800000000);

	pg_assert_contains('INSERT INTO marketplace_price_state (account_id, product_id, buybox_price, buybox_ours, buybox_at, updated_at)', $sql);
	pg_assert_contains("('7', '501', '60000', '1', '1800000000', '1800000000')", $sql);
	pg_assert_contains("('7', '503', '0', '0', '1800000000', '1800000000')", $sql);
	pg_assert_contains('ON DUPLICATE KEY UPDATE buybox_price = VALUES(buybox_price)', $sql);
	pg_assert_false(strpos($sql, 'strategy') !== false, 'the strategy and typed prices are never touched');
	pg_assert_false(strpos($sql, 'own_price') !== false);

	pg_assert_same('', mp_sync_buybox_state_sql(7, array(), 1800000000));
}

function test_marketplace_buybox_barcode_matches_without_case()
{
	$values = mp_sync_buybox_values(array(array('product_id' => 9, 'remote_code' => ' ty-cap-1 ')),
		array('buybox' => array('TY-CAP-1' => array('rank' => 2, 'price' => 100, 'others' => true))), 5);

	pg_assert_same(array(9 => array('buybox_price' => 100, 'buybox_ours' => 130, 'buybox_at' => 5)), $values);
}

// The due listings: approved, active, with stock, read over an hour ago or
// never.
function test_marketplace_buybox_due_listings_sql()
{
	$sql = mp_sync_buybox_due_sql(7, 1800000000, 500);

	pg_assert_contains("map.account_id = '7'", $sql);
	pg_assert_contains("map.status = 'active'", $sql);
	pg_assert_contains("map.remote_id <> ''", $sql);
	pg_assert_contains("map.remote_code <> ''", $sql);
	pg_assert_contains('products.inventory_quantity > 0', $sql);
	pg_assert_contains('products.enabled', $sql);
	pg_assert_contains("COALESCE(state.buybox_at, 0) <= '1799996400'", $sql);
	pg_assert_contains('LIMIT 500', $sql);
	pg_assert_false((bool) preg_match('/\bAS\s+(rank|stored|row|groups)\b/i', $sql), 'no reserved alias');
}
