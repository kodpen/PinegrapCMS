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
 * they ask is carried on, how a channel's file is handed out
 * (includes/workspace/files.php), and that a handler calls the panel's
 * functions that log in the signed-in person's name as the application's
 * owner.
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

// A channel is marked read with a tool; changing, deleting and hiding a
// message are no tools, since only a team devices session may make them and
// an MCP client signs in through an OAuth connection.
function test_ws_api_message_edit_delete_and_hide_are_no_tools()
{
	$offered = array();

	foreach (ws_api_routes() as $route) {
		if (pg_mcp_route_is_offered($route)) {
			$offered[] = pg_mcp_tool_name($route['id']);
		}
	}

	pg_assert_contains('workspace_channels_read', $offered);

	foreach (array('workspace_messages_update', 'workspace_messages_delete', 'workspace_messages_delete_post', 'workspace_messages_hide') as $tool) {
		pg_assert_false(in_array($tool, $offered, true), $tool . ' is no tool');
	}
}

// The changes a phone makes to messages and read marks can each be rehearsed
// with a dry run.
function test_ws_api_message_writes_rehearse()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_true(isset($routes['workspace.messages.hide']), 'hide is declared');

	foreach (array('workspace.messages.update', 'workspace.messages.delete', 'workspace.messages.delete_post', 'workspace.messages.hide', 'workspace.channels.read') as $id) {
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes dry_run');
	}
}

