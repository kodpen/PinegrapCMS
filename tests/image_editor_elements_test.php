<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's element sets in the Sticker tool: the check
 * of the elements file (assets/canvas/elements.json), an element's markup,
 * colour and width, its layer in the board's middle (run under Node), the
 * shipped shapes inside their boxes, and a press on an editor.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and the shipped file as F and answers its
// JSON; null without Node.
function image_editor_elements_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieelements');

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

// The file is checked: a bad id, set, label key, size, path or stroke drops
// an element; ids once; at most ELEMENT_MAX.
function test_image_editor_elements_check()
{
	$r = image_editor_elements_node(
		'var e = function (id, o) { return Object.assign({ id: id, set: "badges", label: "elementX", w: 100, h: 100, d: "M2 2 H98 V98 Z", stroke: 0 }, o || {}); };'
		. 'var many = []; for (var i = 0; i < 70; i++) { many.push(e("e" + i)); }'
		. 'console.log(JSON.stringify({ list: M.elementSets({ elements: [e("good"), e("good"), e("Bad Id"), e("set", { set: "frames" }), e("key", { label: "glyphColor" }),'
		. ' e("size", { w: 0 }), e("big", { h: 5000 }), e("quote", { d: "M2 2\\" onload=\\"x" }), e("url", { d: "M2 2 url(#a)" }), e("nomove", { d: "L2 2" }),'
		. ' e("thick", { stroke: 30 }), e("line", { stroke: 4 }), null] }), many: M.elementSets({ elements: many }).length, none: M.elementSets(null), max: M.ELEMENT_MAX, sets: M.ELEMENT_SETS }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('good', 'line'), array_map(function ($x) { return $x['id']; }, $r['list']), 'only well-formed elements, ids once');
	pg_assert_same(array('id' => 'good', 'set' => 'badges', 'label' => 'elementX', 'w' => 100, 'h' => 100, 'd' => 'M2 2 H98 V98 Z', 'stroke' => 0), $r['list'][0], 'an element kept whole');
	pg_assert_same(4, $r['list'][1]['stroke'], 'a stroked one keeps its width');
	pg_assert_same(60, $r['many'], 'sixty at most');
	pg_assert_same(60, $r['max'], 'the limit');
	pg_assert_same(array(), $r['none'], 'no file is no element');
	pg_assert_same(array('lines', 'badges', 'ribbons', 'blobs', 'arrows', 'speech', 'ornaments'), $r['sets'], 'the seven chips in order');
}

// An element's markup: a filled one takes the colour as its fill, a stroked
// one as its stroke with its width and round ends; the paint is read back
// and changed in place, the width held to its range.
function test_image_editor_element_markup_and_paint()
{
	$r = image_editor_elements_node(
		'var fill = M.elementMarkup({ d: "M2 2 H98 V98 Z", stroke: 0 }, "#ff0000"), line = M.elementMarkup({ d: "M8 20 H192", stroke: 4 }, "#00ff00");'
		. 'console.log(JSON.stringify({ fill: fill, line: line, bad: M.elementMarkup({ d: "M0 0", stroke: 0 }, "\\"><x"),'
		. ' pf: M.elementPaint(fill), pl: M.elementPaint(line), other: M.elementPaint("<g/>"),'
		. ' rf: M.elementRepaint(fill, "#123456"), rl: M.elementRepaint(line, "#123456", 9), wide: M.elementPaint(M.elementRepaint(line, null, 40)).width,'
		. ' thin: M.elementPaint(M.elementRepaint(line, null, 0)).width, range: M.ELEMENT_WIDTH }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('<path d="M2 2 H98 V98 Z" fill="#ff0000" stroke="none"/>', $r['fill'], 'a filled shape');
	pg_assert_same('<path d="M8 20 H192" fill="none" stroke="#00ff00" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>', $r['line'], 'a stroked shape');
	pg_assert_true(strpos($r['bad'], '"><x') === false, 'the colour is escaped');
	pg_assert_same(array('color' => '#ff0000', 'width' => null), $r['pf'], 'a fill has no width');
	pg_assert_same(array('color' => '#00ff00', 'width' => 4), $r['pl'], 'a stroke has its width');
	pg_assert_same(null, $r['other'], 'markup not of an element');
	pg_assert_same('<path d="M2 2 H98 V98 Z" fill="#123456" stroke="none"/>', $r['rf'], 'a fill recoloured');
	pg_assert_same('<path d="M8 20 H192" fill="none" stroke="#123456" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>', $r['rl'], 'a stroke recoloured and widened');
	pg_assert_same(array(1, 12), $r['range'], 'the width range');
	pg_assert_same(12, $r['wide'], 'not over the range');
	pg_assert_same(1, $r['thin'], 'not under the range');
}

// A placed element: an svg path layer, a share of the board's shorter fit,
// its middle on the point given, its box the element's own proportions.
function test_image_editor_element_layer()
{
	$r = image_editor_elements_node(
		'var e = { id: "line-x", set: "lines", label: "elementX", w: 200, h: 40, d: "M8 20 H192", stroke: 4 };'
		. 'console.log(JSON.stringify({ l: M.elementLayer(e, { x: 0, y: 0, w: 1000, h: 500 }, [600, 200], "#212529"),'
		. ' sq: M.elementLayer({ id: "b", w: 100, h: 100, d: "M0 0 Z", stroke: 0 }, { x: 0, y: 0, w: 1000, h: 500 }, [500, 250], "#000000") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('type' => 'svg', 'kind' => 'path', 'element' => 'line-x',
		'markup' => '<path d="M8 20 H192" fill="none" stroke="#212529" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>',
		'vb' => array('x' => 0, 'y' => 0, 'w' => 200, 'h' => 40), 'x' => 425, 'y' => 165, 'w' => 350, 'h' => 70, 'rot' => 0, 'opacity' => 1), $r['l'], 'a line, 35 % wide, about the point');
	pg_assert_same(array(412.5, 162.5, 175, 175), array($r['sq']['x'], $r['sq']['y'], $r['sq']['w'], $r['sq']['h']), 'a square, 35 % of the shorter side');
}

// The shipped file: forty elements, every set at least four, every label one
// of the part's labels, and every shape inside its box (a stroked one with
// room for the widest stroke).
function test_image_editor_elements_file()
{
	$r = image_editor_elements_node(
		// The points of an absolute M L H V C Q Z path, each curve sampled.
		'function points(d) { var out = [], at = [0, 0], start = [0, 0], tok = d.match(/[A-Za-z]|-?[0-9.]+/g), i = 0, cmd = "";'
		. ' var num = function () { return Number(tok[i++]); };'
		. ' var curve = function (pts) { for (var s = 1; s <= 16; s++) { var t = s / 16, q = pts.slice(); while (q.length > 1) { q = q.slice(1).map(function (p, k) { return [q[k][0] + (p[0] - q[k][0]) * t, q[k][1] + (p[1] - q[k][1]) * t]; }); } out.push(q[0]); } };'
		. ' while (i < tok.length) { if (/[A-Za-z]/.test(tok[i])) { cmd = tok[i++]; }'
		. '  if (cmd === "M") { at = [num(), num()]; start = at; out.push(at); cmd = "L"; }'
		. '  else if (cmd === "L") { at = [num(), num()]; out.push(at); }'
		. '  else if (cmd === "H") { at = [num(), at[1]]; out.push(at); }'
		. '  else if (cmd === "V") { at = [at[0], num()]; out.push(at); }'
		. '  else if (cmd === "C") { var c1 = [num(), num()], c2 = [num(), num()], p = [num(), num()]; curve([at, c1, c2, p]); at = p; }'
		. '  else if (cmd === "Q") { var c = [num(), num()], p2 = [num(), num()]; curve([at, c, p2]); at = p2; }'
		. '  else if (cmd === "Z") { at = start; }'
		. '  else { return null; } } return out; }'
		. 'var kept = M.elementSets(F), out = [];'
		. 'kept.forEach(function (e) { var pts = points(e.d), m = e.stroke ? M.ELEMENT_WIDTH[1] / 2 : 0;'
		. ' var inside = !!pts && pts.every(function (p) { return (p[0] >= m - 0.5) && (p[0] <= e.w - m + 0.5) && (p[1] >= m - 0.5) && (p[1] <= e.h - m + 0.5); });'
		. ' out.push({ id: e.id, set: e.set, label: e.label, inside: inside }); });'
		. 'console.log(JSON.stringify({ raw: F.elements.length, kept: out }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(40, $r['raw'], 'forty elements in the file');
	pg_assert_same(40, count($r['kept']), 'every element passes the check');

	$strings = pg_image_editor_strings_elements();
	$sets = array();

	foreach ($r['kept'] as $e) {
		pg_assert_true(isset($strings[$e['label']]), $e['id'] . ': its name is a label');
		pg_assert_true($e['inside'], $e['id'] . ': inside its box');
		$sets[$e['set']] = isset($sets[$e['set']]) ? $sets[$e['set']] + 1 : 1;
	}

	foreach (array('lines', 'badges', 'ribbons', 'blobs', 'arrows', 'speech', 'ornaments') as $set) {
		pg_assert_true(isset($sets[$set]) && ($sets[$set] >= 4), $set . ': four or more');
		pg_assert_true(isset($strings['elementSet_' . $set]), $set . ': its chip is labelled');
	}
}

// The part is listed and bundled after the icons, and its file's address
// reaches the editor.
function test_image_editor_elements_part()
{
	pg_assert_true(in_array('elements', pg_image_editor_parts(), true), 'the part is listed');

	$strings = pg_image_editor_strings_elements();

	foreach (array('elements', 'elementsError', 'elementColor', 'elementWidth') as $key) {
		pg_assert_true(isset($strings[$key]), $key . ' is labelled');
	}

	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];
	$at = array_search('assets/js/pg_image_editor_elements.js', $files, true);

	pg_assert_true($at !== false, 'bundled');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_glyph.js', $files, true), 'after the icons and emoji');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_svg.js', $files, true), 'after the svg layers');
	pg_assert_true(strpos(pg_image_editor_elements_url(), 'assets/canvas/elements.json?v=') !== false, 'the file address');
}

