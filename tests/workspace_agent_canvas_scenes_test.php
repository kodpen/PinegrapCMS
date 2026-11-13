<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an agent's board and scene operations on a canvas
 * (includes/workspace/canvas_board.php through ws_canvas_edit_apply()):
 * sizes by preset or in pixels around the board's middle, the open scene's
 * background, scenes added, chosen, removed, given a transition and a
 * duration, the cap, the read, the rows of the routes and tools; and the
 * same steps run through the image editor's model (pg_image_editor_model.js)
 * in Node giving the same document. The Node part is skipped where Node
 * cannot run.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_ai.php');

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

// A 600 x 300 canvas with a layer of each way the editor moves one: an svg
// box, a text, a line with its box, a turned rectangle, a pen path and a
// free drawing.
function workspace_canvas_scenes_doc()
{
	return array(
		'v'        => 1,
		'artboard' => array('width' => 600, 'height' => 300, 'background' => '#ffffff'),
		'layers'   => array(
			array('id' => 'l0', 'type' => 'svg', 'kind' => 'g', 'x' => 0, 'y' => 0, 'w' => 600, 'h' => 300, 'markup' => '<rect width="600" height="300" fill="#eeeeee"/>', 'vb' => array(0, 0, 600, 300)),
			array('id' => 'l1', 'type' => 'text', 'x' => 40, 'y' => 30, 'w' => 101, 'h' => 33, 'text' => 'Q4', 'name' => 'title'),
			array('id' => 'l2', 'type' => 'line', 'x1' => 10, 'y1' => 280, 'x2' => 590, 'y2' => 280, 'x' => 10, 'y' => 279, 'w' => 580, 'h' => 2),
			array('id' => 'l3', 'type' => 'rect', 'x' => 200, 'y' => 100, 'w' => 80, 'h' => 40, 'rot' => 190),
			array('id' => 'l4', 'type' => 'path', 'x' => 300, 'y' => 50, 'w' => 20, 'h' => 20, 'nodes' => array(array('x' => 300, 'y' => 50, 'ix' => 298, 'iy' => 50, 'ox' => 302, 'oy' => 50), array('x' => 320, 'y' => 70, 'ix' => 320, 'iy' => 68, 'ox' => 320, 'oy' => 72))),
			array('id' => 'l5', 'type' => 'pen', 'x' => 100, 'y' => 200, 'w' => 30, 'h' => 10, 'pts' => array(array(100, 200), array(130, 210))),
		),
	);
}

function workspace_canvas_scenes_apply($doc, $operations)
{
	return ws_canvas_edit_apply($doc, $operations);
}

// The document as the editor's and the server's twins are compared: keys
// sorted, a whole float as an integer, the picture mark left out.
function workspace_canvas_scenes_plain($value)
{
	if (is_float($value) && ($value == (int) $value)) {
		return (int) $value;
	}

	if (!is_array($value)) {
		return $value;
	}

	unset($value['picture']);

	if (array_values($value) !== $value) {
		ksort($value);
	}

	return array_map('workspace_canvas_scenes_plain', $value);
}

function test_workspace_canvas_scenes_sizes()
{
	$doc = workspace_canvas_scenes_doc();

	$square = workspace_canvas_scenes_apply($doc, array(array('op' => 'size', 'preset' => 'square')));
	pg_assert_same('', $square['error'], 'a preset');
	pg_assert_same(array(1080, 1080), array($square['doc']['artboard']['width'], $square['doc']['artboard']['height']), 'square is 1080 x 1080');
	pg_assert_same(array(240, 390), array($square['doc']['layers'][0]['x'], $square['doc']['layers'][0]['y']), 'the artwork stays in the middle');
	pg_assert_same(array(250, 670, 830), array($square['doc']['layers'][2]['x1'], $square['doc']['layers'][2]['y1'], $square['doc']['layers'][2]['x2']), 'a line\'s ends move with it');
	pg_assert_same(-170, $square['doc']['layers'][3]['rot'], 'a turned box keeps its angle, written in -180 to 180');
	pg_assert_same('pending', $square['doc']['picture'], 'the picture waits for a browser');

	foreach (array('portrait' => array(1080, 1350), 'story' => array(1080, 1920), 'share' => array(1200, 630), 'banner' => array(1920, 600)) as $preset => $size) {
		$made = workspace_canvas_scenes_apply($doc, array(array('op' => 'size', 'preset' => $preset)));
		pg_assert_same($size, array($made['doc']['artboard']['width'], $made['doc']['artboard']['height']), $preset);
	}

	$pixels = workspace_canvas_scenes_apply($doc, array(array('op' => 'size', 'width' => 601, 'height' => 299)));
	pg_assert_same(array(601, 299), array($pixels['doc']['artboard']['width'], $pixels['doc']['artboard']['height']), 'width and height');
	// The board moves by -0.5 and 0.5 pixels: rounded as the editor
	// rounds, a half goes up, so the layers stay across and go one up.
	pg_assert_same(array(0, -1), array($pixels['doc']['layers'][0]['x'], $pixels['doc']['layers'][0]['y']), 'half a pixel rounds up, as the editor rounds');
}

