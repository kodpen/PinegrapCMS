<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's SVG part (the SVG section of
 * assets/js/pg_image_editor_model.js, run under Node): a root's size and
 * view box, transform attributes, an element's box placed as a layer and
 * drawn back where it was, and the written file.
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
function image_editor_svg_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiesvg');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var r3 = function (v) { return Math.round(v * 1000) / 1000; };' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A root's artboard: its size in px, else its view box; the view box
// fitted centred.
function test_image_editor_svg_root()
{
	$r = image_editor_svg_node(
		'var a = M.svgRoot("100", "50", "0 0 200 100"), b = M.svgRoot(null, null, "10 10 40 20"), c = M.svgRoot("1in", "", ""), d = M.svgRoot("100", "100", "0 0 50 25");' . "\n"
		. 'console.log(JSON.stringify({ a: [a.w, a.h, a.m], b: [b.w, b.h, b.m], c: [c.w, c.h], d: d.m, none: [M.svgRoot("50%", "", "").w, M.svgRoot("50%", "", "").h] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(100, 50, array(0.5, 0, 0, 0.5, 0, 0)), $r['a'], 'the size, the view box scaled into it');
	pg_assert_same(array(40, 20, array(1, 0, 0, 1, -10, -10)), $r['b'], 'no size: the view box\'s');
	pg_assert_same(array(96, 96), $r['c'], 'inches in px, the missing side square');
	pg_assert_same(array(2, 0, 0, 2, 0, 25), $r['d'], 'a view box of another ratio fits centred');
	pg_assert_same(array(300, 150), $r['none'], 'a percentage: the default size');
}

// Transform attributes in order; a layer box from an element's box and
// matrix draws the element's corners where they were; a skew has no box.
function test_image_editor_svg_place()
{
	$r = image_editor_svg_node(
		'var m = M.svgTransform("translate(10, 20) rotate(90) scale(2 3)"), vb = { x: 1, y: 2, w: 10, h: 4 };' . "\n"
		. 'var corners = function (mx) { return [[vb.x, vb.y], [vb.x + vb.w, vb.y + vb.h]].map(function (p) { return M.apply(mx, p[0], p[1]).map(r3); }); };' . "\n"
		. 'var flip = M.svgTransform("scale(-1 1) translate(-50 0)"), fb = M.svgPlace(vb, flip), vflip = M.svgTransform("matrix(1 0 0 -1 0 30)"), vb2 = M.svgPlace(vb, vflip);' . "\n"
		. 'var box = M.svgPlace(vb, m), l = Object.assign({ vb: vb }, box), lf = Object.assign({ vb: vb }, fb), lv = Object.assign({ vb: vb }, vb2);' . "\n"
		. 'console.log(JSON.stringify({ m: m.map(r3), box: [r3(box.x), r3(box.y), r3(box.w), r3(box.h), box.rot], same: [corners(m), corners(M.svgMatrix(l))],'
		. ' flip: [corners(flip), corners(M.svgMatrix(lf)), !!fb.mirror], vflip: [corners(vflip), corners(M.svgMatrix(lv))],'
		. ' skew: M.svgPlace(vb, M.svgTransform("skewX(30)")), own: M.svgOwnBox({ x: 0, y: 5, w: 10, h: 0 }), text: M.svgMatrixText([1, 0, 0, 1, 0.1234567, 2]) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 2, -3, 0, 10, 20), $r['m'], 'translate, rotate and scale in order');
	pg_assert_same(array(-12, 26, 20, 12, 90), $r['box'], 'the box around the turned element');
	pg_assert_same($r['same'][0], $r['same'][1], 'the layer draws the element where it was');
	pg_assert_same($r['flip'][0], $r['flip'][1], 'a mirrored element too');
	pg_assert_true($r['flip'][2], 'and its layer is mirrored');
	pg_assert_same($r['vflip'][0], $r['vflip'][1], 'an upside-down one too');
	pg_assert_same(null, $r['skew'], 'a skew has no box');
	pg_assert_same(array('x' => 0, 'y' => 4.5, 'w' => 10, 'h' => 1), $r['own'], 'a flat box is a unit high');
	pg_assert_same('matrix(1 0 0 1 0.123457 2)', $r['text'], 'a matrix as an attribute');
}

// The written file: size, view box, the artboard's fill, definitions and
// layers in order; a transparent artboard has no fill.
function test_image_editor_svg_file()
{
	$r = image_editor_svg_node(
		'console.log(JSON.stringify({ a: M.svgFile(20, 10, "#ffffff", "<defs/>", ["<rect/>", "<circle/>"]), b: M.svgFile(5, 5, "transparent", "", []), e: M.svgEscape("a<\"&>") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="10" viewBox="0 0 20 10"><defs/><rect width="20" height="10" fill="#ffffff"/><rect/><circle/></svg>', $r['a'], 'the whole file');
	pg_assert_false(strpos($r['b'], '<rect') !== false, 'no fill on a transparent artboard');
	pg_assert_same('a&lt;&quot;&amp;&gt;', $r['e'], 'escaped');

	// The file the editor writes passes the sanitizer unchanged in substance.
	pg_assert_contains('<rect width="20" height="10" fill="#ffffff"/>', pg_svg_sanitize($r['a']), 'the sanitizer keeps it');
}
