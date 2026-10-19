<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the length limits of the external API's product and product group
 * text fields: the session runs without strict mode, so MySQL would cut a
 * longer value to the column's width and the client would get 200 with a
 * shortened record. Every limit has to fit the column it is written to, as the
 * setup SQL defines it.
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

// The varchar columns of one table in a setup SQL file, as name => width.
function api_text_bounds_test_columns($table, $setup = 'english_default')
{
	$sql = (string) file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $setup . '/sql.sql');

	if (!preg_match('/CREATE TABLE `?' . preg_quote($table, '/') . '`? \((.*?)\) ENGINE/s', $sql, $match)) {
		return array();
	}

	preg_match_all('/^\s*`([a-z0-9_]+)` varchar\((\d+)\)/m', $match[1], $columns, PREG_SET_ORDER);

	$out = array();

	foreach ($columns as $column) {
		$out[$column[1]] = (int) $column[2];
	}

	return $out;
}

// The body parameters of a route, keyed by name.
function api_text_bounds_test_params($route_id)
{
	foreach (api_schema() as $route) {

		if ($route['id'] === $route_id) {

			$out = array();

			foreach ($route['params'] as $param) {

				if ($param['in'] === 'body') {
					$out[$param['name']] = $param;
				}

			}

			return $out;

		}

	}

	return array();
}

function test_api_text_bounds_the_setup_files_agree()
{
	foreach (array('products', 'product_groups') as $table) {

		$english = api_text_bounds_test_columns($table);

		pg_assert_true(isset($english['name'], $english['short_description']), $table . ' is read from the setup SQL');
		pg_assert_same($english, api_text_bounds_test_columns($table, 'turkish_default'), $table . ' has the same widths in both setups');

	}
}

function test_api_text_bounds_product_fields_fit_their_columns()
{
	$routes = array(
		'products.create'       => 'products',
		'products.update'       => 'products',
		'product_groups.create' => 'product_groups',
		'product_groups.update' => 'product_groups'
	);

	foreach ($routes as $route_id => $table) {

		$columns = api_text_bounds_test_columns($table);
		$params  = api_text_bounds_test_params($route_id);

		pg_assert_true(count($params) > 3, $route_id . ' is found');

		foreach ($params as $name => $param) {

			if (!isset($columns[$name]) || $param['type'] !== 'string') {
				continue;
			}

			pg_assert_true(isset($param['max_length']), $route_id . '.' . $name . ' declares max_length');

			pg_assert_true(
				isset($param['max_length']) && $param['max_length'] <= $columns[$name],
				$route_id . '.' . $name . ' takes at most ' . $columns[$name] . ' characters (column width)'
			);

		}

	}
}

// The two fields named in the report, by value, so a wider column has to be
// a deliberate change here as well.
function test_api_text_bounds_name_and_short_description_are_one_hundred()
{
	foreach (array('products.create', 'products.update') as $route_id) {

		$params = api_text_bounds_test_params($route_id);

		pg_assert_same(100, $params['name']['max_length'], $route_id . '.name');
		pg_assert_same(100, $params['short_description']['max_length'], $route_id . '.short_description');

	}
}
