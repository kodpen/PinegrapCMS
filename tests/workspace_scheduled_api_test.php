<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for creating, changing and resuming scheduled actions over the
 * external API (includes/workspace/api_scheduled.php): the three routes,
 * staff in their own team devices session only and no MCP tools, the
 * rehearsal through the panel's preview, a change that keeps what it does
 * not send, the save check the panel's save shares, and the object type a
 * body field may declare (includes/api/router.php).
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

// A function's source without its comments.
function ws_scheduled_api_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The routes by id.
function ws_scheduled_api_test_routes()
{
	$routes = array();

	foreach (ws_scheduled_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

// Create, change and resume: written by staff from their own team devices
// session, so no key, OAuth connection or MCP client; rehearsable.
function test_workspace_scheduled_api_write_routes()
{
	$routes = ws_scheduled_api_test_routes();
	$expected = array(
		'workspace.scheduled_actions.create' => array('POST', '/workspace/scheduled-actions', 'ws_api_scheduled_actions_create'),
		'workspace.scheduled_actions.update' => array('POST', '/workspace/scheduled-actions/{id}', 'ws_api_scheduled_actions_update'),
		'workspace.scheduled_actions.resume' => array('POST', '/workspace/scheduled-actions/{id}/resume', 'ws_api_scheduled_actions_resume'),
	);

	foreach ($expected as $id => $shape) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');

		if (!isset($routes[$id])) {
			continue;
		}

		$route = $routes[$id];

		pg_assert_same($shape, array($route['method'], $route['path'], $route['handler']), $id . ' method, path, handler');
		pg_assert_same('workspace:write', $route['scope'], $id . ' scope');
		pg_assert_true(!empty($route['dry_run']), $id . ' is rehearsable');
		pg_assert_same(false, $route['mcp'] ?? null, $id . ' is no MCP tool');
		pg_assert_false(pg_mcp_route_is_offered($route), $id . ' is offered to no MCP client');

		// Resume writes through the pause and cancel route's own path.
		$source = ws_scheduled_api_test_source($route['handler']) . ws_scheduled_api_test_source('ws_api_scheduled_actions_set_status');

		pg_assert_contains('ws_api_scheduled_viewer()', $source, $id . ' is for staff');
		pg_assert_contains('ws_api_own_words_only(', $source, $id . ' only from the person\'s own session');
	}

	pg_assert_same(array('PATCH'), $routes['workspace.scheduled_actions.update']['also_accepts'] ?? null, 'a change is also a PATCH');
	pg_assert_same(201, $routes['workspace.scheduled_actions.create']['status'] ?? null, 'a create answers 201');

	$params = array();

	foreach ($routes['workspace.scheduled_actions.create']['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_same(array('name', 'rules', 'action', 'follow', 'channel_id'), array_keys($params), 'the fields of a create');
	pg_assert_same('object', $params['action']['type'], 'the step is an object');
	pg_assert_same('list', $params['rules']['type']);
	pg_assert_same('list', $params['follow']['type']);
	pg_assert_true(!empty($params['name']['required']) && !empty($params['rules']['required']) && !empty($params['action']['required']), 'name, rules and step are needed');
}

// A change keeps what it does not send: the name, rules, step and follow-ups
// of the action as it is.
function test_workspace_scheduled_api_change_keeps_what_is_not_sent()
{
	$row = array(
		'id'      => 9,
		'name'    => 'Morning summary',
		'rules'   => array(array('type' => 'at', 'date' => '2026-11-01', 'time' => '09:00', 'repeat' => 'daily', 'every' => 1)),
		'action'  => array('type' => 'post', 'channel_id' => 4, 'text' => 'Good morning'),
		'follow'  => array(array('on' => 'done', 'do' => array('type' => 'notify', 'text' => 'sent'))),
	);

	pg_assert_same(array(
		'id'     => 9,
		'name'   => 'Morning summary',
		'rules'  => $row['rules'],
		'action' => $row['action'],
		'follow' => $row['follow'],
	), ws_api_scheduled_change_data($row, array('id' => 9)), 'nothing sent: as it is');

	$changed = ws_api_scheduled_change_data($row, array('id' => 9, 'name' => 'Evening summary', 'follow' => array()));

	pg_assert_same('Evening summary', $changed['name']);
	pg_assert_same(array(), $changed['follow'], 'an empty list takes the follow-ups away');
	pg_assert_same($row['action'], $changed['action'], 'the step is kept');
}

// The panel's save and the API share one check; the save writes only what
// the check accepted.
function test_workspace_scheduled_api_save_check_is_shared()
{
	$save = ws_scheduled_api_test_source('ws_scheduled_save');
	$check = ws_scheduled_api_test_source('ws_scheduled_save_check');

	pg_assert_contains('ws_scheduled_save_check($viewer, $data)', $save, 'the save runs the check');
	pg_assert_false(strpos($save, 'ws_scheduled_input(') !== false, 'the save reads no input of its own');

	foreach (array('ws_can_schedule($viewer)', 'ws_scheduled_input($viewer, $data, $existing)', 'ws_can_post_channel($viewer, $channel)', '$fail(403, ', '$fail(404, ') as $needle) {
		pg_assert_contains($needle, $check, 'the check: ' . $needle);
	}

	foreach (array('ws_api_scheduled_actions_create', 'ws_api_scheduled_actions_update') as $handler) {
		pg_assert_contains('ws_api_scheduled_save($viewer, ', ws_scheduled_api_test_source($handler), $handler . ' saves through the shared path');

		$source = ws_scheduled_api_test_source('ws_api_scheduled_save');

		pg_assert_contains('ws_scheduled_save_check(', $source, $handler . ' checks as the panel does');
		pg_assert_contains('ws_scheduled_preview(', $source, $handler . ' rehearses with the preview');
		pg_assert_true(strpos($source, 'api_dry_run_stop(') < strpos($source, 'ws_scheduled_save($viewer'), $handler . ' stops a rehearsal before the write');
		pg_assert_contains('ws_api_as_owner(', $source, $handler . ' logs in the owner\'s name');
	}

	$resume = ws_scheduled_api_test_source('ws_api_scheduled_actions_resume');

	pg_assert_contains("ws_api_scheduled_actions_set_status(\$params, 'active')", $resume, 'resume through the status check');
}

// A body field may be an object: a JSON object is taken, a list or a word is
// refused.
function test_workspace_scheduled_api_object_parameter()
{
	$router = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/router.php');
	$at = strpos($router, "case 'object':");

	pg_assert_true($at !== false, 'the router knows the object type');

	$case = substr($router, (int) $at, 600);

	pg_assert_contains('is_array($raw)', $case);
	pg_assert_contains('array_values($raw) === $raw', $case, 'a list is no object');
}
