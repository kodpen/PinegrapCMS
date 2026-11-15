<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel reads of the external API
 * (includes/workspace/api_channel_reads.php) that need no database: the
 * detail gives what Channel info gives its reader (a guest's last seen only
 * to a manager), the board's cards and columns, an earlier version, the
 * tables list narrowed to one channel, every route reading the channel
 * through the panel's rule (404 for a reader who may not read it), a
 * guest's room with no board, and the export with no MCP tool.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function ws_api_channel_reads_test_route($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function ws_api_channel_reads_test_detail($can_manage)
{
	return array(
		'owner_id'   => 4,
		'owner_name' => 'Ayşe',
		'members'    => array(array('id' => 4), array('id' => 9)),
		'agents'     => array(array('id' => 2)),
		'guests'     => array(array('guest_id' => 7, 'name' => 'Guest', 'access' => 'read', 'seen' => $can_manage ? 'Here now' : '')),
		'eras'       => 2,
		'pin'        => array('id' => 55),
		'tab_counts' => array('decisions' => 3, 'tasks' => 5, 'summary' => true),
		'can_post'   => true,
		'can_manage' => $can_manage,
		'audit'      => false,
		'scheduled_mine' => 1,
	);
}

function test_workspace_api_channel_reads_detail_member()
{
	$out = ws_api_channel_detail_present(array('id' => 12), ws_api_channel_reads_test_detail(false));

	pg_assert_same(array('id' => 12), $out['channel']);
	pg_assert_same(array('user_id' => 4, 'name' => 'Ayşe'), $out['owner']);
	pg_assert_same(2, $out['member_count']);
	pg_assert_same(1, $out['agent_count']);
	pg_assert_same(array(array('guest_id' => 7, 'name' => 'Guest', 'access' => 'read', 'seen' => null)), $out['guests'], 'a reader who does not manage the channel never reads a guest\'s last seen');
	pg_assert_same(55, $out['pinned_message_id']);
	pg_assert_same(3, $out['decisions']);
	pg_assert_same(5, $out['open_tasks']);
	pg_assert_false($out['discussion']);
	pg_assert_true($out['can_post']);
	pg_assert_false($out['can_manage']);
	pg_assert_same(array_keys(ws_api_channel_detail_schema()), array_keys($out), 'the schema names every field');
}

function test_workspace_api_channel_reads_detail_manager()
{
	$detail = ws_api_channel_reads_test_detail(true);
	$detail['thread'] = array('id' => 3);
	$detail['pin'] = null;
	$out = ws_api_channel_detail_present(array('id' => 12), $detail);

	pg_assert_same('Here now', $out['guests'][0]['seen'], 'a manager reads it, as on the screen');
	pg_assert_true($out['discussion']);
	pg_assert_same(null, $out['pinned_message_id']);
}

function test_workspace_api_channel_reads_board()
{
	$task = array('kind' => 'task', 'id' => 8, 'number' => 'T-8', 'title' => 'Ship it', 'sub' => '', 'status' => 'doing', 'priority' => 'high',
		'due_date' => '2026-11-20', 'overdue' => false, 'assignees' => array(array('id' => 4, 'name' => 'Ayşe', 'avatar' => '')));
	$file = array('kind' => 'file', 'id' => 40, 'title' => 'a.pdf', 'sub' => 'Posted by Ayşe', 'url' => '/files/a.pdf', 'message_id' => 40);

	$out = ws_api_channel_board_present(array(
		'group_by' => 'type',
		'columns'  => array(array('key' => 'type:tasks', 'title' => 'Tasks', 'more' => 0, 'items' => array($task)), array('key' => 'type:files', 'title' => 'Files', 'items' => array($file))),
		'counts'   => array('tasks' => 1),
		'can_post' => true,
	));

	pg_assert_same('type', $out['group_by']);
	pg_assert_same(1, $out['open_tasks']);
	$card = $out['columns'][0]['cards'][0];
	pg_assert_same(8, $card['task_id']);
	pg_assert_same('T-8', $card['number']);
	pg_assert_same(array(array('user_id' => 4, 'name' => 'Ayşe')), $card['assignees']);
	pg_assert_same(null, $card['message_id']);
	$card = $out['columns'][1]['cards'][0];
	pg_assert_same(40, $card['message_id']);
	pg_assert_same(null, $card['task_id']);
	pg_assert_same('/files/a.pdf', $card['url']);
	pg_assert_same(array_keys(ws_api_channel_board_card_schema()), array_keys($card));
}

