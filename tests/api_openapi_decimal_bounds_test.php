<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a decimal field's bounds reach the OpenAPI document and the MCP
 * tool schema. The router refuses a decimal outside its min and max
 * (api_validate_bounds()), so a client generated from the document, or an
 * MCP client reading the tool's input schema, has to be told them: a tax
 * rate of 150 otherwise passes the client's own validation and comes back
 * as a 422 it was never warned about.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');

function test_api_openapi_decimal_carries_its_bounds()
{
	$schema = api_openapi_type(array('name' => 'tax_rate', 'type' => 'decimal', 'min' => 0, 'max' => 100));

	pg_assert_same('number', $schema['type'], 'type');
	pg_assert_same(0, $schema['minimum'], 'minimum');
	pg_assert_same(100, $schema['maximum'], 'maximum');
}

function test_api_openapi_decimal_without_bounds_stays_plain()
{
	pg_assert_same(array('type' => 'number'), api_openapi_type(array('name' => 'x', 'type' => 'decimal')), 'no bounds declared');
}

// Every decimal the route table bounds is bounded in the document too.
function test_api_openapi_every_bounded_decimal_route_field_is_documented()
{
	$missing = array();

	foreach (api_schema() as $route) {
		foreach ($route['params'] as $param) {
			if (!isset($param['type']) || ($param['type'] !== 'decimal')) {
				continue;
			}

			$schema = api_openapi_type($param);

			foreach (array('min' => 'minimum', 'max' => 'maximum') as $key => $keyword) {
				if (isset($param[$key]) && (!isset($schema[$keyword]) || ($schema[$keyword] != $param[$key]))) {
					$missing[] = $route['id'] . ' ' . $param['name'] . ' ' . $keyword;
				}
			}
		}
	}

	pg_assert_same(array(), $missing, 'decimal bounds left out of the document');
}
