<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's canvas mirrors: when one is drawn again
 * (assets/js/pg_mirror_schedule.js, window.PGMirrorSchedule), what of the
 * canvas it leaves out, how it fits its box, and the part 'mirror' that
 * holds it. The pure module runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGMirrorSchedule loaded as M; its `out`
// comes back decoded. Null when Node cannot run.
function mirror_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_mirror_schedule.js') . "\n"
		. "var M = window.PGMirrorSchedule, out = null;\n"
		. "function run(events) { var s = M.initial(); events.forEach(function (e) { s = M.step(s, e[0], e[1]); }); return M.due(s); }\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgmir');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// Nothing waits until the canvas changes; a change is drawn when the hand
// has rested REST ms, and every later change or key puts it off again.
function test_mirror_redraws_when_the_hand_rests()
{
	$r = mirror_test_node('out = ['
		. 'M.REST,'
		. 'run([]),'
		. 'run([["key", 100]]),'
		. 'run([["change", 1000]]),'
		. 'run([["change", 1000], ["change", 1100]]),'
		. 'run([["change", 1000], ["key", 1250]]),'
		. 'run([["change", 1000], ["drawn", 1300]]),'
		. 'run([["change", 1000], ["drawn", 1300], ["key", 1400]])'
		. '];');

	if ($r === null) {
		return;
	}

	pg_assert_same(array(300, null, null, 1300, 1400, 1550, null, null), $r, 'rest-time redraw');
}

// A held pointer (a drag) puts the draw off until it is let go; a pointer
// held HOLD_MAX with nothing happening counts as let go.
function test_mirror_waits_for_a_held_pointer()
{
	$r = mirror_test_node('out = ['
		. 'run([["down", 1000], ["change", 1050], ["change", 1900]]),'
		. 'run([["down", 1000], ["change", 1050], ["up", 2000]]),'
		. 'run([["change", 900], ["down", 1000]]),'
		. 'run([["down", 1000], ["up", 1200]]),'
		. 'M.HOLD_MAX'
		. '];');

	if ($r === null) {
		return;
	}

	pg_assert_same(array(1900 + 15000, 2300, 1000 + 15000, null, 15000), $r, 'drag holds the redraw');
}

// The state given to step() is never changed; an unknown event changes
// nothing.
function test_mirror_step_is_pure()
{
	$r = mirror_test_node('var a = M.initial(); var b = M.step(a, "change", 5); var c = M.step(b, "nope", 9);'
		. 'out = [a, b, c, M.due(null)];');

	if ($r === null) {
		return;
	}

	pg_assert_same(array('dirty' => false, 'held' => false, 'last' => 0), $r[0], 'initial state kept');
	pg_assert_same(array('dirty' => true, 'held' => false, 'last' => 5), $r[1], 'change marks dirty');
	pg_assert_same($r[1], $r[2], 'unknown event changes nothing');
	pg_assert_same(null, $r[3], 'no state, no draw');
}

// The editor's own marks on the canvas are not a change a mirror shows; a
// change of the design is.
function test_mirror_tells_design_changes_from_editor_marks()
{
	$r = mirror_test_node('out = ['
		. 'M.matters({type: "attributes", name: "class", cls: "sd-wrap sd-selected", old: "sd-wrap", now: "sd-wrap sd-selected"}),'
		. 'M.matters({type: "attributes", name: "class", cls: "btn sd-drop-hl", old: "btn", now: "btn sd-drop-hl"}),'
		. 'M.matters({type: "attributes", name: "class", cls: "sd-wrap sd-tb-below", old: "sd-wrap sd-tb-end", now: "sd-wrap sd-tb-below"}),'
		. 'M.matters({type: "attributes", name: "class", cls: "btn btn-lg", old: "btn", now: "btn btn-lg"}),'
		. 'M.matters({type: "attributes", name: "contenteditable", cls: "p", old: null, now: "true"}),'
		. 'M.matters({type: "attributes", name: "style", cls: "p", old: "", now: "color:red"}),'
		. 'M.matters({type: "attributes", name: "style", cls: "sd-align-guide", old: "top:1px", now: "top:2px"}),'
		. 'M.matters({type: "childList", cls: "sd-wrap", nodes: ["sd-tb"]}),'
		. 'M.matters({type: "childList", cls: "sd-wrap", nodes: ["sd-issue-badge", "sd-chip-row"]}),'
		. 'M.matters({type: "childList", cls: "sd-wrap", nodes: ["sd-tb", "sd-wrap"]}),'
		. 'M.matters({type: "childList", cls: "", nodes: [null]}),'
		. 'M.matters({type: "childList", cls: "", nodes: []}),'
		. 'M.matters({type: "characterData", cls: "lead"}),'
		. 'M.matters({type: "characterData", cls: "sd-tb"}),'
		. 'M.matters(null)'
		. '];');

	if ($r === null) {
		return;
	}

	pg_assert_same(array(false, false, false, true, false, true, false, false, false, true, true, false, true, false, false), $r, 'what a mirror shows');
}

