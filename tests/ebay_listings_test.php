<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for eBay listings (includes/fn/ebay.php and the marketplace
 * connector includes/api/outbound/connectors/ebay.php): the inventory item,
 * the group of an article's variants, the offer and its price in the site's
 * currency, the "does not apply" text of a missing identifier, the publish
 * and each SKU's verdict, and the listing's state read back.
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

function ebay_listings_test_fixture($name)
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/ebay/' . $name), true);
}

// A call answering "METHOD path" from $routes (=> array(status, fixture or
// null)) and writing each call, with its body, into $calls. A GET of an
// offer not in the routes is eBay's 404; any other unknown call is 204.
function ebay_listings_test_call($routes, &$calls)
{
	return function ($method, $path, $body = null) use ($routes, &$calls) {
		$key = $method . ' ' . $path;
		$calls[] = array('call' => $key, 'body' => $body);

		if (isset($routes[$key])) {
			$status = $routes[$key][0];
			$data = ($routes[$key][1] !== null) ? ebay_listings_test_fixture($routes[$key][1]) : array();
		} elseif (($method === 'GET') && (strpos($path, '/sell/inventory/v1/offer?') === 0)) {
			$status = 404;
			$data = ebay_listings_test_fixture('offer-not-found.json');
		} elseif (($method === 'POST') && ($path === '/sell/inventory/v1/offer')) {
			$status = 201;
			$data = ebay_listings_test_fixture('offer-created.json');
		} else {
			$status = 204;
			$data = array();
		}

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'data' => is_array($data) ? $data : array(), 'error' => '');
	};
}

function ebay_listings_test_account($credentials = array())
{
	return array('id' => 7, 'provider' => 'ebay', 'credentials' => $credentials + array(
		'environment'        => 'production',
		'site'               => 'EBAY_DE',
		'location'           => 'warehouse-1',
		'fulfillment_policy' => '6196932000',
		'payment_policy'     => '6196933000',
		'return_policy'      => '6196934000',
	));
}

function ebay_listings_test_item($code = 'CHAIR-RED', $colour = 'Rot', $overrides = array())
{
	return $overrides + array(
		'product_id'  => 11,
		'code'        => $code,
		'stock_code'  => $code,
		'barcode'     => '4006381333931',
		'brand'       => 'Woodline',
		'group_code'  => 'CHAIR',
		'group_title' => 'Wooden chair',
		'title'       => 'Wooden chair ' . $colour,
		'description' => '<p>Solid <b>beech</b>.</p>',
		'category_id' => '63861',
		'price_major' => 39.9,
		'quantity'    => 4,
		'taxable'     => true,
		'tax_rate'    => null,
		'vat_rate'    => 20.0,
		'images'      => array('https://shop.example/' . $code . '.jpg'),
		'attributes'  => array(
			array('id' => 'Marke', 'value_id' => '', 'custom_value' => 'Woodline'),
			array('id' => 'Farbe', 'value_id' => $colour, 'custom_value' => ''),
		),
	);
}

function ebay_listings_test_calls_only($calls)
{
	return array_column($calls, 'call');
}

/* ----------------------------------------------------------- pure parts */

function test_ebay_does_not_apply_is_worded_per_site()
{
	pg_assert_same('Does not apply', pg_ebay_does_not_apply('EBAY_US'));
	pg_assert_same('Does not apply', pg_ebay_does_not_apply('EBAY_GB'));
	pg_assert_same('Nicht zutreffend', pg_ebay_does_not_apply('EBAY_DE'));
	pg_assert_same('Non applicable', pg_ebay_does_not_apply('EBAY_FR'));
	pg_assert_same('Nie dotyczy', pg_ebay_does_not_apply('EBAY_PL'));
}

