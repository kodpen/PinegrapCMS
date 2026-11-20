<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application's answer to a / command shown to its writer alone
 * (includes/workspace/app_answers.php): the signed answer_token, its expiry,
 * the three answers a token gives, the application and command it binds, the
 * body's limit, the event that carries the token and the route.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/app_commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/app_answers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// A token signed for a writer, channel, application and command reads back
// to the same claims, and its signature is the 32 characters the table keeps.
function test_workspace_app_answer_token_round_trip()
{
	$token = ws_app_answer_token(5, 40, 7, 'özetle', 1000, 'key');
	$read = ws_app_answer_token_read($token, 'key', 1100);

	pg_assert_true($read['ok'], 'a fresh token is accepted');
	pg_assert_same(array(5, 40, 7, 'özetle', 1000), array($read['user_id'], $read['channel_id'], $read['app_id'], $read['command'], $read['time']));
	pg_assert_same(32, strlen($read['signature']), 'fits ws_app_answers.token');
	pg_assert_true((bool) preg_match('/^[\x20-\x7e]+$/', $token), 'the token is ASCII');
}

// Another key, a changed claim or a cut token is not one the site signed.
function test_workspace_app_answer_token_refuses_a_forgery()
{
	$token = ws_app_answer_token(5, 40, 7, 'report', 1000, 'key');

	pg_assert_same('token_invalid', ws_app_answer_token_read($token, 'other', 1100)['error'], 'another key');
	pg_assert_same('token_invalid', ws_app_answer_token_read('6' . substr($token, 1), 'key', 1100)['error'], 'another writer');
	pg_assert_same('token_invalid', ws_app_answer_token_read(str_replace('.40.', '.41.', $token), 'key', 1100)['error'], 'another channel');
	pg_assert_same('token_invalid', ws_app_answer_token_read(substr($token, 0, -1), 'key', 1100)['error'], 'cut');
	pg_assert_same('token_invalid', ws_app_answer_token_read(array($token), 'key', 1100)['error'], 'not a string');
	pg_assert_same('token_invalid', ws_app_answer_token_read($token, '', 1100)['error'], 'no key on the site');
	pg_assert_same('', ws_app_answer_token(5, 40, 7, 'report', 1000, ''), 'nothing signed without a key');
	pg_assert_same('', ws_app_answer_token(0, 40, 7, 'report', 1000, 'key'), 'nor without a writer');
}

// Fifteen minutes after the command the token has expired; one from the
// future beyond a minute's clock difference is refused.
function test_workspace_app_answer_token_expires()
{
	$token = ws_app_answer_token(5, 40, 7, 'report', 1000, 'key');

	pg_assert_true(ws_app_answer_token_read($token, 'key', 1000 + 900)['ok'], 'still within 15 minutes');
	pg_assert_same('token_expired', ws_app_answer_token_read($token, 'key', 1000 + 901)['error'], 'expired');
	pg_assert_true(ws_app_answer_token_read($token, 'key', 1000 - 60)['ok'], 'a minute of clock difference');
	pg_assert_same('token_invalid', ws_app_answer_token_read($token, 'key', 1000 - 61)['error'], 'from the future');
}

// The calling application must be the token's, the command still its own,
// and a token gives three answers.
function test_workspace_app_answer_admit()
{
	$claims = array('app_id' => 7);

	pg_assert_same('', ws_app_answer_admit($claims, 7, true, 0), 'the first answer');
	pg_assert_same('', ws_app_answer_admit($claims, 7, true, 2), 'the third answer');
	pg_assert_same('limit_reached', ws_app_answer_admit($claims, 7, true, 3), 'a fourth');
	pg_assert_same(3, WS_APP_ANSWER_MAX);
	pg_assert_same('wrong_app', ws_app_answer_admit($claims, 8, true, 0), 'another application');
	pg_assert_same('wrong_app', ws_app_answer_admit($claims, 0, true, 0), 'no application');
	pg_assert_same('command_gone', ws_app_answer_admit($claims, 7, false, 0), 'the command was removed');
}

// The body is cleaned as a message's and held to a message's length.
function test_workspace_app_answer_body_limit()
{
	pg_assert_same('Done', ws_app_answer_body("  Done \n")['body']);
	pg_assert_same('text_required', ws_app_answer_body('   ')['error']);
	pg_assert_same('text_required', ws_app_answer_body(null)['error']);
	pg_assert_true(ws_app_answer_body(str_repeat('ş', WS_MESSAGE_MAX))['ok'], 'a message at its limit');
	pg_assert_same('text_too_long', ws_app_answer_body(str_repeat('ş', WS_MESSAGE_MAX + 1))['error']);
}

// The event carries the token only when one was signed: before the upgrade
// the payload is what it was.
function test_workspace_app_answer_token_in_the_event()
{
	$channel = array('id' => 3, 'name' => 'general', 'kind' => 'public');
	$plain = ws_app_command_payload($channel, null, 7, 'report', 5, 'x', 'x');
	$with = ws_app_command_payload($channel, null, 7, 'report', 5, 'x', 'x', 0, null, 'tok');

	pg_assert_false(array_key_exists('answer_token', $plain), 'no token, no key');
	pg_assert_same('tok', $with['answer_token']);
	pg_assert_same(array_keys($plain), array_slice(array_keys($with), 0, count($plain)), 'the fields of before stay');
}

// The route: the application's scope, a dry run, no MCP tool.
function test_workspace_app_answer_route()
{
	$route = null;

	foreach (ws_api_routes() as $item) {
		if ($item['id'] === 'workspace.commands.answer') {
			$route = $item;
		}
	}

	pg_assert_true(is_array($route), 'declared');
	pg_assert_same(array('POST', '/workspace/commands/answer'), array($route['method'], $route['path']));
	pg_assert_same('workspace:commands', $route['scope']);
	pg_assert_true(!empty($route['dry_run']), 'rehearses');
	pg_assert_false(pg_mcp_route_is_offered($route), 'no MCP tool');
	pg_assert_true(function_exists($route['handler']) || ($route['handler'] === 'ws_api_commands_answer'), 'handler');
}
