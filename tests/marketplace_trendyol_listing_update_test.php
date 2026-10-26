<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for updating approved Trendyol listings by content id
 * (mp_trendyol_update_listings() and its body, the batch result named by
 * content id, includes/api/outbound/connectors/trendyol.php), the content an
 * update sends (mp_listing_update_item(), includes/api/outbound/listings.php)
 * and the commission the approval read keeps (mp_sync_listing_row_update(),
 * includes/api/outbound/sync.php).
 *
 * Trendyol is never called: the transport here answers in the shape of their
 * documentation (approved product content update, October 2026), not recorded
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/listings.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function listing_update_test_account()
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

function listing_update_test_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

function listing_update_test_item($overrides = array())
{
	return array_merge(array(
		'content_id'  => '1234567',
		'product_id'  => 501,
		'name'        => 'SHIRT-RED-M',
		'title'       => 'Red shirt',
		'description' => '<p>Cotton.</p>',
		'images'      => array('https://shop.example/a.jpg', 'https://shop.example/b.jpg'),
	), $overrides);
}

/* ----------------------------------------------------------------- the body */

function test_marketplace_trendyol_listing_update_body_carries_content_only()
{
	$built = mp_trendyol_update_body(array(listing_update_test_item()));

	pg_assert_same(array(), $built['refused']);
	pg_assert_same(array(array(
		'contentId'   => 1234567,
		'title'       => 'Red shirt',
		'description' => '<p>Cotton.</p>',
		'images'      => array(array('url' => 'https://shop.example/a.jpg'), array('url' => 'https://shop.example/b.jpg')),
	)), $built['items'], 'no barcode, price, stock, brand, category or attributes');
}

function test_marketplace_trendyol_listing_update_body_sends_one_item_per_content()
{
	$built = mp_trendyol_update_body(array(
		listing_update_test_item(),
		listing_update_test_item(array('product_id' => 502, 'name' => 'SHIRT-RED-L', 'title' => 'Other')),
		listing_update_test_item(array('content_id' => '7654321')),
	));

	pg_assert_same(2, count($built['items']));
	pg_assert_same('Red shirt', $built['items'][0]['title'], 'the first product speaks for its listing');
	pg_assert_same(7654321, $built['items'][1]['contentId']);
}

function test_marketplace_trendyol_listing_update_body_keeps_their_limits()
{
	$images = array();

	for ($i = 1; $i <= 10; $i++) {
		$images[] = 'https://shop.example/' . $i . '.jpg';
	}

	$built = mp_trendyol_update_body(array(listing_update_test_item(array('title' => str_repeat('ş', 120), 'images' => $images))));

	pg_assert_same(100, mb_strlen($built['items'][0]['title']), 'cut at 100 characters');
	pg_assert_same(8, count($built['items'][0]['images']));
}

function test_marketplace_trendyol_listing_update_body_refuses_per_listing()
{
	$built = mp_trendyol_update_body(array(
		listing_update_test_item(array('content_id' => '11', 'title' => '  ')),
		listing_update_test_item(array('content_id' => '12', 'description' => '')),
		listing_update_test_item(array('content_id' => '13', 'description' => str_repeat('a', 30001))),
		listing_update_test_item(array('content_id' => '14', 'images' => array('http://shop.example/a.jpg'))),
		listing_update_test_item(array('content_id' => '15', 'images' => array())),
		listing_update_test_item(array('content_id' => '')),
		listing_update_test_item(array('content_id' => '0123')),
		listing_update_test_item(array('content_id' => '1234567890123456789')),
		listing_update_test_item(array('content_id' => '16')),
	));

	pg_assert_same(array('11', '12', '13', '14', '15', '', '0123', '1234567890123456789'),
		array_map('strval', array_keys($built['refused'])));
	pg_assert_same(1, count($built['items']));
	pg_assert_same(16, $built['items'][0]['contentId']);
	pg_assert_contains('https', $built['refused']['14']);
}

/* ----------------------------------------------------------------- the call */

function test_marketplace_trendyol_listing_update_connector_names_the_call()
{
	pg_assert_true(mp_can('trendyol', 'update_listings'));
	pg_assert_false(mp_can('n11', 'update_listings'));
}

