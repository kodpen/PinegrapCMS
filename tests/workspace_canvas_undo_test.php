<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Edited by @handle" mark of a person's canvas an agent
 * edited (includes/workspace/canvas.php) that need no database: the handle
 * read from the tail of the version's document, and when the mark and its
 * Undo are shown.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');

// The tail the state query cuts from an agent's document, as the edit
// route writes it: "by" is the document's last member.
function test_workspace_canvas_by_handle()
{
	$doc = (object) array('v' => 1, 'artboard' => (object) array('width' => 10, 'height' => 10), 'layers' => array(
		(object) array('id' => 'l0', 'svg' => '<g id="by"><text>"by":{"handle":"fake"}</text></g>'),
	));
	$doc->by = (object) array('id' => 4, 'kind' => 'app', 'handle' => 'koordinator-a');
	$json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	$tail = substr($json, strpos($json, '"by":{'), 300);

	pg_assert_same('koordinator-a', ws_canvas_by_handle($tail), 'a quoted "by" inside a layer\'s markup is escaped, so the agent\'s member is found');
	pg_assert_same('', ws_canvas_by_handle(''), 'no "by" member (LOCATE found nothing)');
	pg_assert_same('', ws_canvas_by_handle('"by":{"id":4,"kind":"app"}'), 'no handle');
	pg_assert_same('', ws_canvas_by_handle('"by":{"handle":"<b>x</b>"}'), 'only a handle\'s shape');
	pg_assert_same('', ws_canvas_by_handle('x"by":{"handle":"a"}'), 'the tail starts at the member');
}

function test_workspace_canvas_edit_mark()
{
	$state = array('version_no' => 5, 'pending' => true, 'edited_by' => 'chatgpt');

	pg_assert_same(array('handle' => 'chatgpt', 'version' => 5, 'can_undo' => true), ws_canvas_edit_mark($state, 'user', true));
	pg_assert_same(false, ws_canvas_edit_mark($state, 'user', false)['can_undo'], 'shown to every reader, Undo only to whoever may edit');
	pg_assert_same(null, ws_canvas_edit_mark($state, 'app', true), 'an agent\'s own canvas carries no mark');
	pg_assert_same(null, ws_canvas_edit_mark(array('version_no' => 1, 'pending' => true, 'edited_by' => ''), 'user', true), 'the version the agent made the canvas with, or a person\'s version');
	pg_assert_same(null, ws_canvas_edit_mark(null, 'user', true), 'no canvas');
}

function test_workspace_canvas_undo_texts()
{
	$strings = ws_canvas_js_strings();

	foreach (array('canvas_edited_by', 'canvas_undo', 'canvas_undo_ask', 'canvas_undone') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key);
	}

	pg_assert_true(strpos($strings['canvas_edited_by'], '{1}') !== false, 'the handle\'s hole');
}