function test_workspace_api_channel_reads_board_not_in_a_guest_room()
{
	pg_assert_false(ws_channel_board_available(array('kind' => 'guest')), 'a guest\'s room has no board');
	pg_assert_false(ws_channel_board_available(array('kind' => 'thread')));
	pg_assert_true(ws_channel_board_available(array('kind' => 'private')));
	pg_assert_contains('ws_channel_board_available(', ws_api_channel_reads_test_source('ws_api_channel_board_get'));
}

function test_workspace_api_channel_reads_era()
{
	$out = ws_api_channel_era_present(array('id' => 3, 'number' => 2, 'title' => '', 'message_count' => 40, 'started_at' => 0, 'ended_at' => 0, 'cleared_by' => 4, 'cleared_by_name' => 'Ayşe', 'last_message_id' => 99));

	pg_assert_same(2, $out['number']);
	pg_assert_contains('2', $out['label']);
	pg_assert_same(40, $out['messages']);
	pg_assert_same(null, $out['from']);
	pg_assert_same(array('user_id' => 4, 'name' => 'Ayşe'), $out['cleared_by']);
	pg_assert_same(99, $out['last_message_id']);
}

function test_workspace_api_channel_reads_tables_of_channel()
{
	$tables = array(array('id' => 1, 'channel_id' => 0), array('id' => 2, 'channel_id' => 5), array('id' => 3, 'channel_id' => 6), array('id' => 4, 'channel_id' => 5));

	pg_assert_same(array(2, 4), array_column(ws_api_wtables_of_channel($tables, 5), 'id'));
	pg_assert_same(array(), ws_api_wtables_of_channel($tables, 9));
	pg_assert_contains('ws_api_channel_or_404(', ws_api_channel_reads_test_source('ws_api_wtables_list'), 'a channel the owner cannot read answers 404');
	$route = ws_api_channel_reads_test_route('workspace.tables.list');
	pg_assert_same('channel_id', $route['params'][1]['name']);
}

// A function's own source.
function ws_api_channel_reads_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

function test_workspace_api_channel_reads_routes_read_as_the_panel()
{
	$handlers = array(
		'workspace.channels.get'        => 'ws_api_channel_get',
		'workspace.channels.files.list' => 'ws_api_channel_files_list',
		'workspace.channels.board.get'  => 'ws_api_channel_board_get',
		'workspace.channels.eras.list'  => 'ws_api_channel_eras_list',
		'workspace.channels.export'     => 'ws_api_channel_export',
	);

	foreach ($handlers as $id => $handler) {
		$route = ws_api_channel_reads_test_route($id);

		pg_assert_true(is_array($route), $id . ' is declared');
		pg_assert_same('GET', $route['method'], $id);
		pg_assert_same('workspace:read', $route['scope'], $id . ': no new scope');
		pg_assert_same($handler, $route['handler'], $id);
		pg_assert_contains('ws_api_channel_or_404(', ws_api_channel_reads_test_source($handler), $id . ': a non-member of a private channel gets 404');
	}
}

function test_workspace_api_channel_reads_mcp_tools()
{
	pg_assert_false(pg_mcp_route_is_offered(ws_api_channel_reads_test_route('workspace.channels.export')), 'the export is REST only');

	foreach (array('workspace.channels.get', 'workspace.channels.files.list', 'workspace.channels.board.get', 'workspace.channels.eras.list') as $id) {
		pg_assert_true(pg_mcp_route_is_offered(ws_api_channel_reads_test_route($id)), $id . ' is an MCP tool');
	}
}

function test_workspace_api_channel_reads_file_rows_shared()
{
	pg_assert_contains('ws_channel_file_rows(', ws_api_channel_reads_test_source('ws_channel_files'), 'the panel\'s tab and the route read the same rows');
	pg_assert_contains('ws_channel_file_rows(', ws_api_channel_reads_test_source('ws_api_channel_files_list'));
}