// A class list loses the editor's state classes only.
function test_mirror_cleans_state_classes()
{
	$r = mirror_test_node('out = [M.clean("sd-wrap sd-selected  btn sd-multi-selected"), M.clean(""), M.clean(null), M.CHROME_SELECTOR.indexOf("script") === 0, M.CHROME_PARTS.indexOf("script") === -1];');

	if ($r === null) {
		return;
	}

	pg_assert_same(array('sd-wrap btn', '', '', true, true), $r, 'state classes removed');
}

// A width is scaled to fit its box, never above 1; the frame's height is the
// box's height before scaling.
function test_mirror_fits_its_box()
{
	$r = mirror_test_node('out = [M.fit(375, 750, 600), M.fit(768, 384, 500), M.fit(375, 250, 500), M.fit(0, 0, 0)];');

	if ($r === null) {
		return;
	}

	pg_assert_same(array('scale' => 1, 'height' => 600), $r[0], 'wide box: no scaling');
	pg_assert_same(array('scale' => 0.5, 'height' => 1000), $r[1], 'half width');
	pg_assert_same(array('scale' => 0.667, 'height' => 750), $r[2], 'rounded scale');
	pg_assert_same(array('scale' => 1, 'height' => 0), $r[3], 'empty box');
}

// The mirror is a part loaded on first use: its own bundle, the pure module
// first, never in the core's bundle.
function test_mirror_is_a_part()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_mirror.min.js', isset($parts['mirror']) ? $parts['mirror'] : null, 'mirror part listed');
	$bundles = pg_js_bundles();
	pg_assert_same(array('assets/js/pg_mirror_schedule.js', 'assets/js/style_designer_mirror.js'), $bundles['assets/js/style_designer_mirror.min.js'], 'mirror bundle sources');
	pg_assert_false(in_array('assets/js/style_designer_mirror.js', $bundles['assets/js/style_designer.min.js'], true), 'not in the core bundle');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_false(strpos($core, 'PGMirrorSchedule') !== false, 'the core holds no scheduling');
}

// Every editor gets the part's address; it runs only when the switch is on.
function test_mirror_is_handed_to_every_editor()
{
	pg_assert_true(in_array('mirror', pg_designer_parts_for(array()), true), 'handed with no condition');
}

// One function of style_designer.src.js, from its declaration to its
// closing brace.
function mirror_test_core_fn($name)
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// The mirrors' widths: a phone and a tablet unless two of the bar's five
// differing widths are kept; an e-mail design has the phone alone.
function test_mirror_side_widths()
{
	$fn = mirror_test_core_fn('_sdSideWidths');
	pg_assert_true($fn !== '', 'style_designer.src.js has _sdSideWidths()');
	$out = mirror_test_node($fn . "\nout = [_sdSideWidths('', false), _sdSideWidths('576,992', false), _sdSideWidths('768,768', false), _sdSideWidths('375,1400', false), _sdSideWidths('576,992', true), _sdSideWidths(null, false)];");

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array(375, 768), array(576, 992), array(375, 768), array(375, 768), array(375), array(375, 768)), $out, 'widths');
}

// The row's room: boxes of 200 and 280, 12 px around and between, a canvas
// of at least 400; three need a column of 928 px, an e-mail design 636.
function test_mirror_side_layout_needs_room()
{
	$fn = mirror_test_core_fn('_sdSideLayout');
	pg_assert_true($fn !== '', 'style_designer.src.js has _sdSideLayout()');
	$out = mirror_test_node($fn . "\nout = [_sdSideLayout(930, [375, 768]), _sdSideLayout(927, [375, 768]), _sdSideLayout(928, [375, 768]), _sdSideLayout(636, [375]), _sdSideLayout(635, [375]), _sdSideLayout(2000, [992, 1200]), _sdSideLayout(1000, [])];");

	if ($out === null) {
		return;
	}

	pg_assert_same(array('boxes' => array(200, 280), 'side' => 516, 'canvas' => 402, 'fits' => true), $out[0], 'the drawing\'s 1440 px window');
	pg_assert_false($out[1]['fits'], 'one pixel short');
	pg_assert_true($out[2]['fits'], 'three fit in 928');
	pg_assert_same(array('boxes' => array(200), 'side' => 224, 'canvas' => 400, 'fits' => true), $out[3], 'an e-mail design in 636');
	pg_assert_false($out[4]['fits'], 'an e-mail design one pixel short');
	pg_assert_same(array(280, 280), $out[5]['boxes'], 'wide widths take 280');
	pg_assert_false($out[6]['fits'], 'no mirror, no row');
}

