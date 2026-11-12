<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a canvas's picture as the external API shows it in its message
 * (includes/workspace/api_resources.php, canvas.php, api_canvas.php):
 * file.canvas with the newest version and whether the picture is still to
 * be drawn, read once for a page, and an agent's edit counting as a change
 * of the message.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

// The newest version's row says the version and whether a browser has still
// to draw it: only an agent's version marked pending waits.
function test_workspace_api_message_canvas_version_state()
{
	pg_assert_same(array('version_no' => 3, 'pending' => true, 'server' => false),
		ws_canvas_version_state(array('version_no' => '3', 'source' => 'agent', 'has_doc' => '1', 'marked' => '1', 'served' => '0')));
	pg_assert_same(array('version_no' => 3, 'pending' => false, 'server' => false),
		ws_canvas_version_state(array('version_no' => '3', 'source' => 'saved', 'has_doc' => '1', 'marked' => '1', 'served' => '0')), 'a person\'s save drew it');
	pg_assert_same(array('version_no' => 4, 'pending' => false, 'server' => true),
		ws_canvas_version_state(array('version_no' => '4', 'source' => 'agent', 'has_doc' => '1', 'marked' => '0', 'served' => '1')), 'the server drew it');
	pg_assert_same(array('version_no' => 4, 'pending' => false, 'server' => false),
		ws_canvas_version_state(array('version_no' => '4', 'source' => 'new', 'has_doc' => '1', 'marked' => '0', 'served' => '1')), 'a person\'s save carrying the server\'s mark');
	pg_assert_same(array('version_no' => 2, 'pending' => false, 'server' => false),
		ws_canvas_version_state(array('version_no' => 2, 'source' => 'agent', 'has_doc' => 1, 'marked' => 0, 'served' => 0)), 'drawn by a browser');
	pg_assert_same(null, ws_canvas_version_state(array('version_no' => 2, 'source' => 'save', 'has_doc' => '0', 'marked' => '0', 'served' => '0')), 'a picture\'s version keeps no document');
	pg_assert_same(null, ws_canvas_version_state(false), 'no version');
}

// Only a PNG of a message not deleted may be a canvas's picture.
function test_workspace_api_message_canvas_file_ids()
{
	pg_assert_same(array(4, 6), ws_api_message_canvas_file_ids(array(
		array('file_id' => '4', 'file_name' => 'Canvas.png', 'deleted_at' => '0'),
		array('file_id' => '5', 'file_name' => 'photo.jpg', 'deleted_at' => '0'),
		array('file_id' => '6', 'file_name' => 'Drawing.PNG', 'deleted_at' => 0),
		array('file_id' => '7', 'file_name' => 'Canvas.png', 'deleted_at' => '1762900000'),
		array('file_id' => '0', 'file_name' => '', 'deleted_at' => '0'),
		false,
	)));
	pg_assert_same(array(), ws_api_message_canvas_file_ids(array()));
}

// file.canvas: version and picture pending, server or drawn - server apart
// from drawn, as a browser draws it again; null on any other file.
function test_workspace_api_message_canvas_object()
{
	$canvases = array(
		4 => array('version_no' => 2, 'pending' => true, 'server' => false),
		6 => array('version_no' => 5, 'pending' => false, 'server' => false),
		9 => array('version_no' => 3, 'pending' => false, 'server' => true),
	);

	pg_assert_same(array('version' => 2, 'picture' => 'pending'),
		ws_api_message_canvas(array('file_id' => '4', 'file_name' => 'Canvas.png', 'deleted_at' => '0'), $canvases));
	pg_assert_same(array('version' => 5, 'picture' => 'drawn'),
		ws_api_message_canvas(array('file_id' => 6, 'file_name' => 'x.png', 'deleted_at' => 0), $canvases));
	pg_assert_same(array('version' => 3, 'picture' => 'server'),
		ws_api_message_canvas(array('file_id' => '9', 'file_name' => 'Canvas.png', 'deleted_at' => '0'), $canvases), 'a browser draws it again');
	pg_assert_same(null, ws_api_message_canvas(array('file_id' => '8', 'file_name' => 'Canvas.png', 'deleted_at' => '0'), $canvases), 'a PNG with no kept document');
	pg_assert_same(null, ws_api_message_canvas(array('file_id' => '4', 'file_name' => 'Canvas.jpg', 'deleted_at' => '0'), $canvases), 'not a PNG');
	pg_assert_same(null, ws_api_message_canvas(array('file_id' => '4', 'file_name' => 'Canvas.png', 'deleted_at' => '5'), $canvases), 'deleted');
	pg_assert_same(null, ws_api_message_canvas(array('file_id' => '4', 'file_name' => 'Canvas.png', 'deleted_at' => '0'), array()), 'nothing read');
}

// The file declares it, and the presenter fills it.
function test_workspace_api_message_canvas_is_on_the_file()
{
	$schema = ws_api_message_file_schema();

	pg_assert_same('WorkspaceMessageCanvas?', $schema['canvas'] ?? null);
	pg_assert_same(array('version' => 'integer', 'picture' => 'string'), ws_api_message_canvas_schema());

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

	pg_assert_contains("'WorkspaceMessageCanvas'    => 'ws_api_message_canvas_schema',", $source);
}

// A page of messages reads its canvases in one query; a message alone reads
// its own.
function test_workspace_api_message_canvas_is_read_once_for_a_page()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');

	pg_assert_contains('$canvases = function_exists(\'ws_canvas_message_states\') ? ws_canvas_message_states(ws_api_message_canvas_file_ids($rows)) : array();', $source);
	pg_assert_contains('$checks, $forwards, $group_cards, $canvases);', $source);
	pg_assert_contains('ws_canvas_message_states(ws_api_message_canvas_file_ids(array($row)))', $source);

	$canvas = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');

	pg_assert_true(preg_match('/function ws_canvas_message_states\(.*?\n}\n/s', $canvas, $match) === 1, 'the batch is found');
	pg_assert_same(1, substr_count($match[0], 'db_items('), 'one query for each slice');
	pg_assert_contains('array_chunk($ids, 500)', $match[0]);
	pg_assert_contains('ws_canvas_version_state($row)', $match[0], 'the same rule as one message');
	pg_assert_contains('AS marked', $match[0]);
	pg_assert_contains('AS served', $match[0], 'the server\'s mark is read too');
}

// An agent's edit is a change of the message, as Pinegrap AI's edit and
// the picture drawn by a browser are.
function test_workspace_api_message_canvas_edit_touches_the_message()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

	pg_assert_true(preg_match('/function ws_api_canvas_edit\(.*?\n}\n/s', $source, $match) === 1, 'the edit is found');
	pg_assert_true(strpos($match[0], 'pg_image_editor_canvas_keep(') < strpos($match[0], "ws_message_touch((int) \$message['id']);"), 'after the version is kept');
	pg_assert_true(strpos($match[0], 'api_dry_run_stop(') < strpos($match[0], 'ws_message_touch('), 'not on a dry run');
}

// The routes say what the field means and when the message comes again.
function test_workspace_api_message_canvas_is_documented()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_contains('file.canvas is set on a canvas\'s picture', $routes['workspace.messages.list']['description']);
	pg_assert_contains('a canvas edited or drawn', $routes['workspace.messages.list']['description']);
	pg_assert_contains('whose canvas was edited or drawn', $routes['workspace.sync']['description']);
	pg_assert_contains('file.canvas at the new version', $routes['workspace.messages.canvas.edit']['description']);
	pg_assert_contains('server once the server drew it', $routes['workspace.messages.list']['description']);
}
