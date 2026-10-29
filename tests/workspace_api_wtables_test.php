<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for workflow tables in the external API
 * (includes/workspace/api_wtables.php) that need no database: the routes,
 * which are MCP tools, that every write runs the store's check before the
 * rehearsal stops it, and how a row is handed out.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function ws_api_wtables_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		if ((strpos($route['id'], 'workspace.table') === 0)) {
			$routes[$route['id']] = $route;
		}
	}

	return $routes;
}

function ws_api_wtables_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function test_ws_api_wtables_routes()
{
	$routes = ws_api_wtables_test_routes();

	$expected = array(
		'workspace.tables.list'               => array('GET', '/workspace/tables', 'workspace:read'),
		'workspace.tables.get'                => array('GET', '/workspace/tables/{id}', 'workspace:read'),
		'workspace.tables.create'             => array('POST', '/workspace/tables', 'workspace:write'),
		'workspace.tables.update'             => array('POST', '/workspace/tables/{id}', 'workspace:write'),
		'workspace.tables.delete'             => array('DELETE', '/workspace/tables/{id}', 'workspace:write'),
		'workspace.tables.delete_post'        => array('POST', '/workspace/tables/{id}/delete', 'workspace:write'),
		'workspace.table_columns.create'      => array('POST', '/workspace/tables/{id}/columns', 'workspace:write'),
		'workspace.table_columns.update'      => array('POST', '/workspace/tables/{id}/columns/{column_id}', 'workspace:write'),
		'workspace.table_columns.delete'      => array('DELETE', '/workspace/tables/{id}/columns/{column_id}', 'workspace:write'),
		'workspace.table_columns.delete_post' => array('POST', '/workspace/tables/{id}/columns/{column_id}/delete', 'workspace:write'),
		'workspace.table_rows.list'           => array('GET', '/workspace/tables/{id}/rows', 'workspace:read'),
		'workspace.table_rows.get'            => array('GET', '/workspace/tables/{id}/rows/{row_id}', 'workspace:read'),
		'workspace.table_rows.create'         => array('POST', '/workspace/tables/{id}/rows', 'workspace:write'),
		'workspace.table_rows.update'         => array('POST', '/workspace/tables/{id}/rows/{row_id}', 'workspace:write'),
		'workspace.table_rows.move'           => array('POST', '/workspace/tables/{id}/rows/{row_id}/move', 'workspace:write'),
		'workspace.table_rows.delete'         => array('DELETE', '/workspace/tables/{id}/rows/{row_id}', 'workspace:write'),
		'workspace.table_rows.delete_post'    => array('POST', '/workspace/tables/{id}/rows/{row_id}/delete', 'workspace:write'),
	);

	pg_assert_same(array_keys($expected), array_keys($routes), 'the routes, in order');

	foreach ($expected as $id => $shape) {
		if (isset($routes[$id])) {
			pg_assert_same($shape, array($routes[$id]['method'], $routes[$id]['path'], $routes[$id]['scope']), $id . ' method, path, scope');
			pg_assert_true(function_exists($routes[$id]['handler']), $id . ' reaches a handler');
			pg_assert_same($routes[$id]['method'] !== 'GET', !empty($routes[$id]['dry_run']), $id . ': a write rehearses');
		}
	}

	foreach (array('workspace.tables.update', 'workspace.table_columns.update', 'workspace.table_rows.update') as $id) {
		pg_assert_same(array('PATCH'), $routes[$id]['also_accepts'] ?? null, $id . ' takes PATCH too');
	}

	foreach (array('tables', 'table_columns', 'table_rows') as $kind) {
		pg_assert_same($routes['workspace.' . $kind . '.delete']['handler'], $routes['workspace.' . $kind . '.delete_post']['handler'], $kind . ': the POST twin is the same handler');
	}
}

// Reading and writing tables, columns and rows are tools, as notes are;
// deleting a table or a column cannot be undone: those are no tools.
function test_ws_api_wtables_mcp_tools()
{
	$routes = ws_api_wtables_test_routes();
	$no_tools = array(
		'workspace.tables.delete', 'workspace.tables.delete_post',
		'workspace.table_columns.delete', 'workspace.table_columns.delete_post',
	);

	foreach ($routes as $id => $route) {
		if (in_array($id, $no_tools, true)) {
			pg_assert_same(false, $route['mcp'] ?? null, $id . ' carries \'mcp\' => false');
			pg_assert_false(pg_mcp_route_is_offered($route), $id . ' is no tool');
		} elseif (substr($id, -5) !== '_post') {
			pg_assert_true(pg_mcp_route_is_offered($route), $id . ' is a tool');
		}
	}
}