// The source of a route's handler, to read which gate it calls.
function ws_api_test_handler_source($handler)
{
	$function = new ReflectionFunction($handler);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// A route that only a team devices session may call refuses every OAuth
// connection, and so every MCP client: it is offered to none as a tool. The
// gate is called by the handler itself or by a helper of the module it calls
// (ws_api_pin_target()).
function test_ws_api_own_words_routes_are_no_tools()
{
	$gates = array('ws_api_own_words_only(');

	foreach (get_defined_functions()['user'] as $name) {
		if ((strpos($name, 'ws_api_') === 0) && ($name !== 'ws_api_own_words_only') && (strpos(ws_api_test_handler_source($name), 'ws_api_own_words_only(') !== false)) {
			$gates[] = $name . '(';
		}
	}

	$gated = array();

	foreach (ws_api_routes() as $route) {
		$source = ws_api_test_handler_source($route['handler']);

		foreach ($gates as $gate) {
			if (strpos($source, $gate) !== false) {
				$gated[] = $route['id'];
				pg_assert_false(pg_mcp_route_is_offered($route), $route['id'] . ' is no MCP tool');
				break;
			}
		}
	}

	foreach (array('workspace.messages.update', 'workspace.messages.delete', 'workspace.messages.hide', 'workspace.messages.pin',
		'workspace.messages.pin_close', 'workspace.polls.create', 'workspace.polls.update', 'workspace.scheduled_messages.create') as $id) {
		pg_assert_contains($id, $gated);
	}
}

// A scheduled message is posted later in the person's own name, so it is
// scheduled from a team devices session only, and no MCP tool schedules one;
// listing and deleting the owner's scheduled messages stay tools.
function test_ws_api_scheduled_message_is_scheduled_by_the_person()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$source = ws_api_test_handler_source($routes['workspace.scheduled_messages.create']['handler']);

	pg_assert_true(strpos($source, 'ws_api_own_words_only(') !== false, 'scheduling asks for the team devices session');
	pg_assert_false(strpos($source, 'ws_api_person_only(') !== false, 'an OAuth connection is not enough');
	pg_assert_false(pg_mcp_route_is_offered($routes['workspace.scheduled_messages.create']), 'scheduling is no MCP tool');
	pg_assert_true(!empty($routes['workspace.scheduled_messages.create']['dry_run']), 'scheduling rehearses');

	foreach (array('workspace.scheduled_messages.list', 'workspace.scheduled_messages.delete') as $id) {
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' is a tool');
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

// A message the reader deleted for themselves since the moment counts as
// changed for them alone: those ids join the touched_at range and the rest of
// the condition stays as it was; with none, the range is read alone.
function test_ws_messages_touched_where()
{
	pg_assert_same("channel_id = '3' AND touched_at >= '1700000000' AND id <= '900'", ws_messages_touched_where(3, 1700000000, 900));
	pg_assert_same("channel_id = '3' AND touched_at >= '1700000000' AND id <= '900' AND id > '40'", ws_messages_touched_where('3', 1700000000, 900, 40, array()));
	pg_assert_same("channel_id = '3' AND (touched_at >= '1700000000' OR id IN (41,77)) AND id <= '900' AND id > '40'", ws_messages_touched_where(3, 1700000000, 900, 40, array('41', 77, 77, 0, -2)));
}

// Without a reader, a moment or a message held there is nothing of the
// reader's own to look for, and the database is not asked.
function test_ws_message_hidden_since_needs_a_reader()
{
	pg_assert_same(array(), ws_message_hidden_since(0, 3, 1700000000, 900));
	pg_assert_same(array(), ws_message_hidden_since(7, 3, 0, 900));
	pg_assert_same(array(), ws_message_hidden_since(7, 3, 1700000000, 0));
}

// The person's other devices are told of a message hidden on one of them by
// the two reads that follow changes, and the hide says so.
function test_ws_api_hide_reaches_the_other_devices_in_the_docs()
{
	$descriptions = array();

	foreach (ws_api_routes() as $route) {
		$descriptions[$route['id']] = (string) ($route['description'] ?? '');
	}

	pg_assert_true(strpos($descriptions['workspace.messages.list'], 'deleted for themselves since then') !== false, 'changed_since names hidden messages');
	pg_assert_true(strpos($descriptions['workspace.sync'], 'deleted for themselves since') !== false, 'sync names hidden messages');
	pg_assert_true(strpos($descriptions['workspace.messages.hide'], 'other devices') !== false, 'hide names the other devices');
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
	foreach (array('', '9f8e7d6c') as $state) {
		$mark = array('t' => 1760414400, 'c' => 12, 'm' => 4815, 'ch' => '0a1b2c3d', 'th' => 'ffffffff', 'p' => $state);
		$cursor = ws_api_sync_cursor_write($mark);

		pg_assert_true((bool) preg_match('/^[A-Za-z0-9_-]+$/', $cursor), 'the cursor is URL safe');
		pg_assert_same($mark, ws_api_sync_cursor_read($cursor));

		// The same state, the same cursor: an answer after which nothing
		// changed is the same bytes, so its ETag matches.
		pg_assert_same($cursor, ws_api_sync_cursor_write($mark));
	}

	foreach (array('', 'abc', 'not a cursor!', rtrim(strtr(base64_encode('{"t":1}'), '+/', '-_'), '='),
		ws_api_sync_cursor_write(array('t' => 1, 'c' => 0, 'm' => 0, 'ch' => 'xyz', 'th' => '00000000')),
		ws_api_sync_cursor_write(array('t' => 1, 'c' => 0, 'm' => 0, 'ch' => '00000000', 'th' => '00000000', 'p' => 'xyz')),
		rtrim(strtr(base64_encode('{"t":1,"c":0,"m":0,"ch":"00000000","th":"00000000","p":7}'), '+/', '-_'), '='),
		rtrim(strtr(base64_encode('{"t":-1,"c":0,"m":0,"ch":"00000000","th":"00000000"}'), '+/', '-_'), '=')) as $bad) {
		pg_assert_same(null, ws_api_sync_cursor_read($bad), 'refused: ' . $bad);
	}
}

// A cursor an app holds from before the channel state was added still reads,
// with no state mark, so the state is sent once; and a call that carries no
// state writes its cursor as before, so an app key's ETag still matches.
function test_ws_api_sync_cursor_without_state_mark()
{
	$before = rtrim(strtr(base64_encode('{"t":1760414400,"c":12,"m":4815,"ch":"0a1b2c3d","th":"ffffffff"}'), '+/', '-_'), '=');

	pg_assert_same(array('t' => 1760414400, 'c' => 12, 'm' => 4815, 'ch' => '0a1b2c3d', 'th' => 'ffffffff', 'p' => ''), ws_api_sync_cursor_read($before));
	pg_assert_same($before, ws_api_sync_cursor_write(ws_api_sync_cursor_read($before)));
}

// The open channel's state: each part as the panel's screen has it, nothing
// added on the way out, and a channel's state never marked like another's.
function test_ws_api_sync_channel_state_present()
{
	$message = array('id' => 77, 'channel_id' => 12, 'kind' => 'message', 'text' => 'Kargo yarın');

	$state = array(
		'channel_id' => 12,
		'pin'        => array('message' => $message, 'pinned_by' => array('user_id' => 3, 'name' => 'Erdal'), 'pinned_at' => 0, 'hidden' => 1),
		'tab_counts' => array('decisions' => '4', 'tasks' => 2, 'summary' => 1),
		'board'      => 'd41d8cd98f00b204e9800998ecf8427e',
	);

	$out = ws_api_sync_channel_state_present($state);

	pg_assert_same(array_keys(ws_api_sync_channel_state_schema()), array_keys($out));
	pg_assert_same(array_keys(ws_api_sync_pin_schema()), array_keys($out['pin']));
	pg_assert_same($message, $out['pin']['message']);
	pg_assert_same(null, $out['pin']['pinned_at'], 'no moment, no time');
	pg_assert_same(true, $out['pin']['hidden']);
	pg_assert_same(array('decisions' => 4, 'tasks' => 2, 'summary' => true), $out['tab_counts']);
	pg_assert_same('d41d8cd98f00b204e9800998ecf8427e', $out['board']);

	// A discussion: no pin, no tabs, no board.
	$thread = ws_api_sync_channel_state_present(array('channel_id' => 40, 'pin' => null, 'tab_counts' => null, 'board' => null));

	pg_assert_same(array('channel_id' => 40, 'pin' => null, 'tab_counts' => null, 'board' => null), $thread);

	// Two channels with nothing pinned, counted or on a board are still two
	// marks: a switch always sends the new channel's state.
	pg_assert_false(ws_api_sync_mark(array('channel_id' => 40, 'pin' => null, 'tab_counts' => null, 'board' => null))
		=== ws_api_sync_mark(array('channel_id' => 41, 'pin' => null, 'tab_counts' => null, 'board' => null)), 'the channel is in the mark');

	// Closing the pin on the panel moves the mark.
	$closed = $state;
	$closed['pin']['hidden'] = 0;

	pg_assert_false(ws_api_sync_mark($state) === ws_api_sync_mark($closed), 'hiding the pin moves it');
}

// What is not there comes as null, and every field the answer has is
// declared: an empty inbox has no moment, a caller without the bell's
// permission no count, a call without a person's open channel no state.
function test_ws_api_sync_present_nulls()
{
	$out = ws_api_sync_present(array(
		'cursor' => 'abc', 'channels' => null, 'threads' => null, 'inbox_unread' => 0, 'inbox_changed_at' => 0,
		'notifications_unread' => null, 'gone' => false, 'messages' => array(), 'more' => false,
		'changed' => array(), 'reload' => false, 'channel_state' => null,
	));

	pg_assert_same(array_keys(ws_api_sync_schema()), array_keys($out));
	pg_assert_same(null, $out['inbox_changed_at']);
	pg_assert_same(null, $out['notifications_unread']);
	pg_assert_same(null, $out['channel_state']);
}

// The bell is the Own account permission's: without it the count is left out
// rather than refused, as GET /auth/me leaves its unread out.
function test_ws_api_sync_bell_needs_own_account()
{
	pg_assert_same(null, ws_api_sync_bell(array('id' => 1, 'role' => 0), array('workspace:read', 'workspace:write')));
	pg_assert_same(null, ws_api_sync_bell(array('id' => 1, 'role' => 0), array()));
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

// The inbox's listing says beside the page whether the app must read it
// again (reload), and the document describes that field next to data and
// page; a listing that names nothing beside 'list' stays as it was.
function test_ws_api_inbox_list_declares_reload()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');

	$list = null;

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.inbox.list') {
			$list = $route;
		}
	}

	pg_assert_same(array('list' => 'WorkspaceInboxItem', 'reload' => 'boolean'), $list['returns']);

	$schema = api_openapi_success_schema($list);

	pg_assert_same(array('data', 'page', 'reload'), array_keys($schema['properties']));
	pg_assert_same('boolean', $schema['properties']['reload']['type']);

	$plain = api_openapi_success_schema(array('returns' => array('list' => 'WorkspaceInboxItem')));

	pg_assert_same(array('data', 'page'), array_keys($plain['properties']));
}

