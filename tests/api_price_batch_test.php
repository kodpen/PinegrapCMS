<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how POST /products/prices reads each item's price
 * (api_price_batch_price(), includes/api/resources/products.php): whole minor
 * units within the bound the single-product routes declare, refused per item
 * rather than rounded or cut by the column.
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
// init.php. Behind the gate both files only declare functions, so the gate is
// answered here: nothing under tests/ calls the outbound HTTP client.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// Whole minor units, as a number or a numeric string, are taken.
function test_api_price_batch_price_takes_whole_minor_units()
{
	pg_assert_same(1999, api_price_batch_price(1999), 'a number');
	pg_assert_same(1999, api_price_batch_price('1999'), 'a numeric string');
	pg_assert_same(0, api_price_batch_price(0), 'free');
}

// A decimal, text, a boolean or nothing is not a price.
function test_api_price_batch_price_refuses_what_is_not_a_whole_number()
{
	pg_assert_same(null, api_price_batch_price(19.99), 'a decimal');
	pg_assert_same(null, api_price_batch_price('19.99'), 'a decimal string');
	pg_assert_same(null, api_price_batch_price('abc'), 'text');
	pg_assert_same(null, api_price_batch_price(true), 'a boolean');
	pg_assert_same(null, api_price_batch_price(null), 'missing');
	pg_assert_same(null, api_price_batch_price(array(5)), 'a list');
}

// Below 0 or past the column's top is refused: 3000000000 used to be answered
// "ok" and stored as 2147483647.
function test_api_price_batch_price_refuses_a_figure_outside_the_bound()
{
	pg_assert_same(2147483647, api_price_batch_price(2147483647), 'the top itself');
	pg_assert_same(false, api_price_batch_price(2147483648), 'one past');
	pg_assert_same(false, api_price_batch_price(3000000000), 'the reported figure');
	pg_assert_same(false, api_price_batch_price('3000000000'), 'as a string');
	pg_assert_same(false, api_price_batch_price(-1), 'negative');
}

// The batch bound is the one the single-product routes declare for price, so
// the endpoints cannot drift apart.
function test_api_price_batch_price_bound_matches_the_single_routes()
{
	$found = 0;

	foreach (api_schema() as $route) {

		if (!in_array($route['id'], array('products.create', 'products.update'), true)) {
			continue;
		}

		foreach ($route['params'] as $param) {

			if ($param['name'] === 'price') {
				pg_assert_same(array(0, API_PRODUCT_PRICE_MAX), array($param['min'], $param['max']), $route['id']);
				$found++;
			}

		}

	}

	pg_assert_same(2, $found, 'both routes declare price');
}
