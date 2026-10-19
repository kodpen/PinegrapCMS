<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's product price history in
 * includes/api/resources/products.php: the shape of one history row, the
 * initial row the history starts with, and the route that lists the rows
 * under the products read scope, cursor paged.
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
// init.php. Behind the gate both files only declare functions (no query, no
// configuration read at load), so the gate is answered here: nothing under
// tests/ calls the outbound HTTP client.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// The presenter's value helpers live in includes/api/response.php, which
// loads only behind the API's own entry. Stand-ins of the same shape, so the
// presenter can be read without it.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

if (!function_exists('api_money')) {
	function api_money($minor_units)
	{
		return (int) $minor_units;
	}
}

// A row as the endpoint reads it from product_price_history.
function api_price_history_test_row($changes = array())
{
	return array_merge(array(
		'id' => '42', 'channel' => 'web', 'price' => '8250', 'currency' => 'TRY',
		'changed_at' => '1760000000', 'source' => 'panel',
	), $changes);
}

function api_price_history_test_route()
{
	foreach (api_schema() as $route) {
		if ($route['id'] === 'products.price_history') {
			return $route;
		}
	}

	return null;
}

function test_api_price_history_entry_shape()
{
	$entry = api_price_history_entry_present(api_price_history_test_row());

	pg_assert_same(42, $entry['id']);
	pg_assert_same('web', $entry['channel']);
	pg_assert_same(8250, $entry['price'], 'minor units, as every price on the wire');
	pg_assert_same('TRY', $entry['currency']);
	pg_assert_same('panel', $entry['source']);
	pg_assert_same('2025-10-09T08:53:20Z', $entry['changed_at']);
	pg_assert_same(1760000000, $entry['changed_at_unix']);

	pg_assert_same(array_keys(api_price_history_entry_schema()), array_keys($entry), 'every field declared, in order');
}

// The first row of a product: the price it had when the history began, in the
// currency the migration read, with the upgrade's own moment.
function test_api_price_history_initial_row()
{
	$entry = api_price_history_entry_present(api_price_history_test_row(array(
		'id' => '1', 'price' => '7995', 'source' => 'initial', 'changed_at' => '1759900000',
	)));

	pg_assert_same('initial', $entry['source']);
	pg_assert_same(7995, $entry['price']);
	pg_assert_same(1759900000, $entry['changed_at_unix']);
	pg_assert_same('2025-10-08T05:06:40Z', $entry['changed_at']);
}

// Values come from the database as strings and may be empty: a free product
// is a price, an empty currency is a string, and a per-channel row keeps its
// channel name.
function test_api_price_history_entry_edges()
{
	$entry = api_price_history_entry_present(api_price_history_test_row(array(
		'price' => '0', 'currency' => '', 'channel' => 'marketplace:n11', 'source' => 'api',
	)));

	pg_assert_same(0, $entry['price'], 'a free product is a price too');
	pg_assert_same('', $entry['currency'], 'never null: declared as a string');
	pg_assert_same('marketplace:n11', $entry['channel']);
	pg_assert_same('api', $entry['source']);
}

function test_api_price_history_route_lists_rows_under_the_read_scope()
{
	$route = api_price_history_test_route();

	pg_assert_true($route !== null, 'the route exists');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/products/{id}/price-history', $route['path']);
	pg_assert_same('products:read', $route['scope'], 'reading the history is reading the product');
	pg_assert_same('api_products_price_history', $route['handler']);
	pg_assert_true(function_exists($route['handler']), 'the handler is declared');
	pg_assert_same(array('list' => 'PriceHistoryEntry'), $route['returns']);
	pg_assert_false(isset($route['mcp']) && ($route['mcp'] === false), 'offered to the MCP tools like every read');
}

// The listing is paged the way the catalogue listing is: a page size with the
// API's ceiling, a cursor, and the moment to read from; the channel narrows.
function test_api_price_history_route_params()
{
	$route = api_price_history_test_route();

	$params = array();

	foreach ($route['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_same(array('id', 'since', 'channel', 'limit', 'cursor'), array_keys($params));

	pg_assert_same('path', $params['id']['in']);
	pg_assert_same('int', $params['id']['type']);
	pg_assert_true(!empty($params['id']['required']));

	pg_assert_same('query', $params['since']['in']);
	pg_assert_same('datetime', $params['since']['type'], 'ISO-8601, a day or a unix stamp, like updated_since');
	pg_assert_true(empty($params['since']['required']), 'the whole history when left out');

	pg_assert_same('string', $params['channel']['type']);
	pg_assert_same(64, $params['channel']['max_length'], 'the width of product_price_history.channel');

	pg_assert_same('int', $params['limit']['type']);
	pg_assert_same(1, $params['limit']['min']);
	pg_assert_same(250, $params['limit']['max'], 'the ceiling every listing of the API has');
	pg_assert_same(50, $params['limit']['default']);

	pg_assert_same('string', $params['cursor']['type']);
}

// The object the route answers with is registered for the OpenAPI document,
// and the declaration is the presenter's.
function test_api_price_history_object_is_declared()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');

	$objects = api_openapi_objects();

	pg_assert_true(isset($objects['PriceHistoryEntry']), 'registered under the name the route uses');
	pg_assert_same('api_price_history_entry_schema', $objects['PriceHistoryEntry']);
	pg_assert_true(function_exists($objects['PriceHistoryEntry']));

	$schema = api_price_history_entry_schema();

	pg_assert_same('string?', $schema['changed_at'], 'null only for a stamp of zero, which api_time() answers null');
	pg_assert_same('integer', $schema['price']);
}
