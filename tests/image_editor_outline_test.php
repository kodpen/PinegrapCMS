<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's text to outlines (the OUTLINE section of
 * assets/js/pg_image_editor_model.js, run under Node, and the action font
 * of includes/fn/image_editor.php): the OpenType reader on a small TrueType
 * and a small CFF font (tests/fixtures/outline, made with fontTools: A a
 * triangle with a hole, V a triangle, O a curve, B a composite of V in the
 * TrueType one; kern A V -80 and V A -70 in its kern table, GPOS A V -60
 * and the classes [V O] A -40 in the CFF one), the outline against the
 * text layout, the path layer it makes, and the Google Fonts face helpers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php';

// Runs $script with the model as M, the fixtures as ttf and otf (read by
// M.fontRead()) and answers its JSON; null without Node.
function image_editor_outline_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieoutline');

	if ($file === false) {
		return null;
	}

	$fixtures = dirname(__FILE__) . '/fixtures/outline/';

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . '), fs = require("fs");' . "\n"
		. 'var ttf = M.fontRead(fs.readFileSync(' . json_encode($fixtures . 'fixture.ttf') . '));' . "\n"
		. 'var otf = M.fontRead(fs.readFileSync(' . json_encode($fixtures . 'fixture.otf') . '));' . "\n"
		. 'var r3 = function (v) { return Math.round(v * 1000) / 1000; };' . "\n"
		. 'var pts = function (cs) { return cs.map(function (c) { return c.map(function (k) { return [r3(k.x), r3(k.y), r3(k.ix), r3(k.iy), r3(k.ox), r3(k.oy)]; }); }); };' . "\n"
		. 'var gid = function (f, c) { return M.fontGlyph(f, c.charCodeAt(0)); };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// TrueType: the character map, advances, contours (a hole, a quadratic
