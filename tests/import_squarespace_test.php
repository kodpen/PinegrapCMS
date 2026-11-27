<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Squarespace reader (includes/import/squarespace.php) on
 * tests/fixtures/import/squarespace_products.csv and squarespace_customers.csv,
 * files built from the columns Squarespace documents for its exports: a
 * product's variant rows read with it, sale prices, the two old addresses,
 * a weight without a unit, a refused price, the cursor across requests, and
 * customers as contacts that are not subscribed.
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
require_once(PG_FUNCTIONS_DIR . '/includes/import/squarespace.php');

function pg_test_import_squarespace_file($name)
{
	return dirname(__FILE__) . '/fixtures/import/squarespace_' . $name . '.csv';
}

function pg_test_import_squarespace_items($name, $cursor = null)
{
	$cursor = ($cursor !== null) ? $cursor : pg_import_squarespace_open(pg_test_import_squarespace_file($name));
	$items = array();

	while (($item = pg_import_squarespace_next($cursor)) !== null) {
		$items[$item['source_key']] = $item;
	}

	return $items;
}

function test_import_squarespace_columns_and_file_kind()
{
	pg_assert_same('product id', pg_import_squarespace_column('Product ID [Non Editable]'), 'note dropped');
	pg_assert_same('hosted image urls', pg_import_squarespace_column(' Hosted  Image URLs '), 'spaces');
	pg_assert_same('products', pg_import_squarespace_open(pg_test_import_squarespace_file('products'))['kind'], 'products');
	pg_assert_same('customers', pg_import_squarespace_open(pg_test_import_squarespace_file('customers'))['kind'], 'customers');
	pg_assert_same('', pg_import_squarespace_file_kind(array('handle', 'title')), 'a Shopify header is not one');
}

// The rows below a product's first row, with or without its id, are its variants.
function test_import_squarespace_products_grouped()
{
	pg_assert_same(
		array('squarespace:product:5f1a0001', 'squarespace:product:5f1a0002', 'squarespace:product:5f1a0003', 'squarespace:product:5f1a0004'),
		array_keys(pg_test_import_squarespace_items('products'))
	);
}

function test_import_squarespace_variable_product()
{
	$item = pg_test_import_squarespace_items('products')['squarespace:product:5f1a0001'];
	$data = $item['data'];

	pg_assert_same('Linen Shirt', $item['title'], 'title');
	pg_assert_same('variable', $data['type'], 'type');
	pg_assert_same('linen-shirt', $data['sku'], 'set named by its URL');
	pg_assert_same(array('Size'), $data['axes'], 'axes');
	pg_assert_same(3, count($data['variants']), 'variants');
	pg_assert_same(array('Size' => 'L'), $data['variants'][2]['options'], 'options of the third');
	pg_assert_same('Linen Shirt - M', $data['variants'][1]['name'], 'variant name');
	pg_assert_same('SQ-SHIRT-S', $data['variants'][0]['sku'], 'sku');
	pg_assert_same(3990, $data['variants'][0]['price'], 'sale price is the price');
	pg_assert_same(4990, $data['variants'][0]['compare_at'], 'price is the compare at');
	pg_assert_same(5200, $data['variants'][2]['price'], 'not on sale');
	pg_assert_same(null, $data['variants'][2]['compare_at'], 'no compare at');
	pg_assert_same(5, $data['variants'][0]['stock'], 'stock');
	pg_assert_same(null, $data['variants'][1]['stock'], 'unlimited is untracked');
	pg_assert_same(0, $data['variants'][2]['stock'], 'none left');
	pg_assert_same(array(array('Clothing'), array('Clothing', 'Summer shirts')), $data['categories'], 'categories');
	pg_assert_same('summer, linen', $data['keywords'], 'tags');
	pg_assert_same(array('https://images.example.com/shirt-front.jpg', 'https://images.example.com/shirt-back.jpg'), $data['images'], 'pictures');
	pg_assert_same("<p>A soft shirt.</p>\n<p>Made of linen &amp; cotton.</p>", $data['description'], 'text as paragraphs');
	pg_assert_same('0.25', $data['weight_figure'], 'weight as written');
	pg_assert_same('', $data['weight'], 'no pounds before the unit is known');
	pg_assert_same(array('/shop/linen-shirt', '/shop/p/linen-shirt'), $data['addresses'], 'both old addresses');
	pg_assert_true($data['enabled'], 'visible');
	pg_assert_true($data['shippable'], 'physical');
}

