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

// From the site opens the site's picker where the screen prints it: a pick
// becomes the file the editor's own lists hand on, its name encoded as
// encode_url_path() writes it; nothing chosen is null.
function test_image_editor_import_picked_file()
{
	$r = image_editor_import_node(
		'console.log(JSON.stringify({'
		. ' a: M.pickedFile({ id: 7, text: "Işık plan (1).svg", url: "/Işık plan (1).svg", type: "SVG", width: "40", height: 20, size_label: "2 KB" }),'
		. ' b: M.pickedFile({ id: 3, text: "a.mp3", url: "/site/a.mp3", type: "mp3" }),'
		. ' c: M.pickedFile(null), d: M.pickedFile({ id: 0, text: "x.png" }) }));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not installed');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('id' => 7, 'name' => 'Işık plan (1).svg', 'url' => '/' . rawurlencode('Işık plan (1).svg'), 'width' => 40, 'height' => 20, 'size' => '2 KB', 'svg' => true), $r['a']);
	pg_assert_same('/site/a.mp3', $r['b']['url'], 'the site path kept');
	pg_assert_false($r['b']['svg']);
	pg_assert_same(null, $r['c']);
	pg_assert_same(null, $r['d'], 'no file without an id');
}

// Import, Video from the site, Replace, Add sound and an erased area's
// picture open the picker's canvas kinds first and keep their own lists for
// a site page; the panel screens print the picker with the loader.
function test_image_editor_import_site_picker_wired()
{
	$js = function ($part) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_' . $part . '.js');
	};

	pg_assert_contains("Editor.prototype.sitePicker = function (kind, title, ret)", $js('artboard'));
	pg_assert_contains("document.getElementById('pg_picker_config')", $js('artboard'));
	pg_assert_contains("this.sitePicker((typeof pick === 'function') ? 'canvas_picture' : 'canvas_import'", $js('artboard'));
	pg_assert_contains("this.sitePicker('canvas_video'", $js('clip'));
	pg_assert_contains("this.sitePicker('canvas_sound'", $js('sound'));

	foreach (array('canvas_picture', 'canvas_import', 'canvas_video', 'canvas_sound') as $kind) {
		pg_assert_true(isset(pg_picker_kinds()[$kind]), $kind . ' is a picker kind');
	}

	pg_assert_same(array('mp4', 'm4v', 'webm'), pg_picker_canvas_types('video'), 'the clip types');
	pg_assert_same(pg_image_editor_clip_types(), pg_picker_canvas_types('video'));
	pg_assert_same(pg_image_editor_sound_types(), pg_picker_canvas_types('sound'));
	pg_assert_contains("'svg'", "'" . implode("', '", pg_picker_canvas_types('import')) . "'");

	$fn = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');
	pg_assert_contains("(\$picker && function_exists('pg_picker_script')) ? pg_picker_script() : ''", $fn);
	pg_assert_contains('pg_image_editor_includes(false)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php'), 'a site page keeps the editor\'s lists');

	$bundle = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor.min.js');
	pg_assert_contains('canvas_sound', $bundle, 'the bundle is built');
}
