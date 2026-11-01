<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's drawing tool (the DRAW section of
 * assets/js/pg_image_editor_model.js, run under Node): the boxes a drag
 * draws, a line's box and its ends, the ready-made shapes' paths, the pen's
 * trace and the eraser, hits on a shape's outline, and shapes that follow a
 * turn or a flip of the picture.
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
function image_editor_draw_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiedraw');

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

// A drag draws a box from either corner; Shift makes it square from the
// corner it started at; the stroke presets follow the picture's size.
function test_image_editor_draw_boxes_and_strokes()
{
	$r = image_editor_draw_node(
		'console.log(JSON.stringify({'
		. ' kinds: M.DRAW_KINDS, box: M.shapeBox(100, 80, 40, 50, false), square: M.shapeBox(100, 80, 40, 50, true),'
		. ' stroke: M.strokeFor("medium", 2400, 1600), thin: M.strokeFor("thin", 100, 50), preset: M.strokePreset(M.strokeFor("thick", 2400, 1600), 2400, 1600)'
		. ' }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('arrow', 'line', 'rect', 'ellipse', 'triangle', 'polygon', 'star', 'bubble', 'pen'), $r['kinds'], 'the nine kinds of screen 04');
	pg_assert_same(array('x' => 40, 'y' => 50, 'w' => 60, 'h' => 30, 'rot' => 0), $r['box'], 'a box dragged up and left');
	pg_assert_same(array('x' => 40, 'y' => 20, 'w' => 60, 'h' => 60, 'rot' => 0), $r['square'], 'Shift draws a square from the corner it started at');
	pg_assert_same(10.1, $r['stroke'], 'medium is 0.42 % of the longer side');
	pg_assert_same(1, $r['thin'], 'never thinner than a pixel');
	pg_assert_same('thick', $r['preset'], 'a width reads back as its preset');
}

// A line is a box along its length, turned to its angle, as thick as its
// stroke or its arrow head; its ends come back from the box, a mirror
// swaps them; Shift snaps the angle to 45 degrees.
function test_image_editor_draw_lines()
{
	$r = image_editor_draw_node(
		'var l = M.lineBox([0, 0], [100, 100], false, { kind: "line", stroke: 4 });' . "\n"
		. 'var a = M.lineBox([0, 0], [200, 0], false, { kind: "arrow", stroke: 4, head: "fill" });' . "\n"
		. 'var s = M.lineBox([0, 0], [100, 10], true, { kind: "line", stroke: 4 });' . "\n"
		. 'var m = Object.assign({ type: "line", stroke: 4 }, l, { mirror: true });' . "\n"
		. 'var ends = M.lineEnds(Object.assign({ type: "line", stroke: 4 }, l)), back = M.lineEnds(m);' . "\n"
		. 'console.log(JSON.stringify({ len: r3(l.w), h: l.h, rot: l.rot, mid: [r3(l.x + l.w / 2), r3(l.y + l.h / 2)],'
		. ' arrowH: r3(a.h), snapped: s.rot, ends: ends.map(function (p) { return p.map(r3); }), back: back.map(function (p) { return p.map(r3); }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(141.421, $r['len'], 'the box is as long as the line');
	pg_assert_same(4, $r['h'], 'and as thick as its stroke');
	pg_assert_same(45, $r['rot'], 'turned to its angle');
	pg_assert_same(array(50, 50), $r['mid'], 'around its middle');
	pg_assert_same(true, $r['arrowH'] > 4, 'an arrow is as thick as its head');
	pg_assert_same(0, $r['snapped'], 'Shift snaps to 45 degree steps');
	pg_assert_same(array(array(0, 0), array(100, 100)), $r['ends'], 'the ends come back');
	pg_assert_same(array(array(100, 100), array(0, 0)), $r['back'], 'a mirrored line runs the other way');
}

// Every kind has a path in its box; a closed shape is filled; the corner
// radius is kept under half the shorter side; a polygon has its sides, a
// star twice its points, and both fill their box.
function test_image_editor_draw_paths()
{
	$r = image_editor_draw_node(
		'var base = { x: 0, y: 0, w: 120, h: 80, rot: 0, stroke: 4, color: "#e53935" };' . "\n"
		. 'var paths = {}; M.DRAW_KINDS.forEach(function (k) { var p = M.shapePath(Object.assign({ type: k, trace: [[0, 0], [0.5, 1], [1, 0]] }, base)); paths[k] = { d: p.d.length > 0, closed: p.closed, head: !!p.head }; });' . "\n"
		. 'var hex = M.regularPoints(120, 80, 6, 0), star = M.regularPoints(100, 100, 5, 0.5);' . "\n"
		. 'var xs = function (pts, i) { return pts.map(function (p) { return p[i]; }); };' . "\n"
		. 'console.log(JSON.stringify({ paths: paths, hex: hex.length, star: star.length,'
		. ' hexBox: [r3(Math.min.apply(null, xs(hex, 0))), r3(Math.max.apply(null, xs(hex, 0))), r3(Math.min.apply(null, xs(hex, 1))), r3(Math.max.apply(null, xs(hex, 1)))],'
		. ' starTop: star[0].map(r3), maxRadius: M.maxRadius({ type: "rect", w: 120, h: 80 }), bubbleMax: M.maxRadius({ type: "bubble", w: 120, h: 80 }),'
		. ' bigRadius: M.shapePath(Object.assign({}, base, { type: "rect", radius: 500 })).d === M.shapePath(Object.assign({}, base, { type: "rect", radius: 40 })).d,'
		. ' dash: M.dashFor({ stroke: 2, dash: "dashed" }), dot: M.dashFor({ stroke: 2, dash: "dotted" }), solid: M.dashFor({ stroke: 2 }),'
		. ' soft: M.fillFor({ type: "rect", color: "#ff0000", fill: "soft" }), none: M.fillFor({ type: "rect", color: "#ff0000" }), lineFill: M.fillFor({ type: "line", color: "#ff0000", fill: "solid" }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach (array('arrow', 'line', 'pen') as $open) {
		pg_assert_same(false, $r['paths'][$open]['closed'], $open . ' is not filled');
	}

	foreach (array('rect', 'ellipse', 'triangle', 'polygon', 'star', 'bubble') as $closed) {
		pg_assert_same(true, $r['paths'][$closed]['closed'], $closed . ' can be filled');
	}

	foreach ($r['paths'] as $kind => $p) {
		pg_assert_true($p['d'], $kind . ' has a path');
	}

	pg_assert_true($r['paths']['arrow']['head'], 'an arrow has a head');
	pg_assert_same(6, $r['hex'], 'a polygon has its sides');
	pg_assert_same(10, $r['star'], 'a star has twice its points');
	pg_assert_same(array(0, 120, 0, 80), $r['hexBox'], 'and fills its box');
	pg_assert_same(array(50, 0), $r['starTop'], 'the first point is at the top');
	pg_assert_same(40, $r['maxRadius'], 'a box rounds up to half its shorter side');
	pg_assert_true($r['bubbleMax'] < 40, 'a bubble keeps room for its tail');
	pg_assert_true($r['bigRadius'], 'a larger radius is cut to the most');
	pg_assert_same(array(4.8, 3.8), $r['dash'], 'dashes follow the stroke');
	pg_assert_same(array(0.01, 4), $r['dot'], 'dots too');
	pg_assert_same(null, $r['solid'], 'a solid line has none');
	pg_assert_same('rgba(255, 0, 0, 0.28)', $r['soft'], 'a tint is the colour, see-through');
	pg_assert_same('none', $r['none'], 'no fill by default');
	pg_assert_same('none', $r['lineFill'], 'a line is never filled');
}

// The pen keeps its points in parts of its box, so moving and resizing the
// box carries them; the eraser cuts a stroke where it passes and drops what
// is left of nothing.
function test_image_editor_draw_pen_and_eraser()
{
	$r = image_editor_draw_node(
		'var pen = Object.assign({ id: "p", type: "pen", stroke: 2 }, M.penLayer([[10, 10], [60, 10], [110, 10]]));' . "\n"
		. 'var dot = M.penLayer([[5, 5]]);' . "\n"
		. 'var cut = M.eraseTrace(pen, 60, 10, 5), miss = M.eraseTrace(pen, 60, 80, 5), all = M.eraseTrace(pen, 60, 10, 80);' . "\n"
		. 'var long = Object.assign({ id: "q", type: "pen", stroke: 2 }, M.penLayer([[0, 0], [20, 0], [40, 0], [60, 0], [80, 0], [100, 0]]));' . "\n"
		. 'var split = M.eraseTrace(long, 50, 0, 5);' . "\n"
		. 'console.log(JSON.stringify({ pen: [pen.x, pen.y, pen.w, pen.h], trace: pen.trace, dot: [dot.x, dot.y, dot.w, dot.h, dot.trace],'
		. ' cut: cut.length, miss: miss, all: all, split: split.map(function (l) { return [r3(l.x), r3(l.w), l.trace.length]; }),'
		. ' smooth: M.smoothTrace([[0, 0], [10, 10], [20, 0]], 1).map(function (p) { return p.map(r3); }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(10, 10, 100, 1), $r['pen'], 'the box around the points, at least a pixel high');
	pg_assert_same(array(array(0, 0.5), array(0.5, 0.5), array(1, 0.5)), $r['trace'], 'points in parts of the box');
	pg_assert_same(array(4.5, 4.5, 1, 1, array(array(0.5, 0.5))), $r['dot'], 'a click is a dot');
	pg_assert_same(2, $r['cut'], 'the eraser through the middle point leaves two ends');
	pg_assert_same(null, $r['miss'], 'a stroke it does not touch stays');
	pg_assert_same(array(), $r['all'], 'a stroke under it all goes');
	pg_assert_same(array(array(0, 40, 3), array(60, 40, 3)), $r['split'], 'the pieces keep their own boxes');
	pg_assert_same(array(array(0, 0), array(10, 3.333), array(20, 0)), $r['smooth'], 'smoothing averages the middle, keeps the ends');
}

// A press near an empty shape's outline hits it, inside it does not; a
// filled shape is hit inside; a line only along itself.
function test_image_editor_draw_hits()
{
	$r = image_editor_draw_node(
		'var rect = { type: "rect", x: 0, y: 0, w: 100, h: 60, stroke: 4 };' . "\n"
		. 'var filled = Object.assign({}, rect, { fill: "solid" });' . "\n"
		. 'var star = { type: "star", x: 0, y: 0, w: 100, h: 100, stroke: 2, fill: "solid", points: 5, inner: 0.4 };' . "\n"
		. 'console.log(JSON.stringify({ edge: M.hitShape(rect, [1, 30], 2), inside: M.hitShape(rect, [50, 30], 2), filled: M.hitShape(filled, [50, 30], 2),'
		. ' starCorner: M.hitShape(star, [3, 3], 1), starMiddle: M.hitShape(star, [50, 50], 1) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['edge'], 'the outline is hit');
	pg_assert_false($r['inside'], 'an empty shape lets the press through');
	pg_assert_true($r['filled'], 'a filled shape is hit inside');
	pg_assert_false($r['starCorner'], 'outside a star\'s points is a miss');
	pg_assert_true($r['starMiddle'], 'its middle is a hit');
}

// Under a quarter turn a shape keeps its size and turns; under a flip it
// is mirrored, so an arrow points the other way and a triangle down.
function test_image_editor_draw_shapes_follow_the_picture()
{
	$r = image_editor_draw_node(
		'var d = M.newDocument({}); d.source.width = 400; d.source.height = 300;' . "\n"
		. 'var arrow = Object.assign({ id: "a", type: "arrow", stroke: 4, head: "fill" }, M.lineBox([100, 100], [200, 100], false, { kind: "arrow", stroke: 4, head: "fill" }));' . "\n"
		. 'd.layers = [arrow, { id: "t", type: "triangle", x: 10, y: 10, w: 40, h: 30, rot: 0 }];' . "\n"
		. 'var f = M.flipDocument(d, "x"), v = M.flipDocument(d, "y"), t = M.turnDocument(d, 1);' . "\n"
		. 'var e = function (doc) { return M.lineEnds(doc.layers[0]).map(function (p) { return p.map(r3); }); };' . "\n"
		. 'var back = M.flipDocument(f, "x");' . "\n"
		. 'console.log(JSON.stringify({ flipped: e(f), turned: e(t), size: [r3(t.layers[1].w), r3(t.layers[1].h), t.layers[1].rot],'
		. ' down: [v.layers[1].rot, !!v.layers[1].mirror], back: [e(back), !!back.layers[0].mirror] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array(300, 100), array(200, 100)), $r['flipped'], 'a flipped arrow points the other way');
	pg_assert_same(array(array(200, 100), array(200, 200)), $r['turned'], 'a turned arrow points down');
	pg_assert_same(array(40, 30, 90), $r['size'], 'a turned shape keeps its size and turns');
	pg_assert_same(array(180, true), $r['down'], 'a triangle flipped upside down');
	pg_assert_same(array(array(array(100, 100), array(200, 100)), false), $r['back'], 'two flips bring it back');
}