function test_import_squarespace_simple_service_and_refused()
{
	$items = pg_test_import_squarespace_items('products');
	$mug = $items['squarespace:product:5f1a0002']['data'];
	$service = $items['squarespace:product:5f1a0003']['data'];
	$poster = $items['squarespace:product:5f1a0004']['data'];

	pg_assert_same('simple', $mug['type'], 'no options is simple');
	pg_assert_same('SQ-MUG', $mug['sku'], 'sku');
	pg_assert_same(1200, $mug['price'], 'price');
	pg_assert_same(null, $mug['stock'], 'untracked');
	pg_assert_same('1.5', $mug['weight_figure'], 'decimal comma');
	pg_assert_same('<p>Holds <strong>tea</strong>.</p>', $mug['description'], 'HTML kept');
	pg_assert_false($mug['enabled'], 'hidden');
	pg_assert_same('', $mug['error'], 'no error');

	pg_assert_same('simple', $service['type'], 'a service is a product');
	pg_assert_false($service['shippable'], 'not shipped');
	pg_assert_same('consultation', $service['sku'], 'sku from the URL');
	pg_assert_same(8000, $service['price'], 'whole price');
	pg_assert_same(array('/services/consultation', '/services/p/consultation'), $service['addresses'], 'its page');

	pg_assert_contains('abc', $poster['error'], 'refused price');
}

// A digital product is named by its type, which the product writer skips.
function test_import_squarespace_other_types()
{
	$item = pg_import_squarespace_product(array(array('product id' => 'd1', 'product type' => 'Digital', 'product page' => 'shop', 'product url' => 'ebook', 'title' => 'E-book', 'price' => '5')), 2);

	pg_assert_same('digital', $item['data']['type'], 'type');
	pg_assert_same('digital', $item['data']['source_type'], 'named in the reason');
}

// A cursor saved after the first product and read back gives the rest.
function test_import_squarespace_cursor_resumes()
{
	$cursor = pg_import_squarespace_open(pg_test_import_squarespace_file('products'));
	pg_import_squarespace_next($cursor);
	$saved = json_decode(json_encode($cursor), true);

	pg_assert_same(array('squarespace:product:5f1a0002', 'squarespace:product:5f1a0003', 'squarespace:product:5f1a0004'), array_keys(pg_test_import_squarespace_items('products', $saved)));
}

function test_import_squarespace_customers()
{
	$items = pg_test_import_squarespace_items('customers');

	pg_assert_same(array('squarespace:email:ayse@example.com', 'squarespace:email:john@example.com', 'squarespace:line:4'), array_keys($items), 'keys');

	$ayse = $items['squarespace:email:ayse@example.com'];
	pg_assert_same('contact', $ayse['kind'], 'kind');
	pg_assert_same('Ayşe Demir', $ayse['title'], 'title');
	pg_assert_same('ayse@example.com', $ayse['data']['email_address'], 'address in lower case');
	pg_assert_same('Demir Ltd, Bağdat Cd. 10, İstanbul 34730, TR', $ayse['data']['description'], 'billing first, kept whole');
	pg_assert_same(array('vip', 'wholesale'), $ayse['data']['tags'], 'tags');
	pg_assert_false($ayse['data']['opt_in'], 'no consent column');
	pg_assert_false($items['squarespace:email:john@example.com']['data']['opt_in'], 'never subscribed');
	pg_assert_same(array('title' => 'Ayşe Demir', 'detail' => 'ayse@example.com'), pg_import_squarespace_plan($ayse), 'plan');
}

// A file with a column per part fills the contact's own columns.
function test_import_squarespace_customer_split_address()
{
	$item = pg_import_squarespace_customer(array(
		'first name' => 'Ana', 'last name' => 'Lima', 'email' => 'ana@example.com',
		'shipping address' => 'Rua 1', 'shipping city' => 'Porto', 'shipping zip' => '4000', 'shipping country' => 'PT', 'shipping phone' => '+351 1',
		'billing address' => '', 'billing city' => '',
	), 2);

	pg_assert_same('Rua 1', $item['data']['business_address_1'], 'address');
	pg_assert_same('Porto', $item['data']['business_city'], 'city');
	pg_assert_same('4000', $item['data']['business_zip_code'], 'zip');
	pg_assert_same('PT', $item['data']['business_country'], 'country');
	pg_assert_same('+351 1', $item['data']['business_phone'], 'phone');
	pg_assert_same('', $item['data']['description'], 'nothing whole');
}

// The writer turns the figure into pounds with the unit the plan step asked.
function test_import_squarespace_weight_unit()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/import/write_product.php');

	$data = array('weight' => '', 'weight_figure' => '1.5');

	pg_assert_same('3.3069', pg_import_product_weight($data, array('weight_unit' => 'kg')), 'kilograms');
	pg_assert_same('0.0938', pg_import_product_weight($data, array('weight_unit' => 'oz')), 'ounces');
	pg_assert_same('1.5', pg_import_product_weight($data, array()), 'pounds when none was asked');
	pg_assert_same('2', pg_import_product_weight(array('weight' => '2', 'weight_figure' => '9'), array('weight_unit' => 'kg')), 'a reader that knew the unit');
	pg_assert_same('', pg_import_product_weight(array('weight_figure' => ''), array('weight_unit' => 'kg')), 'none');
	pg_assert_same('lb', pg_import_squarespace_mapping()['weight_unit']['default'], 'pounds at first');
}

