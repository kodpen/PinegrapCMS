<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Etsy listings (includes/fn/etsy.php and the marketplace
 * connector includes/api/outbound/connectors/etsy.php): the draft's fields,
 * the variations of an article, the inventory body, the picture upload's
 * multipart body, the price in the shop's currency and the calls of one
 * listing from draft to active, read back.
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

function etsy_listings_test_fixture($name)
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/etsy/' . $name), true);
}

// A call answering each "METHOD path" from $routes (=> array(status,
// fixture)), writing each one and its options into $calls; an unknown one
// is 404.
function etsy_listings_test_call($routes, &$calls)
{
	return function ($method, $path, $options = array()) use ($routes, &$calls) {
		$key = $method . ' ' . $path;
		$calls[] = array($key, $options);

		if (!isset($routes[$key])) {
			return array('ok' => false, 'status' => 404, 'data' => array(), 'error' => '');
		}

		$status = $routes[$key][0];

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status,
			'data' => etsy_listings_test_fixture($routes[$key][1]), 'error' => '');
	};
}

function etsy_listings_test_account()
{
	return array('id' => 9, 'provider' => 'etsy', 'credentials' => array(
		'shop' => '87654321', 'shipping_profile' => '201', 'readiness_state' => '301',
		'who_made' => 'i_did', 'when_made' => 'made_to_order'));
}

function etsy_listings_test_item($code, $size, $size_id, $quantity, $images = null)
{
	return array(
		'product_id'  => crc32($code) % 1000,
		'code'        => $code,
		'group_code'  => 'APRON',
		'group_title' => 'Linen apron',
		'title'       => 'Linen apron ' . $size,
		'description' => '<p>Stonewashed&nbsp;linen apron.</p><ul><li>Two pockets</li></ul>',
		'category_id' => '3',
		'price_major' => 24.5,
		'quantity'    => $quantity,
		'images'      => ($images === null) ? array('https://shop.example/apron-front.jpg', 'https://shop.example/apron-back.png') : $images,
		'attributes'  => array(
			array('id' => '100', 'value_id' => $size_id, 'custom_value' => '', 'value' => $size),
			array('id' => '200', 'value_id' => '1', 'custom_value' => '', 'value' => 'Beige'),
		),
	);
}

// The bytes of a picture by its address: a JPEG and a PNG header.
function etsy_listings_test_read($url)
{
	return (substr($url, -4) === '.png') ? "\x89PNG\r\n\x1A\nrest" : "\xFF\xD8\xFF\xE0rest";
}

function etsy_listings_test_routes()
{
	return array(
		'GET /shops/87654321'                                   => array(200, 'shop.json'),
		'POST /shops/87654321/listings'                         => array(201, 'listing-draft.json'),
		'POST /shops/87654321/listings/1543210987/images'       => array(201, 'listing-image.json'),
		'PUT /shops/87654321/listings/1543210987/properties/200' => array(200, 'listing-property.json'),
		'PUT /shops/87654321/listings/1543210987/properties/100' => array(200, 'listing-property.json'),
		'PUT /listings/1543210987/inventory'                    => array(200, 'listing-inventory.json'),
		'PATCH /shops/87654321/listings/1543210987'             => array(200, 'listing-active.json'),
	);
}

/* --------------------------------------------------------------- pure parts */

function test_etsy_price_converts_through_the_minor_unit()
{
	pg_assert_same('24.50', pg_etsy_price(24.5, 1));
	pg_assert_same('0.71', pg_etsy_price(24.5, 0.029), '2450 x 0.029 = 71.05 cents, half away from zero');
	pg_assert_same('', pg_etsy_price(0, 1), 'no price');
	pg_assert_same('', pg_etsy_price(10, null), 'no rate');
}

function test_etsy_plain_text_keeps_lines_and_drops_tags()
{
	pg_assert_same("Stonewashed linen apron.\n- Two pockets", pg_etsy_plain_text('<p>Stonewashed&nbsp;linen apron.</p><ul><li>Two pockets</li></ul>'));
	pg_assert_same('', pg_etsy_plain_text('<p> </p>'));
}

