<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's routes of a message's files
 * (includes/workspace/api_message_files.php) that need no database: a file
 * of the file manager posted in a channel, an edited file put in place of
 * the old one and the earlier versions of a message's file. Each route is
 * declared with its scope, the writes rehearse and stop before they write,
 * the replacement is no MCP tool, and an edited file keeps its kind.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/office.php');

// The routes of this file, by id.
function ws_message_file_api_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

// The source of a handler, to read the order of its calls.
function ws_message_file_api_test_source($handler)
{
	$function = new ReflectionFunction($handler);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

function test_ws_message_file_api_routes_are_declared()
{
	$routes = ws_message_file_api_test_routes();
	$expected = array(
		'workspace.channels.library_files.create' => array('POST', '/workspace/channels/{id}/library-files', 'workspace:write'),
		'workspace.messages.file.replace'         => array('POST', '/workspace/messages/{id}/file', 'workspace:write'),
		'workspace.messages.file.history'         => array('GET', '/workspace/messages/{id}/file/history', 'workspace:read'),
	);

	foreach ($expected as $id => $shape) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same($shape[0], $routes[$id]['method'], $id . ' method');
		pg_assert_same($shape[1], $routes[$id]['path'], $id . ' path');
		pg_assert_same($shape[2], $routes[$id]['scope'], $id . ' scope');
	}

	pg_assert_same(201, $routes['workspace.channels.library_files.create']['status']);
	pg_assert_same('file', $routes['workspace.messages.file.replace']['form_file'], 'the edited file may come as a form part');
}

// Both writes rehearse, and stop after their checks, before the panel's
// function writes.
function test_ws_message_file_api_writes_rehearse_after_their_checks()
{
	$routes = ws_message_file_api_test_routes();

	$writes = array(
		'workspace.channels.library_files.create' => array('ws_library_send_check(', 'ws_library_send($viewer'),
		'workspace.messages.file.replace'         => array('ws_upload_check(', 'ws_message_file_replace($viewer'),
	);

	foreach ($writes as $id => $calls) {
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes dry_run');

		$source = ws_message_file_api_test_source($routes[$id]['handler']);
		$check = strpos($source, $calls[0]);
		$stop = strpos($source, 'api_dry_run_stop(');
		$write = strpos($source, $calls[1]);

		pg_assert_true(($check !== false) && ($stop !== false) && ($write !== false), $id . ': check, stop and write are all there');
		pg_assert_true(($check < $stop) && ($stop < $write), $id . ': the stop sits between the check and the write');
	}
}

// Posting a file of the file manager and reading the history are tools; the
// replacement changes the person's own message and is no tool.
function test_ws_message_file_api_tools()
{
	$routes = ws_message_file_api_test_routes();

	pg_assert_true(pg_mcp_route_is_offered($routes['workspace.channels.library_files.create']), 'posting a file of the file manager is a tool');
	pg_assert_true(pg_mcp_route_is_offered($routes['workspace.messages.file.history']), 'the history is a tool');
	pg_assert_false(pg_mcp_route_is_offered($routes['workspace.messages.file.replace']), 'the replacement is no tool');

	$source = ws_message_file_api_test_source($routes['workspace.messages.file.replace']['handler']);

	pg_assert_true(strpos($source, 'ws_api_own_words_only(') !== false, 'the replacement comes from the person\'s own session only');
	pg_assert_true(strpos($source, 'ws_file_can_replace(') !== false, 'only in the person\'s own message');
}

// The kind of edit a file takes, from its name.
function test_ws_file_edit_kind()
{
	pg_assert_same('image', ws_file_edit_kind('photo.PNG'));
	pg_assert_same('office', ws_file_edit_kind('report.docx'));
	pg_assert_same('office', ws_file_edit_kind('sheet.xlsx'));
	pg_assert_same('text', ws_file_edit_kind('notes.md'));
	pg_assert_same('', ws_file_edit_kind('movie.mp4'));
	pg_assert_same('', ws_file_edit_kind('archive.zip'));
}

// The edited file keeps the old name with its own extension, and its kind.
function test_ws_message_file_replace_name()
{
	pg_assert_same('photo.png', ws_message_file_replace_name('photo.png', ''), 'no name: the old one stays');
	pg_assert_same('photo.jpg', ws_message_file_replace_name('photo.png', 'edited.JPG'), 'a picture saved as JPEG');
	pg_assert_same('notes.txt', ws_message_file_replace_name('notes.md', 'x.txt'), 'text stays text');
	pg_assert_same('report.docm', ws_message_file_replace_name('report.docx', 'a.docm'), 'a document stays a document');
	pg_assert_same('', ws_message_file_replace_name('report.docx', 'a.xlsx'), 'a document does not become a workbook');
	pg_assert_same('', ws_message_file_replace_name('photo.png', 'a.pdf'), 'a picture does not become a PDF');
	pg_assert_same('', ws_message_file_replace_name('notes.md', 'photo.png'), 'text does not become a picture');
	pg_assert_same('', ws_message_file_replace_name('movie.mp4', 'movie.mp4'), 'no editor opens a video');
	pg_assert_same('', ws_message_file_replace_name('photo.png', 'noextension'), 'a name without an extension says nothing');
}

// One earlier version as the API shows it.
function test_ws_api_message_file_version()
{
	$version = ws_api_message_file_version(array('name' => 'photo.png', 'by' => 'Ada', 'by_id' => 7, 'created_at' => 0, 'stored' => ''));

	pg_assert_same('photo.png', $version['name']);
	pg_assert_same('', $version['url'], 'a file gone has no address');
	pg_assert_same(array('user_id' => 7, 'name' => 'Ada'), $version['edited_by']);
	pg_assert_same(null, $version['created_at']);
	pg_assert_same(array_keys(ws_api_message_file_version_schema()), array_keys($version), 'the entry matches its schema');
}
