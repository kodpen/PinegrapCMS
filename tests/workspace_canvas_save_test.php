<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Workspace canvas saved into Files
 * (includes/workspace/canvas_save.php): the name and the change it is, the
 * refusals, its tier as a change of the file kind, and the route row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/agent_tiers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_save.php');
require_once(__DIR__ . '/helpers/source_reader.php');

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

function test_workspace_canvas_save_name()
{
	pg_assert_same('Poster.png', ws_canvas_save_name('Poster', 'Canvas.png', 'png'), 'the given name with the format\'s extension');
	pg_assert_same('Poster.jpg', ws_canvas_save_name('Poster.png', 'Canvas.png', 'jpeg'), 'jpeg is .jpg, the old extension goes');
	pg_assert_same('Canvas.webp', ws_canvas_save_name('', 'Canvas.png', 'webp'), 'the canvas\'s name when none is given');
	pg_assert_same('Canvas.avif', ws_canvas_save_name(null, '', 'avif'), 'Canvas when neither has one');
	pg_assert_same('a b.png', ws_canvas_save_name('a/b', 'x.png', 'png'), 'no folder part');
	pg_assert_same('.env.png', ws_canvas_save_name('.env', 'x.png', 'png'), 'a leading dot is no extension');
	pg_assert_same(120, mb_strlen(ws_canvas_save_name(str_repeat('é', 300), 'x.png', 'webp')), 'at most 120 letters with the extension');
}

function test_workspace_canvas_save_change()
{
	pg_assert_same(array(
		'type'   => 'file',
		'action' => 'create',
		'fields' => array('canvas' => 12, 'folder_id' => 3, 'name' => 'A.png', 'format' => 'png'),
		'reason' => '',
	), ws_canvas_save_change('12', '3', 'A.png', 'png'));
}

function test_workspace_canvas_save_refusals()
{
	$all = array('canvas' => true, 'pending' => false, 'folder' => true, 'binned' => false, 'edit' => true, 'format' => true);

	pg_assert_same('', ws_canvas_save_refusal($all), 'a drawn canvas into a folder the person may fill');

	foreach (array('canvas' => false, 'pending' => true, 'folder' => false, 'binned' => true, 'edit' => false, 'format' => false) as $key => $value) {
		pg_assert_true(ws_canvas_save_refusal(array_merge($all, array($key => $value))) !== '', 'refused when ' . $key . ' is ' . var_export($value, true));
	}

	pg_assert_same(lang('There is no folder with that id.'), ws_canvas_save_refusal(array_merge($all, array('folder' => false, 'edit' => false))), 'a missing folder before the right to it');
}

function test_workspace_canvas_save_is_a_reversible_file_create()
{
	$types = pg_test_ws_change_types();
	$file = $types['file'];

	pg_assert_true(in_array('create', $file['actions'], true), 'the file kind creates');
	pg_assert_same(array('canvas', 'name', 'format'), array_keys($file['create']), 'its create-only fields');
	pg_assert_same(array('jpeg', 'png', 'webp', 'avif'), $file['create']['format'][1], 'the export formats');
	pg_assert_same(WS_TIER_REVERSIBLE, ws_changes_tier(array(ws_canvas_save_change(1, 2, 'A.png', 'png')), $types), 'a save is tier 2');

	$plain = $types;
	unset($plain['file']['create_reversible']);

	pg_assert_same(WS_TIER_IRREVERSIBLE, ws_changes_tier(array(ws_canvas_save_change(1, 2, 'A.png', 'png')), $plain), 'create-only fields are tier 3 without the mark');
	pg_assert_same(WS_TIER_IRREVERSIBLE, ws_change_tier('file', 'create', array('size'), $types), 'a field of no list stays tier 3');
	pg_assert_same(WS_TIER_IRREVERSIBLE, ws_change_tier('file', 'delete', array(), $types), 'a delete stays tier 3');
	pg_assert_same(null, ws_agent_undo_reverse(array('record_type' => 'file', 'action' => 'create', 'record_id' => 5, 'fields' => json_encode(array(array('name' => 'canvas', 'from' => null, 'to' => 1)))), $types), 'a saved file is not undone from the card');
}

function test_workspace_canvas_save_route()
{
	$routes = array_column(ws_canvas_api_routes(), null, 'id');
	$route = $routes['workspace.messages.canvas.save'] ?? null;

	pg_assert_true(is_array($route), 'the route is declared');
	pg_assert_same(array('POST', '/workspace/messages/{id}/canvas/save', 'workspace:write', true, 201), array($route['method'], $route['path'], $route['scope'], $route['dry_run'], $route['status']));
	pg_assert_same(array('id', 'folder_id', 'name', 'format', 'description', 'text'), array_column($route['params'], 'name'));
	pg_assert_same('WorkspaceProposal', $route['returns']['proposal']);
}