// curve with an implied point as cubic handles, a moved component) and the
// kern table's pairs.
function test_image_editor_outline_truetype()
{
	$r = image_editor_outline_node(
		'console.log(JSON.stringify({ upm: ttf.upm, gids: ["A", "V", "O", "B", "Z"].map(function (c) { return gid(ttf, c); }),'
		. ' adv: M.fontAdvance(ttf, gid(ttf, "V")), a: pts(M.fontContours(ttf, gid(ttf, "A"))), o: pts(M.fontContours(ttf, gid(ttf, "O"))),'
		. ' b: pts(M.fontContours(ttf, gid(ttf, "B"))), av: M.fontKerning(ttf, gid(ttf, "A"), gid(ttf, "V")),'
		. ' va: M.fontKerning(ttf, gid(ttf, "V"), gid(ttf, "A")), aa: M.fontKerning(ttf, gid(ttf, "A"), gid(ttf, "A")) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(1000, $r['upm']);
	pg_assert_same(array(1, 2, 3, 4, 0), $r['gids'], 'the character map; a missing letter is 0');
	pg_assert_same(620, $r['adv'], 'the advance');
	pg_assert_same(2, count($r['a']), 'the outer contour and the hole');
	pg_assert_same(array(array(0, 0), array(300, 700), array(600, 0)), array_map(function ($k) { return array($k[0], $k[1]); }, $r['a'][0]), 'straight points');
	pg_assert_same(array(array(350, 0, 350, 0, 583.333, 0), array(700, 350, 700, 116.667, 700, 583.333), array(350, 700, 583.333, 700, 350, 700)), $r['o'][0], 'two curve points in a row: the point between them, handles at two thirds');
	pg_assert_same(array(100, 750), array_slice($r['b'][0][0], 0, 2), 'a component moved by its offset');
	pg_assert_same(-80, $r['av']);
	pg_assert_same(-70, $r['va']);
	pg_assert_same(0, $r['aa'], 'no pair, no kerning');
}

// CFF: the charstrings' lines and curves, and GPOS pairs (a glyph pair and
// a class pair) of the kern feature.
function test_image_editor_outline_cff()
{
	$r = image_editor_outline_node(
		'console.log(JSON.stringify({ a: pts(M.fontContours(otf, gid(otf, "A"))), o: pts(M.fontContours(otf, gid(otf, "O"))),'
		. ' adv: M.fontAdvance(otf, gid(otf, "O")), av: M.fontKerning(otf, gid(otf, "A"), gid(otf, "V")), va: M.fontKerning(otf, gid(otf, "V"), gid(otf, "A")),'
		. ' oa: M.fontKerning(otf, gid(otf, "O"), gid(otf, "A")), ao: M.fontKerning(otf, gid(otf, "A"), gid(otf, "O")),'
		. ' woff: (function () { try { M.fontRead(new Uint8Array([119, 79, 70, 50, 0, 0, 0, 0])); return "read"; } catch (e) { return "refused"; } }()) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(2, count($r['a']), 'two contours');
	pg_assert_same(array(array(0, 0), array(300, 700), array(600, 0)), array_map(function ($k) { return array($k[0], $k[1]); }, $r['a'][0]), 'the outer contour');
	pg_assert_same(array(array(350, 0, 350, 0, 550, 0), array(700, 350, 700, 150, 700, 350), array(0, 350, 0, 350, 0, 350)), $r['o'][0], 'a cubic curve as it is written');
	pg_assert_same(700, $r['adv']);
	pg_assert_same(-60, $r['av'], 'a glyph pair');
	pg_assert_same(-40, $r['va'], 'a class pair');
	pg_assert_same(-40, $r['oa'], 'the class holds O too');
	pg_assert_same(0, $r['ao']);
	pg_assert_same('refused', $r['woff'], 'WOFF2 is not read');
}

// The letters sit on the text tool's layout: the baseline, kerning from
// the aligned side, spaced characters where the layout put them.
function test_image_editor_outline_against_layout()
{
	$r = image_editor_outline_node(
		'var measure = function (font, s) { var w = 0; Array.from(s).forEach(function (c) { w += M.fontAdvance(ttf, gid(ttf, c)) / 10; }); return w; };' . "\n"
		. 'var at = function (l) { var lay = M.textLayout(l, measure, null), o = M.outlineText(ttf, l, lay); return { base: lay.lines[0].base, starts: o.contours.map(function (c) { return [r3(c[0].x), r3(c[0].y)]; }), missing: o.missing }; };' . "\n"
		. 'var l = { text: "AV", size: 100, lineHeight: 1.25, align: "left", font: "g:Fixture" };' . "\n"
		. 'console.log(JSON.stringify({ left: at(l), right: at(Object.assign({}, l, { text: "AV\nO", align: "right" })),'
		. ' spaced: at(Object.assign({}, l, { spacing: 0.1 })), missing: at(Object.assign({}, l, { text: "A Z" })).missing }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	$base = $r['left']['base'];
	pg_assert_same(array(array(0, $base), array(20, $base - 10), array(52, $base - 70)), $r['left']['starts'], 'A at the left on the baseline (y down), V after its advance and the pair\'s kerning');
	// The layout's box is 122 wide (60 + 62, measured without kerning); O
	// (70) is set from the right: 52, and its first point 35 in.
	pg_assert_same(87, $r['right']['starts'][3][0], 'a line set from the right side');
	pg_assert_same(70, $r['spaced']['starts'][2][0], 'spaced: where the layout put the letter (60 + 10)');
	pg_assert_same(array('Z'), $r['missing'], 'letters the font does not have');
}

// The path that takes a text's place: its box around the letters, the
// first contour as knots and the rest as subs, the text's angle and
// colour; the outline a line under the fill; a refit keeps the subs.
function test_image_editor_outline_layer()
{
	$r = image_editor_outline_node(
		'var l = { type: "text", text: "A", size: 100, lineHeight: 1, align: "left", font: "g:Fixture", x: 10, y: 20, rot: 30, color: "#ff0000", opacity: 0.5 };' . "\n"
		. 'var lay = M.textLayout(l, function () { return 60; }, function () { return { ascent: 80, descent: 20 }; }); l.w = lay.w; l.h = lay.h;' . "\n"
		. 'var cs = M.outlineText(ttf, l, lay).contours, p = M.outlineLayer(l, cs);' . "\n"
		. 'var q = M.outlineLayer(Object.assign({}, l, { outline: { color: "#000000", width: 0.05 }, shadow: { color: "#000", blur: 0.1, dx: 0, dy: 0.1 }, bg: "#ffffff" }), cs);' . "\n"
		. 'var px = M.pathKnots(p); px[1] = M.moveKnot(px[1], px[1].x, px[1].y - 30); var fit = M.refitPath(p, px), moved = Object.assign({}, p, fit);' . "\n"
		. 'var hole = function (o) { var s = M.subKnots(o)[0].knots[0], c = M.drawnPoint(o, s.x, s.y); return [r3(c[0]), r3(c[1])]; };' . "\n"
		. 'console.log(JSON.stringify({ box: [r3(p.w), r3(p.h), p.rot, p.type, p.closed, p.fill, p.color, p.stroke, p.opacity], subs: p.subs.length, d: (M.pathShape(p).d.match(/M/g) || []).length,'
		. ' q: [q.stroke, q.ink, q.shadow || null], hole: [hole(p), hole(moved)], hit: M.hitShape(p, [p.w / 2, p.h * 0.95], 0) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(60, 70, 30, 'path', true, 'solid', '#ff0000', 0, 0.5), $r['box'], 'the box around the letter (60 x 70), the text\'s angle, colour and opacity, no line');
	pg_assert_same(1, $r['subs'], 'the hole as a sub');
	pg_assert_same(2, $r['d'], 'both contours drawn');
	pg_assert_same(array(10, '#000000', null), $r['q'], 'the outline as a line twice its width under the fill; a background takes the shadow');
	pg_assert_same($r['hole'][0], $r['hole'][1], 'a refit after a moved knot keeps the hole where it was');
	pg_assert_true($r['hit'], 'a press inside a letter hits it');
}

function test_image_editor_outline_needs_shaping()
{
	$r = image_editor_outline_node(
		'console.log(JSON.stringify(["Şöyle ağaç", "مرحبا", "नमस्ते", "Привет", ""].map(M.outlineNeedsShaping)));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(false, true, true, false, false), $r, 'Arabic and Devanagari need a shaper; Turkish and Cyrillic do not');
}

// The action font's face: the family cleaned, the weight in hundreds, the
// stylesheets tried nearest first, the file named in the stylesheet, the
// bytes checked.
function test_image_editor_outline_font_face()
{
	$face = pg_image_editor_font_face(' Open  Sans<script> ', 650, '1');
	pg_assert_same(array('family' => 'Open Sansscript', 'weight' => 700, 'italic' => true, 'file' => 'open-sansscript-700i.ttf'), $face);
	pg_assert_same(400, pg_image_editor_font_face('Roboto', 1200, false)['weight'], 'a weight out of range is 400');
	pg_assert_same('', pg_image_editor_font_face('***', 400, false)['family'], 'nothing left: no family');

	$urls = pg_image_editor_font_css_urls(pg_image_editor_font_face('Source Serif 4', 400, true));
	pg_assert_same(array(
		'https://fonts.googleapis.com/css2?family=Source+Serif+4:ital,wght@1,400',
		'https://fonts.googleapis.com/css2?family=Source+Serif+4:wght@400',
		'https://fonts.googleapis.com/css2?family=Source+Serif+4',
	), $urls);

	$css = "@font-face {\n  font-family: 'Roboto';\n  src: url(https://fonts.gstatic.com/s/roboto/v51/abc.ttf) format('truetype');\n}\n";
	pg_assert_same('https://fonts.gstatic.com/s/roboto/v51/abc.ttf', pg_image_editor_font_file_url($css));
	pg_assert_same('', pg_image_editor_font_file_url("src: url(https://fonts.gstatic.com/s/x.woff2) format('woff2');"), 'WOFF2 is not taken');
	pg_assert_same('', pg_image_editor_font_file_url("src: url(https://evil.example/x.ttf) format('truetype');"), 'only Google\'s font host');

	pg_assert_true(pg_image_editor_font_bytes_ok("\x00\x01\x00\x00rest"));
	pg_assert_true(pg_image_editor_font_bytes_ok('OTTOrest'));
	pg_assert_false(pg_image_editor_font_bytes_ok('wOF2rest'));
	pg_assert_false(pg_image_editor_font_bytes_ok('<html>'));
}

// The part is bundled after the text part, whose controls it wraps, and
// keeps its labels.
function test_image_editor_outline_bundled()
{
	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];

	pg_assert_true(array_search('assets/js/pg_image_editor_outline.js', $files, true) > array_search('assets/js/pg_image_editor_text.js', $files, true), 'after the text part');
	pg_assert_true(in_array('outline', pg_image_editor_parts(), true), 'a part with its strings');
	pg_assert_true(function_exists('pg_image_editor_action_font'), 'the action font');
}
