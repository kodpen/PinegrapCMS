<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's "Start from a design" in the New image
 * dialog: the check of the designs index and of a design file, the purpose
 * filter, a text's fit and place, the "Your picture" box and the cut of a
 * picture filling it (run under Node), the shipped designs against the
 * labels and the font catalogue, and a design opened on an editor.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M, the fonts catalogue as G and the
// shipped index as I and answers its JSON; null without Node.
function image_editor_starts_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiestarts');

	if ($file === false) {
		return null;
	}

	$dir = PG_FUNCTIONS_DIR . '/assets/canvas/';

	file_put_contents($file, 'global.window = global;' . "\n"
		. 'require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_google_fonts.js') . '); var G = window.PgGoogleFonts;' . "\n"
		. 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var fs = require("fs"), I = JSON.parse(fs.readFileSync(' . json_encode($dir . 'designs.json') . ', "utf8"));' . "\n"
		. 'var D = function (id) { return JSON.parse(fs.readFileSync(' . json_encode($dir . 'designs/') . ' + id + ".json", "utf8")); };' . "\n"
		. 'var known = function (f) { return G.all().indexOf(f) >= 0; };' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The index: an id [a-z0-9-] once, a purpose but 'all', a label key start…;
// the purposes in the chips' order; the filter.
function test_image_editor_starts_index()
{
	$r = image_editor_starts_node(
		'var d = function (id, o) { return Object.assign({ id: id, purpose: "sale", label: "startX" }, o || {}); };'
		. 'var list = M.startIndex({ designs: [d("a"), d("a"), d("B b"), d("all", { purpose: "all" }), d("p", { purpose: "poster" }), d("k", { label: "elementX" }), d("q", { purpose: "quote" }), null] });'
		. 'var many = []; for (var i = 0; i < 70; i++) { many.push(d("d" + i)); }'
		. 'console.log(JSON.stringify({ list: list, many: M.startIndex({ designs: many }).length, none: M.startIndex(null), purposes: M.START_PURPOSES,'
		. ' quote: M.startPurposeList(list, "quote").map(function (x) { return x.id; }), all: M.startPurposeList(list, "all").length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('id' => 'a', 'purpose' => 'sale', 'label' => 'startX'), array('id' => 'q', 'purpose' => 'quote', 'label' => 'startX')), $r['list'], 'only well-formed entries, ids once');
	pg_assert_same(60, $r['many'], 'sixty at most');
	pg_assert_same(array(), $r['none'], 'no file is no design');
	pg_assert_same(array('all', 'post', 'story', 'banner', 'announcement', 'sale', 'event', 'quote', 'invitation'), $r['purposes'], 'the chips in order');
	pg_assert_same(array('q'), $r['quote'], 'a chip shows its purpose');
	pg_assert_same(2, $r['all'], 'All shows every design');
}

// A design file: its id must be the entry's, its size 16-8000, every layer
// well formed (one bad layer refuses the design); a text's font must be in
// the catalogue; defaults fill what a layer leaves out.
function test_image_editor_starts_design()
{
	$r = image_editor_starts_node(
		'var e = { id: "x", purpose: "sale", label: "startX" };'
		. 'var text = function (o) { return Object.assign({ type: "text", text: "startHead", font: "g:Montserrat", size: 60, weight: 700, color: "#FF0000", align: "center", x: 500, y: 100 }, o || {}); };'
		. 'var f = function (layers, o) { return Object.assign({ id: "x", width: 1000, height: 800, background: "#ABCDEF", layers: layers }, o || {}); };'
		. 'var good = M.startDesign(f([{ type: "slot", x: 0, y: 0, w: 400, h: 300, radius: 20 }, { type: "rect", x: 10, y: 10, w: 50, h: 50, color: "#000000", radius: 99 },'
		. ' { type: "star", x: 1, y: 1, w: 9, h: 9, color: "#000000" }, text()]), e, known);'
		. 'var bad = function (layer, o) { return M.startDesign(f([layer], o), e, known); };'
		. 'console.log(JSON.stringify({ good: good, wrongId: M.startDesign(f([text()], { id: "y" }), e, known), noLayers: M.startDesign(f([]), e, known),'
		. ' size: bad(text(), { width: 9000 }), font: bad(text({ font: "g:Not A Font" })), sysfont: bad(text({ font: "serif" })).layers[0].font, key: bad(text({ text: "Hello" })),'
		. ' color: bad(text({ color: "red" })), outside: bad(text({ x: 2000 })), shape: bad({ type: "path", x: 0, y: 0, w: 5, h: 5, color: "#000000" }),'
		. ' empty: bad({ type: "rect", x: 0, y: 0, w: 5, h: 5, color: "#000000", fill: "none" }), transparent: bad(text(), { background: "transparent" }).background,'
		. ' room: [bad(text({ align: "left", x: 100 })).layers[0].maxW, bad(text({ align: "right", x: 100 })).layers[0].maxW, bad(text({ x: 300 })).layers[0].maxW] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(1000, 800, '#abcdef', 'sale', 'startX'), array($r['good']['width'], $r['good']['height'], $r['good']['background'], $r['good']['purpose'], $r['good']['label']), 'size, colour and the entry\'s purpose and label');
	$slot = $r['good']['layers'][0];
	ksort($slot);
	pg_assert_same(array('h' => 300, 'opacity' => 1, 'radius' => 20, 'rot' => 0, 'type' => 'slot', 'w' => 400, 'x' => 0, 'y' => 0), $slot, 'a slot');
	pg_assert_same(array(0, 'solid', 0, 'solid'), array($r['good']['layers'][1]['radius'], $r['good']['layers'][1]['fill'], $r['good']['layers'][1]['stroke'], $r['good']['layers'][1]['dash']), 'a rect: a radius beyond half a side dropped, solid fill');
	pg_assert_same(array(5, 0.48), array($r['good']['layers'][2]['points'], $r['good']['layers'][2]['inner']), 'a star\'s defaults');
	$t = $r['good']['layers'][3];
	pg_assert_same(array('startHead', 'g:Montserrat', '#ff0000', 'center', 1.2, 0, false, 1000), array($t['text'], $t['font'], $t['color'], $t['align'], $t['lineHeight'], $t['spacing'], $t['italic'], $t['maxW']), 'a text with its defaults');
	foreach (array('wrongId', 'noLayers', 'size', 'font', 'key', 'color', 'outside', 'shape', 'empty') as $k) {
		pg_assert_same(null, $r[$k], $k . ' refuses the design');
	}
	pg_assert_same('serif', $r['sysfont'], 'a system font is kept');
	pg_assert_same('transparent', $r['transparent'], 'a transparent board');
	pg_assert_same(array(900, 100, 600), $r['room'], 'the room a text has by its align');
}

// A text's layer, fit and place; the "Your picture" box; a picture's cut.
function test_image_editor_starts_layers()
{
	$r = image_editor_starts_node(
		'var d = { type: "text", text: "startHead", font: "serif", size: 60, weight: 700, italic: true, align: "center", color: "#112233", lineHeight: 1.1, spacing: 0.1, opacity: 1, x: 500, y: 40 };'
		. 'var l = M.startText(d, "Hello"), slot = M.startSlotLayer({ x: 10, y: 20, w: 600, h: 300, radius: 30, rot: 0, opacity: 1 }, "Your <picture>");'
		. 'console.log(JSON.stringify({ text: [l.type, l.text, l.font, l.size, l.weight, l.italic, l.align, l.color, l.lineHeight, l.spacing, l.rot],'
		. ' fit: [M.startTextFit(60, 400, 500), M.startTextFit(60, 1000, 500), M.startTextFit(60, 10000, 500)],'
		. ' place: [M.startTextPlace(d, 200), M.startTextPlace({ align: "left", x: 50, y: 7 }, 200), M.startTextPlace({ align: "right", x: 950, y: 7 }, 200)],'
		. ' slot: [slot.type, slot.kind, slot.slot, slot.name, slot.x, slot.y, slot.w, slot.h, slot.vb], escaped: slot.markup.indexOf("Your &lt;picture&gt;") > 0, dashed: slot.markup.indexOf("stroke-dasharray") > 0, rx: slot.markup.indexOf(\'rx="30"\') > 0,'
		. ' cover: [M.startCover(4000, 2000, 300, 300), M.startCover(1000, 3000, 400, 200), M.startCover(100, 100, 50, 100)] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('text', 'Hello', 'serif', 60, 700, true, 'center', '#112233', 1.1, 0.1, 0), $r['text'], 'a text layer with the words');
	pg_assert_same(array(60, 30, 8), $r['fit'], 'a wide translation gets smaller, never under 8 px');
	pg_assert_same(array(array('x' => 400, 'y' => 40), array('x' => 50, 'y' => 7), array('x' => 750, 'y' => 7)), $r['place'], 'the anchor by the align');
	pg_assert_same(array('svg', 'g', true, 'Your <picture>', 10, 20, 600, 300, array('x' => 0, 'y' => 0, 'w' => 600, 'h' => 300)), $r['slot'], 'the box as an svg layer marked slot');
	pg_assert_true($r['escaped'] && $r['dashed'] && $r['rx'], 'dashed, rounded, its words escaped');
	pg_assert_same(array('sx' => 1000, 'sy' => 0, 'sw' => 2000, 'sh' => 2000, 'w' => 2000, 'h' => 2000), $r['cover'][0], 'a wide picture cut to a square in its middle');
	pg_assert_same(array('sx' => 0, 'sy' => 1250, 'sw' => 1000, 'sh' => 500, 'w' => 1000, 'h' => 500), $r['cover'][1], 'a tall picture cut to a wide box');
	pg_assert_same(array('sx' => 25, 'sy' => 0, 'sw' => 50, 'sh' => 100, 'w' => 50, 'h' => 100), $r['cover'][2], 'never more pixels than the picture has');
}

// The shipped designs: six, each in the index, passing the check with its
// fonts in the catalogue, every text and name a label, a "Your picture" box
// where a photo goes, and the size a preset of the dialog.
function test_image_editor_starts_shipped()
{
	$r = image_editor_starts_node(
		'var out = M.startIndex(I).map(function (e) { var d = M.startDesign(D(e.id), e, known);'
		. ' return { id: e.id, purpose: e.purpose, label: e.label, ok: !!d, preset: d ? M.boardPreset(d.width, d.height) : "",'
		. ' texts: d ? d.layers.filter(function (l) { return l.type === "text"; }).map(function (l) { return l.text; }) : [],'
		. ' slots: d ? d.layers.filter(function (l) { return l.type === "slot"; }).length : 0 }; });'
		. 'console.log(JSON.stringify({ raw: I.designs.length, list: out }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(6, $r['raw'], 'six designs in the index');
	pg_assert_same(6, count($r['list']), 'every entry passes the check');

	$strings = pg_image_editor_strings_starts();
	$presets = array();

	foreach ($r['list'] as $d) {
		pg_assert_true($d['ok'], $d['id'] . ': its file passes the check');
		pg_assert_true(isset($strings[$d['label']]), $d['id'] . ': its name is a label');
		pg_assert_true(isset($strings['startPurpose_' . $d['purpose']]), $d['id'] . ': its purpose has a chip');
		pg_assert_true($d['preset'] !== 'custom', $d['id'] . ': a size of the dialog');
		pg_assert_true(count($d['texts']) >= 3, $d['id'] . ': three texts or more');
		$presets[$d['preset']] = true;

		foreach ($d['texts'] as $key) {
			pg_assert_true(isset($strings[$key]), $d['id'] . ': ' . $key . ' is a label');
		}
	}

	pg_assert_same(5, count($presets), 'the six designs use the five sizes');
}

// The part is listed, bundled after the svg layers and before the menu,
// and its addresses reach the editor; the dialog has its head row hook.
function test_image_editor_starts_part()
{
	pg_assert_true(in_array('starts', pg_image_editor_parts(), true), 'the part is listed');

	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];
	$at = array_search('assets/js/pg_image_editor_starts.js', $files, true);

	pg_assert_true($at !== false, 'bundled');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_svg.js', $files, true), 'after the svg layers');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_artboard.js', $files, true), 'after the New image dialog');

	$urls = pg_image_editor_starts_urls();

	pg_assert_true(strpos($urls['index'], 'assets/canvas/designs.json?v=') !== false, 'the index address');
	pg_assert_true(substr($urls['base'], -strlen('assets/canvas/designs/')) === 'assets/canvas/designs/', 'the designs folder');
}

// A design opened on an editor: its board, its texts in the panel's words,
// fitted and placed, its box an svg slot; a picture fills the box as a
// picture layer in its place, in one undo step.
function test_image_editor_starts_open()
{
	if (!function_exists('exec')) {
		return;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.document = { activeElement: null, addEventListener: function () {} }; global.navigator = { platform: "Linux" };' . "\n"
		. 'window.PgImageEditorModel = M;' . "\n";

	foreach (array('', '_layers', '_sticker', '_artboard', '_svg', '_starts') as $part) {
		$head .= 'require(' . json_encode($js . 'pg_image_editor' . $part . '.js') . ');' . "\n";
	}

	$r = image_editor_starts_node($head
		. 'var X = window.PgImageEditor.parts, Editor = X.Editor, none = function () {};' . "\n"
		. 'X.t = function (k) { return "[" + k + "]"; };' . "\n"
		. 'var e = Object.create(Editor.prototype); e.opts = {}; e.tools = { list: ["select", "image"] };' . "\n"
		. 'Editor.inits.forEach(function (fn) { try { fn.call(e); } catch (err) {} });' . "\n"
		. 'e.doc = M.newDocument({ blank: { width: 1080, height: 1080 } });' . "\n"
		. 'e.textLayout = function (l) { return { w: l.text.length * l.size * 0.5, h: l.size * 1.2 }; };' . "\n"
		. 'var entry = M.startIndex(I).filter(function (x) { return x.id === "sale-square"; })[0], design = M.startDesign(D(entry.id), entry, known);' . "\n"
		. 'var made = e.startDocument(design), texts = made.layers.filter(function (l) { return l.type === "text"; }), slot = made.layers.filter(function (l) { return l.slot; })[0];' . "\n"
		. 'var ids = made.layers.map(function (l) { return l.id; });' . "\n"
		. 'e.doc.layers = made.layers; e.sel = []; e.steps = 0; e.change = function (fn) { this.steps++; fn(this.doc); }; e.select = function (s) { this.sel = s; }; e.render = e.renderPanel = none;' . "\n"
		. 'e.slotPut(slot.id, "data:image/png;base64,AAAA", "photo.png"); var put = e.doc.layers.filter(function (l) { return l.id === slot.id; })[0];' . "\n"
		. 'console.log(JSON.stringify({ board: made.artboard, count: made.layers.length, unique: ids.filter(function (id, i) { return ids.indexOf(id) === i; }).length,'
		. ' first: [texts[0].text, texts[0].x, texts[0].y, texts[0].size], slot: [slot.type, slot.name, slot.x, slot.w], order: e.doc.layers.indexOf(put),'
		. ' put: [put.type, put.src, put.fileName, put.x, put.y, put.w, put.h], steps: e.steps, sel: e.sel }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('width' => 1080, 'height' => 1080, 'background' => '#fff4e6'), $r['board'], 'the design\'s board');
	pg_assert_same(7, $r['count'], 'every layer');
	pg_assert_same(7, $r['unique'], 'each its own id');
	// "[startSaleHead]" is 15 letters at 130 px: 975 px under the stub, so
	// it shrinks to the 440 px it may take.
	pg_assert_same(array('[startSaleHead]', 70, 200, 58), $r['first'], 'the words, fitted, at the anchor');
	pg_assert_same(array('svg', '[startPicture]', 540, 540), $r['slot'], 'the box, named');
	pg_assert_same(0, $r['order'], 'the picture keeps the box\'s place in the stack');
	pg_assert_same(array('image', 'data:image/png;base64,AAAA', 'photo.png', 540, 0, 540, 1080), $r['put'], 'a picture layer in the box');
	pg_assert_same(1, $r['steps'], 'one undo step');
}
