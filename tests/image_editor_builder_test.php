<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's shape builder (the BUILDER section of
 * assets/js/pg_image_editor_model.js, run under Node): the five pathfinder
 * operations on squares and circles, holes, touching edges, the regions a
 * drag merges or removes, and the rings fitted back to curves.
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
function image_editor_builder_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiebuilder');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var r0 = function (v) { return Math.round(v); };' . "\n"
		. 'var sq = function (x, y, w, h) { return { type: "rect", x: x, y: y, w: w, h: h, rot: 0, fill: "solid", color: "#000000" }; };' . "\n"
		. 'var ci = function (x, y, d) { return { type: "ellipse", x: x, y: y, w: d, h: d, rot: 0, fill: "solid", color: "#000000" }; };' . "\n"
		. 'var it = function (id, l) { return { id: id, rings: M.layerRings(l) }; };' . "\n"
		. 'var sum = function (out) { return out.map(function (o) { return [o.id, o.rings.length, r0(M.ringsArea(o.rings))]; }); };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Two overlapping squares: each operation's pieces (style layer, ring count,
// area) and the shape of the union's ring.
function test_image_editor_builder_squares()
{
	$r = image_editor_builder_node(
		'var a = it(1, sq(0, 0, 100, 100)), b = it(2, sq(50, 50, 100, 100)), ops = {};' . "\n"
		. '["unite", "minusFront", "intersect", "exclude", "divide"].forEach(function (op) { ops[op] = sum(M.pathfinder(op, [a, b])); });' . "\n"
		. 'var u = M.pathfinder("unite", [a, b])[0].rings[0];' . "\n"
		. 'console.log(JSON.stringify({ ops: ops, union: u.length, one: M.pathfinder("unite", [a]) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array(2, 1, 17500)), $r['ops']['unite'], 'unite: one ring, the top layer\'s style');
	pg_assert_same(array(array(1, 1, 7500)), $r['ops']['minusFront'], 'subtract front: the bottom one without the front, its own style');
	pg_assert_same(array(array(2, 1, 2500)), $r['ops']['intersect'], 'intersect: the overlap');
	pg_assert_same(array(array(2, 2, 15000)), $r['ops']['exclude'], 'exclude: the union with the overlap as a hole');
	pg_assert_same(array(array(1, 1, 7500), array(2, 1, 2500), array(2, 1, 7500)), $r['ops']['divide'], 'divide: the rest of the first, the overlap, the rest of the second');
	pg_assert_same(8, $r['union'], 'the union\'s outline has eight corners');
	pg_assert_same(array(), $r['one'], 'one shape: nothing to combine');
}

// Two circles: the areas against the true lens, and every result smooth
// within half a pixel of the circles after the fit to curves.
function test_image_editor_builder_circles()
{
	$r = image_editor_builder_node(
		'var a = it(1, ci(0, 0, 100)), b = it(2, ci(50, 0, 100)), ops = {};' . "\n"
		. '["unite", "minusFront", "intersect", "exclude"].forEach(function (op) { ops[op] = sum(M.pathfinder(op, [a, b])); });' . "\n"
		. 'var p = M.ringsPath(M.pathfinder("unite", [a, b])[0].rings), l = Object.assign({ type: "path" }, p), off = 0;' . "\n"
		. 'M.curvePoints(M.pathKnots(l), true, 16).forEach(function (q) {' . "\n"
		. '  var x = q[0] + l.x, y = q[1] + l.y, d = Math.min(Math.abs(Math.hypot(x - 50, y - 50) - 50), Math.abs(Math.hypot(x - 100, y - 50) - 50));' . "\n"
		. '  off = Math.max(off, d); });' . "\n"
		. 'console.log(JSON.stringify({ ops: ops, knots: p.knots.length, sharp: p.knots.filter(function (k) { return !k.s; }).length, off: off < 0.75,'
		. ' box: [r0(p.x), r0(p.y), r0(p.w), r0(p.h)] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	// The lens of two circles of radius 50 whose centres are 50 apart.
	$lens = 2 * 2500 * acos(0.5) - 25 * sqrt(7500);
	$circle = M_PI * 2500;
	$near = function ($got, $want) { return abs($got - $want) < $want * 0.005 + 2; };

	pg_assert_true($near($r['ops']['unite'][0][2], 2 * $circle - $lens), 'unite: two circles less the lens');
	pg_assert_true($near($r['ops']['intersect'][0][2], $lens), 'intersect: the lens');
	pg_assert_true($near($r['ops']['minusFront'][0][2], $circle - $lens), 'subtract front: a crescent');
	pg_assert_true($near($r['ops']['exclude'][0][2], 2 * $circle - 2 * $lens), 'exclude: two crescents');
	pg_assert_same(2, $r['ops']['exclude'][0][1], 'exclude: two rings');
	pg_assert_same(2, $r['sharp'], 'the union is sharp only where the circles cross');
	pg_assert_true($r['knots'] < 12, 'and fitted with few curves');
	pg_assert_true($r['off'], 'within the fit tolerance of the circles');
	pg_assert_true((abs($r['box'][0]) <= 1) && (abs($r['box'][1]) <= 1) && (abs($r['box'][2] - 150) <= 1) && (abs($r['box'][3] - 100) <= 1), 'the path\'s box is the union\'s');
}

// Holes: a frame cut out of a square, kept as a contour winding the other
// way; a shape inside the hole; divide with a shape inside another.
function test_image_editor_builder_holes()
{
	$r = image_editor_builder_node(
		'var big = it(1, sq(0, 0, 100, 100)), mid = it(2, sq(25, 25, 50, 50)), dot = it(3, ci(40, 40, 20));' . "\n"
		. 'var frame = M.pathfinder("minusFront", [big, mid])[0], p = M.ringsPath(frame.rings);' . "\n"
		. 'var areas = frame.rings.map(function (q) { return r0(M.ringArea(q)); });' . "\n"
		. 'var back = it(4, Object.assign({ type: "path", fill: "solid" }, p));' . "\n"
		. 'var withDot = M.pathfinder("unite", [back, dot])[0];' . "\n"
		. 'var div = sum(M.pathfinder("divide", [big, mid]));' . "\n"
		. 'console.log(JSON.stringify({ areas: areas, subs: p.subs.length, back: [back.rings.length, r0(M.ringsArea(M.clipRings(back.rings, [], "union")))],'
		. ' dot: [withDot.rings.length, r0(M.ringsArea(withDot.rings))], inHole: M.insideRings([50, 50], frame.rings), onFrame: M.insideRings([10, 10], frame.rings),'
		. ' div: div, islands: M.ringIslands(withDot.rings).length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(10000, -2500), $r['areas'], 'the hole winds against the outer ring');
	pg_assert_same(1, $r['subs'], 'the path keeps the hole as a sub-contour');
	pg_assert_same(array(2, 7500), $r['back'], 'the path reads back as the same frame');
	pg_assert_false($r['inHole'], 'the hole is empty');
	pg_assert_true($r['onFrame'], 'the frame is filled');
	pg_assert_same(3, $r['dot'][0], 'a circle in the hole stays a ring of its own');
	pg_assert_true(abs($r['dot'][1] - (7500 + 314)) < 4, 'and adds its area');
	pg_assert_same(2, $r['islands'], 'two islands: the frame with its hole, the circle');
	pg_assert_same(array(array(1, 2, 7500), array(2, 1, 2500)), $r['div'], 'divide: the frame, the inner square');
}

// Edges that touch: shared sides merge, a shared corner keeps both, and an
// overlap of nothing is nothing.
function test_image_editor_builder_touching()
{
	$r = image_editor_builder_node(
		'var a = it(1, sq(0, 0, 100, 100)), side = it(2, sq(100, 0, 100, 100)), corner = it(3, sq(100, 100, 50, 50)), half = it(4, sq(100, 25, 50, 50));' . "\n"
		. 'var u = M.pathfinder("unite", [a, side])[0], p = M.ringsPath(u.rings);' . "\n"
		. 'console.log(JSON.stringify({ side: sum([u]), knots: p.knots.length, meet: M.pathfinder("intersect", [a, side]), corner: sum(M.pathfinder("unite", [a, corner])),'
		. ' half: sum(M.pathfinder("unite", [a, half])), halfKnots: M.ringsPath(M.pathfinder("unite", [a, half])[0].rings).knots.length,'
		. ' cut: sum(M.pathfinder("minusFront", [a, side])), div: sum(M.pathfinder("divide", [a, side])) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array(2, 1, 20000)), $r['side'], 'squares side by side unite into one ring');
	pg_assert_same(4, $r['knots'], 'their shared side is gone: four corners');
	pg_assert_same(array(), $r['meet'], 'touching squares have no overlap');
	pg_assert_same(array(array(3, 2, 12500)), $r['corner'], 'squares meeting at a corner stay two rings');
	pg_assert_same(array(array(4, 1, 12500)), $r['half'], 'a square against part of a side');
	pg_assert_same(8, $r['halfKnots'], 'its outline: eight corners');
	pg_assert_same(array(array(1, 1, 10000)), $r['cut'], 'subtracting a touching square leaves the square');
	pg_assert_same(array(array(1, 1, 10000), array(2, 1, 10000)), $r['div'], 'divide: both squares as they were');
}

// The shape builder: regions of three shapes, the region under a point, a
// drag merging two regions and an Alt-drag removing one; turned and
// mirrored layers in the rotated space.
function test_image_editor_builder_regions()
{
	$r = image_editor_builder_node(
		'var items = [it(1, sq(0, 0, 100, 100)), it(2, sq(50, 0, 100, 100))], regions = M.builderRegions(items);' . "\n"
		. 'var left = M.regionAt(regions, [25, 50]), mid = M.regionAt(regions, [75, 50]), right = M.regionAt(regions, [125, 50]);' . "\n"
		. 'var merged = M.builderMerge(regions, [left, mid], false), removed = M.builderMerge(regions, [mid], true);' . "\n"
		. 'var turned = M.layerRings({ type: "rect", x: 0, y: 0, w: 100, h: 100, rot: 45, fill: "solid" })[0];' . "\n"
		. 'var mirrored = M.layerRings({ type: "triangle", x: 0, y: 0, w: 100, h: 100, rot: 0, mirror: true, fill: "solid" })[0];' . "\n"
		. 'var open = M.layerRings({ type: "path", x: 0, y: 0, w: 10, h: 10, closed: false, knots: [] });' . "\n"
		. 'console.log(JSON.stringify({ count: regions.length, members: regions.map(function (g) { return g.members; }), at: [left >= 0, mid >= 0, right >= 0, M.regionAt(regions, [500, 500])],'
		. ' merged: [merged.ids, sum(merged.pieces)], removed: [removed.ids, sum(removed.pieces)], none: M.builderMerge(regions, [], false),'
		. ' turned: [r0(Math.min.apply(null, turned.map(function (q) { return q[1]; }))), r0(Math.abs(M.ringArea(turned)))],'
		. ' mirrored: mirrored.map(function (q) { return [r0(q[0]), r0(q[1])]; }), open: open }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(3, $r['count'], 'two overlapping squares give three regions');
	pg_assert_same(array(array(1), array(1, 2), array(2)), $r['members'], 'each region knows the shapes covering it');
	pg_assert_same(array(true, true, true, -1), $r['at'], 'the region under a point; none outside');
	pg_assert_same(array(array(1, 2), array(array(1, 1, 10000), array(2, 1, 5000))), $r['merged'], 'a drag over two regions merges them in the first one\'s style; the third stays');
	pg_assert_same(array(array(1, 2), array(array(1, 1, 5000), array(2, 1, 5000))), $r['removed'], 'an Alt-drag removes the overlap');
	pg_assert_same(array('ids' => array(), 'pieces' => array()), $r['none'], 'no region touched, no change');
	pg_assert_same(array(-21, 10000), $r['turned'], 'a turned square in the rotated space');
	pg_assert_same(array(array(50, 0), array(0, 100), array(100, 100)), $r['mirrored'], 'a mirrored triangle');
	pg_assert_same(null, $r['open'], 'an open path is not an area');
}

// A shape builder click: on the overlap of two squares it unites them whole
// in the top one's style; on a region of one square it changes nothing; a
// third square elsewhere stays out of it.
function test_image_editor_builder_click_unites_the_stack()
{
	$r = image_editor_builder_node(
		'var items = [it(1, sq(0, 0, 100, 100)), it(2, sq(50, 0, 100, 100)), it(3, sq(400, 0, 50, 50))], regions = M.builderRegions(items);' . "\n"
		. 'var mid = regions[M.regionAt(regions, [75, 50])], left = regions[M.regionAt(regions, [25, 50])];' . "\n"
		. 'var stacked = M.builderStack(items, mid), alone = M.builderStack(items, left);' . "\n"
		. 'console.log(JSON.stringify({ stacked: [stacked.ids, sum(stacked.pieces)], alone: alone, none: M.builderStack(items, null) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array(1, 2), array(array(2, 1, 15000))), $r['stacked'], 'a click on the overlap unites both squares in the top one\'s style');
	pg_assert_same(array('ids' => array(), 'pieces' => array()), $r['alone'], 'a click on one square\'s own region leaves it whole');
	pg_assert_same(array('ids' => array(), 'pieces' => array()), $r['none'], 'a click outside every region does nothing');
}
