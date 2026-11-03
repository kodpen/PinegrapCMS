<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace canvas (includes/workspace/canvas.php): which
 * documents are a canvas's, which are kept beside the file, the new file's
 * name, and the model's canvasOpenDoc() run under Node.
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

function workspace_canvas_test_doc()
{
	return '{"v":1,"source":null,"artboard":{"width":1080,"height":1350,"background":"#ffffff"},"geometry":{"rot":0},"adjust":{},"filter":"none","frame":null,"layers":[{"id":"t1","type":"text","text":"Hello"}]}';
}

// An artboard with its size and no picture is a canvas; a photo's document
// is not.
function test_workspace_canvas_is_doc()
{
	pg_assert_true(ws_canvas_is_doc(json_decode(workspace_canvas_test_doc(), true)), 'array decode');
	pg_assert_true(ws_canvas_is_doc(json_decode(workspace_canvas_test_doc())), 'object decode');
	pg_assert_false(ws_canvas_is_doc(null), 'nothing');
	pg_assert_false(ws_canvas_is_doc(array('v' => 1, 'source' => array('fileId' => 3), 'artboard' => array('width' => 9, 'height' => 9))), 'a picture under it');
	pg_assert_false(ws_canvas_is_doc(array('v' => 2, 'artboard' => array('width' => 9, 'height' => 9))), 'another version');
	pg_assert_false(ws_canvas_is_doc(array('v' => 1, 'artboard' => array('width' => 9))), 'no height');
	pg_assert_false(ws_canvas_is_doc(array('v' => 1, 'artboard' => array('width' => 'x', 'height' => 9))), 'no number');
	pg_assert_false(ws_canvas_is_doc(array('v' => 1, 'artboard' => array('width' => 0, 'height' => 9))), 'no size');
}

// The document kept is the one sent, {} still an object; one holding a pasted
// picture or no canvas is not kept.
function test_workspace_canvas_doc_json()
{
	$json = ws_canvas_doc_json(json_decode(workspace_canvas_test_doc()));

	pg_assert_true($json !== '', 'kept');
	pg_assert_same(json_decode(workspace_canvas_test_doc(), true), json_decode($json, true), 'as sent');
	pg_assert_true(strpos($json, '"adjust":{}') !== false, 'an empty object stays one');

	$pasted = json_decode(workspace_canvas_test_doc());
	$pasted->layers[] = (object) array('id' => 'p1', 'type' => 'image', 'src' => 'data:image/png;base64,AAAA');
	pg_assert_same('', ws_canvas_doc_json($pasted), 'a pasted picture is not kept');

	pg_assert_same('', ws_canvas_doc_json(json_decode(workspace_canvas_test_doc(), true)), 'an array decode is not taken');
	pg_assert_same('', ws_canvas_doc_json(json_decode('{"v":1,"source":{"fileId":3},"geometry":{}}')), 'a photo is no canvas');
}

// A PNG named by what the editor sent, else "Canvas".
function test_workspace_canvas_file_name()
{
	pg_assert_same('Poster.png', ws_canvas_file_name('Poster'), 'a name');
	pg_assert_same('Poster.png', ws_canvas_file_name('Poster.jpg'), 'its extension replaced');
	pg_assert_same('a b.png', ws_canvas_file_name('a/b'), 'no folder in it');
	pg_assert_same(lang('Canvas') . '.png', ws_canvas_file_name(''), 'no name');
	pg_assert_same(lang('Canvas') . '.png', ws_canvas_file_name(array('x')), 'no string');
	pg_assert_same(124, mb_strlen(ws_canvas_file_name(str_repeat('é', 300))), 'at most 120 letters and .png');
}

// The labels of the two screens.
function test_workspace_canvas_strings()
{
	foreach (ws_canvas_js_strings() as $key => $text) {
		pg_assert_true(is_string($text) && ($text !== ''), 'label ' . $key);
	}
}