function test_workspace_canvas_scenes_refusals()
{
	$doc = workspace_canvas_scenes_doc();
	$two = workspace_canvas_scenes_apply($doc, array(array('op' => 'scene', 'action' => 'add')))['doc'];
	$cases = array(
		array($doc, array('op' => 'size'), 'size'),
		array($doc, array('op' => 'size', 'preset' => 'poster'), 'size'),
		array($doc, array('op' => 'size', 'preset' => 'square', 'width' => 100), 'size'),
		array($doc, array('op' => 'size', 'width' => 15, 'height' => 100), 'size'),
		array($doc, array('op' => 'size', 'width' => 8001, 'height' => 100), 'size'),
		array($doc, array('op' => 'size', 'width' => 100.5, 'height' => 100), 'size'),
		array($doc, array('op' => 'size', 'width' => 100), 'size'),
		array($doc, array('op' => 'background', 'background' => 'red'), 'background'),
		array($doc, array('op' => 'background'), 'background'),
		array($doc, array('op' => 'scene'), 'scene'),
		array($doc, array('op' => 'scene', 'action' => 'move'), 'scene'),
		array($doc, array('op' => 'scene', 'action' => 'choose', 'scene' => 's1'), 'scene'),
		array($doc, array('op' => 'scene', 'action' => 'remove'), 'scene'),
		array($doc, array('op' => 'scene', 'action' => 'add', 'copy' => 'yes'), 'scene'),
		array($doc, array('op' => 'scene', 'action' => 'add', 'duration' => 999), 'scene'),
		array($doc, array('op' => 'scene', 'action' => 'add', 'name' => str_repeat('a', 81)), 'scene'),
		array($two, array('op' => 'scene', 'action' => 'add', 'name' => 's1'), 'scene'),
		array($two, array('op' => 'scene', 'action' => 'choose', 'scene' => 's9'), 'scene'),
		array($two, array('op' => 'scene', 'action' => 'transition', 'kind' => 'spin'), 'scene'),
		array($two, array('op' => 'scene', 'action' => 'transition', 'kind' => 'fade', 'ms' => 50), 'scene'),
		array($two, array('op' => 'scene', 'action' => 'duration'), 'scene'),
		array($two, array('op' => 'scene', 'action' => 'duration', 'duration' => 60001), 'scene'),
	);

	foreach ($cases as $case) {
		pg_assert_same(array($case[2], 0), array_values(array_intersect_key(workspace_canvas_scenes_apply($case[0], array($case[1])), array('error' => 1, 'index' => 1))), json_encode($case[1]));
	}

	$adds = array_fill(0, 20, array('op' => 'scene', 'action' => 'add'));
	$twenty = workspace_canvas_scenes_apply(workspace_canvas_scenes_apply($doc, $adds)['doc'], array_fill(0, 9, array('op' => 'scene', 'action' => 'add')))['doc'];
	pg_assert_same(30, count($twenty['scenes']), 'thirty scenes');
	pg_assert_same('scenes', workspace_canvas_scenes_apply($twenty, array(array('op' => 'scene', 'action' => 'add')))['error'], 'not a thirty-first');
}

