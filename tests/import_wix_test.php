<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Wix's products and contacts files (includes/import/wix.php) on
 * tests/fixtures/import/wix_products.csv (the current layout, keyed by
 * handle), wix_products_v1.csv (the previous one, keyed by handleId) and
 * wix_contacts.csv, files built from the columns Wix's help pages name:
 * how each file is told, variants and media rows read with their product,
 * prices in minor units, discounts, brand, barcode and cost, and the
 * contacts' mapping guessed from the headings.
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
require_once(PG_FUNCTIONS_DIR . '/includes/import/wix.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/write_product.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/write_contact.php');

function pg_test_import_wix_file($name)
{
	return dirname(__FILE__) . '/fixtures/import/wix_' . $name . '.csv';
}

function pg_test_import_wix_items($name, $cursor = null)
{
	$cursor = ($cursor !== null) ? $cursor : pg_import_wix_open(pg_test_import_wix_file($name));
	$items = array();

	while (($item = pg_import_wix_next($cursor)) !== null) {
		$items += array($item['source_key'] => $item);
	}

	return $items;
}

function test_import_wix_detect_and_file_kind()
{
	$head = function ($name) {
		return (string) file_get_contents(pg_test_import_wix_file($name), false, null, 0, 8192);
	};

	pg_assert_same('wix', pg_import_detect_kind($head('products')), 'products');
	pg_assert_same('wix', pg_import_detect_kind($head('products_v1')), 'previous products layout');
	pg_assert_same('wix', pg_import_detect_kind($head('contacts')), 'contacts');
	pg_assert_same('wix', pg_import_detect_kind($head('collection')), 'collection still');
	pg_assert_same('shopify', pg_import_detect_kind("Handle,Body (HTML),Title\n"), 'Shopify stays');
	pg_assert_same('', pg_import_detect_kind("First Name,Last Name,Email\n"), 'names alone are no Wix file');
	pg_assert_same('products', pg_import_wix_open(pg_test_import_wix_file('products'))['wix_file'], 'products cursor');
	pg_assert_same('products', pg_import_wix_open(pg_test_import_wix_file('products_v1'))['wix_file'], 'previous layout cursor');
	pg_assert_same('contacts', pg_import_wix_open(pg_test_import_wix_file('contacts'))['wix_file'], 'contacts cursor');
	pg_assert_false(isset(pg_import_wix_open(pg_test_import_wix_file('collection'))['wix_file']), 'a collection keeps its cursor');
	pg_assert_same('productoptionname1', pg_import_wix_column(' productOptionName[1] '), 'brackets dropped');
	pg_assert_same('address1street', pg_import_wix_column('Address 1 - Street'), 'spaces and dashes dropped');
}

function test_import_wix_mapping_by_file()
{
	pg_assert_same(array('product_group', 'file_folder', 'weight_unit'), array_keys(pg_import_wix_mapping(pg_test_import_wix_file('products'))), 'products');
	pg_assert_true(isset(pg_import_wix_mapping(pg_test_import_wix_file('collection'))['form']), 'collection keeps its rows');

	$rows = pg_import_wix_mapping(pg_test_import_wix_file('contacts'));

	pg_assert_same('select', $rows['contact_email_address']['type'], 'a select');
	pg_assert_same('Email 1', $rows['contact_email_address']['choices']['col_2'], 'headings offered');
	pg_assert_same('col_2', $rows['contact_email_address']['default'], 'e-mail guessed');
	pg_assert_same('col_0', $rows['contact_first_name']['default'], 'first name');
	pg_assert_same('col_3', $rows['contact_mobile_phone']['default'], 'phone');
	pg_assert_same('col_6', $rows['contact_business_address_1']['default'], 'street');
	pg_assert_same('col_8', $rows['contact_business_state']['default'], 'state');
	pg_assert_same('col_11', $rows['contact_tags']['default'], 'labels');
	pg_assert_same('col_12', $rows['contact_opt_in']['default'], 'subscription status, not the e-mail');
	pg_assert_same('', $rows['contact_description']['default'], 'nothing guessed');
}