// Lines taken away are remembered for a while: a moment within it reads the
// changes whole, a moment further back is told to read the inbox again.
function test_ws_api_inbox_reload()
{
	$now = 1700000000;
	$keep = 30 * 86400;

	pg_assert_same(false, ws_api_inbox_reload($now - 60, $now, $keep));
	pg_assert_same(false, ws_api_inbox_reload($now - $keep, $now, $keep));
	pg_assert_same(true, ws_api_inbox_reload($now - $keep - 1, $now, $keep));
	pg_assert_same(true, ws_api_inbox_reload(0, $now, $keep));
	pg_assert_same(false, ws_api_inbox_reload($now + 60, $now, $keep));
}

// Back in a channel, the lines about its tasks come back only for the tasks
// seen through it: the creator and the people on a task saw it all along.
function test_ws_inbox_task_through_channel()
{
	$task = array('channel_id' => 7, 'creator_id' => 3);

	pg_assert_same(true, ws_inbox_task_through_channel(5, $task, array(4)));
	pg_assert_same(true, ws_inbox_task_through_channel(5, $task, array()));
	pg_assert_same(false, ws_inbox_task_through_channel(3, $task, array(4)), 'its creator');
	pg_assert_same(false, ws_inbox_task_through_channel(5, $task, array('4', '5')), 'one of its people');
	pg_assert_same(false, ws_inbox_task_through_channel(5, array('channel_id' => 0, 'creator_id' => 3), array()), 'a task in no channel');
}

// A row changes by being written, sent again or read: either stamp at or
// after the moment counts, only the person's rows after the one given.
function test_ws_api_inbox_changed_where()
{
	pg_assert_same("user_id = '7' AND (created_at >= '1700000000' OR read_at >= '1700000000') AND id > '42'", ws_api_inbox_changed_where(7, 1700000000, 42));

	// An unread row has read_at 0; no moment may reach it.
	pg_assert_same("user_id = '7' AND (created_at >= '1' OR read_at >= '1') AND id > '0'", ws_api_inbox_changed_where('7', 0, -5));
}

// A line taken away is found by when it went, only the person's marks after
// the one given, and no further than the live rows were read when that read
// stopped short.
function test_ws_api_inbox_deleted_where()
{
	pg_assert_same("user_id = '7' AND deleted_at >= '1700000000' AND id > '42'", ws_api_inbox_deleted_where(7, 1700000000, 42));
	pg_assert_same("user_id = '7' AND deleted_at >= '1' AND id > '0' AND id <= '900'", ws_api_inbox_deleted_where('7', 0, -5, 900));
}

// A line taken away comes back with the fields of any other line, deleted,
// read and with nothing of what it said; a line still there is not deleted.
function test_ws_api_inbox_deleted_present()
{
	$line = ws_api_inbox_deleted_present(array('id' => '12', 'kind' => 'mention', 'channel_id' => '3', 'created_at' => '1700000000', 'deleted_at' => '1700000100'));

	pg_assert_same(array_keys(ws_api_inbox_schema()), array_keys($line));
	pg_assert_same(12, $line['id']);
	pg_assert_same('mention', $line['kind']);
	pg_assert_true($line['deleted'], 'marked deleted');
	pg_assert_true($line['read'], 'no longer waits');
	pg_assert_same('', $line['title']);
	pg_assert_same(null, $line['channel_id']);
	pg_assert_same('2023-11-14T22:13:20Z', $line['created_at']);
	pg_assert_same('boolean', ws_api_inbox_schema()['deleted']);
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

// Muting a channel, voting in a poll and joining or leaving a discussion are
// the person's own choices from a team app: writes that rehearse, offered to
// no MCP client.
function test_ws_api_notify_vote_and_thread_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$expect = array(
		'workspace.channels.notify' => array('/workspace/channels/{id}/notify', 'WorkspaceChannel'),
		'workspace.polls.vote'      => array('/workspace/polls/{id}/vote', 'WorkspaceMessage'),
		'workspace.threads.join'    => array('/workspace/threads/{id}/join', 'WorkspaceThread'),
		'workspace.threads.leave'   => array('/workspace/threads/{id}/leave', 'WorkspaceThread'),
	);

	foreach ($expect as $id => $shape) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same('POST', $routes[$id]['method'], $id . ' is a POST');
		pg_assert_same($shape[0], $routes[$id]['path'], $id . ' path');
		pg_assert_same('workspace:write', $routes[$id]['scope'], $id . ' scope');
		pg_assert_same($shape[1], $routes[$id]['returns'], $id . ' answer');
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
	}

	pg_assert_true(isset(ws_openapi_objects()['WorkspacePoll']), 'the poll object is declared');
}

