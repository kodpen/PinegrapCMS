<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workspace's route table in the external API
 * (includes/workspace/api.php) that need no database: every route reaches a
 * handler, no two routes answer the same method on the same path, and each
 * action becomes one MCP tool. Also the scopes the search asks of each kind
 * of panel record (includes/workspace/api_day.php), who a call from a
 * device writes as, which of its messages ask an assistant, where what
 * they ask is carried on, and how a channel's file is handed out
 * (includes/workspace/files.php).
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

// The inbox is read with workspace:read and marked read with workspace:write;
// all three are tools, and the mark can be rehearsed.
function test_ws_api_inbox_routes()
{
	$found = array();

	foreach (ws_api_routes() as $route) {
		if (strpos($route['id'], 'workspace.inbox.') === 0) {
			$found[$route['id']] = $route;
		}
	}

	$ids = array_keys($found);
	sort($ids);

	pg_assert_same(array('workspace.inbox.get', 'workspace.inbox.list', 'workspace.inbox.read'), $ids);

	pg_assert_same('GET /workspace/inbox workspace:read', $found['workspace.inbox.list']['method'] . ' ' . $found['workspace.inbox.list']['path'] . ' ' . $found['workspace.inbox.list']['scope']);
	pg_assert_same('GET /workspace/inbox/{id} workspace:read', $found['workspace.inbox.get']['method'] . ' ' . $found['workspace.inbox.get']['path'] . ' ' . $found['workspace.inbox.get']['scope']);
	pg_assert_same('POST /workspace/inbox/read workspace:write', $found['workspace.inbox.read']['method'] . ' ' . $found['workspace.inbox.read']['path'] . ' ' . $found['workspace.inbox.read']['scope']);
	pg_assert_true(!empty($found['workspace.inbox.read']['dry_run']), 'marking read can be rehearsed');

	foreach ($found as $id => $route) {
		pg_assert_true(pg_mcp_route_is_offered($route), $id . ' is a tool');
	}
}

// The inbox picks up its changed lines as a channel does its messages: a
// changed_since moment, paged with since_id, in the tool as well.
function test_ws_api_inbox_list_takes_changed_since()
{
	$list = null;

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.inbox.list') {
			$list = $route;
		}
	}

	$types = array();

	foreach ($list['params'] as $param) {
		$types[$param['name']] = $param['type'];
	}

	pg_assert_same('datetime', $types['changed_since'] ?? '');
	pg_assert_same('int', $types['since_id'] ?? '');

	$properties = (array) pg_mcp_tool_from_route($list)['inputSchema']['properties'];

	pg_assert_true(isset($properties['changed_since']) && isset($properties['since_id']), 'the tool takes both');
}

// A row changes by being written, sent again or read: either stamp at or
// after the moment counts, only the person's rows after the one given.
function test_ws_api_inbox_changed_where()
{
	pg_assert_same("user_id = '7' AND (created_at >= '1700000000' OR read_at >= '1700000000') AND id > '42'", ws_api_inbox_changed_where(7, 1700000000, 42));

	// An unread row has read_at 0; no moment may reach it.
	pg_assert_same("user_id = '7' AND (created_at >= '1' OR read_at >= '1') AND id > '0'", ws_api_inbox_changed_where('7', 0, -5));
}

// The inbox's addresses start at the site's root; an app gets them whole,
// and any other address as it is.
function test_ws_api_inbox_url()
{
	pg_assert_same('https://example.com/pinegrap/workspace.php?channel=3&message=9', ws_api_inbox_url('/pinegrap/workspace.php?channel=3&message=9', 'https://example.com'));
	pg_assert_same('https://example.com/pinegrap/workspace_tasks.php?task=4', ws_api_inbox_url('/pinegrap/workspace_tasks.php?task=4', 'https://example.com/'));
	pg_assert_same('https://meet.example.org/abc', ws_api_inbox_url('https://meet.example.org/abc', 'https://example.com'));
	pg_assert_same('workspace.php', ws_api_inbox_url('workspace.php', 'https://example.com'));
	pg_assert_same('//elsewhere.example/x', ws_api_inbox_url('//elsewhere.example/x', 'https://example.com'));
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

// Pinegrap AI and an MCP agent asked from a device: after the answer where
// the server can send it first; where it cannot, only an open app's sync,
// which waits for news anyway, does the work in the call, and a message
// written leaves it to the next sync, the panel's screens and the hourly job.
function test_ws_api_carry_on_where()
{
	pg_assert_same('after', ws_api_carry_on_where(true, false), 'PHP-FPM, a message written');
	pg_assert_same('after', ws_api_carry_on_where(true, true), 'PHP-FPM, the sync');
	pg_assert_same('here', ws_api_carry_on_where(false, true), 'IIS, the sync');
	pg_assert_same('', ws_api_carry_on_where(false, false), 'IIS, a message written');
}

// A file is posted and fetched with the caller's own token; neither is an
// MCP tool, since a model has no use for a file's bytes, and the download is
// described as the file itself rather than a record.
function test_ws_api_files_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_true(isset($routes['workspace.files.create']), 'workspace.files.create is declared');
	pg_assert_same('POST', $routes['workspace.files.create']['method']);
	pg_assert_same('/workspace/channels/{id}/files', $routes['workspace.files.create']['path']);
	pg_assert_same('workspace:write', $routes['workspace.files.create']['scope']);
	pg_assert_same('WorkspaceMessage', $routes['workspace.files.create']['returns']);
	pg_assert_false(pg_mcp_route_is_offered($routes['workspace.files.create']), 'posting a file is no MCP tool');

	pg_assert_true(isset($routes['workspace.files.get']), 'workspace.files.get is declared');
	pg_assert_same('GET', $routes['workspace.files.get']['method']);
	pg_assert_same('/workspace/messages/{id}/file', $routes['workspace.files.get']['path']);
	pg_assert_same('workspace:read', $routes['workspace.files.get']['scope']);
	pg_assert_true(!empty($routes['workspace.files.get']['binary']), 'the download answers with the file');
	pg_assert_false(pg_mcp_route_is_offered($routes['workspace.files.get']), 'the download is no MCP tool');
}

// What a browser shows is sent with its own type and inline; anything else,
// and anything a browser would run, as bytes to be saved.
function test_ws_file_type()
{
	pg_assert_same(array('mime' => 'image/jpeg', 'inline' => true), ws_file_type('photo.jpg'));
	pg_assert_same(array('mime' => 'image/jpeg', 'inline' => true), ws_file_type('PHOTO.JPEG'));
	pg_assert_same(array('mime' => 'application/pdf', 'inline' => true), ws_file_type('offer.pdf'));
	pg_assert_same(array('mime' => 'video/mp4', 'inline' => true), ws_file_type('clip.mp4'));
	pg_assert_same(array('mime' => 'audio/mp4', 'inline' => true), ws_file_type('voice.m4a'));

	foreach (array('page.html', 'logo.svg', 'feed.xml', 'script.js', 'report.docx', 'archive.tar.gz', 'README', 'photo.jpg.php') as $name) {
		pg_assert_same(array('mime' => 'application/octet-stream', 'inline' => false), ws_file_type($name), $name . ' is saved, not shown');
	}
}
