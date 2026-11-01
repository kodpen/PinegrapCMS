<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/merchant_center_push.php: the item
 * a product is pushed as, the product input with the account's switches, how
 * an answer is classified, the listing state read from a product, the account
 * credentials and the mapping difference against the feed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_merchant_push_item()
{
	$product = array('id' => '7', 'price' => '12990', 'enabled' => '1', 'out_of_stock' => '0',
		'inventory' => '1', 'inventory_quantity' => '3', 'backorder' => '0');

	pg_assert_same(array('id' => '7', 'price' => 12990, 'sale_price' => 9990, 'currency' => 'TRY', 'availability' => 'in_stock'),
		pg_merchant_push_item($product, 9990, 'try'));

	pg_assert_same(null, pg_merchant_push_item($product, 12990, 'TRY')['sale_price'], 'an offer that does not undercut is no sale price');
	pg_assert_same(null, pg_merchant_push_item($product, null, 'TRY')['sale_price']);

	$product['inventory_quantity'] = '0';
	pg_assert_same('out_of_stock', pg_merchant_push_item($product, null, 'TRY')['availability']);

	$product['backorder'] = '1';
	pg_assert_same('backorder', pg_merchant_push_item($product, null, 'TRY')['availability'], 'back orders keep selling, as in the feed');

	$product['inventory'] = '0';
	pg_assert_same('in_stock', pg_merchant_push_item($product, null, 'TRY')['availability'], 'an untracked product is in stock');

	$product['out_of_stock'] = '1';
	pg_assert_same('out_of_stock', pg_merchant_push_item($product, null, 'TRY')['availability'], 'flagged out of stock');

	$product['out_of_stock'] = '0';
	$product['enabled'] = '0';
	pg_assert_same('out_of_stock', pg_merchant_push_item($product, null, 'TRY')['availability'], 'switched off in the shop');
}

function test_merchant_push_input_switches()
{
	$item = array('id' => '7', 'price' => 12990, 'sale_price' => 9990, 'currency' => 'TRY', 'availability' => 'in_stock');

	$both = pg_merchant_push_input($item, 'tr-TR', 'TR', true, true);
	pg_assert_same(array('price', 'salePrice', 'availability'), array_keys($both['productAttributes']));
	pg_assert_same('7', $both['offerId']);
	pg_assert_same('tr', $both['contentLanguage']);

	$price_only = pg_merchant_push_input($item, 'tr', 'TR', true, false);
	pg_assert_same(array('price', 'salePrice'), array_keys($price_only['productAttributes']));

	$stock_only = pg_merchant_push_input($item, 'tr', 'TR', false, true);
	pg_assert_same(array('availability'), array_keys($stock_only['productAttributes']));
	pg_assert_same('IN_STOCK', $stock_only['productAttributes']['availability']);

	pg_assert_same(null, pg_merchant_push_input($item, 'tr', 'TR', false, false), 'nothing to say');
}

function test_merchant_push_verdict()
{
	pg_assert_same('ok', pg_merchant_push_verdict(200));
	pg_assert_same('refused', pg_merchant_push_verdict(400));
	pg_assert_same('refused', pg_merchant_push_verdict(404));
	pg_assert_same('later', pg_merchant_push_verdict(429));
	pg_assert_same('later', pg_merchant_push_verdict(408));
	pg_assert_same('later', pg_merchant_push_verdict(401), 'a token being renewed is tried again');
	pg_assert_same('later', pg_merchant_push_verdict(503));
	pg_assert_same('later', pg_merchant_push_verdict(0), 'no answer at all');
}

function test_merchant_listing_state()
{
	pg_assert_same(array('state' => 'unknown'), pg_merchant_listing_state(null, 'tr~TR~7'));

	pg_assert_same(array('state' => 'approved', 'content_id' => 'tr~TR~7'), pg_merchant_listing_state(array(
		'name'          => 'accounts/1/products/tr~TR~7',
		'productStatus' => array('itemLevelIssues' => array(
			array('code' => 'missing_gtin', 'severity' => 'DEMOTED', 'description' => 'Missing GTIN'),
		)),
	), 'tr~TR~7'), 'a demoted product still shows');

	pg_assert_same(array('state' => 'rejected', 'reasons' => array('Invalid image [image link]', 'Price mismatch')), pg_merchant_listing_state(array(
		'name'          => 'accounts/1/products/tr~TR~7',
		'productStatus' => array('itemLevelIssues' => array(
			array('code' => 'missing_gtin', 'severity' => 'DEMOTED', 'description' => 'Missing GTIN'),
			array('code' => 'image', 'severity' => 'DISAPPROVED', 'description' => 'Invalid image [image link]'),
			array('code' => 'price', 'severity' => 'DISAPPROVED', 'description' => 'Price mismatch'),
		)),
	), 'tr~TR~7'));
}

function test_merchant_account_credentials()
{
	$state = pg_merchant_state_parse(array('connection_id' => 4, 'account' => '123', 'country' => 'TR',
		'supplement' => 'accounts/123/dataSources/9'));

	pg_assert_same(array('connection_id' => 4, 'account' => '123', 'country' => 'TR'), pg_merchant_account_credentials($state));

	$state['supplement'] = '';
	pg_assert_same(null, pg_merchant_account_credentials($state), 'no update source, nothing to push to');

	pg_assert_same(null, pg_merchant_account_credentials(pg_merchant_state_parse(null)));
}

function test_merchant_map_diff()
{
	pg_assert_same(array('add' => array(3, 9), 'remove' => array(5)),
		pg_merchant_map_diff(array(1, 5, 7), array(1, 3, 7, 9, 3)));

	pg_assert_same(array('add' => array(), 'remove' => array()), pg_merchant_map_diff(array(2), array('2')));
	pg_assert_same(array('add' => array(), 'remove' => array(1, 2)), pg_merchant_map_diff(array(1, 2), array()));
}