function test_import_wix_contact_guess()
{
	$header = array('Given Name', 'Family Name', 'E-mail 1 - Value', 'Email 1 Label', 'Phone 1 - Value', 'Organization 1 - Name', 'Email subscription status', 'Tags', 'Notes');
	$guess = pg_import_wix_contact_guess($header);

	pg_assert_same('col_0', $guess['first_name'], 'given name');
	pg_assert_same('col_1', $guess['last_name'], 'family name');
	pg_assert_same('col_2', $guess['email_address'], 'the address, not its label');
	pg_assert_same('col_4', $guess['mobile_phone'], 'phone value');
	pg_assert_same('col_5', $guess['company'], 'organisation');
	pg_assert_same('col_6', $guess['opt_in'], 'status');
	pg_assert_same('col_7', $guess['tags'], 'tags');
	pg_assert_same('col_8', $guess['description'], 'notes');
}

function test_import_wix_contacts()
{
	$items = pg_test_import_wix_items('contacts');

	pg_assert_same(array('wix:email:ayse@example.com', 'wix:email:john@example.com', 'wix:line:4'), array_keys($items), 'keys');

	$ayse = $items['wix:email:ayse@example.com'];

	pg_assert_same('contact', $ayse['kind'], 'kind');
	pg_assert_same('Ayşe Yılmaz', $ayse['title'], 'title');
	pg_assert_same('ayse@example.com', $ayse['data']['email_address'], 'address lower case');
	pg_assert_same('Acme', $ayse['data']['company'], 'company');
	pg_assert_same('Istanbul', $ayse['data']['business_city'], 'city');
	pg_assert_same('TR', $ayse['data']['business_country'], 'country');
	pg_assert_same('+90 555 111 2233', $ayse['data']['mobile_phone'], 'phone');
	pg_assert_true($ayse['data']['opt_in'], 'subscribed');
	pg_assert_same(array('VIP', 'Newsletter'), $ayse['data']['tags'], 'labels as tags');
	pg_assert_same('Ayşe', $ayse['data']['columns']['col_0'], 'cells kept for the mapping');

	pg_assert_false($items['wix:email:john@example.com']['data']['opt_in'], 'unsubscribed');
	pg_assert_false($items['wix:line:4']['data']['opt_in'], 'never subscribed');
	pg_assert_same('+1 555 0100', $items['wix:line:4']['title'], 'titled by the phone');
	pg_assert_same(array('title' => 'Ayşe Yılmaz', 'detail' => 'ayse@example.com'), pg_import_wix_plan($ayse), 'plan line');
}

// The writer reads the cells with the pairs the person chose.
function test_import_wix_contact_from_columns()
{
	$columns = array('col_0' => 'Ayşe', 'col_2' => 'AYSE@Example.com', 'col_11' => 'VIP; Newsletter', 'col_12' => 'Subscribed');

	$data = pg_import_contact_from_columns($columns, array('first_name' => 'col_0', 'email_address' => 'col_2', 'tags' => 'col_11', 'opt_in' => 'col_12'));

	pg_assert_same('Ayşe', $data['first_name'], 'first name');
	pg_assert_same('ayse@example.com', $data['email_address'], 'e-mail');
	pg_assert_same(array('VIP', 'Newsletter'), $data['tags'], 'semicolons split first');
	pg_assert_true($data['opt_in'], 'subscribed');

	$left = pg_import_contact_from_columns($columns, array('first_name' => 'col_0'));

	pg_assert_same('', $left['email_address'], 'a part left out stays empty');
	pg_assert_false($left['opt_in'], 'no status, no consent');
	pg_assert_same(array(), $left['tags'], 'no labels');

	pg_assert_same(
		array('first_name' => 'col_0', 'opt_in' => 'col_12'),
		pg_import_contact_pairs(array('contact_first_name' => 'col_0', 'contact_opt_in' => 'col_12', 'contact_nothing' => 'col_1', 'weight_unit' => 'kg')),
		'pairs from the mapping'
	);

	foreach (array('Subscribed' => true, 'SUBSCRIBED' => true, 'yes' => true, 'Unsubscribed' => false, 'Never subscribed' => false, 'Not set' => false, 'Pending' => false, '' => false) as $cell => $expected) {
		pg_assert_same($expected, pg_import_contact_subscribed($cell), 'status ' . $cell);
	}
}

