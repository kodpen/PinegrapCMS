<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's "Resize to other sizes" in the Canvas tool:
 * the sizes picked, a made canvas's name, the units (groups as one), the
 * place of a unit on the new board by its edge or its share, bars and
 * backgrounds, the letters and strokes scaled, the texts under the floor,
 * every scene resized (run under Node), and the labels of the part.
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
function image_editor_resize_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieresize');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The ticked presets in their order, the custom size only whole and in
// range, a size twice once, six at most; the name of a made canvas.
function test_image_editor_resize_sizes()
{
	$r = image_editor_resize_node(
		'console.log(JSON.stringify({'
		. ' order: M.resizeSizes(["banner", "square", "nope"], null),'
		. ' custom: M.resizeSizes([], { on: true, w: "800", h: 400 }),'
		. ' off: M.resizeSizes([], { on: false, w: 800, h: 400 }),'
		. ' bad: [M.resizeSizes([], { on: true, w: "", h: 400 }), M.resizeSizes([], { on: true, w: 10, h: 400 }), M.resizeSizes([], { on: true, w: 800.5, h: 400 }), M.resizeSizes([], { on: true, w: 9000, h: 400 })],'
		. ' twice: M.resizeSizes(["square"], { on: true, w: 1080, h: 1080 }),'
		. ' all: M.resizeSizes(["square", "portrait", "story", "share", "banner"], { on: true, w: 500, h: 500 }).length,'
		. ' names: [M.resizeName("Poster.png", 1080, 1920), M.resizeName("folder/Sale card", 1200, 630), M.resizeName("", 16, 16)]'
		. ' }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('square', 1080, 1080), array('banner', 1920, 600)), $r['order'], 'the presets in their own order');
	pg_assert_same(array(array('custom', 800, 400)), $r['custom'], 'a ticked custom size');
	pg_assert_same(array(), $r['off'], 'an unticked custom size is not made');
	pg_assert_same(array(array(), array(), array(), array()), $r['bad'], 'empty, too small, broken or too large sides are not made');
	pg_assert_same(array(array('square', 1080, 1080)), $r['twice'], 'a size twice once');
	pg_assert_same(6, $r['all'], 'six sizes at most');
	pg_assert_same(array('Poster 1080x1920', 'Sale card 1200x630', '16x16'), $r['names'], 'the name and the size');
}

// A unit by its middle: near an edge it keeps its distance from it times
// k, between it keeps its share of the side.
function test_image_editor_resize_axis()
{
	$r = image_editor_resize_node(
		'console.log(JSON.stringify({'
		. ' left: M.resizeAxis(60, 100, 1080, 1920, 0.5, 50),'
		. ' right: M.resizeAxis(920, 100, 1080, 1920, 0.5, 50),'
		. ' middle: M.resizeAxis(490, 100, 1080, 1920, 0.5, 50)'
		. ' }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(30, $r['left'], 'a margin from the left edge scales');
	pg_assert_same(1920 - 30 - 50, $r['right'], 'a margin from the right edge scales');
	pg_assert_same(960 - 25, $r['middle'], 'a centred unit stays centred');
}

// A square post made a banner: a title centred, a logo in a corner, a
// background picture covering, a bar stretching, a group as one, the
// letters and strokes by k, a text under the floor named, the scenes.
function test_image_editor_resize_doc()
{
	$r = image_editor_resize_node(
		'var doc = { artboard: { width: 1000, height: 1000, background: "#ffffff" }, selection: ["t"], layers: ['
		. ' { id: "bg", type: "image", x: 0, y: 0, w: 1000, h: 1000, rot: 0 },'
		. ' { id: "bar", type: "rect", x: 0, y: 900, w: 1000, h: 100, rot: 0, stroke: 4, radius: 10 },'
		. ' { id: "back", type: "rect", x: 0, y: 0, w: 1000, h: 1000, rot: 0 },'
		. ' { id: "t", type: "text", text: "Sale", x: 300, y: 450, w: 400, h: 100, rot: 0, size: 80 },'
		. ' { id: "logo", type: "svg", x: 850, y: 50, w: 100, h: 100, rot: 0 },'
		. ' { id: "fine", type: "text", text: "Terms", x: 400, y: 600, w: 200, h: 20, rot: 0, size: 20, anim: { keys: [{ t: 0, x: 100, y: 40 }] } },'
		. ' { id: "g1", type: "text", text: "A", grp: ["g"], x: 50, y: 400, w: 100, h: 50, rot: 0, size: 40 },'
		. ' { id: "g2", type: "rect", grp: ["g"], x: 50, y: 500, w: 100, h: 50, rot: 0 }'
		. ' ], scene: "s1", scenes: [{ id: "s1", layers: [] }, { id: "s2", layers: [{ id: "x", type: "text", text: "B", x: 450, y: 450, w: 100, h: 100, rot: 0, size: 10 }] }] };'
		. 'var r = M.resizeDoc(doc, 2000, 500), by = {}; r.doc.layers.forEach(function (l) { by[l.id] = l; });'
		. 'var units = M.resizeUnits(doc.layers).map(function (u) { return u.list.map(function (l) { return l.id; }); });'
		. 'console.log(JSON.stringify({ board: r.doc.artboard, selection: r.doc.hasOwnProperty("selection"), order: r.doc.layers.map(function (l) { return l.id; }), units: units, by: by,'
		. ' scene2: r.doc.scenes[1].layers[0], scene1: r.doc.scenes[0].layers, small: r.small, same: doc.layers[3].size, back: doc.artboard.width }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	$b = $r['by'];
	pg_assert_same(array('width' => 2000, 'height' => 500, 'background' => '#ffffff'), $r['board'], 'the new size, the background kept');
	pg_assert_false($r['selection'], 'no selection is carried');
	pg_assert_same(array('bg', 'bar', 'back', 't', 'logo', 'fine', 'g1', 'g2'), $r['order'], 'the layers keep their order');
	pg_assert_same(array(array('bg'), array('bar'), array('back'), array('t'), array('logo'), array('fine'), array('g1', 'g2')), $r['units'], 'a group is one unit');
	// k = min(2000 / 1000, 500 / 1000) = 0.5.
	pg_assert_same(array(0, -750, 2000, 2000), array($b['bg']['x'], $b['bg']['y'], $b['bg']['w'], $b['bg']['h']), 'a picture background covers the board, centred, never stretched');
	pg_assert_same(array(0, 0, 2000, 500), array($b['back']['x'], $b['back']['y'], $b['back']['w'], $b['back']['h']), 'a shape background stretches to the board');
	pg_assert_same(array(0, 450, 2000, 50), array($b['bar']['x'], $b['bar']['y'], $b['bar']['w'], $b['bar']['h']), 'a bar stretches across, its height by k at its edge');
	pg_assert_same(array(2, 5), array($b['bar']['stroke'], $b['bar']['radius']), 'a stroke and corners by k');
	pg_assert_same(array(900, 225, 200, 50, 40), array($b['t']['x'], $b['t']['y'], $b['t']['w'], $b['t']['h'], $b['t']['size']), 'a centred title stays centred, its letters by k');
	pg_assert_same(array(1925, 25, 50, 50), array($b['logo']['x'], $b['logo']['y'], $b['logo']['w'], $b['logo']['h']), 'a corner logo keeps its margins times k');
	pg_assert_same(10, $b['fine']['size'], 'a small text shrinks');
	pg_assert_same(array(50, 20), array($b['fine']['anim']['keys'][0]['x'], $b['fine']['anim']['keys'][0]['y']), 'motion keys move by the scale');
	// The group's box 50,400 100x150: its middle is in the first third
	// across (x 25) and the middle third down (its middle 475 at 237.5).
	pg_assert_same(array(25, 200, 50, 25, 20), array($b['g1']['x'], $b['g1']['y'], $b['g1']['w'], $b['g1']['h'], $b['g1']['size']), 'a group moves as one: its first layer');
	pg_assert_same(array(25, 250, 50, 25), array($b['g2']['x'], $b['g2']['y'], $b['g2']['w'], $b['g2']['h']), 'a group moves as one: its second layer');
	pg_assert_same(5, $r['scene2']['size'], 'a closed scene is resized too');
	pg_assert_same(array(), $r['scene1'], 'the open scene\'s entry is left as it is');
	pg_assert_same(array(array('id' => 'fine', 'scene' => '')), $r['small'], 'a text under the floor is named; one already under it is not');
	pg_assert_same(array(80, 1000), array($r['same'], $r['back']), 'the open document is not changed');
}

// A turned shape does not stretch; the part's labels are its own.
function test_image_editor_resize_turned_and_labels()
{
	$r = image_editor_resize_node(
		'var doc = { artboard: { width: 1000, height: 1000 }, layers: [{ id: "r", type: "rect", x: 0, y: 0, w: 1000, h: 1000, rot: 10 }] };'
		. 'var l = M.resizeDoc(doc, 2000, 500).doc.layers[0];'
		. 'console.log(JSON.stringify([l.w, l.h, l.rot]));'
	);

	if ($r !== null) {
		pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
		pg_assert_same(array(2000, 2000, 10), $r, 'a turned background covers instead of stretching');
	}

	require_once PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php';

	pg_assert_true(in_array('resize', pg_image_editor_parts(), true), 'the part is listed');

	$s = pg_image_editor_strings_resize();

	foreach (array('resizeOpen', 'resizeMake', 'resizeMade', 'resizeSmall', 'resizeShow', 'resizeNoFiles') as $key) {
		pg_assert_true(isset($s[$key]) && ($s[$key] !== ''), 'label ' . $key);
	}

	pg_assert_true(strpos($s['resizeMake'], '{n}') !== false, 'Make {n} sizes counts');
}

// The part is bundled after the Canvas tool's; on an editor Make saves
// each size as a copy beside the canvas's file (or into a new canvas's
// folder) named by its size, the texts under the floor kept for the line,
// and the open canvas left as it was.
function test_image_editor_resize_make()
{
	require_once PG_FUNCTIONS_DIR . '/includes/fn/output.php';

	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];
	$at = array_search('assets/js/pg_image_editor_resize.js', $files, true);

	pg_assert_true($at !== false, 'bundled');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_artboard.js', $files, true), 'after the Canvas tool');

	if (!function_exists('exec')) {
		return;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} }; global.navigator = { platform: "Linux" };' . "\n"
		. 'window.PgImageEditorModel = M;' . "\n";

	foreach (array('', '_layers', '_artboard', '_resize') as $part) {
		$head .= 'require(' . json_encode($js . 'pg_image_editor' . $part . '.js') . ');' . "\n";
	}

	$r = image_editor_resize_node($head
		. 'var X = window.PgImageEditor.parts, Editor = X.Editor, none = function () {};' . "\n"
		. 'X.t = function (k) { return "[" + k + "]"; };' . "\n"
		. 'var make = function (mode, opts) { var e = Object.create(Editor.prototype); e.opts = opts; e.saveMode = mode; e.tools = { list: ["select"] };' . "\n"
		. ' Editor.inits.forEach(function (fn) { try { fn.call(e); } catch (err) {} });' . "\n"
		. ' e.doc = { artboard: { width: 1000, height: 1000, background: "#ffffff" }, layers: [{ id: "t", type: "text", text: "Terms", x: 400, y: 600, w: 200, h: 20, rot: 0, size: 18 }] };' . "\n"
		. ' e.sent = []; e.layersReady = function () { return Promise.resolve(); }; e.renderPanel = e.hideMessage = e.showMessage = none;' . "\n"
		. ' e.sendBody = function (mode, to) { return { action: "save", mode: mode, folder_id: to ? to.folder.id : 0, doc: this.doc, export: { format: "webp" } }; };' . "\n"
		. ' e.request = function (b) { this.sent.push(b); return Promise.resolve({ status: "success", id: this.sent.length, name: "f" + this.sent.length }); };' . "\n"
		. ' return e; };' . "\n"
		. 'var a = make("replace", { fileId: 7, fileName: "Poster.png", onCreated: function (f) { a.created = f; } }), b = make("new", { save: { folderId: 3, name: "Card" } }), c = make("url", { save: { url: "x" } });' . "\n"
		. 'a.resizeState.ticks = ["banner", "square"]; b.resizeState.custom = { on: true, w: "500", h: "500" }; c.resizeState.ticks = ["square"];' . "\n"
		. 'a.resizeMake(); b.resizeMake(); c.resizeMake();' . "\n"
		. 'setTimeout(function () { console.log(JSON.stringify({'
		. ' a: a.sent.map(function (s) { return [s.mode, s.copy_name, s.doc.artboard.width, s.doc.artboard.height, s.hasOwnProperty("export")]; }),'
		. ' b: b.sent.map(function (s) { return [s.mode, s.name, s.folder_id, s.doc.artboard.width]; }), c: c.sent.length, target: c.resizeTarget(),'
		. ' result: a.resizeState.result, created: a.created, open: a.doc.artboard.width, busy: a.resizeState.busy })); }, 50);'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('copy', 'Poster 1080x1080', 1080, 1080, false), array('copy', 'Poster 1920x600', 1920, 600, false)), $r['a'], 'a copy a size beside the file, named by its size, no export settings');
	pg_assert_same(array(array('new', 'Card 500x500', 3, 500)), $r['b'], 'a new canvas into its folder');
	pg_assert_same(0, $r['c'], 'nothing is made where Files cannot be reached');
	pg_assert_same(null, $r['target'], 'no target on a channel\'s canvas');
	pg_assert_same(array('made' => 2, 'n' => 2, 'small' => array(array('id' => 't', 'scene' => '')), 'error' => false), $r['result'], 'the line\'s numbers and the small text');
	pg_assert_same(array(array('id' => 1, 'name' => 'f1', 'url' => ''), array('id' => 2, 'name' => 'f2', 'url' => '')), $r['created'], 'the caller hears of the files');
	pg_assert_same(array(1000, false), array($r['open'], $r['busy']), 'the open canvas is left as it was');
}