function test_workspace_canvas_scenes_steps()
{
	$doc = workspace_canvas_scenes_doc();
	$made = workspace_canvas_scenes_apply($doc, array(
		array('op' => 'background', 'background' => '#112233'),
		array('op' => 'scene', 'action' => 'add', 'name' => 'Numbers', 'duration' => 4000),
		array('op' => 'add', 'type' => 'text', 'text' => 'Second', 'name' => 'second'),
	));
	pg_assert_same('', $made['error'], 'the steps apply');
	$d = $made['doc'];
	pg_assert_same(array('s1', 's2'), array_column($d['scenes'], 'id'), 'the canvas\'s own content is the first scene, the new one after it');
	pg_assert_same(array('s2', 'Numbers', 4000, '#112233'), array($d['scene'], $d['scenes'][1]['name'], $d['motion']['duration'], $d['artboard']['background']), 'the new scene is open, with its name and duration, on the same background');
	pg_assert_same(array('second'), array_column($d['layers'], 'name'), 'a layer operation works on the open scene');
	pg_assert_same(6, count($d['scenes'][0]['layers']), 'the first scene keeps its layers');
	pg_assert_same(5000, $d['scenes'][0]['duration'], 'and the canvas\'s duration');

	$more = workspace_canvas_scenes_apply($d, array(
		array('op' => 'scene', 'action' => 'choose', 'scene' => 's1'),
		array('op' => 'scene', 'action' => 'transition', 'kind' => 'fade', 'ms' => 840),
		array('op' => 'scene', 'action' => 'add', 'copy' => true),
		array('op' => 'scene', 'action' => 'duration', 'scene' => 'Numbers', 'duration' => 2500),
	));
	pg_assert_same('', $more['error'], 'more steps apply');
	$d = $more['doc'];
	pg_assert_same(array('s1', 's3', 's2'), array_column($d['scenes'], 'id'), 'a copy goes after the open scene');
	pg_assert_same(array('fade', 800), array_values($d['scenes'][0]['transition']), 'a transition\'s time in steps of 100 ms');
	pg_assert_same(array('fade', 800), array_values($d['scenes'][1]['transition']), 'a copy keeps the transition');
	pg_assert_same(array(6, 2500), array(count($d['layers']), $d['scenes'][2]['duration']), 'the copy has the layers; a closed scene named by its name takes its duration');

	$read = ws_canvas_board_scenes_read($d);
	pg_assert_same(array('s1', 's3', 's2'), array_column($read, 'id'), 'the read in playing order');
	pg_assert_same(array(null, true, 6, '#112233', 5000), array($read[1]['name'], $read[1]['open'], $read[1]['layers'], $read[1]['background'], $read[1]['duration']), 'the open scene is read from the document');
	pg_assert_same(array('Numbers', false, 1, 2500), array($read[2]['name'], $read[2]['open'], $read[2]['layers'], $read[2]['duration']), 'a closed scene from its entry');
	pg_assert_same(array(), ws_canvas_board_scenes_read($doc), 'one scene reads as none');

	$back = workspace_canvas_scenes_apply($d, array(
		array('op' => 'scene', 'action' => 'remove', 'scene' => 's3'),
		array('op' => 'scene', 'action' => 'remove', 'scene' => 'Numbers'),
	));
	pg_assert_same('', $back['error'], 'scenes removed');
	pg_assert_false(isset($back['doc']['scenes']) || isset($back['doc']['scene']), 'down to one scene the canvas has none');
	pg_assert_same(6, count($back['doc']['layers']), 'the open scene\'s neighbour opened');
	pg_assert_same('scene', workspace_canvas_scenes_apply($back['doc'], array(array('op' => 'scene', 'action' => 'remove', 'scene' => 's1')))['error'], 'never the only scene');

	$sized = workspace_canvas_scenes_apply($d, array(array('op' => 'size', 'preset' => 'story')))['doc'];
	pg_assert_same(array(240, 240, 240), array($sized['layers'][0]['x'], $sized['scenes'][0]['layers'][0]['x'], (int) ($sized['scenes'][2]['layers'][0]['x'] - $d['scenes'][2]['layers'][0]['x'])), 'every scene\'s layers move with the board');

	$json = ws_canvas_doc_json(json_decode(json_encode($sized)));
	pg_assert_true($json !== '', 'the document is one the editor may keep');
}