function test_import_wix_products_grouped()
{
	pg_assert_same(array('wix:product:linen-tee', 'wix:product:stone-mug', 'wix:product:poster'), array_keys(pg_test_import_wix_items('products')));
	pg_assert_same(array('wix:product:product_1', 'wix:product:product_2'), array_keys(pg_test_import_wix_items('products_v1')));
}

function test_import_wix_variable_product()
{
	$item = pg_test_import_wix_items('products')['wix:product:linen-tee'];
	$data = $item['data'];

	pg_assert_same('Linen Tee', $item['title'], 'title');
	pg_assert_same('variable', $data['type'], 'type');
	pg_assert_same('linen-tee', $data['sku'], 'set named by its handle');
	pg_assert_same(array('Size', 'Color'), $data['axes'], 'axes');
	pg_assert_same(2, count($data['variants']), 'variants');
	pg_assert_same(array('Size' => 'S', 'Color' => 'White'), $data['variants'][0]['options'], 'swatch named by its label');
	pg_assert_same('Linen Tee - M / Black', $data['variants'][1]['name'], 'variant name');
	pg_assert_same('TEE-S-W', $data['variants'][0]['sku'], 'sku');
	pg_assert_same(2500, $data['variants'][0]['price'], 'price');
	pg_assert_same(3000, $data['variants'][0]['compare_at'], 'strikethrough price');
	pg_assert_same(2750, $data['variants'][1]['price'], 'own price');
	pg_assert_same(null, $data['variants'][1]['compare_at'], 'none');
	pg_assert_same(5, $data['variants'][0]['stock'], 'stock');
	pg_assert_same(0, $data['variants'][1]['stock'], 'out of stock');
	pg_assert_false($data['variants'][1]['enabled'], 'hidden variant');
	pg_assert_same('4006381333931', $data['variants'][0]['barcode'], 'variant barcode');
	pg_assert_same(950, $data['variants'][0]['cost'], 'variant cost');
	pg_assert_same(1000, $data['variants'][1]['cost'], 'own cost');
	pg_assert_same('Northwind', $data['brand'], 'brand');
	pg_assert_same(array(array('Shirts'), array('Summer')), $data['categories'], 'primary category first');
	pg_assert_same(
		array('https://static.wixstatic.com/media/abc123_tee~mv2.jpg', 'https://static.wixstatic.com/media/def456_back~mv2.jpg'),
		$data['images'],
		'product and media rows, a media id as its address'
	);
	pg_assert_same("<p>A light tee.</p>\n<p>Washed linen.</p>", $data['description'], 'text as paragraphs');
	pg_assert_same('0.2', $data['weight_figure'], 'weight of the first variant');
	pg_assert_same(array(), $data['addresses'], 'no old address');
	pg_assert_true($data['enabled'], 'enabled');
	pg_assert_true($data['shippable'], 'shipped');
	pg_assert_same(array('title' => 'Linen Tee', 'detail' => 'Variant set with 2 variants'), pg_import_wix_plan($item), 'plan line');
}

function test_import_wix_simple_products()
{
	$items = pg_test_import_wix_items('products');
	$mug = $items['wix:product:stone-mug']['data'];

	pg_assert_same('simple', $mug['type'], 'no options is simple');
	pg_assert_same('MUG-1', $mug['sku'], 'sku');
	pg_assert_same(1300, $mug['price'], 'rounded, not cut');
	pg_assert_same(null, $mug['stock'], 'in stock is untracked');
	pg_assert_same('5901234123457', $mug['barcode'], 'barcode');
	pg_assert_same(420, $mug['cost'], 'cost');
	pg_assert_same('Clayworks', $mug['brand'], 'brand');
	pg_assert_same('0.4', $mug['weight_figure'], 'weight');
	pg_assert_same('', $mug['error'], 'no error');
	pg_assert_same('The product has no price.', $items['wix:product:poster']['data']['error'], 'no price');
}

