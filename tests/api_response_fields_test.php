<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which answers ?fields= trims row by row (api_route_returns_listing(),
 * includes/api/openapi.php): a paged listing, and GET /webhooks, whose rows sit
 * in data beside the event catalogue with no page.
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

// A core route by id.
function api_response_fields_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

// GET /webhooks answers data and events without page. Read as one record, its
// answer to ?fields=url lost both and came back as [].
function test_api_response_fields_webhooks_list_is_a_listing()
{
	$route = api_response_fields_test_route('webhooks.list');

	pg_assert_true(is_array($route), 'the route exists');
	pg_assert_true(api_route_returns_listing($route), 'its rows are trimmed one by one');
}

// A paged listing is one too; a single record and a record that merely has
// nested fields are not.
function test_api_response_fields_tells_listings_from_records()
{
	pg_assert_true(api_route_returns_listing(api_response_fields_test_route('products.list')), 'products.list');
	pg_assert_false(api_route_returns_listing(api_response_fields_test_route('products.get')), 'products.get');
	pg_assert_false(api_route_returns_listing(api_response_fields_test_route('meta.read')), 'meta.read');
	pg_assert_false(api_route_returns_listing(array('id' => 'x', 'returns' => array('data' => 'Webhook'))), 'data as one object');
	pg_assert_false(api_route_returns_listing(array('id' => 'x')), 'nothing declared');
}
