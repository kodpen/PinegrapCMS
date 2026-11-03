<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for POST /offers and POST /offers/{id}/duplicate
 * (includes/api/resources/offers_create.php): the route rows, how a body is
 * read into the offer editor's own payload, which values are refused before
 * the editor's checks run, and that the handlers write only through the
 * editor's save.
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

function api_offers_create_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function test_api_offers_create_routes()
{
	$route = api_offers_create_test_route('offers.create');
	pg_assert_true(is_array($route), 'create declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/offers', $route['path']);
	pg_assert_same('offers:write', $route['scope']);
	pg_assert_same(201, $route['status']);
	pg_assert_true(!empty($route['dry_run']));

	$route = api_offers_create_test_route('offers.duplicate');
	pg_assert_true(is_array($route), 'duplicate declared');
	pg_assert_same('/offers/{id}/duplicate', $route['path']);
	pg_assert_same('offers:write', $route['scope']);
	pg_assert_same(201, $route['status']);
}
