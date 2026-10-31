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
