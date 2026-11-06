<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the WooCommerce reader of the content import framework
 * (includes/import/woocommerce.php) on tests/fixtures/import/woocommerce.csv,
 * and the pure parts of the product writer (includes/import/write_product.php):
 * the product row and the variant set input.
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
require_once(PG_FUNCTIONS_DIR . '/includes/import/woocommerce.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/write_product.php');

// Every item of the fixture by source key; the cursor goes through JSON
// between items, as the cursor file takes it.
function pg_test_woocommerce_items()
{
	$cursor = pg_import_woocommerce_open(dirname(__DIR__) . '/tests/fixtures/import/woocommerce.csv');
	$items = array();

	while (true) {
		$cursor = json_decode(json_encode($cursor), true);
		$item = pg_import_woocommerce_next($cursor);

		if ($item === null) {
			break;
		}

		$items[$item['source_key']] = $item;
	}

	return $items;
}

// The file is told as a WooCommerce export.
function test_import_woocommerce_detected()
{
	$head = file_get_contents(dirname(__DIR__) . '/tests/fixtures/import/woocommerce.csv', false, null, 0, 8192);

	pg_assert_same('woocommerce', pg_import_detect_kind($head), 'kind');
	pg_assert_true(pg_import_reader_load('woocommerce'), 'reader loads');
}

// One item per product; variations come inside their parent.
function test_import_woocommerce_counts()
{
	$items = pg_test_woocommerce_items();

	pg_assert_same(array('woo:product:10', 'woo:product:11', 'woo:product:15', 'woo:product:16', 'woo:product:17'), array_keys($items), 'keys');

	foreach ($items as $item) {
		pg_assert_same('product', $item['kind'], 'kind of ' . $item['source_key']);
	}
}

// A simple product: price, stock, categories as paths, tags, pictures.
function test_import_woocommerce_simple()
{
	$data = pg_test_woocommerce_items()['woo:product:10']['data'];

	pg_assert_same('simple', $data['type'], 'type');
	pg_assert_same('MUG-01', $data['sku'], 'sku');
	pg_assert_same('Coffee Mug', $data['name'], 'name');
	pg_assert_same(1250, $data['price'], 'price');
	pg_assert_same(null, $data['compare_at'], 'no compare-at');
	pg_assert_same(25, $data['stock'], 'stock');
	pg_assert_same('0.8818', $data['weight'], 'Weight (kg) in pounds, the unit products.weight holds');
	pg_assert_same(true, $data['enabled'], 'published');
	pg_assert_same(array(array('Kitchen', 'Mugs'), array('Gifts')), $data['categories'], 'categories');
	pg_assert_same('coffee, mug', $data['keywords'], 'tags');
	pg_assert_same(2, count($data['images']), 'pictures');
	pg_assert_same('', $data['error'], 'no error');
}

// A variable product carries its variations: a sale price under the regular
// one, a typed price, a missing SKU, a price that is no amount left out.
function test_import_woocommerce_variable()
{
	$data = pg_test_woocommerce_items()['woo:product:11']['data'];

	pg_assert_same('variable', $data['type'], 'type');
	pg_assert_same(array('Color', 'Size'), $data['axes'], 'axes');
	pg_assert_same(2, count($data['variants']), 'variants');
	pg_assert_same(1, count($data['notes']), 'one left out');
	pg_assert_true(strpos($data['notes'][0], 'abc') !== false, 'the refused price is named');

	$blue = $data['variants'][0];
	pg_assert_same('TEE-BLUE-S', $blue['sku'], 'blue sku');
	pg_assert_same(1500, $blue['price'], 'sale price is the price');
	pg_assert_same(2000, $blue['compare_at'], 'regular price is compare-at');
	pg_assert_same(5, $blue['stock'], 'blue stock');
	pg_assert_same(array('Color' => 'Blue', 'Size' => 'S'), $blue['options'], 'blue options');
	pg_assert_same('woo:product:12', $blue['source_key'], 'blue key');

	$red = $data['variants'][1];
	pg_assert_same('TEE-13', $red['sku'], 'a variation without SKU takes the parent one and its id');
	pg_assert_same(1800, $red['price'], 'typed price');
	pg_assert_same(0, $red['stock'], 'out of stock');
	pg_assert_same(array('Color' => 'Red', 'Size' => 'M'), $red['options'], 'red options');
}

