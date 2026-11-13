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

// Convert to a layer: the canvas becomes an artboard of its size and fill,
// the picture the server drew a picture layer at the bottom where the
// picture lay; the other layers keep their order and place, the picture's
// own areas and the selection go (their pixels are in the drawn picture).
function test_image_editor_picture_to_layer()
{
	$r = image_editor_picture_node(
		'var sq = [[[0, 0], [1, 0], [1, 1], [0, 1]]];'
		. 'photo.layers.push({ id: "l3", type: "erase", shape: "area", x: 200, y: 100, w: 100, h: 50, area: sq }, { id: "l5", type: "image", src: "a.png", x: 300, y: 200, w: 100, h: 50, rot: 0, opacity: 1 });'
		. 'photo.selection = { ops: [], invert: true, feather: 0 };'
		. 'var u = M.applyCanvas(M.lockPicture(photo, false), { x: -100, y: -50, w: 1200, h: 600 }); u.canvas.background = "#000000";'
		. 'u = M.placePicture(u, { x: 200, y: 100, w: 500, h: 250 });'
		. 'var d = M.pictureLayer(u, "data:image/png;base64,AA", "photo.png"), plain = M.pictureLayer(photo, "data:image/jpeg;base64,AA", "photo.jpg"), b = d.layers[0];'
		. 'console.log(JSON.stringify({ board: d.artboard, source: d.source, selection: d.selection === undefined,'
		. ' ids: d.layers.map(function (l) { return l.id; }), first: [b.type, b.src, b.fileName, b.rot, b.opacity], box: box(b),'
		. ' shape: box(d.layers[1]), cut: box(d.layers[2]), fresh: ["l1", "l2", "l3", "l5"].indexOf(b.id) < 0,'
		. ' plainBoard: plain.artboard, plainBox: box(plain.layers[0]), photoLayers: photo.layers.length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('width' => 1200, 'height' => 600, 'background' => '#000000'), $r['board'], 'an artboard of the canvas, its fill kept');
	pg_assert_same(null, $r['source'], 'no picture left');
	pg_assert_true($r['selection'], 'the selection goes with the picture');
	pg_assert_same(3, count($r['ids']), 'the picture layer, the shape and the picture layer above');
	pg_assert_same(array('image', 'data:image/png;base64,AA', 'photo.png', 0, 1), $r['first'], 'the drawn picture as a picture layer at the bottom');
	pg_assert_true($r['fresh'], 'with an id of its own');
	pg_assert_same(array(300, 150, 500, 250), $r['box'], 'where the picture lay, at its size on the canvas');
	pg_assert_same(array(200, 150, 50, 40), $r['shape'], 'a shape stays where it was');
	pg_assert_same(array(400, 250, 100, 50), $r['cut'], 'a picture layer too');
	pg_assert_same(array('width' => 1000, 'height' => 500, 'background' => '#ffffff'), $r['plainBoard'], 'a photo without a canvas: an artboard of the picture');
	pg_assert_same(array(0, 0, 1000, 500), $r['plainBox'], 'the layer covers it');
	pg_assert_same(4, $r['photoLayers'], 'the document given is left as it was');
}

// The picture Convert to a layer asks for (action base with png) keeps what
// is see-through, a JPEG's too: a round crop's corners stay clear in a PNG;
// without the flag the base keeps the file's own format.
function test_image_editor_picture_base_png()
{
	if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = sys_get_temp_dir() . '/pgiepicbase' . getmypid() . mt_rand(1000, 9999);
	@mkdir($folder, 0777, true);
	$path = $folder . '/photo.jpg';
	$image = imagecreatetruecolor(80, 60);
	imagefilledrectangle($image, 0, 0, 79, 59, imagecolorallocate($image, 200, 30, 30));
	imagejpeg($image, $path, 90);
	pg_image_destroy($image);

	$doc = array(
		'v' => 1,
		'source' => array('fileId' => 0, 'width' => 80, 'height' => 60),
		'artboard' => null,
		'geometry' => array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => array('x' => 10, 'y' => 0, 'w' => 60, 'h' => 60), 'cropShape' => 'circle', 'output' => null),
		'adjust' => array(),
		'filter' => 'none',
		'frame' => null,
		'layers' => array());

	try {
		$png = pg_image_editor_render($path, array('doc' => $doc), true);
		$own = pg_image_editor_render($path, array('doc' => $doc));

		pg_assert_same('', $png['error'], 'drawn');
		pg_assert_same('png', $png['extension'], 'as a PNG');
		pg_assert_same(array(60, 60), array_slice(getimagesizefromstring($png['data']), 0, 2), 'cropped');

		// The PNG may be written with a palette: imagecolorsforindex() reads
		// a pixel's alpha from a palette index and a truecolor value alike.
		$drawn = imagecreatefromstring($png['data']);
		$corner = imagecolorsforindex($drawn, imagecolorat($drawn, 0, 0));
		$middle = imagecolorsforindex($drawn, imagecolorat($drawn, 30, 30));
		pg_assert_true($corner['alpha'] > 100, 'a corner the circle cut stays see-through');
		pg_assert_same(0, $middle['alpha'], 'the middle is opaque');
		pg_image_destroy($drawn);

		pg_assert_same('jpg', $own['extension'], 'without the flag the file\'s own format');
		pg_assert_same(array($path), glob($folder . '/*'), 'no temporary file left, the file untouched');
	} finally {
		foreach ((array) glob($folder . '/*') as $file) {
			@unlink($file);
		}

		@rmdir($folder);
	}
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