function test_marketplace_trendyol_listing_update_posts_the_content_update()
{
	$calls = array();

	$result = mp_trendyol_update_listings(listing_update_test_account(),
		array(listing_update_test_item(), listing_update_test_item(array('content_id' => '99', 'images' => array()))),
		listing_update_test_transport(200, '{"batchRequestId":"b1f2-77"}', $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('b1f2-77', $result['data']['task_id']);
	pg_assert_same(1, $result['data']['sent']);
	pg_assert_same(array('99'), array_map('strval', array_keys($result['data']['refused'])));

	pg_assert_same(1, count($calls));
	pg_assert_same('POST', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/product/sellers/123456/products/content-bulk-update', $calls[0]['url']);

	$sent = json_decode($calls[0]['options']['body'], true);

	pg_assert_same(1234567, $sent['items'][0]['contentId']);
	pg_assert_false(isset($sent['items'][0]['attributes']), 'attributes are never sent');
}

function test_marketplace_trendyol_listing_update_with_nothing_left_makes_no_call()
{
	$calls = array();

	$result = mp_trendyol_update_listings(listing_update_test_account(),
		array(listing_update_test_item(array('title' => ''))), listing_update_test_transport(200, '{}', $calls));

	pg_assert_true($result['ok']);
	pg_assert_same('', $result['data']['task_id']);
	pg_assert_same(0, count($calls));
	pg_assert_same(1, count($result['data']['refused']));
}

function test_marketplace_trendyol_listing_update_without_a_batch_id_is_retried()
{
	$calls = array();

	$result = mp_trendyol_update_listings(listing_update_test_account(), array(listing_update_test_item()),
		listing_update_test_transport(200, '{}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
}

function test_marketplace_trendyol_listing_update_batch_result_is_named_by_content_id()
{
	$read = mp_trendyol_batch_results(array(
		'batchRequestId' => 'b1f2-77',
		'status'         => 'COMPLETED',
		'items'          => array(
			array('requestItem' => array('contentId' => 1234567, 'title' => 'Red shirt'), 'status' => 'SUCCESS', 'failureReasons' => array()),
			array('requestItem' => array('contentId' => 7654321), 'status' => 'FAILED', 'failureReasons' => array('Görsel bulunamadı')),
		),
	));

	pg_assert_true($read['finished']);
	pg_assert_true($read['results']['1234567']['ok']);
	pg_assert_false($read['results']['7654321']['ok']);
	pg_assert_same('Görsel bulunamadı', $read['results']['7654321']['error']);
}

/* --------------------------------------------------------- the content sent */

function test_marketplace_listing_update_item_follows_the_create_choices()
{
	$row = array('remote_id' => '1234567', 'product_id' => '501', 'name' => 'SHIRT-RED-M', 'title' => 'Red shirt',
		'full_description' => '<p>Own</p>', 'short_description' => 'Short', 'group_title' => 'Shirts',
		'group_description' => '<p>Article</p>');

	$item = mp_listing_update_item($row, array('https://shop.example/a.jpg'));

	pg_assert_same('1234567', $item['content_id']);
	pg_assert_same(501, $item['product_id']);
	pg_assert_same('Red shirt', $item['title']);
	pg_assert_same('<p>Own</p>', $item['description']);
	pg_assert_same(array('https://shop.example/a.jpg'), $item['images']);

	$item = mp_listing_update_item(array_merge($row, array('title' => '', 'full_description' => '')), array());

	pg_assert_same('Shirts', $item['title'], 'the article\'s title');
	pg_assert_same('<p>Article</p>', $item['description'], 'the article\'s description');

	$item = mp_listing_update_item(array_merge($row, array('full_description' => '', 'group_description' => null)), array());

	pg_assert_same('Short', $item['description'], 'the short description last');
}

/* ------------------------------------------------------------- commission */

function listing_update_test_row($overrides = array())
{
	return array_merge(array(
		'id'          => 31,
		'product_id'  => 501,
		'remote_code' => '8680000000011',
		'remote_id'   => '',
		'status'      => 'active',
		'last_error'  => '',
		'commission'  => null,
	), $overrides);
}

function test_marketplace_trendyol_approval_keeps_the_commission()
{
	$update = mp_sync_listing_row_update(listing_update_test_row(),
		array('state' => 'approved', 'content_id' => '1234567', 'commission' => 7.83), 'Trendyol');

	pg_assert_same('1234567', $update['remote_id']);
	pg_assert_same('7.83', $update['commission']);

	$update = mp_sync_listing_row_update(listing_update_test_row(),
		array('state' => 'approved', 'content_id' => '1234567', 'commission' => 0), 'Trendyol');

	pg_assert_same('0.00', $update['commission'], 'zero is a rate, not a missing one');
}

function test_marketplace_trendyol_approval_without_a_usable_commission_writes_none()
{
	foreach (array(null, 'abc', -1, 10000) as $commission) {

		$update = mp_sync_listing_row_update(listing_update_test_row(),
			array('state' => 'approved', 'content_id' => '1234567', 'commission' => $commission), 'Trendyol');

		pg_assert_false(array_key_exists('commission', $update), var_export($commission, true));
	}

	$row = listing_update_test_row();

	unset($row['commission']);

	$update = mp_sync_listing_row_update($row,
		array('state' => 'approved', 'content_id' => '1234567', 'commission' => 7.83), 'Trendyol');

	pg_assert_false(array_key_exists('commission', $update), 'a row read before the upgrade step has no column to write');
	pg_assert_same('1234567', $update['remote_id']);
}
