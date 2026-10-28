<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a product bundle in the external API (includes/api/resources/
 * products.php): the bundle block a product carries, how an update's
 * bundle_items are read, and the declarations that put both in the OpenAPI
 * document.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

// products.php pulls in the outbound webhook module, whose gate asks for
// init.php; behind the gate both files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// A plain product, and every product on a site before the bundle schema,
// carries null.
function test_api_product_bundle_is_null_on_a_plain_product()
{
	pg_assert_same(null, api_product_bundle(array('bundle' => '0'), array()), 'bundle 0');
	pg_assert_same(null, api_product_bundle(array(), array()), 'no column');
}

// A bundle carries its pricing, discount and contents in their order.
function test_api_product_bundle_carries_pricing_and_contents()
{
	$row = array('bundle' => '1', 'bundle_pricing' => 'sum', 'bundle_discount_bp' => '1250');
	$items = array(
		array('bundle_id' => '4', 'product_id' => '9', 'quantity' => '2'),
		array('bundle_id' => '4', 'product_id' => '3', 'quantity' => '1')
	);

	pg_assert_same(array(
		'pricing'     => 'sum',
		'discount_bp' => 1250,
		'items'       => array(
			array('product_id' => 9, 'quantity' => 2),
			array('product_id' => 3, 'quantity' => 1)
		)
	), api_product_bundle($row, $items), 'sum bundle');

	$fixed = api_product_bundle(array('bundle' => '1', 'bundle_pricing' => 'odd', 'bundle_discount_bp' => '0'), array());

	pg_assert_same('fixed', $fixed['pricing'], 'anything but sum reads fixed');
	pg_assert_same(array(), $fixed['items'], 'a bundle with no contents');
}

// Whole ids and quantities, from JSON numbers or numeric strings, come back
// as rows in the order sent; an empty list empties the bundle.
function test_api_products_bundle_items_read_takes_whole_rows()
{
	pg_assert_same(array(
		array('product_id' => 9, 'quantity' => 2),
		array('product_id' => 3, 'quantity' => PG_BUNDLE_MAX_QUANTITY)
	), api_products_bundle_items_read(array(
		array('product_id' => 9, 'quantity' => '2'),
		array('product_id' => '3', 'quantity' => PG_BUNDLE_MAX_QUANTITY)
	), 4), 'two rows');

	pg_assert_same(array(), api_products_bundle_items_read(array(), 4), 'empty');
}

// What a form would clean up is refused with the reason, never dropped or
// added up.
function test_api_products_bundle_items_read_refuses()
{
	pg_assert_same('shape', api_products_bundle_items_read(array('junk'), 4), 'not an object');
	pg_assert_same('shape', api_products_bundle_items_read(array(array('product_id' => 9)), 4), 'no quantity');
	pg_assert_same('shape', api_products_bundle_items_read(array(array('product_id' => 9.5, 'quantity' => 1)), 4), 'a decimal id');
	pg_assert_same('shape', api_products_bundle_items_read(array(array('product_id' => 0, 'quantity' => 1)), 4), 'id 0');
	pg_assert_same('shape', api_products_bundle_items_read(array(array('product_id' => 9, 'quantity' => true)), 4), 'a boolean');
	pg_assert_same('quantity', api_products_bundle_items_read(array(array('product_id' => 9, 'quantity' => 0)), 4), 'quantity 0');
	pg_assert_same('quantity', api_products_bundle_items_read(array(array('product_id' => 9, 'quantity' => PG_BUNDLE_MAX_QUANTITY + 1)), 4), 'past the top');
	pg_assert_same('self', api_products_bundle_items_read(array(array('product_id' => 4, 'quantity' => 1)), 4), 'itself');
	pg_assert_same('twice', api_products_bundle_items_read(array(
		array('product_id' => 9, 'quantity' => 1),
		array('product_id' => 9, 'quantity' => 2)
	), 4), 'named twice');
}

// The update route takes the bundle fields within the bounds the code keeps,
// and the product object declares its bundle block.
function test_api_product_bundle_declarations()
{
	$found = array();

	foreach (api_schema() as $route) {

		if ($route['id'] !== 'products.update') {
			continue;
		}

		foreach ($route['params'] as $param) {
			$found[$param['name']] = $param;
		}
	}

	pg_assert_same(true, isset($found['bundle'], $found['bundle_pricing'], $found['bundle_discount_bp'], $found['bundle_items']), 'update params');
	pg_assert_same(array('fixed', 'sum'), $found['bundle_pricing']['values'], 'pricing values');
	pg_assert_same(array(0, 10000), array($found['bundle_discount_bp']['min'], $found['bundle_discount_bp']['max']), 'discount bound');
	pg_assert_same(PG_BUNDLE_MAX_ITEMS, $found['bundle_items']['max_items'], 'contents bound');

	$schema = api_product_schema();

	pg_assert_same('ProductBundle?', $schema['bundle'], 'product field');
	pg_assert_same(array('pricing', 'discount_bp', 'items'), array_keys(api_product_bundle_schema()), 'bundle object');
}