// canvasOpenDoc(): a canvas's document as the editor opens it again.
function test_workspace_canvas_model_open_doc()
{
	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwscanvas');

	if ($file === false) {
		return;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'console.log(JSON.stringify({'
		. ' doc: M.canvasOpenDoc(' . workspace_canvas_test_doc() . '),'
		. ' odd: M.canvasOpenDoc({ v: 1, artboard: { width: 99999, height: "40" }, geometry: {}, adjust: [] }),'
		. ' bad: [M.canvasOpenDoc(null), M.canvasOpenDoc({ v: 1, source: { fileId: 3 }, artboard: { width: 9, height: 9 }, geometry: {} }),'
		. ' M.canvasOpenDoc({ v: 2, artboard: { width: 9, height: 9 }, geometry: {} }), M.canvasOpenDoc({ v: 1, geometry: {} })]'
		. '}));');

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$node = ($code === 0) ? json_decode(implode("\n", $out), true) : null;

	pg_assert_true(is_array($node), ($code === 0) ? 'node ran' : implode("\n", $out));

	if (!is_array($node)) {
		return;
	}

	pg_assert_same(array('width' => 1080, 'height' => 1350, 'background' => '#ffffff'), $node['doc']['artboard'], 'the artboard');
	pg_assert_same(null, $node['doc']['source'], 'no picture');
	pg_assert_same('Hello', $node['doc']['layers'][0]['text'], 'the text layer stays editable');
	pg_assert_same(8000, $node['odd']['artboard']['width'], 'a side kept to the limit');
	pg_assert_same(40, $node['odd']['artboard']['height'], 'a side as a number');
	pg_assert_same('#ffffff', $node['odd']['artboard']['background'], 'white without a background');
	pg_assert_same(array(), $node['odd']['layers'], 'no layers');
	pg_assert_same(array(null, null, null, null), $node['bad'], 'no canvas, no document');
}

// A pasted picture kept as a file beside the canvas: its layer points at the
// file, the document may then be kept, and the editor opens it again with
// the picture as a layer.
function test_workspace_canvas_pictures_pointed()
{
	$doc = json_decode(workspace_canvas_test_doc());
	$doc->layers[] = (object) array('id' => 'p1', 'type' => 'image', 'src' => 'data:image/png;base64,AAAA', 'fileName' => 'clip.png', 'x' => 10, 'y' => 20, 'w' => 300, 'h' => 200);
	$doc->layers[] = (object) array('id' => 'p2', 'type' => 'image', 'src' => 'data:image/jpeg;base64,BBBB');

	pg_assert_same('', ws_canvas_doc_json($doc), 'not kept with the pasted pictures in it');

	$left = ws_canvas_pictures_point($doc, array('p1' => array('id' => 41, 'url' => '/clip.png')));

	pg_assert_same(1, $left, 'one picture left in the document');
	pg_assert_same('', ws_canvas_doc_json($doc), 'still not kept while one is left');

	$left = ws_canvas_pictures_point($doc, array('p2' => array('id' => 42, 'url' => '/Picture.jpg')));

	pg_assert_same(0, $left, 'none left');

	$json = ws_canvas_doc_json($doc);

	pg_assert_true($json !== '', 'kept once every picture is a file');

	$kept = json_decode($json, true);

	pg_assert_same('/clip.png', $kept['layers'][1]['src'], 'the layer points at its file');
	pg_assert_same(41, $kept['layers'][1]['fileId'], 'with the file id');
	pg_assert_same(42, $kept['layers'][2]['fileId'], 'the second one too');
	pg_assert_same(0, ws_canvas_pictures_point(null, array()), 'no document');

	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwscanvas');

	if ($file === false) {
		return;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'console.log(JSON.stringify(M.canvasOpenDoc(' . $json . ')));');

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$node = ($code === 0) ? json_decode(implode("\n", $out), true) : null;

	pg_assert_true(is_array($node), ($code === 0) ? 'node ran' : implode("\n", $out));

	if (is_array($node)) {
		pg_assert_same('image', $node['layers'][1]['type'], 'opened again: the picture is a layer');
		pg_assert_same('/clip.png', $node['layers'][1]['src'], 'opened again: from its file');
		pg_assert_same(41, $node['layers'][1]['fileId'], 'opened again: with its file id');
	}
}

// The picture's file name: the name it came with, else "Picture", in the
// type of its bytes.
function test_workspace_canvas_picture_name()
{
	pg_assert_same('clip.png', ws_canvas_picture_name('clip.png', 'png'), 'its name');
	pg_assert_same('clip.jpg', ws_canvas_picture_name('C:\\x\\clip.webp', 'jpg'), 'no folder, the type of its bytes');
	pg_assert_same(lang('Picture') . '.png', ws_canvas_picture_name('', 'png'), 'no name');
	pg_assert_same(lang('Picture') . '.png', ws_canvas_picture_name(array('x'), 'png'), 'no string');
}

// The save keeps the pictures as files before the document, and takes them
// from the request before it is let go.
function test_workspace_canvas_save_order()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_file_save.php');

	pg_assert_true(substr_count($source, "ws_canvas_keep(") === 3, 'three places keep a canvas');
	pg_assert_true(substr_count($source, "\$request['pictures'] ?? null") >= 1, 'the pictures go along');
	pg_assert_true(strpos($source, '$pictures = $request[\'pictures\'] ?? null;') < strpos($source, 'unset($request);'), 'taken before the request is let go');

	$canvas = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
	$keep = substr($canvas, (int) strpos($canvas, 'function ws_canvas_keep('));

	pg_assert_true(strpos($keep, 'ws_canvas_pictures_keep(') < strpos($keep, 'pg_image_editor_canvas_keep('), 'pictures before the document');
}
