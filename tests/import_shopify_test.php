<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Shopify reader (includes/import/shopify.php) on
 * tests/fixtures/import/shopify_products.csv and shopify_customers.csv:
 * rows grouped by handle, a variable product with options, a simple one, a
 * refused price, the cursor across requests, and customers as contacts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/shopify.php');

function pg_test_import_shopify_file($name)
{
	return dirname(__FILE__) . '/fixtures/import/shopify_' . $name . '.csv';
}

function pg_test_import_shopify_items($name, $cursor = null)
{
	$cursor = ($cursor !== null) ? $cursor : pg_import_shopify_open(pg_test_import_shopify_file($name));
	$items = array();

	while (($item = pg_import_shopify_next($cursor)) !== null) {
		$items[$item['source_key']] = $item;
	}

	return $items;
}

function test_import_shopify_detected()
{
	foreach (array('products', 'customers') as $name) {
		pg_assert_same('shopify', pg_import_detect_kind((string) file_get_contents(pg_test_import_shopify_file($name))), $name);
	}
}

// One item per handle, the picture-only and variant rows read with it.
function test_import_shopify_products_grouped_by_handle()
{
	pg_assert_same(array('shopify:product:linen-shirt', 'shopify:product:gift-mug', 'shopify:product:old-poster'), array_keys(pg_test_import_shopify_items('products')));
}

function test_import_shopify_variable_product()
{
	$data = pg_test_import_shopify_items('products')['shopify:product:linen-shirt']['data'];

	pg_assert_same('variable', $data['type'], 'type');
	pg_assert_same(array('Size', 'Colour'), $data['axes'], 'axes');
	pg_assert_same(3, count($data['variants']), 'variants');
	pg_assert_same(array('Size' => 'M', 'Colour' => 'Blue'), $data['variants'][2]['options'], 'options of the third');
	pg_assert_same(4990, $data['variants'][0]['price'], 'price');
	pg_assert_same(5990, $data['variants'][0]['compare_at'], 'compare at');
	pg_assert_same(null, $data['variants'][2]['compare_at'], 'no compare at');
	pg_assert_same(0, $data['variants'][2]['stock'], 'oversold is 0');
	pg_assert_same(array('https://cdn.example.com/files/shirt-blue.jpg'), $data['variants'][2]['images'], 'variant picture');
	pg_assert_same(array('https://cdn.example.com/files/shirt-front.jpg', 'https://cdn.example.com/files/shirt-back.jpg'), $data['images'], 'pictures in order');
	pg_assert_same(array(array('Shirts')), $data['categories'], 'type as category');
	pg_assert_same('summer, linen', $data['keywords'], 'tags');
	pg_assert_same('0.5512', $data['weight'], 'grams in pounds, the unit products.weight holds');
	pg_assert_true($data['enabled'], 'published');
}

function test_import_shopify_simple_and_refused()
{
	$items = pg_test_import_shopify_items('products');
	$mug = $items['shopify:product:gift-mug']['data'];
	$poster = $items['shopify:product:old-poster']['data'];

	pg_assert_same('simple', $mug['type'], 'Default Title is simple');
	pg_assert_same('MUG-1', $mug['sku'], 'sku');
	pg_assert_same(129900, $mug['price'], 'typed price');
	pg_assert_same(null, $mug['stock'], 'untracked');
	pg_assert_same('', $mug['error'], 'no error');

	pg_assert_false($poster['enabled'], 'draft');
	pg_assert_contains('abc', $poster['error'], 'refused price');
	pg_assert_same('old-poster-1', $poster['sku'], 'sku from the handle');
}

// A cursor saved after the first product and read back gives the rest.
function test_import_shopify_cursor_resumes()
{
	$cursor = pg_import_shopify_open(pg_test_import_shopify_file('products'));
	pg_import_shopify_next($cursor);
	$saved = json_decode(json_encode($cursor), true);

	pg_assert_same(array('shopify:product:gift-mug', 'shopify:product:old-poster'), array_keys(pg_test_import_shopify_items('products', $saved)));
}

function test_import_shopify_customers()
{
	$items = pg_test_import_shopify_items('customers');

	pg_assert_same(array('shopify:customer:7001', 'shopify:customer:7002', 'shopify:customer:7003'), array_keys($items), 'keys');

	$ayse = $items['shopify:customer:7001'];
	pg_assert_same('contact', $ayse['kind'], 'kind');
	pg_assert_same('Ayşe Demir', $ayse['title'], 'title');
	pg_assert_same('İstanbul', $ayse['data']['business_city'], 'city');
	pg_assert_same('TR', $ayse['data']['business_country'], 'country');
	pg_assert_true($ayse['data']['opt_in'], 'accepts marketing');
	pg_assert_same(array('vip', 'wholesale'), $ayse['data']['tags'], 'tags');

	pg_assert_same('john@example.com', $items['shopify:customer:7002']['data']['email_address'], 'address in lower case');
	pg_assert_false($items['shopify:customer:7002']['data']['opt_in'], 'no consent');
	pg_assert_same(array('title' => 'Ayşe Demir', 'detail' => 'ayse@example.com'), pg_import_shopify_plan($ayse), 'plan');
}

function test_import_shopify_weight()
{
	// products.weight is in pounds whatever unit the shop showed: before,
	// 1500 g in a kg shop was stored as 1.5 (lb), 250 g in a g shop as 250.
	pg_assert_same('3.3069', pg_import_shopify_weight(array('variant grams' => '1500', 'variant weight unit' => 'kg')), 'kg shop');
	pg_assert_same('0.5512', pg_import_shopify_weight(array('variant grams' => '250', 'variant weight unit' => 'g')), 'g shop');
	pg_assert_same('1', pg_import_shopify_weight(array('variant grams' => '453.59237', 'variant weight unit' => 'lb')), 'lb shop');
	pg_assert_same('0.0022', pg_import_shopify_weight(array('variant grams' => '1')), 'one gram');
	pg_assert_same('', pg_import_shopify_weight(array('variant grams' => '0')), 'none');
}

// The contact writer keeps known columns, cuts to the column, drops a bad address.
function test_import_shopify_contact_values()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/import/write_contact.php');

	$values = pg_import_contact_values(array('first_name' => ' Ayşe ', 'email_address' => 'not-an-address', 'business_zip_code' => '1234567890', 'user' => 9), array('business_zip_code' => 5));

	pg_assert_same('Ayşe', $values['first_name'], 'trimmed');
	pg_assert_same('', $values['email_address'], 'bad address dropped');
	pg_assert_same('12345', $values['business_zip_code'], 'cut to the column');
	pg_assert_false(isset($values['user']), 'unknown column left out');
}
