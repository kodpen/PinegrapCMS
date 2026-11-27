<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Webflow's products and 301 redirects files (includes/import/
 * webflow.php) on tests/fixtures/import/webflow_products.csv and
 * webflow_redirects.csv, files built from the columns of Webflow's import
 * template: a product's variant rows read with it, prices in minor units,
 * types that are skipped, the old address, the cursor across requests, and
 * redirects with a wildcard line named as skipped.
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
require_once(PG_FUNCTIONS_DIR . '/includes/import/webflow.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/write_redirect.php');

function pg_test_import_webflow_file($name)
{
	return dirname(__FILE__) . '/fixtures/import/webflow_' . $name . '.csv';
}

function pg_test_import_webflow_items($name, $cursor = null)
{
	$cursor = ($cursor !== null) ? $cursor : pg_import_webflow_open(pg_test_import_webflow_file($name));
	$items = array();

	// A key read twice is written once: the run skips the second.
	while (($item = pg_import_webflow_next($cursor)) !== null) {
		$items += array($item['source_key'] => $item);
	}

	return $items;
}

function test_import_webflow_detect_and_file_kind()
{
	$head = function ($name) {
		return (string) file_get_contents(pg_test_import_webflow_file($name), false, null, 0, 8192);
	};

	pg_assert_same('webflow', pg_import_detect_kind($head('products')), 'products');
	pg_assert_same('webflow', pg_import_detect_kind($head('redirects')), 'redirects');
	pg_assert_same('webflow', pg_import_detect_kind("/old,/new\n"), 'redirects without a header');
	pg_assert_same('', pg_import_detect_kind("/old,/new,/third\n"), 'three paths are not one');
	pg_assert_same('webflow', pg_import_detect_kind($head('collection')), 'collection still');
	pg_assert_same('products', pg_import_webflow_open(pg_test_import_webflow_file('products'))['webflow_file'], 'products cursor');
	pg_assert_same('redirects', pg_import_webflow_open(pg_test_import_webflow_file('redirects'))['webflow_file'], 'redirects cursor');
	pg_assert_same('', pg_import_webflow_file_kind(array('handle', 'body (html)')), 'a Shopify header is not one');
	pg_assert_same('redirects', pg_import_webflow_file_kind(array('/a', 'https://example.com/b')), 'a first line of paths');
}

function test_import_webflow_mapping_by_file()
{
	pg_assert_same(array('product_group', 'file_folder', 'weight_unit'), array_keys(pg_import_webflow_mapping(pg_test_import_webflow_file('products'))), 'products');
	pg_assert_same(array(), pg_import_webflow_mapping(pg_test_import_webflow_file('redirects')), 'redirects ask nothing');
	pg_assert_true(isset(pg_import_webflow_mapping(pg_test_import_webflow_file('collection'))['address_base']), 'collection keeps its rows');
}

// Rows with one handle are one product, each row a variant.
function test_import_webflow_products_grouped()
{
	pg_assert_same(
		array('webflow:product:cotton-tee', 'webflow:product:stone-mug', 'webflow:product:garden-visit', 'webflow:product:plant-guide', 'webflow:product:gift-box', 'webflow:product:broken-price'),
		array_keys(pg_test_import_webflow_items('products'))
	);
}

function test_import_webflow_variable_product()
{
	$item = pg_test_import_webflow_items('products')['webflow:product:cotton-tee'];
	$data = $item['data'];

	pg_assert_same('product', $item['kind'], 'kind');
	pg_assert_same('Cotton Tee', $item['title'], 'title');
	pg_assert_same('variable', $data['type'], 'type');
	pg_assert_same('cotton-tee', $data['sku'], 'set named by its handle');
	pg_assert_same(array('Size', 'Colour'), $data['axes'], 'axes');
	pg_assert_same(3, count($data['variants']), 'variants');
	pg_assert_same(array('Size' => 'L', 'Colour' => 'Black'), $data['variants'][2]['options'], 'options');
	pg_assert_same('Cotton Tee - M / White', $data['variants'][1]['name'], 'variant name');
	pg_assert_same('WF-TEE-W-S', $data['variants'][0]['sku'], 'sku');
	pg_assert_same('cotton-tee-3', $data['variants'][2]['sku'], 'sku made from the handle');
	pg_assert_same(2499, $data['variants'][0]['price'], 'price in minor units');
	pg_assert_same(2999, $data['variants'][0]['compare_at'], 'compare at');
	pg_assert_same(null, $data['variants'][1]['compare_at'], 'none');
	pg_assert_same(2650, $data['variants'][2]['price'], 'rounded');
	pg_assert_same(5, $data['variants'][0]['stock'], 'stock');
	pg_assert_same(null, $data['variants'][1]['stock'], 'empty is untracked');
	pg_assert_same(0, $data['variants'][2]['stock'], 'none left');
	pg_assert_same(array('https://cdn.prod.website-files.com/65f0/tee-white-m.jpg'), $data['variants'][1]['images'], 'variant picture');
	pg_assert_same(array(array('Clothing'), array('Summer shirts')), $data['categories'], 'categories');
	pg_assert_same(
		array('https://cdn.prod.website-files.com/65f0/tee-white-s.jpg', 'https://cdn.prod.website-files.com/65f0/tee-back.jpg', 'https://cdn.prod.website-files.com/65f0/tee-side.jpg'),
		$data['images'],
		'pictures'
	);
	pg_assert_same("<p>A soft tee.</p>\n<p>Cotton &amp; linen.</p>", $data['description'], 'text as paragraphs');
	pg_assert_same('0.2', $data['weight_figure'], 'weight as written');
	pg_assert_same(array('/product/cotton-tee'), $data['addresses'], 'old address');
	pg_assert_same(array('/product/cotton-tee'), pg_import_redirect_old_addresses('webflow', $item), 'redirect address');
	pg_assert_true($data['enabled'], 'enabled');
	pg_assert_true($data['shippable'], 'shipped');
}