// A press puts one svg layer, named, selected, in one undo step; with
// elements selected a colour and a width change them in one step each.
function test_image_editor_elements_press()
{
	if (!function_exists('exec')) {
		return;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} }; global.navigator = { platform: "Linux" };' . "\n"
		. 'window.PgImageEditorModel = M;' . "\n";

	foreach (array('', '_layers', '_sticker', '_svg', '_glyph', '_elements') as $part) {
		$head .= 'require(' . json_encode($js . 'pg_image_editor' . $part . '.js') . ');' . "\n";
	}

	$r = image_editor_elements_node($head
		. 'var X = window.PgImageEditor.parts, Editor = X.Editor, none = function () {};' . "\n"
		. 'X.t = function (k) { return "[" + k + "]"; };' . "\n"
		. 'var e = Object.create(Editor.prototype); e.opts = {}; e.tools = { list: ["sticker"] };' . "\n"
		. 'Editor.inits.forEach(function (fn) { try { fn.call(e); } catch (err) {} });' . "\n"
		. 'e.doc = M.newDocument({ blank: { width: 1000, height: 500 } }); e.doc.layers = [];' . "\n"
		. 'e.sel = []; e.steps = 0; e.history = { key: null }; e.render = e.renderView = e.renderPanel = none; e.change = function (fn) { this.steps++; fn(this.doc); };' . "\n"
		. 'e.select = function (ids) { this.sel = ids; }; e.stickerCentre = function () { return [500, 250]; };' . "\n"
		. 'var line = M.elementSets(F).filter(function (x) { return x.id === "line-wave"; })[0];' . "\n"
		. 'e.addElement(line); var l = e.doc.layers[0];' . "\n"
		. 'var out = { steps: e.steps, sel: e.sel, type: l.type, kind: l.kind, element: l.element, name: l.name, paint: M.elementPaint(l.markup) };' . "\n"
		. 'e.paintElements([l.id], "#ff0000", 7); out.after = [e.steps, M.elementPaint(e.doc.layers[0].markup)];' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(1, $r['steps'], 'one undo step');
	pg_assert_same(1, count($r['sel']), 'selected');
	pg_assert_same(array('svg', 'path', 'line-wave', '[elementLineWave]'), array($r['type'], $r['kind'], $r['element'], $r['name']), 'an svg path layer, named');
	pg_assert_same(array('color' => '#212529', 'width' => 4), $r['paint'], 'dark, its own width');
	pg_assert_same(array(2, array('color' => '#ff0000', 'width' => 7)), $r['after'], 'recoloured and widened in one step');
}