// The same steps through the editor's model: M.sceneAdd(), M.sceneOpen(),
// M.sceneSet(), M.sceneDelete() and M.applyBoard() on each scene give the
// server's document.
function test_workspace_canvas_scenes_match_the_editors_model()
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

	$doc = workspace_canvas_scenes_doc();
	$made = workspace_canvas_scenes_apply($doc, array(
		array('op' => 'background', 'background' => '#112233'),
		array('op' => 'scene', 'action' => 'add'),
		array('op' => 'scene', 'action' => 'add', 'copy' => true),
		array('op' => 'scene', 'action' => 'transition', 'kind' => 'wipe', 'ms' => 1250),
		array('op' => 'scene', 'action' => 'duration', 'scene' => 's1', 'duration' => 4000),
		array('op' => 'scene', 'action' => 'choose', 'scene' => 's1'),
		array('op' => 'size', 'preset' => 'story'),
		array('op' => 'size', 'width' => 1081, 'height' => 1919),
		array('op' => 'scene', 'action' => 'remove', 'scene' => 's2'),
	));
	pg_assert_same('', $made['error'], 'the server\'s steps apply');

	$script = 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var doc = ' . json_encode($doc) . ';' . "\n"
		. 'function size(w, h) {' . "\n"
		. '    var r = M.boardAnchored(doc.artboard.width, doc.artboard.height, w, h, 0.5, 0.5);' . "\n"
		. '    (doc.scenes || []).forEach(function (s) { if (s.layers) { s.layers = M.applyBoard({ artboard: { width: doc.artboard.width, height: doc.artboard.height }, layers: s.layers }, r).layers; } });' . "\n"
		. '    var next = M.applyBoard(doc, r); doc.artboard = next.artboard; doc.layers = next.layers;' . "\n"
		. '}' . "\n"
		. 'doc.artboard.background = "#112233";' . "\n"
		. 'M.sceneAdd(doc, false);' . "\n"
		. 'M.sceneAdd(doc, true);' . "\n"
		. 'M.sceneSet(doc, doc.scene, { transition: { kind: "wipe", ms: 1250 } });' . "\n"
		. 'M.sceneSet(doc, "s1", { duration: 4000 });' . "\n"
		. 'M.sceneOpen(doc, "s1");' . "\n"
		. 'size(1080, 1920);' . "\n"
		. 'size(1081, 1919);' . "\n"
		. 'M.sceneDelete(doc, "s2");' . "\n"
		. 'console.log(JSON.stringify(doc));';

	$file = @tempnam(sys_get_temp_dir(), 'pgscenes');

	if ($file === false) {
		return;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the model ran: ' . implode("\n", array_slice($out, 0, 8)));

	$model = json_decode(implode("\n", $out), true);
	pg_assert_same(workspace_canvas_scenes_plain($model), workspace_canvas_scenes_plain($made['doc']), 'the editor\'s model makes the same document');
}

function test_workspace_canvas_scenes_rows()
{
	pg_assert_same(array('add', 'replace', 'remove', 'text', 'box', 'svg', 'style', 'order', 'name', 'lock', 'size', 'background', 'scene'), ws_canvas_edit_ops(), 'the operations');

	$routes = array_column(ws_canvas_api_routes(), null, 'id');
	$of = array_column($routes['workspace.messages.canvas.edit']['params'], null, 'name')['operations']['of'];

	foreach (array('preset', 'width', 'height', 'background', 'action', 'scene', 'copy', 'name', 'kind', 'ms', 'duration') as $field) {
		pg_assert_true(isset($of[$field]), 'an operation takes ' . $field);
	}

	pg_assert_true(isset(ws_api_canvas_returns()['scenes']), 'the read names its scenes');
	pg_assert_true(strpos($routes['workspace.messages.canvas.edit']['description'], 'scene (action add') !== false, 'the edit route describes scenes');

	$tools = ws_canvas_ai_tools(function ($name, $description) {
		return array('name' => $name, 'description' => $description);
	});

	foreach (array('size (preset', 'background (', 'scene (action') as $word) {
		pg_assert_true(strpos($tools[2]['description'], $word) !== false, 'edit_canvas names ' . $word);
	}

	pg_assert_true(strpos($tools[1]['description'], 'scenes') !== false, 'read_canvas names the scenes');

	foreach (array('size', 'background', 'scene', 'scenes') as $error) {
		pg_assert_true(strpos(ws_canvas_edit_error_text($error), ' ') !== false, 'a sentence for ' . $error);
	}
}
