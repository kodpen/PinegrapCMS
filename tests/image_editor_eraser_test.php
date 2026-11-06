<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's eraser on shapes and paths (the DRAW section
 * of assets/js/pg_image_editor_model.js, run under Node): which layers it
 * masks, when its move touches a drawing, the strokes it keeps in the
 * layer's frame, the mask staying in place when a path's box is fitted
 * again or a warp is expanded, and the pen stroke cut along the eraser's
 * whole move.
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
function image_editor_eraser_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieeraser');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var r1 = function (v) { return Math.round(v * 10) / 10; };' . "\n"
		// Where a mask point (stroke s, point i) lies on the document.
		. 'var at = function (l, s, i) { var p = l.erase[s].pts[i]; return M.drawnPoint(l, p[0] * l.w / l.erase[s].w, p[1] * l.h / l.erase[s].h).map(r1); };' . "\n"
		. 'var rect = { id: "a", type: "rect", x: 100, y: 100, w: 200, h: 100, rot: 0, fill: "solid", color: "#1e88e5", stroke: 4 };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Shapes and paths take a mask; a pen stroke is cut instead, and a text,
// a sticker or a picture is left alone. A broken stroke is not drawn.
function test_image_editor_eraser_layers()
{
	$r = image_editor_eraser_node(
		'console.log(JSON.stringify({'
		. ' kinds: ["rect", "ellipse", "star", "arrow", "line", "bubble", "path", "pen", "text", "sticker", "image"].filter(function (t) { return M.erasable({ type: t }); }),'
		. ' none: M.eraseOn(rect),'
		. ' on: M.eraseOn(Object.assign({}, rect, { erase: [{ w: 200, h: 100, r: 5, pts: [[1, 2]] }] })),'
		. ' broken: M.eraseStrokes(Object.assign({}, rect, { erase: [{ w: 0, h: 100, r: 5, pts: [[1, 2]] }, { w: 200, h: 100, r: 5, pts: [["x", 2]] }, null, { w: 200, h: 100, r: 5, pts: [[3, 4]] }] })).length,'
		. ' text: M.eraseOn({ type: "text", w: 10, h: 10, erase: [{ w: 10, h: 10, r: 5, pts: [[1, 2]] }] })'
		. ' }));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(array('rect', 'ellipse', 'star', 'arrow', 'line', 'bubble', 'path'), $r['kinds'], 'drawn shapes and paths');
	pg_assert_false($r['none'], 'no mask yet');
	pg_assert_true($r['on'], 'a stroke masks');
	pg_assert_same(1, $r['broken'], 'only the sound stroke is drawn');
	pg_assert_false($r['text'], 'a text is not masked');
}

// A move touches a filled shape inside it, a shape without a fill only on
// its line, also when the move is fast and only its way crosses the line;
// a move far away does not.
function test_image_editor_eraser_touches()
{
	$r = image_editor_eraser_node(
		'var hollow = Object.assign({}, rect, { fill: "none" }), turned = Object.assign({}, rect, { rot: 90, mirror: true });' . "\n"
		. 'console.log(JSON.stringify({'
		. ' inside: M.eraseTouches(rect, [200, 150], [205, 150], 5),'
		. ' hollowInside: M.eraseTouches(hollow, [200, 150], [205, 150], 5),'
		. ' hollowLine: M.eraseTouches(hollow, [200, 102], [205, 102], 5),'
		. ' fastAcross: M.eraseTouches(hollow, [200, 40], [200, 260], 5),'
		. ' far: M.eraseTouches(rect, [500, 500], [520, 500], 5),'
		. ' turnedIn: M.eraseTouches(turned, [200, 100], [201, 100], 5),'
		. ' turnedOut: M.eraseTouches(turned, [120, 150], [121, 150], 5)'
		. ' }));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_true($r['inside'], 'inside a filled box');
	pg_assert_false($r['hollowInside'], 'inside a box without a fill');
	pg_assert_true($r['hollowLine'], 'on its line');
	pg_assert_true($r['fastAcross'], 'a fast move over the line');
	pg_assert_false($r['far'], 'far away');
	pg_assert_true($r['turnedIn'], 'a turned box, inside its turned frame');
	pg_assert_false($r['turnedOut'], 'outside its turned frame');
}

// A drag keeps one stroke: the first move makes it in the layer's frame
// before the mirror, later points join it, a point within a quarter of the
// radius of the last is skipped, and a full stroke goes on in a new one.
function test_image_editor_eraser_strokes()
{
	$r = image_editor_eraser_node(
		'var turned = Object.assign({}, rect, { rot: 30, mirror: true });' . "\n"
		. 'var a = M.eraseAdd(turned, [150, 140], [160, 150], 6, null), t2 = Object.assign({}, turned, { erase: a.erase });' . "\n"
		. 'var b = M.eraseAdd(t2, [160, 150], [180, 150], 6, a.i), t3 = Object.assign({}, turned, { erase: b.erase });' . "\n"
		. 'var c = M.eraseAdd(t3, [180, 150], [180.5, 150], 6, b.i);' . "\n"
		. 'var full = Object.assign({}, rect, { erase: [{ w: 200, h: 100, r: 6, pts: [] }] });' . "\n"
		. 'for (var i = 0; i < M.ERASE_POINTS; i++) { full.erase[0].pts.push([i % 200, 50]); }' . "\n"
		. 'var d = M.eraseAdd(full, [150, 150], [260, 160], 6, 0);' . "\n"
		. 'console.log(JSON.stringify({'
		. ' first: a.erase, firstAt: [at(t2, 0, 0), at(t2, 0, 1)], i: a.i,'
		. ' joined: b.erase[0].pts.length, joinedAt: at(t3, 0, 2), same: b.i,'
		. ' skipped: c.erase[0].pts.length,'
		. ' full: d.erase.length, fullPts: d.erase[0].pts.length, next: d.erase[1].pts.length, nextI: d.i'
		. ' }));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(1, count($r['first']), 'one stroke');
	pg_assert_same(array(200, 100, 6), array($r['first'][0]['w'], $r['first'][0]['h'], $r['first'][0]['r']), 'the box it was drawn in and the radius');
	pg_assert_same(array(array(150, 140), array(160, 150)), $r['firstAt'], 'its points lie where the eraser went, turned and mirrored');
	pg_assert_same(0, $r['i'], 'the drag goes on with it');
	pg_assert_same(3, $r['joined'], 'a later point joins');
	pg_assert_same(array(180, 150), $r['joinedAt'], 'where the eraser went');
	pg_assert_same(0, $r['same'], 'the same stroke');
	pg_assert_same(3, $r['skipped'], 'a point within a quarter of the radius is skipped');
	pg_assert_same(2, $r['full'], 'a full stroke goes on in a new one');
	pg_assert_same(4000, $r['fullPts'], 'the full one is left as it is');
	pg_assert_same(2, $r['next'], 'from its last point');
	pg_assert_same(1, $r['nextI'], 'the drag goes on with the new one');
}

// Convert to path, a path's knot moved and Expand fit the box again: the
// mask stays where it was on the document. A resize stretches it with the
// drawing.
function test_image_editor_eraser_mask_stays()
{
	$r = image_editor_eraser_node(
		'var star = { id: "s", type: "star", x: 100, y: 100, w: 200, h: 200, rot: 20, mirror: true, fill: "solid", stroke: 4, points: 5, inner: 0.48,'
		. ' erase: [{ w: 200, h: 200, r: 8, pts: [[10, 20], [150, 120]] }] };' . "\n"
		. 'var before = [at(star, 0, 0), at(star, 0, 1)];' . "\n"
		. 'var sk = M.shapeKnots(star), fit = M.refitPath(star, sk.knots), path = Object.assign({}, star, fit, { type: "path" });' . "\n"
		. 'var knots = M.pathKnots(path); knots[0] = M.moveKnot(knots[0], knots[0].x - 60, knots[0].y - 40);' . "\n"
		. 'var moved = Object.assign({}, path, M.refitPath(path, knots));' . "\n"
		. 'var warped = Object.assign({}, rect, { warp: { style: "arc", bend: 50, h: 0, v: 0 }, erase: [{ w: 200, h: 100, r: 5, pts: [[20, 30]] }] });' . "\n"
		. 'var wBefore = at(warped, 0, 0), expanded = Object.assign({}, warped, M.warpExpand(warped));' . "\n"
		. 'var wide = Object.assign({}, rect, { w: 400, erase: [{ w: 200, h: 100, r: 5, pts: [[50, 50]] }] });' . "\n"
		. 'console.log(JSON.stringify({'
		. ' before: before, converted: [at(path, 0, 0), at(path, 0, 1)], rebased: fit.erase[0].w === fit.w,'
		. ' moved: [at(moved, 0, 0), at(moved, 0, 1)], movedBox: (moved.x !== path.x) || (moved.y !== path.y) || (moved.w !== path.w) || (moved.h !== path.h),'
		. ' warpBefore: wBefore, expanded: at(expanded, 0, 0), expandedBox: [expanded.w, expanded.h].join("x") !== "200x100",'
		. ' wide: at(wide, 0, 0)'
		. ' }));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_true($r['rebased'], 'Convert to path rebases the mask on the box it fits');
	pg_assert_same($r['before'], $r['converted'], 'the mask stays in place after Convert to path');
	pg_assert_true($r['movedBox'], 'a moved knot fits the box again');
	pg_assert_same($r['before'], $r['moved'], 'the mask stays in place after a knot moved');
	pg_assert_true($r['expandedBox'], 'Expand fits a new box');
	pg_assert_same($r['warpBefore'], $r['expanded'], 'the mask stays in place after Expand');
	pg_assert_same(array(200.0, 150.0), array((float) $r['wide'][0], (float) $r['wide'][1]), 'a wider box stretches the mask with the drawing');
}

// The pen eraser cuts the points it swept over between two moves, not
// only those around where it stopped.
function test_image_editor_eraser_pen_swept()
{
	$r = image_editor_eraser_node(
		'var trace = []; for (var i = 0; i <= 20; i++) { trace.push([i / 20, 0.5]); }' . "\n"
		. 'var pen = { id: "p", type: "pen", x: 0, y: 0, w: 200, h: 10, rot: 0, stroke: 2, trace: trace };' . "\n"
		. 'var still = M.eraseTrace(pen, 100, 5, 4), swept = M.eraseTrace(pen, 100, 5, 4, [40, 5]);' . "\n"
		. 'console.log(JSON.stringify({ still: still.map(function (p) { return Math.round(p.w); }), swept: swept.map(function (p) { return Math.round(p.w); }) }));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(array(90, 90), $r['still'], 'where it stopped: a gap in the middle');
	pg_assert_same(array(30, 90), $r['swept'], 'its whole move: 40 to 100 gone');
}