// The poll on a message, from what the channel screen draws: an anonymous
// poll names nobody, a guest who voted has no user id, and a poll open
// until it is closed has no closes_at.
function test_ws_api_poll_present()
{
	$present = ws_api_poll_present(array(
		'id' => 7, 'multiple' => false, 'anonymous' => false, 'closed' => false, 'closes_at' => 0,
		'voters' => 2, 'voted' => true, 'can_vote' => true, 'result_id' => 0,
		'options' => array(
			array('id' => 31, 'label' => 'Tuesday', 'count' => 2, 'mine' => true, 'leading' => true, 'voters' => array(
				array('id' => 12, 'name' => 'Ayşe', 'avatar' => ''),
				array('id' => 0, 'name' => 'Guest', 'avatar' => '', 'guest' => true),
			)),
			array('id' => 32, 'label' => 'Wednesday', 'count' => 0, 'mine' => false, 'leading' => false, 'voters' => array()),
		),
	));

	pg_assert_same(7, $present['id']);
	pg_assert_false($present['can_edit'] || $present['can_close'], 'what the person may do is false unless the screen says so');
	pg_assert_same(null, $present['closes_at'], 'open until closed');
	pg_assert_same(null, $present['result_message_id']);
	pg_assert_same(array(31, 32), array_column($present['options'], 'id'));
	pg_assert_same(array(array('user_id' => 12, 'name' => 'Ayşe'), array('user_id' => null, 'name' => 'Guest')), $present['options'][0]['voters']);
	pg_assert_true($present['options'][0]['mine'] && !$present['options'][1]['mine'], 'mine marks the person\'s choice');
	pg_assert_same(array_keys(ws_api_poll_schema()), array_keys($present), 'the answer has the declared fields in order');
}

// The download takes a size for a picture's smaller copy, bounded so the
// query string cannot ask for anything absurd.
function test_ws_api_files_get_takes_a_size()
{
	$route = null;

	foreach (ws_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.files.get') {
			$route = $candidate;
		}
	}

	$size = null;

	foreach ((array) ($route['params'] ?? array()) as $param) {
		if ($param['name'] === 'size') {
			$size = $param;
		}
	}

	pg_assert_true(is_array($size), 'size is declared');
	pg_assert_same('query', $size['in']);
	pg_assert_same('int', $size['type']);
	pg_assert_same(1, $size['min']);
	pg_assert_same(4096, $size['max']);
}

// A smaller copy is drawn at one of a few fixed edges: the first at least as
// large as asked, the largest of them at most.
function test_ws_file_preview_edge()
{
	pg_assert_same(160, ws_file_preview_edge(1));
	pg_assert_same(160, ws_file_preview_edge(160));
	pg_assert_same(320, ws_file_preview_edge(161));
	pg_assert_same(640, ws_file_preview_edge(600));
	pg_assert_same(1280, ws_file_preview_edge(1000));
	pg_assert_same(1920, ws_file_preview_edge(1500));
	pg_assert_same(1920, ws_file_preview_edge(4096));
}

// A picture already no larger than the edge, and a file that is no picture,
// are sent as they are; nothing is drawn or kept for them.
function test_ws_file_preview_sends_the_original()
{
	$directory = sys_get_temp_dir() . '/pg_ws_preview_test_' . getmypid();
	@mkdir($directory);

	$text = $directory . '/notes.jpg';
	file_put_contents($text, 'not a picture');
	pg_assert_same(null, ws_file_preview($text, 160), 'not a picture');

	if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
		$small = $directory . '/small.png';
		$image = imagecreatetruecolor(150, 90);
		imagepng($image, $small);
		imagedestroy($image);

		pg_assert_same(null, ws_file_preview($small, 160), 'no larger than the edge');
		pg_assert_same(null, ws_file_preview($small, 1), 'smaller than the smallest edge');
		@unlink($small);
	}

	@unlink($text);
	@rmdir($directory);
}

// The pin bar's actions are writes of the person's own app: they can be
// rehearsed, answer with the channel's state the sync carries, and are no
// MCP tools.
function test_ws_api_pin_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$paths = array('workspace.messages.pin' => '/workspace/messages/{id}/pin', 'workspace.messages.pin_close' => '/workspace/messages/{id}/pin/close');

	foreach ($paths as $id => $path) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same('POST', $routes[$id]['method']);
		pg_assert_same($path, $routes[$id]['path']);
		pg_assert_same('workspace:write', $routes[$id]['scope']);
		pg_assert_same('WorkspaceSyncChannelState', $routes[$id]['returns']);
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes dry_run');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
	}

	$on = null;

	foreach ($routes['workspace.messages.pin']['params'] as $param) {
		if ($param['name'] === 'on') {
			$on = $param;
		}
	}

	pg_assert_true(is_array($on) && ($on['type'] === 'bool') && ($on['default'] === true), 'on pins by default');
}