function test_import_webflow_simple_service_and_skipped()
{
	$items = pg_test_import_webflow_items('products');
	$mug = $items['webflow:product:stone-mug']['data'];
	$visit = $items['webflow:product:garden-visit']['data'];

	pg_assert_same('simple', $mug['type'], 'no options is simple');
	pg_assert_same('WF-MUG', $mug['sku'], 'sku');
	pg_assert_same(1200, $mug['price'], 'decimal comma');
	pg_assert_same(1500, $mug['compare_at'], 'compare at');
	pg_assert_same('1.5', $mug['weight_figure'], 'weight decimal comma');
	pg_assert_same('<p>Holds <strong>tea</strong>.</p>', $mug['description'], 'HTML kept');
	pg_assert_same('', $mug['error'], 'no error');

	pg_assert_same('simple', $visit['type'], 'a service is a product');
	pg_assert_false($visit['shippable'], 'not shipped');
	pg_assert_same('garden-visit', $visit['sku'], 'sku from the handle');
	pg_assert_same(8000, $visit['price'], 'whole price');

	pg_assert_same('digital', $items['webflow:product:plant-guide']['data']['type'], 'digital skipped by the writer');
	pg_assert_same('advanced', $items['webflow:product:gift-box']['data']['source_type'], 'advanced named');
	pg_assert_contains('abc', $items['webflow:product:broken-price']['data']['error'], 'refused price');
	pg_assert_same(array('title' => 'Cotton Tee', 'detail' => 'Variant set with 3 variants'), pg_import_webflow_plan($items['webflow:product:cotton-tee']), 'plan line');
}

function test_import_webflow_shipping_column()
{
	$row = array('product handle' => 'kit', 'product name' => 'Kit', 'product type' => 'Physical', 'variant price' => '3', 'requires shipping' => 'false');

	pg_assert_false(pg_import_webflow_product(array($row), 2)['data']['shippable'], 'a physical product not shipped');
	pg_assert_true(pg_import_webflow_product(array(array('requires shipping' => '') + $row), 2)['data']['shippable'], 'physical default');
}

function test_import_webflow_products_cursor_resumes()
{
	$cursor = pg_import_webflow_open(pg_test_import_webflow_file('products'));
	pg_import_webflow_next($cursor);
	$saved = json_decode(json_encode($cursor), true);

	pg_assert_same(5, count(pg_test_import_webflow_items('products', $saved)));
}

function test_import_webflow_redirects()
{
	$items = pg_test_import_webflow_items('redirects');

	pg_assert_same(array('webflow:redirect:old-blog/(.*)', 'webflow:redirect:about-us', 'webflow:redirect:shop/old-mug'), array_keys($items), 'one key a name, letter case ignored');

	$about = $items['webflow:redirect:about-us'];
	pg_assert_same('redirect', $about['kind'], 'kind');
	pg_assert_same('/about-us', $about['title'], 'title is the old path');
	pg_assert_same(array('from' => '/about-us', 'to' => '/about', 'error' => ''), $about['data'], 'data');
	pg_assert_same(array('title' => '/about-us', 'detail' => '/about'), pg_import_webflow_plan($about), 'plan line');

	$wild = $items['webflow:redirect:old-blog/(.*)'];
	pg_assert_contains('(.*)', $wild['data']['error'], 'wildcard named');
	pg_assert_same($wild['data']['error'], pg_import_precheck_redirect($wild), 'dry run says so');
	pg_assert_same('', pg_import_precheck_redirect($about), 'a plain line passes');
}

function test_import_webflow_redirect_rows()
{
	pg_assert_same(null, pg_import_webflow_redirect(array('', '/b'), 3), 'no old path');
	pg_assert_contains('/x', pg_import_webflow_redirect(array('/x', ''), 3)['data']['error'], 'no new path');
	pg_assert_same('', pg_import_webflow_redirect(array('https://old.example.com/x?y=1', '/z'), 3)['data']['error'], 'a whole address');
	pg_assert_same('webflow:redirect:x', pg_import_webflow_redirect(array('https://old.example.com/x', '/z'), 3)['source_key'], 'keyed by its path');
}
