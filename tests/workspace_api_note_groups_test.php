<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for note groups in the external API and MCP
 * (includes/workspace/api_note_groups.php) that need no database: the
 * routes, which are MCP tools, the answer's shape against its schema, how a
 * refusal is answered, group_id on a note and that every write checks before
 * the rehearsal stops it.
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

function ws_api_note_groups_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function ws_api_note_groups_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		if ((strpos($route['id'], 'workspace.note_groups.') === 0) || ($route['id'] === 'workspace.notes.place')) {
			$routes[$route['id']] = $route;
		}
	}

	return $routes;
}

function test_ws_api_note_groups_routes()
{
	$routes = ws_api_note_groups_test_routes();
	$expected = array(
		'workspace.note_groups.list'        => array('GET', '/workspace/note-groups', 'workspace:read'),
		'workspace.note_groups.get'         => array('GET', '/workspace/note-groups/{id}', 'workspace:read'),
		'workspace.note_groups.create'      => array('POST', '/workspace/note-groups', 'workspace:write'),
		'workspace.note_groups.order'       => array('POST', '/workspace/note-groups/order', 'workspace:write'),
		'workspace.note_groups.update'      => array('POST', '/workspace/note-groups/{id}', 'workspace:write'),
		'workspace.note_groups.delete'      => array('DELETE', '/workspace/note-groups/{id}', 'workspace:write'),
		'workspace.note_groups.delete_post' => array('POST', '/workspace/note-groups/{id}/delete', 'workspace:write'),
		'workspace.notes.place'             => array('POST', '/workspace/notes/{id}/group', 'workspace:write'),
	);

	pg_assert_same(array_keys($expected), array_keys($routes), 'the routes, in order');

	foreach ($expected as $id => $shape) {
		if (isset($routes[$id])) {
			pg_assert_same($shape, array($routes[$id]['method'], $routes[$id]['path'], $routes[$id]['scope']), $id);
			pg_assert_true(function_exists($routes[$id]['handler']), $id . ' reaches a handler');
			pg_assert_same($routes[$id]['method'] !== 'GET', !empty($routes[$id]['dry_run']), $id . ': a write rehearses');
		}
	}

	pg_assert_same(array('PATCH'), $routes['workspace.note_groups.update']['also_accepts'] ?? null);
	pg_assert_same($routes['workspace.note_groups.delete']['handler'], $routes['workspace.note_groups.delete_post']['handler']);

	// As for notes: reading, writing and placing are tools; a delete is not.
	foreach ($routes as $id => $route) {
		if (strpos($id, '.delete') !== false) {
			pg_assert_false(pg_mcp_route_is_offered($route), $id . ' is no tool');
		} else {
			pg_assert_true(pg_mcp_route_is_offered($route), $id . ' is a tool');
		}
	}

	// Sharing stays on the screen.
	foreach (ws_api_routes() as $route) {
		pg_assert_false((strpos($route['path'], '/workspace/note-groups') === 0) && (strpos($route['path'], 'share') !== false), $route['id'] . ' shares a group');
	}

	$names = array();

	foreach ($routes['workspace.note_groups.create']['params'] as $param) {
		$names[$param['name']] = $param;
	}

	pg_assert_true(!empty($names['name']['required']), 'a new group needs a name');
	pg_assert_same(WS_NOTE_GROUP_NAME_MAX, $names['name']['max_length'], 'the name fits its column');
}

function test_ws_api_note_group_present()
{
	$group = array('id' => '4', 'user_id' => '2', 'name' => 'Clients', 'color' => '3', 'sort' => '2', 'locked' => '0', 'notes' => '5');

	$people = array(2 => array('name' => 'Ayşe'));
	$out = ws_api_note_group_present($group, 'owner', true, null, $people);

	pg_assert_same(array_keys(ws_api_note_group_schema()), array_keys($out), 'the schema names every field');
	pg_assert_same(array(4, 'Clients', 3, 2, false, 5, 'owner', true, null),
		array($out['id'], $out['name'], $out['color'], $out['position'], $out['locked'], $out['note_count'], $out['access'], $out['shared'], $out['note_ids']));
	pg_assert_same(array('user_id' => 2, 'name' => 'Ayşe'), $out['owner']);

	$full = ws_api_note_group_present($group, 'view', true, array('9', 7), $people);
	pg_assert_same(array(9, 7), $full['note_ids'], 'the notes in their order');
	pg_assert_same('view', $full['access']);
}

