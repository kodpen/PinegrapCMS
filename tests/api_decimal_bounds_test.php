<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bounds of the external API's product number fields that are
 * written to a decimal column (weight, length, width, height): the session
 * runs without strict mode, so MySQL would store a negative figure in an
 * unsigned column as 0 and a larger one as the column's top, and the client
 * would get 200. Every bound has to fit the column, as the setup SQL defines
 * it, on create and on update alike.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// The decimal columns of one table in a setup SQL file, as name =>
// array(digits, scale, unsigned).
function api_decimal_bounds_test_columns($table, $setup = 'english_default')
{
	$sql = (string) file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $setup . '/sql.sql');

	if (!preg_match('/CREATE TABLE `?' . preg_quote($table, '/') . '`? \((.*?)\) ENGINE/s', $sql, $match)) {
		return array();
	}

	preg_match_all('/^\s*`([a-z0-9_]+)` decimal\((\d+),(\d+)\)( unsigned)?/m', $match[1], $columns, PREG_SET_ORDER);

	$out = array();

	foreach ($columns as $column) {
		$out[$column[1]] = array((int) $column[2], (int) $column[3], !empty($column[4]));
	}

	return $out;
}

// The decimal body parameters of a route, keyed by name.
function api_decimal_bounds_test_params($route_id)
{
	$out = array();

	foreach (api_schema() as $route) {

		if ($route['id'] !== $route_id) {
			continue;
		}

		foreach ($route['params'] as $param) {

			if (($param['in'] === 'body') && ($param['type'] === 'decimal')) {
				$out[$param['name']] = $param;
			}

		}

	}

	return $out;
}

function test_api_decimal_bounds_the_setup_files_agree()
{
	$english = api_decimal_bounds_test_columns('products');

	pg_assert_same(array(12, 4, true), isset($english['weight']) ? $english['weight'] : null, 'weight is read from the setup SQL');
	pg_assert_same($english, api_decimal_bounds_test_columns('products', 'turkish_default'), 'both setups agree');
}

// A decimal field that names a column declares a floor and a top the column
// can hold. Without them the update route took -5 for weight, answered 200,
// and the row held 0.
function test_api_decimal_bounds_product_fields_fit_their_columns()
{
	$columns = api_decimal_bounds_test_columns('products');

	foreach (array('products.create', 'products.update') as $route_id) {

		$checked = 0;

		foreach (api_decimal_bounds_test_params($route_id) as $name => $param) {

			if (!isset($columns[$name])) {
				continue;
			}

			list($digits, $scale, $unsigned) = $columns[$name];

			$top = pow(10, $digits - $scale) - pow(10, -$scale);

			pg_assert_true(isset($param['min']), $route_id . '.' . $name . ' declares min');
			pg_assert_true(!$unsigned || (isset($param['min']) && $param['min'] >= 0), $route_id . '.' . $name . ' takes no negative figure (unsigned column)');
			pg_assert_true(isset($param['max']) && ($param['max'] <= $top + 1e-9), $route_id . '.' . $name . ' takes at most ' . $top);

			$checked++;

		}

		pg_assert_same(4, $checked, $route_id . ': weight, length, width and height');

	}
}

// The figures named in the report, by value, so a wider column has to be a
// deliberate change here as well.
function test_api_decimal_bounds_dimensions_are_the_column_range()
{
	foreach (array('products.create', 'products.update') as $route_id) {

		$params = api_decimal_bounds_test_params($route_id);

		foreach (array('weight', 'length', 'width', 'height') as $name) {

			pg_assert_same(array(0, 99999999.9999), array($params[$name]['min'], $params[$name]['max']), $route_id . '.' . $name);

		}

	}
}
