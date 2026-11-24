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

	pg_assert_same(array(false, false, true, false, true, false, false, false, true, true, false, true, false, false), $r, 'what a mirror shows');
}

// A class list loses the editor's state classes only.
function test_mirror_cleans_state_classes()
{
	$r = mirror_test_node('out = [M.clean("sd-wrap sd-selected  btn sd-multi-selected"), M.clean(""), M.clean(null), M.CHROME_SELECTOR.indexOf("script") === 0];');

	if ($r === null) {
		return;
	}

	pg_assert_same(array('sd-wrap btn', '', '', true), $r, 'state classes removed');
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
// first, handed to the editor, never in the core's bundle.
function test_mirror_is_a_part()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_mirror.min.js', isset($parts['mirror']) ? $parts['mirror'] : null, 'mirror part listed');
	$bundles = pg_js_bundles();
	pg_assert_same(array('assets/js/pg_mirror_schedule.js', 'assets/js/style_designer_mirror.js'), $bundles['assets/js/style_designer_mirror.min.js'], 'mirror bundle sources');
	pg_assert_false(in_array('assets/js/style_designer_mirror.js', $bundles['assets/js/style_designer.min.js'], true), 'not in the core bundle');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "'mirror'") !== false, 'the screen hands the part over');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_false(strpos($core, 'PGMirrorSchedule') !== false, 'the core holds no scheduling');
}
