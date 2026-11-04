<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's warp (the WARP section of
 * assets/js/pg_image_editor_model.js, run under Node): where each preset
 * takes the corners and the middle points of a box, the envelope mesh,
 * which layers warp, and Expand against the drawn outline.
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
function image_editor_warp_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiewarp');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var r1 = function (v) { return Math.round(v * 10) / 10; };' . "\n"
		// The named points of a 200 x 100 box under a warp, rounded.
		. 'var at = function (wp) { var o = {}; [["tl", 0, 0], ["tm", 0.5, 0], ["tr", 1, 0], ["ml", 0, 0.5], ["c", 0.5, 0.5], ["mr", 1, 0.5],'
		. ' ["bl", 0, 1], ["bm", 0.5, 1], ["br", 1, 1], ["q1", 0.25, 0], ["q3", 0.75, 0], ["qc", 0.25, 0.5]].forEach(function (n) {'
		. ' o[n[0]] = M.warpPoint(wp, 200, 100, n[1], n[2]).map(r1); }); return o; };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Each preset at bend 0.5 on a 200 x 100 box: the corners, the edges'
// middles and the centre go where its formula puts them.
function test_image_editor_warp_presets()
{
	$r = image_editor_warp_node(
		'var o = {}; M.WARP_STYLES.forEach(function (s) { o[s] = at({ style: s, bend: 0.5 }); });' . "\n"
		. 'o.flat = at({ style: "bulge", bend: 0 }); o.hdist = at({ style: "bulge", bend: 0, h: 0.5 }); o.vdist = at({ style: "bulge", bend: 0, v: 0.5 });' . "\n"
		. 'o.down = at({ style: "arc", bend: -0.5 });' . "\n"
		. 'console.log(JSON.stringify(o));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	$still = function ($o, $names, $style) {
		$home = array('tl' => array(0, 0), 'tm' => array(100, 0), 'tr' => array(200, 0), 'ml' => array(0, 50), 'c' => array(100, 50),
			'mr' => array(200, 50), 'bl' => array(0, 100), 'bm' => array(100, 100), 'br' => array(200, 100));

		foreach ($names as $n) {
			pg_assert_same($home[$n], $o[$n], $style . ': ' . $n . ' stays');
		}
	};

	$still($r['flat'], array('tl', 'tm', 'tr', 'ml', 'c', 'mr', 'bl', 'bm', 'br'), 'no bend');

	// Arc: the middle line becomes a quarter circle (R = 200 / (pi / 2)).
	$still($r['arc'], array('tm', 'c', 'bm'), 'arc');
	pg_assert_same(array(-25.4, 51.9), $r['arc']['tl'], 'arc: the top left corner goes out and down');
	pg_assert_same(array(225.4, 51.9), $r['arc']['tr'], 'arc: the top right mirrors it');
	pg_assert_same(array(45.3, 122.6), $r['arc']['bl'], 'arc: the bottom left comes in');
	pg_assert_same(array(-25.4, 48.1), $r['down']['bl'], 'arc: a negative bend curves the other way');

	// Arch: everything lifts by a dome over x.
	$still($r['arch'], array('tl', 'tr', 'ml', 'mr', 'bl', 'br'), 'arch');
	pg_assert_same(array(100, -25), $r['arch']['tm'], 'arch: the top middle lifts by a quarter of the height');
	pg_assert_same(array(100, 25), $r['arch']['c'], 'arch: the centre lifts as much');
	pg_assert_same(array(100, 75), $r['arch']['bm'], 'arch: and the bottom middle');

	// Bulge: the top goes up and the bottom down in the middle.
	$still($r['bulge'], array('tl', 'tr', 'ml', 'c', 'mr', 'bl', 'br'), 'bulge');
	pg_assert_same(array(100, -25), $r['bulge']['tm'], 'bulge: the top middle goes up');
	pg_assert_same(array(100, 125), $r['bulge']['bm'], 'bulge: the bottom middle goes down');

	// Flag: one sine wave along x, the same for every row.
	$still($r['flag'], array('tl', 'tm', 'tr', 'ml', 'c', 'mr', 'bl', 'bm', 'br'), 'flag');
	pg_assert_same(array(50, 12.5), $r['flag']['q1'], 'flag: a quarter along goes down');
	pg_assert_same(array(150, -12.5), $r['flag']['q3'], 'flag: three quarters along goes up');

	// Wave: the sine runs across the diagonal.
	$still($r['wave'], array('tl', 'tm', 'tr', 'c', 'bl', 'bm', 'br'), 'wave');
	pg_assert_same(array(50, 62.5), $r['wave']['qc'], 'wave: a quarter along the middle row goes down');
	pg_assert_same(array(50, -12.5), $r['wave']['q1'], 'wave: a quarter along the top goes up');

	// Fish: a body on the left, a tail on the right.
	$still($r['fish'], array('tl', 'tm', 'tr', 'ml', 'c', 'mr', 'bl', 'bm', 'br'), 'fish');
	pg_assert_same(array(50, -12.5), $r['fish']['q1'], 'fish: the body grows');
	pg_assert_same(array(150, 12.5), $r['fish']['q3'], 'fish: the tail narrows');

	// Rise: the right side lifts, the left sinks.
	$still($r['rise'], array('tm', 'c', 'bm'), 'rise');
	pg_assert_same(array(200, -25), $r['rise']['tr'], 'rise: the top right lifts');
	pg_assert_same(array(0, 25), $r['rise']['tl'], 'rise: the top left sinks');
	pg_assert_same(array(200, 25), $r['rise']['mr'], 'rise: the right middle lifts as much');

	// Squeeze: the middle row narrows.
	$still($r['squeeze'], array('tl', 'tm', 'tr', 'c', 'bl', 'bm', 'br'), 'squeeze');
	pg_assert_same(array(25, 50), $r['squeeze']['ml'], 'squeeze: the left middle comes in');
	pg_assert_same(array(175, 50), $r['squeeze']['mr'], 'squeeze: the right middle comes in');

	// Twist: turns most at the centre, not at the corners.
	$still($r['twist'], array('tl', 'tr', 'c', 'bl', 'br'), 'twist');
	pg_assert_same(array(189.6, 94.4), $r['twist']['mr'], 'twist: the right middle turns by 0.29 of a half turn');

	// Distortion: one side taller (horizontal) or wider (vertical).
	pg_assert_same(array(0, 12.5), $r['hdist']['tl'], 'horizontal distortion: the left side shrinks');
	pg_assert_same(array(200, -12.5), $r['hdist']['tr'], 'horizontal distortion: the right side grows');
	pg_assert_same(array(25, 0), $r['vdist']['tl'], 'vertical distortion: the top narrows');
	pg_assert_same(array(-25, 100), $r['vdist']['bl'], 'vertical distortion: the bottom widens');
}