function test_ebay_identifier_from_the_barcode_or_the_site_text()
{
	pg_assert_same(array('upc' => array('036000291452')), pg_ebay_identifier('036000291452', 'EBAY_DE'));
	pg_assert_same(array('ean' => array('4006381333931')), pg_ebay_identifier(' 4006381333931 ', 'EBAY_US'));
	pg_assert_same(array('ean' => array('96385074')), pg_ebay_identifier('96385074', 'EBAY_DE'));
	pg_assert_same(array('ean' => array('Nicht zutreffend')), pg_ebay_identifier('', 'EBAY_DE'));
	pg_assert_same(array('upc' => array('Does not apply')), pg_ebay_identifier('', 'EBAY_US'));
	pg_assert_same(array('ean' => array('Does not apply')), pg_ebay_identifier('ABC-12', 'EBAY_GB'), 'a code that is no GTIN is no identifier');
}

function test_ebay_aspects_take_the_value_text_or_the_free_answer()
{
	pg_assert_same(array('Marke' => array('Woodline'), 'Farbe' => array('Rot')), pg_ebay_aspects(ebay_listings_test_item()['attributes']));
	pg_assert_same(array(), pg_ebay_aspects(array(array('id' => 'Farbe', 'value_id' => '', 'custom_value' => ' '))));
}

function test_ebay_offer_price_is_converted_and_rounded_to_the_cent()
{
	pg_assert_same('39.90', pg_ebay_offer_price(39.9, 1.0));
	pg_assert_same('19.99', pg_ebay_offer_price(19.99, 1));
	pg_assert_same('1.15', pg_ebay_offer_price(100.0, 0.0115), '10000 x 0.0115 = 115');
	pg_assert_same('0.03', pg_ebay_offer_price(1.0, 0.025), 'half a cent rounds away from zero');
	pg_assert_same('', pg_ebay_offer_price(10, null));
	pg_assert_same('', pg_ebay_offer_price(10, 0));
	pg_assert_same('', pg_ebay_offer_price(0, 1.0));
}

function test_ebay_group_key_is_the_group_name_or_a_stable_one()
{
	pg_assert_same('CHAIR', pg_ebay_group_key('CHAIR'));
	pg_assert_same(pg_ebay_group_key('Stuhl aus Holz'), pg_ebay_group_key('Stuhl aus Holz'));
	pg_assert_same(1, preg_match('/^pg-[0-9a-f]{24}$/', pg_ebay_group_key('Stuhl aus Holz')));
	pg_assert_same(1, preg_match('/^pg-/', pg_ebay_group_key(str_repeat('A', 51))));
}

function test_ebay_plain_description_has_no_tags_and_is_cut_at_a_word()
{
	pg_assert_same('Solid beech.', pg_ebay_plain_description('<p>Solid <b>beech</b>.</p>'));
	pg_assert_same('one two', pg_ebay_plain_description('<p>one</p><p>two</p>'));

	$long = pg_ebay_plain_description(str_repeat('word ', 1000));

	pg_assert_true(mb_strlen($long) <= 4000);
	pg_assert_same('word', mb_substr($long, -4));
}

function test_ebay_item_problems_are_found_before_any_call()
{
	pg_assert_same('', pg_ebay_item_problem(ebay_listings_test_item()));
	pg_assert_contains('50', pg_ebay_item_problem(ebay_listings_test_item(str_repeat('X', 51))));
	pg_assert_contains('80', pg_ebay_item_problem(ebay_listings_test_item('A', 'Rot', array('title' => str_repeat('t', 81)))));
	pg_assert_contains('https', pg_ebay_item_problem(ebay_listings_test_item('A', 'Rot', array('images' => array('http://shop.example/a.jpg')))));
	pg_assert_true(pg_ebay_item_problem(ebay_listings_test_item('A', 'Rot', array('title' => ''))) !== '');
}

function test_ebay_inventory_item_body()
{
	$body = pg_ebay_inventory_item(ebay_listings_test_item('A', 'Rot', array('barcode' => '')), 'EBAY_DE');

	pg_assert_same(array('quantity' => 4), $body['availability']['shipToLocationAvailability']);
	pg_assert_same('NEW', $body['condition']);
	pg_assert_same('Wooden chair Rot', $body['product']['title']);
	pg_assert_same('Solid beech.', $body['product']['description']);
	pg_assert_same(array('Marke' => array('Woodline'), 'Farbe' => array('Rot')), (array) $body['product']['aspects']);
	pg_assert_same(array('https://shop.example/A.jpg'), $body['product']['imageUrls']);
	pg_assert_same(array('Nicht zutreffend'), $body['product']['ean']);

	$many = pg_ebay_inventory_item(ebay_listings_test_item('A', 'Rot', array('images' => array_fill(0, 30, 'https://x/a.jpg'))), 'EBAY_DE');

	pg_assert_same(24, count($many['product']['imageUrls']));
}

