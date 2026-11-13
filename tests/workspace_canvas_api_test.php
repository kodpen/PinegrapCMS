<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the canvas as one thing in the external API and MCP
 * (includes/workspace/api_canvases.php): the one list of versions over the
 * parts, the rows of the routes and the tool names they give.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function ws_test_canvas_one_routes()
{
	$rows = array();

	foreach (ws_canvases_api_routes() as $route) {
		$rows[$route['id']] = $route;
	}

	return $rows;
}

function test_workspace_canvas_one_versions_merge()
{
	$merged = ws_canvas_versions_merge(array(
		'drawing' => array(
			array('version' => 1, 'source' => 'agent', 'user_id' => 3, 'agent_id' => 9, 'agent' => 'claude', 'created_at' => 100),
			array('version' => 2, 'source' => 'user', 'user_id' => 3, 'agent_id' => null, 'agent' => null, 'created_at' => 300),
		),
		'page'    => array(
			array('version' => 1, 'source' => 'agent', 'user_id' => 3, 'agent_id' => 9, 'agent' => 'claude', 'size' => 2048, 'created_at' => 200),
		),
	));

	pg_assert_same(3, count($merged));
	pg_assert_same(array('drawing', 'page', 'drawing'), array_map(function ($row) {
		return $row['part'];
	}, $merged), 'oldest first over the parts');
	pg_assert_same(array(1, 1, 2), array_map(function ($row) {
		return $row['version'];
	}, $merged), 'each part keeps its own numbering');
	pg_assert_same(2048, $merged[1]['size'], 'a page version says how big it is');
	pg_assert_same(null, $merged[0]['size'], 'a drawing version has no size');
	pg_assert_same('claude', $merged[0]['agent']);
	pg_assert_same(300, $merged[2]['created_at'], 'the moment is given as it came');
}

function test_workspace_canvas_one_versions_merge_same_moment()
{
	$merged = ws_canvas_versions_merge(array(
		'page'    => array(
			array('version' => 7, 'source' => 'agent', 'created_at' => 500),
			array('version' => 8, 'source' => 'restore', 'created_at' => 500),
		),
		'drawing' => array(array('version' => 4, 'source' => 'user', 'created_at' => 500)),
	));

	pg_assert_same(array('drawing', 'page', 'page'), array_map(function ($row) {
		return $row['part'];
	}, $merged), 'versions of one second keep their part together');
	pg_assert_same(array(4, 7, 8), array_map(function ($row) {
		return $row['version'];
	}, $merged), 'and a part never reads as if it went backwards');
	pg_assert_same(array(), ws_canvas_versions_merge(array()), 'no parts, no versions');
	pg_assert_same(array(), ws_canvas_versions_merge(array('page' => array())));
}

function test_workspace_canvas_one_routes_are_declared()
{
	$rows = ws_test_canvas_one_routes();
	$expected = array(
		'workspace.canvases.get'                 => array('GET', '/workspace/canvases/{id}', 'workspace:read'),
		'workspace.canvases.tables.bind'         => array('POST', '/workspace/canvases/{id}/tables', 'workspace:write'),
		'workspace.canvases.tables.unbind'       => array('DELETE', '/workspace/canvases/{id}/tables/{table_id}', 'workspace:write'),
		'workspace.canvases.tables.unbind_post'  => array('POST', '/workspace/canvases/{id}/tables/{table_id}/delete', 'workspace:write'),
		'workspace.canvases.page.put'            => array('POST', '/workspace/canvases/{id}/page', 'workspace:write'),
		'workspace.canvases.drawing.put'         => array('POST', '/workspace/canvases/{id}/drawing', 'workspace:write'),
	);

	pg_assert_same(array_keys($expected), array_keys($rows), 'the rows the step declares');

	foreach ($expected as $id => $says) {
		pg_assert_same($says[0], $rows[$id]['method'], $id . ' method');
		pg_assert_same($says[1], $rows[$id]['path'], $id . ' path');
		pg_assert_same($says[2], $rows[$id]['scope'], $id . ' scope');
		pg_assert_true(function_exists($rows[$id]['handler']), $id . ': ' . $rows[$id]['handler'] . '() exists');
		pg_assert_true(!empty($rows[$id]['returns']), $id . ' says what it answers with');
	}

	// IIS answers PATCH and DELETE with 405 of its own, so a write is a
	// POST and the delete has its POST twin.
	foreach (array('workspace.canvases.page.put', 'workspace.canvases.drawing.put') as $id) {
		pg_assert_same(array('PATCH'), $rows[$id]['also_accepts'], $id . ' takes PATCH as well');
	}

	foreach ($rows as $id => $route) {
		if ($id !== 'workspace.canvases.get') {
			pg_assert_true(!empty($route['dry_run']), $id . ' rehearses its write');
		}
	}
}

