<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workspace's route table in the external API
 * (includes/workspace/api.php) that need no database: every route reaches a
 * handler, no two routes answer the same method on the same path, and each
 * action becomes one MCP tool. Also the scopes the search asks of each kind
 * of panel record (includes/workspace/api_day.php), who a call from a
 * device writes as, and which of its messages ask an assistant.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_ws_api_routes_reach_a_handler()
{
	$ids = array();

	foreach (ws_api_routes() as $route) {
		pg_assert_false(isset($ids[$route['id']]), $route['id'] . ' is declared once');
		$ids[$route['id']] = true;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');
		pg_assert_true(in_array($route['scope'], array('workspace:read', 'workspace:write', 'tasks:read', 'tasks:write'), true), $route['id'] . ' asks for one of the module\'s scopes');
	}
}

// The router takes the first route that matches, so a method a route accepts
// beside its own must not be one another route on the same path answers.
function test_ws_api_routes_do_not_share_a_method_and_path()
{
	$seen = array();

	foreach (ws_api_routes() as $route) {
		$methods = array_merge(array($route['method']), (array) ($route['also_accepts'] ?? array()));

		foreach ($methods as $method) {
			$key = $method . ' ' . $route['path'];

			pg_assert_false(isset($seen[$key]), $key . ' is ' . $route['id'] . (isset($seen[$key]) ? ' and ' . $seen[$key] : ''));
			$seen[$key] = $route['id'];
		}
	}
}

// A removal addressed as a POST for IIS is the same action as the DELETE, and
// is not offered as a second tool.
function test_ws_api_plan_item_removal_is_one_tool()
{
	$offered = array();

	foreach (ws_api_routes() as $route) {
		if (pg_mcp_route_is_offered($route)) {
			$offered[] = pg_mcp_tool_name($route['id']);
		}
	}

	pg_assert_contains('workspace_plan_items_delete', $offered);
	pg_assert_false(in_array('workspace_plan_items_delete_post', $offered, true), 'the POST alias is no tool');

	foreach (array('workspace_notes_create', 'workspace_notes_update', 'workspace_plan_items_list', 'workspace_plan_items_create',
		'workspace_plan_items_update', 'workspace_search', 'workspace_my_day_get') as $tool) {
		pg_assert_contains($tool, $offered);
	}
}

// A message is changed, deleted and a channel marked read with one tool each;
// the deletion addressed as a POST is no second tool.
function test_ws_api_message_edit_delete_and_read_are_tools()
{
	$offered = array();

	foreach (ws_api_routes() as $route) {
		if (pg_mcp_route_is_offered($route)) {
			$offered[] = pg_mcp_tool_name($route['id']);
		}
	}

	foreach (array('workspace_messages_update', 'workspace_messages_delete', 'workspace_channels_read') as $tool) {
		pg_assert_contains($tool, $offered);
	}

	pg_assert_false(in_array('workspace_messages_delete_post', $offered, true), 'the POST alias is no tool');
}

// Deleting a message for oneself is a tool of its own; the changes a phone
// makes to messages and read marks can each be rehearsed with a dry run.
function test_ws_api_message_hide_is_a_tool_and_writes_rehearse()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_true(isset($routes['workspace.messages.hide']) && pg_mcp_route_is_offered($routes['workspace.messages.hide']), 'hide is offered as a tool');
	pg_assert_true(pg_mcp_tool_name('workspace.messages.hide') === 'workspace_messages_hide', 'the tool is workspace_messages_hide');

	foreach (array('workspace.messages.update', 'workspace.messages.delete', 'workspace.messages.delete_post', 'workspace.messages.hide', 'workspace.channels.read') as $id) {
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes dry_run');
	}
}

// The list reads changed messages with changed_since, a moment.
function test_ws_api_messages_list_takes_changed_since()
{
	$types = array();

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.messages.list') {
			foreach ($route['params'] as $param) {
				$types[$param['name']] = $param['type'];
			}
		}
	}

	pg_assert_true(($types['changed_since'] ?? '') === 'datetime', 'changed_since is a datetime');
}

