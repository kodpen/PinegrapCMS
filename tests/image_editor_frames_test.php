<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's photo frames: the frame shapes (the four own
 * ones and the element set's filled badges), a frame placed on the board, a
 * picture put in it, the photo's place inside (cover, zoom, moved within its
 * edges, a turned frame), emptied again, the save's pictures, and the frames
 * part on an editor (run under Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and the shipped elements file as F and
// answers its JSON; null without Node.
function image_editor_frames_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieframes');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var F = JSON.parse(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/canvas/elements.json') . ', "utf8"));' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The shapes: circle, rounded square, arch and blob first, then the element
// set's filled badges but its circle; a stroked or other set's element and
// an unchecked list add nothing.
function test_image_editor_frame_shapes()
{
	$r = image_editor_frames_node(
		'var all = M.frameShapes(M.elementSets(F));'
		. 'console.log(JSON.stringify({ ids: all.map(function (s) { return s.id; }), first: all[0], labels: all.map(function (s) { return s.label; }),'
		. ' none: M.frameShapes(null).map(function (s) { return s.id; }),'
		. ' odd: M.frameShapes([{ id: "x", set: "badges", label: "elementX", w: 10, h: 10, d: "M0 0 Z", stroke: 2 }, { id: "y", set: "blobs", label: "elementY", w: 10, h: 10, d: "M0 0 Z", stroke: 0 }]).length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('circle', 'rounded', 'arch', 'blob', 'badge-seal', 'badge-burst', 'badge-shield', 'badge-hexagon', 'badge-rosette'), $r['ids'], 'own shapes, then the filled badges but the circle');
	pg_assert_same(array('circle', 'rounded', 'arch', 'blob'), $r['none'], 'the own four with no elements file');
	pg_assert_same(4, $r['odd'], 'a stroked badge or a blob element is no frame');
	pg_assert_same(array('id', 'label', 'w', 'h', 'd'), array_keys($r['first']), 'a shape is an id, a label and a path in its box');

	$strings = pg_image_editor_strings_frames() + pg_image_editor_strings_elements();

	foreach ($r['labels'] as $label) {
		pg_assert_true(isset($strings[$label]), $label . ' is labelled');
	}
}

// A placed frame: the one frame layer type, the shape's path and box, fitted
// into a share of the board about the point given, empty.
function test_image_editor_frame_layer()
{
	$r = image_editor_frames_node(
		'var arch = M.frameShapes([])[2];'
		. 'console.log(JSON.stringify({ l: M.frameLayer(arch, { x: 0, y: 0, w: 1000, h: 500 }, [500, 250]), arch: arch }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	$l = $r['l'];
	pg_assert_same(array('type' => 'frame', 'shape' => 'arch', 'd' => $r['arch']['d'], 'vw' => $r['arch']['w'], 'vh' => $r['arch']['h']),
		array('type' => $l['type'], 'shape' => $l['shape'], 'd' => $l['d'], 'vw' => $l['vw'], 'vh' => $l['vh']), 'the shape is kept in the layer');
	pg_assert_true(abs($l['h'] - 225) < 1e-9, 'its height is the board\'s share');
	pg_assert_true(abs($l['w'] / $l['h'] - $r['arch']['w'] / $r['arch']['h']) < 1e-9, 'the shape\'s proportions');
	pg_assert_true(abs($l['x'] + $l['w'] / 2 - 500) < 1e-9 && abs($l['y'] + $l['h'] / 2 - 250) < 1e-9, 'its middle on the point');
	pg_assert_same(array(0, 1), array($l['rot'], $l['opacity']), 'upright, opaque');
	pg_assert_false(isset($l['src']), 'empty');
}

// A picture put in a frame covers it, centred; zoom grows it from the
// middle; a move stays within the photo's edges, along the frame's own
// axes when it is turned; emptied, the frame keeps its place.
function test_image_editor_frame_fit_move_zoom()
{
	$r = image_editor_frames_node(
		'var f = { id: "l1", type: "frame", shape: "circle", d: "M0 0 Z", vw: 100, vh: 100, x: 100, y: 50, w: 200, h: 200, rot: 0, opacity: 1, fileName: "old.png" };'
		. 'var a = M.frameFill(f, { src: "/a.jpg", w: 800, h: 400, fileId: 7 }), b = M.frameFill(f, { src: "data:image/png;base64,AA", w: 100, h: 300, fileName: "p.png" });'
		. 'var right = Object.assign({}, a, M.frameMove(a, 50, 0)), far = Object.assign({}, a, M.frameMove(a, 500, 30));'
		. 'var turned = Object.assign({}, a, { rot: 90 }), down = M.frameMove(turned, 0, 50);'
		. 'var zoom = Object.assign({}, a, M.frameZoom(a, 2)), big = M.frameZoom(a, 9), small = M.frameZoom(a, 0.2);'
		. 'console.log(JSON.stringify({ a: a, b: b, fitA: M.frameFit(a), fitB: M.frameFit(b), empty: M.frameFit(f), right: M.frameFit(right), far: far.fx, farY: far.fy,'
		. ' down: down, zoom: M.frameFit(zoom), big: big, small: small, clear: M.frameClear(a), still: M.frameMove(b, 0, 40), range: M.FRAME_ZOOM }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('/a.jpg', 800, 400, 1, 0, 0, 7), array($r['a']['src'], $r['a']['pw'], $r['a']['ph'], $r['a']['zoom'], $r['a']['fx'], $r['a']['fy'], $r['a']['fileId']), 'a file picture, its size, centred');
	pg_assert_false(isset($r['a']['fileName']), 'a file picture drops a name from this computer');
	pg_assert_same(array('p.png', false), array($r['b']['fileName'], isset($r['b']['fileId'])), 'a picture from this computer is named');
	pg_assert_same(array('x' => 0, 'y' => 50, 'w' => 400, 'h' => 200), $r['fitA'], 'a wide picture covers the frame by its height, centred');
	pg_assert_same(array('x' => 100, 'y' => -150, 'w' => 200, 'h' => 600), $r['fitB'], 'a tall one by its width');
	pg_assert_same(null, $r['empty'], 'an empty frame has no photo');
	pg_assert_same(50, $r['right']['x'], 'moved right by 50');
	pg_assert_same(array(1, 0), array($r['far'], $r['farY']), 'never past its edge, nor across a side it covers exactly');
	pg_assert_same(array('fx' => 0.5, 'fy' => 0), $r['down'], 'a turned frame moves along its own axes');
	pg_assert_same(array('x' => -200, 'y' => -50, 'w' => 800, 'h' => 400), $r['zoom'], 'zoomed about the middle');
	pg_assert_same(array('zoom' => 4), $r['big'], 'zoom at most four');
	pg_assert_same(array('zoom' => 1), $r['small'], 'never under covering the frame');
	pg_assert_same(array(1, 4), $r['range'], 'the zoom range');
	pg_assert_same(array('fx' => 0, 'fy' => 0.2), $r['still'], 'a fraction of the room it has');

	$clear = $r['clear'];
	pg_assert_false(isset($clear['src']) || isset($clear['pw']) || isset($clear['zoom']) || isset($clear['fileId']) || isset($clear['fx']), 'emptied');
	pg_assert_same(array(100, 50, 200, 200, 'circle'), array($clear['x'], $clear['y'], $clear['w'], $clear['h'], $clear['shape']), 'in its place');
}

// A save into Files sends a frame's picture from this computer, as a
// picture layer's, and points the frame at the file it was kept as.
function test_image_editor_frame_save_pictures()
{
	$r = image_editor_frames_node(
		'var doc = { layers: [{ id: "l1", type: "frame", src: "data:image/jpeg;base64,AA", fileName: "a.jpg" }, { id: "l2", type: "frame", src: "/x.png", fileId: 3 }, { id: "l3", type: "frame" }] };'
		. 'var list = M.animPictures(doc); M.picturesTaken(doc, { l1: { id: 9, url: "/a.jpg" } });'
		. 'console.log(JSON.stringify({ list: list, l1: doc.layers[0] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('id' => 'l1', 'data' => 'data:image/jpeg;base64,AA', 'name' => 'a.jpg')), $r['list'], 'only the picture with no file yet');
	pg_assert_same(array('id' => 'l1', 'type' => 'frame', 'src' => '/a.jpg', 'fileId' => 9), $r['l1'], 'pointed at its file');
}

// The part is listed and bundled after the elements, its labels there.
function test_image_editor_frames_part()
{
	pg_assert_true(in_array('frames', pg_image_editor_parts(), true), 'the part is listed');

	$strings = pg_image_editor_strings_frames();

	foreach (array('elementSet_frames', 'framePicture', 'frameChoose', 'frameMove', 'frameMoving', 'frameZoom', 'frameRemove', 'frameError', 'frameCircle', 'frameRounded', 'frameArch', 'frameBlob') as $key) {
		pg_assert_true(isset($strings[$key]), $key . ' is labelled');
	}

	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];
	$at = array_search('assets/js/pg_image_editor_frames.js', $files, true);

	pg_assert_true($at !== false, 'bundled');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_elements.js', $files, true), 'after the elements');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_starts.js', $files, true), 'after the "Your picture" box');
}

// On an editor: a tile press puts an empty frame, selected, in one step; a
// picture from Files picked for it fills it; a double press enters the
// photo's moving, a drag moves the photo in one step, the zoom slider's
// value zooms it, Escape ends; a picture dropped on a frame fills it.
function test_image_editor_frames_editor()
{
	if (!function_exists('exec')) {
		return;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} }; global.navigator = { platform: "Linux" };' . "\n"
		. 'window.PgImageEditorModel = M;' . "\n";

	foreach (array('', '_layers', '_sticker', '_svg', '_glyph', '_elements', '_frames') as $part) {
		$head .= 'require(' . json_encode($js . 'pg_image_editor' . $part . '.js') . ');' . "\n";
	}

	$r = image_editor_frames_node($head
		. 'var X = window.PgImageEditor.parts, Editor = X.Editor, none = function () {};' . "\n"
		. 'X.t = function (k) { return "[" + k + "]"; };' . "\n"
		. 'var e = Object.create(Editor.prototype); e.opts = {}; e.tools = { list: ["sticker", "image"] }; e.tool = "select";' . "\n"
		. 'Editor.inits.forEach(function (fn) { try { fn.call(e); } catch (err) {} });' . "\n"
		. 'e.doc = M.newDocument({ blank: { width: 1000, height: 500 } }); e.doc.layers = [];' . "\n"
		. 'e.sel = []; e.steps = 0; e.history = { key: null }; e.render = e.renderView = e.renderPanel = none; e.view = { k: 1 };' . "\n"
		. 'e.change = function (fn, key) { if (!key || key !== this.history.key) { this.steps++; } this.history.key = key || null; fn(this.doc); };' . "\n"
		. 'e.select = function (ids) { this.sel = ids; }; e.stickerCentre = function () { return [500, 250]; };' . "\n"
		. 'e.layer = function (id) { return this.doc.layers.filter(function (l) { return l.id === id; })[0] || null; };' . "\n"
		. 'e.picture = function (src) { return Promise.resolve({ naturalWidth: 800, naturalHeight: 400, src: src }); };' . "\n"
		. 'e.toDoc = function (x, y) { return [x, y]; }; e.stage = { setPointerCapture: none };' . "\n"
		. 'var out = {};' . "\n"
		. 'e.addFrame(M.frameShapes([])[0]); var l = e.doc.layers[0];' . "\n"
		. 'out.add = { steps: e.steps, sel: e.sel, type: l.type, name: l.name, empty: !l.src };' . "\n"
		. 'e.framePick(l.id); out.pick = [e.tool, e.frameTarget];' . "\n"
		. 'e.addFilePicture({ id: 5, url: "/f.jpg", name: "f.jpg" });' . "\n"
		. 'setTimeout(function () {' . "\n"
		. '  l = e.doc.layers[0]; out.fill = { steps: e.steps, src: l.src, fileId: l.fileId, pw: l.pw, count: e.doc.layers.length, target: e.frameTarget };' . "\n"
		. '  e.tool = "select"; var c = [l.x + l.w / 2, l.y + l.h / 2];' . "\n"
		. '  out.edit = Editor.layerTypes.frame.edit.call(e, l) !== false && e.frameEdit === l.id;' . "\n"
		. '  var ev = function (x, y) { return { clientX: x, clientY: y, pointerId: 1, preventDefault: none, type: "pointerup" }; };' . "\n"
		. '  var before = e.steps; Editor.pointers.select.down.call(e, ev(c[0], c[1])); Editor.pointers.select.move.call(e, ev(c[0] + 10, c[1])); Editor.pointers.select.move.call(e, ev(c[0] + 20, c[1])); Editor.pointers.select.up.call(e, ev(c[0] + 20, c[1]));' . "\n"
		. '  out.move = { steps: e.steps - before, fx: e.doc.layers[0].fx, x: l.x };' . "\n"
		. '  e.frameZoomTo(l.id, 2); out.zoom = e.doc.layers[0].zoom;' . "\n"
		. '  out.esc = Editor.escapes.some(function (fn) { return fn.call(e); }) && !e.frameEdit;' . "\n"
		. '  e.doc.layers.push({ id: "l9", type: "frame", shape: "circle", d: "M0 0 Z", vw: 100, vh: 100, x: 0, y: 0, w: 100, h: 100, rot: 0, opacity: 1 });' . "\n"
		. '  e.frameDropAt(50, 50); global.FileReader = function () { var r = this; r.readAsDataURL = function () { r.result = "data:image/png;base64,AA"; r.onload(); }; };' . "\n"
		. '  e.addPicture({ name: "drop.png", type: "image/png" });' . "\n"
		. '  setTimeout(function () { var d = e.doc.layers[1]; out.drop = { src: d.src, name: d.fileName, pw: d.pw, count: e.doc.layers.length }; console.log(JSON.stringify(out)); }, 20);' . "\n"
		. '}, 20);'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('steps' => 1, 'sel' => array('l0'), 'type' => 'frame', 'name' => '[frameCircle]', 'empty' => true), $r['add'], 'an empty frame, named, selected, one step');
	pg_assert_same(array('image', 'l0'), $r['pick'], 'Choose a picture opens Picture from Files for it');
	pg_assert_same(array('steps' => 2, 'src' => '/f.jpg', 'fileId' => 5, 'pw' => 800, 'count' => 1, 'target' => null), $r['fill'], 'the file fills the frame in one step');
	pg_assert_true($r['edit'], 'a double press on a filled frame moves its photo');
	pg_assert_same(1, $r['move']['steps'], 'a drag is one step');
	pg_assert_true($r['move']['fx'] > 0, 'the photo moved right');
	pg_assert_same(2, $r['zoom'], 'zoomed');
	pg_assert_true($r['esc'], 'Escape ends the moving');
	pg_assert_same(array('src' => 'data:image/png;base64,AA', 'name' => 'drop.png', 'pw' => 800, 'count' => 2), $r['drop'], 'a picture dropped on a frame fills it');
}
