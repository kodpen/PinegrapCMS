<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's second icon set, Tabler Icons, in the Sticker
 * tool: the check of the shipped file (assets/canvas/tabler-icons/), the
 * search, an icon's markup, colour and stroke width, its layer in the
 * board's middle (run under Node), the part, and a press on an editor.
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
function image_editor_tabler_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgietabler');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var F = JSON.parse(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/canvas/tabler-icons/icons.json') . ', "utf8"));' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The file is checked: a name [a-z0-9-] and a path of path commands and
// numbers starting with a move; anything else drops the icon.
function test_image_editor_tabler_check()
{
	$r = image_editor_tabler_node(
		'var map = M.tablerIcons({ icons: { star: "M12 17.75l-6.172 3.245", "circle-check": "M3 12a9 9 0 1 0 18 0 M9 12l2 2l4 -4", "Bad Name": "M0 0",'
		. ' quote: "M2 2\\" onload=\\"x", url: "M2 2 url(#a)", nomove: "l2 2", empty: "", num: 5, long: "M" + new Array(5000).join("1 ") } });'
		. 'console.log(JSON.stringify({ names: Object.keys(map).sort(), star: map.star, none: Object.keys(M.tablerIcons(null)).length, list: Object.keys(M.tablerIcons({ icons: [] })).length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('circle-check', 'star'), $r['names'], 'only well-formed icons');
	pg_assert_same('M12 17.75l-6.172 3.245', $r['star'], 'a path kept whole');
	pg_assert_same(0, $r['none'], 'no file is no icon');
	pg_assert_same(0, $r['list'], 'a list is not a map');
}

// The search: names holding the query, any case, spaces read as dashes,
// names starting with it first; at most the limit, the total in found.
function test_image_editor_tabler_search()
{
	$r = image_editor_tabler_node(
		'var map = M.tablerIcons({ icons: { "arrow-left": "M1 1", "left-arrow": "M1 2", "star": "M1 3", "star-off": "M1 4" } });'
		. 'console.log(JSON.stringify({ arrow: M.tablerSearch(map, "Arrow", 0).icons.map(function (i) { return i.name; }),'
		. ' dash: M.tablerSearch(map, "star off", 5).icons, cut: M.tablerSearch(map, "", 2), none: M.tablerSearch(map, "zzz", 5).found,'
		. ' nomap: M.tablerSearch(null, "a", 5).found }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('arrow-left', 'left-arrow'), $r['arrow'], 'starting with the query first');
	pg_assert_same(array(array('name' => 'star-off', 'd' => 'M1 4')), $r['dash'], 'a space reads as a dash; the path comes along');
	pg_assert_same(2, count($r['cut']['icons']), 'at most the limit');
	pg_assert_same(4, $r['cut']['found'], 'the total found');
	pg_assert_same(0, $r['none'], 'nothing found');
	pg_assert_same(0, $r['nomap'], 'no map');
}

// An icon's markup: one stroked path with round ends; the colour and width
// are read back and changed in place, the width held to its range in
// quarter steps.
function test_image_editor_tabler_markup_and_paint()
{
	$r = image_editor_tabler_node(
		'var m = M.tablerMarkup("M3 12a9 9 0 1 0 18 0", "#ff0000", 2);'
		. 'console.log(JSON.stringify({ m: m, bad: M.tablerMarkup("M0 0", "\\"><x", 2), p: M.tablerPaint(m), other: M.tablerPaint("<path d=\\"M0 0\\" fill=\\"#000\\" stroke=\\"none\\"/>"),'
		. ' r: M.tablerRepaint(m, "#123456", 1.5), keep: M.tablerPaint(M.tablerRepaint(m, null, undefined)), wide: M.tablerPaint(M.tablerRepaint(m, null, 40)).width,'
		. ' thin: M.tablerPaint(M.tablerRepaint(m, null, 0)).width, step: M.tablerPaint(M.tablerRepaint(m, null, 1.6)).width, notours: M.tablerRepaint("<g/>", "#000", 2),'
		. ' range: M.TABLER_WIDTH }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('<path d="M3 12a9 9 0 1 0 18 0" fill="none" stroke="#ff0000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>', $r['m'], 'a stroked path');
	pg_assert_true(strpos($r['bad'], '"><x') === false, 'the colour is escaped');
	pg_assert_same(array('color' => '#ff0000', 'width' => 2), $r['p'], 'colour and width read back');
	pg_assert_same(null, $r['other'], 'markup not of an icon');
	pg_assert_same('<path d="M3 12a9 9 0 1 0 18 0" fill="none" stroke="#123456" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>', $r['r'], 'recoloured and thinned');
	pg_assert_same(array('color' => '#ff0000', 'width' => 2), $r['keep'], 'nothing given keeps both');
	pg_assert_same(array(0.5, 3), $r['range'], 'the width range');
	pg_assert_same(3, $r['wide'], 'not over the range');
	pg_assert_same(0.5, $r['thin'], 'not under the range');
	pg_assert_same(1.5, $r['step'], 'in quarter steps');
	pg_assert_same('<g/>', $r['notours'], 'other markup is left as it is');
}

// A placed icon: an svg path layer in its 24 box, a fifth of the visible
// board's shorter side, its middle on the point given.
function test_image_editor_tabler_layer()
{
	$r = image_editor_tabler_node(
		'console.log(JSON.stringify(M.tablerLayer("star", "M12 17.75l-6.172 3.245", { x: 0, y: 0, w: 1000, h: 500 }, [600, 200], "#212529")));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('type' => 'svg', 'kind' => 'path', 'tabler' => 'star',
		'markup' => '<path d="M12 17.75l-6.172 3.245" fill="none" stroke="#212529" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		'vb' => array('x' => 0, 'y' => 0, 'w' => 24, 'h' => 24), 'x' => 550, 'y' => 150, 'w' => 100, 'h' => 100, 'rot' => 0, 'opacity' => 1), $r, 'a 100 square about the point');
}

// The shipped file: a Tabler Icons release, thousands of icons, every one
// passing the check, and its MIT licence beside it.
function test_image_editor_tabler_file()
{
	$license = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/assets/canvas/tabler-icons/LICENSE');

	pg_assert_true(strpos($license, 'MIT License') !== false, 'the MIT licence ships');
	pg_assert_true(strpos($license, 'Paweł Kuna') !== false, 'with its copyright line');

	$r = image_editor_tabler_node(
		'console.log(JSON.stringify({ version: F.version, raw: Object.keys(F.icons).length, kept: Object.keys(M.tablerIcons(F)).length,'
		. ' star: !!M.tablerIcons(F).star, heart: !!M.tablerIcons(F).heart }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true((bool) preg_match('/^3\.[0-9]+\.[0-9]+$/', (string) $r['version']), 'a release version');
	pg_assert_true($r['raw'] > 4000, 'thousands of icons');
	pg_assert_same($r['raw'], $r['kept'], 'every icon passes the check');
	pg_assert_true($r['star'] && $r['heart'], 'the common names are there');
}

// The part is listed and bundled after the icons and the elements, its
// labels are there, and the file's address reaches the editor.
function test_image_editor_tabler_part()
{
	pg_assert_true(in_array('tabler', pg_image_editor_parts(), true), 'the part is listed');

	$strings = pg_image_editor_strings_tabler();

	foreach (array('tablerSet', 'tablerSetBi', 'tablerSetTabler', 'tablerWidth') as $key) {
		pg_assert_true(isset($strings[$key]), $key . ' is labelled');
	}

	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];
	$at = array_search('assets/js/pg_image_editor_tabler.js', $files, true);

	pg_assert_true($at !== false, 'bundled');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_glyph.js', $files, true), 'after the icons and emoji');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_elements.js', $files, true), 'after the elements');
	pg_assert_true(strpos(pg_image_editor_tabler_url(), 'assets/canvas/tabler-icons/icons.json?v=') !== false, 'the file address');
}

// A press puts one svg layer, named, selected, in one undo step; with
// Tabler icons selected a colour and a width change them in one step.
function test_image_editor_tabler_press()
{
	if (!function_exists('exec')) {
		return;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} }; global.navigator = { platform: "Linux" };' . "\n"
		. 'window.PgImageEditorModel = M;' . "\n";

	foreach (array('', '_layers', '_sticker', '_svg', '_glyph', '_elements', '_tabler') as $part) {
		$head .= 'require(' . json_encode($js . 'pg_image_editor' . $part . '.js') . ');' . "\n";
	}

	$r = image_editor_tabler_node($head
		. 'var X = window.PgImageEditor.parts, Editor = X.Editor, none = function () {};' . "\n"
		. 'X.t = function (k) { return "[" + k + "]"; };' . "\n"
		. 'var e = Object.create(Editor.prototype); e.opts = {}; e.tools = { list: ["sticker"] };' . "\n"
		. 'Editor.inits.forEach(function (fn) { try { fn.call(e); } catch (err) {} });' . "\n"
		. 'e.doc = M.newDocument({ blank: { width: 1000, height: 500 } }); e.doc.layers = [];' . "\n"
		. 'e.sel = []; e.steps = 0; e.history = { key: null }; e.render = e.renderView = e.renderPanel = none; e.change = function (fn) { this.steps++; fn(this.doc); };' . "\n"
		. 'e.select = function (ids) { this.sel = ids; }; e.stickerCentre = function () { return [500, 250]; };' . "\n"
		. 'e.addTabler("star", F.icons.star); var l = e.doc.layers[0];' . "\n"
		. 'var out = { steps: e.steps, sel: e.sel, type: l.type, kind: l.kind, tabler: l.tabler, name: l.name, paint: M.tablerPaint(l.markup), set: e.glyphs.set };' . "\n"
		. 'e.paintTabler([l.id], "#ff0000", 1.25); out.after = [e.steps, M.tablerPaint(e.doc.layers[0].markup)];' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(1, $r['steps'], 'one undo step');
	pg_assert_same(1, count($r['sel']), 'selected');
	pg_assert_same(array('svg', 'path', 'star', '[glyphIcon] · star'), array($r['type'], $r['kind'], $r['tabler'], $r['name']), 'an svg path layer, named');
	pg_assert_same(array('color' => '#212529', 'width' => 2), $r['paint'], 'dark, Tabler\'s own width');
	pg_assert_same('bi', $r['set'], 'Bootstrap is the first set');
	pg_assert_same(array(2, array('color' => '#ff0000', 'width' => 1.25)), $r['after'], 'recoloured and thinned in one step');
}
