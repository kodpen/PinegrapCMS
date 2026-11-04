<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a canvas's scenes (pg_image_editor_scenes.js and the SCENES
 * section of the model, run under Node): opening a scene swaps its layers,
 * background and duration with the document's, add, duplicate, delete and
 * move, the whole length, the moment of the whole animation with its
 * transition, a scene drawn as a document of its own, the transitions'
 * placing, a reopened canvas's scenes; and the save's cap on scenes and
 * their layers (pg_image_edit_validate()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and a canvas as board(); answers its
// JSON, null without Node.
function image_editor_scenes_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiescenes');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'function board() { return { v: 1, source: null, geometry: { rot: 0 }, artboard: { width: 1920, height: 1080, background: "#ffffff" }, layers: [{ id: "l0", type: "text" }], motion: { duration: 4000, loop: 0, base: "none" } }; }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Adding a scene makes the document's content scene 1 and opens an empty
// scene 2 of 3 s; opening scene 1 again brings its layers, background and
// duration back, scene 2 keeping its own in its entry.
function test_image_editor_scenes_open_swaps()
{
	$r = image_editor_scenes_node(
		'var d = board(); M.sceneAdd(d, false); var added = JSON.parse(JSON.stringify(d));' . "\n"
		. 'd.artboard.background = "#000000"; d.layers.push({ id: "l0", type: "rect" });' . "\n"
		. 'M.sceneOpen(d, "s1");' . "\n"
		. 'console.log(JSON.stringify({ added: added, back: d, list: M.sceneList(d).map(function (s) { return [s.id, s.layers.length, s.background, s.duration, s.open]; }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('s2', $r['added']['scene'], 'the new scene is open');
	pg_assert_same(array(), $r['added']['layers'], 'it starts empty');
	pg_assert_same(3000, $r['added']['motion']['duration'], 'for 3 s');
	pg_assert_same(4000, $r['added']['scenes'][0]['duration'], 'scene 1 keeps the document\'s duration');
	pg_assert_same(array(array('id' => 'l0', 'type' => 'text')), $r['added']['scenes'][0]['layers'], 'and its layers');
	pg_assert_same('s1', $r['back']['scene'], 'scene 1 open again');
	pg_assert_same(array(array('id' => 'l0', 'type' => 'text')), $r['back']['layers'], 'its layers are the document\'s');
	pg_assert_same('#ffffff', $r['back']['artboard']['background'], 'its background');
	pg_assert_same(4000, $r['back']['motion']['duration'], 'its duration');
	pg_assert_false(isset($r['back']['scenes'][0]['layers']), 'the open entry keeps no layers');
	pg_assert_same(array(array('s1', 1, '#ffffff', 4000, true), array('s2', 1, '#000000', 3000, false)), $r['list'], 'the list fills the open scene from the document');
}

// Duplicate copies the open scene after it; delete opens a neighbour and
// one scene left is a document without scenes; move stays in the list.
function test_image_editor_scenes_edit()
{
	$r = image_editor_scenes_node(
		'var d = board(); M.sceneSet(M.sceneStart(d), "s1", { transition: { kind: "fade", ms: 840 } }); M.sceneAdd(d, true);' . "\n"
		. 'var dup = JSON.parse(JSON.stringify(d));' . "\n"
		. 'M.sceneAdd(d, false); M.sceneMove(d, "s3", -5); var moved = d.scenes.map(function (s) { return s.id; });' . "\n"
		. 'M.sceneDelete(d, "s3"); var deleted = { open: d.scene, ids: d.scenes.map(function (s) { return s.id; }) };' . "\n"
		. 'M.sceneDelete(d, "s1");' . "\n"
		. 'console.log(JSON.stringify({ dup: dup, moved: moved, deleted: deleted, single: d }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('s2', $r['dup']['scene'], 'the copy is open');
	pg_assert_same(array(array('id' => 'l0', 'type' => 'text')), $r['dup']['layers'], 'with the layers copied');
	pg_assert_same(4000, $r['dup']['motion']['duration'], 'and the duration');
	pg_assert_same(array('kind' => 'fade', 'ms' => 800), $r['dup']['scenes'][1]['transition'], 'and the transition, in steps of 0.1 s');
	pg_assert_same(array('s3', 's1', 's2'), $r['moved'], 'moved first, not past it');
	pg_assert_same(array('open' => 's1', 'ids' => array('s1', 's2')), $r['deleted'], 'deleting the open scene opens the next');
	pg_assert_false(isset($r['single']['scenes']), 'one scene left: no scenes');
	pg_assert_false(isset($r['single']['scene']), 'and no open scene');
	pg_assert_same(4000, $r['single']['motion']['duration'], 'the remaining scene\'s duration is the document\'s');
}

// The whole length and its moments: scene 2 starts after scene 1; the fade
// of scene 1 runs over the start of scene 2, never longer than it.
function test_image_editor_scenes_timeline()
{
	$r = image_editor_scenes_node(
		'var d = board(); M.sceneAdd(d, false); M.sceneSet(d, "s1", { transition: { kind: "wipe", ms: 1000 } });' . "\n"
		. 'console.log(JSON.stringify({ total: M.sceneTotal(d), still: M.sceneTotal(board()),' . "\n"
		. ' first: M.sceneAt(d, 1500), half: M.sceneAt(d, 4500), after: M.sceneAt(d, 5200), past: M.sceneAt(d, 99000),' . "\n"
		. ' view: (function () { var v = M.sceneView(d, 0); return [v.layers.length, v.motion.duration, v.artboard.background, v.scenes === undefined]; }()) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(7000, $r['total'], 'the scenes back to back');
	pg_assert_same(4000, $r['still'], 'a document without scenes: its own length');
	pg_assert_same(array('index' => 0, 'local' => 1500, 'from' => null, 'kind' => 'none', 'p' => 1), $r['first'], 'inside scene 1');
	pg_assert_same(1, $r['half']['index'], 'scene 2 from 4 s');
	pg_assert_same(500, $r['half']['local'], 'at its own time');
	pg_assert_same(0, $r['half']['from'], 'scene 1 under it');
	pg_assert_same('wipe', $r['half']['kind'], 'its transition');
	pg_assert_same(0.5, round($r['half']['p'], 6), 'half way, eased');
	pg_assert_same(null, $r['after']['from'], 'the transition is over');
	pg_assert_same(array('index' => 1, 'local' => 3000, 'from' => null, 'kind' => 'none', 'p' => 1), $r['past'], 'past the end: the last scene at its end');
	pg_assert_same(array(1, 4000, '#ffffff', true), $r['view'], 'scene 1 drawn as a document of its own');
}

// The whole timeline's places: where each scene starts, and the slot a
// card dragged along the strip lands at.
function test_image_editor_scenes_starts_and_slots()
{
	$r = image_editor_scenes_node(
		'var d = board(); M.sceneAdd(d, false); M.sceneAdd(d, false); M.sceneSet(d, "s2", { duration: 2000 });' . "\n"
		. 'console.log(JSON.stringify({ starts: [0, 1, 2, 9].map(function (i) { return M.sceneStartAt(d, i); }), still: M.sceneStartAt(board(), 0),' . "\n"
		. ' slots: [M.sceneSlot([100, 200], 50), M.sceneSlot([100, 200], 150), M.sceneSlot([100, 200], 250), M.sceneSlot([], 10)] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 4000, 6000, 9000), $r['starts'], 'each scene after the ones before it; past the end, the whole length');
	pg_assert_same(0, $r['still'], 'a document without scenes starts at 0');
	pg_assert_same(array(0, 1, 2, 0), $r['slots'], 'the cards left of the pointer stay before the dragged one');
}

// How each transition places the two scenes.
function test_image_editor_scenes_transitions()
{
	$r = image_editor_scenes_node(
		'console.log(JSON.stringify({ none: M.sceneTransitionAt("none", 0.5, 200, 100), fade: M.sceneTransitionAt("fade", 0.25, 200, 100),' . "\n"
		. ' slide: M.sceneTransitionAt("slide", 0.25, 200, 100), wipe: M.sceneTransitionAt("wipe", 0.5, 200, 100), zoom: M.sceneTransitionAt("zoom", 1, 200, 100) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(null, $r['none']['from'], 'none: only the new scene');
	pg_assert_same(0.25, $r['fade']['to']['alpha'], 'fade: the new scene shows through');
	pg_assert_same(array(-50, 150), array($r['slide']['from']['x'], $r['slide']['to']['x']), 'slide: from the right, the old one leaving left');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 100, 'h' => 100), $r['wipe']['to']['clip'], 'wipe: the left half');
	pg_assert_same(array(1, 1), array($r['zoom']['to']['scale'], $r['zoom']['to']['alpha']), 'zoom: whole at the end');
}

// A canvas opened again keeps its scenes only with the open one among them.
function test_image_editor_scenes_reopen()
{
	$r = image_editor_scenes_node(
		'var d = board(); M.sceneAdd(d, false); var ok = M.canvasOpenDoc(d); d.scene = "s9";' . "\n"
		. 'console.log(JSON.stringify({ ok: M.sceneCount(ok), lost: M.sceneCount(M.canvasOpenDoc(d)) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(2, $r['ok'], 'two scenes kept');
	pg_assert_same(0, $r['lost'], 'an open scene not in the list drops them');
}

// The save takes at most 50 scenes of a canvas, each with at most 500
// layers; a picture has none.
function test_image_editor_scenes_validate_cap()
{
	$board = array('v' => 1, 'artboard' => array('width' => 1920, 'height' => 1080), 'layers' => array());
	$scene = array('id' => 's2', 'layers' => array());

	$ok = $board + array('scenes' => array(array('id' => 's1'), $scene), 'scene' => 's1');
	pg_assert_same('', pg_image_edit_validate($ok)['error'], 'two scenes');

	$many = $board + array('scenes' => array_fill(0, 51, $scene));
	pg_assert_same('scenes', pg_image_edit_validate($many)['error'], '51 scenes refused');

	$full = $board + array('scenes' => array(array('id' => 's1'), array('id' => 's2', 'layers' => array_fill(0, 501, array('id' => 'l0')))));
	pg_assert_same('scenes', pg_image_edit_validate($full)['error'], 'a scene with 501 layers refused');

	$odd = $board + array('scenes' => array('s1'));
	pg_assert_same('scenes', pg_image_edit_validate($odd)['error'], 'a scene that is no object refused');

	$photo = array('v' => 1, 'source' => array('width' => 10, 'height' => 10), 'geometry' => array(), 'scenes' => array());
	pg_assert_same('scenes', pg_image_edit_validate($photo)['error'], 'a picture has no scenes');
}

// The part's labels.
function test_image_editor_scenes_labels()
{
	$labels = pg_image_editor_strings_scenes();

	foreach (array('scenesRegion', 'sceneName', 'sceneAdd', 'sceneDuration', 'sceneTransition', 'sceneTransitionTime', 'sceneNote') as $key) {
		pg_assert_true(isset($labels[$key]) && ($labels[$key] !== ''), $key);
	}

	pg_assert_contains('{n}', $labels['sceneName'], 'the scene\'s number');
}