function test_etsy_variations_split_differing_and_shared_properties()
{
	$items = array(etsy_listings_test_item('APRON-S', 'S', '11', 3), etsy_listings_test_item('APRON-M', 'M', '12', 4));
	$variations = pg_etsy_variations($items);

	pg_assert_true($variations['ok']);
	pg_assert_same(array(100), $variations['variations'], 'size differs');
	pg_assert_same(array(200), array_keys($variations['shared']), 'colour is the listing\'s');
	pg_assert_same(array('value_ids' => array(1), 'values' => array('Beige')), array_slice($variations['shared'][200], 1), 'sent by id with its text');
}

function test_etsy_variations_refuse_products_that_do_not_differ()
{
	$items = array(etsy_listings_test_item('APRON-S', 'S', '11', 3), etsy_listings_test_item('APRON-S2', 'S', '11', 4));

	pg_assert_false(pg_etsy_variations($items)['ok']);
}

function test_etsy_variations_refuse_more_than_two_options()
{
	$items = array(etsy_listings_test_item('A', 'S', '11', 1), etsy_listings_test_item('B', 'M', '12', 1));
	$items[0]['attributes'][] = array('id' => '300', 'value_id' => '', 'custom_value' => 'Plain');
	$items[1]['attributes'][] = array('id' => '300', 'value_id' => '', 'custom_value' => 'Striped');
	$items[0]['attributes'][] = array('id' => '400', 'value_id' => '', 'custom_value' => 'Short');
	$items[1]['attributes'][] = array('id' => '400', 'value_id' => '', 'custom_value' => 'Long');

	pg_assert_false(pg_etsy_variations($items)['ok']);
}

function test_etsy_variations_of_one_product_are_none()
{
	$variations = pg_etsy_variations(array(etsy_listings_test_item('APRON-S', 'S', '11', 3)));

	pg_assert_true($variations['ok']);
	pg_assert_same(array(), $variations['variations']);
	pg_assert_same(array(100, 200), array_keys($variations['shared']));
}

function test_etsy_inventory_body_has_a_product_per_variant()
{
	$items = array(etsy_listings_test_item('APRON-S', 'S', '11', 3), etsy_listings_test_item('APRON-M', 'M', '12', 0));
	$body = pg_etsy_inventory_body($items, array(100), array('APRON-S' => '24.50', 'APRON-M' => '26.00'), '301');

	pg_assert_same(array(100), $body['price_on_property']);
	pg_assert_same(array(100), $body['quantity_on_property']);
	pg_assert_same(array(100), $body['sku_on_property']);
	pg_assert_same('APRON-S', $body['products'][0]['sku']);
	pg_assert_same(array(array('property_id' => 100, 'value_ids' => array(11), 'values' => array('S'))), $body['products'][0]['property_values']);
	pg_assert_same(array('price' => 24.5, 'quantity' => 3, 'is_enabled' => true, 'readiness_state_id' => 301), $body['products'][0]['offerings'][0]);
	pg_assert_false($body['products'][1]['offerings'][0]['is_enabled'], 'no stock: disabled');
}

function test_etsy_draft_fields_name_the_accounts_choices()
{
	$items = array(etsy_listings_test_item('APRON-S', 'S', '11', 3), etsy_listings_test_item('APRON-M', 'M', '12', 4));
	$fields = pg_etsy_draft_fields($items, etsy_listings_test_account()['credentials'], '24.50');

	pg_assert_same('Linen apron', $fields['title'], 'the article\'s title');
	pg_assert_same(7, $fields['quantity']);
	pg_assert_same(3, $fields['taxonomy_id']);
	pg_assert_same(201, $fields['shipping_profile_id']);
	pg_assert_same(301, $fields['readiness_state_id']);
	pg_assert_same('physical', $fields['type']);
	pg_assert_same("Stonewashed linen apron.\n- Two pockets", $fields['description']);
}

function test_etsy_multipart_body_carries_the_fields_and_the_file()
{
	$multipart = pg_etsy_multipart(array('rank' => 2, 'overwrite' => 'true'), 'apron.jpg', 'BYTES', 'image/jpeg', 'XYZ');

	pg_assert_same('multipart/form-data; boundary=XYZ', $multipart['content_type']);
	pg_assert_contains("name=\"rank\"\r\n\r\n2\r\n", $multipart['body']);
	pg_assert_contains("name=\"image\"; filename=\"apron.jpg\"\r\nContent-Type: image/jpeg\r\n\r\nBYTES\r\n--XYZ--\r\n", $multipart['body']);
}

