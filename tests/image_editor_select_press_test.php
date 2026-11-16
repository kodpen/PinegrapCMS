<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for selecting from any tool of the image editor
 * (assets/js/pg_image_editor_model.js, pg_image_editor.js and the tool
 * parts, run under Node on an editor without a page): Ctrl held (Cmd on a
 * Mac) makes a press Select's in every tool but where the tool's own work
 * is under way (cropping, typing a text, placing a path or a polygon,
 * editing a path's points, recording a move); in Animate a click on a layer
 * keeps the playhead; the Text tool selects a layer that is not a text.
 * Skipped where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and, when $editor is true, the editor's
// core and tool parts loaded, Editor its class and fake(tool) a bare editor
// on a 200 x 100 canvas with two pictures (7 at 10..50, 8 at 120..160).
// Answers the script's JSON, null without Node.
function image_editor_select_press_node($script, $editor = false)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieselp');

	if ($file === false) {
		return null;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} }; global.navigator = { platform: "Linux" };' . "\n"
		. 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . '); window.PgImageEditorModel = window.PgImageEditorModel || M;' . "\n";

	if ($editor) {
		foreach (array('', '_layers', '_draw', '_text', '_area', '_artboard', '_vector', '_motion', '_tracks', '_keyframes', '_record') as $part) {
			$head .= 'require(' . json_encode($js . 'pg_image_editor' . $part . '.js') . ');' . "\n";
		}

		$head .= 'var Editor = window.PgImageEditor.parts.Editor, none = function () {};' . "\n"
			. 'window.cancelAnimationFrame = none;' . "\n"
			. 'function picture(id, x, y, w, h) { return { id: id, type: "image", src: "a.png", x: x, y: y, w: w, h: h, rot: 0, opacity: 1 }; }' . "\n"
			. 'function fake(tool) {' . "\n"
			. ' var e = Object.create(Editor.prototype);' . "\n"
			. ' Editor.inits.forEach(function (fn) { try { fn.call(e); } catch (err) {} });' . "\n"
			. ' e.doc = M.newDocument({ width: 200, height: 100 }); e.doc.layers = [picture(7, 10, 10, 40, 40), picture(8, 120, 10, 40, 40)];' . "\n"
			. ' e.tool = tool || "select"; e.sel = []; e.pointers = {}; e.view = { k: 1, x: 0, y: 0 }; e.history = {}; e.ready = true; e.board = {}; e.log = [];' . "\n"
			. ' e.stage = { setPointerCapture: none, classList: { add: none, remove: none } }; e.toDoc = function (x, y) { return [x, y]; };' . "\n"
			. ' e.render = e.renderView = e.renderLayers = e.renderPanel = e.applyMotion = e.updateBar = none; e.change = function (fn) { fn(this.doc); };' . "\n"
			. ' e.pictureOn = function () { return false; }; e.motionEnd = function () { return 3000; }; e.motionStart = function () { return 0; };' . "\n"
			. ' e.motionNow = function () { return this.time || 0; };' . "\n"
			. ' return e;' . "\n"
			. '}' . "\n"
			. 'function ev(type, x, y, keys) {' . "\n"
			. ' var o = { type: type, clientX: x, clientY: y, button: 0, pointerId: 1, pointerType: "mouse", shiftKey: false, altKey: false, ctrlKey: false, metaKey: false, target: { getAttribute: function () { return null; } }, preventDefault: none };' . "\n"
			. ' Object.keys(keys || {}).forEach(function (k) { o[k] = keys[k]; });' . "\n"
			. ' return o;' . "\n"
			. '}' . "\n"
			. 'function press(e, x, y, keys, to) { e.onDown(ev("pointerdown", x, y, keys)); if (to) { e.onMove(ev("pointermove", to[0], to[1], keys)); } e.onUp(ev("pointerup", to ? to[0] : x, to ? to[1] : y, keys)); return e; }' . "\n";
	}

	file_put_contents($file, $head . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Ctrl (Cmd on a Mac) makes the press Select's in every tool whose own press
// is not Select's already; without the key, with another button or with the
// other platform's key it is the tool's own.
function test_image_editor_press_selects_by_tool()
{
	$r = image_editor_select_press_node(
		'var tools = ["crop", "adjust", "draw", "text", "redact", "area", "frame", "vector", "scissors", "warp", "puppet", "builder", "artboard", "image", "export", "motion", "arrow", "pen"];' . "\n"
		. 'var out = { ctrl: {}, plain: {}, meta: {}, macCmd: {}, macCtrl: {} };' . "\n"
		. 'tools.forEach(function (t) {' . "\n"
		. ' out.ctrl[t] = M.pressSelects(t, {}, { ctrlKey: true, button: 0 });' . "\n"
		. ' out.plain[t] = M.pressSelects(t, {}, { button: 0 });' . "\n"
		. ' out.meta[t] = M.pressSelects(t, {}, { metaKey: true, button: 0 });' . "\n"
		. ' out.macCmd[t] = M.pressSelects(t, { mac: true }, { metaKey: true, button: 0 });' . "\n"
		. ' out.macCtrl[t] = M.pressSelects(t, { mac: true }, { ctrlKey: true, button: 0 });' . "\n"
		. '});' . "\n"
		. 'out.own = ["select", "sticker"].map(function (t) { return M.pressSelects(t, {}, { ctrlKey: true, button: 0 }); });' . "\n"
		. 'out.right = M.pressSelects("draw", {}, { ctrlKey: true, button: 2 });' . "\n"
		. 'out.none = M.pressSelects("draw");' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach ($r['ctrl'] as $tool => $on) {
		pg_assert_true($on, 'Ctrl makes the press Select\'s with ' . $tool);
		pg_assert_false($r['plain'][$tool], 'a plain press stays the tool\'s with ' . $tool);
		pg_assert_false($r['meta'][$tool], 'the Windows key is not Ctrl with ' . $tool);
		pg_assert_true($r['macCmd'][$tool], 'Cmd makes the press Select\'s on a Mac with ' . $tool);
		pg_assert_false($r['macCtrl'][$tool], 'Ctrl on a Mac is the context menu\'s with ' . $tool);
	}

	pg_assert_same(array(false, false), $r['own'], 'Select and Sticker press as Select already');
	pg_assert_false($r['right'], 'only the left button');
	pg_assert_false($r['none'], 'no press, no key');
}

// The states a tool's own work holds refuse the key: cropping, typing a
// text, placing a path or a polygon, editing a path's points, recording.
function test_image_editor_press_selects_refused_states()
{
	$r = image_editor_select_press_node(
		'var k = { ctrlKey: true, button: 0 }, out = {};' . "\n"
		. 'out.cropping = M.pressSelects("crop", { cropping: true }, k);' . "\n"
		. 'out.typing = M.pressSelects("text", { typing: true }, k);' . "\n"
		. 'out.path = M.pressSelects("vector", { placing: true }, k);' . "\n"
		. 'out.polygon = M.pressSelects("area", { placing: true }, k);' . "\n"
		. 'out.points = M.pressSelects("vector", { editing: true }, k);' . "\n"
		. 'out.recording = M.pressSelects("motion", { recording: true }, k);' . "\n"
		. 'out.idle = M.pressSelects("vector", { cropping: false, typing: false, placing: false, editing: false, recording: false }, k);' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_false($r['cropping'], 'not while cropping');
	pg_assert_false($r['typing'], 'not while a text is typed');
	pg_assert_false($r['path'], 'not while a path is placed');
	pg_assert_false($r['polygon'], 'not while a polygon is placed');
	pg_assert_false($r['points'], 'not while a path\'s points are edited');
	pg_assert_false($r['recording'], 'not while a move is recorded');
	pg_assert_true($r['idle'], 'with none of them the key selects');
}

// Through the editor: Ctrl and a click selects the layer in Draw, Select
// area, Pen and Shape builder's places without drawing, a drag moves it, and
// the tool stays; while a polygon is placed the press is the tool's.
function test_image_editor_ctrl_press_selects_in_tools()
{
	$r = image_editor_select_press_node(
		'var out = {}, c = { ctrlKey: true };' . "\n"
		. '["draw", "area", "vector", "artboard", "adjust"].forEach(function (t) {' . "\n"
		. ' var e = press(fake(t), 20, 20, c);' . "\n"
		. ' out[t] = { sel: e.sel, tool: e.tool, layers: e.doc.layers.length, drag: e.drag || null, draft: !!e.vdraft };' . "\n"
		. '});' . "\n"
		. 'var e = press(fake("draw"), 20, 20, { ctrlKey: true, altKey: true }, [30, 25]); out.moved = [e.doc.layers[0].x, e.doc.layers[0].y, e.tool];' . "\n"
		. 'e = fake("draw"); e.sel = [7]; press(e, 130, 20, { ctrlKey: true, shiftKey: true }); out.shift = e.sel;' . "\n"
		. 'e = fake("area"); e.areaDefaults.kind = "polygon"; e.areaPoly = { op: "add", pts: [[0, 0]] }; press(e, 20, 20, c); out.polygon = { sel: e.sel, pts: e.areaPoly ? e.areaPoly.pts.length : 0 };' . "\n"
		. 'e = fake("draw"); press(e, 20, 20); out.plain = { sel: e.sel };' . "\n"
		. 'console.log(JSON.stringify(out));',
		true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach (array('draw', 'area', 'vector', 'artboard', 'adjust') as $tool) {
		pg_assert_same(array(7), $r[$tool]['sel'], 'Ctrl and a click selects the layer with ' . $tool);
		pg_assert_same($tool, $r[$tool]['tool'], 'the tool stays: ' . $tool);
		pg_assert_same(2, $r[$tool]['layers'], 'nothing is drawn with ' . $tool);
		pg_assert_same(null, $r[$tool]['drag'], 'the press ended with ' . $tool);
		pg_assert_false($r[$tool]['draft'], 'no path started with ' . $tool);
	}

	pg_assert_same(array(20, 15, 'draw'), $r['moved'], 'Ctrl and a drag moves the layer, the tool stays');
	pg_assert_same(array(7, 8), $r['shift'], 'Ctrl and Shift add a layer');
	pg_assert_same(array(), $r['polygon']['sel'], 'while a polygon is placed the press is the tool\'s');
	pg_assert_same(2, $r['polygon']['pts'], 'and places a point');
}

// The Text tool: a click on a layer that is not a text selects it; an empty
// spot starts a new text, as before.
function test_image_editor_text_click_selects_layer()
{
	$r = image_editor_select_press_node(
		'var out = {}, e = fake("text"), started = [];' . "\n"
		. 'e.startTextEdit = function (l) { started.push(l); }; e.newText = function (x, y) { return { type: "text", x: x, y: y }; };' . "\n"
		. 'press(e, 20, 20); out.sel = e.sel; out.started = started.length;' . "\n"
		. 'press(e, 90, 80); out.empty = started.length;' . "\n"
		. 'console.log(JSON.stringify(out));',
		true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(7), $r['sel'], 'a click on a picture selects it');
	pg_assert_same(0, $r['started'], 'and starts no text');
	pg_assert_same(1, $r['empty'], 'an empty spot starts a new text');
}

// Animate: a click on a layer keeps the playhead; a drag that moves it
// edits at rest, as before.
function test_image_editor_motion_click_keeps_playhead()
{
	$r = image_editor_select_press_node(
		'var out = {}, e = fake("motion");' . "\n"
		. 'e.time = 0; press(e, 130, 20); out.zero = { time: e.time, sel: e.sel };' . "\n"
		. 'e = fake("motion"); e.time = 1200; e.sel = [8]; press(e, 20, 20, { shiftKey: true }); out.shift = { time: e.time, sel: e.sel };' . "\n"
		. 'e = fake("motion"); e.time = 0; press(e, 130, 20, null, [140, 30]); out.drag = { time: e.time, x: e.doc.layers[1].x };' . "\n"
		. 'e = fake("motion"); e.time = 1200; press(e, 20, 20, { ctrlKey: true }); out.ctrl = { time: e.time, sel: e.sel, tab: e.motionTab };' . "\n"
		. 'console.log(JSON.stringify(out));',
		true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(0, $r['zero']['time'], 'a click at 0 keeps the playhead');
	pg_assert_same(array(8), $r['zero']['sel'], 'and selects the layer');
	pg_assert_same(1200, $r['shift']['time'], 'Shift and a click keep the playhead');
	pg_assert_same(array(8, 7), $r['shift']['sel'], 'and add the layer');
	pg_assert_same(null, $r['drag']['time'], 'a drag that moves a layer drops the playhead to rest');
	pg_assert_same(130, (int) $r['drag']['x'], 'and moves it at rest');
	pg_assert_same(1200, $r['ctrl']['time'], 'Ctrl and a click keep the playhead');
	pg_assert_same(array(7), $r['ctrl']['sel'], 'and select the layer');
	pg_assert_same('in', $r['ctrl']['tab'], 'without the key press of the Path tab');
}
