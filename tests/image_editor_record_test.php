<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's recorded moves (pg_image_editor_record.js
 * and the RECORD section of the model, run under Node): a drag sampled
 * while the clock runs, thinned into linear keyframes, replacing the keys
 * of the recorded span, lengthening the document, at a recording speed.
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

// A wavy drag sampled every frame for 1.5 s: far fewer keys, every sample
// (its time on the keys' 10 ms grid) within 1 px of the keyed path within
// a frame of its time, the keys at
// the samples' own times, the ends kept, linear between.
function test_image_editor_record_thin()
{
	$r = image_editor_motion_node(
		'var samples = [], i, t, kept, worst = 0, times = {}, onTimes = true;' . "\n"
		. 'for (i = 0; i <= 90; i++) { t = 1000 + Math.round(i * 1000 / 60); samples.push({ t: t, x: i * 4, y: 60 * Math.sin(i / 12) }); times[Math.round(t / 10) * 10] = 1; }' . "\n"
		. 'kept = M.recordThin(samples);' . "\n"
		. 'var l = { x: 0, y: 0, w: 10, h: 10, anim: { keys: kept } };' . "\n"
		. 'samples.forEach(function (s) { var best = Infinity, d; for (d = -17; d <= 17; d++) { var k = M.keyAt(l, Math.round(s.t / 10) * 10 + d); best = Math.min(best, Math.hypot(k.x - s.x, k.y - s.y)); } worst = Math.max(worst, best); });' . "\n"
		. 'kept.forEach(function (k) { onTimes = onTimes && !!times[k.t]; });' . "\n"
		. 'console.log(JSON.stringify({ n: kept.length, worst: worst <= 1.05, onTimes: onTimes, first: [kept[0].t, kept[0].x], last: [kept[kept.length - 1].t, r(kept[kept.length - 1].x)],' . "\n"
		. ' ease: kept.every(function (k) { return k.ease === "linear"; }), line: M.recordThin([{ t: 0, x: 0, y: 0 }, { t: 500, x: 50, y: 0 }, { t: 1000, x: 100, y: 0 }]).length,' . "\n"
		. ' speed: M.recordThin([{ t: 0, x: 0, y: 0 }, { t: 100, x: 90, y: 0 }, { t: 1000, x: 100, y: 0 }]).length, empty: M.recordThin([]).length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true(($r['n'] >= 3) && ($r['n'] < 30), 'thinned to a few keys (' . $r['n'] . ')');
	pg_assert_true($r['worst'], 'every sample within 1 px of the path within a frame');
	pg_assert_true($r['onTimes'], 'keys sit at the samples\' times');
	pg_assert_same(array(1000, 0), $r['first'], 'the first sample kept');
	pg_assert_same(array(2500, 360), $r['last'], 'the last sample kept');
	pg_assert_true($r['ease'], 'linear between recorded keys');
	pg_assert_same(2, $r['line'], 'an even straight move is its two ends');
	pg_assert_same(3, $r['speed'], 'a change of speed on a straight line is kept');
	pg_assert_same(0, $r['empty'], 'nothing recorded, no keys');
}

// The recorded span's keys are replaced, the ones before and after stay,
// and what was not recorded (size, turn, opacity) follows the old keys.
function test_image_editor_record_apply_span()
{
	$r = image_editor_motion_node(
		'var l = { id: "a", x: 0, y: 0, w: 10, h: 10, anim: { keys: [' . "\n"
		. ' { t: 0, x: 0, y: 0, scale: 1, rot: 0, opacity: 1 }, { t: 1500, x: 5, y: 5, scale: 2, rot: 0, opacity: 1, ease: "linear" },' . "\n"
		. ' { t: 4000, x: 9, y: 9, scale: 2, rot: 0, opacity: 0.5, ease: "in" } ] } };' . "\n"
		. 'var out = M.recordApply(l, [{ t: 1000, x: 0, y: 0 }, { t: 2000, x: 100, y: 0 }], 5000);' . "\n"
		. 'console.log(JSON.stringify({ times: out.keys.map(function (k) { return k.t; }), end: out.end, scale: r(out.keys[1].scale),' . "\n"
		. ' after: [out.keys[3].x, out.keys[3].ease], second: [out.keys[2].x, out.keys[2].ease], none: M.recordApply(l, [], 5000).keys.length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 1000, 2000, 4000), $r['times'], 'the key at 1.5 s replaced, 0 s and 4 s kept');
	pg_assert_same(1.667, $r['scale'], 'size taken from the old keys at that moment');
	pg_assert_same(array(9, 'in'), $r['after'], 'the key after the span unchanged');
	pg_assert_same(array(100, 'linear'), $r['second'], 'the recorded place');
	pg_assert_same(3, $r['none'], 'an empty recording keeps the keys');
}

// A recording past the end lengthens the document, up to the longest
// animation it may have; samples past that are left out.
function test_image_editor_record_lengthens()
{
	$r = image_editor_motion_node(
		'var l = { id: "a", x: 0, y: 0, w: 10, h: 10 };' . "\n"
		. 'var a = M.recordApply(l, [{ t: 3000, x: 0, y: 0 }, { t: 6420, x: 80, y: 0 }], 5000);' . "\n"
		. 'var b = M.recordApply(l, [{ t: 3000, x: 0, y: 0 }, { t: 4000, x: 80, y: 0 }], 5000);' . "\n"
		. 'var c = M.recordApply(l, [{ t: 59000, x: 0, y: 0 }, { t: 60000, x: 10, y: 0 }, { t: 61000, x: 80, y: 0 }], 5000);' . "\n"
		. 'console.log(JSON.stringify({ a: a.end, b: b.end, c: c.end, cLast: c.keys[c.keys.length - 1].t, max: M.MOTION_MAX }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(6500, $r['a'], 'lengthened to the next tenth of a second past the last sample');
	pg_assert_same(5000, $r['b'], 'a recording inside the document keeps its length');
	pg_assert_same($r['max'], $r['c'], 'no longer than the longest animation');
	pg_assert_same($r['max'], $r['cLast'], 'samples past the longest left out');
}

// The clock runs at the recording speed: a drag recorded at 0.5x plays
// back twice as fast, at 2x half as fast.
function test_image_editor_record_speed()
{
	$r = image_editor_motion_node(
		'function rec(rate) { var s = [], ms; for (ms = 0; ms <= 2000; ms += 100) { s.push({ t: M.recordClock(1000, ms, rate), x: ms / 10, y: 0 }); } return M.recordApply({ id: "a", x: 0, y: 0, w: 10, h: 10 }, s, 10000); }' . "\n"
		. 'var half = rec(0.5), one = rec(1), two = rec(2), lh = { x: 0, y: 0, w: 10, h: 10, anim: { keys: half.keys } };' . "\n"
		. 'console.log(JSON.stringify({ half: [half.keys[0].t, half.keys[half.keys.length - 1].t], one: one.keys[one.keys.length - 1].t, two: two.keys[two.keys.length - 1].t,' . "\n"
		. ' mid: r(M.keyAt(lh, 1500).x), rates: M.RECORD_RATES, odd: M.recordClock(0, 1000, 3) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(1000, 2000), $r['half'], '2 s of dragging at 0.5x is 1 s of animation');
	pg_assert_same(3000, $r['one'], 'at 1x as long as the drag');
	pg_assert_same(5000, $r['two'], 'at 2x twice as long');
	pg_assert_same(100, $r['mid'], 'halfway through the second the layer is halfway');
	pg_assert_same(array(0.5, 1, 2), $r['rates'], 'the speeds offered');
	pg_assert_same(1000, $r['odd'], 'an unknown speed runs at 1x');
}