// Pinning a channel and moving it in the list are the person's own sidebar:
// a write that rehearses, offered to no MCP client, whose fields the channel
// object carries under the same names.
function test_ws_api_channel_prefs_route()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$route = $routes['workspace.channels.prefs'] ?? null;

	pg_assert_true(is_array($route), 'the route is declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/channels/{id}/prefs', $route['path']);
	pg_assert_same('workspace:write', $route['scope']);
	pg_assert_same('WorkspaceChannel', $route['returns']);
	pg_assert_true(!empty($route['dry_run']), 'it rehearses');
	pg_assert_false(pg_mcp_route_is_offered($route), 'it is no MCP tool');

	$body = array();

	foreach ($route['params'] as $param) {
		if (($param['in'] ?? '') === 'body') {
			$body[$param['name']] = $param;
			pg_assert_true(empty($param['required']), $param['name'] . ' may be left out');
		}
	}

	pg_assert_same(array('pinned', 'order'), array_keys($body));
	pg_assert_same(1, $body['order']['min'], 'the top is place 1');
	pg_assert_true(array_key_exists('pinned', ws_api_channel_schema()) && array_key_exists('order', ws_api_channel_schema()), 'the channel carries both');
}

// A channel the person never placed has no order; a public one they are not
// in is neither pinned nor placed.
function test_ws_api_channel_present_prefs()
{
	$brief = array(
		'id' => 4, 'name' => 'sales', 'kind' => 'public', 'topic' => '', 'joined' => true, 'notify' => 'all',
		'pinned' => true, 'sort' => 3, 'contact_id' => 0, 'customer_type' => '', 'customer_id' => 0,
		'department_id' => 0, 'archived' => false, 'unread' => 0, 'last_message_at' => 0,
	);

	$present = ws_api_channel_present($brief);

	pg_assert_true($present['pinned']);
	pg_assert_same(3, $present['order']);

	$present = ws_api_channel_present(array_merge($brief, array('joined' => false, 'pinned' => false, 'sort' => 0)));

	pg_assert_false($present['pinned']);
	pg_assert_same(null, $present['order']);
	pg_assert_same(array_keys(ws_api_channel_schema()), array_keys($present), 'the answer has the declared fields in order');
}

// Asking, changing and closing a poll: writes that rehearse, offered to no
// MCP client. The change answers PATCH beside POST, and its path takes no
// method the vote or the closing answers.
function test_ws_api_poll_create_update_close_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$expect = array(
		'workspace.polls.create' => '/workspace/channels/{id}/polls',
		'workspace.polls.update' => '/workspace/polls/{id}',
		'workspace.polls.close'  => '/workspace/polls/{id}/close',
	);

	foreach ($expect as $id => $path) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same('POST', $routes[$id]['method'], $id . ' is a POST');
		pg_assert_same($path, $routes[$id]['path'], $id . ' path');
		pg_assert_same('workspace:write', $routes[$id]['scope'], $id . ' scope');
		pg_assert_same('WorkspaceMessage', $routes[$id]['returns'], $id . ' answers with the poll\'s message');
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
	}

	pg_assert_same(array('PATCH'), $routes['workspace.polls.update']['also_accepts'], 'a change also answers PATCH');

	$params = array();

	foreach ($routes['workspace.polls.update']['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_false(!empty($params['question']['required']) || !empty($params['options']['required']), 'a change sends only what changes');
	pg_assert_true(!empty($params['closes_at']['nullable']), 'null leaves the poll open until it is closed');
}

// The checks a poll is asked with, without a database: what a dry run meets
// before anything is written.
function test_ws_poll_input()
{
	$ok = ws_poll_input(array('question' => '  Which day?  ', 'options' => array('Tuesday', ' Wednesday ', 'Tuesday', ''), 'closes_at' => 0));

	pg_assert_true($ok['ok'], 'a question with two different options');
	pg_assert_same('Which day?', $ok['question']);
	pg_assert_same(array('Tuesday', 'Wednesday'), $ok['options'], 'blank and repeated options are left out');
	pg_assert_same(0, $ok['closes_at'], 'open until closed');

	$refused = array(
		'question'  => array('question' => '', 'options' => array('A', 'B')),
		'options'   => array('question' => 'Q', 'options' => array('A', 'A')),
		'closes_at' => array('question' => 'Q', 'options' => array('A', 'B'), 'closes_at' => time() + 30),
	);

	foreach ($refused as $field => $data) {
		$result = ws_poll_input($data);
		pg_assert_false($result['ok'], $field . ' is refused');
		pg_assert_same($field, $result['field'], $field . ' is named');
	}

	pg_assert_false(ws_poll_input(array('question' => 'Q', 'options' => range(1, 11)))['ok'], 'eleven options are too many');
	pg_assert_same('question', ws_poll_input(array('question' => str_repeat('a', WS_MESSAGE_MAX + 1), 'options' => array('A', 'B')))['field'], 'a question longer than a message');
	pg_assert_true(ws_poll_input(array('question' => 'Q', 'options' => array('A', 'B'), 'closes_at' => time() + 3600))['ok'], 'closing in an hour');
}

// Moving a channel into a group is staff work on the channel, gated the way
// changing the channel is: any caller the owner's rights allow, a rehearsal,
// and offered to an MCP client as the other channel changes are.
function test_ws_api_channel_group_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$move = $routes['workspace.channels.group'] ?? null;

	pg_assert_true(is_array($move), 'the move is declared');
	pg_assert_same('POST', $move['method']);
	pg_assert_same('/workspace/channels/{id}/group', $move['path']);
	pg_assert_same($routes['workspace.channels.update']['scope'], $move['scope'], 'the permission that changes a channel');
	pg_assert_same('WorkspaceChannelGroupMove', $move['returns']);
	pg_assert_true(!empty($move['dry_run']), 'it rehearses');
	pg_assert_same(pg_mcp_route_is_offered($routes['workspace.channels.update']), pg_mcp_route_is_offered($move), 'a tool when changing a channel is one');

	$body = array();

	foreach ($move['params'] as $param) {
		if (($param['in'] ?? '') === 'body') {
			$body[$param['name']] = $param;
		}
	}

	pg_assert_same(array('group_id'), array_keys($body));
	pg_assert_true(!empty($body['group_id']['required']) && !empty($body['group_id']['nullable']), 'group_id is sent; null takes the channel out');
	pg_assert_same(0, $body['group_id']['min'], '0 takes the channel out');

	$list = $routes['workspace.channel_groups.list'] ?? null;

	pg_assert_true(is_array($list), 'the groups are listed');
	pg_assert_same('GET', $list['method']);
	pg_assert_same('/workspace/channel-groups', $list['path']);
	pg_assert_same('workspace:read', $list['scope']);
	pg_assert_same(array('list' => 'WorkspaceChannelGroup'), $list['returns']);
	pg_assert_true(array_key_exists('group_id', ws_api_channel_schema()), 'the channel says which group it is in');
}

