<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for changing a scheduled message, scheduling one as a reply and
 * sending one now over the Workspace API, without a database: the routes,
 * that they stay the person's own words and no tool, and that every write
 * runs the panel's own check before the rehearsal stops it.
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

function ws_api_scheduled_changes_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function ws_api_scheduled_changes_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		if (strpos($route['id'], 'workspace.scheduled_messages.') === 0) {
			$routes[$route['id']] = $route;
		}
	}

	return $routes;
}

function test_ws_api_scheduled_message_change_routes()
{
	$routes = ws_api_scheduled_changes_test_routes();
	$expected = array(
		'workspace.scheduled_messages.update' => array('POST', '/workspace/scheduled-messages/{id}/update', 'workspace:write', 'WorkspaceScheduledMessage'),
		'workspace.scheduled_messages.send'   => array('POST', '/workspace/scheduled-messages/{id}/send', 'workspace:write', 'WorkspaceMessage'),
	);

	foreach ($expected as $id => $shape) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');

		if (!isset($routes[$id])) {
			continue;
		}

		pg_assert_same($shape, array($routes[$id]['method'], $routes[$id]['path'], $routes[$id]['scope'], $routes[$id]['returns']), $id);
		pg_assert_true(function_exists($routes[$id]['handler']), $id . ' reaches a handler');
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');

		// Posted in the person's own name: no tool, as create.
		pg_assert_same(false, $routes[$id]['mcp'] ?? null, $id . ' is no tool');
	}

	$create = array_map(function ($param) { return $param['name']; }, $routes['workspace.scheduled_messages.create']['params']);
	pg_assert_true(in_array('parent_id', $create, true), 'create takes parent_id');

	// The delete route keeps its POST on the bare path, so a change has a
	// path of its own.
	pg_assert_same(array('POST'), $routes['workspace.scheduled_messages.delete']['also_accepts'] ?? null);
}

// Own words first, then the panel's check, the rehearsal and the write.
function test_ws_api_scheduled_message_check_before_the_rehearsal()
{
	$cases = array(
		'ws_api_scheduled_messages_create' => array('ws_api_own_words_only(', 'ws_api_scheduled_message_parent(', 'ws_scheduled_message_save_check(', 'api_dry_run_stop(', 'ws_scheduled_message_save($viewer'),
		'ws_api_scheduled_messages_update' => array('ws_api_own_words_only(', 'ws_api_scheduled_message_mine_or_404(', 'ws_api_scheduled_message_parent(', 'ws_scheduled_message_save_check(', 'api_dry_run_stop(', 'ws_scheduled_message_save($viewer'),
		'ws_api_scheduled_messages_send'   => array('ws_api_own_words_only(', 'ws_api_scheduled_message_mine_or_404(', 'ws_scheduled_message_send_check(', 'api_dry_run_stop(', 'ws_scheduled_message_send_now('),
	);

	foreach ($cases as $handler => $calls) {
		$source = ws_api_scheduled_changes_test_source($handler);
		$at = -1;

		foreach ($calls as $call) {
			$found = strpos($source, $call);
			pg_assert_true(($found !== false) && ($found > $at), $handler . ': ' . $call . ' in its place');
			$at = ($found === false) ? $at : $found;
		}
	}

	// One copy of the rules: the API's create no longer checks the words or
	// the moment itself, and the panel's save and send run the same checks.
	pg_assert_false(strpos(ws_api_scheduled_changes_test_source('ws_api_scheduled_messages_create'), 'WS_SCHEDULED_MESSAGES_MAX') !== false, 'create leaves the limit to the check');
	pg_assert_contains('ws_scheduled_message_save_check(', ws_api_scheduled_changes_test_source('ws_scheduled_message_save'));
	pg_assert_contains('ws_scheduled_execute(', ws_api_scheduled_changes_test_source('ws_scheduled_message_send_now'));
	pg_assert_contains('ws_message_send_check(', ws_api_scheduled_changes_test_source('ws_scheduled_message_send_check'));
	pg_assert_contains('ws_message_parent_check(', ws_api_scheduled_changes_test_source('ws_api_scheduled_message_parent'), 'a reply is checked as messages.create checks it');
}

function test_ws_api_scheduled_message_field()
{
	pg_assert_same('text', ws_api_scheduled_message_field('body'));
	pg_assert_same('send_at', ws_api_scheduled_message_field('time'));
	pg_assert_same(null, ws_api_scheduled_message_field(''));
}
