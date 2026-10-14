<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workspace's route table in the external API
 * (includes/workspace/api.php) that need no database: every route reaches a
 * handler, no two routes answer the same method on the same path, and each
 * action becomes one MCP tool. Also the scopes the search asks of each kind
 * of panel record (includes/workspace/api_day.php), and who a call from a
 * device writes as.
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

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_ws_api_routes_reach_a_handler()
{
	$ids = array();

	foreach (ws_api_routes() as $route) {
		pg_assert_false(isset($ids[$route['id']]), $route['id'] . ' is declared once');
		$ids[$route['id']] = true;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');
		pg_assert_true(in_array($route['scope'], array('workspace:read', 'workspace:write', 'tasks:read', 'tasks:write'), true), $route['id'] . ' asks for one of the module\'s scopes');
	}
}

// The router takes the first route that matches, so a method a route accepts
// beside its own must not be one another route on the same path answers.
function test_ws_api_routes_do_not_share_a_method_and_path()
{
	$seen = array();

	foreach (ws_api_routes() as $route) {
		$methods = array_merge(array($route['method']), (array) ($route['also_accepts'] ?? array()));

		foreach ($methods as $method) {
			$key = $method . ' ' . $route['path'];

			pg_assert_false(isset($seen[$key]), $key . ' is ' . $route['id'] . (isset($seen[$key]) ? ' and ' . $seen[$key] : ''));
			$seen[$key] = $route['id'];
		}
	}
}

// A removal addressed as a POST for IIS is the same action as the DELETE, and
// is not offered as a second tool.
function test_ws_api_plan_item_removal_is_one_tool()
{
	$offered = array();

	foreach (ws_api_routes() as $route) {
		if (pg_mcp_route_is_offered($route)) {
			$offered[] = pg_mcp_tool_name($route['id']);
		}
	}

	pg_assert_contains('workspace_plan_items_delete', $offered);
	pg_assert_false(in_array('workspace_plan_items_delete_post', $offered, true), 'the POST alias is no tool');

	foreach (array('workspace_notes_create', 'workspace_notes_update', 'workspace_plan_items_list', 'workspace_plan_items_create',
		'workspace_plan_items_update', 'workspace_search', 'workspace_my_day_get') as $tool) {
		pg_assert_contains($tool, $offered);
	}
}

// A message is changed, deleted and a channel marked read with one tool each;
// the deletion addressed as a POST is no second tool.
function test_ws_api_message_edit_delete_and_read_are_tools()
{
	$offered = array();

	foreach (ws_api_routes() as $route) {
		if (pg_mcp_route_is_offered($route)) {
			$offered[] = pg_mcp_tool_name($route['id']);
		}
	}

	foreach (array('workspace_messages_update', 'workspace_messages_delete', 'workspace_channels_read') as $tool) {
		pg_assert_contains($tool, $offered);
	}

	pg_assert_false(in_array('workspace_messages_delete_post', $offered, true), 'the POST alias is no tool');
}

// The list reads changed messages with changed_since, a moment.
function test_ws_api_messages_list_takes_changed_since()
{
	$types = array();

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.messages.list') {
			foreach ($route['params'] as $param) {
				$types[$param['name']] = $param['type'];
			}
		}
	}

	pg_assert_true(($types['changed_since'] ?? '') === 'datetime', 'changed_since is a datetime');
}

// The search looks in a kind of record only with the scope that reads it; a
// kind it names has to be one a tag can point at.
function test_ws_api_search_record_scopes_name_known_kinds()
{
	foreach (ws_api_search_record_scopes() as $kind => $scope) {
		pg_assert_contains($kind, ws_record_type_keys(), $kind . ' is a record kind');
		pg_assert_true((bool) preg_match('/^[a-z_]+:read$/', $scope), $kind . ' asks for a read scope');
	}

	pg_assert_false(array_key_exists('user_account', ws_api_search_record_scopes()), 'no scope reads user accounts');
}

// A person signed in on their own device writes in their own name; an
// application key, and an OAuth connection (a device row with a client),
// write as the application.
function test_ws_api_device_writes_as_person()
{
	pg_assert_false(ws_api_device_writes_as_person(null), 'an application key writes as the application');
	pg_assert_true(ws_api_device_writes_as_person(array('id' => 4, 'app_id' => 2)), 'a team devices session writes as the person');
	pg_assert_true(ws_api_device_writes_as_person(array('id' => 4, 'app_id' => 2, 'oauth_client_id' => '0')), 'a device row with no client writes as the person');
	pg_assert_false(ws_api_device_writes_as_person(array('id' => 5, 'app_id' => 3, 'oauth_client_id' => '7')), 'an OAuth connection writes as the application');
}