// Other kinds are skipped, an orphan variation fails, a draft is off.
function test_import_woocommerce_other_rows()
{
	$items = pg_test_woocommerce_items();

	pg_assert_same('other', $items['woo:product:15']['data']['type'], 'grouped');
	pg_assert_same('grouped', $items['woo:product:15']['data']['source_type'], 'grouped named');
	pg_assert_true($items['woo:product:17']['data']['error'] !== '', 'orphan variation');

	$poster = $items['woo:product:16']['data'];
	pg_assert_same('woo-16', $poster['sku'], 'sku from the id');
	pg_assert_same(123450, $poster['price'], 'decimal comma');
	pg_assert_same(false, $poster['enabled'], 'draft');
	pg_assert_same(null, $poster['stock'], 'not tracked');

	pg_assert_same('Variant set with 2 variants', pg_import_woocommerce_plan($items['woo:product:11'])['detail'], 'plan line');
}

// The list cells: a "\," stays in a name; a parent reference by SKU is
// compared case aside.
function test_import_woocommerce_cells()
{
	pg_assert_same(array('Tea, Coffee', 'Gifts'), pg_import_woocommerce_list('Tea\\, Coffee, Gifts'), 'escaped comma');
	pg_assert_same('id:11', pg_import_woocommerce_ref('id: 11'), 'id ref');
	pg_assert_same('tee', pg_import_woocommerce_ref('TEE'), 'sku ref');
	pg_assert_same(null, pg_import_woocommerce_stock(array('stock' => '', 'in stock?' => '1')), 'untracked');
	pg_assert_same(0, pg_import_woocommerce_stock(array('stock' => '-3', 'in stock?' => '1')), 'backorder');
}

// The writer's rows: a simple product's columns and a variant set that
// passes the set writer's own check.
function test_import_woocommerce_writer_rows()
{
	$data = pg_test_woocommerce_items()['woo:product:11']['data'];

	$row = pg_import_product_row(pg_test_woocommerce_items()['woo:product:10']['data'], 4);
	pg_assert_same('MUG-01', $row['name'], 'sku is name');
	pg_assert_same('Coffee Mug', $row['short_description'], 'name is short description');
	pg_assert_same(1250, $row['price'], 'price');
	pg_assert_same('1', $row['inventory'], 'tracked');
	pg_assert_same(25, $row['inventory_quantity'], 'quantity');
	pg_assert_same('1', $row['enabled'], 'enabled');

	$pairs = array(
		array(array('attribute_id' => 1, 'option_id' => 10), array('attribute_id' => 2, 'option_id' => 20)),
		array(array('attribute_id' => 1, 'option_id' => 11), array('attribute_id' => 2, 'option_id' => 21)),
	);
	$set = pg_import_product_variant_set($data, $data['variants'], $pairs, 7, array('tee.jpg'), 4);

	pg_assert_same(array(), pg_catalog_variant_set_check($set), 'set check');
	pg_assert_same('V-Neck T-Shirt', pg_catalog_variant_set_name($set), 'group name');
	pg_assert_same(7, $set['parent_id'], 'parent');
	pg_assert_same(1500, $set['price_base'], 'base is the lowest');
	pg_assert_same(2, count($set['attribute_meta']), 'axes');
	pg_assert_same(true, $set['variants'][0]['price_manual'], 'own prices');
	pg_assert_false(isset($set['common']['price']), 'price is per variant');
}

function test_import_woocommerce_weight_units()
{
	// The unit is the one the header names; WooCommerce's default is kg.
	pg_assert_same('2.2046', pg_import_woocommerce_weight(array('weight (kg)' => '1')), 'kg');
	pg_assert_same('2.2046', pg_import_woocommerce_weight(array('weight (kg)' => '1,0')), 'decimal comma');
	pg_assert_same('1.5', pg_import_woocommerce_weight(array('weight (lbs)' => '1.5')), 'lbs as written');
	pg_assert_same('0.5', pg_import_woocommerce_weight(array('weight (oz)' => '8')), 'oz');
	pg_assert_same('0.5512', pg_import_woocommerce_weight(array('weight (g)' => '250')), 'g');
	pg_assert_same('2.2046', pg_import_woocommerce_weight(array('weight' => '1')), 'no unit is kg');
	pg_assert_same('', pg_import_woocommerce_weight(array('weight (kg)' => '')), 'empty');
	pg_assert_same('', pg_import_woocommerce_weight(array('weight (kg)' => 'heavy')), 'words');
	pg_assert_same('', pg_import_woocommerce_weight(array('name' => 'x')), 'no column');
}

function test_import_weight_pounds()
{
	pg_assert_same('2.2046', pg_import_weight_pounds(1, 'kg'), 'kg');
	pg_assert_same('1', pg_import_weight_pounds(453.59237, 'g'), 'g');
	pg_assert_same('0.0625', pg_import_weight_pounds(1, 'oz'), 'oz');
	pg_assert_same('3', pg_import_weight_pounds(3, 'lb'), 'lb');
	pg_assert_same('', pg_import_weight_pounds(0, 'kg'), 'zero');
	pg_assert_same('', pg_import_weight_pounds(-2, 'kg'), 'negative');
}
