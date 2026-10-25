<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /products/{id}/marketplaces (includes/api/resources/products.php):
 * the route row, the ProductMarketplace presenter against its declared schema,
 * the manager-only rule that matches the marketplace screen, and that no
 * account credentials are read.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\TH:i:s\Z', (int) $unix) : null;
	}
}

function api_product_marketplaces_test_row($overrides = array())
{
	return array_merge(array(
		'account_id'        => '3',
		'account_name'      => 'Trendyol Store',
		'provider'          => 'trendyol',
		'account_status'    => 'active',
		'remote_code'       => '8690000000017',
		'remote_id'         => '1234567',
		'status'            => 'active',
		'last_pushed'       => '1761300000',
		'last_error'        => '',
		'created_timestamp' => '1761200000',
	), $overrides);
}

function api_product_marketplaces_test_route()
{
	foreach (api_schema() as $route) {
		if ($route['id'] === 'products.marketplaces') {
			return $route;
		}
	}

	return null;
}

function test_api_product_marketplaces_route()
{
	$route = api_product_marketplaces_test_route();

	pg_assert_true(is_array($route), 'the route is declared');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/products/{id}/marketplaces', $route['path']);
	pg_assert_same('products:read', $route['scope']);
	pg_assert_same('api_products_marketplaces', $route['handler']);
	pg_assert_same(array('list' => 'ProductMarketplace'), $route['returns']);
	pg_assert_contains('manager', $route['description'], 'the description says who may read it');
}

function test_api_product_marketplaces_present()
{
	$listed = api_product_marketplace_present(api_product_marketplaces_test_row());

	pg_assert_same(array_keys(api_product_marketplace_schema()), array_keys($listed), 'every field declared, in order');
	pg_assert_same(3, $listed['account_id']);
	pg_assert_same('Trendyol Store', $listed['account_name']);
	pg_assert_same('trendyol', $listed['provider']);
	pg_assert_same('active', $listed['account_status']);
	pg_assert_same('8690000000017', $listed['remote_code']);
	pg_assert_same('1234567', $listed['remote_id']);
	pg_assert_same('active', $listed['status']);
	pg_assert_same(null, $listed['last_error']);
	pg_assert_same('2025-10-24T10:00:00Z', $listed['last_pushed_at']);
	pg_assert_same('2025-10-23T06:13:20Z', $listed['created_at']);

	$waiting = api_product_marketplace_present(api_product_marketplaces_test_row(array(
		'remote_id'   => '',
		'status'      => 'error',
		'last_error'  => 'Rejected by Trendyol: brand: missing',
		'last_pushed' => '0',
	)));

	pg_assert_same(null, $waiting['remote_id'], 'no remote id before the marketplace gave one');
	pg_assert_same('error', $waiting['status']);
	pg_assert_same('Rejected by Trendyol: brand: missing', $waiting['last_error']);
	pg_assert_same(null, $waiting['last_pushed_at'], 'never pushed');
	pg_assert_true(!array_key_exists('credentials', $waiting), 'no credentials in the answer');
}

function test_api_product_marketplaces_manager_only()
{
	pg_assert_true(api_product_marketplaces_allowed(array('role' => '0')), 'administrator');
	pg_assert_true(api_product_marketplaces_allowed(array('role' => '1')), 'designer');
	pg_assert_true(api_product_marketplaces_allowed(array('role' => '2')), 'manager');
	pg_assert_true(!api_product_marketplaces_allowed(array('role' => '3', 'manage_ecommerce' => '1')), 'a user with commerce rights does not see the marketplace screen either');
	pg_assert_true(!api_product_marketplaces_allowed(array()), 'no role');
}

function test_api_product_marketplaces_reads_no_credentials()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');
	$start = strpos($source, 'function api_products_marketplaces(');

	pg_assert_true($start !== false, 'the handler exists');

	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_same(false, stripos($body, 'credentials'), 'the account blob is never read');
	pg_assert_contains('api_product_marketplaces_allowed(', $body);
	pg_assert_contains("api_fail(403, 'forbidden'", $body);
}

function test_api_product_marketplaces_registered()
{
	$objects = api_openapi_objects();

	pg_assert_same('api_product_marketplace_schema', $objects['ProductMarketplace']);
}
