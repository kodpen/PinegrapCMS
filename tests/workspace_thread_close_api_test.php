<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's routes that conclude or rename a discussion
 * and take back or close an approval request
 * (includes/workspace/api_thread_close.php) that need no database: each
 * route is declared with its scope and is an MCP tool, the writes rehearse
 * and stop between the check they share with the panel and the panel's
 * function, and the panel's functions run that same check.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// The routes of the workspace, by id.
function ws_thread_close_api_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

// The source of a function, to read the order of its calls.
function ws_thread_close_api_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

function test_ws_thread_close_api_routes_are_declared()
{
	$routes = ws_thread_close_api_test_routes();
	$expected = array(
		'workspace.threads.close'   => array('/workspace/threads/{id}/close', 'WorkspaceThread'),
		'workspace.threads.update'  => array('/workspace/threads/{id}', 'WorkspaceThread'),
		'workspace.approvals.close' => array('/workspace/approvals/{id}/close', 'WorkspaceApproval'),
	);

	foreach ($expected as $id => $shape) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same('POST', $routes[$id]['method'], $id . ' is a POST');
		pg_assert_same($shape[0], $routes[$id]['path'], $id . ' path');
		pg_assert_same('workspace:write', $routes[$id]['scope'], $id . ' scope');
		pg_assert_same($shape[1], $routes[$id]['returns'], $id . ' answers');
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes dry_run');
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' is an MCP tool: who may is the panel function\'s rule');
	}

	pg_assert_same(array('PATCH'), $routes['workspace.threads.update']['also_accepts'], 'a rename is also a PATCH');
	pg_assert_same(WS_THREAD_TITLE_MAX, $routes['workspace.threads.update']['params'][1]['max_length'], 'the title is held to the discussion\'s own limit');
}

// Each write stops after the check it shares with the panel, before the
// panel's function writes; the panel's function runs the same check.
function test_ws_thread_close_api_writes_rehearse_after_the_shared_check()
{
	$routes = ws_thread_close_api_test_routes();
	$pairs = array(
		'workspace.threads.close'   => array('ws_thread_close_check(', 'ws_thread_close($viewer', 'ws_thread_close'),
		'workspace.threads.update'  => array('ws_thread_rename_check(', 'ws_thread_rename($viewer', 'ws_thread_rename'),
		'workspace.approvals.close' => array('ws_approval_close_check(', 'ws_approval_close($viewer', 'ws_approval_close'),
	);

	foreach ($pairs as $id => $calls) {
		$source = ws_thread_close_api_test_source($routes[$id]['handler']);
		$check = strpos($source, $calls[0]);
		$stop = strpos($source, 'api_dry_run_stop(');
		$write = strpos($source, $calls[1]);

		pg_assert_true(($check !== false) && ($stop !== false) && ($write !== false), $id . ': check, stop and write are all there');
		pg_assert_true(($check < $stop) && ($stop < $write), $id . ': the stop sits between the check and the write');
		pg_assert_contains($calls[0], ws_thread_close_api_test_source($calls[2]), $calls[2] . '() runs the same check');
	}
}
