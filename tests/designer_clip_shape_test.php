<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Clip shape and Mask model (assets/js/pg_clip_shape.js):
 * each shape and mask written and read back, a hand-written value kept,
 * a state's rule, the -webkit- twins. Run under Node; skipped on a
 * machine without Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as C; returns what it prints as JSON, null
// without Node.
function designer_clip_shape_run($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgclip');

	if ($file === false) {
		return null;
	}

	$dir = PG_FUNCTIONS_DIR . '/assets/js';
	file_put_contents($file,
		"global.window = {};\n"
		. 'require(' . json_encode($dir . '/pg_user_styles.js') . ");\n"
		. 'var C = require(' . json_encode($dir . '/pg_clip_shape.js') . ");\n"
		. $script
	);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		pg_assert_false(true, implode("\n", $out));

		return null;
	}

	return json_decode(implode("\n", $out), true);
}

function test_designer_clip_shape_css_and_back()
{
	$r = designer_clip_shape_run(
		'var shapes = [{ kind: "circle", size: 40, at: 1 }, { kind: "ellipse", w: 50, h: 30, at: 4 }, { kind: "inset", inset: 5, corner: 16 },'
		. ' { kind: "inset", inset: 10, corner: 0 }, { kind: "slant", edge: "bottom", amount: 10, dir: "right" }, { kind: "slant", edge: "top", amount: 8, dir: "left" },'
		. ' { kind: "slant", edge: "both", amount: 12, dir: "right" }, { kind: "arrow", points: "right", point: 20 }, { kind: "arrow", points: "up", point: 15 },'
		. ' { kind: "arrow", points: "down", point: 25 }, { kind: "arrow", points: "left", point: 30 }];'
		. 'console.log(JSON.stringify({ css: shapes.map(C.clipCss), back: shapes.map(function (s) { return C.clipParse(C.clipCss(s)); }),'
		. ' own: C.clipParse("polygon(0 0, 50% 10%, 100% 100%)"), path: C.clipParse("path(\'M0 0\')"), none: C.clipParse("none"),'
		. ' kept: C.clipCss({ kind: "circle", size: 500, at: 12 }), bad: C.clipCss({ kind: "star" }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same('circle(40% at 50% 0%)', $r['css'][0]);
	pg_assert_same('ellipse(50% 30% at 50% 50%)', $r['css'][1]);
	pg_assert_same('inset(5% round 16px)', $r['css'][2]);
	pg_assert_same('inset(10%)', $r['css'][3], 'no corner, no round');
	pg_assert_same('polygon(0% 0%, 100% 0%, 100% 90%, 0% 100%)', $r['css'][4], 'the bottom edge going up to the right');
	pg_assert_same('polygon(0% 0%, 100% 8%, 100% 100%, 0% 100%)', $r['css'][5], 'the top edge going up to the left');
	pg_assert_same('polygon(0% 12%, 100% 0%, 100% 88%, 0% 100%)', $r['css'][6], 'both edges');
	pg_assert_same('polygon(0% 0%, 80% 0%, 100% 50%, 80% 100%, 0% 100%)', $r['css'][7], 'an arrow pointing right');
	pg_assert_same('polygon(50% 0%, 100% 15%, 100% 100%, 0% 100%, 0% 15%)', $r['css'][8], 'an arrow pointing up');

	foreach ($r['back'] as $i => $back) {
		pg_assert_true(is_array($back) && $back['kind'] !== 'own', 'shape ' . $i . ' reads back as a row\'s shape');
	}

	pg_assert_same(array('kind' => 'circle', 'size' => 40, 'at' => 1), $r['back'][0], 'a circle reads back');
	pg_assert_same(array('kind' => 'inset', 'inset' => 10, 'corner' => 0), $r['back'][3]);
	pg_assert_same(array('kind' => 'slant', 'edge' => 'top', 'amount' => 8, 'dir' => 'left'), $r['back'][5], 'a slant reads back');
	pg_assert_same(array('kind' => 'slant', 'edge' => 'both', 'amount' => 12, 'dir' => 'right'), $r['back'][6]);
	pg_assert_same(array('kind' => 'arrow', 'points' => 'down', 'point' => 25), $r['back'][9], 'an arrow reads back');
	pg_assert_same(array('kind' => 'arrow', 'points' => 'left', 'point' => 30), $r['back'][10]);
	pg_assert_same('own', $r['own']['kind'], 'a polygon no row draws is the designer\'s own');
	pg_assert_same('polygon(0 0, 50% 10%, 100% 100%)', $r['own']['css']);
	pg_assert_same('own', $r['path']['kind']);
	pg_assert_same(array('kind' => 'none'), $r['none']);
	pg_assert_same('circle(100% at 50% 50%)', $r['kept'], 'a value is kept to its row\'s ends');
	pg_assert_same('', $r['bad'], 'a kind no row has writes nothing');
}

function test_designer_clip_shape_mask()
{
	$r = designer_clip_shape_run(
		'var f = C.maskCss({ kind: "fade", edge: "bottom", length: 30 });'
		. 'var p = C.maskCss({ kind: "picture", url: "/files/blob \"a\".svg", size: "cover", at: 4 });'
		. 'console.log(JSON.stringify({ f: f, p: p, empty: C.maskCss({ kind: "picture", url: "" }),'
		. ' fBack: C.maskParse(f), pBack: C.maskParse(p), webkit: C.maskParse({ "-webkit-mask-image": "url(a.png)", "-webkit-mask-size": "contain", "-webkit-mask-position": "100% 100%" }),'
		. ' own: C.maskParse({ "mask-image": "radial-gradient(black, transparent)" }), short: C.maskParse({ mask: "url(a.png)" }), none: C.maskParse({}) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('mask-image' => 'linear-gradient(to bottom, #000 70%, transparent)'), $r['f'], 'a fade at the bottom taking 30 %');
	pg_assert_same('url("/files/blob \\"a\\".svg")', $r['p']['mask-image'], 'the address quoted and escaped');
	pg_assert_same('cover', $r['p']['mask-size']);
	pg_assert_same('50% 50%', $r['p']['mask-position']);
	pg_assert_same('no-repeat', $r['p']['mask-repeat']);
	pg_assert_same(array(), $r['empty'], 'a picture not chosen yet writes nothing');
	pg_assert_same(array('kind' => 'fade', 'edge' => 'bottom', 'length' => 30), $r['fBack']);
	pg_assert_same(array('kind' => 'picture', 'url' => '/files/blob "a".svg', 'size' => 'cover', 'at' => 4), $r['pBack']);
	pg_assert_same(array('kind' => 'picture', 'url' => 'a.png', 'size' => 'contain', 'at' => 8), $r['webkit'], 'the -webkit- twins alone read');
	pg_assert_same(array('kind' => 'own'), $r['own']);
	pg_assert_same(array('kind' => 'own'), $r['short'], 'the shorthand is the designer\'s own');
	pg_assert_same(null, $r['none']);
}

function test_designer_clip_shape_write_in_user_styles()
{
	$r = designer_clip_shape_run(
		'var cls = "pgs-abc123";'
		. 'var t0 = ".other { color: red; }\n";'
		. 'var t1 = C.writeClip(t0, cls, "", { kind: "circle", size: 50, at: 4 });'
		. 'var t2 = C.writeMask(t1, cls, "", { kind: "fade", edge: "top", length: 20 });'
		. 'var t3 = C.writeMask(t2, cls, "", { kind: "picture", url: "m.svg", size: "contain", at: 4 });'
		. 'var h1 = C.writeClip(t3, cls, "hover", { kind: "circle", size: 60, at: 4 });'
		. 'var h2 = C.writeClip(h1, cls, "hover", { kind: "circle", size: 50, at: 4 });'
		. 'var h3 = C.writeClip(t3, cls, "hover", { kind: "none" });'
		. 'var n0 = C.writeClip(C.writeMask(t3, cls, "", null), cls, "", { kind: "none" });'
		. 'var own = ".pgs-abc123 { clip-path: path(\'M0 0\'); }\n";'
		. 'console.log(JSON.stringify({ t1: t1, t2: t2, t3: t3, r3: C.read(t3, cls, ""), h1: h1, rh: C.read(h1, cls, "hover"), h2: h2, h3: h3, n0: n0,'
		. ' own: C.read(own, cls, ""), maybe: [C.maybe(t3, cls), C.maybe(t0, cls), C.maybe(t3, "pgs-zzz999")], badCls: C.writeClip(t0, "x", "", { kind: "circle" }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_contains(".pgs-abc123 {", $r['t1']);
	pg_assert_contains('clip-path: circle(50% at 50% 50%)', $r['t1']);
	pg_assert_contains('.other { color: red; }', $r['t1'], 'another rule stays');
	pg_assert_contains('mask-image: linear-gradient(to top, #000 80%, transparent)', $r['t2']);
	pg_assert_contains('-webkit-mask-image: linear-gradient(to top, #000 80%, transparent)', $r['t2'], 'with its -webkit- twin');
	pg_assert_contains('-webkit-mask-size: contain', $r['t3']);
	pg_assert_contains('mask-repeat: no-repeat', $r['t3']);
	pg_assert_false(strpos($r['t3'], 'linear-gradient') !== false, 'the picture takes the fade\'s place');
	pg_assert_same('picture', $r['r3']['mask']['kind']);
	pg_assert_same('circle', $r['r3']['clip']['kind']);
	pg_assert_contains(".pgs-abc123:hover {", $r['h1']);
	pg_assert_same(60, $r['rh']['clip']['size'], 'the state holds its own shape');
	pg_assert_same(null, $r['rh']['mask'], 'the state holds no mask of its own');
	pg_assert_false(strpos($r['h2'], ':hover') !== false, 'a state\'s shape equal to Normal\'s goes, and its empty rule');
	pg_assert_contains('clip-path: none', $r['h3'], 'None under a state takes Normal\'s shape away');
	pg_assert_false(strpos($r['n0'], 'clip-path') !== false || strpos($r['n0'], 'mask') !== false, 'None on Normal takes both out');
	pg_assert_same('own', $r['own']['clip']['kind'], 'a hand-written shape reads Custom');
	pg_assert_same(array(true, false, false), $r['maybe']);
	pg_assert_same(".other { color: red; }\n", $r['badCls'], 'not a custom style class: nothing written');
}

// Whether the element's own rules hold a shape or a mask (the dashed
// outline on the canvas): another element's clip-path in the same User
// Styles is not this element's, nor is a rule inside a media query.
function test_designer_clip_shape_has_own_rule_only()
{
	$r = designer_clip_shape_run(
		'var text = ".pgs-aaa111 { color: red; }\n.pgs-bbb222 { clip-path: circle(50% at 50% 50%); }\n.pgs-ccc333:hover { -webkit-mask-image: none; mask-image: none; }\n'
		. '.pgs-ddd444 { padding: 0; }\n.pgs-ddd444:focus-visible { clip-path: inset(5% round 16px); }\n";'
		. 'console.log(JSON.stringify([C.has(text, "pgs-aaa111"), C.has(text, "pgs-bbb222"), C.has(text, "pgs-ccc333"), C.has(text, "pgs-ddd444"),'
		. ' C.has(text, "pgs-eee555"), C.has(text, ""), C.has(".pgs-aaa111 { mask-image: linear-gradient(to bottom, #000 70%, transparent); }", "pgs-aaa111")]));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(false, $r[0], 'an element without a shape, beside one with a clip-path');
	pg_assert_same(true, $r[1]);
	pg_assert_same(false, $r[2], 'a state\'s None with no mask on Normal cuts nothing');
	pg_assert_same(true, $r[3], 'a state\'s own shape');
	pg_assert_same(false, $r[4]);
	pg_assert_same(false, $r[5]);
	pg_assert_same(true, $r[6], 'a mask alone');

	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_shape.js');
	pg_assert_contains('C.has(host.text(), host.cls()', $part, 'the canvas outline asks the element\'s own rules');
}
