<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's picture as the bottom layer (the picture part
 * of the ARTBOARD section of assets/js/pg_image_editor_model.js, run under
 * Node): unlocking makes a canvas, a move, a resize, a crop and a turn keep
 * the canvas and the layers where they are, a delete leaves an artboard of
 * the canvas with its layers; the picture's erased and adjusted areas go
 * with it; the save's extend step takes the picture's size on the canvas.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and a 1000 x 500 photo with a shape and a
// redaction area as photo; answers its JSON; null without Node.
function image_editor_picture_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiepic');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var r3 = function (v) { return Math.round(v * 1000) / 1000; };' . "\n"
		. 'var box = function (b) { return b ? [r3(b.x), r3(b.y), r3(b.w), r3(b.h)] : null; };' . "\n"
		. 'var photo = M.newDocument({ fileId: 1 }); photo.source.width = 1000; photo.source.height = 500;' . "\n"
		. 'photo.layers = [{ id: "l1", type: "rect", x: 100, y: 100, w: 50, h: 40 }, { id: "l2", type: "redact", mode: "solid", shape: "rect", x: 0, y: 0, w: 100, h: 100 }];' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Locked by default; unlocking a photo without a canvas makes one on the
// picture; locked again it keeps its place.
function test_image_editor_picture_lock()
{
	$r = image_editor_picture_node(
		'var u = M.lockPicture(photo, false), m = M.placePicture(u, { x: 100, y: 50, w: 500, h: 250 }), l = M.lockPicture(m, true);'
		. 'console.log(JSON.stringify({ before: M.pictureFree(photo), canvas: photo.canvas || null, free: M.pictureFree(u), board: box(M.canvasBox(u)), layout: M.saveLayout(u),'
		. ' locked: M.pictureFree(l), kept: box(M.pictureBox(l)), lockedBoard: box(M.canvasBox(l)) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_false($r['before'], 'a photo\'s picture is locked');
	pg_assert_same(null, $r['canvas'], 'with no canvas');
	pg_assert_true($r['free'], 'unlocked');
	pg_assert_same(array(0, 0, 1000, 500), $r['board'], 'the canvas made on the picture');
	pg_assert_same(array('w' => 1000, 'h' => 500, 'x' => 0, 'y' => 0, 'fill' => '#ffffff'), $r['layout'], 'saved at its own size');
	pg_assert_false($r['locked'], 'locked again');
	pg_assert_same(array(100, 50, 500, 250), $r['kept'], 'where it was put');
	pg_assert_same(array(0, 0, 1000, 500), $r['lockedBoard'], 'on the same canvas');
}

