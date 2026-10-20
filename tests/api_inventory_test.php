<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the quantity a batch item of POST /products/inventory carries
 * (api_inventory_quantity(), includes/api/resources/inventory.php): the same
 * whole number and the same bound the single-product endpoint's route
 * declares.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/inventory.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// Whole numbers come through as ints, from JSON numbers and numeric strings.
function test_api_inventory_quantity_reads_whole_numbers()
{
	pg_assert_same(5, api_inventory_quantity(5), 'a JSON number');
	pg_assert_same(-3, api_inventory_quantity('-3'), 'a string');
	pg_assert_same(0, api_inventory_quantity('0'), 'zero');
	pg_assert_same(12, api_inventory_quantity(12.0), 'a JSON number written with .0');
}

// Anything after a decimal point, a non-number or a missing value is not a
// quantity. 2.9 used to be stored as 2.
function test_api_inventory_quantity_refuses_fractions_and_text()
{
	pg_assert_same(null, api_inventory_quantity('2.9'), 'a decimal string');
	pg_assert_same(null, api_inventory_quantity(2.9), 'a decimal number');
	pg_assert_same(null, api_inventory_quantity('1e3'), 'exponent notation');
	pg_assert_same(null, api_inventory_quantity('abc'), 'text');
	pg_assert_same(null, api_inventory_quantity(''), 'empty');
	pg_assert_same(null, api_inventory_quantity(null), 'missing');
	pg_assert_same(null, api_inventory_quantity(true), 'a boolean');
	pg_assert_same(null, api_inventory_quantity(array(1)), 'a list');
}

// A whole number past the bound is refused rather than clamped by the column.
// 5000000000 used to be stored as 4294967295.
function test_api_inventory_quantity_refuses_out_of_range()
{
	pg_assert_same(1000000, api_inventory_quantity(1000000), 'the upper bound itself');
	pg_assert_same(-1000000, api_inventory_quantity('-1000000'), 'the lower bound itself');
	pg_assert_same(false, api_inventory_quantity(1000001), 'one past');
	pg_assert_same(false, api_inventory_quantity(5000000000), 'past the column');
	pg_assert_same(false, api_inventory_quantity('-5000000000'), 'far below');
}

// The batch bound is the one the single-product route declares, so the two
// endpoints cannot drift apart.
function test_api_inventory_quantity_bound_matches_the_single_route()
{
	$bound = null;

	foreach (api_schema() as $route) {

		if ($route['id'] !== 'inventory.write') {
			continue;
		}

		foreach ($route['params'] as $param) {

			if ($param['name'] === 'quantity') {
				$bound = array($param['min'], $param['max']);
			}

		}

	}

	pg_assert_same(array(-API_INVENTORY_QUANTITY_LIMIT, API_INVENTORY_QUANTITY_LIMIT), $bound);
}