// A refusal of the panel's checks, as the API answers it.
function test_ws_api_note_group_refusal()
{
	pg_assert_same(array(422, 'validation_failed', 'name'), ws_api_note_group_refusal(array('error' => 'x', 'field' => 'name')));
	pg_assert_same(array(409, 'limit_reached', null), ws_api_note_group_refusal(array('error' => 'x', 'field' => '')));
	pg_assert_same(array(403, 'forbidden', 'group_id'), ws_api_note_place_refusal(array('error' => 'x', 'field' => 'group_id')));
	pg_assert_same(array(404, 'not_found', 'note_id'), ws_api_note_place_refusal(array('error' => 'x', 'field' => 'note_id')));
}

function test_ws_api_note_group_reach()
{
	$group = array('id' => 4, 'user_id' => 2);
	$shares = array(
		array('user_id' => 0, 'channel_id' => 8, 'can_edit' => 0),
		array('user_id' => 5, 'channel_id' => 0, 'can_edit' => 1),
	);

	pg_assert_same('owner', ws_note_group_reach_of(2, $group, $shares, array()));
	pg_assert_same('edit', ws_note_group_reach_of(5, $group, $shares, array()));
	pg_assert_same('view', ws_note_group_reach_of(6, $group, $shares, array(8)), 'through a channel read');
	pg_assert_same('', ws_note_group_reach_of(6, $group, $shares, array(9)), 'a channel not read');
}

// The writes run the panel's check, stop for a rehearsal, then write.
function test_ws_api_note_groups_check_before_the_rehearsal()
{
	$cases = array(
		'ws_api_note_groups_create' => array('ws_note_group_save_check(', 'ws_note_group_save($viewer'),
		'ws_api_note_groups_update' => array('ws_note_group_save_check(', 'ws_note_group_save($viewer'),
		'ws_api_notes_place'        => array('ws_note_place_check(', 'ws_note_place($viewer'),
		'ws_api_note_groups_delete' => array('ws_api_note_group_owned_or_404(', 'ws_note_group_delete($viewer'),
		'ws_api_note_groups_order'  => array('ws_api_note_groups_ready_or_503(', 'ws_note_groups_order($viewer'),
	);

	foreach ($cases as $handler => $calls) {
		$source = ws_api_note_groups_test_source($handler);
		$check = strpos($source, $calls[0]);
		$stop = strpos($source, 'api_dry_run_stop(');
		$write = strpos($source, $calls[1]);

		pg_assert_true(($check !== false) && ($stop !== false) && ($write !== false) && ($check < $stop) && ($stop < $write), $handler . ': check, rehearsal, write');
	}

	pg_assert_contains('ws_note_group_for(', ws_api_note_groups_test_source('ws_api_note_group_owned_or_404'), 'only the owner changes a group');
}

// Every note object says which group the owner of the application keeps it in.
function test_ws_api_note_carries_its_group()
{
	pg_assert_same('integer?', ws_api_note_schema()['group_id'] ?? null);
	pg_assert_contains("'group_id'", ws_api_note_groups_test_source('ws_api_note_present'));
	pg_assert_contains('ws_note_places_add(', ws_api_note_groups_test_source('ws_api_notes_list'), 'one read for a page');

	pg_assert_same(null, ws_api_note_group_id(array('id' => 3, 'group_id' => 0)), 'no group');
	pg_assert_same(6, ws_api_note_group_id(array('id' => 3, 'group_id' => '6')));
}

function test_ws_api_note_groups_schema_registered()
{
	$schemas = ws_openapi_objects();

	pg_assert_same('ws_api_note_group_schema', $schemas['WorkspaceNoteGroup'] ?? null);
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_note_groups.php');", file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api.php'));
}
