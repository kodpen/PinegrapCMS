<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's tracks (pg_image_editor_tracks.js and the
 * TRACKS section of the model, run under Node): a layer's bars, the snap
 * targets, a whole bar moved, an end moved, the snapping to a tenth of a
 * second and to other ends, the clamps, and the part's labels.
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
function image_editor_tracks_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgietracks');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'function lay(id, anim) { return { id: id, type: "rect", x: 0, y: 0, w: 100, h: 50, anim: anim }; }' . "\n"
		. 'function ph(kind, ms, delay) { return { kind: kind, ms: ms, delay: delay, ease: "out", dir: "up" }; }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A bar per phase in time order; a layer that only stays has none.
function test_image_editor_tracks_bars()
{
	$r = image_editor_tracks_node(
		'console.log(JSON.stringify({' . "\n"
		. ' all: M.trackBars(lay("a", { in: ph("fade", 800, 500), loop: ph("pulse", 1500, 200), out: ph("fade", 600, 400) }), 5000),' . "\n"
		. ' none: M.trackBars(lay("b"), 5000),' . "\n"
		. ' loopOnly: M.trackBars(lay("c", { loop: ph("spin", 1000, 0) }), 5000)' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(
		array('phase' => 'in', 'from' => 500, 'to' => 1300),
		array('phase' => 'loop', 'from' => 1500, 'to' => 4000),
		array('phase' => 'out', 'from' => 4000, 'to' => 4600)
	), $r['all'], 'in, loop and out bars');
	pg_assert_same(array(), $r['none'], 'a layer without anim has no bar');
	pg_assert_same(array(array('phase' => 'loop', 'from' => 0, 'to' => 5000)), $r['loopOnly'], 'a loop alone runs the whole length');
}

