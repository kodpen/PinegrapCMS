<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for automation flows in the external API on a site whose Workspace is
 * off: api_modules() merges the flow routes, their objects and the run events
 * alone, under the Workspace's own ids, paths and scopes.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/modules.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows_alone.php');

// Merged alone only with the Workspace off and the flow tables there.
function test_automation_flows_api_module_switch()
{
	pg_assert_true(api_modules_flows_alone(false, true), 'Workspace off, flows ready');
	pg_assert_false(api_modules_flows_alone(true, true), 'Workspace on: its own file carries flows');
	pg_assert_false(api_modules_flows_alone(false, false), 'no flow tables');
	pg_assert_false(api_modules_flows_alone(true, false), 'neither');

	$modules = api_modules();

	pg_assert_true(isset($modules['flows']), 'the flows module is listed');
	pg_assert_same('ws_flows_alone_', $modules['flows']['prefix'], 'its prefix');
	pg_assert_true(file_exists($modules['flows']['file']), 'its file is there');
}

// The 26 flow routes, unchanged, and nothing else of the Workspace.
function test_automation_flows_api_routes()
{
	$all = ws_api_routes();
	$picked = ws_flows_alone_api_routes();
	$ids = array();
	$scopes = array();

	foreach ($picked as $route) {
		$ids[] = $route['id'];
		$scopes[$route['scope']] = isset($scopes[$route['scope']]) ? $scopes[$route['scope']] + 1 : 1;
		pg_assert_true(strpos($route['path'], '/workspace/flow') === 0, $route['id'] . ' is under /workspace/flow…');
		pg_assert_true(function_exists($route['handler']), $route['id'] . ' has its handler');
	}

	pg_assert_same(26, count($picked), 'the 26 flow routes');
	pg_assert_same(array('workspace:read' => 11, 'workspace:write' => 15), array('workspace:read' => $scopes['workspace:read'], 'workspace:write' => $scopes['workspace:write']), 'their scopes');
	pg_assert_same(2, count($scopes), 'no other scope');

	foreach ($all as $route) {
		if (strpos($route['path'], '/workspace/flow') === 0) {
			pg_assert_true(in_array($route['id'], $ids, true), $route['id'] . ' is picked');
		}
	}

	foreach ($picked as $route) {
		pg_assert_true(in_array($route, $all, true), $route['id'] . ' is the Workspace\'s own row');
	}

	pg_assert_same(array(), ws_flows_alone_pick_routes(array(array('id' => 'workspace.channels.list'), array('id' => 'workspace.flowers'))), 'other ids stay out');
}

// The four run events and the flow objects, every object a route or an
// object names among them.
function test_automation_flows_api_events_and_objects()
{
	$events = ws_flows_alone_webhook_events();

	pg_assert_same(array('workspace.flow.run.finished', 'workspace.flow.run.failed', 'workspace.flow.run.cancelled', 'workspace.flow.run.waiting'), array_keys($events), 'the four run events');

	$objects = ws_flows_alone_openapi_objects();
	$named = array();

	foreach (ws_flows_alone_api_routes() as $route) {
		$returns = is_array($route['returns']) ? $route['returns']['list'] : $route['returns'];
		$named[$returns] = true;
	}

	foreach ($objects as $name => $declare) {
		pg_assert_true(function_exists($declare), $name . ' is declared');

		foreach (call_user_func($declare) as $field) {
			if (is_string($field) && preg_match('/^(Workspace[A-Za-z]+)/', $field, $m)) {
				$named[$m[1]] = true;
			}
		}
	}

	foreach (array_keys($named) as $name) {
		pg_assert_true(isset($objects[$name]), $name . ' is merged');
	}
}

// The permission row and the ceiling are the two scopes the routes ask.
function test_automation_flows_api_scopes()
{
	$groups = ws_flows_alone_scope_groups();

	pg_assert_same(array('workspace'), array_keys($groups), 'one row');
	pg_assert_same('workspace:read', $groups['workspace']['read'], 'read scope unchanged');
	pg_assert_same('workspace:write', $groups['workspace']['write'], 'write scope unchanged');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows_alone.php');

	pg_assert_true(strpos($source, "array_intersect(ws_owner_scopes(\$owner), array('workspace:read', 'workspace:write'))") !== false, 'the ceiling is the Workspace\'s, kept to the two scopes');
}
