<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customs data of a product in the external API: hs_code and
 * origin_country on the product object, the two body fields of
 * POST /products and POST /products/{id}, and how a sent value is read. No
 * database is reached.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function api_product_customs_test_param($route_id, $name)
{
	foreach (api_schema() as $route) {
		if ($route['id'] !== $route_id) {
			continue;
		}

		foreach ($route['params'] as $param) {
			if ($param['name'] === $name) {
				return $param;
			}
		}
	}

	return null;
}

function api_product_customs_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// The product object carries both, null for none and on a site whose table
// lacks the columns.
function test_api_product_customs_fields()
{
	$schema = api_product_schema();

	pg_assert_same('string?', $schema['hs_code']);
	pg_assert_same('string?', $schema['origin_country']);

	pg_assert_same('6109.10', api_product_customs_value(array('hs_code' => '6109.10'), 'hs_code'));
	pg_assert_same('TR', api_product_customs_value(array('origin_country' => 'TR'), 'origin_country'));
	pg_assert_same(null, api_product_customs_value(array('hs_code' => ''), 'hs_code'), 'empty is the shop default');
	pg_assert_same(null, api_product_customs_value(array(), 'origin_country'), 'a site without the column');

	$select = api_product_customs_test_source('api_product_select');
	pg_assert_true(strpos($select, 'pg_product_customs_ready()') !== false, 'the list reads the columns only once they exist');
	pg_assert_true(strpos($select, 'products.hs_code') !== false);

	$present = api_product_customs_test_source('api_product_present');
	pg_assert_true(strpos($present, "'hs_code'") !== false && strpos($present, "'origin_country'") !== false);
}

// Both write routes take the pair, nullable, within their columns.
function test_api_product_customs_route_params()
{
	foreach (array('products.create', 'products.update') as $route_id) {
		$limits = array('hs_code' => PG_PRODUCT_HS_CODE_MAX, 'origin_country' => 2);

		foreach ($limits as $name => $max) {
			$param = api_product_customs_test_param($route_id, $name);

			pg_assert_true(is_array($param), $route_id . ' takes ' . $name);

			if (!is_array($param)) {
				continue;
			}

			pg_assert_same('body', $param['in'], $route_id . ' ' . $name);
			pg_assert_same('string', $param['type'], $route_id . ' ' . $name);
			pg_assert_same($max, $param['max_length'], $route_id . ' ' . $name);
			pg_assert_true(!empty($param['nullable']), $route_id . ' ' . $name . ': null clears it');
		}
	}
}

// Only the fields sent are read; each is stored as the product screen
// stores it, and a value the screen would refuse names its field.
function test_api_product_customs_values()
{
	pg_assert_same(array(), api_products_customs_values(array('name' => 'x')), 'nothing sent, nothing written');

	pg_assert_same(
		array('hs_code' => '6109.10', 'origin_country' => 'TR'),
		api_products_customs_values(array('hs_code' => ' 6109.10 ', 'origin_country' => 'tr')));

	pg_assert_same(array('hs_code' => ''), api_products_customs_values(array('hs_code' => '')), 'empty clears it');
	pg_assert_same(array('origin_country' => ''), api_products_customs_values(array('origin_country' => '')));

	pg_assert_same('hs_code', api_products_customs_values(array('hs_code' => '6109 10')), 'a space inside is refused');
	pg_assert_same('hs_code', api_products_customs_values(array('hs_code' => 'HS6109')));
	pg_assert_same('origin_country', api_products_customs_values(array('origin_country' => 'XX')), 'not a country');
}

// Both handlers read the pair before the dry run stops, so a rehearsal
// refuses what the write would refuse.
function test_api_product_customs_handlers_check_before_the_dry_run()
{
	foreach (array('api_products_create', 'api_products_update') as $handler) {
		$source = api_product_customs_test_source($handler);
		$read = strpos($source, 'api_products_customs_param(');
		$stop = strpos($source, 'api_dry_run_stop(');

		pg_assert_true($read !== false, $handler . ' reads the customs fields');
		pg_assert_true($read !== false && $stop !== false && $read < $stop, $handler . ' checks them before the dry run stops');
	}

	$param = api_product_customs_test_source('api_products_customs_param');
	pg_assert_true(strpos($param, 'pg_product_customs_ready()') !== false, 'a site before 11.0.130 is refused, not ignored');
}
