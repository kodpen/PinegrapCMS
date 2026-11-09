<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for dropping the image editor's area selection (the marching ants)
 * outside Select area (assets/js/pg_image_editor.js, pg_image_editor_layers.js,
 * pg_image_editor_area.js and pg_image_editor_artboard.js, run under Node on
 * an editor without a page). With Select a click outside the selected area
 * deselects it, whatever the click selects; a click inside it, a drag, Shift,
 * Alt, Ctrl or Cmd, a cancelled pointer, a long press and the context menu's
 * press keep it. Escape clears it after the layers Select outlines and before
 * layers a tool holds without showing them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M, the Select tool's pointers as P and a
// bare editor from fake(tool, unlocked): a 200 x 100 photo, locked or on an
// unlocked canvas, with the square 20..70 selected. click(e, x, y, keys)
// presses and lets go at the same point and answers whether the area is
// still selected. Answers the script's JSON, null without Node.
function image_editor_deselect_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiedesel');

	if ($file === false) {
		return null;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} };' . "\n"
		. 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . '); window.PgImageEditorModel = window.PgImageEditorModel || M;' . "\n"
		. 'require(' . json_encode($js . 'pg_image_editor.js') . '); require(' . json_encode($js . 'pg_image_editor_layers.js') . ');' . "\n"
		. 'require(' . json_encode($js . 'pg_image_editor_area.js') . '); require(' . json_encode($js . 'pg_image_editor_artboard.js') . ');' . "\n"
		. 'var Editor = window.PgImageEditor.parts.Editor, P = Editor.pointers.select, none = function () {};' . "\n"
		. 'function fake(tool, unlocked) {' . "\n"
		. ' var e = Object.create(Editor.prototype), doc = M.newDocument({ fileId: 1 });' . "\n"
		. ' doc.source.width = 200; doc.source.height = 100; e.doc = unlocked ? M.lockPicture(doc, false) : doc;' . "\n"
		. ' e.doc.selection = { ops: [{ op: "add", kind: "rect", ring: M.areaRect([20, 20], [70, 70]) }], invert: false, feather: 0 };' . "\n"
		. ' e.tool = tool || "select"; e.sel = []; e.pointers = {}; e.view = { k: 1, x: 0, y: 0 }; e.history = {}; e.ready = true; e.board = {}; e.log = [];' . "\n"
		. ' e.stage = { setPointerCapture: none }; e.toDoc = function (x, y) { return [x, y]; };' . "\n"
		. ' e.render = e.renderView = none; e.change = function (fn) { fn(this.doc); }; e.cropping = function () { return false; };' . "\n"
		. ' e.setFull = function (on) { this.full = !!on; this.log.push("full"); }; e.requestClose = function () { this.log.push("close"); };' . "\n"
		. ' return e;' . "\n"
		. '}' . "\n"
		. 'function ev(type, x, y, keys) {' . "\n"
		. ' var o = { type: type, clientX: x, clientY: y, button: 0, pointerId: 1, pointerType: "mouse", shiftKey: false, altKey: false, ctrlKey: false, metaKey: false, target: {}, preventDefault: none };' . "\n"
		. ' Object.keys(keys || {}).forEach(function (k) { o[k] = keys[k]; });' . "\n"
		. ' return o;' . "\n"
		. '}' . "\n"
		. 'function click(e, x, y, keys) { P.down.call(e, ev("pointerdown", x, y, keys)); P.up.call(e, ev("pointerup", x, y, keys)); return !!e.doc.selection; }' . "\n"
		. 'function picture(id, x, y, w, h) { return { id: id, type: "image", src: "a.png", x: x, y: y, w: w, h: h, rot: 0, opacity: 1 }; }' . "\n";

	file_put_contents($file, $head . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A click with Select on an empty spot outside the ants deselects the area;
// inside them, with a key held, or as a drag it stays.
function test_image_editor_select_click_outside_drops_area()
{
	$r = image_editor_deselect_node(
		'var out = {};' . "\n"
		. 'out.outside = click(fake(), 150, 50);' . "\n"
		. 'out.offPicture = click(fake(), 260, 140);' . "\n"
		. 'out.inside = click(fake(), 40, 40);' . "\n"
		. 'out.shift = click(fake(), 150, 50, { shiftKey: true });' . "\n"
		. 'out.alt = click(fake(), 150, 50, { altKey: true });' . "\n"
		. 'out.ctrl = click(fake(), 150, 50, { ctrlKey: true });' . "\n"
		. 'out.meta = click(fake(), 150, 50, { metaKey: true });' . "\n"
		. 'var e = fake(); P.down.call(e, ev("pointerdown", 150, 50)); P.move.call(e, ev("pointermove", 180, 80)); P.up.call(e, ev("pointerup", 180, 80)); out.drag = !!e.doc.selection;' . "\n"
		. 'e = fake(); P.down.call(e, ev("pointerdown", 150, 50)); P.move.call(e, ev("pointermove", 190, 50)); P.up.call(e, ev("pointerup", 150, 50)); out.back = !!e.doc.selection;' . "\n"
		. 'e = fake(); P.down.call(e, ev("pointerdown", 150, 50)); P.move.call(e, ev("pointermove", 152, 51)); P.up.call(e, ev("pointerup", 152, 51)); out.jitter = !!e.doc.selection;' . "\n"
		. 'e = fake(); e.sel = [7]; e.doc.layers.push(picture(7, 120, 10, 40, 20)); out.clearsLayers = click(e, 150, 80); out.sel = e.sel;' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_false($r['outside'], 'a click on the picture outside the area deselects it');
	pg_assert_false($r['offPicture'], 'a click on the stage beside the picture deselects it');
	pg_assert_true($r['inside'], 'a click inside the area keeps it');
	pg_assert_true($r['shift'], 'Shift keeps the area');
	pg_assert_true($r['alt'], 'Alt keeps the area');
	pg_assert_true($r['ctrl'], 'Ctrl keeps the area');
	pg_assert_true($r['meta'], 'Cmd keeps the area');
	pg_assert_true($r['drag'], 'a selection box drawn outside keeps the area');
	pg_assert_true($r['back'], 'a drag that comes back to where it began is still a drag');
	pg_assert_false($r['jitter'], 'a press that moves under 3 px is a click');
	pg_assert_false($r['clearsLayers'], 'an empty spot clears the layers and the area together');
	pg_assert_same(array(), $r['sel'], 'the layers were cleared too');
}

// A click on a layer outside the ants selects it and deselects the area;
// a layer inside them is selected and the area stays.
function test_image_editor_select_click_on_layer_outside_drops_area()
{
	$r = image_editor_deselect_node(
		'var out = {}, e = fake();' . "\n"
		. 'e.doc.layers.push(picture(7, 120, 10, 40, 20), picture(8, 30, 30, 20, 20));' . "\n"
		. 'out.outside = click(e, 140, 20); out.outsideSel = e.sel;' . "\n"
		. 'e = fake(); e.doc.layers.push(picture(7, 120, 10, 40, 20), picture(8, 30, 30, 20, 20));' . "\n"
		. 'out.inside = click(e, 40, 40); out.insideSel = e.sel;' . "\n"
		. 'e = fake(); e.doc.layers.push(picture(7, 120, 10, 40, 20));' . "\n"
		. 'P.down.call(e, ev("pointerdown", 140, 20)); P.move.call(e, ev("pointermove", 160, 40)); P.up.call(e, ev("pointerup", 160, 40));' . "\n"
		. 'out.moved = !!e.doc.selection; out.movedX = e.doc.layers[0].x;' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_false($r['outside'], 'a click on a layer outside the area deselects the area');
	pg_assert_same(array(7), $r['outsideSel'], 'and selects that layer');
	pg_assert_true($r['inside'], 'a click on a layer inside the area keeps it');
	pg_assert_same(array(8), $r['insideSel'], 'and selects that layer');
	pg_assert_true($r['moved'], 'moving a layer that lies outside keeps the area');
	pg_assert_same(140, (int) $r['movedX'], 'the layer moved');
}

// On an unlocked canvas a click on the picture outside the ants selects the
// picture and deselects the area.
function test_image_editor_select_click_on_picture_drops_area()
{
	$r = image_editor_deselect_node(
		'var e = fake("select", true), out = {};' . "\n"
		. 'out.on = e.pictureOn(); out.kept = click(e, 150, 50); out.pic = !!e.board.pic;' . "\n"
		. 'e = fake("select", true); out.inside = click(e, 40, 40);' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['on'], 'the picture is Select\'s to move');
	pg_assert_false($r['kept'], 'a click on the picture outside the area deselects it');
	pg_assert_true($r['pic'], 'and selects the picture');
	pg_assert_true($r['inside'], 'a click on the picture inside the area keeps it');
}

// What is not a click keeps the area: a cancelled pointer, a long press
// (the menu ends the press with its own pointerdown), the context menu's
// press (endDrag() with no event), and another tool that presses as Select.
function test_image_editor_not_a_click_keeps_area()
{
	$r = image_editor_deselect_node(
		'var out = {}, e = fake(), down;' . "\n"
		. 'P.down.call(e, ev("pointerdown", 150, 50)); P.up.call(e, ev("pointercancel", 150, 50)); out.cancel = !!e.doc.selection;' . "\n"
		. 'e = fake(); down = ev("pointerdown", 150, 50); P.down.call(e, down); P.up.call(e, down); out.longPress = !!e.doc.selection;' . "\n"
		. 'e = fake(); P.down.call(e, { clientX: 150, clientY: 50, target: {}, shiftKey: false, button: 0, pointerId: -1, preventDefault: none }); e.endDrag(); out.menu = !!e.doc.selection;' . "\n"
		. 'e = fake("motion"); out.motion = click(e, 150, 50);' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['cancel'], 'a cancelled pointer keeps the area');
	pg_assert_true($r['longPress'], 'a long press for the menu keeps the area');
	pg_assert_true($r['menu'], 'the context menu\'s press keeps the area');
	pg_assert_true($r['motion'], 'a click with another tool keeps the area');
}

// Escape: with Select the selected layers go first, then the area, then the
// full screen; with Select area the area goes before layers it does not
// show. A selection with no area left is passed over.
function test_image_editor_escape_drops_area()
{
	$r = image_editor_deselect_node(
		'var out = {}, e = fake(); e.sel = [7]; e.full = true;' . "\n"
		. 'e.onEscape(); out.first = [e.sel.length, !!e.doc.selection, e.log.slice()];' . "\n"
		. 'e.onEscape(); out.second = [e.sel.length, !!e.doc.selection, e.log.slice()];' . "\n"
		. 'e.onEscape(); out.third = [e.sel.length, !!e.doc.selection, e.log.slice()];' . "\n"
		. 'e = fake("area"); e.sel = [7];' . "\n"
		. 'e.onEscape(); out.areaFirst = [e.sel.length, !!e.doc.selection];' . "\n"
		. 'e.onEscape(); out.areaSecond = [e.sel.length, !!e.doc.selection];' . "\n"
		. 'e = fake(); e.doc.selection = { ops: [], invert: false, feather: 0 }; e.onEscape(); out.empty = e.log;' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, true, array()), $r['first'], 'Select: the layers go first');
	pg_assert_same(array(0, false, array()), $r['second'], 'then the area');
	pg_assert_same(array(0, false, array('full')), $r['third'], 'then the full screen');
	pg_assert_same(array(1, false), $r['areaFirst'], 'Select area: the area goes first');
	pg_assert_same(array(0, false), $r['areaSecond'], 'then the layers it does not show');
	pg_assert_same(array('close'), $r['empty'], 'a selection without an area is passed over');
}
