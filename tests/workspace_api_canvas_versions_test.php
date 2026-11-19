<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a canvas's older version put back, an agent's edit undone and
 * the tables a page may bind over the external API
 * (includes/workspace/api_canvases.php) that need no database: the routes'
 * rows, who may call each (the panel's own checks, the undo the person's
 * alone), each write's dry run making the panel writer's checks before it
 * stops, a version that is not there refused with 404, a binding line of
 * another canvas refused, and the table choices narrowed to what the
 * caller reaches.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function ws_api_canvas_versions_test_route($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

// A function's own source.
function ws_api_canvas_versions_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

function test_workspace_api_canvas_versions_routes()
{
	$restore = ws_api_canvas_versions_test_route('workspace.canvases.versions.restore');
	$undo = ws_api_canvas_versions_test_route('workspace.canvases.undo');
	$choices = ws_api_canvas_versions_test_route('workspace.canvases.table_choices');

	pg_assert_same('/workspace/canvases/{id}/versions/{n}/restore', $restore['path']);
	pg_assert_same('workspace:write', $restore['scope']);
	pg_assert_true(!empty($restore['dry_run']), 'restore has a dry run');
	pg_assert_true(pg_mcp_route_is_offered($restore), 'restore is an MCP tool');

	pg_assert_same('/workspace/canvases/{id}/undo', $undo['path']);
	pg_assert_true(!empty($undo['dry_run']), 'undo has a dry run');
	pg_assert_false(pg_mcp_route_is_offered($undo), 'undo is the person\'s own: no MCP tool');

	pg_assert_same('GET', $choices['method']);
	pg_assert_same('workspace:read', $choices['scope']);
	pg_assert_true(pg_mcp_route_is_offered($choices), 'the table choices are an MCP tool');
	pg_assert_same(array_keys(ws_api_canvas_table_choice_schema()), array_keys(ws_api_canvas_table_choices_present(array(array('id' => 1, 'name' => 'A', 'bound' => '')), array(1))[0]));
}

function test_workspace_api_canvas_versions_undo_is_the_persons()
{
	$source = ws_api_canvas_versions_test_source('ws_api_canvases_undo');

	pg_assert_true(strpos($source, 'ws_api_person_only(') < strpos($source, 'ws_api_viewer('), 'an application key is refused before anything is read');
	pg_assert_true(strpos($source, '$run(true)') < strpos($source, 'api_dry_run_stop('), 'the dry run makes the panel writer\'s checks first');
	pg_assert_true(strpos($source, 'api_dry_run_stop(') < strpos($source, '$run(false)'), 'and stops before the write');
}

function test_workspace_api_canvas_versions_restore_checks_as_the_panel()
{
	$source = ws_api_canvas_versions_test_source('ws_api_canvases_version_restore');

	pg_assert_contains('ws_api_canvas_one_write_target(', $source, 'a non-member gets 404, a reader who may not write 403');
	pg_assert_true(strpos($source, '$version, true)') < strpos($source, 'api_dry_run_stop('), 'the dry run makes the writer\'s checks first');

	$writer = ws_api_canvas_versions_test_source('ws_canvas_part_restore');

	pg_assert_contains("lang('That version could not be read.'), 404)", $writer, 'a version the part has not got is 404');
	pg_assert_contains("lang('You cannot write in this channel.'), 403)", $writer);
}

function test_workspace_api_canvas_versions_writers_check_only()
{
	$writes = array(
		'ws_canvas_part_restore' => 'pg_image_editor_put(',
		'ws_canvas_undo'         => 'pg_image_editor_put(',
		'ws_page_undo'           => 'ws_page_write(',
		'ws_canvas_binding_undo' => 'ws_canvas_table_unbind(',
	);

	foreach ($writes as $function => $write) {
		$source = ws_api_canvas_versions_test_source($function);
		$stop = strpos($source, 'if ($check_only)');

		pg_assert_true($stop !== false, $function . ' has a check-only mode');
		pg_assert_true($stop < strpos($source, $write), $function . ' stops before its first write');
	}

	// The page restore stops before its own write too.
	$restore = ws_api_canvas_versions_test_source('ws_canvas_part_restore');
	pg_assert_true(strpos($restore, 'if ($check_only)') < strpos($restore, 'return ws_page_write('));
}

function test_workspace_api_canvas_versions_refusal_codes()
{
	pg_assert_same(array('status' => 403, 'code' => 'forbidden'), ws_api_canvas_refusal_code(403));
	pg_assert_same(array('status' => 404, 'code' => 'not_found'), ws_api_canvas_refusal_code(404));
	pg_assert_same(array('status' => 409, 'code' => 'conflict'), ws_api_canvas_refusal_code(409));
	pg_assert_same(array('status' => 409, 'code' => 'conflict'), ws_api_canvas_refusal_code(0));
	pg_assert_same(array('status' => 500, 'code' => 'server_error'), ws_api_canvas_refusal_code(500));
	pg_assert_same(array('status' => 503, 'code' => 'service_unavailable'), ws_api_canvas_refusal_code(503));
}

function test_workspace_api_canvas_versions_undo_problem()
{
	pg_assert_same('', ws_api_canvas_undo_problem('binding', 5, array('canvas' => 5), 0));
	pg_assert_same('line_id', ws_api_canvas_undo_problem('binding', 5, array('canvas' => 6), 0), 'a line of another canvas');
	pg_assert_same('line_id', ws_api_canvas_undo_problem('binding', 5, null, 0), 'a message that is no binding line');
	pg_assert_same('version', ws_api_canvas_undo_problem('drawing', 5, null, 0));
	pg_assert_same('', ws_api_canvas_undo_problem('page', 5, null, 3));
}

function test_workspace_api_canvas_versions_table_choices_narrowed()
{
	$choices = array(
		array('id' => 1, 'name' => 'Leads', 'bound' => 'read'),
		array('id' => 2, 'name' => 'Private', 'bound' => ''),
		array('id' => 3, 'name' => 'Stock', 'bound' => ''),
	);

	pg_assert_same(array(
		array('table_id' => 1, 'name' => 'Leads', 'bound' => 'read'),
		array('table_id' => 3, 'name' => 'Stock', 'bound' => ''),
	), ws_api_canvas_table_choices_present($choices, array(1, 3)), 'a table the caller does not reach is never offered');
	pg_assert_same(array(), ws_api_canvas_table_choices_present($choices, array()));
	pg_assert_contains('ws_api_wtable_reachable(', ws_api_canvas_versions_test_source('ws_api_canvases_table_choices'));
	pg_assert_contains('ws_canvas_page_reach(', ws_api_canvas_versions_test_source('ws_api_canvases_table_choices'), 'a canvas with no page, or out of reach, is 404');
}
