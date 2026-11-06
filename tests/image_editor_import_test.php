<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Import in the image editor's top bar: an imported SVG's ids made
 * unique and its references renamed with them (the SVG import section of
 * assets/js/pg_image_editor_model.js, run under Node), the fit of an
 * imported picture or drawing, the canvas rail without the Image item, and
 * the Files listing with SVG files for the import.
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
function image_editor_import_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieimp');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Each id gets one prefix that clashes with nothing in the document; a
// second import of the same file gets the next one.
function test_image_editor_import_unique_ids()
{
	$r = image_editor_import_node(
		'var a = M.svgIdMap(["g1", "clip", "g1", ""], []);' . "\n"
		. 'var b = M.svgIdMap(["g1", "clip"], ["i1-g1", "i1-clip", "pgsvg-1"]);' . "\n"
		. 'var c = M.svgIdMap(["clip"], ["i1-g1", "i2-clip"]);' . "\n"
		. 'console.log(JSON.stringify({ a: a, b: b, c: c }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('g1' => 'i1-g1', 'clip' => 'i1-clip'), $r['a'], 'each id once, an empty one left out');
	pg_assert_same(array('g1' => 'i2-g1', 'clip' => 'i2-clip'), $r['b'], 'an id already in the document moves every id to the next prefix');
	pg_assert_same(array('clip' => 'i1-clip'), $r['c'], 'only a real clash counts');
}

// References follow the new ids: href, url() in an attribute and a style,
// selectors in a style sheet; a colour and an unknown id stay.
function test_image_editor_import_references()
{
	$r = image_editor_import_node(
		'var map = { g1: "i1-g1", root: "i1-root", fff: "i1-fff" };' . "\n"
		. 'console.log(JSON.stringify({'
		. ' href: M.svgRenameValue("#g1", map), other: M.svgRenameValue("#nope", map),'
		. ' fill: M.svgRenameValue("url(#g1)", map), quoted: M.svgRenameValue("fill: url(\'#g1\'); stroke: url(#nope)", map),'
		. ' plain: M.svgRenameValue("#fff", {}), none: M.svgRenameValue(null, map),'
		. ' css: M.svgRenameCss("#root path, #root .a{fill:#fff;stroke:url(#g1)}#root #g1 > rect{opacity:.5}", map)'
		. ' }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('#i1-g1', $r['href'], 'a link to an element');
	pg_assert_same('#nope', $r['other'], 'an id not in the file stays');
	pg_assert_same('url(#i1-g1)', $r['fill'], 'a paint server');
	pg_assert_same('fill: url(\'#i1-g1\'); stroke: url(#nope)', $r['quoted'], 'quoted, in a style attribute');
	pg_assert_same('#fff', $r['plain'], 'a colour is not a reference');
	pg_assert_same('', $r['none'], 'no value');
	pg_assert_same('#i1-root path, #i1-root .a{fill:#fff;stroke:url(#i1-g1)}#i1-root #i1-g1 > rect{opacity:.5}', $r['css'], 'the scope and selectors renamed, the colour kept');
}

// A picture is fitted into the visible part without growing; a drawing
// grows to the same share; both centred.
function test_image_editor_import_fit()
{
	$r = image_editor_import_node(
		'var vr = { x: 100, y: 50, w: 1000, h: 500 };' . "\n"
		. 'console.log(JSON.stringify({ big: M.svgFit(2000, 500, vr, 0.6), small: M.svgFit(100, 50, vr, 0.6), icon: M.svgFit(24, 24, vr, 0.6, true), zero: M.svgFit(0, 0, vr, 0.6) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('x' => 300, 'y' => 225, 'w' => 600, 'h' => 150), $r['big'], 'a large picture at 60 % of the width');
	pg_assert_same(array('x' => 550, 'y' => 275, 'w' => 100, 'h' => 50), $r['small'], 'a small picture keeps its size');
	pg_assert_same(array('x' => 450, 'y' => 150, 'w' => 300, 'h' => 300), $r['icon'], 'a drawing grows to 60 % of the height');
	pg_assert_same(1, $r['zero']['w'], 'no size reads as a unit');
}

// The canvas rail has no Image item (Import in the top bar takes its
// place); a caller may still name the tool.
function test_image_editor_import_rail()
{
	$r = image_editor_import_node(
		'console.log(JSON.stringify({ canvas: M.resolveTools("canvas").list, caller: M.resolveTools("canvas", ["image", "text"]).list }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_false(in_array('image', $r['canvas'], true), 'the canvas rail has no Image item');
	pg_assert_same(array('image', 'text'), $r['caller'], 'a caller\'s list keeps it');
}

// The import's Files listing adds SVG files, marked; the sticker panel's
// stays pictures only.
function test_image_editor_import_files()
{
	$rows = array(
		array('id' => 1, 'name' => 'a.png', 'type' => 'png', 'folder' => 2, 'image_width' => 10, 'image_height' => 5),
		array('id' => 2, 'name' => 'b.svg', 'type' => 'svg', 'folder' => 2, 'image_width' => 0, 'image_height' => 0));

	$pictures = pg_image_editor_sticker_rows($rows, null);
	$all = pg_image_editor_sticker_rows($rows, null, true);

	pg_assert_same(array(1), array_column($pictures, 'id'), 'pictures only for a sticker');
	pg_assert_same(array(1, 2), array_column($all, 'id'), 'SVG files for the import');
	pg_assert_same(array(false, true), array_column($all, 'svg'), 'an SVG file is marked');
	pg_assert_contains("'svg'", pg_image_editor_sticker_where('', null, array(), false, true), 'the query asks for SVG files');
	pg_assert_false(strpos(pg_image_editor_sticker_where('', null, array(), false), "'svg'") !== false, 'the sticker query does not');
}