function test_ebay_group_shares_the_common_aspects_and_varies_by_the_rest()
{
	$group = pg_ebay_inventory_group(array(ebay_listings_test_item('CHAIR-RED', 'Rot'), ebay_listings_test_item('CHAIR-BLUE', 'Blau')));

	pg_assert_true($group['ok']);
	pg_assert_same('Wooden chair', $group['body']['title']);
	pg_assert_same(array('Marke' => array('Woodline')), (array) $group['body']['aspects']);
	pg_assert_same(array('CHAIR-RED', 'CHAIR-BLUE'), $group['body']['variantSKUs']);
	pg_assert_same(array(array('name' => 'Farbe', 'values' => array('Rot', 'Blau'))), $group['body']['variesBy']['specifications']);
	pg_assert_same(array('https://shop.example/CHAIR-RED.jpg', 'https://shop.example/CHAIR-BLUE.jpg'), $group['body']['imageUrls']);

	$same = pg_ebay_inventory_group(array(ebay_listings_test_item('A', 'Rot'), ebay_listings_test_item('B', 'Rot')));

	pg_assert_false($same['ok'], 'variants that differ by nothing are refused');
}

function test_ebay_offer_body()
{
	$body = pg_ebay_offer_body(ebay_listings_test_item(), 'EBAY_DE', array('location' => 'warehouse-1', 'fulfillment_policy' => 'f', 'payment_policy' => 'p', 'return_policy' => 'r'), '39.90');

	pg_assert_same('CHAIR-RED', $body['sku']);
	pg_assert_same('EBAY_DE', $body['marketplaceId']);
	pg_assert_same('FIXED_PRICE', $body['format']);
	pg_assert_same('63861', $body['categoryId']);
	pg_assert_same('<p>Solid <b>beech</b>.</p>', $body['listingDescription']);
	pg_assert_same('warehouse-1', $body['merchantLocationKey']);
	pg_assert_same(array('fulfillmentPolicyId' => 'f', 'paymentPolicyId' => 'p', 'returnPolicyId' => 'r'), $body['listingPolicies']);
	pg_assert_same(array('value' => '39.90', 'currency' => 'EUR'), $body['pricingSummary']['price']);
}

function test_ebay_listing_state_from_the_offer()
{
	$offer = pg_ebay_offer_of(ebay_listings_test_fixture('offers.json'), 'EBAY_DE');

	pg_assert_same(array('offer_id' => '5005317010', 'status' => 'PUBLISHED', 'listing_id' => '110554419321', 'listing_status' => 'ACTIVE'), $offer);
	pg_assert_same(array('state' => 'approved', 'content_id' => '110554419321', 'archived' => false), pg_ebay_listing_state($offer));
	pg_assert_same(null, pg_ebay_offer_of(ebay_listings_test_fixture('offers.json'), 'EBAY_FR'), 'another site\'s offer is not this one');
	pg_assert_same(array('state' => 'unknown'), pg_ebay_listing_state(pg_ebay_offer_of(ebay_listings_test_fixture('offers-unpublished.json'), 'EBAY_DE')));
	pg_assert_same(array('state' => 'unknown'), pg_ebay_listing_state(null));

	$offer['listing_status'] = 'ENDED';

	pg_assert_true(pg_ebay_listing_state($offer)['archived']);
}

function test_ebay_error_messages_are_joined()
{
	pg_assert_same('Invalid item condition information. Item specific Marke is missing.', pg_ebay_error_messages(ebay_listings_test_fixture('error-publish.json')));
	pg_assert_same('', pg_ebay_error_messages(null));
}

/* -------------------------------------------------------------- the connector */

