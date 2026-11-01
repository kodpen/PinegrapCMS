<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's keyboard rules in the model
 * (assets/js/pg_image_editor_model.js, run under Node): a focused slider or
 * radio button keeps the stepping keys instead of nudging the selected
 * layers, and the focus finds the pressed control again after the panel is
 * drawn anew.
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
function image_editor_keys_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiekeys');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The arrows, Home, End and the page keys step a slider or a radio button;
// on a button, a checkbox or the stage they stay the editor's (nudge).
function test_image_editor_keys_step_keys()
{
	$r = image_editor_keys_node(
		'var range = { tag: "INPUT", type: "range" };' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. ' left: M.ownsKey(range, "ArrowLeft"), down: M.ownsKey(range, "ArrowDown"), home: M.ownsKey(range, "Home"),' . "\n"
		. ' end: M.ownsKey(range, "End"), pageUp: M.ownsKey(range, "PageUp"), radio: M.ownsKey({ tag: "INPUT", type: "radio" }, "ArrowRight"),' . "\n"
		. ' roleSlider: M.ownsKey({ tag: "DIV", role: "slider" }, "ArrowUp"),' . "\n"
		. ' button: M.ownsKey({ tag: "BUTTON" }, "ArrowLeft"), check: M.ownsKey({ tag: "INPUT", type: "checkbox" }, "ArrowLeft"),' . "\n"
		. ' stage: M.ownsKey({ tag: "MAIN" }, "ArrowLeft"), none: M.ownsKey(null, "ArrowLeft"), rangeZ: M.ownsKey(range, "z"),' . "\n"
		. ' rangeDelete: M.ownsKey(range, "Delete")' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach (array('left', 'down', 'home', 'end', 'pageUp', 'radio', 'roleSlider') as $k) {
		pg_assert_true($r[$k], $k . ': the slider steps its own value');
	}

	foreach (array('button', 'check', 'stage', 'none', 'rangeZ', 'rangeDelete') as $k) {
		pg_assert_false($r[$k], $k . ': the key stays the editor\'s');
	}
}

// After a redraw the pressed control is found by its label and occurrence,
// else at its old place with the same tag and classes; else it is gone.
function test_image_editor_keys_focus_match()
{
	$r = image_editor_keys_node(
		'function c(name, cls) { return { name: name, cls: cls || "BUTTON|b" }; }' . "\n"
		. 'var before = [c("A"), c("Hide"), c("Layer"), c("Layer"), c("Details")];' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. ' same: M.focusMatch(before, 4, [c("A"), c("Hide"), c("Layer"), c("Layer"), c("Details"), c("More")]),' . "\n"
		. ' moved: M.focusMatch(before, 4, [c("Details"), c("A")]),' . "\n"
		. ' second: M.focusMatch(before, 3, [c("A"), c("X"), c("Layer"), c("Y"), c("Layer")]),' . "\n"
		. ' relabelled: M.focusMatch(before, 1, [c("A"), c("Show"), c("Layer")]),' . "\n"
		. ' otherKind: M.focusMatch(before, 1, [c("A"), c("Show", "INPUT|r"), c("Layer")]),' . "\n"
		. ' gone: M.focusMatch(before, 4, [c("A")]),' . "\n"
		. ' outside: M.focusMatch(before, -1, before), empty: M.focusMatch([], 0, before)' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(4, $r['same'], 'the same control in the same place');
	pg_assert_same(0, $r['moved'], 'the control is found by its label where it moved');
	pg_assert_same(4, $r['second'], 'the second of two controls of one label stays the second');
	pg_assert_same(1, $r['relabelled'], 'a toggle with a new label keeps its place');
	pg_assert_same(-1, $r['otherKind'], 'another kind of control at the old place is not it');
	pg_assert_same(-1, $r['gone'], 'a control gone hands the focus to the stage');
	pg_assert_same(-1, $r['outside'], 'a focus outside the panel is not looked for');
	pg_assert_same(-1, $r['empty'], 'nothing before, nothing to find');
}