function test_workspace_canvas_one_tool_names()
{
	$names = array();

	foreach (ws_test_canvas_one_routes() as $route) {
		if (pg_mcp_route_is_offered($route)) {
			$names[] = pg_mcp_tool_name($route['id']);
		}
	}

	pg_assert_same(array(
		'workspace_canvases_get',
		'workspace_canvases_tables_bind',
		'workspace_canvases_tables_unbind',
		'workspace_canvases_page_put',
		'workspace_canvases_drawing_put',
	), $names, 'the tools of the canvas, the POST twin of the delete left out');
}

function test_workspace_canvas_one_params_come_from_the_parts()
{
	$rows = ws_test_canvas_one_routes();
	$names = function ($route) {
		return array_map(function ($param) {
			return $param['name'];
		}, $route['params']);
	};

	pg_assert_same(array('id', 'version', 'html', 'libraries'), $names($rows['workspace.canvases.page.put']), 'the page part is written as the page route writes it');
	pg_assert_same(array('id', 'version', 'svg', 'background', 'layers'), $names($rows['workspace.canvases.drawing.put']), 'the drawing from SVG, as it is drawn');

	foreach ($rows['workspace.canvases.drawing.put']['params'] as $param) {
		if ($param['name'] === 'svg') {
			pg_assert_true(!empty($param['required']), 'a new version of the drawing needs its SVG');
		}
	}

	foreach ($rows['workspace.canvases.page.put']['params'] as $param) {
		if ($param['name'] === 'html') {
			pg_assert_true(!empty($param['required']), 'a new version of the page needs its HTML');
			pg_assert_same(512000, $param['max_length'], 'and the frame\'s own limit holds');
		}
	}
}

function test_workspace_canvas_one_returns_name_the_parts()
{
	$returns = ws_api_canvas_one_returns();

	pg_assert_same(array('drawing', 'page', 'data'), array_keys($returns['parts']), 'a canvas has three parts');
	pg_assert_same(array('width', 'height', 'background'), array_keys($returns['parts']['drawing']['artboard']));
	pg_assert_same(array('id', 'name', 'access', 'reached', 'row_count'), array_keys($returns['parts']['data'][0]), 'a bound table as the card shows it');
	pg_assert_same('string', $returns['versions'][0]['part'], 'every version says which part it is of');
}

function test_workspace_canvas_one_binder_is_the_application()
{
	pg_assert_same(array('kind' => 'app', 'id' => 4), ws_api_canvas_table_binder(4), 'a key binds as its application');
	pg_assert_same(array('kind' => 'user', 'id' => 0), ws_api_canvas_table_binder(0), 'the person binds in their own name from their device');
	pg_assert_same('user', ws_api_canvas_table_binder(-1)['kind']);
}

function test_workspace_canvas_one_part_file_pick()
{
	pg_assert_same(10, ws_canvas_part_file_pick('drawing', 10, 'drawing', array()), 'the message\'s own drawing');
	pg_assert_same(0, ws_canvas_part_file_pick('page', 10, 'drawing', array()), 'a drawing with no page added');
	pg_assert_same(22, ws_canvas_part_file_pick('page', 10, 'drawing', array('page' => 22)), 'a page added beside the drawing');
	pg_assert_same(10, ws_canvas_part_file_pick('page', 10, 'page', array('page' => 22)), 'the message\'s own page wins over a row');
	pg_assert_same(23, ws_canvas_part_file_pick('drawing', 10, 'page', array('drawing' => 23)), 'a drawing added beside the page');
	pg_assert_same(0, ws_canvas_part_file_pick('page', 0, '', array()), 'no file, no part');
	pg_assert_same(array('drawing', 'page'), ws_canvas_part_names());
}

function test_workspace_canvas_one_parts_step()
{
	$step = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_true(strpos($step, "upgrade_2026_11_0_ws_canvas_parts();         // 11.0.194") !== false, 'the step is called under its label');
	pg_assert_true(strpos($step, "UNIQUE KEY uniq_canvas_part (message_id, part)") !== false, 'one row a canvas and part');
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'), "'ws_canvas_parts',") !== false, 'a reinstall knows the table');
}

function test_workspace_canvas_one_version_zero_adds()
{
	foreach (array('workspace.canvases.page.put', 'workspace.canvases.drawing.put') as $id) {
		$route = ws_test_canvas_one_routes()[$id];

		pg_assert_same(0, $route['params'][1]['min'], $id . ': version 0 adds the part');
		pg_assert_true(strpos($route['description'], 'version 0 adds') !== false, $id . ' says so');
		pg_assert_true(strpos($route['description'], 'part_missing') === false, $id . ' no longer refuses a missing part');
	}
}
