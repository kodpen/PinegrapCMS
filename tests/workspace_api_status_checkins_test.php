<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's status update and check-ins over the Workspace API
 * that need no database: the routes (the answer is the person's own and no
 * tool), the check-in's shape against its schema, and that every write
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

function ws_api_status_checkins_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function ws_api_status_checkins_test_route($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function test_ws_api_status_checkins_routes()
{
	$expected = array(
		'workspace.channels.status' => array('POST', '/workspace/channels/{id}/status', 'workspace:write', 'WorkspaceMessage', true),
		'workspace.checkins.start'  => array('POST', '/workspace/channels/{id}/checkins', 'workspace:write', 'WorkspaceCheckin', true),
		'workspace.checkins.answer' => array('POST', '/workspace/checkins/{id}/answer', 'workspace:write', 'WorkspaceCheckin', false),
	);

	foreach ($expected as $id => $shape) {
		$route = ws_api_status_checkins_test_route($id);

		pg_assert_true(is_array($route), $id . ' is declared');

		if (!is_array($route)) {
			continue;
		}

		pg_assert_same(array_slice($shape, 0, 4), array($route['method'], $route['path'], $route['scope'], $route['returns']), $id);
		pg_assert_true(function_exists($route['handler']), $id . ' reaches a handler');
		pg_assert_true(!empty($route['dry_run']), $id . ' rehearses');
		pg_assert_same($shape[4], !array_key_exists('mcp', $route) || ($route['mcp'] !== false), $id . ': a tool unless it is the person\'s own answer');
	}

	$status = ws_api_status_checkins_test_route('workspace.channels.status');
	$health = null;

	foreach ($status['params'] as $param) {
		if ($param['name'] === 'health') {
			$health = $param['values'];
		}
	}

	pg_assert_same(array_keys(ws_status_healths()), $health, 'the states the panel offers');
}

function test_ws_api_checkin_shape()
{
	$card = array(
		'kind' => 'checkin', 'id' => 3, 'question' => 'How is it going?',
		'answers' => array(array('id' => 4, 'name' => 'Ayla', 'avatar' => 'x', 'text' => 'Fine', 'time' => 'now', 'mine' => true)),
		'waiting' => array('Bora'), 'count' => 1, 'total' => 2, 'mine' => 'Fine', 'can_answer' => true,
	);
	$shape = ws_api_checkin_shape($card, 90, 7);

	pg_assert_same(array_keys(ws_api_checkin_schema()), array_keys($shape));
	pg_assert_same(array_keys(ws_api_checkin_answer_schema()), array_keys($shape['answers'][0]));
	pg_assert_same(array(90, 7, 4), array($shape['message_id'], $shape['channel_id'], $shape['answers'][0]['user_id']));
	pg_assert_same(null, ws_api_checkin_shape(array('mine' => '') + $card, 90, 7)['mine'], 'no answer of their own');

	$objects = ws_openapi_objects();
	pg_assert_true(isset($objects['WorkspaceCheckin'], $objects['WorkspaceCheckinAnswer']), 'the objects are declared');
}

// The person's own answer first, then the panel's check, the rehearsal and
// the write; the panel's functions run the same checks.
function test_ws_api_status_checkins_check_before_the_rehearsal()
{
	$cases = array(
		'ws_api_channel_status_post'    => array('ws_api_channel_or_404(', 'ws_status_post_check(', 'api_dry_run_stop(', 'ws_status_post($viewer'),
		'ws_api_channel_checkins_start' => array('ws_api_channel_or_404(', 'ws_checkin_start_check(', 'api_dry_run_stop(', 'ws_checkin_start($viewer'),
		'ws_api_checkins_answer'        => array('ws_api_person_only(', 'ws_checkin_answer_check(', 'api_dry_run_stop(', 'ws_checkin_answer($viewer'),
	);

	foreach ($cases as $handler => $calls) {
		$source = ws_api_status_checkins_test_source($handler);
		$at = -1;

		foreach ($calls as $call) {
			$found = strpos($source, $call);
			pg_assert_true(($found !== false) && ($found > $at), $handler . ': ' . $call . ' in its place');
			$at = ($found === false) ? $at : $found;
		}
	}

	pg_assert_contains('ws_status_post_check(', ws_api_status_checkins_test_source('ws_status_post'));
	pg_assert_contains('ws_checkin_start_check(', ws_api_status_checkins_test_source('ws_checkin_start'));
	pg_assert_contains('ws_checkin_answer_check(', ws_api_status_checkins_test_source('ws_checkin_answer'));

	// A discussion is refused by the shared checks, as by the panel.
	pg_assert_contains('ws_status_thread_refusal(', ws_api_status_checkins_test_source('ws_status_post_check'));
	pg_assert_contains('ws_status_thread_refusal(', ws_api_status_checkins_test_source('ws_checkin_start_check'));
	pg_assert_contains('ws_status_thread_refusal(', ws_api_status_checkins_test_source('ws_status_action'));

	// The writer reaches the message: an application's update is its own.
	pg_assert_contains('ws_api_message_writer(', ws_api_status_checkins_test_source('ws_api_channel_status_post'));
	pg_assert_contains('ws_api_message_writer(', ws_api_status_checkins_test_source('ws_api_channel_checkins_start'));
}
