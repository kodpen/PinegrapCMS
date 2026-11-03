<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "send a voice message" (VA5): the route and its MCP tool, the
 * target rule (a channel or a discussion, one of them), the refusal codes
 * and their statuses, the order of the handler (check, rehearsal stop,
 * write), Pinegrap AI's tool and Claude's instruction.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/voice.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

// The route: a write with the write scope, rehearsable, answering the
// message, offered as an MCP tool with the declared arguments.
function test_ws_voice_message_route()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_true(isset($routes['workspace.messages.voice']), 'workspace.messages.voice is declared');

	$route = $routes['workspace.messages.voice'];

	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/messages/voice', $route['path']);
	pg_assert_same('workspace:write', $route['scope']);
	pg_assert_same('WorkspaceMessage', $route['returns']);
	pg_assert_same(201, $route['status']);
	pg_assert_true(!empty($route['dry_run']), 'rehearsable');
	pg_assert_true(pg_mcp_route_is_offered($route), 'a voice message is an MCP tool');

	$names = array();

	foreach ($route['params'] as $param) {
		$names[$param['name']] = $param;
	}

	foreach (array('channel_id', 'thread_id', 'text', 'reply_to', 'voice') as $name) {
		pg_assert_true(isset($names[$name]), 'takes ' . $name);
		pg_assert_same('body', $names[$name]['in'], $name . ' in the body');
	}

	pg_assert_true(!empty($names['text']['required']), 'text is required');
	pg_assert_true(empty($names['channel_id']['required']) && empty($names['thread_id']['required']), 'the target is one of two');

	$properties = (array) pg_mcp_tool_from_route($route)['inputSchema']['properties'];
	pg_assert_true(isset($properties['text']) && isset($properties['channel_id']) && isset($properties['thread_id']) && isset($properties['voice']), 'the tool takes the arguments');
}

// Where the message goes: channel_id or thread_id, one of them, each of its
// own kind.
function test_ws_voice_message_target_rule()
{
	pg_assert_same(null, ws_voice_message_target_rule(5, 0, 'public'), 'a channel');
	pg_assert_same(null, ws_voice_message_target_rule(0, 9, 'thread'), 'a discussion');
	pg_assert_same('channel_id', ws_voice_message_target_rule(0, 0, '')['field'], 'neither');
	pg_assert_same('channel_id', ws_voice_message_target_rule(5, 9, 'public')['field'], 'both');
	pg_assert_same('thread_id', ws_voice_message_target_rule(0, 9, 'public')['field'], 'thread_id that is a channel');
	pg_assert_same('channel_id', ws_voice_message_target_rule(5, 0, 'thread')['field'], 'channel_id that is a discussion');
	pg_assert_true(ws_voice_message_target_rule(0, 0, '')['error'] !== '', 'says why');
}

// Each refusal of the check has its HTTP status: no service and a channel
// that keeps its recordings are 409 with their own codes, the service
// failing is 503, everything else is the field's 422.
function test_ws_voice_message_status()
{
	pg_assert_same(409, ws_voice_message_status('voice_unavailable'));
	pg_assert_same(409, ws_voice_message_status('voice_closed'));
	pg_assert_same(503, ws_voice_message_status('voice_failed'));
	pg_assert_same(422, ws_voice_message_status('validation_failed'));
	pg_assert_same(422, ws_voice_message_status(''));
}

// The handler checks, stops a rehearsal, then writes; Pinegrap AI's tool is
// named, offered and handled; Claude's instructions name the route.
function test_ws_voice_message_is_wired()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	$handler = $read('includes/workspace/api_resources.php');
	$from = strpos($handler, 'function ws_api_messages_voice(');
	pg_assert_true($from !== false, 'the handler is there');
	$body = substr($handler, $from, strpos($handler, "\n}\n", $from) - $from);

	$check = strpos($body, 'ws_voice_message_check(');
	$stop = strpos($body, 'api_dry_run_stop(');
	$write = strpos($body, 'ws_voice_message_send(');

	pg_assert_true(($check !== false) && ($stop !== false) && ($write !== false) && ($check < $stop) && ($stop < $write), 'check, then the stop, then the write');
	pg_assert_contains("'voice_unavailable'", $read('includes/workspace/voice.php'), 'the code without a service');

	$voice = $read('includes/workspace/voice.php');
	$send_from = strpos($voice, 'function ws_voice_message_send(');
	$send = substr($voice, $send_from, strpos($voice, "\n}\n", $send_from) - $send_from);
	pg_assert_contains('ws_voice_message_check(', $send, 'the write makes the same checks');
	pg_assert_contains('ws_voice_speak(', $send, 'and speaks');

	$check_from = strpos($voice, 'function ws_voice_message_check(');
	$check_body = substr($voice, $check_from, strpos($voice, "\n}\n", $check_from) - $check_from);
	pg_assert_contains('ws_voice_channel_allowed($channel)', $check_body, 'a channel that keeps its recordings gets none');
	pg_assert_contains('ws_message_parent_check(', $check_body, 'the answered message is checked');

	pg_assert_true(in_array('send_voice_message', ws_ai_tool_names(), true), 'Pinegrap AI names the tool');
	$ai = $read('includes/workspace/ai.php');
	pg_assert_contains("\$tool('send_voice_message'", $ai, 'offered');
	pg_assert_contains("case 'send_voice_message':", $ai, 'handled');
	pg_assert_contains('ws_voice_message_send(', $ai, 'through the shared write');

	pg_assert_contains('POST /workspace/messages/voice', $read('includes/workspace/claude.php'), 'Claude is told the route');
	pg_assert_contains('voice_unavailable', $read('includes/workspace/claude.php'), 'and what no service answers');
}
