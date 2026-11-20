<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the POST twins of the external API's deletes, without a
 * database: every DELETE route has a POST …/delete twin with the same
 * handler, and the two older deletes addressed by a plain POST (webhooks,
 * scheduled messages) keep answering there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/router.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// Every declared route of the core and the modules.
function api_delete_twins_test_routes()
{
	return array_merge(api_schema(), erp_api_routes(), translate_api_routes(), ws_api_routes());
}

// A delete is reached by POST …/delete as well, under the id ending in
// _post, with the same handler, scope and rehearsal.
function test_api_delete_twins_every_delete_has_one()
{
	$routes = api_delete_twins_test_routes();
	$by_address = array();

	foreach ($routes as $route) {
		$by_address[$route['method'] . ' ' . $route['path']] = $route;
	}

	$deletes = 0;

	foreach ($routes as $route) {
		if ($route['method'] !== 'DELETE') {
			continue;
		}

		$deletes++;
		$address = 'POST ' . $route['path'] . '/delete';

		pg_assert_true(isset($by_address[$address]), $route['id'] . ' has ' . $address);

		if (!isset($by_address[$address])) {
			continue;
		}

		$twin = $by_address[$address];

		pg_assert_same($route['id'] . '_post', $twin['id'], $address . ' id');
		pg_assert_same($route['handler'], $twin['handler'], $twin['id'] . ' handler');
		pg_assert_same($route['scope'], $twin['scope'], $twin['id'] . ' scope');
		pg_assert_same(!empty($route['dry_run']), !empty($twin['dry_run']), $twin['id'] . ' dry run');
		pg_assert_false(pg_mcp_route_is_offered($twin), $twin['id'] . ' is no second tool');
	}

	pg_assert_true($deletes > 50, 'the deletes were read');
}

// The webhook and scheduled message deletes were reached by a plain POST
// before their twins existed; that address keeps deleting.
function test_api_delete_twins_older_plain_post_keeps_deleting()
{
	$routes = array();

	foreach (api_delete_twins_test_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$cases = array(
		'webhooks.delete' => '/webhooks/{id}',
		'workspace.scheduled_messages.delete' => '/workspace/scheduled-messages/{id}',
	);

	foreach ($cases as $id => $path) {
		pg_assert_same($path, $routes[$id]['path'], $id . ' path');
		pg_assert_same(array('POST'), $routes[$id]['also_accepts'] ?? null, $id . ' takes a plain POST');
		pg_assert_true(strpos($routes[$id]['description'], 'POST ' . $path . '/delete') !== false, $id . ' names the twin');
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' stays the tool');
	}

	// The removal answers 204 at both addresses.
	pg_assert_same(204, $routes['webhooks.delete_post']['status'] ?? null, 'webhook twin 204');
}

// The twin's address never falls to another route first: the router takes
// the first match, and an integer id matches digits only.
function test_api_delete_twins_addresses_reach_the_twin()
{
	$routes = api_delete_twins_test_routes();

	$cases = array(
		'/webhooks/7/delete' => 'webhooks.delete_post',
		'/webhooks/7' => 'webhooks.delete',
		'/workspace/scheduled-messages/7/delete' => 'workspace.scheduled_messages.delete_post',
		'/workspace/scheduled-messages/7' => 'workspace.scheduled_messages.delete',
	);

	foreach ($cases as $path => $expected) {
		$found = null;

		foreach ($routes as $route) {
			$methods = array_merge(array($route['method']), $route['also_accepts'] ?? array());

			if (in_array('POST', $methods, true) && preg_match(api_route_pattern($route), $path)) {
				$found = $route['id'];
				break;
			}
		}

		pg_assert_same($expected, $found, 'POST ' . $path);
	}
}