function test_etsy_image_type_and_file_name()
{
	pg_assert_same('image/png', pg_etsy_image_type("\x89PNG\r\n\x1A\nx"));
	pg_assert_same('', pg_etsy_image_type('<?php'));
	pg_assert_same('apron front.jpg', pg_etsy_image_file_name('https://shop.example/apron%20front.jpg', 'https://shop.example/'));
	pg_assert_same('', pg_etsy_image_file_name('https://other.example/a.jpg', 'https://shop.example/'), 'another site');
	pg_assert_same('', pg_etsy_image_file_name('https://shop.example/..%2Fconfig.php', 'https://shop.example/'), 'no path');
}

function test_etsy_listing_verdict_reads_the_state()
{
	pg_assert_true(pg_etsy_listing_verdict(etsy_listings_test_fixture('listing-active.json'))['ok']);

	$draft = pg_etsy_listing_verdict(etsy_listings_test_fixture('listing-draft.json'));

	pg_assert_false($draft['ok']);
	pg_assert_same('draft', $draft['state']);
}

/* ------------------------------------------------------------ the connector */

function test_etsy_create_listings_goes_from_draft_to_active()
{
	$calls = array();
	$items = array(etsy_listings_test_item('APRON-S', 'S', '11', 3), etsy_listings_test_item('APRON-M', 'M', '12', 4));

	$result = mp_etsy_create_listings(etsy_listings_test_account(), $items,
		etsy_listings_test_call(etsy_listings_test_routes(), $calls), 1, 'etsy_listings_test_read', '');

	pg_assert_true($result['ok']);
	pg_assert_same('', $result['data']['task_id']);
	pg_assert_same(array('ok' => true, 'error' => '', 'content_id' => '1543210987'), $result['data']['results']['APRON-M']);

	pg_assert_same(array(
		'GET /shops/87654321',
		'POST /shops/87654321/listings',
		'POST /shops/87654321/listings/1543210987/images',
		'POST /shops/87654321/listings/1543210987/images',
		'PUT /shops/87654321/listings/1543210987/properties/200',
		'PUT /listings/1543210987/inventory',
		'PATCH /shops/87654321/listings/1543210987',
	), array_column($calls, 0), 'shop, draft, pictures in order, shared property, inventory, active');

	pg_assert_contains("name=\"rank\"\r\n\r\n1\r\n", $calls[2][1]['body']);
	pg_assert_contains('Content-Type: image/jpeg', $calls[2][1]['body'], 'the first product\'s first picture first');
	pg_assert_contains("name=\"rank\"\r\n\r\n2\r\n", $calls[3][1]['body']);

	$draft = json_decode($calls[1][1]['body'], true);

	pg_assert_same('Linen apron', $draft['title']);
	pg_assert_same(24.5, $draft['price']);
	pg_assert_same(array('state' => 'active'), json_decode($calls[6][1]['body'], true));
}

function test_etsy_create_listings_updates_the_listing_of_an_earlier_send()
{
	$calls = array();
	$routes = etsy_listings_test_routes();
	$routes['GET /listings/1543210987'] = array(200, 'listing-draft.json');

	$result = mp_etsy_create_listings(etsy_listings_test_account(), array(etsy_listings_test_item('APRON-S', 'S', '11', 3)),
		etsy_listings_test_call($routes, $calls), 1, 'etsy_listings_test_read', '1543210987');

	pg_assert_true($result['data']['results']['APRON-S']['ok']);
	pg_assert_false(in_array('POST /shops/87654321/listings', array_column($calls, 0), true), 'no second listing');

	$update = json_decode($calls[2][1]['body'], true);

	pg_assert_same('PATCH /shops/87654321/listings/1543210987', $calls[2][0]);
	pg_assert_false(isset($update['price']) || isset($update['quantity']), 'the inventory sets those');
}