// Every write runs the store's own check, then the rehearsal stops it, then
// the write: a dry run meets the refusals of the real call.
function test_ws_api_wtables_writes_check_before_the_rehearsal()
{
	$writes = array(
		'ws_api_wtables_create'        => array('ws_wtable_create_check(', 'ws_wtable_create('),
		'ws_api_wtables_update'        => array('ws_wtable_update_check(', 'ws_wtable_update('),
		'ws_api_wtables_delete'        => array('ws_wtable_delete_check(', 'ws_wtable_delete('),
		'ws_api_wtable_columns_create' => array('ws_wtable_column_create_check(', 'ws_wtable_column_create('),
		'ws_api_wtable_columns_update' => array('ws_wtable_column_update_check(', 'ws_wtable_column_update('),
		'ws_api_wtable_columns_delete' => array('ws_wtable_column_delete_check(', 'ws_wtable_column_delete('),
		'ws_api_wtable_rows_create'    => array('ws_wtable_row_create_check(', 'ws_wtable_row_create('),
		'ws_api_wtable_rows_update'    => array('ws_wtable_row_update_check(', 'ws_wtable_row_update('),
		'ws_api_wtable_rows_move'      => array('ws_wtable_row_move_check(', 'ws_wtable_row_move('),
		'ws_api_wtable_rows_delete'    => array('ws_wtable_row_delete_check(', 'ws_wtable_row_delete('),
	);

	foreach ($writes as $handler => $calls) {
		$source = ws_api_wtables_test_source($handler);
		$check = strpos($source, $calls[0]);
		$stop = strpos($source, 'api_dry_run_stop(');
		$write = strpos($source, $calls[1] . '$viewer');

		pg_assert_true(($check !== false) && ($stop !== false) && ($write !== false), $handler . ': check, rehearsal and write');
		pg_assert_true(($check < $stop) && ($stop < $write), $handler . ': in that order');
	}

	// The column delete the screen and the API share has a check of its own.
	pg_assert_contains('ws_wtable_column_delete_check(', ws_api_wtables_test_source('ws_wtable_column_delete'));
}

// A row is handed out with its cells as an object, an empty one too, so a
// client never meets [] where it reads cells by column id.
function test_ws_api_wtables_row_present()
{
	$row = array(
		'id' => '7', 'position' => '2', 'revision' => '3',
		'cells' => array(), 'formula_errors' => array(),
		'created_by' => '4', 'created_at' => 0, 'updated_by' => '5', 'updated_at' => 86400,
	);

	$out = ws_wtable_row_present($row);

	pg_assert_same('{}', json_encode($out['cells']), 'empty cells are an object');
	pg_assert_same('{}', json_encode($out['formula_errors']));
	pg_assert_same(null, $out['created_at'], 'no time is null');
	pg_assert_same('1970-01-02T00:00:00Z', $out['updated_at']);
	pg_assert_same(array(7, 2, 3, 4, 5), array($out['id'], $out['position'], $out['revision'], $out['created_by'], $out['updated_by']));

	$row['cells'] = array('12' => 1500, '14' => 'Paid');
	pg_assert_same('{"12":1500,"14":"Paid"}', json_encode(ws_wtable_row_present($row)['cells']), 'by column id');
	pg_assert_same(array_keys(ws_api_wtable_row_schema()), array_keys($out), 'the schema names every field');
	pg_assert_contains('ws_claude_channel_allowed(', ws_api_wtables_test_source('ws_api_wtable_reachable'), 'Claude reads a channel\'s table where it may be asked');
}

function test_ws_api_wtables_objects_declared()
{
	$objects = ws_openapi_objects();

	foreach (array('WorkspaceTable', 'WorkspaceTableColumn', 'WorkspaceTableRow') as $name) {
		pg_assert_true(isset($objects[$name]) && function_exists($objects[$name]), $name . ' is declared');
	}
}