// A move and a resize keep the canvas and the layers; the redaction area goes
// with the picture; the save lays the picture at its place and size.
function test_image_editor_picture_move_and_resize()
{
	$r = image_editor_picture_node(
		'var u = M.lockPicture(photo, false), mv = M.placePicture(u, { x: 300, y: -100, w: 1000, h: 500 }), rs = M.placePicture(u, { x: 200, y: 100, w: 500, h: 250 });'
		. 'console.log(JSON.stringify({ moveBoard: box(M.canvasBox(mv)), movePic: box(M.pictureBox(mv)), moveLayer: box(mv.layers[0]), moveArea: box(mv.layers[1]), moveLayout: M.saveLayout(mv),'
		. ' sizeBoard: box(M.canvasBox(rs)), sizePic: box(M.pictureBox(rs)), sizeLayer: box(rs.layers[0]), sizeArea: box(rs.layers[1]), sizeLayout: M.saveLayout(rs), size: M.saveSize(rs) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 0, 1000, 500), $r['moveBoard'], 'a move keeps the canvas');
	pg_assert_same(array(300, -100, 1000, 500), $r['movePic'], 'the picture moved');
	pg_assert_same(array(100, 100, 50, 40), $r['moveLayer'], 'a layer stays');
	pg_assert_same(array(300, -100, 100, 100), $r['moveArea'], 'the redaction area goes with the picture');
	pg_assert_same(array('w' => 1000, 'h' => 500, 'x' => 300, 'y' => -100, 'fill' => '#ffffff'), $r['moveLayout'], 'laid at its place');
	pg_assert_same(array(0, 0, 1000, 500), $r['sizeBoard'], 'a resize keeps the canvas');
	pg_assert_same(array(200, 100, 500, 250), $r['sizePic'], 'the picture at half its size');
	pg_assert_same(array(100, 100, 50, 40), $r['sizeLayer'], 'a layer stays');
	pg_assert_same(array(200, 100, 50, 50), $r['sizeArea'], 'the redaction area scaled with the picture');
	pg_assert_same(array('w' => 1000, 'h' => 500, 'x' => 200, 'y' => 100, 'fill' => '#ffffff', 'pw' => 500, 'ph' => 250), $r['sizeLayout'], 'laid at its size');
	pg_assert_same(array('w' => 1000, 'h' => 500), $r['size'], 'the saved size stays');
}

// A crop and a quarter turn of a placed picture keep the canvas and the
// layers; the turned picture keeps its middle.
function test_image_editor_picture_crop_and_turn()
{
	$r = image_editor_picture_node(
		'var u = M.placePicture(M.lockPicture(photo, false), { x: 100, y: 0, w: 500, h: 250 });'
		. 'var c = M.clone(u); c.geometry.crop = { x: 200, y: 0, w: 600, h: 500 }; M.keepCanvas(u, c);'
		. 'var t = M.turnDocument(u, 1);'
		. 'console.log(JSON.stringify({ cropBoard: box(M.canvasBox(c)), cropPic: box(M.pictureBox(c)), turnBoard: box(M.canvasBox(t)), turnPic: box(M.pictureBox(t)), turnLayer: box(t.layers[0]) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 0, 1000, 500), $r['cropBoard'], 'a crop keeps the canvas');
	pg_assert_same(array(200, 0, 300, 250), $r['cropPic'], 'the picture keeps what stays of it in place');
	pg_assert_same(array(0, 0, 1000, 500), $r['turnBoard'], 'a turn keeps the canvas');
	pg_assert_same(array(225, -125, 250, 500), $r['turnPic'], 'the picture turns around its middle');
	pg_assert_same(array(100, 100, 50, 40), $r['turnLayer'], 'a layer stays');
}

// A delete leaves an artboard of the canvas: its fill, the layers moved
// onto it, no redaction areas, no picture.
function test_image_editor_picture_delete()
{
	$r = image_editor_picture_node(
		'var u = M.lockPicture(photo, false); u = M.applyCanvas(u, { x: -100, y: -50, w: 1200, h: 600 }); u.canvas.background = "#000000";'
		. 'var d = M.dropPicture(u);'
		. 'console.log(JSON.stringify({ board: d.artboard, source: d.source, canvas: d.canvas, layers: d.layers.map(function (l) { return [l.id].concat(box(l)); }), free: M.pictureFree(d) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('width' => 1200, 'height' => 600, 'background' => '#000000'), $r['board'], 'the canvas with its fill');
	pg_assert_same(null, $r['source'], 'no picture');
	pg_assert_same(null, $r['canvas'], 'no picture canvas');
	pg_assert_same(array(array('l1', 200, 150, 50, 40)), $r['layers'], 'the layers where they were on the canvas, no redaction');
	pg_assert_false($r['free'], 'nothing to move');
}

// An erased and an adjusted area of Select area are the picture's own, as a
// redaction area is: they move and scale with the picture and go when it is
// deleted; a picture layer (a cut-out over the hole) stays where it is.
function test_image_editor_picture_areas()
{
	$r = image_editor_picture_node(
		'var sq = [[[0, 0], [1, 0], [1, 1], [0, 1]]];'
		. 'photo.layers.push({ id: "l3", type: "erase", shape: "area", x: 200, y: 100, w: 100, h: 50, feather: 4, area: sq },'
		. ' { id: "l4", type: "adjust", shape: "area", x: 400, y: 200, w: 100, h: 100, adjust: { brightness: 10 }, filter: "none", area: sq },'
		. ' { id: "l5", type: "image", x: 200, y: 100, w: 100, h: 50 });'
		. 'var u = M.lockPicture(photo, false), mv = M.placePicture(u, { x: 300, y: -100, w: 1000, h: 500 }), rs = M.placePicture(u, { x: 200, y: 100, w: 500, h: 250 }), d = M.dropPicture(u);'
		. 'console.log(JSON.stringify({ own: photo.layers.map(function (l) { return M.pictureArea(l); }),'
		. ' moveErase: box(mv.layers[2]), moveAdjust: box(mv.layers[3]), moveCut: box(mv.layers[4]),'
		. ' sizeErase: box(rs.layers[2]), sizeFeather: rs.layers[2].feather, sizeAdjust: box(rs.layers[3]), sizeCut: box(rs.layers[4]),'
		. ' dropped: d.layers.map(function (l) { return l.id; }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(false, false, true, true, false), $r['own'], 'the erased and the adjusted areas are the picture\'s own');
	pg_assert_same(array(500, 0, 100, 50), $r['moveErase'], 'the erased area goes with the picture');
	pg_assert_same(array(700, 100, 100, 100), $r['moveAdjust'], 'the adjusted area goes with the picture');
	pg_assert_same(array(200, 100, 100, 50), $r['moveCut'], 'a cut-out stays');
	pg_assert_same(array(300, 150, 50, 25), $r['sizeErase'], 'the erased area scaled with the picture');
	pg_assert_same(2, $r['sizeFeather'], 'its feather too');
	pg_assert_same(array(400, 200, 50, 50), $r['sizeAdjust'], 'the adjusted area scaled with the picture');
	pg_assert_same(array(200, 100, 100, 50), $r['sizeCut'], 'a cut-out stays');
	pg_assert_same(array('l1', 'l5'), $r['dropped'], 'the picture\'s areas go with it');
}

// The extend step takes the picture's size on the canvas.
function test_image_editor_picture_extend_size()
{
	pg_assert_same(array('w' => 1000, 'h' => 500, 'x' => -100, 'y' => 100, 'pw' => 500, 'ph' => 250, 'fill' => array(255, 255, 255)),
		pg_image_edit_extend(array('w' => 1000, 'h' => 500, 'x' => -100, 'y' => 100, 'pw' => 500, 'ph' => 250, 'fill' => '#ffffff'), 1000, 500), 'a picture at half its size');
	pg_assert_same(false, pg_image_edit_extend(array('w' => 1000, 'h' => 500, 'x' => -500, 'y' => 0, 'pw' => 500, 'ph' => 250, 'fill' => '#ffffff'), 1000, 500), 'off the canvas at its size');
	pg_assert_same(false, pg_image_edit_extend(array('w' => 1000, 'h' => 500, 'x' => 0, 'y' => 0, 'pw' => 8001, 'ph' => 250, 'fill' => '#ffffff'), 1000, 500), 'too large');
	pg_assert_same(false, pg_image_edit_extend(array('w' => 1000, 'h' => 500, 'x' => 0, 'y' => 0, 'pw' => 50.5, 'ph' => 250, 'fill' => '#ffffff'), 1000, 500), 'a fraction');
}
