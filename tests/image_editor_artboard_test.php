<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's canvas and artboard (the ARTBOARD section of
 * assets/js/pg_image_editor_model.js, run under Node): the preset of a size,
 * dragging the board by its edges and corners with the ratio kept or from
 * the centre, a size placed by its anchor, the board around the layers, and
 * moving the board without moving the artwork.
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
function image_editor_artboard_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieboard');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var r3 = function (v) { return Math.round(v * 1000) / 1000; };' . "\n"
		. 'var box = function (b) { return b ? [r3(b.x), r3(b.y), r3(b.w), r3(b.h)] : null; };' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A size names its preset; any other size is custom; sides stay in range.
function test_image_editor_artboard_presets()
{
	$r = image_editor_artboard_node(
		'console.log(JSON.stringify({ story: M.boardPreset(1080, 1920), share: M.boardPreset(1200, 630), other: M.boardPreset(1000, 1000),'
		. ' sides: [M.boardSide(3), M.boardSide(9000), M.boardSide("640.4"), M.boardSide("x")], names: M.BOARD_PRESETS.map(function (p) { return p[0]; }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('story', $r['story'], '1080 x 1920 is the story');
	pg_assert_same('share', $r['share'], '1200 x 630 is the share image');
	pg_assert_same('custom', $r['other'], 'any other size is custom');
	pg_assert_same(array(16, 8000, 640, 16), $r['sides'], 'sides between 16 and 8000 whole pixels');
	pg_assert_same(array('square', 'portrait', 'story', 'share', 'banner'), $r['names'], 'the five presets of the dialog');
}

// An edge moves only its side; a corner its two; Shift keeps the ratio;
// Alt grows both sides around the centre.
function test_image_editor_artboard_drag()
{
	$r = image_editor_artboard_node(
		'console.log(JSON.stringify({ e: box(M.boardDrag(1000, 500, "e", 200, 40)), w: box(M.boardDrag(1000, 500, "w", -100, 0)),'
		. ' nw: box(M.boardDrag(1000, 500, "nw", -100, -50)), keep: box(M.boardDrag(1000, 500, "se", 500, 0, true)),'
		. ' keepEdge: box(M.boardDrag(1000, 500, "s", 0, 250, true)), centre: box(M.boardDrag(1000, 500, "e", 100, 0, false, true)),'
		. ' small: box(M.boardDrag(1000, 500, "e", -2000, 0)), big: box(M.boardDrag(1000, 500, "se", 9000, 0, true)) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 0, 1200, 500), $r['e'], 'the right edge widens the board to the right');
	pg_assert_same(array(-100, 0, 1100, 500), $r['w'], 'the left edge grows it to the left');
	pg_assert_same(array(-100, -50, 1100, 550), $r['nw'], 'a corner moves its two sides');
	pg_assert_same(array(0, 0, 1500, 750), $r['keep'], 'Shift keeps the ratio from a corner');
	pg_assert_same(array(-250, 0, 1500, 750), $r['keepEdge'], 'from an edge the other side grows around its middle');
	pg_assert_same(array(-100, 0, 1200, 500), $r['centre'], 'Alt sizes from the centre');
	pg_assert_same(array(0, 0, 16, 500), $r['small'], 'never under 16 pixels');
	pg_assert_same(8000, $r['big'][2], 'never over 8000 pixels, the ratio kept');
	pg_assert_same(4000, $r['big'][3], 'the other side by the ratio');
}

// A size typed in sits on the anchor; the board fits the visible layers.
function test_image_editor_artboard_anchor_and_fit()
{
	$r = image_editor_artboard_node(
		'var layers = [{ id: "a", type: "rect", x: 100.4, y: 50, w: 200, h: 100 }, { id: "b", type: "text", x: 500, y: 300, w: 99.2, h: 20 }, { id: "c", type: "rect", x: -900, y: -900, w: 10, h: 10, hidden: true }];' . "\n"
		. 'console.log(JSON.stringify({ centre: box(M.boardAnchored(1000, 1000, 1200, 800, 0.5, 0.5)), topLeft: box(M.boardAnchored(1000, 1000, 1200, 800, 0, 0)),'
		. ' bottomRight: box(M.boardAnchored(1000, 1000, 600, 800, 1, 1)), around: box(M.boardAround(layers)), none: M.boardAround([]),'
		. ' turned: box(M.boardAround([{ type: "rect", x: 0, y: 0, w: 100, h: 100, rot: 90 }])) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(-100, 100, 1200, 800), $r['centre'], 'the centre anchor grows and trims both sides alike');
	pg_assert_same(array(0, 0, 1200, 800), $r['topLeft'], 'the top left stays');
	pg_assert_same(array(400, 200, 600, 800), $r['bottomRight'], 'the bottom right stays');
	pg_assert_same(array(100, 50, 500, 270), $r['around'], 'whole pixels around the visible layers');
	pg_assert_same(null, $r['none'], 'no layers, no box');
	pg_assert_same(array(0, 0, 100, 100), $r['turned'], 'a turned layer by its corners');
}

// Moving the board moves every layer the other way by whole pixels; the
// rest of the document stays.
function test_image_editor_artboard_apply()
{
	$r = image_editor_artboard_node(
		'var doc = M.newDocument({ blank: { width: 1000, height: 800, background: "#000000" } });' . "\n"
		. 'doc.layers = [{ id: "a", type: "rect", x: 100, y: 50, w: 200, h: 100, rot: 30 }, { id: "b", type: "image", x: 10, y: 20, w: 40, h: 30, src: "x" },'
		. ' { id: "c", type: "path", x: 0, y: 0, w: 10, h: 10, knots: [{ x: 0, y: 0, ix: 0, iy: 0, ox: 0, oy: 0 }] }];' . "\n"
		. 'var next = M.applyBoard(doc, { x: -99.6, y: 20.2, w: 1200.4, h: 700 });' . "\n"
		. 'console.log(JSON.stringify({ board: next.artboard, a: [next.layers[0].x, next.layers[0].y, next.layers[0].w, next.layers[0].rot], b: [next.layers[1].x, next.layers[1].y],'
		. ' knot: next.layers[2].knots[0].x, old: [doc.artboard.width, doc.layers[0].x], same: M.applyBoard(doc, { x: 0, y: 0, w: 1000, h: 800 }).layers[0].x }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('width' => 1200, 'height' => 700, 'background' => '#000000'), $r['board'], 'the new size, the background kept');
	pg_assert_same(array(200, 30, 200, 30), $r['a'], 'a shape moves the other way, its size and angle kept');
	pg_assert_same(array(110, 0), $r['b'], 'a picture too');
	pg_assert_same(0, $r['knot'], 'a path\'s knots stay parts of its box');
	pg_assert_same(array(1000, 100), $r['old'], 'the old document is not changed');
	pg_assert_same(100, $r['same'], 'a board that does not move leaves the layers');
}

// A picture's canvas: margins around the crop box, an outward frame whose
// ring has the canvas as its hole, and the layout the save sends.
function test_image_editor_artboard_picture_canvas()
{
	$r = image_editor_artboard_node(
		'var doc = M.newDocument({ fileId: 7 }); doc.source.width = 1000; doc.source.height = 800;' . "\n"
		. 'var none = M.saveLayout(doc);' . "\n"
		. 'var grown = M.applyCanvas(doc, { x: -100.4, y: 0, w: 1200, h: 700 });' . "\n"
		. 'var back = M.applyCanvas(grown, { x: 100, y: 0, w: 1000, h: 800 });' . "\n"
		. 'var framed = M.clone(doc); framed.frame = { kind: "polaroid", color: "#fafafa", width: 4, outside: true };' . "\n"
		. 'var fb = M.saveBox(framed), ring = M.frameShape(framed.frame, fb), p = ring.pads;' . "\n"
		. 'var sized = M.clone(grown); sized.geometry.output = { w: 500, h: 400 };' . "\n"
		. 'var inward = M.clone(doc); inward.frame = { kind: "polaroid", color: "#fafafa", width: 4 };' . "\n"
		. 'console.log(JSON.stringify({ none: none, canvas: grown.canvas, box: box(M.canvasBox(grown)), layout: M.saveLayout(grown), back: back.canvas,'
		. ' hole: [r3(fb.x + p.l), r3(fb.y + p.t), r3(fb.w - p.l - p.r), r3(fb.h - p.t - p.b)], framed: M.saveLayout(framed),'
		. ' sized: M.saveLayout(sized), size: M.saveSize(sized), inward: M.saveLayout(inward), corners: M.frameOutward({ kind: "corners", outside: true }),'
		. ' layers: M.applyCanvas(doc, { x: -10, y: 0, w: 1010, h: 800 }).layers.length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(null, $r['none'], 'a picture without a canvas lays on nothing');
	pg_assert_same(array('l' => 100, 't' => 0, 'r' => 100, 'b' => -100, 'background' => '#ffffff'), $r['canvas'], 'whole units past each side, a trim below 0');
	pg_assert_same(array(-100, 0, 1200, 700), $r['box'], 'the canvas box around the crop box');
	pg_assert_same(array('w' => 1200, 'h' => 700, 'x' => 100, 'y' => 0, 'fill' => '#ffffff'), $r['layout'], 'the picture placed on the canvas, its foot trimmed');
	pg_assert_same(null, $r['back'], 'a canvas back on the picture with a white fill is none');
	pg_assert_same(array(0, 0, 1000, 800), $r['hole'], 'an outward polaroid has the picture as its hole');
	pg_assert_same('#fafafa', $r['framed']['fill'], 'an outward frame alone fills in its colour');
	pg_assert_true(($r['framed']['h'] - 800 - $r['framed']['y']) > 3 * $r['framed']['y'], 'the polaroid\'s foot is the wide side');
	pg_assert_same(array('w' => 600, 'h' => 350, 'x' => 50, 'y' => 0, 'fill' => '#ffffff'), $r['sized'], 'an output size scales the canvas');
	pg_assert_same(array('w' => 600, 'h' => 350), $r['size'], 'the size the save writes');
	pg_assert_same(null, $r['inward'], 'an inward frame keeps the size');
	pg_assert_false($r['corners'], 'corner marks never lie outside');
	pg_assert_same(0, $r['layers'], 'the layers stay where they are');
}

// The extend step's request is checked before any pixel work.
function test_image_editor_artboard_extend_check()
{
	pg_assert_same(null, pg_image_edit_extend(null, 100, 80), 'none asked');
	pg_assert_same(array('w' => 120, 'h' => 70, 'x' => 10, 'y' => -10, 'fill' => array(255, 0, 16)),
		pg_image_edit_extend(array('w' => 120, 'h' => 70, 'x' => 10, 'y' => -10, 'fill' => '#ff0010'), 100, 80), 'a canvas with its fill');
	pg_assert_same(null, pg_image_edit_extend(array('w' => 120, 'h' => 70, 'x' => 0, 'y' => 0, 'fill' => 'transparent'), 100, 80)['fill'], 'transparent');

	foreach (array(
		'not an object' => 'x',
		'too wide' => array('w' => 8001, 'h' => 70, 'x' => 0, 'y' => 0, 'fill' => '#ffffff'),
		'a fraction' => array('w' => 120.5, 'h' => 70, 'x' => 0, 'y' => 0, 'fill' => '#ffffff'),
		'a string' => array('w' => '120', 'h' => 70, 'x' => 0, 'y' => 0, 'fill' => '#ffffff'),
		'off the canvas' => array('w' => 120, 'h' => 70, 'x' => 120, 'y' => 0, 'fill' => '#ffffff'),
		'off above' => array('w' => 120, 'h' => 70, 'x' => 0, 'y' => -80, 'fill' => '#ffffff'),
		'no fill' => array('w' => 120, 'h' => 70, 'x' => 0, 'y' => 0),
		'a named colour' => array('w' => 120, 'h' => 70, 'x' => 0, 'y' => 0, 'fill' => 'red'),
	) as $label => $value) {
		pg_assert_same(false, pg_image_edit_extend($value, 100, 80), $label . ' is refused');
	}

	pg_assert_same(array(10, 0, 0, 5, 100, 70), pg_image_edit_extend_box(array('w' => 120, 'h' => 70, 'x' => 10, 'y' => -5), 100, 80), 'grown left and right, trimmed top and bottom');
	pg_assert_same(array(0, 0, 30, 0, 50, 80), pg_image_edit_extend_box(array('w' => 50, 'h' => 90, 'x' => -30, 'y' => 0), 100, 80), 'trimmed on the left');
}

// The pictures sent beside the document: layer ids, PNG or JPEG data.
function test_image_editor_artboard_picture_list()
{
	$png = 'data:image/png;base64,iVBORw0KGgo=';

	pg_assert_same(array(), pg_image_editor_picture_list(null), 'none sent');
	pg_assert_same(array(array('id' => 'l1', 'data' => $png, 'name' => 'logo.webp')), pg_image_editor_picture_list(array(array('id' => 'l1', 'data' => $png, 'name' => 'logo.webp'))), 'kept with its name');
	pg_assert_same('', pg_image_editor_picture_list(array(array('id' => 'l1', 'data' => $png)))[0]['name'], 'a pasted one has no name');
	pg_assert_same(false, pg_image_editor_picture_list(array(array('id' => 'l 1', 'data' => $png))), 'an id outside the editor\'s');
	pg_assert_same(false, pg_image_editor_picture_list(array(array('id' => 'l1', 'data' => 'data:image/svg+xml;base64,PHN2Zz4='))), 'no SVG');
	pg_assert_same(false, pg_image_editor_picture_list(array(array('id' => 'l1', 'data' => 'https://example.com/a.png'))), 'no address');
	pg_assert_same(false, pg_image_editor_picture_list(array_fill(0, 51, array('id' => 'l1', 'data' => $png))), 'at most 50');
	pg_assert_same(false, pg_image_editor_picture_list('x'), 'not a list');
}

// GD lays the picture on the canvas: the fill around it, the trimmed part
// gone; without alpha a transparent fill is white.
function test_image_editor_artboard_gd_extend()
{
	if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
		return;
	}

	$solid = function () {
		$image = imagecreatetruecolor(40, 30);
		imagealphablending($image, false);
		imagesavealpha($image, true);
		imagefilledrectangle($image, 0, 0, 39, 29, imagecolorallocatealpha($image, 200, 10, 10, 0));

		return $image;
	};
	$pixel = function ($image, $x, $y) {
		$c = imagecolorat($image, $x, $y);

		return array(($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, ($c >> 24) & 0x7F);
	};

	$out = pg_image_edit_gd_extend($solid(), array('w' => 60, 'h' => 20, 'x' => 10, 'y' => -5, 'fill' => array(0, 0, 255)), true);
	pg_assert_same(array(60, 20), array(imagesx($out), imagesy($out)), 'the canvas size');
	pg_assert_same(array(0, 0, 255, 0), $pixel($out, 5, 10), 'the fill on the left');
	pg_assert_same(array(200, 10, 10, 0), $pixel($out, 10, 0), 'the picture from x 10, its top trimmed');
	pg_assert_same(array(200, 10, 10, 0), $pixel($out, 49, 19), 'to its right edge');
	pg_assert_same(array(0, 0, 255, 0), $pixel($out, 50, 10), 'the fill on the right');

	$clear = pg_image_edit_gd_extend($solid(), array('w' => 50, 'h' => 30, 'x' => 0, 'y' => 0, 'fill' => null), true);
	pg_assert_same(127, $pixel($clear, 45, 5)[3], 'a transparent fill keeps alpha');

	$white = pg_image_edit_gd_extend($solid(), array('w' => 50, 'h' => 30, 'x' => 0, 'y' => 0, 'fill' => null), false);
	pg_assert_same(array(255, 255, 255, 0), $pixel($white, 45, 5), 'white where the format has no alpha');
}

// A canvas saved in Files keeps its document: the JSON a version row holds,
// opened again by the model, gives back the text as a layer.
function test_image_editor_artboard_canvas_document_round_trip()
{
	$doc = json_decode('{"v":1,"source":null,"artboard":{"width":1080,"height":1080,"background":"#ffffff"},'
		. '"geometry":{"rot":0,"flipX":false,"flipY":false,"straighten":0,"crop":null,"cropShape":"rect","output":null},"adjust":{},"filter":"none","frame":null,'
		. '"layers":[{"id":"t1","type":"text","text":"Hello","x":100,"y":100,"w":300,"h":80},{"id":"p1","type":"image","src":"/logo.png","fileId":12,"x":0,"y":0,"w":50,"h":50}]}');

	pg_assert_true(pg_image_editor_canvas_is_doc($doc), 'a canvas document');
	pg_assert_false(pg_image_editor_canvas_is_doc(json_decode('{"v":1,"source":{"fileId":3},"artboard":null}')), 'a picture\'s is not');

	$json = pg_image_editor_canvas_doc_json($doc);
	pg_assert_true($json !== '', 'kept');
	pg_assert_same('', pg_image_editor_canvas_doc_json(json_decode(str_replace('"/logo.png"', '"data:image/png;base64,AAAA"', json_encode($doc, JSON_UNESCAPED_SLASHES)))), 'a picture still inline is not kept');

	$r = image_editor_artboard_node('var doc = M.canvasOpenDoc(' . $json . ');' . "\n"
		. 'console.log(JSON.stringify({ types: doc.layers.map(function (l) { return l.type; }), text: doc.layers[0].text, file: doc.layers[1].fileId, board: [doc.artboard.width, doc.artboard.height] }));');

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('text', 'image'), $r['types'], 'the layers come back');
	pg_assert_same('Hello', $r['text'], 'the text is editable again');
	pg_assert_same(12, $r['file'], 'the picture by its file');
	pg_assert_same(array(1080, 1080), $r['board'], 'the board');
}

// The save order: a canvas keeps its document after its pictures became
// files; a picture's copy makes no files of its pictures.
function test_image_editor_artboard_save_order()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');
	$canvas = substr($source, (int) strpos($source, 'function pg_image_editor_save_canvas('));
	$canvas = substr($canvas, 0, (int) strpos($canvas, "\n}\n"));
	$save = substr($source, (int) strpos($source, 'function pg_image_editor_save('));
	$save = substr($save, 0, (int) strpos($save, "\n}\n"));

	pg_assert_true(strpos($canvas, 'pg_image_editor_pictures_saved(') < strpos($canvas, 'pg_image_editor_canvas_keep('), 'pictures before the document is kept');
	pg_assert_contains("if (\$mode === 'replace') {\n        \$record = pg_image_editor_pictures_saved(", $save, 'a picture keeps its pictures as files on a replace only');
}
