<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's free deformation (the PUPPET section of
 * assets/js/pg_image_editor_model.js, run under Node): rigid moving least
 * squares on the pins, a pin added to a bent shape, which layers take pins,
 * and Expand baking the bent outline.
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
function image_editor_puppet_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiepup');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		// A 200 x 100 rectangle with the given pins, and its outline points.
		. 'var rect = function (pins) { var l = { id: "r", type: "rect", x: 10, y: 20, w: 200, h: 100, radius: 0 }; if (pins) { l.puppet = { pins: pins }; } return l; };' . "\n"
		. 'var pts = function (l) { var o = []; M.warpContours(l).forEach(function (c) { o = o.concat(c.pts); }); return o; };' . "\n"
		// The largest distance between two equally long point lists.
		. 'var gap = function (a, b) { var m = 0; a.forEach(function (p, i) { m = Math.max(m, Math.hypot(p[0] - b[i][0], p[1] - b[i][1])); }); return Math.round(m * 1e4) / 1e4; };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// One pin moves the whole shape by its own move; a pin left at rest
// changes nothing.
function test_image_editor_puppet_one_pin()
{
	$r = image_editor_puppet_node(
		'var bare = pts(rect()), moved = pts(rect([{ rest: [0.5, 0.5], at: [0.6, 0.7] }])), still = pts(rect([{ rest: [0.3, 0.2], at: [0.3, 0.2] }]));' . "\n"
		. 'var shifted = bare.map(function (p) { return [p[0] + 20, p[1] + 20]; });' . "\n"
		. 'console.log(JSON.stringify({ n: bare.length, shift: gap(moved, shifted), still: gap(still, bare) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['n'] > 8, 'the outline is flattened');
	pg_assert_same(0, $r['shift'], 'one pin moved by 20, 20 moves every point by 20, 20');
	pg_assert_same(0, $r['still'], 'one pin at rest keeps the shape');
}

// Two pins: at rest the shape stays; one dragged, the other stays where
// it is and the dragged one goes where it was dragged; both turned about
// the middle turn the shape rigidly, every point keeping its distance from
// the middle.
function test_image_editor_puppet_two_pins()
{
	$r = image_editor_puppet_node(
		'var bare = pts(rect()), rest = pts(rect([{ rest: [0.2, 0.5], at: [0.2, 0.5] }, { rest: [0.8, 0.5], at: [0.8, 0.5] }]));' . "\n"
		. 'var l = rect([{ rest: [0.2, 0.5], at: [0.2, 0.5] }, { rest: [0.8, 0.5], at: [0.8, 0.9] }]), pins = M.puppetPins(l);' . "\n"
		. 'var keep = M.mlsRigid(pins, [40, 50]), drag = M.mlsRigid(pins, [160, 50]);' . "\n"
		// Both pins turned a quarter about the middle (100, 50), in parts of the box.
		. 'var turned = rect([{ rest: [0.2, 0.5], at: [0.5, 0.5 - 0.6] }, { rest: [0.8, 0.5], at: [0.5, 0.5 + 0.6] }]);' . "\n"
		. 'var radii = function (list) { return list.map(function (p) { return Math.round(Math.hypot(p[0] - 100, p[1] - 50) * 1e3) / 1e3; }); };' . "\n"
		. 'console.log(JSON.stringify({ rest: gap(rest, bare), keep: keep.map(Math.round), drag: drag.map(Math.round),'
		. ' same: JSON.stringify(radii(pts(turned))) === JSON.stringify(radii(bare)), moved: gap(pts(turned), bare) > 1 }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(0, $r['rest'], 'two pins at rest keep the shape');
	pg_assert_same(array(40, 50), $r['keep'], 'the pin left at rest stays');
	pg_assert_same(array(160, 90), $r['drag'], 'the dragged pin goes where it was dragged');
	pg_assert_true($r['same'], 'pins turned about the middle keep every point as far from the middle');
	pg_assert_true($r['moved'], 'and the shape turns');
}

// A symmetric pull gives a symmetric shape: pins mirrored about the box's
// middle and moved in mirror take mirrored points to mirrored places.
function test_image_editor_puppet_symmetric()
{
	$r = image_editor_puppet_node(
		'var l = rect([{ rest: [0.2, 0.3], at: [0.05, 0.1] }, { rest: [0.8, 0.3], at: [0.95, 0.1] }, { rest: [0.5, 0.8], at: [0.5, 0.8] }]);' . "\n"
		. 'var pins = M.puppetPins(l), worst = 0, x, y, a, b;' . "\n"
		. 'for (x = 0; x <= 200; x += 12.5) { for (y = 0; y <= 100; y += 12.5) {'
		. ' a = M.mlsRigid(pins, [x, y]); b = M.mlsRigid(pins, [200 - x, y]);'
		. ' worst = Math.max(worst, Math.abs(a[0] - (200 - b[0])), Math.abs(a[1] - b[1])); } }' . "\n"
		. 'var out = pts(l), xs = out.map(function (p) { return p[0]; });' . "\n"
		. 'var mid = (Math.min.apply(null, xs) + Math.max.apply(null, xs)) / 2;' . "\n"
		. 'console.log(JSON.stringify({ worst: Math.round(worst * 1e6) / 1e6, mid: Math.round(mid * 1e3) / 1e3 }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(0, $r['worst'], 'mirrored points go to mirrored places');
	pg_assert_same(100, $r['mid'], 'the bent outline stays centred on the middle');
}

// A pin added to a bent shape sits where it was pressed, and adding it
// leaves its place where it was; past the limit nothing is added.
function test_image_editor_puppet_add()
{
	$r = image_editor_puppet_node(
		'var l = rect([{ rest: [0.2, 0.5], at: [0.2, 0.5] }, { rest: [0.8, 0.5], at: [0.85, 0.8] }]);' . "\n"
		. 'var pp = M.puppetAdd(l, 0.6, 0.4), pin = pp.pins[2], back = M.mlsRigid(M.puppetPins(l), [pin.rest[0] * 200, pin.rest[1] * 100]);' . "\n"
		. 'var full = rect([]); full.puppet.pins = []; for (var i = 0; i < M.PUPPET_MAX; i++) { full.puppet.pins.push({ rest: [i / 40, 0], at: [i / 40, 0] }); }' . "\n"
		. 'console.log(JSON.stringify({ n: pp.pins.length, at: pin.at, back: back.map(function (v) { return Math.round(v * 10) / 10; }),'
		. ' fresh: M.puppetAdd(rect(), 0.5, 0.5).pins, full: M.puppetAdd(full, 0.5, 0.5).pins.length,'
		. ' hit: M.puppetHit(l, 171, 79, 6), miss: M.puppetHit(l, 100, 50, 6) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(3, $r['n'], 'a pin is added');
	pg_assert_same(array(0.6, 0.4), $r['at'], 'it is drawn where it was pressed');
	pg_assert_same(array(120, 40), $r['back'], 'its rest place is bent back to the pressed point');
	pg_assert_same(array(array('rest' => array(0.5, 0.5), 'at' => array(0.5, 0.5))), $r['fresh'], 'on an unbent shape rest and place are one');
	pg_assert_same(32, $r['full'], 'no pin past the limit');
	pg_assert_same(1, $r['hit'], 'a press near a pin finds it');
	pg_assert_same(-1, $r['miss'], 'a press away from the pins finds none');
}

// Pins apply to a path or a drawn shape with valid pins; on a warp they
// move the warped outline; Expand bakes them into the path.
function test_image_editor_puppet_layers_and_expand()
{
	$r = image_editor_puppet_node(
		'var pin = [{ rest: [0.5, 0.5], at: [0.5, 0.6] }];' . "\n"
		. 'var on = function (l) { l.puppet = { pins: pin }; return M.puppetOn(l); };' . "\n"
		. 'var l = rect([{ rest: [0.1, 0.5], at: [0.1, 0.5] }, { rest: [0.9, 0.5], at: [0.9, 0.2] }]);' . "\n"
		. 'var drawn = M.outline(l), fit = M.warpExpand(l);' . "\n"
		// The baked knots in the page against the drawn points (the layer is not turned).
		. 'var off = fit.knots.reduce(function (m, k, i) { return Math.max(m, Math.hypot(fit.x + k.x * fit.w - l.x - drawn[0][i][0], fit.y + k.y * fit.h - l.y - drawn[0][i][1])); }, 0);' . "\n"
		. 'var warped = rect(pin); warped.warp = { style: "arch", bend: 0.5 }; var arch = rect(); arch.warp = warped.warp;' . "\n"
		. 'var shifted = pts(arch).map(function (p) { return [p[0], p[1] + 10]; });' . "\n"
		. 'console.log(JSON.stringify({ rect: on({ type: "rect", w: 10, h: 10 }), path: on({ type: "path", w: 10, h: 10, knots: [{}, {}] }),'
		. ' text: on({ type: "text", w: 10, h: 10 }), arrow: on({ type: "arrow", w: 10, h: 10 }),'
		. ' none: M.puppetOn({ type: "rect", w: 10, h: 10, puppet: { pins: [] } }), bad: M.puppetOn({ type: "rect", w: 10, h: 10, puppet: { pins: [{ rest: [0] }] } }),'
		. ' shape: M.shapePath(l).d.indexOf("L") > 0, type: fit.type, knots: fit.knots.length === drawn[0].length - 1,'
		. ' onWarp: gap(pts(warped), shifted),'
		. ' same: off < 0.01 }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['rect'], 'a rectangle takes pins');
	pg_assert_true($r['path'], 'a path takes pins');
	pg_assert_false($r['text'], 'a live text does not');
	pg_assert_false($r['arrow'], 'an arrow does not');
	pg_assert_false($r['none'], 'no pins, no deformation');
	pg_assert_false($r['bad'], 'a broken pin turns it off');
	pg_assert_true($r['shape'], 'the pinned shape is drawn from its flattened outline');
	pg_assert_same('path', $r['type'], 'Expand makes a path');
	pg_assert_true($r['knots'], 'with a corner knot per drawn point');
	pg_assert_same(0, $r['onWarp'], 'pins move the warped outline');
	pg_assert_true($r['same'], 'the baked path draws the bent outline');
}

// Selecting follows the bent shape: a rectangle lifted 250 px above its
// box by one pin is hit, and caught by a selection box, where it is drawn,
// not in the box it left; a pinned path's rings for the shape builder
// follow its pins.
function test_image_editor_puppet_hit_and_rings()
{
	$r = image_editor_puppet_node(
		'var up = [{ rest: [0.5, 0.5], at: [0.5, -2] }];' . "\n"
		. 'var l = rect(up); l.fill = "solid"; var doc = { layers: [l] };' . "\n"
		. 'var shape = function (o, p, s) { return M.hitShape(o, p, s); };' . "\n"
		. 'var hit = function (x, y) { var h = M.hitLayer(doc, x, y, 0, shape); return h ? h.id : null; };' . "\n"
		. 'var mirrored = Object.assign({}, l, { mirror: true, puppet: { pins: [{ rest: [0.5, 0.5], at: [0.9, 0.5] }] } });' . "\n"
		. 'var path = { id: "p", type: "path", x: 0, y: 0, w: 100, h: 100, closed: true, fill: "solid", knots: [[0, 0], [1, 0], [1, 1], [0, 1]].map(function (k) { return { x: k[0], y: k[1], ix: k[0], iy: k[1], ox: k[0], oy: k[1], s: false }; }) };' . "\n"
		. 'var bare = M.layerRings(path)[0]; path.puppet = { pins: [{ rest: [0.5, 0.5], at: [0.7, 0.4] }] }; var bent = M.layerRings(path)[0];' . "\n"
		// The bent ring is sampled more densely: compare where it reaches.
		. 'var ext = function (r) { var xs = r.map(function (p) { return p[0]; }), ys = r.map(function (p) { return p[1]; }); return [Math.min.apply(null, xs), Math.min.apply(null, ys), Math.max.apply(null, xs), Math.max.apply(null, ys)].map(Math.round); };' . "\n"
		. 'var moved = JSON.stringify(ext(bent)) === JSON.stringify(ext(bare).map(function (n, i) { return n + ((i % 2) ? -10 : 20); }));' . "\n"
		. 'console.log(JSON.stringify({ above: hit(110, -180), inBox: hit(110, 70), nearBox: hit(110, 70 - 250 + 45),'
		. ' marquee: M.layersInBox(doc, { x: 100, y: -200, w: 5, h: 5 }),'
		. ' mirroredLeft: M.hitLayer({ layers: [mirrored] }, 5, 70, 0, shape) ? 1 : 0,'
		. ' moved: moved }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('r', $r['above'], 'a point on the shape above its box hits the layer');
	pg_assert_same(null, $r['inBox'], 'a point in the box where nothing is drawn any more misses');
	pg_assert_same('r', $r['nearBox'], 'the lifted shape hits inside its drawn edge');
	pg_assert_same(array('r'), $r['marquee'], 'a selection box over the drawn shape catches it');
	pg_assert_same(1, $r['mirroredLeft'], 'a mirrored layer bent out of its box hits where it is drawn');
	pg_assert_true($r['moved'], 'a pinned path\'s rings follow its pins');
}

// The arrow keys move a chosen pin by document pixels: as a share of the box,
// through the layer's turn and mirror; a missing pin gives null.
function test_image_editor_puppet_nudge()
{
	$r = image_editor_puppet_node(
		'var l = rect([{ rest: [20, 50], at: [0.1, 0.5] }]);' . "\n"
		. 'var turned = Object.assign(rect([{ rest: [20, 50], at: [0.1, 0.5] }]), { rot: 90 });' . "\n"
		. 'var mirrored = Object.assign(rect([{ rest: [20, 50], at: [0.1, 0.5] }]), { mirror: true });' . "\n"
		. 'var round = function (p) { return p ? p.map(function (v) { return Math.round(v * 1e4) / 1e4; }) : p; };' . "\n"
		. 'console.log(JSON.stringify({ right: M.puppetNudge(l, 0, 10, 0), down: M.puppetNudge(l, 0, 0, 1), turned: round(M.puppetNudge(turned, 0, 10, 0)),' . "\n"
		. ' mirrored: M.puppetNudge(mirrored, 0, 10, 0), none: M.puppetNudge(l, 3, 1, 0), bare: M.puppetNudge(rect(), 0, 1, 0), rest: l.puppet.pins[0].rest }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0.15, 0.5), $r['right'], '10 px right on a 200 px box');
	pg_assert_same(array(0.1, 0.51), $r['down'], '1 px down on a 100 px box');
	pg_assert_same(array(0.1, 0.4), $r['turned'], 'on a layer turned 90 degrees, right on the page is up in the box');
	pg_assert_same(array(0.05, 0.5), $r['mirrored'], 'on a mirrored layer, right on the page is left in the box');
	pg_assert_same(null, $r['none'], 'no such pin');
	pg_assert_same(null, $r['bare'], 'no pins');
	pg_assert_same(array(20, 50), $r['rest'], 'the rest stays');
}