// How many people hold access on a group is for staff to see; a group at the
// top has no parent, and one without a colour none.
function test_ws_api_channel_group_present()
{
	$group = array('id' => 7, 'parent_id' => 0, 'name' => 'Customers', 'color' => 0, 'hex' => '', 'sort' => 1, 'access' => 3, 'mine' => true, 'path' => 'Customers');

	$staff = ws_api_channel_group_present($group, true);

	pg_assert_same(7, $staff['id']);
	pg_assert_same('Customers', $staff['path']);
	pg_assert_same(null, $staff['parent_id']);
	pg_assert_same(null, $staff['color']);
	pg_assert_same(3, $staff['access']);
	pg_assert_true($staff['mine']);
	pg_assert_same(array_keys(ws_api_channel_group_schema()), array_keys($staff), 'the answer has the declared fields in order');

	$other = ws_api_channel_group_present(array_merge($group, array('parent_id' => 2, 'hex' => '#0d6efd', 'mine' => false)), false);

	pg_assert_same(2, $other['parent_id']);
	pg_assert_same('#0d6efd', $other['color']);
	pg_assert_same(null, $other['access'], 'only staff see who holds access');
	pg_assert_false($other['mine']);
}

// A move to where the channel already is names nobody. The dry run's detail
// leaves the channel out; the answer carries it.
function test_ws_api_channel_group_move_present()
{
	$move = array('ok' => true, 'error' => '', 'joining' => array(), 'leaving' => array(), 'joining_ids' => array(5), 'leaving_ids' => array(), 'path' => 'Customers');
	$channel = array('id' => 4, 'group_id' => 7);

	$same = ws_api_channel_group_move_present($channel, 7, $move, false);

	pg_assert_false($same['changes']);
	pg_assert_same(array(), $same['joining'], 'nothing moves, so nobody joins');
	pg_assert_same(7, $same['group_id']);
	pg_assert_same(7, $same['previous_group_id']);
	pg_assert_false(array_key_exists('channel', $same), 'a dry run has no channel');

	$out = ws_api_channel_group_move_present($channel, 0, array_merge($move, array('joining_ids' => array(), 'path' => '')), true, array('id' => 4));

	pg_assert_true($out['changes']);
	pg_assert_same(null, $out['group_id'], 'out of every group');
	pg_assert_same(null, $out['group_path']);
	pg_assert_same(array(), $out['leaving']);
	pg_assert_same(array_keys(ws_api_channel_group_move_schema()), array_keys($out), 'the answer has the declared fields in order');
}

