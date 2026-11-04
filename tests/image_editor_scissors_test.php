<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's scissors (the VECTOR section of
 * assets/js/pg_image_editor_model.js, run under Node): the nearest cut
 * point, where a line crosses a path, and a path cut into pieces on lines,
 * curves and closed shapes (knot counts, end points).
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
function image_editor_scissors_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiescissors');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var r1 = function (v) { return Math.round(v * 10) / 10; };' . "\n"
		. 'var k = function (x, y) { return { x: x, y: y, ix: x, iy: y, ox: x, oy: y, s: false }; };' . "\n"
		. 'var ends = function (p) { var a = p.knots[0], b = p.knots[p.knots.length - 1]; return [p.knots.length, p.closed, r1(a.x), r1(a.y), r1(b.x), r1(b.y)]; };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A click on an open line cuts it in two; on a curve the cut lies where the
// curve was; a cut on an end of an open path does nothing.
function test_image_editor_scissors_open_paths()
{
	$r = image_editor_scissors_node(
		'var line = [k(0, 0), k(100, 0)], curve = M.smoothKnot([k(0, 0), k(50, 50), k(100, 0), k(150, 50)], 1, false);' . "\n"
		. 'var near = M.nearestCut(line, false, [40.4, 3]), at = M.cubicAt(curve[1], curve[2], 0.5);' . "\n"
		. 'var lp = M.cutKnots(line, false, [{ seg: near.seg, t: near.t }]), cp = M.cutKnots(curve, false, [{ seg: 1, t: 0.5 }]);' . "\n"
		. 'var knot = M.cutKnots(curve, false, [{ seg: 1, t: 0 }]);' . "\n"
		. 'console.log(JSON.stringify({ near: [near.seg, r1(M.cubicAt(line[0], line[1], near.t)[0]), r1(near.d)], line: lp.map(ends), curve: cp.map(ends), at: at.map(r1),'
		. ' cend: [cp[0].knots[2].ox === cp[0].knots[2].x, cp[1].knots[0].ix === cp[1].knots[0].x, cp[1].knots[0].s],'
		. ' knot: knot.map(ends), end: M.cutKnots(line, false, [{ seg: 0, t: 0 }]), none: M.cutKnots(line, false, []) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 40.4, 3), $r['near'], 'the nearest point of the line, finer than a sampled step');
	pg_assert_same(array(array(2, false, 0, 0, 40.4, 0), array(2, false, 40.4, 0, 100, 0)), $r['line'], 'a line cut in two at the click');
	pg_assert_same(array(3, false, 0, 0, $r['at'][0], $r['at'][1]), $r['curve'][0], 'the first piece ends where the curve was cut');
	pg_assert_same(array(3, false, $r['at'][0], $r['at'][1], 150, 50), $r['curve'][1], 'the second starts there');
	pg_assert_same(array(true, true, false), $r['cend'], 'the cut ends lose the handle past the cut');
	pg_assert_same(array(array(2, false, 0, 0, 50, 50), array(3, false, 50, 50, 150, 50)), $r['knot'], 'a cut on a knot splits there without a new knot');
	pg_assert_same(null, $r['end'], 'a cut on an end does nothing');
	pg_assert_same(null, $r['none'], 'no cut, no pieces');
}

// A closed shape opens where it is cut: one piece whose first and last knots
// are the cut point; two cuts give two pieces.
function test_image_editor_scissors_closed_paths()
{
	$r = image_editor_scissors_node(
		'var sq = [k(0, 0), k(100, 0), k(100, 100), k(0, 100)];' . "\n"
		. 'var one = M.cutKnots(sq, true, [{ seg: 1, t: 0.5 }]), atKnot = M.cutKnots(sq, true, [{ seg: 2, t: 0 }]);' . "\n"
		. 'var two = M.cutKnots(sq, true, [{ seg: 3, t: 0.5 }, { seg: 1, t: 0.5 }]);' . "\n"
		. 'var circle = M.shapeKnots({ type: "ellipse", w: 100, h: 100 }), cc = M.cutKnots(circle.knots, true, [{ seg: 0, t: 0.5 }]);' . "\n"
		. 'console.log(JSON.stringify({ one: one.map(ends), atKnot: atKnot.map(ends), two: two.map(ends), circle: cc.map(ends),'
		. ' mid: two[0].knots.map(function (q) { return [r1(q.x), r1(q.y)]; }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array(6, false, 100, 50, 100, 50)), $r['one'], 'a closed square opens at the cut: four corners and the cut point twice');
	pg_assert_same(array(array(5, false, 100, 100, 100, 100)), $r['atKnot'], 'cut on a corner: that corner twice');
	pg_assert_same(array(array(4, false, 100, 50, 0, 50), array(4, false, 0, 50, 100, 50)), $r['two'], 'two cuts: two open pieces');
	pg_assert_same(array(array(100, 50), array(100, 100), array(0, 100), array(0, 50)), $r['mid'], 'the first piece runs from the first cut round to the second');
	pg_assert_same(array(array(6, false, 85.4, 14.6, 85.4, 14.6)), $r['circle'], 'a circle opens on its curve');
}

// A drag across paths: the crossings of the line with each curve, twice in
// one segment too, and the pieces they give.
function test_image_editor_scissors_line_cuts()
{
	$r = image_editor_scissors_node(
		'var sq = [k(0, 0), k(100, 0), k(100, 100), k(0, 100)];' . "\n"
		. 'var across = M.lineCuts(sq, true, [-10, 50], [110, 50]), short = M.lineCuts(sq, true, [-10, 50], [50, 50]);' . "\n"
		. 'var arch = [{ x: 0, y: 0, ix: 0, iy: 0, ox: 0, oy: -100, s: false }, { x: 100, y: 0, ix: 100, iy: -100, ox: 100, oy: 0, s: false }];' . "\n"
		. 'var twice = M.lineCuts(arch, false, [-20, -30], [120, -30]), parts = M.cutKnots(arch, false, twice);' . "\n"
		. 'var circle = M.shapeKnots({ type: "ellipse", w: 100, h: 100 }), diag = M.lineCuts(circle.knots, true, [0, 0], [100, 100]);' . "\n"
		. 'var corner = M.lineCuts(sq, true, [-10, -10], [10, 10]);' . "\n"
		. 'console.log(JSON.stringify({ across: across.map(function (c) { return [c.seg, r1(c.t)]; }), short: short.length,'
		. ' pieces: M.cutKnots(sq, true, across).map(ends), twice: twice.length, parts: parts.map(ends),'
		. ' diag: M.cutKnots(circle.knots, true, diag).map(ends), corner: corner.length, miss: M.lineCuts(sq, true, [200, 0], [300, 0]).length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array(1, 0.5), array(3, 0.5)), $r['across'], 'a line across a square crosses two sides');
	pg_assert_same(1, $r['short'], 'a line that stops inside crosses one');
	pg_assert_same(array(array(4, false, 100, 50, 0, 50), array(4, false, 0, 50, 100, 50)), $r['pieces'], 'the square falls in two');
	pg_assert_same(2, $r['twice'], 'an arch crossed twice in one segment');
	pg_assert_same(3, count($r['parts']), 'gives three pieces');
	pg_assert_same(array(-30, -30), array($r['parts'][1][3], $r['parts'][1][5]), 'the middle piece ends on the line both ways');
	pg_assert_same(array(array(4, false, 85.4, 85.4, 14.6, 14.6), array(4, false, 14.6, 14.6, 85.4, 85.4)), $r['diag'], 'a circle cut along its diagonal');
	pg_assert_same(1, $r['corner'], 'a line through a corner counts it once');
	pg_assert_same(0, $r['miss'], 'a line past the shape cuts nothing');
}
