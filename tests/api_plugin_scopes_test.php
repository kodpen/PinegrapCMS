<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what an assistant plugin is offered: every MCP tool name keeps
 * within the 64 characters Claude's directory allows and names one route,
 * and no route marked 'moves_money' sits under a scope of
 * api_plugin_scopes().
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

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

// Every route of the core and of the modules, switched on or not.
function api_plugin_scopes_test_routes()
{
	return array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes());
}

// Scope groups the way api_scope_groups() writes them, built from the scopes
// the routes use, so every module's groups are in it without a database.
function api_plugin_scopes_test_groups()
{
	$groups = array();

	foreach (api_plugin_scopes_test_routes() as $route) {
		$scope = isset($route['scope']) ? (string) $route['scope'] : '';

		if (strpos($scope, ':') === false) {
			continue;
		}

		list($key, $half) = explode(':', $scope, 2);

		if (!isset($groups[$key])) {
			$groups[$key] = array('read' => '', 'write' => '');
		}

		$groups[$key][($half === 'read') ? 'read' : 'write'] = $scope;
	}

	return $groups;
}

function test_api_plugin_scopes_tool_names_fit_and_are_unique()
{
	$long = array();
	$seen = array();
	$twice = array();

	foreach (api_plugin_scopes_test_routes() as $route) {
		if (!pg_mcp_route_is_offered($route)) {
			continue;
		}

		$id = (string) $route['id'];

		if (strlen(preg_replace('/[^A-Za-z0-9_\-]/', '_', $id)) > 64) {
			$long[] = $id;
		}

		$name = pg_mcp_tool_name($id);

		if (isset($seen[$name]) && ($seen[$name] !== $id)) {
			$twice[] = $seen[$name] . ' / ' . $id;
		}

		$seen[$name] = $id;
	}

	pg_assert_same(array(), $long, 'route ids whose tool name would pass 64 characters');
	pg_assert_same(array(), $twice, 'routes that become the same tool name');
	pg_assert_true(count($seen) > 100, 'the routes were read');
}

function test_api_plugin_scopes_hold_no_money_route()
{
	$scopes = api_plugin_scopes(api_plugin_scopes_test_groups());
	$inside = array();
	$marked = 0;

	foreach (api_plugin_scopes_test_routes() as $route) {
		if (empty($route['moves_money'])) {
			continue;
		}

		$marked++;

		if (in_array((string) $route['scope'], $scopes, true)) {
			$inside[] = $route['id'] . ' (' . $route['scope'] . ')';
		}
	}

	pg_assert_same(array(), $inside, 'money routes under a plugin scope');
	pg_assert_true($marked > 0, 'some route is marked');
}

// The routes known to move money or make a cost keep their mark, so taking
// it off one cannot slip a money route past the test above.
function test_api_plugin_scopes_known_money_routes_are_marked()
{
	$known = array(
		'commissions.update', 'customers.credit.issue', 'customers.credit.void',
		'customers.subscriptions.resume', 'erp.cash_transfers.create', 'erp.cheques.create',
		'erp.cheques.step', 'erp.expenses.pay', 'erp.receipts.cancel', 'erp.receipts.create',
		'gift_cards.create', 'gift_cards.delete', 'gift_cards.update', 'orders.create',
		'orders.labels.create', 'returns.update'
	);

	$marked = array();

	foreach (api_plugin_scopes_test_routes() as $route) {
		if (!empty($route['moves_money'])) {
			$marked[] = (string) $route['id'];
		}
	}

	pg_assert_same(array(), array_values(array_diff($known, $marked)), 'known money routes without the mark');
}

function test_api_plugin_scopes_reads_and_chosen_writes()
{
	$groups = api_plugin_scopes_test_groups();
	$scopes = api_plugin_scopes($groups);

	foreach (api_plugin_writes() as $write) {
		$key = substr($write, 0, strpos($write, ':'));
		pg_assert_true(isset($groups[$key]) && ($groups[$key]['write'] === $write), 'a route uses ' . $write);
		pg_assert_true(in_array($write, $scopes, true), $write . ' is offered');
	}

	foreach (api_assistant_scopes_left_out() as $left) {
		pg_assert_true(!in_array($left, $scopes, true), $left . ' is left out');
	}

	foreach (array('orders:write', 'gift_cards:write', 'erp_cash:write', 'commissions:write', 'customers:write', 'webhooks:manage', 'account:write', 'users:write') as $never) {
		pg_assert_true(!in_array($never, $scopes, true), $never . ' is not offered');
	}

	pg_assert_true(in_array('orders:read', $scopes, true), 'orders are read');
	pg_assert_true(in_array('erp_cash:read', $scopes, true), 'cash is read');
}

function test_api_plugin_scopes_skip_a_missing_group()
{
	$scopes = api_plugin_scopes(array(
		'pages'      => array('read' => 'pages:read', 'write' => 'pages:write'),
		'gift_cards' => array('read' => 'gift_cards:read', 'write' => 'gift_cards:write'),
		'webhooks'   => array('read' => '', 'write' => 'webhooks:manage'),
	));

	pg_assert_same(array('pages:read', 'pages:write'), $scopes);
}
