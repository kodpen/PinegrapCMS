<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for products.compare_at_price, the merchant's own "was" price: how a
 * value is stored (includes/fn/price_history.php), how the product screen and
 * list read a typed amount (product_builder.php), how the product file writes
 * it (export_products_f.php) and how the external API declares and answers it
 * (includes/api/schema.php, includes/api/resources/products.php).
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
// init.php. Behind the gate both files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
require_once(PG_FUNCTIONS_DIR . '/export_products_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

if (!function_exists('api_money')) {
	function api_money($minor_units)
	{
		return (int) $minor_units;
	}
}

// Nothing, an empty value and zero all mean "no compare-at price".
function test_compare_at_price_value_none()
{
	pg_assert_same(null, pg_compare_at_price_value(null));
	pg_assert_same(null, pg_compare_at_price_value(''));
	pg_assert_same(null, pg_compare_at_price_value('  '), 'blank');
	pg_assert_same(null, pg_compare_at_price_value(0));
	pg_assert_same(null, pg_compare_at_price_value('0'), 'as the database or a form gives it');
	pg_assert_same(null, pg_compare_at_price_value(0.0));
}

// A whole number of minor units up to the top of the signed INT column is
// kept, whether it arrives as an int, a string from the database or a float.
function test_compare_at_price_value_kept()
{
	pg_assert_same(1999, pg_compare_at_price_value(1999));
	pg_assert_same(1999, pg_compare_at_price_value('1999'));
	pg_assert_same(1999, pg_compare_at_price_value(' 1999 '), 'trimmed');
	pg_assert_same(1999, pg_compare_at_price_value(1999.0), 'a whole float');
	pg_assert_same(1, pg_compare_at_price_value(1), 'the smallest');
	pg_assert_same(2147483647, pg_compare_at_price_value(2147483647), 'the top of the column');
	pg_assert_same(2147483647, pg_compare_at_price_value('2147483647'));
}

// What the column cannot hold is refused, never cut to fit or cleared.
function test_compare_at_price_value_refused()
{
	pg_assert_same(false, pg_compare_at_price_value(-1), 'negative');
	pg_assert_same(false, pg_compare_at_price_value('-1999'), 'negative string');
	pg_assert_same(false, pg_compare_at_price_value(2147483648), 'one above the column');
	pg_assert_same(false, pg_compare_at_price_value('99999999999'), 'far above');
	pg_assert_same(false, pg_compare_at_price_value(19.99), 'not a whole number of minor units');
	pg_assert_same(false, pg_compare_at_price_value('19.99'), 'a decimal string is a typed amount, not minor units');
	pg_assert_same(false, pg_compare_at_price_value('abc'));
	pg_assert_same(false, pg_compare_at_price_value(true));
	pg_assert_same(false, pg_compare_at_price_value(array(1999)));
	pg_assert_same(false, pg_compare_at_price_value(INF));
}

function test_compare_at_price_sql()
{
	pg_assert_same('NULL', pg_compare_at_price_sql(null));
	pg_assert_same('NULL', pg_compare_at_price_sql(0), 'zero is none');
	pg_assert_same("'1999'", pg_compare_at_price_sql(1999));
	pg_assert_same("'1999'", pg_compare_at_price_sql('1999'));

	// A value the column cannot hold never reaches a statement.
	pg_assert_same('NULL', pg_compare_at_price_sql(-5));
	pg_assert_same('NULL', pg_compare_at_price_sql("1999' OR '1'='1"));
}

// The product screen and the product list read a typed compare-at price with
// the price box's own reader, so both boxes take the same typing.
function test_compare_at_price_typed_amounts()
{
	pg_assert_same(129990, pg_pb_compare_at_price_from_input('1.299,90'), 'Turkish style');
	pg_assert_same(129990, pg_pb_compare_at_price_from_input('1,299.90'), 'English style');
	pg_assert_same(129990, pg_pb_compare_at_price_from_input('1299.90'), 'as the product file writes it');
	pg_assert_same(1250, pg_pb_compare_at_price_from_input('12,50'));
	pg_assert_same(4990, pg_pb_compare_at_price_from_input('₺49,90'), 'a symbol pasted with the figure');
	pg_assert_same(pg_pb_price_to_cents('1.299,90'), pg_pb_compare_at_price_from_input('1.299,90'), 'the same reading as the price box');

	pg_assert_same(null, pg_pb_compare_at_price_from_input(''), 'an empty box is none');
	pg_assert_same(null, pg_pb_compare_at_price_from_input('0'));
	pg_assert_same(null, pg_pb_compare_at_price_from_input('0,00'));
	pg_assert_same(null, pg_pb_compare_at_price_from_input(null));
	pg_assert_same(null, pg_pb_compare_at_price_from_input(array('1')), 'a posted list is no amount');

	pg_assert_same(2147483647, pg_pb_compare_at_price_from_input('21.474.836,47'), 'the top of the column');
	pg_assert_same(false, pg_pb_compare_at_price_from_input('21.474.836,48'), 'one kuruş above it');
	pg_assert_same(false, pg_pb_compare_at_price_from_input('99999999999999999999'), 'beyond what a float holds exactly');
}

// The product file writes no compare-at price as an empty cell, which the
// importer leaves alone, and a price the way it writes every price.
function test_compare_at_price_export_value()
{
	$column = array('heading' => 'compare_at_price', 'column' => 'compare_at_price', 'kind' => 'money_optional');

	pg_assert_same('', pg_products_export_value(array('compare_at_price' => null), $column), 'none');
	pg_assert_same('', pg_products_export_value(array(), $column), 'a row read without the column');
	pg_assert_same('1299.90', pg_products_export_value(array('compare_at_price' => '129990'), $column));

	$price = array('heading' => 'price', 'column' => 'price', 'kind' => 'money');

	pg_assert_same('1299.90', pg_products_export_value(array('price' => '129990'), $price), 'the same figure as the price');
}

function compare_at_price_test_param($route_id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] !== $route_id) {
			continue;
		}

		foreach ($route['params'] as $param) {
			if ($param['name'] === 'compare_at_price') {
				return $param;
			}
		}
	}

	return null;
}

// Both writes take it in minor units, bounded by the column, and take null
// (or an empty value) as the clear.
function test_compare_at_price_api_routes_take_it()
{
	foreach (array('products.create', 'products.update') as $route_id) {
		$param = compare_at_price_test_param($route_id);

		pg_assert_true(is_array($param), $route_id . ' takes compare_at_price');

		if (!is_array($param)) {
			continue;
		}

		pg_assert_same('body', $param['in'], $route_id);
		pg_assert_same('money', $param['type'], $route_id);
		pg_assert_same(0, $param['min'], $route_id . ' min');
		pg_assert_same(PG_COMPARE_AT_PRICE_MAX, $param['max'], $route_id . ' max');
		pg_assert_true(!empty($param['nullable']), $route_id . ' null clears it');
	}
}

// The product object carries it, null when there is none.
function test_compare_at_price_api_product_field()
{
	$schema = api_product_schema();

	pg_assert_same('integer?', isset($schema['compare_at_price']) ? $schema['compare_at_price'] : null, 'declared as a nullable integer');

	pg_assert_same(129990, api_product_compare_at_price(array('compare_at_price' => '129990')));
	pg_assert_same(null, api_product_compare_at_price(array('compare_at_price' => null)), 'none');
	pg_assert_same(null, api_product_compare_at_price(array('compare_at_price' => '0')), 'zero put there by hand reads as none');
	pg_assert_same(null, api_product_compare_at_price(array()), 'a site without the column');
}