// A service product is written as one that is not shipped.
function test_import_squarespace_service_not_shipped()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/import/write_product.php');

	$service = pg_test_import_squarespace_items('products')['squarespace:product:5f1a0003']['data'];
	$mug = pg_test_import_squarespace_items('products')['squarespace:product:5f1a0002']['data'];

	pg_assert_same('0', pg_import_product_row($service, 1)['shippable'], 'service');
	pg_assert_same('1', pg_import_product_row($mug, 1)['shippable'], 'physical');
}

function pg_test_import_squarespace_site()
{
	return dirname(__FILE__) . '/fixtures/import/squarespace_site.xml';
}

// The site content is told from a WordPress export by the Squarespace
// addresses in its first bytes; the two CSVs by their header.
function test_import_squarespace_detected()
{
	$head = function ($file) {
		return (string) file_get_contents($file, false, null, 0, 8192);
	};

	pg_assert_same('squarespace', pg_import_detect_kind($head(pg_test_import_squarespace_site())), 'site content');
	pg_assert_same('wordpress', pg_import_detect_kind($head(dirname(__FILE__) . '/fixtures/import/wordpress.xml')), 'a WordPress export stays WordPress');
	pg_assert_same('squarespace', pg_import_detect_kind($head(pg_test_import_squarespace_file('products'))), 'products');
	pg_assert_same('squarespace', pg_import_detect_kind($head(pg_test_import_squarespace_file('customers'))), 'customers');
	pg_assert_same('squarespace', pg_import_detect_kind("First Name,Last Name,Email,Customer Since\nA,B,a@b.c,2026-01-01"), 'customers, the columns alone');
	pg_assert_true(isset(pg_import_kinds()['squarespace']), 'a kind of its own');
}

// The WordPress reader reads the site content: a page numbered 0, keys on the
// channel's host (the file has no base_site_url), a post at its old address,
// an attachment without its placeholder caption.
function test_import_squarespace_site_content()
{
	$cursor = pg_import_squarespace_open(pg_test_import_squarespace_site());
	pg_assert_same('xml', $cursor['format'], 'read as XML');

	$items = pg_test_import_squarespace_items('', $cursor);

	pg_assert_same(array('pine-studio.example:author:owner@pine-studio.example', 'pine-studio.example:post:0', 'pine-studio.example:post:1', 'pine-studio.example:post:2'), array_keys($items), 'keys');

	$page = $items['pine-studio.example:post:0'];
	pg_assert_same('page', $page['kind'], 'page numbered 0');
	pg_assert_same('/about-us', $page['data']['link'], 'page address');

	$file = $items['pine-studio.example:post:1'];
	pg_assert_same('file', $file['kind'], 'attachment');
	pg_assert_same('', $file['data']['caption'], 'placeholder caption dropped');

	$post = $items['pine-studio.example:post:2'];
	pg_assert_same('record', $post['kind'], 'post');
	pg_assert_same('/blog/2026/3/14/opening-day', $post['data']['link'], 'blog page slug, then the post slug');
	pg_assert_same(array('title' => 'Opening Day', 'detail' => 'post, Deniz Kaya'), pg_import_squarespace_plan($post), 'plan');
}

// The XML cursor saved between requests and read back gives the rest.
function test_import_squarespace_site_cursor_resumes()
{
	$cursor = pg_import_squarespace_open(pg_test_import_squarespace_site());
	pg_import_squarespace_next($cursor);
	pg_import_squarespace_next($cursor);
	$saved = json_decode(json_encode($cursor), true);

	pg_assert_same(array('pine-studio.example:post:1', 'pine-studio.example:post:2'), array_keys(pg_test_import_squarespace_items('', $saved)));
}

// Each file asks its own plan rows.
function test_import_squarespace_mapping_by_file()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/import/wordpress.php');

	pg_assert_same(array_keys(pg_import_wordpress_mapping()), array_keys(pg_import_squarespace_mapping(pg_test_import_squarespace_site())), 'site content asks what WordPress asks');
	pg_assert_same(array(), pg_import_squarespace_mapping(pg_test_import_squarespace_file('customers')), 'customers ask nothing');

	$rows = pg_import_squarespace_mapping(pg_test_import_squarespace_file('products'));

	pg_assert_same(array('product_group', 'file_folder', 'weight_unit'), array_keys($rows), 'products');
	pg_assert_same('lb', $rows['weight_unit']['default'], 'pounds at first');
}