// The sync an app polls is a read and no tool: a model gains nothing from a
// cursor, and a route can stay out of MCP by saying so.
function test_ws_api_sync_is_a_read_and_no_tool()
{
	$sync = null;

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.sync') {
			$sync = $route;
		}
	}

	pg_assert_true(is_array($sync), 'workspace.sync is declared');
	pg_assert_same('GET', $sync['method']);
	pg_assert_same('/workspace/sync', $sync['path']);
	pg_assert_same('workspace:read', $sync['scope']);
	pg_assert_false(pg_mcp_route_is_offered($sync), 'the sync is no MCP tool');

	// The same route without the flag would be one.
	unset($sync['mcp']);
	pg_assert_true(pg_mcp_route_is_offered($sync), 'only the flag keeps it out');
}

// The cursor reads back what it was written from; anything else is refused,
// so a damaged cursor is a 400 and not a silent fresh start.
function test_ws_api_sync_cursor_round_trip()
{
	$mark = array('t' => 1760414400, 'c' => 12, 'm' => 4815, 'ch' => '0a1b2c3d', 'th' => 'ffffffff');
	$cursor = ws_api_sync_cursor_write($mark);

	pg_assert_true((bool) preg_match('/^[A-Za-z0-9_-]+$/', $cursor), 'the cursor is URL safe');
	pg_assert_same($mark, ws_api_sync_cursor_read($cursor));

	// The same state, the same cursor: an answer after which nothing changed
	// is the same bytes, so its ETag matches.
	pg_assert_same($cursor, ws_api_sync_cursor_write($mark));

	foreach (array('', 'abc', 'not a cursor!', rtrim(strtr(base64_encode('{"t":1}'), '+/', '-_'), '='),
		ws_api_sync_cursor_write(array('t' => 1, 'c' => 0, 'm' => 0, 'ch' => 'xyz', 'th' => '00000000')),
		rtrim(strtr(base64_encode('{"t":-1,"c":0,"m":0,"ch":"00000000","th":"00000000"}'), '+/', '-_'), '=')) as $bad) {
		pg_assert_same(null, ws_api_sync_cursor_read($bad), 'refused: ' . $bad);
	}
}

// A list's mark moves when the list does, and only then.
function test_ws_api_sync_mark_follows_the_list()
{
	$list = array(array('id' => 1, 'unread' => 0), array('id' => 2, 'unread' => 3));

	pg_assert_same(ws_api_sync_mark($list), ws_api_sync_mark($list));
	pg_assert_true((bool) preg_match('/^[0-9a-f]{8}$/', ws_api_sync_mark($list)), 'eight hex digits');

	$list[1]['unread'] = 4;

	pg_assert_false(ws_api_sync_mark($list) === ws_api_sync_mark(array(array('id' => 1, 'unread' => 0), array('id' => 2, 'unread' => 3))), 'an unread count moves it');
}

// The search looks in a kind of record only with the scope that reads it; a
// kind it names has to be one a tag can point at.
function test_ws_api_search_record_scopes_name_known_kinds()
{
	foreach (ws_api_search_record_scopes() as $kind => $scope) {
		pg_assert_contains($kind, ws_record_type_keys(), $kind . ' is a record kind');
		pg_assert_true((bool) preg_match('/^[a-z_]+:read$/', $scope), $kind . ' asks for a read scope');
	}

	pg_assert_false(array_key_exists('user_account', ws_api_search_record_scopes()), 'no scope reads user accounts');
}

// A person signed in on their own device writes in their own name; an
// application key, and an OAuth connection (a device row with a client),
// write as the application.
function test_ws_api_device_writes_as_person()
{
	pg_assert_false(ws_api_device_writes_as_person(null), 'an application key writes as the application');
	pg_assert_true(ws_api_device_writes_as_person(array('id' => 4, 'app_id' => 2)), 'a team devices session writes as the person');
	pg_assert_true(ws_api_device_writes_as_person(array('id' => 4, 'app_id' => 2, 'oauth_client_id' => '0')), 'a device row with no client writes as the person');
	pg_assert_false(ws_api_device_writes_as_person(array('id' => 5, 'app_id' => 3, 'oauth_client_id' => '7')), 'an OAuth connection writes as the application');
}

// Only the person's own message asks the assistants it names, as the panel's
// composer does: never an application's words, nor a decision or a note.
function test_ws_api_message_asks_assistants()
{
	pg_assert_true(ws_api_message_asks_assistants(0, 'message'), 'a message from the person\'s device asks');
	pg_assert_false(ws_api_message_asks_assistants(7, 'message'), 'an application\'s message does not ask');
	pg_assert_false(ws_api_message_asks_assistants(0, 'decision'), 'a decision does not ask');
	pg_assert_false(ws_api_message_asks_assistants(0, 'note'), 'a note does not ask');
}