// The envelope mesh: an even mesh changes nothing, the curve runs through a
// moved point, a resized mesh keeps the shape, its size stays 3 to 6.
function test_image_editor_warp_mesh()
{
	$r = image_editor_warp_node(
		'var even = M.meshGrid(3, 3), moved = M.meshGrid(3, 3); moved.pts[4] = [0.5, 0.2];' . "\n"
		. 'var big = M.meshResize(moved, 5, 5), redo = M.meshResize(even, 4, 6);' . "\n"
		. 'console.log(JSON.stringify({ even: M.warpPoint(even, 200, 100, 0.3, 0.7).map(r1), moved: at(moved), big: [big.cols, big.rows, big.pts.length, big.pts[12], big.pts[0]],'
		. ' bigAt: M.warpPoint(big, 200, 100, 0.4, 0.3).map(r1), movedAt: M.warpPoint(moved, 200, 100, 0.4, 0.3).map(r1),'
		. ' redo: [redo.cols, redo.rows, redo.pts[5]], clamp: [M.meshGrid(1, 9).cols, M.meshGrid(1, 9).rows],'
		. ' on: M.warpOn({ type: "rect", w: 10, h: 10, warp: even }), bad: M.warpOn({ type: "rect", w: 10, h: 10, warp: { style: "mesh", cols: 3, rows: 3, pts: [] } }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(60, 70), $r['even'], 'an even mesh changes nothing');
	pg_assert_same(array(100, 20), $r['moved']['c'], 'the curve runs through the moved point');
	pg_assert_same(array(0, 0), $r['moved']['tl'], 'the corners stay');
	pg_assert_same(array(100, 0), $r['moved']['tm'], 'the other points stay');
	pg_assert_same(array(5, 5, 25), array_slice($r['big'], 0, 3), 'a 5 x 5 mesh');
	pg_assert_same(array(0.5, 0.2), $r['big'][3], 'its middle point is the moved one');
	pg_assert_same(array(0, 0), $r['big'][4], 'its corner stays');
	pg_assert_true(abs($r['bigAt'][0] - $r['movedAt'][0]) < 2 && abs($r['bigAt'][1] - $r['movedAt'][1]) < 2, 'the resized mesh keeps the shape');
	pg_assert_same(array(4, 6, array(0.33333, 0.2)), $r['redo'], 'an even mesh resized stays even');
	pg_assert_same(array(3, 6), $r['clamp'], 'a mesh has 3 to 6 points each way');
	pg_assert_true($r['on'], 'a valid mesh warps');
	pg_assert_false($r['bad'], 'a mesh without its points does not');
}

// Paths and shapes warp, lines, arrows and texts do not; a warped layer
// draws and hits its warped outline.
function test_image_editor_warp_layers()
{
	$r = image_editor_warp_node(
		'var k = function (x, y) { return { x: x, y: y, ix: x, iy: y, ox: x, oy: y, s: false }; };' . "\n"
		. 'var rect = { type: "rect", x: 0, y: 0, w: 200, h: 100, stroke: 2, color: "#000000", warp: { style: "bulge", bend: 0.5 } };' . "\n"
		. 'var path = { type: "path", x: 0, y: 0, w: 200, h: 100, knots: [k(0, 0.5), k(1, 0.5)], closed: false, warp: { style: "arch", bend: 0.5 } };' . "\n"
		. 'var d = M.shapePath(rect).d, out = M.outline(rect)[0], po = M.outline(path)[0];' . "\n"
		. 'console.log(JSON.stringify({ kinds: [M.warpable(rect), M.warpable(path), M.warpable({ type: "line" }), M.warpable({ type: "arrow" }), M.warpable({ type: "text" }), M.warpable({ type: "ellipse" })],'
		. ' poly: /^M[^C]+Z$/.test(d), first: out[0], closedEnd: [out[0][0] === out[out.length - 1][0], out[0][1] === out[out.length - 1][1]], many: out.length > 40,'
		. ' hitTop: M.hitShape(rect, [100, -25], 1), missOld: M.hitShape(rect, [100, 0], 1), pathMid: po[Math.floor(po.length / 2)].map(r1), pathEnds: [po[0], po[po.length - 1]],'
		. ' plain: M.shapePath({ type: "rect", w: 10, h: 10 }).d, none: M.warpOn({ type: "rect", w: 10, h: 10, warp: { style: "spiral" } }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(true, true, false, false, false, true), $r['kinds'], 'paths and shapes warp; lines, arrows and texts do not');
	pg_assert_true($r['poly'], 'a warped shape draws as a flattened outline');
	pg_assert_same(array(0, 0), $r['first'], 'its first point is the box corner');
	pg_assert_same(array(true, true), $r['closedEnd'], 'a closed outline ends on its first point');
	pg_assert_true($r['many'], 'straight sides are flattened into short steps');
	pg_assert_true($r['hitTop'], 'a warped shape hits on its warped top');
	pg_assert_false($r['missOld'], 'and not on its old one');
	pg_assert_same(array(100, 25), $r['pathMid'], 'a straight path arches in the middle');
	pg_assert_same(array(array(0, 50), array(200, 50)), $r['pathEnds'], 'its ends stay');
	pg_assert_same('M0 0H10V10H0Z', $r['plain'], 'a layer without a warp draws as before');
	pg_assert_false($r['none'], 'an unknown style does not warp');
}

// Expand: the path it makes draws the same points, in the picture's space,
// as the warped layer it replaces, turned and mirrored too.
function test_image_editor_warp_expand()
{
	$r = image_editor_warp_node(
		'var world = function (l, pts) { return pts.map(function (p) { return M.drawnPoint(l, p[0], p[1]); }); };' . "\n"
		. 'var gap = function (l) { var e = Object.assign({}, l, M.warpExpand(l)); delete e.warp; var a = world(l, M.warpOutline(l)[0]), ek = M.pathKnots(e), b = world(e, ek.map(function (q) { return [q.x, q.y]; }));'
		. ' if (e.closed) { b.push(b[0]); } var most = 0; a.forEach(function (p, i) { most = Math.max(most, Math.hypot(p[0] - b[i][0], p[1] - b[i][1])); });'
		. ' return { n: [a.length, b.length], most: most < 0.02, type: e.type, closed: e.closed, rot: e.rot, mirror: !!e.mirror }; };' . "\n"
		. 'var rect = { type: "rect", x: 10, y: 20, w: 200, h: 100, rot: 30, warp: { style: "arc", bend: 0.5 } };' . "\n"
		. 'var ell = { type: "ellipse", x: 40, y: 5, w: 120, h: 80, rot: -15, mirror: true, warp: { style: "twist", bend: -0.4, h: 0.2 } };' . "\n"
		. 'var mesh = M.meshGrid(4, 4); mesh.pts[5] = [0.5, -0.2];' . "\n"
		. 'var star = { type: "star", x: 0, y: 0, w: 90, h: 90, points: 5, warp: mesh };' . "\n"
		. 'var kn = function (x, y) { return { x: x, y: y, ix: x, iy: y, ox: x, oy: y, s: false }; };' . "\n"
		. 'var open = { type: "path", x: 5, y: 5, w: 100, h: 10, rot: 90, knots: [kn(0, 0.5), kn(1, 0.5)], closed: false, warp: { style: "flag", bend: 1 } };' . "\n"
		. 'var subs = { type: "path", x: 0, y: 0, w: 100, h: 100, closed: true, knots: [kn(0, 0), kn(1, 0), kn(1, 1), kn(0, 1)], subs: [{ knots: [kn(0.25, 0.25), kn(0.75, 0.25), kn(0.75, 0.75)], closed: true }], warp: { style: "bulge", bend: 0.3 } };' . "\n"
		. 'var sx = M.warpExpand(subs);' . "\n"
		. 'console.log(JSON.stringify({ rect: gap(rect), ell: gap(ell), star: gap(star), open: gap(open), subs: [sx.subs.length, sx.subs[0].closed, sx.subs[0].knots.length > 3, M.warpOutline(subs).length] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach (array('rect' => 30, 'ell' => -15, 'star' => null, 'open' => 90) as $name => $rot) {
		$g = $r[$name];

		pg_assert_same($g['n'][0], $g['n'][1], $name . ': as many points as drawn');
		pg_assert_true($g['most'], $name . ': every point where it was drawn');
		pg_assert_same('path', $g['type'], $name . ': becomes a path');
		pg_assert_same($rot, isset($g['rot']) ? $g['rot'] : null, $name . ': keeps its angle');
	}

	pg_assert_same(true, $r['rect']['closed'], 'a closed shape stays closed');
	pg_assert_same(false, $r['open']['closed'], 'an open path stays open');
	pg_assert_true($r['ell']['mirror'], 'the mirror is kept');
	pg_assert_same(array(1, true, true, 2), $r['subs'], 'outlined letters keep their other contours');
}