function test_ebay_connector_names_listing_and_state()
{
	pg_assert_true(mp_can('ebay', 'create_listings'));
	pg_assert_true(mp_can('ebay', 'listing_status'));
}

function test_ebay_single_item_is_put_offered_and_published()
{
	$calls = array();
	$result = mp_ebay_create_listings(ebay_listings_test_account(), array(ebay_listings_test_item()), ebay_listings_test_call(array(
		'POST /sell/inventory/v1/offer/5005317099/publish' => array(200, 'publish.json'),
	), $calls), 1.0);

	pg_assert_true($result['ok']);
	pg_assert_same('', $result['data']['task_id']);
	pg_assert_same(array('CHAIR-RED' => array('ok' => true, 'error' => '')), $result['data']['results']);
	pg_assert_same(array(
		'PUT /sell/inventory/v1/inventory_item/CHAIR-RED',
		'GET /sell/inventory/v1/offer?sku=CHAIR-RED&marketplace_id=EBAY_DE',
		'POST /sell/inventory/v1/offer',
		'POST /sell/inventory/v1/offer/5005317099/publish',
	), ebay_listings_test_calls_only($calls));
	pg_assert_same('39.90', $calls[2]['body']['pricingSummary']['price']['value']);
}

function test_ebay_offer_already_there_is_updated_not_created_again()
{
	$calls = array();
	$result = mp_ebay_create_listings(ebay_listings_test_account(), array(ebay_listings_test_item()), ebay_listings_test_call(array(
		'GET /sell/inventory/v1/offer?sku=CHAIR-RED&marketplace_id=EBAY_DE' => array(200, 'offers.json'),
	), $calls), 0.5);

	pg_assert_true($result['ok']);
	pg_assert_same('PUT /sell/inventory/v1/offer/5005317010', $calls[2]['call']);
	pg_assert_same('19.95', $calls[2]['body']['pricingSummary']['price']['value'], 'converted at the rate');
	pg_assert_same('POST /sell/inventory/v1/offer/5005317010/publish', $calls[3]['call']);
}

function test_ebay_variants_are_one_group_published_together()
{
	$calls = array();
	$result = mp_ebay_create_listings(ebay_listings_test_account(), array(ebay_listings_test_item('CHAIR-RED', 'Rot'), ebay_listings_test_item('CHAIR-BLUE', 'Blau')),
		ebay_listings_test_call(array('POST /sell/inventory/v1/offer/publish_by_inventory_item_group' => array(200, 'publish.json')), $calls), 1.0);

	pg_assert_true($result['ok']);
	pg_assert_same(array('CHAIR-RED' => array('ok' => true, 'error' => ''), 'CHAIR-BLUE' => array('ok' => true, 'error' => '')), $result['data']['results']);
	pg_assert_same(array(
		'PUT /sell/inventory/v1/inventory_item/CHAIR-RED',
		'PUT /sell/inventory/v1/inventory_item/CHAIR-BLUE',
		'PUT /sell/inventory/v1/inventory_item_group/CHAIR',
		'GET /sell/inventory/v1/offer?sku=CHAIR-RED&marketplace_id=EBAY_DE',
		'POST /sell/inventory/v1/offer',
		'GET /sell/inventory/v1/offer?sku=CHAIR-BLUE&marketplace_id=EBAY_DE',
		'POST /sell/inventory/v1/offer',
		'POST /sell/inventory/v1/offer/publish_by_inventory_item_group',
	), ebay_listings_test_calls_only($calls));
	pg_assert_same(array('inventoryItemGroupKey' => 'CHAIR', 'marketplaceId' => 'EBAY_DE'), $calls[7]['body']);
}

function test_ebay_refused_publish_is_each_skus_verdict()
{
	$calls = array();
	$result = mp_ebay_create_listings(ebay_listings_test_account(), array(ebay_listings_test_item()), ebay_listings_test_call(array(
		'POST /sell/inventory/v1/offer/5005317099/publish' => array(400, 'error-publish.json'),
	), $calls), 1.0);

	pg_assert_true($result['ok']);
	pg_assert_false($result['data']['results']['CHAIR-RED']['ok']);
	pg_assert_same('eBay: Invalid item condition information. Item specific Marke is missing.', $result['data']['results']['CHAIR-RED']['error']);
}