// A head's list: the five widths, the shown one ticked, the canvas's and the
// other mirror's dimmed; choosing a dimmed one changes nothing.
function test_mirror_size_list()
{
	$out = mirror_test_node("out = { list: M.sizes([375, 768], 0, 1200), full: M.sizes([375, 768], 1, 0), pick: M.pick([375, 768], 0, 576, 1200), other: M.pick([375, 768], 0, 768, 1200), canvas: M.pick([375, 768], 1, 1200, 1200), outline: M.clean('a sd-outline-grid sd-grid-overlay b') };");

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		array('w' => 375, 'on' => true, 'where' => ''),
		array('w' => 576, 'on' => false, 'where' => ''),
		array('w' => 768, 'on' => false, 'where' => 'other'),
		array('w' => 992, 'on' => false, 'where' => ''),
		array('w' => 1200, 'on' => false, 'where' => 'canvas'),
	), $out['list'], 'the phone mirror\'s list beside a 1200 canvas');
	pg_assert_same('other', $out['full'][0]['where'], 'Full width dims none for the canvas');
	pg_assert_same('', $out['full'][4]['where'], 'Full width is not 1200');
	pg_assert_same(array(576, 768), $out['pick'], 'a free width is taken');
	pg_assert_same(array(375, 768), $out['other'], 'the other mirror\'s width is refused');
	pg_assert_same(array(375, 768), $out['canvas'], 'the canvas\'s width is refused');
	pg_assert_same('a b', $out['outline'], 'Outline and the grid are not drawn in a mirror');
}

// The chosen element is followed: a class change that adds or takes off
// the selection mark counts, any other does not.
function test_mirror_selection_moved()
{
	$out = mirror_test_node("out = ["
		. "M.selectionMoved({ type: 'attributes', name: 'class', old: 'sd-wrap', now: 'sd-wrap sd-selected' }),"
		. "M.selectionMoved({ type: 'attributes', name: 'class', old: 'sd-wrap sd-selected', now: 'sd-wrap' }),"
		. "M.selectionMoved({ type: 'attributes', name: 'class', old: 'sd-wrap sd-selected', now: 'sd-wrap sd-selected sd-tb-below' }),"
		. "M.selectionMoved({ type: 'attributes', name: 'style', old: '', now: 'color:red' }),"
		. "M.selectionMoved({ type: 'childList', nodes: ['sd-selected'] }),"
		. "M.selectionMoved(null)"
		. "];");

	if ($out === null) {
		return;
	}

	pg_assert_same(array(true, true, false, false, false, false), $out, 'only the selection mark moving counts');
}

// A mirror scrolls only when the element is out of its view, and then
// brings it a third of the way down; a hidden element moves nothing.
function test_mirror_follow_scroll()
{
	$out = mirror_test_node("out = ["
		. "M.follow(100, 200, 600, 0),"
		. "M.follow(700, 800, 600, 0),"
		. "M.follow(-300, -200, 600, 900),"
		. "M.follow(550, 650, 600, 0),"
		. "M.follow(50, 100, 600, 10),"
		. "M.follow(0, 0, 600, 0)"
		. "];");

	if ($out === null) {
		return;
	}

	pg_assert_same(array(null, 500, 400, 350, null, null), $out, 'scroll only when out of view, to a third');
}

// "Edit at this size": the canvas takes the mirror's width and the mirror
// the canvas's; Full width hands over 1200; pressed again it swaps back.
function test_mirror_swap()
{
	$out = mirror_test_node("var a = M.swap([375, 768], 0, 1200), b = M.swap(a.widths, 0, a.canvas);"
		. "out = [a, b, M.swap([375, 768], 1, 0), M.swap([375, 1200], 0, 0), M.swap([375, 768], 5, 1200)];");

	if ($out === null) {
		return;
	}

	pg_assert_same(array('widths' => array(1200, 768), 'canvas' => 375), $out[0], 'the phone mirror swaps with a 1200 canvas');
	pg_assert_same(array('widths' => array(375, 768), 'canvas' => 1200), $out[1], 'pressed again it swaps back');
	pg_assert_same(array('widths' => array(375, 1200), 'canvas' => 768), $out[2], 'Full width hands over 1200');
	pg_assert_same(array('widths' => array(1200, 576), 'canvas' => 375), $out[3], 'the other mirror never shows the same width');
	pg_assert_same(null, $out[4], 'no such mirror');
}
