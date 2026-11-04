<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's keyframes (pg_image_editor_keyframes.js and
 * the KEYS section of the model, run under Node): a layer's place, size,
 * turn and opacity at chosen times, eased between, composed with its
 * presets; setting, moving and dropping a key; the path on the stage.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/image_editor_motion_test.php';

// Between two keys the state is eased by the later key's ease; before the
// first and after the last it holds.
function test_image_editor_keyframes_between()
{
	$r = image_editor_motion_node(
		'var l = { id: "a", type: "text", x: 10, y: 20, w: 100, h: 40, anim: { keys: [' . "\n"
		. ' { t: 1000, x: 0, y: 0, scale: 1, rot: 0, opacity: 1, ease: "linear" },' . "\n"
		. ' { t: 3000, x: 200, y: -100, scale: 2, rot: 90, opacity: 0.5, ease: "linear" },' . "\n"
		. ' { t: 4000, x: 200, y: -100, scale: 2, rot: 90, opacity: 0, ease: "in" } ] } };' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. ' before: s(M.motionAt(l, 0, 5000)), mid: s(M.motionAt(l, 2000, 5000)), quarter: s(M.motionAt(l, 1500, 5000)),' . "\n"
		. ' eased: r(M.keyAt(l, 3500).opacity), after: s(M.motionAt(l, 4800, 5000)), rest: s(M.motionAt(l, null, 5000)),' . "\n"
		. ' none: M.keyAt({ anim: { in: { kind: "fade" } } }, 100), moves: [M.hasMotion({ layers: [l] }), M.hasMotion({ layers: [{ anim: { keys: [] } }] })]' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('o' => 1, 'x' => 0, 'y' => 0, 'k' => 1, 'rot' => 0, 'clip' => null, 'draw' => 1, 'chars' => null), $r['before'], 'held before the first key');
	pg_assert_same(array('o' => 0.75, 'x' => 100, 'y' => -50, 'k' => 1.5, 'rot' => 45, 'clip' => null, 'draw' => 1, 'chars' => null), $r['mid'], 'halfway');
	pg_assert_same(array(50, -25), array($r['quarter']['x'], $r['quarter']['y']), 'a quarter of the way');
	pg_assert_same(0.438, $r['eased'], 'the later key eases in (cubic)');
	pg_assert_same(0, $r['after']['o'], 'held after the last key');
	pg_assert_same(1, $r['rest']['o'], 'a null playhead is the rest state');
	pg_assert_same(0, $r['rest']['x'], 'at rest no offset');
	pg_assert_same(null, $r['none'], 'no keys, no key state');
	pg_assert_same(array(true, false), $r['moves'], 'keys make the layer move');
}

