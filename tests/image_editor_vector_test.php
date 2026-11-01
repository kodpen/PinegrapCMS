<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's vector pen (the VECTOR section of
 * assets/js/pg_image_editor_model.js, run under Node): a path's box and
 * knots, its SVG path, smooth and corner knots, moving knots and handles,
 * adding a knot without changing the curve, refitting the box, hits and
 * fills of a closed path, converting shapes to paths, turns and flips.
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
function image_editor_vector_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgievector');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var r3 = function (v) { return Math.round(v * 1000) / 1000; };' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A path's box fits its curve; its knots are parts of the box; the SVG path
// closes when the path does; a refit after a moved knot keeps the rest in
// place.
function test_image_editor_vector_path_box_and_d()
{
	$r = image_editor_vector_node(
		'var ks = [{ x: 10, y: 10, ix: 10, iy: 10, ox: 10, oy: 10 }, { x: 110, y: 10, ix: 110, iy: 10, ox: 110, oy: 10 }, { x: 110, y: 60, ix: 110, iy: 60, ox: 110, oy: 60 }];' . "\n"
		. 'var open = M.pathLayer(ks, false), shut = Object.assign({ type: "path", stroke: 2 }, M.pathLayer(ks, true));' . "\n"
		. 'var px = M.pathKnots(shut); px[2] = M.moveKnot(px[2], 100, 80); var fit = M.refitPath(shut, px);' . "\n"
		. 'console.log(JSON.stringify({ box: [open.x, open.y, open.w, open.h, open.rot], k1: [open.knots[1].x, open.knots[1].y], d: M.pathShape(Object.assign({ type: "path" }, open)).d,'
		. ' closedD: M.pathShape(shut).d.slice(-1), closed: M.pathShape(shut).closed, fit: [r3(fit.x), r3(fit.y), r3(fit.w), r3(fit.h)], back: M.pathKnots(Object.assign({}, shut, fit))[0].x + fit.x,'
		. ' flat: M.pathLayer([{ x: 0, y: 5, ix: 0, iy: 5, ox: 0, oy: 5 }, { x: 50, y: 5, ix: 50, iy: 5, ox: 50, oy: 5 }], false).h }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(10, 10, 100, 50, 0), $r['box'], 'the box around the curve');
	pg_assert_same(array(1, 0), $r['k1'], 'knots in parts of the box');
	pg_assert_same('M0 0C0 0 100 0 100 0C100 0 100 50 100 50', $r['d'], 'the path in the box\'s frame');
	pg_assert_same('Z', $r['closedD'], 'a closed path closes');
	pg_assert_true($r['closed'], 'and can be filled');
	pg_assert_same(array(10, 10, 100, 80), $r['fit'], 'the box grows to the moved knot');
	pg_assert_same(10, $r['back'], 'the first knot stays where it was');
	pg_assert_same(1, $r['flat'], 'a straight path is at least a pixel high');
}

// Smooth and corner knots; a moved knot carries its handles; a smooth
// knot's handles stay in line; a knot added keeps the curve.
function test_image_editor_vector_knots()
{
	$r = image_editor_vector_node(
		'var k = function (x, y) { return { x: x, y: y, ix: x, iy: y, ox: x, oy: y }; };' . "\n"
		. 'var ks = M.smoothKnot([k(0, 0), k(50, 50), k(100, 0)], 1, false);' . "\n"
		. 'var moved = M.moveKnot(ks[1], 60, 40), turned = M.moveHandle(ks[1], "o", 50, 80);' . "\n"
		. 'var before = M.cubicAt(ks[0], ks[1], 0.5), split = M.splitAt(ks, false, 0, 0.5);' . "\n"
		. 'var near = M.nearestOnPath(ks, false, [100, 0]);' . "\n"
		. 'console.log(JSON.stringify({ smooth: [ks[1].ix, ks[1].iy, ks[1].ox, ks[1].oy, ks[1].s], corner: M.cornerKnot(ks, 1)[1],'
		. ' moved: [moved.x, moved.y, r3(moved.ix), r3(moved.ox)], turned: [r3(turned.ix), r3(turned.iy)],'
		. ' split: split.length, mid: [r3(split[1].x), r3(split[1].y)], before: before.map(r3), near: [near.seg, near.t, r3(near.d)] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(50 - 100 / 6, 50, 50 + 100 / 6, 50, true), $r['smooth'], 'a smooth knot\'s handles follow its neighbours');
	pg_assert_same(array('x' => 50, 'y' => 50, 'ix' => 50, 'iy' => 50, 'ox' => 50, 'oy' => 50, 's' => false), $r['corner'], 'a corner has its handles on the point');
	pg_assert_same(array(60, 40, 43.333, 76.667), $r['moved'], 'a moved knot carries its handles');
	pg_assert_same(array(50, 33.333), $r['turned'], 'the other handle stays in line, its length kept');
	pg_assert_same(4, $r['split'], 'a knot is added');
	pg_assert_same($r['before'], $r['mid'], 'on the curve where it was');
	pg_assert_same(array(1, 1, 0), $r['near'], 'the nearest point of the curve');
}

// A closed, filled path is hit inside; an open one only along its line;
// a closed path takes a fill, an open one never.
function test_image_editor_vector_hits_and_fill()
{
	$r = image_editor_vector_node(
		'var k = function (x, y) { return { x: x, y: y, ix: x, iy: y, ox: x, oy: y }; };' . "\n"
		. 'var sq = Object.assign({ type: "path", stroke: 2, color: "#ff0000", fill: "solid" }, M.pathLayer([k(0, 0), k(100, 0), k(100, 100), k(0, 100)], true));' . "\n"
		. 'var open = Object.assign({}, sq, { closed: false });' . "\n"
		. 'console.log(JSON.stringify({ inside: M.hitShape(sq, [50, 50], 1), openInside: M.hitShape(open, [50, 50], 1), edge: M.hitShape(open, [50, 1], 1),'
		. ' fill: M.fillFor(sq), openFill: M.fillFor(open) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['inside'], 'a closed, filled path is hit inside');
	pg_assert_false($r['openInside'], 'an open one is not');
	pg_assert_true($r['edge'], 'it is hit along its line');
	pg_assert_same('#ff0000', $r['fill'], 'a closed path takes the fill');
	pg_assert_same('none', $r['openFill'], 'an open one never');
}

// Converting: an ellipse keeps four smooth knots on its box's edges, a
// rounded box eight, a polygon its corners, a line its two ends open.
function test_image_editor_vector_convert_shapes()
{
	$r = image_editor_vector_node(
		'var box = { x: 0, y: 0, w: 100, h: 60, stroke: 2 };' . "\n"
		. 'var conv = function (t, extra) { return M.shapeKnots(Object.assign({ type: t }, box, extra || {})); };' . "\n"
		. 'var e = conv("ellipse"), rr = conv("rect", { radius: 10 }), sq = conv("rect"), hex = conv("polygon", { sides: 6 }), line = conv("line"), pen = conv("pen", { trace: [[0, 0], [0.5, 1], [1, 0]] });' . "\n"
		. 'var eb = M.knotBounds(e.knots, true);' . "\n"
		. 'console.log(JSON.stringify({ e: [e.knots.length, e.closed, e.knots[0].x, e.knots[0].y, e.knots.every(function (k) { return k.s; })], eb: [r3(eb.x), r3(eb.y), r3(eb.w), r3(eb.h)],'
		. ' rr: rr.knots.length, sq: sq.knots.length, hex: hex.knots.length, line: [line.closed, line.knots.length, line.knots[1].x], pen: [pen.knots.length, pen.knots[1].s] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(4, true, 50, 0, true), $r['e'], 'an ellipse: four smooth knots, the first at the top');
	pg_assert_same(array(0, 0, 100, 60), $r['eb'], 'its curve fills its box');
	pg_assert_same(8, $r['rr'], 'a rounded box: two knots per corner');
	pg_assert_same(4, $r['sq'], 'a square-cornered box: its corners');
	pg_assert_same(6, $r['hex'], 'a polygon: its corners');
	pg_assert_same(array(false, 2, 100), $r['line'], 'a line: its two ends, open');
	pg_assert_same(array(3, true), $r['pen'], 'a pen stroke: smooth knots');
}

// A path follows a flip of the picture like a drawn shape: it is mirrored.
function test_image_editor_vector_follows_the_picture()
{
	$r = image_editor_vector_node(
		'var k = function (x, y) { return { x: x, y: y, ix: x, iy: y, ox: x, oy: y }; };' . "\n"
		. 'var d = M.newDocument({}); d.source.width = 400; d.source.height = 300;' . "\n"
		. 'd.layers = [Object.assign({ id: "p", type: "path", stroke: 2 }, M.pathLayer([k(100, 100), k(200, 150)], false))];' . "\n"
		. 'var f = M.flipDocument(d, "x"), l = f.layers[0], ends = [M.drawnPoint(l, 0, 0), M.drawnPoint(l, l.w, l.h)];' . "\n"
		. 'console.log(JSON.stringify({ mirror: !!l.mirror, ends: ends.map(function (p) { return p.map(r3); }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['mirror'], 'a flipped path is mirrored');
	pg_assert_same(array(array(300, 100), array(200, 150)), $r['ends'], 'its first knot goes to the other side');
}
