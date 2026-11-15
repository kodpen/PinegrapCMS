<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for three message routes of the Workspace API that need no
 * database: who has seen a message and who gave its read receipt
 * (workspace.messages.readers), bringing the people it mentions into its
 * channel (workspace.messages.mention_invite) and note_id on
 * workspace.messages.forward. The routes, the answers against their schema,
 * and that every write runs the panel's own check before the rehearsal.
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

function ws_api_message_people_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function ws_api_message_people_test_route($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function test_ws_api_message_people_routes()
{
	$expected = array(
		'workspace.messages.readers'        => array('GET', '/workspace/messages/{id}/readers', 'workspace:read', 'WorkspaceMessageReaders'),
		'workspace.messages.mention_invite' => array('POST', '/workspace/messages/{id}/mention-invite', 'workspace:write', 'WorkspaceMessageMentionInvite'),
	);

	foreach ($expected as $id => $shape) {
		$route = ws_api_message_people_test_route($id);

		pg_assert_true(is_array($route), $id . ' is declared');

		if (!is_array($route)) {
			continue;
		}

		pg_assert_same($shape, array($route['method'], $route['path'], $route['scope'], $route['returns']), $id);
		pg_assert_true(function_exists($route['handler']), $id . ' reaches a handler');
		pg_assert_same($route['method'] !== 'GET', !empty($route['dry_run']), $id . ': a write rehearses');

		// Neither is a person's own answer nor a change to their words, so
		// both are MCP tools.
		pg_assert_true(!array_key_exists('mcp', $route) || ($route['mcp'] !== false), $id . ' is a tool');
	}

	$forward = ws_api_message_people_test_route('workspace.messages.forward');
	$names = array_map(function ($param) { return $param['name']; }, $forward['params']);

	pg_assert_true(in_array('note_id', $names, true), 'forward takes note_id');
}

// The answers' declared objects exist and the shapers give their keys.
function test_ws_api_message_people_schemas()
{
	$objects = ws_openapi_objects();

	foreach (array('WorkspaceMessageReaders', 'WorkspaceMessageReaderAgent', 'WorkspaceMessageReadReceipts', 'WorkspaceMessageReadReceipt', 'WorkspaceMessageMentionInvite') as $name) {
		pg_assert_true(isset($objects[$name]) && function_exists($objects[$name]), $name . ' is declared');
	}

	$agent = ws_api_message_reader_agent(array('key' => 'claude', 'app_id' => 0, 'name' => 'Claude', 'handle' => 'claude', 'state' => 'quiet', 'at' => 0));

	pg_assert_same(array_keys(ws_api_message_reader_agent_schema()), array_keys($agent));
	pg_assert_same(null, $agent['app_id'], 'no application id for an assistant without one');
	pg_assert_same(null, $agent['at'], 'a quiet agent has no moment');
	pg_assert_same(7, ws_api_message_reader_agent(array('app_id' => 7, 'at' => 1700000000))['app_id']);
}

function test_ws_api_message_read_receipts()
{
	$hidden = ws_api_message_read_receipts(null);

	pg_assert_same(array('listed' => false, 'read' => array(), 'unread' => array()), $hidden, 'somebody who may not list them');

	$shown = ws_api_message_read_receipts(array(
		'read'   => array(array('id' => 4, 'name' => 'Ayla', 'avatar' => 'x', 'time' => 'now', 'at' => 1700000000)),
		'unread' => array(array('id' => 5, 'name' => 'Bora', 'avatar' => 'y')),
	));

	pg_assert_same(array_keys(ws_api_message_read_receipts_schema()), array_keys($shown));
	pg_assert_true($shown['listed']);
	pg_assert_same(array_keys(ws_api_message_read_receipt_schema()), array_keys($shown['read'][0]));
	pg_assert_same(4, $shown['read'][0]['user_id']);
	pg_assert_true(is_string($shown['read'][0]['read_at']), 'when it was read');
	pg_assert_same(array(array('user_id' => 5, 'name' => 'Bora')), $shown['unread']);
}

// The writes run the panel's check, stop for a rehearsal, then write; the
// panel's own functions run the same check.
function test_ws_api_message_people_check_before_the_rehearsal()
{
	$cases = array(
		'ws_api_messages_mention_invite'   => array('ws_message_mention_invite_check(', 'api_dry_run_stop(', 'ws_message_mention_invite($viewer'),
		'ws_api_messages_forward_into_note' => array('ws_api_note_locked_stop(', 'ws_forward_to_note_check(', 'api_dry_run_stop(', 'ws_forward_to_note($viewer'),
	);

	foreach ($cases as $handler => $calls) {
		$source = ws_api_message_people_test_source($handler);
		$at = -1;

		foreach ($calls as $call) {
			$found = strpos($source, $call);
			pg_assert_true(($found !== false) && ($found > $at), $handler . ': ' . $call . ' in its place');
			$at = ($found === false) ? $at : $found;
		}
	}

	pg_assert_contains('ws_message_mention_invite_check(', ws_api_message_people_test_source('ws_message_mention_invite'), 'the panel checks the same way');
	pg_assert_contains('ws_forward_to_note_check(', ws_api_message_people_test_source('ws_forward_to_note'), 'the panel checks the same way');
	pg_assert_contains('ws_note_save_check(', ws_api_message_people_test_source('ws_forward_to_note_check'), 'the note is checked as a save');

	// A forward with note_id goes into the note before any channel is read
	// as the target.
	$forward = ws_api_message_people_test_source('ws_api_messages_forward');
	pg_assert_true(strpos($forward, 'ws_api_messages_forward_into_note(') < strpos($forward, 'ws_forward_to_channel_check('), 'note_id branches first');

	// Readers reuse the panel's two answers.
	$readers = ws_api_message_people_test_source('ws_api_messages_readers');
	pg_assert_contains('ws_message_seen_by(', $readers);
	pg_assert_contains('ws_ack_people(', $readers);
	pg_assert_contains('ws_api_channel_or_404(', $readers, 'Claude\'s limits');
}
