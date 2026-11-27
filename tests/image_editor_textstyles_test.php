<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's text styles and font pairs at the top of the
 * Text panel: the model's styles, the check of the font pairs file
 * (assets/canvas/font-pairs.json), the stack in the board's middle (run
 * under Node), and the file's families and labels.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and the Google Fonts list as G and
// answers its JSON; null without Node.
function image_editor_textstyles_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgietextstyles');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'global.window = {}; require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_google_fonts.js') . '); var G = window.PgGoogleFonts.all();' . "\n"
		. 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var F = JSON.parse(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/canvas/font-pairs.json') . ', "utf8"));' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Heading, subheading and body: sized to the board's shorter side, plain and
// centred, keeping the font and colour of the style given.
function test_image_editor_text_presets()
{
	$r = image_editor_textstyles_node(
		'var b = { x: 0, y: 0, w: 1080, h: 1350 }, base = M.textDefaults(); base.font = "serif"; base.color = "#1a1c1e"; base.bg = "#ffffff"; base.shadow = { blur: 1 };'
		. 'console.log(JSON.stringify({ h: M.textPreset("heading", b, base), s: M.textPreset("subheading", b, base), p: M.textPreset("body", b, base),'
		. ' tiny: M.textPreset("body", { x: 0, y: 0, w: 100, h: 100 }).size, other: M.textPreset("quote", b, base), base: base.bg }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(108, 65, 38), array($r['h']['size'], $r['s']['size'], $r['p']['size']), 'sizes from the shorter side');
	pg_assert_same(array(700, 600, 400), array($r['h']['weight'], $r['s']['weight'], $r['p']['weight']), 'weights');
	pg_assert_same(array('serif', '#1a1c1e', 'center', '', null), array($r['h']['font'], $r['h']['color'], $r['h']['align'], $r['h']['bg'], $r['h']['shadow']), 'plain, centred, font and colour kept');
	pg_assert_same(12, $r['tiny'], 'never under 12 px');
	pg_assert_same(null, $r['other'], 'an unknown kind is none');
	pg_assert_same('#ffffff', $r['base'], 'the style given is not changed');
}

// The file is checked: a bad id, family, weight or label key drops a pair or
// a weight; ids once; at most twelve.
function test_image_editor_font_pairs_check()
{
	$r = image_editor_textstyles_node(
		'var known = function (f) { return G.indexOf(f) >= 0; };'
		. 'var p = function (id, a, b) { return { id: id, head: { family: a, weight: 700, text: "fontPairXHead" }, line: { family: b, weight: 400, text: "fontPairXLine" } }; };'
		. 'var odd = p("odd", "Lato", "Lora"); odd.head.weight = 550;'
		. 'var badKey = p("key", "Lato", "Lora"); badKey.line.text = "textAdd";'
		. 'var many = []; for (var i = 0; i < 15; i++) { many.push(p("p" + i, "Lato", "Lora")); }'
		. 'console.log(JSON.stringify({ list: M.fontPairs({ pairs: [p("good", "Lato", "Lora"), p("good", "Lato", "Lora"), p("Bad Id", "Lato", "Lora"), p("unknown", "No Such Face", "Lora"), p("mark", "Lato\\"x", "Lora"), odd, badKey, null] }, known),'
		. ' many: M.fontPairs({ pairs: many }, known).length, none: M.fontPairs(null, known), max: M.FONT_PAIR_MAX }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('good', 'odd'), array_map(function ($x) { return $x['id']; }, $r['list']), 'only well-formed pairs, ids once');
	pg_assert_same(400, $r['list'][1]['head']['weight'], 'a weight not offered becomes 400');
	pg_assert_same(array('family' => 'Lato', 'weight' => 700, 'text' => 'fontPairXHead'), $r['list'][0]['head'], 'a part kept whole');
	pg_assert_same(12, $r['many'], 'twelve at most');
	pg_assert_same(12, $r['max'], 'the limit');
	pg_assert_same(array(), $r['none'], 'no file is no pair');
}

// A pair's two styles are Google faces, the heading larger; a stack sits in
// the board's middle with each box centred across.
function test_image_editor_font_pair_styles_and_stack()
{
	$r = image_editor_textstyles_node(
		'var pair = { id: "x", head: { family: "Playfair Display", weight: 700, text: "a" }, line: { family: "Lato", weight: 400, text: "b" } };'
		. 'var s = M.fontPairStyles(pair, { x: 0, y: 0, w: 1000, h: 500 }, M.textDefaults());'
		. 'console.log(JSON.stringify({ s: s.map(function (x) { return [x.font, x.weight, x.size, x.align]; }),'
		. ' stack: M.textStack({ x: 100, y: 50, w: 1000, h: 500 }, [{ w: 400, h: 100 }, { w: 200, h: 40 }], 20),'
		. ' one: M.textStack({ x: 0, y: 0, w: 100, h: 100 }, [{ w: 50, h: 20 }], 10) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('g:Playfair Display', 700, 45, 'center'), array('g:Lato', 400, 20, 'center')), $r['s'], 'two faces, heading first');
	pg_assert_same(array(array('x' => 400, 'y' => 220), array('x' => 500, 'y' => 340)), $r['stack'], 'stacked in the middle');
	pg_assert_same(array(array('x' => 25, 'y' => 40)), $r['one'], 'one box in the middle');
}

// The shipped file: twelve pairs, every family in the catalogue, every label
// key one of the part's labels.
function test_image_editor_font_pairs_file()
{
	$r = image_editor_textstyles_node(
		'var known = function (f) { return G.indexOf(f) >= 0; };'
		. 'console.log(JSON.stringify({ raw: F.pairs.length, kept: M.fontPairs(F, known) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(12, $r['raw'], 'twelve pairs in the file');
	pg_assert_same(12, count($r['kept']), 'every pair passes the check');

	$strings = pg_image_editor_strings_textstyles();

	foreach ($r['kept'] as $pair) {
		pg_assert_true(isset($strings[$pair['head']['text']], $strings[$pair['line']['text']]), $pair['id'] . ': its words are labels');
	}
}

// The part is listed and bundled after the Text tool and Google Fonts, and
// its file's address reaches the editor.
function test_image_editor_textstyles_part()
{
	pg_assert_true(in_array('textstyles', pg_image_editor_parts(), true), 'the part is listed');

	$strings = pg_image_editor_strings_textstyles();

	foreach (array('textAddHeading', 'textAddSubheading', 'textAddBody', 'fontPairs', 'fontPairsError') as $key) {
		pg_assert_true(isset($strings[$key]), $key . ' is labelled');
	}

	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];
	$at = array_search('assets/js/pg_image_editor_textstyles.js', $files, true);

	pg_assert_true($at !== false, 'bundled');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_fonts.js', $files, true), 'after the Google Fonts sheet');
	pg_assert_true(strpos(pg_image_editor_font_pairs_url(), 'assets/canvas/font-pairs.json?v=') !== false, 'the file address');
}

// A press on a pair puts its two texts as one group in the board's middle,
// one undo step, both selected; a style button puts one text, no group.
function test_image_editor_textstyles_press()
{
	if (!function_exists('exec')) {
		return;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} }; global.navigator = { platform: "Linux" };' . "\n"
		. 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . '); window.PgImageEditorModel = M;' . "\n";

	foreach (array('', '_layers', '_text', '_textstyles') as $part) {
		$head .= 'require(' . json_encode($js . 'pg_image_editor' . $part . '.js') . ');' . "\n";
	}

	$r = image_editor_textstyles_node($head
		. 'var Editor = window.PgImageEditor.parts.Editor, none = function () {};' . "\n"
		. 'var e = Object.create(Editor.prototype); e.opts = {}; e.tools = { list: ["text"] };' . "\n"
		. 'Editor.inits.forEach(function (fn) { try { fn.call(e); } catch (err) {} });' . "\n"
		. 'e.doc = M.newDocument({ blank: { width: 1000, height: 500 } }); e.doc.layers = [{ id: "l1", type: "rect", x: 0, y: 0, w: 1, h: 1, grp: ["g1"] }, { id: "l2", type: "rect", x: 0, y: 0, w: 1, h: 1, grp: ["g1"] }];' . "\n"
		. 'e.sel = []; e.steps = 0; e.textEdit = null; e.render = e.renderView = e.renderPanel = none; e.change = function (fn) { this.steps++; fn(this.doc); };' . "\n"
		. 'e.select = function (ids) { this.sel = ids; }; e.textLayout = function (l) { return { w: l.text.length * 10, h: l.size }; };' . "\n"
		. 'var pair = { id: "x", head: { family: "Lato", weight: 700, text: "a" }, line: { family: "Lora", weight: 400, text: "b" } };' . "\n"
		. 'e.addStyledTexts(M.fontPairStyles(pair, e.viewRect(), e.textStyle), ["Hello", "World!"]);' . "\n"
		. 'var two = e.doc.layers.slice(2), out = { steps: e.steps, sel: e.sel, two: two.map(function (l) { return [l.type, l.text, l.font, l.grp, l.x, l.y]; }) };' . "\n"
		. 'e.addStyledTexts([M.textPreset("heading", e.viewRect(), e.textStyle)], ["Head"]); var h = e.doc.layers[4];' . "\n"
		. 'out.one = [e.steps, e.sel.length, h.grp || null, h.size, h.weight];' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(1, $r['steps'], 'one undo step');
	pg_assert_same(2, count($r['sel']), 'both selected');
	// Heading 45 px high, gap 12 px (0.6 of the line's 20 px), line 20 px: 77 px
	// stacked in the middle of 500.
	pg_assert_same(array(
		array('text', 'Hello', 'g:Lato', array('g2'), 475, 211.5),
		array('text', 'World!', 'g:Lora', array('g2'), 470, 268.5),
	), $r['two'], 'two texts, one new group, in the middle');
	pg_assert_same(array(2, 1, null, 50, 700), $r['one'], 'a style button puts one text, no group');
}
