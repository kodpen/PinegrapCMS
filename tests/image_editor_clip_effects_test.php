<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a video clip's effects and the transition into the next clip on
 * its row (the CLIP section of the image editor's model, run under Node):
 * the effects' ranges and their colour numbers taken from the Adjust tool,
 * the clip that meets the next one, the transition's time and bounds, which
 * source time and drawing state each clip has while it runs (with either
 * clip as the upper layer), the export's frames, and a split that leaves the
 * transition on the second part.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/image_editor_clip_test.php');

// Two clips on one row: a from 1 s to 5 s, b from 5 s to 9 s (4 s of
// source each), a leading into b with the transition given.
function image_editor_clip_effects_pair($tr, $b_first = false)
{
	return 'var a = clip({ id: "a", row: "r", transition: ' . $tr . ' }), b = clip({ id: "b", row: "r", from: 8000, to: 12000, start: 5000, w: 960, h: 540 });' . "\n"
		. 'var layers = ' . ($b_first ? '[b, a]' : '[a, b]') . ';' . "\n";
}

function test_image_editor_clip_effects_values()
{
	$r = image_editor_clip_node(
		'var l = clip({ fx: { brightness: 8, contrast: 150, saturation: "25", temperature: -20.4, blur: -3, vignette: 30 } });' . "\n"
		. 'console.log(JSON.stringify({ fx: M.clipFx(l), none: M.clipFx(clip()), on: M.clipFxOn(l), off: M.clipFxOn(clip({ fx: { blur: 0 } })),' . "\n"
		. ' color: M.clipFxColor(l), same: M.colorMatrix(M.colorValues({ adjust: { brightness: 8, contrast: 100, saturation: 25, temperature: -20 } })),' . "\n"
		. ' plain: M.clipFxColor(clip()), blur: M.clipFxBlur(clip({ fx: { blur: 50 }, w: 1920, h: 1080 })), noBlur: M.clipFxBlur(clip()),' . "\n"
		. ' vig: M.clipFxVignette(l), vigSame: M.vignetteStops(30), keys: M.CLIP_FX.map(function (d) { return d[0]; }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('brightness' => 8, 'contrast' => 100, 'saturation' => 25, 'temperature' => -20, 'blur' => 0, 'vignette' => 30), $r['fx'], 'whole numbers in range');
	pg_assert_same(array('brightness' => 0, 'contrast' => 0, 'saturation' => 0, 'temperature' => 0, 'blur' => 0, 'vignette' => 0), $r['none'], 'no effects');
	pg_assert_true($r['on'], 'an effect');
	pg_assert_false($r['off'], 'zero is none');
	pg_assert_same($r['same'], $r['color'], 'the Adjust tool\'s colour numbers');
	pg_assert_same(null, $r['plain'], 'no colour step');
	pg_assert_same(10.8, $r['blur'], 'half blur: 1 % of the shorter side');
	pg_assert_same(0, $r['noBlur']);
	pg_assert_same($r['vigSame'], $r['vig'], 'the Adjust tool\'s vignette');
	pg_assert_same(array('brightness', 'contrast', 'saturation', 'temperature', 'blur', 'vignette'), $r['keys'], 'the six sliders');
}

function test_image_editor_clip_transition_meets_and_time()
{
	$r = image_editor_clip_node(
		image_editor_clip_effects_pair('{ kind: "fade", ms: 600 }')
		. 'var gap = clip({ id: "c", row: "r", start: 5500 }), long = clip({ id: "d", row: "r", transition: { kind: "wipe", ms: 9000 } }), short = clip({ id: "e", row: "r", start: 5000, from: 0, to: 300 });' . "\n"
		. 'console.log(JSON.stringify({ next: (M.clipTouching(layers, a, 1) || {}).id, prev: (M.clipTouching(layers, b, -1) || {}).id, last: M.clipTouching(layers, b, 1),' . "\n"
		. ' ms: M.clipTransitionMs(layers, a), gapMs: M.clipTransitionMs([a, gap], a), other: M.clipTouching([a, clip({ id: "x", start: 5000 })], a, 1),' . "\n"
		. ' capped: M.clipTransitionMs([long, short], long), tr: M.clipTransition(long), none: M.clipTransition(clip({ transition: { kind: "none" } })), nul: M.clipTransition(clip({ transition: null })),' . "\n"
		. ' hidden: M.clipTransitionMs([a, Object.assign({}, b, { hidden: true })], a), lastMs: M.clipTransitionMs(layers, b) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('b', $r['next'], 'the next clip starts where a ends');
	pg_assert_same('a', $r['prev'], 'and a ends where b starts');
	pg_assert_same(null, $r['last'], 'the row\'s last clip leads into nothing');
	pg_assert_same(600, $r['ms'], 'its time');
	pg_assert_same(0, $r['gapMs'], 'a gap: no transition, kept but not drawn');
	pg_assert_same(null, $r['other'], 'another row is not the next clip');
	pg_assert_same(array('kind' => 'wipe', 'ms' => 2000), $r['tr'], 'at most 2 s, as a scene\'s');
	pg_assert_same(300, $r['capped'], 'never longer than the clip it leads into');
	pg_assert_same(null, $r['none'], 'none');
	pg_assert_same(null, $r['nul'], 'null is none (a split\'s first part)');
	pg_assert_same(0, $r['hidden'], 'a hidden next clip: none');
	pg_assert_same(0, $r['lastMs'], 'the last clip: none');
}

// Halfway through a 600 ms fade at 5.3 s: a holds its last frame, b plays
// its first 0.3 s; the eased progress is 0.5.
function test_image_editor_clip_transition_midway()
{
	$r = image_editor_clip_node(
		image_editor_clip_effects_pair('{ kind: "fade", ms: 600 }')
		. 'var A = M.clipShowAt(layers, a, 5300), B = M.clipShowAt(layers, b, 5300), C = M.clipShowAt(layers, a, 5600), D = M.clipShowAt(layers, b, 5600);' . "\n"
		. 'var slide = image(\'{ kind: "slide", ms: 600 }\'), rest = M.clipShowAt(layers, a, null), before = M.clipShowAt(layers, a, 3000);' . "\n"
		. 'function image(tr) { var x = clip({ id: "a", row: "r", transition: eval("(" + tr + ")") }); return [M.clipShowAt([x, b], x, 5300), M.clipShowAt([x, b], b, 5300)]; }' . "\n"
		. 'console.log(JSON.stringify({ A: A, B: B, C: C, D: D, slide: slide, rest: rest, before: before }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(5999, $r['A']['at'], 'the clip leading out holds its last frame, to - 1');
	pg_assert_same(array('alpha' => 1, 'x' => 0, 'y' => 0, 'scale' => 1, 'clip' => null), $r['A']['state'], 'under the new one, whole');
	pg_assert_same(8300, $r['B']['at'], 'the next clip plays from its start');
	pg_assert_same(0.5, $r['B']['state']['alpha'], 'fading in, eased');
	pg_assert_same(array('at' => null, 'state' => null), $r['C'], 'after the transition the clip shows nothing');
	pg_assert_same(array('at' => 8600, 'state' => null), $r['D'], 'and the next one plays on its own');
	pg_assert_same(-960, $r['slide'][0]['state']['x'], 'a slide moves a out by half its own width');
	pg_assert_same(480, $r['slide'][1]['state']['x'], 'and b in by half its own');
	pg_assert_same(array('at' => 2000, 'state' => null), $r['rest'], 'at rest the source in');
	pg_assert_same(array('at' => 4000, 'state' => null), $r['before'], 'before the transition as before');
}

// The clip leading out as the upper layer: fade, wipe and zoom take it
// away, so the change shows either way; slide is the same.
function test_image_editor_clip_transition_layer_order()
{
	$r = image_editor_clip_node(
		'var b = clip({ id: "b", row: "r", from: 8000, to: 12000, start: 5000, w: 960, h: 540 });' . "\n"
		. 'var out = {}; ["fade", "wipe", "zoom", "slide"].forEach(function (k) { var a = clip({ id: "a", row: "r", transition: { kind: k, ms: 600 } });' . "\n"
		. ' out[k] = { under: [M.clipShowAt([a, b], a, 5300).state, M.clipShowAt([a, b], b, 5300).state], over: [M.clipShowAt([b, a], a, 5300).state, M.clipShowAt([b, a], b, 5300).state] }; });' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(1, 0.5), array($r['fade']['under'][0]['alpha'], $r['fade']['under'][1]['alpha']), 'a under: b fades in over it');
	pg_assert_same(array(0.5, 1), array($r['fade']['over'][0]['alpha'], $r['fade']['over'][1]['alpha']), 'a over: a fades out over b');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 480, 'h' => 540), $r['wipe']['under'][1]['clip'], 'a under: b is uncovered from the left');
	pg_assert_same(array('x' => 960, 'y' => 0, 'w' => 960, 'h' => 1080), $r['wipe']['over'][0]['clip'], 'a over: a is wiped away from the left');
	pg_assert_same(null, $r['wipe']['over'][1]['clip'], 'and b shows whole under it');
	pg_assert_same(0.5, $r['zoom']['over'][0]['alpha'], 'a over: a fades out as it grows');
	pg_assert_same($r['slide']['under'], $r['slide']['over'], 'a slide is the same either way');
}

// The export asks both clips' frames while the transition runs, a's held at
// to - 1; with no transition a shows nothing from its end on, as before.
function test_image_editor_clip_transition_frames()
{
	$r = image_editor_clip_node(
		image_editor_clip_effects_pair('{ kind: "wipe", ms: 1000 }')
		. 'var plain = [clip({ id: "a", row: "r" }), b];' . "\n"
		. 'console.log(JSON.stringify({ during: M.clipFramesAt({ layers: layers }, 5500), after: M.clipFramesAt({ layers: layers }, 6200), plain: M.clipFramesAt({ layers: plain }, 5500) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('a' => 5999, 'b' => 8500), $r['during'], 'both clips, a on its last frame');
	pg_assert_same(array('a' => null, 'b' => 9200), $r['after'], 'then b alone');
	pg_assert_same(array('a' => null, 'b' => 8500), $r['plain'], 'no transition: a cut');
}

// A split applied as the editor applies it (Object.assign of the patch on
// the layer): both parts keep the effects, only the second the transition,
// and the first meets it in a cut.
function test_image_editor_clip_transition_split()
{
	$r = image_editor_clip_node(
		'var l = clip({ id: "a", row: "r", fx: { blur: 20 }, transition: { kind: "fade", ms: 600 } }), b = clip({ id: "b", row: "r", start: 5000 });' . "\n"
		. 'var r = M.clipSplit(l, 3000, "a2"), first = Object.assign(JSON.parse(JSON.stringify(l)), r.left), layers = [first, r.right, b];' . "\n"
		. 'var plain = M.clipSplit(clip({ id: "p" }), 3000, "p2");' . "\n"
		. 'console.log(JSON.stringify({ firstFx: first.fx, secondFx: r.right.fx, firstTr: M.clipTransition(first), secondTr: M.clipTransition(r.right),' . "\n"
		. ' cut: M.clipTransitionMs(layers, first), lead: M.clipTransitionMs(layers, r.right), plainLeft: plain.left }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('blur' => 20), $r['firstFx'], 'the first part keeps the effects');
	pg_assert_same(array('blur' => 20), $r['secondFx'], 'the second too');
	pg_assert_same(null, $r['firstTr'], 'the first part has no transition');
	pg_assert_same(array('kind' => 'fade', 'ms' => 600), $r['secondTr'], 'the second leads into the next clip');
	pg_assert_same(0, $r['cut'], 'the new cut has none');
	pg_assert_same(600, $r['lead'], 'the clip\'s end still has it');
	pg_assert_false(array_key_exists('transition', $r['plainLeft']), 'a clip without one gets no field');
}
