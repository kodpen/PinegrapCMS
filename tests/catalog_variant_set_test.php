<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the variant set writer (pg_catalog_variant_set_create(),
 * includes/fn/ecommerce.php): the checks the product screen and
 * POST /product-groups/variant-sets share, the price each variant gets (the
 * set's rules through pg_variant_price_compute(), in integer minor units, or
 * the row's own price), and the product row and links built for one variant.
 * Read from the source, since they need a database: the screen's group mode
 * and the API route both write through it, and the route stops a rehearsal
 * above the first write.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The rich text is stored with {path} for the site's path; without a request
// the path is the root, as elsewhere in the tests.
if (!defined('PATH')) {
	define('PATH', '/');
}

function catalog_variant_set_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The body of one function in a source file, up to the next top-level
// function.
function catalog_variant_set_test_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// A two-row set as the screen hands it over: prices already in minor units.
function catalog_variant_set_test_data()
{
	$row = array(
		'name'               => 'TSHIRT-RED-S',
		'short_description'  => 'T-Shirt Red / S',
		'price'              => 1499,
		'price_manual'       => false,
		'inventory'          => '1',
		'inventory_quantity' => '12',
		'images'             => array(),
		'attributes'         => array(array('attribute_id' => 3, 'option_id' => 12)),
		'recurring'          => array(),
		'gtin'               => '',
		'barcode'            => '',
	);

	$second = $row;
	$second['name'] = 'TSHIRT-RED-M';
	$second['short_description'] = '';
	$second['attributes'] = array(array('attribute_id' => 3, 'option_id' => 13));

	return array(
		'name'              => 'TSHIRT',
		'short_description' => 'T-Shirt',
		'full_description'  => '<p>Cotton</p>',
		'details'           => '',
		'title'             => ' T-Shirt title ',
		'meta_description'  => ' Soft ',
		'images'            => array('cover.jpg', 'back.jpg'),
		'zone_ids'          => array(4),
		'common'            => array('enabled' => '1', 'gtin' => 'stale', 'start' => 0),
		'price_base'        => 1499,
		'price_rules'       => null,
		'variants'          => array($row, $second),
	);
}

function test_catalog_variant_set_check_accepts_a_set()
{
	pg_assert_same(array(), pg_catalog_variant_set_check(catalog_variant_set_test_data()), 'a named set of two rows');
}

function test_catalog_variant_set_check_refuses_what_is_not_a_set()
{
	$data = catalog_variant_set_test_data();
	$data['name'] = '';
	$data['short_description'] = '';
	$errors = pg_catalog_variant_set_check($data);
	pg_assert_same('name', $errors[0]['field'], 'no name for the group');

	$data = catalog_variant_set_test_data();
	$data['variants'] = array($data['variants'][0]);
	$errors = pg_catalog_variant_set_check($data);
	pg_assert_same('variants', $errors[0]['field'], 'one row is a product, not a set');

	$data = catalog_variant_set_test_data();
	$data['variants'][1]['name'] = ' ';
	$errors = pg_catalog_variant_set_check($data);
	pg_assert_same('variants', $errors[0]['field'], 'a row without a SKU');

	foreach (array(-1, 12.5, '12.50', null) as $price) {
		$data = catalog_variant_set_test_data();
		$data['variants'][0]['price'] = $price;
		$errors = pg_catalog_variant_set_check($data);
		pg_assert_same('variants', isset($errors[0]['field']) ? $errors[0]['field'] : '', 'a price that is not whole minor units: ' . var_export($price, true));
	}

	$data = catalog_variant_set_test_data();
	$data['price_base'] = 14.99;
	$errors = pg_catalog_variant_set_check($data);
	pg_assert_same('price', isset($errors[0]['field']) ? $errors[0]['field'] : '', 'a base that is not whole minor units');
}

function test_catalog_variant_set_price_follows_the_rules_in_minor_units()
{
	$rules = array('version' => 1, 'attributes' => array(
		array('attribute_id' => 3, 'mode' => 'adjust', 'options' => array(array('option_id' => 12, 'percent' => -33))),
	));

	$row = catalog_variant_set_test_data()['variants'][0];
	$row['price'] = 5000;

	// 1499 less 33% is 1004.33: rounded, never truncated.
	pg_assert_same(1004, pg_catalog_variant_set_price($row, 1499, $rules), 'priced by the rules from the base');

	$row['price_manual'] = true;
	pg_assert_same(5000, pg_catalog_variant_set_price($row, 1499, $rules), 'a price typed over the rules stays');

	$row['price_manual'] = false;
	pg_assert_same(5000, pg_catalog_variant_set_price($row, 1499, null), 'no rules: the row\'s own price');
}

function test_catalog_variant_set_product_is_the_row_the_screen_built()
{
	$data = catalog_variant_set_test_data();
	$built = pg_catalog_variant_set_product($data, $data['variants'][1], 77, null);
	$product = $built['product'];

	pg_assert_same('TSHIRT-RED-M', $product['name'], 'the row\'s SKU');
	pg_assert_same(1499, $product['price'], 'the price in minor units');
	pg_assert_same('1', $product['enabled'], 'the shared fields');
	pg_assert_same('', $product['gtin'], 'the row\'s GTIN wins over the stale shared one, empty included');
	pg_assert_same('T-Shirt title', $product['title'], 'title trimmed');
	pg_assert_same('Soft', $product['meta_description'], 'meta description trimmed');
	pg_assert_same('TSHIRT-RED-M', $product['address_name'], 'address from the SKU when the row has no short description');
	pg_assert_false(isset($product['ai_fields']), 'no AI marks when none were posted');

	pg_assert_same(array('cover.jpg', 'back.jpg'), $built['relations']['images'], 'the set\'s images when the row has none');
	pg_assert_same(array(77), $built['relations']['group_ids'], 'filed in the set only');
	pg_assert_same(array(4), $built['relations']['zone_ids'], 'the zones');
	pg_assert_same($data['variants'][1]['attributes'], $built['relations']['attributes'], 'the row\'s option pair');

	$row = $data['variants'][0];
	$row['images'] = array('red.jpg');
	$row['recurring'] = array('start' => 5, 'payment_period' => 'Monthly');
	$built = pg_catalog_variant_set_product($data, $row, 77, null);

	pg_assert_same('T-Shirt Red / S', $built['product']['address_name'], 'address from the row\'s short description');
	pg_assert_same(array('red.jpg'), $built['relations']['images'], 'the row\'s own images');
	pg_assert_same(5, $built['product']['start'], 'a recurring column the shared set carries');
	pg_assert_false(isset($built['product']['payment_period']), 'one it does not carry is dropped');
}

function test_catalog_variant_set_screen_and_api_write_through_it()
{
	$screen = catalog_variant_set_test_body(catalog_variant_set_test_source('product_builder.php'), 'pg_pb_save_new_product');

	pg_assert_contains('pg_catalog_variant_set_create(', $screen, 'the screen\'s group mode writes through the shared function');
	pg_assert_false(strpos($screen, 'pg_pb_create_group(') !== false, 'no group write of its own');
	pg_assert_contains('pg_pb_price_rules_from_post()', $screen, 'the screen still reads its rules from the form');

	$writer = catalog_variant_set_test_body(catalog_variant_set_test_source('includes/fn/ecommerce.php'), 'pg_catalog_variant_set_create');

	pg_assert_contains('pg_catalog_variant_set_check($data)', $writer, 'the writer checks first');
	pg_assert_contains('pg_variant_price_rules_store(', $writer, 'the rules are kept with the set');
	pg_assert_false(strpos($writer, 'pg_announce(') !== false, 'announces nothing, as the screen never did');

	$api = catalog_variant_set_test_body(catalog_variant_set_test_source('includes/api/resources/product_groups.php'), 'api_product_groups_variant_sets_create');

	pg_assert_contains('pg_catalog_variant_set_check(', $api, 'the route meets the same checks');
	pg_assert_contains('api_dry_run_stop(', $api, 'the route can be rehearsed');
	pg_assert_true(strpos($api, 'api_dry_run_stop(') < strpos($api, 'pg_catalog_variant_set_create('), 'the rehearsal stops above the write');
	pg_assert_false(strpos($api, '(int)$variant[\'price\']') !== false, 'no price cast');
	pg_assert_contains('api_product_group_price_rule_amounts_or_fail($params[\'price_rules\']);', $api, 'rule amounts refused, not rounded or dropped');
	pg_assert_true(strpos($api, 'api_product_group_price_rule_amounts_or_fail(') < strpos($api, 'pg_variant_price_rules_normalize('), 'before the rules are normalized');
	pg_assert_contains("pg_audit_record('create', 'products', (int)\$product_id);", $api, 'every created product gets its audit line, as POST /products and the screen give it');

	$groups = catalog_variant_set_test_source('includes/api/resources/product_groups.php');
	$amounts = catalog_variant_set_test_body($groups, 'api_product_group_price_rule_amounts_or_fail');

	pg_assert_contains("array_key_exists('amount', \$option) && !is_int(\$option['amount'])", $amounts, 'an amount is a whole number of minor units');

	$rows = catalog_variant_set_test_body($groups, 'api_product_group_variant_rows');

	pg_assert_contains('$quantity < 0', $rows, 'stock below zero refused');
	pg_assert_contains('$quantity > API_INVENTORY_QUANTITY_LIMIT', $rows, 'stock above the inventory limit refused');

	// The screen audits the ids itself (add_product.php), so the writer does not.
	pg_assert_false(strpos($writer, 'pg_audit_record(') !== false, 'no audit line in the writer: the screen would get two');

	$schema = catalog_variant_set_test_source('includes/api/schema.php');

	pg_assert_contains("'id'      => 'product_groups.variant_sets_create'", $schema, 'the route is declared');
	pg_assert_contains("'path'    => '/product-groups/variant-sets'", $schema, 'at its path');
}
