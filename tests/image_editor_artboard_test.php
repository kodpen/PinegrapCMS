<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's canvas and artboard (the ARTBOARD section of
 * assets/js/pg_image_editor_model.js, run under Node): the preset of a size,
 * dragging the board by its edges and corners with the ratio kept or from
 * the centre, a size placed by its anchor, the board around the layers, and
 * moving the board without moving the artwork.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_artboard_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgieboard');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var r3 = function (v) { return Math.round(v * 1000) / 1000; };' . "\n"
		. 'var box = function (b) { return b ? [r3(b.x), r3(b.y), r3(b.w), r3(b.h)] : null; };' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A size names its preset; any other size is custom; sides stay in range.
function test_image_editor_artboard_presets()
{
	$r = image_editor_artboard_node(
		'console.log(JSON.stringify({ story: M.boardPreset(1080, 1920), share: M.boardPreset(1200, 630), other: M.boardPreset(1000, 1000),'
		. ' sides: [M.boardSide(3), M.boardSide(9000), M.boardSide("640.4"), M.boardSide("x")], names: M.BOARD_PRESETS.map(function (p) { return p[0]; }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('story', $r['story'], '1080 x 1920 is the story');
	pg_assert_same('share', $r['share'], '1200 x 630 is the share image');
	pg_assert_same('custom', $r['other'], 'any other size is custom');
	pg_assert_same(array(16, 8000, 640, 16), $r['sides'], 'sides between 16 and 8000 whole pixels');
	pg_assert_same(array('square', 'portrait', 'story', 'share', 'banner'), $r['names'], 'the five presets of the dialog');
}

// An edge moves only its side; a corner its two; Shift keeps the ratio;
// Alt grows both sides around the centre.
function test_image_editor_artboard_drag()
{
	$r = image_editor_artboard_node(
		'console.log(JSON.stringify({ e: box(M.boardDrag(1000, 500, "e", 200, 40)), w: box(M.boardDrag(1000, 500, "w", -100, 0)),'
		. ' nw: box(M.boardDrag(1000, 500, "nw", -100, -50)), keep: box(M.boardDrag(1000, 500, "se", 500, 0, true)),'
		. ' keepEdge: box(M.boardDrag(1000, 500, "s", 0, 250, true)), centre: box(M.boardDrag(1000, 500, "e", 100, 0, false, true)),'
		. ' small: box(M.boardDrag(1000, 500, "e", -2000, 0)), big: box(M.boardDrag(1000, 500, "se", 9000, 0, true)) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 0, 1200, 500), $r['e'], 'the right edge widens the board to the right');
	pg_assert_same(array(-100, 0, 1100, 500), $r['w'], 'the left edge grows it to the left');
	pg_assert_same(array(-100, -50, 1100, 550), $r['nw'], 'a corner moves its two sides');
	pg_assert_same(array(0, 0, 1500, 750), $r['keep'], 'Shift keeps the ratio from a corner');
	pg_assert_same(array(-250, 0, 1500, 750), $r['keepEdge'], 'from an edge the other side grows around its middle');
	pg_assert_same(array(-100, 0, 1200, 500), $r['centre'], 'Alt sizes from the centre');
	pg_assert_same(array(0, 0, 16, 500), $r['small'], 'never under 16 pixels');
	pg_assert_same(8000, $r['big'][2], 'never over 8000 pixels, the ratio kept');
	pg_assert_same(4000, $r['big'][3], 'the other side by the ratio');
}

// A size typed in sits on the anchor; the board fits the visible layers.
function test_image_editor_artboard_anchor_and_fit()
{
	$r = image_editor_artboard_node(
		'var layers = [{ id: "a", type: "rect", x: 100.4, y: 50, w: 200, h: 100 }, { id: "b", type: "text", x: 500, y: 300, w: 99.2, h: 20 }, { id: "c", type: "rect", x: -900, y: -900, w: 10, h: 10, hidden: true }];' . "\n"
		. 'console.log(JSON.stringify({ centre: box(M.boardAnchored(1000, 1000, 1200, 800, 0.5, 0.5)), topLeft: box(M.boardAnchored(1000, 1000, 1200, 800, 0, 0)),'
		. ' bottomRight: box(M.boardAnchored(1000, 1000, 600, 800, 1, 1)), around: box(M.boardAround(layers)), none: M.boardAround([]),'
		. ' turned: box(M.boardAround([{ type: "rect", x: 0, y: 0, w: 100, h: 100, rot: 90 }])) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(-100, 100, 1200, 800), $r['centre'], 'the centre anchor grows and trims both sides alike');
	pg_assert_same(array(0, 0, 1200, 800), $r['topLeft'], 'the top left stays');
	pg_assert_same(array(400, 200, 600, 800), $r['bottomRight'], 'the bottom right stays');
	pg_assert_same(array(100, 50, 500, 270), $r['around'], 'whole pixels around the visible layers');
	pg_assert_same(null, $r['none'], 'no layers, no box');
	pg_assert_same(array(0, 0, 100, 100), $r['turned'], 'a turned layer by its corners');
}

// Moving the board moves every layer the other way by whole pixels; the
// rest of the document stays.
function test_image_editor_artboard_apply()
{
	$r = image_editor_artboard_node(
		'var doc = M.newDocument({ blank: { width: 1000, height: 800, background: "#000000" } });' . "\n"
		. 'doc.layers = [{ id: "a", type: "rect", x: 100, y: 50, w: 200, h: 100, rot: 30 }, { id: "b", type: "image", x: 10, y: 20, w: 40, h: 30, src: "x" },'
		. ' { id: "c", type: "path", x: 0, y: 0, w: 10, h: 10, knots: [{ x: 0, y: 0, ix: 0, iy: 0, ox: 0, oy: 0 }] }];' . "\n"
		. 'var next = M.applyBoard(doc, { x: -99.6, y: 20.2, w: 1200.4, h: 700 });' . "\n"
		. 'console.log(JSON.stringify({ board: next.artboard, a: [next.layers[0].x, next.layers[0].y, next.layers[0].w, next.layers[0].rot], b: [next.layers[1].x, next.layers[1].y],'
		. ' knot: next.layers[2].knots[0].x, old: [doc.artboard.width, doc.layers[0].x], same: M.applyBoard(doc, { x: 0, y: 0, w: 1000, h: 800 }).layers[0].x }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('width' => 1200, 'height' => 700, 'background' => '#000000'), $r['board'], 'the new size, the background kept');
	pg_assert_same(array(200, 30, 200, 30), $r['a'], 'a shape moves the other way, its size and angle kept');
	pg_assert_same(array(110, 0), $r['b'], 'a picture too');
	pg_assert_same(0, $r['knot'], 'a path\'s knots stay parts of its box');
	pg_assert_same(array(1000, 100), $r['old'], 'the old document is not changed');
	pg_assert_same(100, $r['same'], 'a board that does not move leaves the layers');
}