function test_etsy_create_listings_reports_a_listing_etsy_keeps_inactive()
{
	$calls = array();
	$routes = etsy_listings_test_routes();
	$routes['PATCH /shops/87654321/listings/1543210987'] = array(200, 'listing-draft.json');

	$result = mp_etsy_create_listings(etsy_listings_test_account(), array(etsy_listings_test_item('APRON-S', 'S', '11', 3)),
		etsy_listings_test_call($routes, $calls), 1, 'etsy_listings_test_read', '');

	$verdict = $result['data']['results']['APRON-S'];

	pg_assert_false($verdict['ok']);
	pg_assert_contains('a draft', $verdict['error']);
	pg_assert_same('1543210987', $verdict['content_id'], 'kept, so the next send updates it');
}

function test_etsy_create_listings_gives_a_refusal_after_the_draft_with_its_listing()
{
	$calls = array();
	$routes = etsy_listings_test_routes();
	$routes['PUT /listings/1543210987/inventory'] = array(400, 'error-listing.json');

	$result = mp_etsy_create_listings(etsy_listings_test_account(), array(etsy_listings_test_item('APRON-S', 'S', '11', 3)),
		etsy_listings_test_call($routes, $calls), 1, 'etsy_listings_test_read', '');

	pg_assert_true($result['ok']);
	pg_assert_contains('Etsy: The listing is missing', $result['data']['results']['APRON-S']['error']);
	pg_assert_same('1543210987', $result['data']['results']['APRON-S']['content_id']);
}

function test_etsy_create_listings_gives_a_fault_before_the_draft_back_to_the_queue()
{
	$calls = array();
	$routes = etsy_listings_test_routes();
	$routes['POST /shops/87654321/listings'] = array(503, 'error-listing.json');

	$result = mp_etsy_create_listings(etsy_listings_test_account(), array(etsy_listings_test_item('APRON-S', 'S', '11', 3)),
		etsy_listings_test_call($routes, $calls), 1, 'etsy_listings_test_read', '');

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
}

function test_etsy_create_listings_refuses_before_any_call()
{
	$calls = array();
	$call = etsy_listings_test_call(etsy_listings_test_routes(), $calls);
	$account = etsy_listings_test_account();

	$no_choice = $account;
	unset($no_choice['credentials']['readiness_state']);
	pg_assert_false(mp_etsy_create_listings($no_choice, array(etsy_listings_test_item('A', 'S', '11', 1)), $call, 1, 'etsy_listings_test_read', '')['ok'], 'no processing profile');

	pg_assert_false(mp_etsy_create_listings($account, array(etsy_listings_test_item('A', 'S', '11', 1, array())), $call, 1, 'etsy_listings_test_read', '')['ok'], 'no picture');

	$long = etsy_listings_test_item('A', 'S', '11', 1);
	$long['group_title'] = '';
	$long['title'] = str_repeat('x', 141);
	pg_assert_false(mp_etsy_create_listings($account, array($long), $call, 1, 'etsy_listings_test_read', '')['ok'], 'title over 140');

	$unreadable = function ($url) {
		return '<html>';
	};
	pg_assert_false(mp_etsy_create_listings($account, array(etsy_listings_test_item('A', 'S', '11', 1)), $call, 1, $unreadable, '')['ok'], 'a picture that is no image');

	pg_assert_same(array(), $calls, 'nothing sent');
}

function test_etsy_create_listings_refuses_without_stock_or_rate()
{
	$calls = array();
	$call = etsy_listings_test_call(etsy_listings_test_routes(), $calls);

	$result = mp_etsy_create_listings(etsy_listings_test_account(), array(etsy_listings_test_item('A', 'S', '11', 0)), $call, 1, 'etsy_listings_test_read', '');
	pg_assert_false($result['ok'], 'nothing in stock');
	pg_assert_false(!empty($result['retry']));

	$result = mp_etsy_create_listings(etsy_listings_test_account(), array(etsy_listings_test_item('A', 'S', '11', 2)), $call, 0, 'etsy_listings_test_read', '');
	pg_assert_false($result['ok'], 'no rate');

	pg_assert_same(array('GET /shops/87654321', 'GET /shops/87654321'), array_column($calls, 0), 'only the shop read');
}

function test_etsy_connector_can_list_and_names_its_fee()
{
	pg_assert_true(mp_can('etsy', 'create_listings'));
	pg_assert_contains('USD 0.20', mp_etsy_listing_fee(etsy_listings_test_account()));
}