function test_import_wix_previous_layout()
{
	$items = pg_test_import_wix_items('products_v1');
	$scarf = $items['wix:product:product_1']['data'];
	$box = $items['wix:product:product_2']['data'];

	pg_assert_same('variable', $scarf['type'], 'variants');
	pg_assert_same(array('Colour'), $scarf['axes'], 'axis');
	pg_assert_same(3600, $scarf['variants'][0]['price'], '10 percent off');
	pg_assert_same(4000, $scarf['variants'][0]['compare_at'], 'the price before it');
	pg_assert_same(4050, $scarf['variants'][1]['price'], 'surcharge then discount');
	pg_assert_same(4500, $scarf['variants'][1]['compare_at'], 'with its surcharge');
	pg_assert_same(3, $scarf['variants'][0]['stock'], 'stock');
	pg_assert_same(0, $scarf['variants'][1]['stock'], 'out of stock');
	pg_assert_same(1500, $scarf['variants'][0]['cost'], 'the product cost');
	pg_assert_same(array(array('Winter'), array('Accessories')), $scarf['categories'], 'collections');
	pg_assert_same(
		array('https://static.wixstatic.com/media/a1b2c3_scarf~mv2.jpg', 'https://static.wixstatic.com/media/scarf2.jpg'),
		$scarf['images'],
		'pictures'
	);
	pg_assert_same('<p>Warm wool.</p>', $scarf['description'], 'HTML kept');

	pg_assert_same('simple', $box['type'], 'simple');
	pg_assert_same(1500, $box['price'], 'amount off');
	pg_assert_same(1999, $box['compare_at'], 'compare at');
	pg_assert_same(7, $box['stock'], 'stock');
	pg_assert_false($box['enabled'], 'hidden');
	pg_assert_same(800, $box['cost'], 'cost');
	pg_assert_same('', $box['barcode'], 'no barcode column');
}

function test_import_wix_discount()
{
	pg_assert_same(1500, pg_import_wix_discounted(1999, 'AMOUNT', '4.99'), 'amount');
	pg_assert_same(293, pg_import_wix_discounted(650, 'PERCENT', '55'), 'percent rounded on the exact figure');
	pg_assert_same(0, pg_import_wix_discounted(500, 'AMOUNT', '9'), 'never below zero');
	pg_assert_same(650, pg_import_wix_discounted(650, '', '10'), 'no mode');
	pg_assert_same(650, pg_import_wix_discounted(650, 'PERCENT', 'abc'), 'not a figure');
}

// The cursor carries a products file across requests.
function test_import_wix_cursor_resumes()
{
	$cursor = pg_import_wix_open(pg_test_import_wix_file('products'));
	$first = pg_import_wix_next($cursor);
	$cursor = json_decode(json_encode($cursor), true);
	$rest = pg_test_import_wix_items('products', $cursor);

	pg_assert_same('wix:product:linen-tee', $first['source_key'], 'first');
	pg_assert_same(array('wix:product:stone-mug', 'wix:product:poster'), array_keys($rest), 'the rest');
}

// The product writer's row takes the item's brand.
function test_import_wix_product_row_brand()
{
	$mug = pg_test_import_wix_items('products')['wix:product:stone-mug']['data'];
	$mug['weight'] = '';

	pg_assert_same('Clayworks', pg_import_product_row($mug, 1)['brand'], 'brand');
	pg_assert_same(100, mb_strlen(pg_import_product_row(array_merge($mug, array('brand' => str_repeat('b', 120))), 1)['brand']), 'cut to the column');
	pg_assert_same('', pg_import_product_row(array_merge($mug, array('brand' => null)), 1)['brand'], 'none');
}