function test_ebay_refused_variant_keeps_the_group_unpublished()
{
	$calls = array();
	$result = mp_ebay_create_listings(ebay_listings_test_account(), array(ebay_listings_test_item('CHAIR-RED', 'Rot'), ebay_listings_test_item('CHAIR-BLUE', 'Blau')),
		ebay_listings_test_call(array('PUT /sell/inventory/v1/inventory_item/CHAIR-BLUE' => array(400, 'error-publish.json')), $calls), 1.0);

	pg_assert_true($result['ok']);
	pg_assert_contains('Marke', $result['data']['results']['CHAIR-BLUE']['error']);
	pg_assert_false($result['data']['results']['CHAIR-RED']['ok']);
	pg_assert_false(in_array('POST /sell/inventory/v1/offer/publish_by_inventory_item_group', ebay_listings_test_calls_only($calls), true), 'nothing is published');
}

function test_ebay_fault_on_their_side_gives_the_article_back_to_the_queue()
{
	$calls = array();
	$result = mp_ebay_create_listings(ebay_listings_test_account(), array(ebay_listings_test_item()), ebay_listings_test_call(array(
		'PUT /sell/inventory/v1/inventory_item/CHAIR-RED' => array(500, 'error-system.json'),
	), $calls), 1.0);

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
	pg_assert_same(1, count($calls));
}

function test_ebay_article_is_refused_before_any_call()
{
	$calls = array();
	$call = ebay_listings_test_call(array(), $calls);

	$no_policy = mp_ebay_create_listings(ebay_listings_test_account(array('return_policy' => '')), array(ebay_listings_test_item()), $call, 1.0);

	pg_assert_false($no_policy['ok']);
	pg_assert_false($no_policy['retry']);

	$no_rate = mp_ebay_create_listings(ebay_listings_test_account(), array(ebay_listings_test_item()), $call, false);

	pg_assert_false($no_rate['ok'], 'no rate to the site\'s currency');
	pg_assert_contains('EUR', $no_rate['error']);

	$no_price = mp_ebay_create_listings(ebay_listings_test_account(), array(ebay_listings_test_item('A', 'Rot', array('price_major' => 0))), $call, 1.0);

	pg_assert_false($no_price['ok']);

	$long = mp_ebay_create_listings(ebay_listings_test_account(), array(ebay_listings_test_item(str_repeat('X', 51))), $call, 1.0);

	pg_assert_false($long['ok']);
	pg_assert_same(array(), $calls);
}

function test_ebay_listing_status_reads_each_skus_offer()
{
	$calls = array();
	$result = mp_ebay_listing_status(ebay_listings_test_account(), array('CHAIR-RED', 'CHAIR-BLUE', 'GONE'), ebay_listings_test_call(array(
		'GET /sell/inventory/v1/offer?sku=CHAIR-RED&marketplace_id=EBAY_DE'  => array(200, 'offers.json'),
		'GET /sell/inventory/v1/offer?sku=CHAIR-BLUE&marketplace_id=EBAY_DE' => array(200, 'offers-unpublished.json'),
	), $calls));

	pg_assert_true($result['ok']);
	pg_assert_same('approved', $result['data']['listings']['CHAIR-RED']['state']);
	pg_assert_same('110554419321', $result['data']['listings']['CHAIR-RED']['content_id']);
	pg_assert_same(array('state' => 'unknown'), $result['data']['listings']['CHAIR-BLUE']);
	pg_assert_same(array('state' => 'unknown'), $result['data']['listings']['GONE'], 'no offer there');

	$failed = mp_ebay_listing_status(ebay_listings_test_account(), array('CHAIR-RED'), ebay_listings_test_call(array(
		'GET /sell/inventory/v1/offer?sku=CHAIR-RED&marketplace_id=EBAY_DE' => array(503, 'error-system.json'),
	), $calls));

	pg_assert_false($failed['ok']);
	pg_assert_true($failed['retry']);
}
