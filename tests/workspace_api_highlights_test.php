<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for readers' highlights in the Workspace API
 * (includes/workspace/api_highlights.php) that need no database: which rows
 * reach which caller, who may write a note, the keys of WorkspaceMessage
 * and the person-only write routes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// One message's rows as ws_highlights_map_rows() gives them to reader 7.
function ws_api_highlights_test_rows()
{
	$row = function ($id, $user_id, $scope, $can_remove) {
		return array('id' => $id, 'quote' => 'milk', 'occurrence' => 0, 'scope' => $scope, 'note' => 'n' . $id,
			'user_id' => $user_id, 'name' => 'P' . $user_id, 'tone' => 3, 'mine' => ($user_id === 7), 'can_remove' => $can_remove);
	};

	return array($row(1, 7, 'all', true), $row(2, 7, 'me', true), $row(3, 9, 'all', false), $row(4, 9, 'me', true));
}

function test_ws_api_highlights_me_rows_reach_the_person_only()
{
	$ids = function ($rows) {
		return array_map(function ($row) { return $row['id']; }, $rows);
	};

	// The person's own session: every 'all' row and their own 'me' row, never
	// another person's 'me' row.
	pg_assert_same(array(1, 2, 3), $ids(ws_api_message_highlights_rows(ws_api_highlights_test_rows(), 7, true, true)), 'own session');

	// An application key or an OAuth connection: 'all' rows only.
	pg_assert_same(array(1, 3), $ids(ws_api_message_highlights_rows(ws_api_highlights_test_rows(), 7, true, false)), 'key or connection');
	pg_assert_same(array(), ws_api_message_highlights_rows(array(), 7, true, true), 'none');
}

function test_ws_api_highlights_row_shape_and_rights()
{
	$rows = ws_api_message_highlights_rows(ws_api_highlights_test_rows(), 7, true, true);

	pg_assert_same(array_keys(ws_api_message_highlight_schema()), array_keys($rows[0]), 'keys as the schema declares');
	pg_assert_true($rows[0]['mine'] && $rows[0]['can_note'] && $rows[0]['can_remove'], 'own all row');
	pg_assert_false($rows[2]['mine'] || $rows[2]['can_note'] || $rows[2]['can_remove'], 'another person\'s all row');
	pg_assert_same('P9', $rows[2]['name'], 'maker name');

	// Where the reader may no longer react (an archived channel), the note
	// of an 'all' one is read-only; a 'me' one stays theirs.
	$archived = ws_api_message_highlights_rows(ws_api_highlights_test_rows(), 7, false, true);
	pg_assert_false($archived[0]['can_note'], 'all row read-only');
	pg_assert_true($archived[1]['can_note'], 'me row still noted');
}

function test_ws_api_highlights_message_keys()
{
	$schema = ws_api_message_schema();

	pg_assert_same('WorkspaceMessageHighlight[]', $schema['highlights'], 'highlights');
	pg_assert_same('string', $schema['highlight_text'], 'highlight_text');
	pg_assert_same('boolean', $schema['can_highlight_all'], 'can_highlight_all');
	pg_assert_same('ws_api_message_highlight_schema', ws_openapi_objects()['WorkspaceMessageHighlight'] ?? null, 'object declared');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');

	foreach (array("'highlights'", "'highlight_text'", "'can_highlight_all'", 'ws_api_message_highlights_map($viewer, $rows)') as $needle) {
		pg_assert_true(strpos($source, $needle) !== false, $needle . ' in the presenter');
	}
}

function test_ws_api_highlights_writes_are_the_person_own()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('workspace.messages.highlights.set' => 'ws_api_messages_highlight', 'workspace.highlights.note' => 'ws_api_highlights_note',
		'workspace.highlights.delete_post' => 'ws_api_highlights_delete') as $id => $handler) {
		pg_assert_true(isset($routes[$id]), $id . ' declared');
		pg_assert_same('POST', $routes[$id]['method'], $id . ' method');
		pg_assert_same($handler, $routes[$id]['handler'], $id . ' handler');
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' dry run');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
		pg_assert_true(strpos($routes[$id]['description'], 'answers 503') !== false, $id . ' says 503');
	}

	pg_assert_true(strpos($routes['workspace.messages.highlights.set']['description'], 'highlight_text') !== false, 'names the string quote indexes');
	pg_assert_true(strpos($routes['workspace.messages.highlights.set']['description'], 'next full read') !== false, 'says how me rows reach other devices');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_highlights.php');
	pg_assert_true(strpos($source, 'ws_api_own_words_only(') !== false, 'own words gate');

	foreach (array('limit_message_mine', 'limit_message', 'limit_person') as $code) {
		pg_assert_true(isset(api_error_catalogue()[$code]), $code . ' explained');
	}
}