// Keys compose with the presets: offsets add, scales and opacities
// multiply.
function test_image_editor_keyframes_compose()
{
	$r = image_editor_motion_node(
		'var l = { type: "text", x: 0, y: 0, w: 100, h: 40, anim: { in: { kind: "fade", ms: 1000, delay: 0, ease: "linear" }, keys: [' . "\n"
		. ' { t: 0, x: 0, y: 0, scale: 1, rot: 0, opacity: 1 }, { t: 1000, x: 40, y: 0, scale: 2, rot: 0, opacity: 0.5, ease: "linear" } ] } };' . "\n"
		. 'console.log(JSON.stringify(s(M.motionAt(l, 500, 5000))));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0.375, 20, 1.5), array($r['o'], $r['x'], $r['k']), 'fade times key opacity, key offset and scale');
}

// Setting a key: the first one away from 0 brings a key at rest at 0; a
// key near the time is changed, not doubled; times round to 10 ms and stay
// inside the animation.
function test_image_editor_keyframes_set_move_drop()
{
	$r = image_editor_motion_node(
		'var l = { type: "sticker", x: 0, y: 0, w: 50, h: 50 };' . "\n"
		. 'var k1 = M.keySet(l, 1503, { x: 30 }, 5000);' . "\n"
		. 'var l2 = { type: "sticker", x: 0, y: 0, w: 50, h: 50, anim: { keys: k1 } };' . "\n"
		. 'var k2 = M.keySet(l2, 1500, { y: 12 }, 5000), k3 = M.keySet(l2, 3000, {}, 5000), k4 = M.keySet(l2, 9000, { rot: 10 }, 5000);' . "\n"
		. 'var l3 = { anim: { keys: k3 } };' . "\n"
		. 'function times(ks) { return ks.map(function (k) { return k.t; }); }' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. ' k1: k1, k2: k2[1], k3: [times(k3), k3[2].x], k4: times(k4), zero: M.keySet(l, 0, { x: 5 }, 5000),' . "\n"
		. ' at: [M.keyIndex(k3, 1504), M.keyIndex(k3, 1520), M.keyIndex(k3, 3000)],' . "\n"
		. ' moved: times(M.keyMove(k3, 3000, 2004, 5000)), over: times(M.keyMove(k3, 3000, 1500, 5000)), far: times(M.keyMove(k3, 1500, -50, 5000)),' . "\n"
		. ' dropped: times(M.keyDrop(k3, 1500)), clean: M.keyList({ anim: { keys: [{ t: "300", x: "4", scale: -2, opacity: 3, ease: "nope" }, { t: 100 }, "x"] } })' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(
		array('t' => 0, 'x' => 0, 'y' => 0, 'scale' => 1, 'rot' => 0, 'opacity' => 1, 'ease' => 'in-out'),
		array('t' => 1500, 'x' => 30, 'y' => 0, 'scale' => 1, 'rot' => 0, 'opacity' => 1, 'ease' => 'in-out')
	), $r['k1'], 'a key at rest at 0, then the new one');
	pg_assert_same(array('t' => 1500, 'x' => 30, 'y' => 12, 'scale' => 1, 'rot' => 0, 'opacity' => 1, 'ease' => 'in-out'), $r['k2'], 'the same key changed');
	pg_assert_same(array(array(0, 1500, 3000), 30), $r['k3'], 'a new key keeps the state it adds at');
	pg_assert_same(array(0, 1500, 5000), $r['k4'], 'clamped to the end');
	pg_assert_same(array(array('t' => 0, 'x' => 5, 'y' => 0, 'scale' => 1, 'rot' => 0, 'opacity' => 1, 'ease' => 'in-out')), $r['zero'], 'a key at 0 alone');
	pg_assert_same(array(1, -1, 2), $r['at'], 'a key within 5 ms');
	pg_assert_same(array(0, 1500, 2000), $r['moved'], 'moved and rounded');
	pg_assert_same(array(0, 1500), $r['over'], 'moved onto another replaces it');
	pg_assert_same(array(0, 3000), $r['far'], 'clamped to 0, replacing the key there');
	pg_assert_same(array(0, 3000), $r['dropped'], 'dropped');
	pg_assert_same(array(
		array('t' => 100, 'x' => 0, 'y' => 0, 'scale' => 1, 'rot' => 0, 'opacity' => 1, 'ease' => 'in-out'),
		array('t' => 300, 'x' => 4, 'y' => 0, 'scale' => 0.05, 'rot' => 0, 'opacity' => 1, 'ease' => 'in-out')
	), $r['clean'], 'sorted, numbers, limits, defaults');
}

// The path: the centre at each key and sampled between.
function test_image_editor_keyframes_path()
{
	$r = image_editor_motion_node(
		'var l = { x: 10, y: 10, w: 20, h: 20, anim: { keys: [{ t: 0, x: 0, y: 0 }, { t: 1000, x: 100, y: 0, ease: "linear" }, { t: 2000, x: 100, y: 50, ease: "linear" }] } };' . "\n"
		. 'var p = M.keyPath(l, 4);' . "\n"
		. 'console.log(JSON.stringify({ dots: p.dots, line: p.line, none: M.keyPath({ x: 0, y: 0, w: 1, h: 1 }, 4) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array(20, 20, 0), array(120, 20, 1000), array(120, 70, 2000)), $r['dots'], 'a dot at each key');
	pg_assert_same(array(array(20, 20), array(70, 20), array(120, 20), array(120, 45), array(120, 70)), $r['line'], 'the line sampled');
	pg_assert_same(null, $r['none'], 'no keys, no path');
}
