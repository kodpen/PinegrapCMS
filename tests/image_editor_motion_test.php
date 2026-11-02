<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's motion (pg_image_editor_motion.js and the
 * MOTION section of the model, run under Node): every in, loop and out kind
 * at its start, middle and end, the eases, the compose order, the spans, the
 * document's motion and base move, and the part's labels.
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
function image_editor_motion_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiemotion');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'function r(n) { return Math.round(n * 1000) / 1000; }' . "\n"
		. 'function s(st) { return { o: r(st.opacity), x: r(st.x), y: r(st.y), k: r(st.scale), rot: r(st.rot), clip: st.clip ? [st.clip.side, r(st.clip.f)] : null, draw: r(st.draw), chars: st.chars }; }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The kinds, directions and eases the panel offers, in its order.
function test_image_editor_motion_lists()
{
	$r = image_editor_motion_node('console.log(JSON.stringify({ ins: M.MOTION_IN, loops: M.MOTION_LOOP, dirs: M.MOTION_DIRS, eases: M.MOTION_EASES, bases: M.MOTION_BASES }));');

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('fade', 'slide', 'rise', 'pop', 'zoom', 'wipe', 'spin', 'type', 'draw'), $r['ins'], 'in and out kinds');
	pg_assert_same(array('pulse', 'float', 'spin', 'blink', 'wiggle', 'swing'), $r['loops'], 'loop kinds');
	pg_assert_same(array('left', 'up', 'right', 'down'), $r['dirs'], 'directions');
	pg_assert_same(array('linear', 'out', 'in', 'in-out', 'back', 'bounce'), $r['eases'], 'eases');
	pg_assert_same(array('none', 'zoomin', 'zoomout', 'panleft', 'panright'), $r['bases'], 'base moves');
}