// The targets: 0, the end and the other layers' ends, once each, sorted.
function test_image_editor_tracks_targets()
{
	$r = image_editor_tracks_node(
		'var ls = [lay("a", { in: ph("fade", 800, 500) }), lay("b", { in: ph("fade", 1000, 0), out: ph("fade", 500, 0) })];' . "\n"
		. 'console.log(JSON.stringify({ skipA: M.trackTargets(ls, 5000, "a"), all: M.trackTargets(ls, 5000, null) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 1000, 4500, 5000), $r['skipA'], 'the dragged layer is left out');
	pg_assert_same(array(0, 500, 1000, 1300, 4500, 5000), $r['all'], 'every end once');
}

// The grid is a tenth of a second, a hundredth with Shift; a target within
// the slack wins over the grid, except with Shift.
function test_image_editor_tracks_snap()
{
	$r = image_editor_tracks_node(
		'console.log(JSON.stringify({' . "\n"
		. ' grid: M.trackSnap(1234, [], 50, false),' . "\n"
		. ' fine: M.trackSnap(1234, [1250], 50, true),' . "\n"
		. ' near: M.trackSnap(1234, [1000, 1260], 50, false),' . "\n"
		. ' far: M.trackSnap(1234, [1300], 50, false)' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(1200, $r['grid'], 'a tenth of a second');
	pg_assert_same(1230, $r['fine'], 'Shift: a hundredth, no target');
	pg_assert_same(1260, $r['near'], 'the nearest target within the slack');
	pg_assert_same(1200, $r['far'], 'a target past the slack is the grid');
}

// An entrance: the whole bar moves its delay, the start end keeps the end
// in place, the end end changes the length; clamps to the timeline and to a
// tenth of a second.
function test_image_editor_tracks_edit_in()
{
	$r = image_editor_tracks_node(
		'var l = lay("a", { in: ph("fade", 800, 500) }), E = 5000;' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. ' move: M.trackEdit(l, "in", "move", 330, E, {}),' . "\n"
		. ' early: M.trackEdit(l, "in", "move", -900, E, {}),' . "\n"
		. ' late: M.trackEdit(l, "in", "move", 9000, E, {}),' . "\n"
		. ' start: M.trackEdit(l, "in", "start", -240, E, {}),' . "\n"
		. ' startPast: M.trackEdit(l, "in", "start", 2000, E, {}),' . "\n"
		. ' end: M.trackEdit(l, "in", "end", 470, E, {}),' . "\n"
		. ' endPast: M.trackEdit(l, "in", "end", -2000, E, {}),' . "\n"
		. ' snapEnd: M.trackEdit(l, "in", "move", 380, E, { targets: [1700], slack: 60 }),' . "\n"
		. ' fine: M.trackEdit(l, "in", "move", 333, E, { targets: [833], slack: 60, fine: true }),' . "\n"
		. ' none: M.trackEdit(l, "out", "move", 100, E, {})' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('delay' => 800, 'ms' => 800), $r['move'], 'moved by 0.3 s on the grid');
	pg_assert_same(array('delay' => 0, 'ms' => 800), $r['early'], 'no earlier than 0');
	pg_assert_same(array('delay' => 4200, 'ms' => 800), $r['late'], 'no later than the end');
	pg_assert_same(array('delay' => 300, 'ms' => 1000), $r['start'], 'the start end keeps the end');
	pg_assert_same(array('delay' => 1200, 'ms' => 100), $r['startPast'], 'at least a tenth of a second');
	pg_assert_same(array('delay' => 500, 'ms' => 1300), $r['end'], 'the end end changes the length');
	pg_assert_same(array('delay' => 500, 'ms' => 100), $r['endPast'], 'the end stays after the start');
	pg_assert_same(array('delay' => 900, 'ms' => 800), $r['snapEnd'], 'its end snapped to another end');
	pg_assert_same(array('delay' => 830, 'ms' => 800), $r['fine'], 'Shift: a hundredth, no snap');
	pg_assert_same(null, $r['none'], 'a phase the layer lacks');
}

// An exit counts its delay back from the end; a loop moves only its start.
function test_image_editor_tracks_edit_out_and_loop()
{
	$r = image_editor_tracks_node(
		'var l = lay("a", { in: ph("fade", 800, 0), loop: ph("pulse", 1500, 0), out: ph("fade", 600, 400) }), E = 5000;' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. ' move: M.trackEdit(l, "out", "move", -500, E, {}),' . "\n"
		. ' toEnd: M.trackEdit(l, "out", "move", 900, E, {}),' . "\n"
		. ' start: M.trackEdit(l, "out", "start", -400, E, {}),' . "\n"
		. ' end: M.trackEdit(l, "out", "end", 200, E, {}),' . "\n"
		. ' loop: M.trackEdit(l, "loop", "move", 420, E, {}),' . "\n"
		. ' loopBack: M.trackEdit(l, "loop", "start", -900, E, {}),' . "\n"
		. ' loopPast: M.trackEdit(l, "loop", "move", 9000, E, {})' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('delay' => 900, 'ms' => 600), $r['move'], 'moved 0.5 s earlier: its delay from the end grows');
	pg_assert_same(array('delay' => 0, 'ms' => 600), $r['toEnd'], 'no later than the end');
	pg_assert_same(array('delay' => 400, 'ms' => 1000), $r['start'], 'the start end keeps the end');
	pg_assert_same(array('delay' => 200, 'ms' => 800), $r['end'], 'the end end changes its delay and length');
	pg_assert_same(array('delay' => 400), $r['loop'], 'the loop starts later after the entrance');
	pg_assert_same(array('delay' => 0), $r['loopBack'], 'never before the entrance ends');
	pg_assert_same(array('delay' => 3100), $r['loopPast'], 'a tenth of a second before the exit at most');
}

// The part's labels are in the tracks part, and it is a part of the editor.
function test_image_editor_tracks_labels()
{
	$strings = pg_image_editor_strings_tracks();

	foreach (array('tracksRegion', 'tracksLayers', 'hintTracks') as $key) {
		pg_assert_true(isset($strings[$key]), $key . ' is in the tracks part');
	}

	pg_assert_true(in_array('tracks', pg_image_editor_parts(), true), 'tracks is a part');
}