// Arranging the groups themselves is staff work, gated the way moving a
// channel into a group is: the permission that changes a channel, a rehearsal
// on every write, and one MCP tool per action; the removal addressed as a
// POST for IIS is no second tool.
function test_ws_api_channel_group_management_routes()
{
	$routes = array();
	$offered = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;

		if (pg_mcp_route_is_offered($route)) {
			$offered[] = pg_mcp_tool_name($route['id']);
		}
	}

	$expected = array(
		'workspace.channel_groups.create'        => array('POST', '/workspace/channel-groups', 'WorkspaceChannelGroup'),
		'workspace.channel_groups.update'        => array('POST', '/workspace/channel-groups/{id}', 'WorkspaceChannelGroupChange'),
		'workspace.channel_groups.delete'        => array('DELETE', '/workspace/channel-groups/{id}', 'WorkspaceChannelGroupRemoval'),
		'workspace.channel_groups.delete_post'   => array('POST', '/workspace/channel-groups/{id}/delete', 'WorkspaceChannelGroupRemoval'),
		'workspace.channel_groups.access.grant'  => array('POST', '/workspace/channel-groups/{id}/access', 'WorkspaceChannelGroupAccessChange'),
		'workspace.channel_groups.access.revoke' => array('DELETE', '/workspace/channel-groups/{id}/access/{user_id}', 'WorkspaceChannelGroupAccessChange'),
	);

	foreach ($expected as $id => $shape) {
		$route = $routes[$id] ?? null;

		pg_assert_true(is_array($route), $id . ' is declared');
		pg_assert_same($shape[0], $route['method'], $id);
		pg_assert_same($shape[1], $route['path'], $id);
		pg_assert_same($shape[2], $route['returns'], $id);
		pg_assert_same($routes['workspace.channels.group']['scope'], $route['scope'], $id . ' asks what moving a channel into a group asks');
		pg_assert_true(!empty($route['dry_run']), $id . ' rehearses');
	}

	pg_assert_same(array('PATCH'), $routes['workspace.channel_groups.update']['also_accepts'], 'PATCH beside POST');
	pg_assert_same(array('POST'), $routes['workspace.channel_groups.access.revoke']['also_accepts'], 'POST beside DELETE for IIS');

	$access = $routes['workspace.channel_groups.access'] ?? null;

	pg_assert_true(is_array($access), 'who holds access is read');
	pg_assert_same('GET', $access['method']);
	pg_assert_same('/workspace/channel-groups/{id}/access', $access['path']);
	pg_assert_same('workspace:read', $access['scope']);
	pg_assert_same('WorkspaceChannelGroupAccess', $access['returns']);

	foreach (array('workspace_channel_groups_create', 'workspace_channel_groups_update', 'workspace_channel_groups_delete',
		'workspace_channel_groups_access', 'workspace_channel_groups_access_grant', 'workspace_channel_groups_access_revoke') as $tool) {
		pg_assert_contains($tool, $offered);
	}

	pg_assert_false(in_array('workspace_channel_groups_delete_post', $offered, true), 'the POST alias is no tool');

	$body = array();

	foreach ($routes['workspace.channel_groups.update']['params'] as $param) {
		if (($param['in'] ?? '') === 'body') {
			$body[$param['name']] = $param;
		}
	}

	pg_assert_same(array('name', 'color', 'parent_id', 'before_id'), array_keys($body));
	pg_assert_same(ws_palette_hexes(), $body['color']['values'], 'a colour is one of the palette\'s');
	pg_assert_true(!empty($body['color']['nullable']) && !empty($body['parent_id']['nullable']), 'null clears the colour and puts the group at the top');
}

// Who a chain of groups wants in a channel: the nearest group that gives the
// access is the one named, and only people in the team count.
function test_ws_group_wanted_from()
{
	$rows = array(
		array('group_id' => 3, 'user_id' => 10),
		array('group_id' => 1, 'user_id' => 10),
		array('group_id' => 1, 'user_id' => 11),
		array('group_id' => 9, 'user_id' => 12),
		array('group_id' => 1, 'user_id' => 99),
	);

	pg_assert_same(array(10 => 3, 11 => 1), ws_group_wanted_from(array(3, 1), $rows, array(10, 11, 12)));
	pg_assert_same(array(10 => 1, 11 => 1), ws_group_wanted_from(array(1), $rows, array(10, 11, 12)), 'a group below gives nothing upwards');
	pg_assert_same(array(), ws_group_wanted_from(array(), $rows, array(10, 11, 12)), 'no group, nobody');
}

// Bringing a channel in step: in come the wanted who are not members, out go
// only the members a group had brought whom no group wants now.
function test_ws_group_sync_split()
{
	$members = array(
		10 => array('role' => 'member', 'via_group' => 3),
		11 => array('role' => 'member', 'via_group' => 0),
		12 => array('role' => 'owner', 'via_group' => 3),
		13 => array('role' => 'member', 'via_group' => 1),
	);

	$split = ws_group_sync_split($members, array(10 => 1, 14 => 1));

	pg_assert_same(array(14), $split['in']);
	pg_assert_same(array(13), $split['out'], 'joined by themselves (11) and the owner (12) stay');

	$none = ws_group_sync_split($members, array(10 => 3, 13 => 1));

	pg_assert_same(array(), $none['in']);
	pg_assert_same(array(), $none['out']);
}

// A channel's chain once a group on it goes inside another one, or is taken
// away.
function test_ws_group_chain_after_a_change()
{
	pg_assert_same(array(5, 3, 7, 8), ws_group_chain_moved(array(5, 3, 1), 3, array(7, 8)), 'what is above the group changes, what is below stays');
	pg_assert_same(array(3), ws_group_chain_moved(array(3, 1), 3, array()), 'to the top');
	pg_assert_same(array(4, 2), ws_group_chain_moved(array(4, 2), 3, array(7)), 'a channel the group is not above');
	pg_assert_same(array(5, 1), ws_group_chain_removed(array(5, 3, 1), 3), 'what was in it moves up');
	pg_assert_same(array(1), ws_group_chain_removed(array(3, 1), 3), 'its own channels go to the group it was in');
}

// A colour is written as the code the list shows; anything else is none.
function test_ws_palette_place_of()
{
	$hexes = ws_palette_hexes();

	pg_assert_same(count(ws_palette()), count($hexes));
	pg_assert_same(1, ws_palette_place_of($hexes[0]));
	pg_assert_same(count($hexes), ws_palette_place_of(strtoupper($hexes[count($hexes) - 1])));
	pg_assert_same(0, ws_palette_place_of(''));
	pg_assert_same(0, ws_palette_place_of('#000000'));
	pg_assert_same($hexes[4], ws_palette_hex(ws_palette_place_of($hexes[4])), 'the round trip');
}

// A channel the owner of the application does not read is counted, never
// named; a channel nobody comes into or leaves is left out.
function test_ws_api_channel_group_memberships_present()
{
	$out = ws_api_channel_group_memberships_present(array(
		4 => array('added' => array(10, 11), 'removed' => array(12)),
		6 => array('added' => array(), 'removed' => array()),
	), array(6 => 'general'));

	pg_assert_same(2, $out['joining']);
	pg_assert_same(1, $out['leaving']);
	pg_assert_same(array(), $out['channels'], 'the private channel is not named');
	pg_assert_same(1, $out['unseen_channels']);
	pg_assert_same(array_keys(ws_api_channel_group_memberships_schema()), array_keys($out), 'the answer has the declared fields in order');
}