// Every ease starts at 0 and ends at 1; back overshoots, linear is the
// identity, an unknown name is linear.
function test_image_editor_motion_eases()
{
	$r = image_editor_motion_node(
		'var out = {};' . "\n"
		. 'M.MOTION_EASES.concat(["nope"]).forEach(function (e) { out[e] = [r(M.easeAt(e, 0)), r(M.easeAt(e, 0.5)), r(M.easeAt(e, 1))]; });' . "\n"
		. 'out.backPeak = Math.max.apply(null, [0.6, 0.7, 0.8, 0.9].map(function (p) { return M.easeAt("back", p); })) > 1;' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach (array('linear', 'out', 'in', 'in-out', 'back', 'bounce', 'nope') as $e) {
		pg_assert_same(0, $r[$e][0] + 0, $e . ' starts at 0');
		pg_assert_same(1, $r[$e][2] + 0, $e . ' ends at 1');
	}

	pg_assert_same(0.5, $r['linear'][1], 'linear is the identity');
	pg_assert_same(0.5, $r['nope'][1], 'an unknown ease is linear');
	pg_assert_true($r['out'][1] > 0.5, 'out is fast first');
	pg_assert_true($r['in'][1] < 0.5, 'in is slow first');
	pg_assert_true($r['backPeak'] === true, 'back overshoots');
}

// No anim, or no playhead, is the rest state.
function test_image_editor_motion_rest()
{
	$r = image_editor_motion_node(
		'var l = { type: "rect", x: 0, y: 0, w: 100, h: 50 };' . "\n"
		. 'var a = { type: "rect", x: 0, y: 0, w: 100, h: 50, anim: { in: { kind: "fade", ms: 800, delay: 0, ease: "linear" } } };' . "\n"
		. 'console.log(JSON.stringify({ none: s(M.motionAt(l, 300, 5000)), nullT: s(M.motionAt(a, null, 5000)) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	$rest = array('o' => 1, 'x' => 0, 'y' => 0, 'k' => 1, 'rot' => 0, 'clip' => null, 'draw' => 1, 'chars' => null);

	pg_assert_same($rest, $r['none'], 'a layer without anim is at rest');
	pg_assert_same($rest, $r['nullT'], 'a null playhead is the rest state');
}

// Each in kind at its start, middle and end (linear ease, 1 s from 0.5 s).
function test_image_editor_motion_in_kinds()
{
	$r = image_editor_motion_node(
		'var out = {};' . "\n"
		. 'M.MOTION_IN.forEach(function (k) {' . "\n"
		. '  var l = { type: k === "type" ? "text" : "rect", text: "Hello", x: 0, y: 0, w: 200, h: 100, anim: { in: { kind: k, ms: 1000, delay: 500, ease: "linear", dir: "up" } } };' . "\n"
		. '  out[k] = [s(M.motionAt(l, 0, 5000)), s(M.motionAt(l, 1000, 5000)), s(M.motionAt(l, 1500, 5000)), s(M.motionAt(l, 3000, 5000))];' . "\n"
		. '});' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach ($r as $kind => $states) {
		pg_assert_same($states[2], $states[3], $kind . ': at rest from its end on');
		pg_assert_same(1, $states[2]['o'] + 0, $kind . ': fully shown at its end');
	}

	pg_assert_same(array(0, 0.5, 1), array($r['fade'][0]['o'] + 0, $r['fade'][1]['o'], $r['fade'][2]['o'] + 0), 'fade');
	pg_assert_true($r['rise'][0]['y'] > 0, 'rise up starts below');
	pg_assert_same($r['rise'][0]['y'] / 2, $r['rise'][1]['y'], 'rise is halfway in the middle');
	pg_assert_same(100, $r['slide'][0]['y'] + 0, 'slide up starts one height below');
	pg_assert_same(0, $r['slide'][0]['o'] + 0, 'slide is hidden before it starts');
	pg_assert_true($r['pop'][0]['k'] < 1, 'pop starts small');
	pg_assert_true($r['zoom'][0]['k'] > 1, 'zoom starts large');
	pg_assert_same(array('bottom', 0.5), $r['wipe'][1]['clip'], 'wipe up reveals from the bottom');
	pg_assert_same(null, $r['wipe'][2]['clip'], 'wipe ends unclipped');
	pg_assert_true($r['spin'][0]['rot'] != 0, 'spin starts turned');
	pg_assert_same(array(0, 0.5, 1), array($r['draw'][0]['draw'] + 0, $r['draw'][1]['draw'], $r['draw'][2]['draw'] + 0), 'draw');
	pg_assert_same(array(0, 2, null), array($r['type'][0]['chars'], $r['type'][1]['chars'], $r['type'][2]['chars']), 'type: letters shown, rounded down');
}

// Out is the in kind played backwards towards its direction, ending at the
// end less its delay; the loop runs between.
function test_image_editor_motion_out_and_loop()
{
	$r = image_editor_motion_node(
		'var l = { type: "rect", x: 0, y: 0, w: 200, h: 100, anim: {' . "\n"
		. '  in: { kind: "fade", ms: 1000, delay: 0, ease: "linear" },' . "\n"
		. '  loop: { kind: "spin", ms: 1000, delay: 0, ease: "linear", dir: "right" },' . "\n"
		. '  out: { kind: "slide", ms: 1000, delay: 0, ease: "linear", dir: "right" } } };' . "\n"
		. 'var sp = M.motionSpans(l, 5000);' . "\n"
		. 'console.log(JSON.stringify({ spans: sp, inMid: s(M.motionAt(l, 500, 5000)), loopQ: s(M.motionAt(l, 1250, 5000)),'
		. ' loopEnd: s(M.motionAt(l, 3999, 5000)), outMid: s(M.motionAt(l, 4500, 5000)), outEnd: s(M.motionAt(l, 5000, 5000)),'
		. ' late: M.motionSpans({ anim: { out: { kind: "fade", ms: 500, delay: 1000 } } }, 5000) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('inStart' => 0, 'inEnd' => 1000, 'loopStart' => 1000, 'loopEnd' => 4000, 'outStart' => 4000, 'outEnd' => 5000), $r['spans'], 'the spans');
	pg_assert_same(0.5, $r['inMid']['o'], 'in at its middle');
	pg_assert_same(90, $r['loopQ']['rot'] + 0, 'spin loop a quarter turn after a quarter period');
	pg_assert_same(100, $r['outMid']['x'] + 0, 'slide out right is half a width right at its middle');
	pg_assert_same(200, $r['outEnd']['x'] + 0, 'slide out ends a width away');
	pg_assert_same(0, $r['outEnd']['o'] + 0, 'slide out ends hidden');
	pg_assert_same(array('inStart' => null, 'inEnd' => null, 'loopStart' => null, 'loopEnd' => null, 'outStart' => 3500, 'outEnd' => 4000), $r['late'], 'out ends its delay before the end');
}

// Loop kinds start at rest, and the compose order adds in, loop and out.
function test_image_editor_motion_loop_kinds_compose()
{
	$r = image_editor_motion_node(
		'var out = {};' . "\n"
		. 'M.MOTION_LOOP.forEach(function (k) {' . "\n"
		. '  var l = { type: "rect", x: 0, y: 0, w: 200, h: 100, anim: { loop: { kind: k, ms: 1000, delay: 0, ease: "linear", dir: "up" } } };' . "\n"
		. '  out[k] = [s(M.motionAt(l, 0, 5000)), s(M.motionAt(l, 250, 5000)), s(M.motionAt(l, 500, 5000))];' . "\n"
		. '});' . "\n"
		. 'var c = { type: "rect", x: 0, y: 0, w: 200, h: 100, anim: { in: { kind: "fade", ms: 1000, delay: 0, ease: "linear" }, loop: { kind: "blink", ms: 1000 } } };' . "\n"
		. 'out.compose = s(M.motionAt(c, 500, 5000));' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach (array('pulse', 'float', 'spin', 'blink', 'wiggle', 'swing') as $k) {
		pg_assert_same(array('o' => 1, 'x' => 0, 'y' => 0, 'k' => 1, 'rot' => 0, 'clip' => null, 'draw' => 1, 'chars' => null), $r[$k][0], $k . ' starts at rest');
	}

	pg_assert_true($r['pulse'][2]['k'] > 1, 'pulse grows');
	pg_assert_true($r['float'][2]['y'] < 0, 'float up moves up');
	pg_assert_true($r['blink'][2]['o'] < 1, 'blink fades');
	pg_assert_true($r['swing'][1]['rot'] != 0, 'swing turns');
	pg_assert_same(0.5, $r['compose']['o'], 'the loop waits for the end of in');
}

// The document's motion: defaults, the duration clamp, and the base move at
// its start and end.
function test_image_editor_motion_document_and_base()
{
	$r = image_editor_motion_node(
		'console.log(JSON.stringify({ none: M.docMotion({}), long: M.docMotion({ motion: { duration: 99999, loop: -2, base: "bogus" } }),'
		. ' zin: [M.baseAt("zoomin", 0, 5000), M.baseAt("zoomin", 5000, 5000)], pan: M.baseAt("panleft", 5000, 5000), still: M.baseAt("none", 2500, 5000),'
		. ' anim: M.hasMotion({ layers: [{ anim: { in: { kind: "fade" } } }] }), plain: M.hasMotion({ layers: [{}] }),'
		. ' fresh: M.newMotion("in", "pop") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('duration' => 5000, 'loop' => 0, 'base' => 'none'), $r['none'], 'defaults: 5 s, forever, no base move');
	pg_assert_same(array('duration' => 60000, 'loop' => 0, 'base' => 'none'), $r['long'], 'the duration is clamped and bad values reset');
	pg_assert_same(array('scale' => 1, 'x' => 0, 'y' => 0), $r['zin'][0], 'zoom in starts at rest');
	pg_assert_true($r['zin'][1]['scale'] > 1, 'zoom in ends larger');
	pg_assert_true($r['pan']['x'] < 0, 'pan left moves the picture left');
	pg_assert_same(array('scale' => 1, 'x' => 0, 'y' => 0), $r['still'], 'no base move');
	pg_assert_true($r['anim'] === true, 'a layer with anim is motion');
	pg_assert_true($r['plain'] === false, 'no anim, no motion');
	pg_assert_same(array('kind' => 'pop', 'ms' => 800, 'delay' => 0, 'ease' => 'back', 'dir' => 'up'), $r['fresh'], 'a new pop entrance');
}

// The part's labels are in the motion part and the tool is in both rails.
function test_image_editor_motion_labels_and_rails()
{
	$strings = pg_image_editor_strings_motion();

	foreach (array('tool_motion', 'motionIn', 'motionLoop', 'motionOut', 'motionPlay', 'motionPause', 'hintMotion') as $key) {
		pg_assert_true(isset($strings[$key]), $key . ' is in the motion part');
	}

	pg_assert_true(in_array('motion', pg_image_editor_parts(), true), 'motion is a part');

	$r = image_editor_motion_node('console.log(JSON.stringify({ photo: M.PROFILES.photo, canvas: M.PROFILES.canvas, tools: M.TOOLS }));');

	if ($r === null) {
		return;
	}

	pg_assert_same('motion', end($r['photo']), 'the photo rail ends with Animate');
	pg_assert_same('motion', end($r['canvas']), 'the canvas rail ends with Animate');
	pg_assert_true(in_array('motion', $r['tools'], true), 'a caller may name it');
}
