<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor model (assets/js/pg_image_editor_model.js): the
 * matrices, quarter turns and flips, the cover scale of a straightened crop,
 * zooming around the pointer, the tools of a call, new documents and the
 * undo history. They run the model under Node and are skipped where there is
 * no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and prints its JSON; null when there is
// no Node, ['error' => …] when the script fails.
function image_editor_model_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiemodel');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'function near(a, b) { return Math.abs(a - b) < 1e-6; }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// A matrix times its inverse is the identity; translate, scale and rotate
// move points the SVG way.
function test_image_editor_model_matrices()
{
	$r = image_editor_model_node(
		'var m = M.mul(M.translate(30, -12), M.mul(M.rotate(33), M.scale(2, 0.5)));' . "\n"
		. 'var id = M.mul(m, M.inv(m));' . "\n"
		. 'var p = M.apply(M.rotate(90), 10, 0);' . "\n"
		. 'console.log(JSON.stringify({'
		. ' identity: near(id[0], 1) && near(id[1], 0) && near(id[2], 0) && near(id[3], 1) && near(id[4], 0) && near(id[5], 0),'
		. ' moved: M.apply(M.translate(5, 7), 1, 1), scaled: M.apply(M.scale(3), 2, 4),'
		. ' turned: near(p[0], 0) && near(p[1], 10) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['identity'] === true, 'a matrix times its inverse is the identity');
	pg_assert_same(array(6, 8), $r['moved'], 'translate moves a point');
	pg_assert_same(array(6, 12), $r['scaled'], 'scale with one factor scales both axes');
	pg_assert_true($r['turned'] === true, 'rotate(90) turns +x onto +y (clockwise on screen)');
}

// A quarter turn clockwise sends the top-left corner to the top right and
// swaps the size; a flip mirrors inside the rotated space.
function test_image_editor_model_quarter_turns_and_flips()
{
	$r = image_editor_model_node(
		'var a = M.flipRot(400, 300, 90, false, false);' . "\n"
		. 'var b = M.flipRot(400, 300, 0, true, false);' . "\n"
		. 'var c = M.flipRot(400, 300, -90, false, false);' . "\n"
		. 'var d = M.flipRot(400, 300, 180, false, true);' . "\n"
		. 'console.log(JSON.stringify({'
		. ' size: [a.rw, a.rh], topLeft: M.apply(a.fr, 0, 0), topRight: M.apply(a.fr, 400, 0),'
		. ' flipped: M.apply(b.fr, 0, 0), minus: c.rot,'
		. ' upsideFlip: M.apply(d.fr, 0, 0) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(300, 400), $r['size'], 'a quarter turn swaps width and height');
	pg_assert_same(array(300, 0), $r['topLeft'], 'the top-left corner goes to the top right');
	pg_assert_same(array(300, 400), $r['topRight'], 'the top-right corner goes to the bottom right');
	pg_assert_same(array(400, 0), $r['flipped'], 'a horizontal flip sends the left edge to the right');
	pg_assert_same(270, $r['minus'], 'a turn of -90 is a turn of 270');
	pg_assert_same(array(400, 0), $r['upsideFlip'], 'a half turn then a vertical flip mirrors left and right');
}

// The cover scale is 1 without straightening and, with it, keeps every crop
// corner inside the turned picture.
function test_image_editor_model_cover_scale()
{
	$r = image_editor_model_node(
		'var crop = { x: 0, y: 0, w: 400, h: 300 };' . "\n"
		. 'var s = M.coverScale(crop, 400, 300, 10);' . "\n"
		. 'var m = M.mul(M.translate(200, 150), M.mul(M.rotate(10), M.mul(M.scale(s), M.translate(-200, -150))));' . "\n"
		. 'var back = M.inv(m), inside = true;' . "\n"
		. '[[0, 0], [400, 0], [0, 300], [400, 300]].forEach(function (p) {' . "\n"
		. '    var q = M.apply(back, p[0], p[1]);' . "\n"
		. '    if (q[0] < -1e-6 || q[1] < -1e-6 || q[0] > 400 + 1e-6 || q[1] > 300 + 1e-6) inside = false;' . "\n"
		. '});' . "\n"
		. 'console.log(JSON.stringify({ none: M.coverScale(crop, 400, 300, 0), grows: s > 1, inside: inside }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(1, $r['none'], 'no straightening, no scale');
	pg_assert_true($r['grows'] === true, 'a straightened picture is scaled up');
	pg_assert_true($r['inside'] === true, 'every crop corner stays on the picture');
}

// Zooming keeps the document point under the pointer, stays inside its
// limits, and a fitted view is centred.
function test_image_editor_model_zoom()
{
	$r = image_editor_model_node(
		'var v = { k: 1, x: 10, y: 20 };' . "\n"
		. 'var z = M.zoomAt(v, 2, 110, 120);' . "\n"
		. 'var k = M.fitZoom(2400, 1600, 1000, 600, 20);' . "\n"
		. 'var c = M.centred(2400, 1600, 1000, 600, k);' . "\n"
		. 'console.log(JSON.stringify({ k: z.k, point: [(110 - z.x) / z.k, (120 - z.y) / z.k],'
		. ' capped: M.zoomAt(v, 1000, 0, 0).k, floor: M.zoomAt(v, 0.0001, 0, 0).k,'
		. ' fit: k, centred: [c.x, c.y] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(2, $r['k'], 'the zoom doubles');
	pg_assert_same(array(100, 100), $r['point'], 'the point under the pointer stays');
	pg_assert_same(32, $r['capped'], 'the zoom stops at 3200 %');
	pg_assert_same(0.02, $r['floor'], 'the zoom stops at 2 %');
	pg_assert_same(0.35, $r['fit'], 'the fit leaves the padding on the tighter side');
	pg_assert_same(array(80, 20), $r['centred'], 'the fitted document is centred');
}

// A caller's list keeps known tools once and makes the mini bar; without one
// the profile decides, and an unknown profile is the photo profile.
function test_image_editor_model_tools_of_a_call()
{
	$r = image_editor_model_node(
		'console.log(JSON.stringify({'
		. ' arrow: M.resolveTools("photo", ["arrow", "bogus", "arrow"]),'
		. ' canvas: M.resolveTools("canvas"),'
		. ' annotate: M.resolveTools("annotate", []),'
		. ' unknown: M.resolveTools("nope", "arrow") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('arrow'), $r['arrow']['list'], 'unknown and repeated names are dropped');
	pg_assert_true($r['arrow']['mini'] === true, 'a caller list draws the mini bar');
	pg_assert_same('artboard', $r['canvas']['list'][1], 'the canvas profile has the artboard tool');
	pg_assert_true($r['canvas']['mini'] === false, 'a profile alone draws the rail');
	pg_assert_true($r['annotate']['mini'] === true, 'the annotate profile draws the mini bar');
	pg_assert_same('photo', $r['unknown']['profile'], 'an unknown profile is the photo profile');
	pg_assert_same('select', $r['unknown']['list'][0], 'a list that is not an array is ignored');
}

// A blank document clamps its size; a picture's document waits for the
// picture's size; the edited size follows the quarter turn.
function test_image_editor_model_documents()
{
	$r = image_editor_model_node(
		'var b = M.newDocument({ blank: { width: 99999, height: 3, background: "transparent" } });' . "\n"
		. 'var p = M.newDocument({ fileId: "42" });' . "\n"
		. 'p.source.width = 400; p.source.height = 300; p.geometry.rot = 90;' . "\n"
		. 'console.log(JSON.stringify({ blank: b.artboard, source: b.source, file: p.source.fileId,'
		. ' size: M.docSize(p), blankSize: M.docSize(b), layers: p.layers.length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('width' => 8000, 'height' => 16, 'background' => 'transparent'), $r['blank'], 'the artboard size is clamped to 16-8000');
	pg_assert_same(null, $r['source'], 'a blank document has no source');
	pg_assert_same(42, $r['file'], 'the file id is a number');
	pg_assert_same(array('w' => 300, 'h' => 400), $r['size'], 'a quarter turn swaps the edited size');
	pg_assert_same(array('w' => 8000, 'h' => 16), $r['blankSize'], 'a blank document is edited at its artboard size');
	pg_assert_same(0, $r['layers'], 'a new document has no layers');
}

// Undo and redo walk the snapshots; one slider drag is one step; the oldest
// step goes when the limit is reached.
function test_image_editor_model_history()
{
	$r = image_editor_model_node(
		'var h = new M.History(3), doc = 0;' . "\n"
		. 'function change(key) { h.push(doc, key); doc++; }' . "\n"
		. 'change(); change("slider"); change("slider"); change("slider");' . "\n"
		. 'var afterDrag = h.past.slice();' . "\n"
		. 'doc = h.undo(doc); var undone = doc;' . "\n"
		. 'doc = h.redo(doc); var redone = doc;' . "\n"
		. 'change(); change(); change(); change();' . "\n"
		. 'console.log(JSON.stringify({ afterDrag: afterDrag, undone: undone, redone: redone,'
		. ' kept: h.past.length, oldest: h.past[0], canRedo: h.canRedo(), empty: new M.History().undo(5) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 1), $r['afterDrag'], 'three moves of one slider make one step');
	pg_assert_same(1, $r['undone'], 'undo goes back over the whole drag');
	pg_assert_same(4, $r['redone'], 'redo comes back to the end of the drag');
	pg_assert_same(3, $r['kept'], 'the history keeps its limit');
	pg_assert_same(5, $r['oldest'], 'the oldest steps go first');
	pg_assert_true($r['canRedo'] === false, 'a new change drops the redo steps');
	pg_assert_same(null, $r['empty'], 'nothing to undo answers null');
}

// A ratio gives the largest box of that shape on the picture, centred
// where the crop was; free keeps the box.
function test_image_editor_model_fit_ratio()
{
	$r = image_editor_model_node(
		'var full = { x: 0, y: 0, w: 400, h: 300 };' . "\n"
		. 'console.log(JSON.stringify({ square: M.fitRatio(full, 1, 400, 300), wide: M.fitRatio(full, 16 / 9, 400, 300),'
		. ' free: M.fitRatio({ x: 5, y: 6, w: 7, h: 8 }, 0, 400, 300), edge: M.fitRatio({ x: 380, y: 0, w: 20, h: 20 }, 1, 400, 300) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('x' => 50, 'y' => 0, 'w' => 300, 'h' => 300), $r['square'], '1:1 is the full height, centred');
	pg_assert_same(array('x' => 0, 'y' => 37.5, 'w' => 400, 'h' => 225), $r['wide'], '16:9 is the full width, centred');
	pg_assert_same(array('x' => 5, 'y' => 6, 'w' => 7, 'h' => 8), $r['free'], 'free keeps the box');
	pg_assert_same(100, $r['edge']['x'], 'a box near the edge is pulled back onto the picture');
}

// Free handles follow the pointer; with a ratio the opposite corner stays,
// an edge keeps the other axis centred, and the box never leaves the picture.
function test_image_editor_model_resize_crop()
{
	$r = image_editor_model_node(
		'console.log(JSON.stringify({'
		. ' free: M.resizeCrop({ x: 0, y: 0, w: 400, h: 300 }, "se", 200, 100, 0, 400, 300),'
		. ' corner: M.resizeCrop({ x: 0, y: 0, w: 200, h: 200 }, "se", 390, 390, 1, 400, 300),'
		. ' edge: M.resizeCrop({ x: 100, y: 100, w: 100, h: 100 }, "e", 350, 0, 1, 400, 300),'
		. ' nw: M.resizeCrop({ x: 100, y: 100, w: 100, h: 100 }, "nw", 90, 0, 1, 400, 300),'
		. ' tiny: M.resizeCrop({ x: 0, y: 0, w: 100, h: 100 }, "e", -50, 0, 0, 400, 300, 16),'
		. ' moved: M.moveCrop({ x: 0, y: 0, w: 100, h: 100 }, -50, 500, 400, 300) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 200, 'h' => 100), $r['free'], 'a free corner follows the pointer');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 300, 'h' => 300), $r['corner'], 'a square stops at the picture\'s height');
	pg_assert_same(array('x' => 100, 'y' => 25, 'w' => 250, 'h' => 250), $r['edge'], 'an edge grows the other axis around its centre');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 200, 'h' => 200), $r['nw'], 'the opposite corner stays');
	pg_assert_same(16, $r['tiny']['w'], 'a box keeps its smallest size');
	pg_assert_same(array('x' => 0, 'y' => 200, 'w' => 100, 'h' => 100), $r['moved'], 'a moved box stays on the picture');
}

// A turn to the right moves the crop and the layers clockwise with the
// picture; an upright layer keeps its size; under a flip the turn is still
// clockwise on screen; turning swaps the output size.
function test_image_editor_model_turns_move_crop_and_layers()
{
	$r = image_editor_model_node(
		'var d = M.newDocument({});' . "\n"
		. 'd.source.width = 400; d.source.height = 300;' . "\n"
		. 'd.geometry.crop = { x: 0, y: 0, w: 200, h: 100 }; d.geometry.output = { w: 100, h: 50 };' . "\n"
		. 'd.layers = [{ type: "rect", x: 0, y: 0, w: 100, h: 50 }, { type: "text", x: 10, y: 10, w: 80, h: 20 }];' . "\n"
		. 'var t = M.turnDocument(d, 1);' . "\n"
		. 'var f = M.newDocument({}); f.source.width = 400; f.source.height = 300;' . "\n"
		. 'f = M.flipDocument(f, "x"); f.layers = [{ type: "rect", x: 0, y: 0, w: 1, h: 1 }];' . "\n"
		. 'var ft = M.turnDocument(f, 1), back = M.turnDocument(M.turnDocument(M.turnDocument(t, 1), 1), 1);' . "\n"
		. 'console.log(JSON.stringify({ rot: t.geometry.rot, crop: t.geometry.crop, rect: t.layers[0], text: t.layers[1], output: t.geometry.output,'
		. ' flippedRot: ft.geometry.rot, flippedRect: ft.layers[0], full: back.layers[0], original: d.layers[0].x }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(90, $r['rot'], 'the picture turns a quarter');
	pg_assert_same(array('x' => 200, 'y' => 0, 'w' => 100, 'h' => 200), $r['crop'], 'the crop box turns with it');
	pg_assert_same(array('type' => 'rect', 'x' => 250, 'y' => 0, 'w' => 50, 'h' => 100), $r['rect'], 'a box turns with the picture');
	pg_assert_same(array('type' => 'text', 'x' => 240, 'y' => 40, 'w' => 80, 'h' => 20), $r['text'], 'a text moves its centre and stays upright');
	pg_assert_same(array('w' => 50, 'h' => 100), $r['output'], 'the output size is swapped');
	pg_assert_same(270, $r['flippedRot'], 'under a flip the picture itself turns the other way');
	pg_assert_same(array('type' => 'rect', 'x' => 299, 'y' => 0, 'w' => 1, 'h' => 1), $r['flippedRect'], 'the top-left corner still goes to the top right');
	pg_assert_same(array('type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 50), $r['full'], 'four turns bring a layer back');
	pg_assert_same(0, $r['original'], 'the document turned is a copy');
}

// The picture's matrix is the identity without turns or straightening, and
// follows the quarter turn; the output size is the crop box rounded.
function test_image_editor_model_picture_matrix_and_output()
{
	$r = image_editor_model_node(
		'var d = M.newDocument({}); d.source.width = 400; d.source.height = 300;' . "\n"
		. 'var id = M.pictureMatrix(d);' . "\n"
		. 'var t = M.turnDocument(d, 1);' . "\n"
		. 'd.geometry.crop = { x: 0, y: 0, w: 200.4, h: 100.6 };' . "\n"
		. 'console.log(JSON.stringify({ identity: id, corner: M.apply(M.pictureMatrix(t), 400, 0), out: M.outputSize(d) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(1, 0, 0, 1, 0, 0), $r['identity'], 'no turn, no straightening: the identity');
	pg_assert_same(array(300, 400), $r['corner'], 'after a turn the top-right corner is at the bottom right');
	pg_assert_same(array('w' => 200, 'h' => 101), $r['out'], 'the output is the crop box in whole pixels');
}

// Layers: the turned box's corners and bounds, hits on the topmost free
// layer, resizing from a handle in the layer's own frame (keeping the ratio
// from a corner), the angle towards the pointer, align, distribute, snap,
// order, new ids; a flip mirrors a turned box, a picture keeps its shape.
function test_image_editor_model_layers()
{
	$r = image_editor_model_node(
		'var box = { id: "a", type: "rect", x: 100, y: 100, w: 200, h: 100, rot: 0 };' . "\n"
		. 'var turned = { id: "b", type: "rect", x: 100, y: 100, w: 200, h: 100, rot: 90 };' . "\n"
		. 'var doc = { layers: [box, turned, { id: "c", type: "image", x: 0, y: 0, w: 10, h: 10, locked: true }] };' . "\n"
		. 'var round = function (v) { return Math.round(v * 1000) / 1000; }, seen = null;' . "\n"
		. 'var d = M.newDocument({}); d.source.width = 400; d.source.height = 300;' . "\n"
		. 'd.layers = [{ id: "r", type: "rect", x: 10, y: 10, w: 50, h: 20, rot: 30 }, { id: "p", type: "image", x: 10, y: 10, w: 50, h: 20, rot: 30 }];' . "\n"
		. 'var f = M.flipDocument(d, "x"), t = M.turnDocument(d, 1);' . "\n"
		. 'console.log(JSON.stringify({'
		. ' bounds: M.bounds([turned]), both: M.bounds([box, turned]),'
		. ' hitTop: M.hitLayer(doc, 200, 140, 0).id, hitTurned: M.hitLayer(doc, 200, 240, 0).id, miss: M.hitLayer(doc, 5, 5, 0),'
		. ' hitOwn: M.hitLayer(doc, 200, 140, 0, function (l, p) { if (l.id === "a") { seen = p; } return l.id !== "b"; }).id, seen: seen,'
		. ' inside: [M.insideBox(box, 200, 150, 10), M.insideBox(box, 105, 150, 10), M.insideBox(box, 200, 195, 10), M.insideBox(turned, 200, 70, 10), M.insideBox(turned, 120, 150, 10)],'
		. ' se: M.resizeBox(box, "se", 400, 300, false, 4), keep: M.resizeBox(box, "se", 400, 150, true, 4), west: M.resizeBox(box, "w", 50, 0, false, 4),'
		. ' small: M.resizeBox(box, "e", 0, 0, false, 4), turnedEast: M.resizeBox(turned, "e", 200, 400, false, 4),'
		. ' up: M.angleTo(box, 200, 0, false), right: M.angleTo(box, 400, 150, false), snapped: M.angleTo(box, 400, 140, true),'
		. ' right2: M.align([{ x: 0, y: 0, w: 10, h: 10 }, { x: 50, y: 20, w: 20, h: 5 }], "right", { x: 0, y: 0, w: 100, h: 100 }),'
		. ' spread: M.distribute([{ x: 0, y: 0, w: 10, h: 10 }, { x: 30, y: 0, w: 10, h: 10 }, { x: 90, y: 0, w: 10, h: 10 }], "x"),'
		. ' snap: M.snapBox({ x: 47, y: 10, w: 10, h: 10 }, [0, 50, 100], [0, 50, 100], 4),'
		. ' order: ["up", "down", "top", "bottom"].map(function (h) { return M.reorder([{ id: "a" }, { id: "b" }, { id: "c" }, { id: "d" }], ["b"], h).map(function (l) { return l.id; }).join(""); }),'
		. ' id: M.newId({ layers: [{ id: "l1" }, { id: "l0" }] }),'
		. ' flippedRect: f.layers[0].rot, flippedPicture: f.layers[1].rot, turnedPicture: [round(t.layers[1].x), round(t.layers[1].y), t.layers[1].w, t.layers[1].h, t.layers[1].rot]'
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('x' => 150, 'y' => 50, 'w' => 100, 'h' => 200), $r['bounds'], 'a box turned a quarter stands up');
	pg_assert_same(array('x' => 100, 'y' => 50, 'w' => 200, 'h' => 200), $r['both'], 'the box around two');
	pg_assert_same('b', $r['hitTop'], 'the topmost layer is hit');
	pg_assert_same('b', $r['hitTurned'], 'a turned box is hit where it is drawn');
	pg_assert_same(null, $r['miss'], 'a locked layer is not hit');
	pg_assert_same('a', $r['hitOwn'], 'a type\'s own test lets a press through to the layer below');
	pg_assert_same(array(100, 40), $r['seen'], 'the test gets the point in the layer\'s own frame');
	pg_assert_same(array(true, false, false, true, false), $r['inside'], 'inside a box by the margin, in its own turned frame');
	pg_assert_same(array('x' => 100, 'y' => 100, 'w' => 300, 'h' => 200), $r['se'], 'the opposite corner stays');
	pg_assert_same(array('x' => 100, 'y' => 100, 'w' => 300, 'h' => 150), $r['keep'], 'from a corner the ratio can be kept');
	pg_assert_same(array('x' => 50, 'y' => 100, 'w' => 250, 'h' => 100), $r['west'], 'the west edge moves alone');
	pg_assert_same(4, $r['small']['w'], 'no side under the minimum');
	pg_assert_same(array('x' => 25, 'y' => 175, 'w' => 350, 'h' => 100), $r['turnedEast'], 'a turned box grows along its own axis');
	pg_assert_same(array(0, 90, 90), array($r['up'], $r['right'], $r['snapped']), 'the angle to the pointer, in steps of 15 with snap');
	pg_assert_same(array(array(90, 0), array(30, 0)), $r['right2'], 'boxes line up on the right');
	pg_assert_same(array(array(0, 0), array(15, 0), array(0, 0)), $r['spread'], 'the middle box moves to even gaps');
	pg_assert_same(array('dx' => -2, 'dy' => 0, 'gx' => array(50), 'gy' => array()), $r['snap'], 'the nearest line within reach wins and shows');
	pg_assert_same(array('acbd', 'bacd', 'acdb', 'bacd'), $r['order'], 'up, down, to the top, to the bottom');
	pg_assert_same('l2', $r['id'], 'a new id is not taken');
	pg_assert_same(-30, $r['flippedRect'], 'a mirror turns a box\'s angle the other way');
	pg_assert_same(30, $r['flippedPicture'], 'a picture keeps its angle');
	pg_assert_same(array(255, 25, 50, 20, 30), $r['turnedPicture'], 'a picture follows a turn with its middle, its shape kept');
}

// Enter and Space go to the focused control that uses them (a button, a
// link, a checkbox's Space, a text field); on the stage, a slider or the
// dialog itself they stay the editor's shortcuts (apply the crop, pan).
function test_image_editor_model_owns_key()
{
	$r = image_editor_model_node(
		'var cases = {' . "\n"
		. ' buttonEnter: M.ownsKey({ tag: "BUTTON" }, "Enter"), buttonSpace: M.ownsKey({ tag: "BUTTON" }, " "),' . "\n"
		. ' link: M.ownsKey({ tag: "A" }, "Enter"), roleButton: M.ownsKey({ tag: "DIV", role: "button" }, " "),' . "\n"
		. ' textEnter: M.ownsKey({ tag: "INPUT", type: "number" }, "Enter"), textarea: M.ownsKey({ tag: "TEXTAREA" }, " "),' . "\n"
		. ' editable: M.ownsKey({ tag: "DIV", editable: true }, " "), select: M.ownsKey({ tag: "SELECT" }, "Enter"),' . "\n"
		. ' checkSpace: M.ownsKey({ tag: "INPUT", type: "checkbox" }, " "), checkEnter: M.ownsKey({ tag: "INPUT", type: "checkbox" }, "Enter"),' . "\n"
		. ' range: M.ownsKey({ tag: "INPUT", type: "range" }, " "), rangeEnter: M.ownsKey({ tag: "INPUT", type: "range" }, "Enter"),' . "\n"
		. ' stage: M.ownsKey({ tag: "DIV" }, " "), dialog: M.ownsKey({ tag: "DIALOG" }, "Enter"), none: M.ownsKey(null, "Enter"),' . "\n"
		. ' otherKey: M.ownsKey({ tag: "BUTTON" }, "+")' . "\n"
		. '};' . "\n"
		. 'console.log(JSON.stringify(cases));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach (array('buttonEnter', 'buttonSpace', 'link', 'roleButton', 'textEnter', 'textarea', 'editable', 'select', 'checkSpace') as $k) {
		pg_assert_true($r[$k], $k . ': the focused control takes the key');
	}

	foreach (array('checkEnter', 'range', 'rangeEnter', 'stage', 'dialog', 'none', 'otherKey') as $k) {
		pg_assert_false($r[$k], $k . ': the key stays the editor\'s shortcut');
	}
}

// A document is unchanged when it holds the same data, whatever order its
// keys were written in; a crop, a turn or a layer changes it.
function test_image_editor_model_same_document()
{
	$r = image_editor_model_node(
		'var a = M.newDocument({ fileId: 7 }); a.source.width = 400; a.source.height = 300;' . "\n"
		. 'var b = M.clone(a);' . "\n"
		. 'var reordered = JSON.parse(JSON.stringify(a)); var g = reordered.geometry; delete reordered.geometry; reordered.geometry = g;' . "\n"
		. 'var cropped = M.clone(a); cropped.geometry.crop = { x: 0, y: 0, w: 300, h: 300 };' . "\n"
		. 'var turned = M.turnDocument(a, 1), back = M.turnDocument(M.turnDocument(M.turnDocument(turned, 1), 1), 1);' . "\n"
		. 'var layered = M.clone(a); layered.layers.push({ id: "l1", type: "rect" });' . "\n"
		. 'console.log(JSON.stringify({ same: M.sameDocument(a, b), reordered: M.sameDocument(a, reordered),'
		. ' cropped: M.sameDocument(a, cropped), turned: M.sameDocument(a, turned), back: M.sameDocument(a, back),'
		. ' layered: M.sameDocument(a, layered), none: M.sameDocument(a, null) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['same'], 'a copy is the same document');
	pg_assert_true($r['reordered'], 'the order of the keys does not matter');
	pg_assert_true($r['back'], 'four quarter turns come back to the opening document');
	pg_assert_false($r['cropped'], 'a crop changes the document');
	pg_assert_false($r['turned'], 'a turn changes the document');
	pg_assert_false($r['layered'], 'a layer changes the document');
	pg_assert_false($r['none'], 'no document is not the same document');
}

// Fitting a picture leaves out the part of the stage an open panel sheet
// covers on the right, so every edge and handle can be reached.
function test_image_editor_model_fit_view_beside_panel()
{
	$r = image_editor_model_node(
		'console.log(JSON.stringify({ open: M.fitView(1200, 900, 684, 316, 304, 12),'
		. ' none: M.fitView(1200, 900, 684, 316, 0, 12), small: M.fitView(100, 50, 1000, 800, 300, 32),'
		. ' covered: M.fitView(400, 300, 300, 300, 400, 12) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	$open = $r['open'];
	pg_assert_true(abs($open['k'] - 356 / 1200) < 1e-6, 'the picture fits the width left beside the panel');
	pg_assert_true($open['x'] + 1200 * $open['k'] <= 684 - 304 + 1e-6, 'the right edge stays left of the panel');
	pg_assert_true($open['x'] >= 0, 'the left edge stays on the stage');
	pg_assert_true(abs($open['x'] - (380 - 1200 * $open['k']) / 2) < 1e-6, 'the picture is centred in the uncovered part');
	pg_assert_true(abs($r['none']['x'] - (684 - 1200 * $r['none']['k']) / 2) < 1e-6, 'without a panel the whole stage is used');
	pg_assert_same(1, $r['small']['k'], 'a small picture is never zoomed past its size');
	pg_assert_true($r['covered']['k'] > 0 && $r['covered']['x'] >= 0, 'a panel wider than the stage still leaves a picture on it');
}