// Taking a group away names what moves up and counts what the owner of the
// application does not read.
function test_ws_api_channel_group_removal_present()
{
	$memberships = ws_api_channel_group_memberships_present(array(), array());
	$group = array('id' => 7, 'parent_id' => 0, 'name' => 'Customers', 'path' => 'Customers');

	$out = ws_api_channel_group_removal_present($group, array(4, 5), array(8 => 'Istanbul'), array(), array(4 => 'sales'), $memberships, false);

	pg_assert_same(null, $out['parent_id'], 'what was in it goes to the top');
	pg_assert_same(array(array('channel_id' => 4, 'name' => 'sales')), $out['channels']);
	pg_assert_same(1, $out['unseen_channels']);
	pg_assert_same(array(array('id' => 8, 'name' => 'Istanbul')), $out['groups']);
	pg_assert_false($out['deleted'], 'a dry run deletes nothing');
	pg_assert_same(array_keys(ws_api_channel_group_removal_schema()), array_keys($out), 'the answer has the declared fields in order');
}

/**
 * The workspace functions that write their own line in the activity log in
 * the name of the signed-in person: log_activity() and the session's user
 * name in one function body.
 *
 * @return string[] function names
 */
function pg_test_ws_api_session_loggers()
{
	$names = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php') as $file) {
		if (strpos(basename($file), 'api') === 0) {
			continue;
		}

		$parts = preg_split('/^function\s+(\w+)\s*\(/m', (string) file_get_contents($file), -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 1; $i < count($parts); $i += 2) {
			$body = $parts[$i + 1];

			if ((strpos($body, 'log_activity(') !== false) && (strpos($body, "\$_SESSION['sessionusername']") !== false)) {
				$names[] = $parts[$i];
			}
		}
	}

	return $names;
}

/**
 * The calls of the given functions in a PHP source, each with whether it
 * sits inside a closure handed to ws_api_as_owner().
 *
 * @param string   $source
 * @param string[] $names
 * @return array[] name, line, as_owner
 */
function pg_test_ws_api_calls_as_owner($source, $names)
{
	$tokens = token_get_all($source);
	$wanted = array_flip($names);
	$significant = array();

	foreach ($tokens as $token) {
		if (!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			$significant[] = $token;
		}
	}

	$depth = 0;
	$owner = array();
	$pending = false;
	$calls = array();
	$count = count($significant);

	for ($i = 0; $i < $count; $i++) {
		$token = $significant[$i];

		if ($token === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
			$depth++;

			// The body of the closure handed to ws_api_as_owner().
			if ($pending && ($token === '{')) {
				$owner[] = $depth;
				$pending = false;
			}

			continue;
		}

		if ($token === '}') {
			if (!empty($owner) && (end($owner) === $depth)) {
				array_pop($owner);
			}

			$depth--;
			continue;
		}

		if (!is_array($token) || ($token[0] !== T_STRING) || (($significant[$i + 1] ?? null) !== '(')) {
			continue;
		}

		$previous = $significant[$i - 1] ?? null;

		if (is_array($previous) && in_array($previous[0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON), true)) {
			continue;
		}

		if ($token[1] === 'ws_api_as_owner') {
			$pending = is_array($significant[$i + 2] ?? null) && ($significant[$i + 2][0] === T_FUNCTION);
		} elseif (isset($wanted[$token[1]])) {
			$calls[] = array('name' => $token[1], 'line' => $token[2], 'as_owner' => !empty($owner));
		}
	}

	return $calls;
}

// The scan above tells a call inside the closure from one beside it.
function test_ws_api_calls_as_owner_scan()
{
	$source = "<?php\nfunction a() {\n    \$x = ws_api_as_owner(function () use (\$y) {\n        if (\$y) { \$s = \"{\$y}\"; }\n        return ws_channel_create(\$y);\n    });\n    ws_channel_create(\$x);\n}\n";
	$calls = pg_test_ws_api_calls_as_owner($source, array('ws_channel_create'));

	pg_assert_same(2, count($calls));
	pg_assert_true($calls[0]['as_owner'], 'inside the closure');
	pg_assert_same(5, $calls[0]['line']);
	pg_assert_false($calls[1]['as_owner'], 'after the closure');
}

// The API keeps no session: a handler calls a panel function that writes its
// activity log line in the name of the signed-in person inside
// ws_api_as_owner(), or the line is written with no name.
function test_ws_api_panel_log_lines_carry_the_owner()
{
	$loggers = pg_test_ws_api_session_loggers();

	foreach (array('ws_channel_create', 'ws_message_delete', 'ws_template_apply', 'ws_group_save') as $name) {
		pg_assert_true(in_array($name, $loggers, true), $name . '() writes its line in the name of the signed-in person');
	}

	$found = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/api*.php') as $file) {
		foreach (pg_test_ws_api_calls_as_owner((string) file_get_contents($file), $loggers) as $call) {
			$found[$call['name']] = true;
			pg_assert_true($call['as_owner'], basename($file) . ':' . $call['line'] . ' calls ' . $call['name'] . '() inside ws_api_as_owner()');
		}
	}

	foreach (array('ws_channel_create', 'ws_message_delete', 'ws_template_apply', 'ws_group_save') as $name) {
		pg_assert_true(isset($found[$name]), 'the API calls ' . $name . '()');
	}
}
