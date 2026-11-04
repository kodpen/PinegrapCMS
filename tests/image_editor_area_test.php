<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's area selection (the SELECTION section of
 * assets/js/pg_image_editor_model.js, run under Node): the shapes as rings,
 * adding and subtracting them, the magic wand's flood fill and the outline
 * traced from its pixels, inverting, the redaction area made of a selection,
 * and the part's place in the bundle, the profiles and the strings.
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
function image_editor_area_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiearea');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var area = function (rings) { return Math.round(M.ringsArea(rings)); };' . "\n"
		// A w x h RGBA picture, every pixel from fn(x, y) as [r, g, b].
		. 'var pic = function (w, h, fn) { var d = new Uint8ClampedArray(w * h * 4), x, y, c; for (y = 0; y < h; y++) { for (x = 0; x < w; x++) { c = fn(x, y); d.set([c[0], c[1], c[2], 255], (y * w + x) * 4); } } return d; };' . "\n"
		. 'var count = function (mask) { var n = 0; mask.forEach(function (v) { n += v ? 1 : 0; }); return n; };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A drag counts by how far its path went from the press, not where it was
// let go: a lasso closed on its first point is a shape, not a click.
function test_image_editor_area_dragged()
{
	$r = image_editor_area_node(
		'var loop = [[10, 10], [60, 10], [60, 60], [10, 60], [10, 10]];' . "\n"
		. 'console.log(JSON.stringify({ loop: M.areaDragged([10, 10], loop, 3), click: M.areaDragged([10, 10], [[10, 10], [11, 11], [10, 10]], 3), none: M.areaDragged([10, 10], [], 3) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(true, $r['loop'], 'a loop back to the press is a drag');
	pg_assert_same(false, $r['click'], 'a pointer that stayed within the slack is a click');
	pg_assert_same(false, $r['none'], 'no points: a click');
}

// A rectangle and an ellipse drawn between two corners, either way round.
function test_image_editor_area_shapes()
{
	$r = image_editor_area_node(
		'console.log(JSON.stringify({'
		. ' rect: area([M.areaRect([10, 20], [110, 70])]), back: area([M.areaRect([110, 70], [10, 20])]),'
		. ' points: M.areaRect([0, 0], [5, 5]).length,'
		. ' ellipse: area([M.areaEllipse([0, 0], [200, 100])]), flat: M.areaRect([0, 0], [0, 40])'
		. ' }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(5000, $r['rect'], 'a 100 x 50 rectangle');
	pg_assert_same(5000, $r['back'], 'drawn from the other corner');
	pg_assert_same(4, $r['points'], 'four corners');
	pg_assert_true(abs($r['ellipse'] - M_PI * 100 * 50) < M_PI * 100 * 50 * 0.005, 'the ellipse within half a percent of pi a b');
	pg_assert_same(null, $r['flat'], 'no width: no shape');
}

// A lasso keeps its turns, drops repeated points, and is nothing below
// three points or without area.
function test_image_editor_area_lasso()
{
	$r = image_editor_area_node(
		'var pts = [[0, 0], [0, 0], [50, 0], [50, 0.1], [100, 0], [100, 100], [0, 100]];' . "\n"
		. 'var ring = M.areaLasso(pts, 1);' . "\n"
		. 'console.log(JSON.stringify({ area: area([ring]), points: ring.length, two: M.areaLasso([[0, 0], [10, 10]], 1), line: M.areaLasso([[0, 0], [5, 5], [10, 10]], 1) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(10000, $r['area'], 'the square the lasso went round');
	pg_assert_true($r['points'] <= 5, 'repeated points dropped');
	pg_assert_same(null, $r['two'], 'two points are no area');
	pg_assert_same(null, $r['line'], 'points on a line are no area');
}

// Shift adds, Alt subtracts: the selection's rings fold the shapes in order;
// invert takes the rest of the picture.
function test_image_editor_area_combine()
{
	$r = image_editor_area_node(
		'var a = { op: "add", kind: "rect", ring: M.areaRect([0, 0], [100, 100]) };' . "\n"
		. 'var b = { op: "add", kind: "rect", ring: M.areaRect([50, 50], [150, 150]) };' . "\n"
		. 'var c = { op: "sub", kind: "rect", ring: M.areaRect([25, 25], [75, 75]) };' . "\n"
		. 'var frame = { x: 0, y: 0, w: 200, h: 200 };' . "\n"
		. 'console.log(JSON.stringify({'
		. ' one: area(M.selectionRings({ ops: [a] }, [], frame)),'
		. ' add: area(M.selectionRings({ ops: [a, b] }, [], frame)),'
		. ' sub: area(M.selectionRings({ ops: [a, b, c] }, [], frame)),'
		. ' hole: M.selectionRings({ ops: [a, c] }, [], frame).length,'
		. ' first: area(M.selectionRings({ ops: [{ op: "add", kind: "rect", ring: c.ring }] }, [], frame)),'
		. ' subFirst: M.selectionRings({ ops: [{ op: "sub", kind: "rect", ring: c.ring }] }, [], frame).length,'
		. ' invert: area(M.selectionRings({ ops: [a], invert: true }, [], frame)),'
		. ' none: M.selectionRings(null, [], frame).length,'
		. ' noneInverted: area(M.selectionRings({ ops: [], invert: true }, [], frame))'
		. ' }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(10000, $r['one'], 'one square');
	pg_assert_same(17500, $r['add'], 'two squares added');
	pg_assert_same(15000, $r['sub'], 'a square taken out of the corner of one');
	pg_assert_same(2, $r['hole'], 'a square taken out of the middle leaves a hole');
	pg_assert_same(2500, $r['first'], 'the first shape is the selection');
	pg_assert_same(0, $r['subFirst'], 'subtracting from nothing leaves nothing');
	pg_assert_same(30000, $r['invert'], 'inverted: the rest of the picture');
	pg_assert_same(0, $r['none'], 'no selection');
	pg_assert_same(40000, $r['noneInverted'], 'nothing inverted is the whole picture');
}

// The wand takes the pixels like the seed's within the tolerance: only
// those it reaches without crossing others when contiguous, all of them
// when not.
function test_image_editor_area_wand()
{
	$r = image_editor_area_node(
		// Red on the left half, blue on the right, with a red 2 x 2 island
		// at 7, 4 in the blue and a slightly lighter red row at y 0.
		'var w = 10, h = 10, data = pic(w, h, function (x, y) {' . "\n"
		. '  if ((x >= 7) && (x <= 8) && (y >= 4) && (y <= 5)) { return [200, 0, 0]; }' . "\n"
		. '  if (x < 5) { return (y === 0) ? [230, 0, 0] : [200, 0, 0]; }' . "\n"
		. '  return [0, 0, 200];' . "\n"
		. '});' . "\n"
		. 'console.log(JSON.stringify({'
		. ' near: count(M.wandMask(data, w, h, 1, 5, 5, true)),'
		. ' wider: count(M.wandMask(data, w, h, 1, 5, 20, true)),'
		. ' global: count(M.wandMask(data, w, h, 1, 5, 20, false)),'
		. ' blue: count(M.wandMask(data, w, h, 9, 9, 0, true)),'
		. ' outside: count(M.wandMask(data, w, h, -1, 3, 50, true))'
		. ' }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(45, $r['near'], 'a tight tolerance leaves out the lighter row');
	pg_assert_same(50, $r['wider'], 'a wider one takes it');
	pg_assert_same(54, $r['global'], 'not contiguous: the island too');
	pg_assert_same(46, $r['blue'], 'the blue around the island');
	pg_assert_same(0, $r['outside'], 'a seed off the picture takes nothing');
}

// The wand's pixels as an outline: a block, a block with a hole, two pixels
// touching at a corner, each with the area of its pixels.
function test_image_editor_area_mask_rings()
{
	$r = image_editor_area_node(
		'var grid = function (rows) { var h = rows.length, w = rows[0].length, m = new Uint8Array(w * h);' . "\n"
		. '  rows.forEach(function (row, y) { row.split("").forEach(function (c, x) { m[y * w + x] = (c === "#") ? 1 : 0; }); });' . "\n"
		. '  return { m: m, w: w, h: h }; };' . "\n"
		. 'var rings = function (g) { return M.maskRings(g.m, g.w, g.h); };' . "\n"
		. 'var block = grid(["....", ".##.", ".##.", "...."]), ring = grid(["###", "#.#", "###"]), diag = grid(["#.", ".#"]);' . "\n"
		. 'console.log(JSON.stringify({'
		. ' block: area(rings(block)), blockRings: rings(block).length, blockPoints: rings(block)[0].length,'
		. ' ring: area(rings(ring)), ringRings: rings(ring).length,'
		. ' diag: area(rings(diag)),'
		. ' empty: rings(grid(["..", ".."])).length,'
		. ' inside: [M.insideRings([2, 2], rings(block)), M.insideRings([1.5, 1.5], rings(ring)), M.insideRings([0.5, 0.5], rings(ring))]'
		. ' }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(4, $r['block'], 'a 2 x 2 block');
	pg_assert_same(1, $r['blockRings'], 'one outline');
	pg_assert_same(4, $r['blockPoints'], 'its straight runs as single edges');
	pg_assert_same(8, $r['ring'], 'a ring of eight pixels: the hole taken off');
	pg_assert_same(2, $r['ringRings'], 'an outline and a hole');
	pg_assert_same(2, $r['diag'], 'two pixels touching at a corner');
	pg_assert_same(0, $r['empty'], 'no pixels: no outline');
	pg_assert_same(array(true, false, true), $r['inside'], 'inside the block, in the hole, on the ring');
}

// A wand seed in the selection: its rings come from the picture (wands[i])
// and fold in with the shapes; a wand not worked out yet counts as nothing.
function test_image_editor_area_wand_in_selection()
{
	$r = image_editor_area_node(
		'var frame = { x: 0, y: 0, w: 100, h: 100 }, wand = [M.areaRect([0, 0], [50, 100])];' . "\n"
		. 'var sel = { ops: [{ op: "add", kind: "wand", at: [10, 10], tolerance: 20, contiguous: true }, { op: "sub", kind: "rect", ring: M.areaRect([0, 0], [50, 50]) }] };' . "\n"
		. 'console.log(JSON.stringify({ with: area(M.selectionRings(sel, [wand, null], frame)), without: area(M.selectionRings(sel, [], frame)) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(2500, $r['with'], 'the wand\'s half less the top quarter');
	pg_assert_same(0, $r['without'], 'a wand not yet worked out');
}

// A redaction area made of a selection: its box, the rings in the box's
// parts, and the same rings back in the turned space, also when the area is
// moved, resized or turned.
function test_image_editor_area_redaction_layer()
{
	$r = image_editor_area_node(
		'var rings = M.clipRings([M.areaRect([10, 10], [110, 60])], [M.areaRect([40, 20], [60, 40])], "difference");' . "\n"
		. 'var l = M.areaLayer(rings), back = M.areaPoints(l);' . "\n"
		. 'var moved = Object.assign({}, l, { x: l.x + 5, w: l.w * 2 }), turned = Object.assign({}, l, { rot: 90 });' . "\n"
		. 'console.log(JSON.stringify({ box: [l.x, l.y, l.w, l.h, l.rot], shape: l.shape, parts: l.area.length, back: area(back),'
		. ' corner: [].concat.apply([], back).sort(function (a, b) { return a[0] - b[0] || a[1] - b[1]; })[0].map(Math.round), moved: area(M.areaPoints(moved)), turned: area(M.areaPoints(turned)),'
		. ' none: M.areaLayer([]) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(10, 10, 100, 50, 0), $r['box'], 'the selection\'s box');
	pg_assert_same('area', $r['shape'], 'a redaction of the area shape');
	pg_assert_same(2, $r['parts'], 'the outline and the hole');
	pg_assert_same(4600, $r['back'], 'the same area back in the turned space');
	pg_assert_same(array(10, 10), $r['corner'], 'at the same place');
	pg_assert_same(9200, $r['moved'], 'a wider box stretches it');
	pg_assert_same(4600, $r['turned'], 'turned, the same area');
	pg_assert_same(null, $r['none'], 'no selection: no area');
}

// The part is bundled after the redaction it uses, sits in the photo
// profile after Redact, and has its strings.
function test_image_editor_area_part()
{
	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];

	pg_assert_true(array_search('assets/js/pg_image_editor_area.js', $files, true) > array_search('assets/js/pg_image_editor_redact.js', $files, true), 'after the redaction part');
	pg_assert_true(in_array('area', pg_image_editor_parts(), true), 'a part with its strings');

	$strings = pg_image_editor_strings_area();
	pg_assert_true(isset($strings['hintArea'], $strings['areaRedact'], $strings['areaInvert'], $strings['areaTolerance']), 'the panel\'s labels');

	$core = pg_image_editor_strings_core();
	pg_assert_true(isset($core['tool_area']), 'the rail\'s label');

	$model = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js');
	pg_assert_contains("'redact', 'area', 'sticker'", $model, 'in the photo profile after Redact');
	pg_assert_contains("'redact', 'area', 'sticker', 'frame', 'vector'", $model, 'a tool a caller may name');
}

// Keep only erases what lies in the frame outside the selection: a frame
// less the selection, nothing for a selection that fills the frame.
function test_image_editor_area_rest()
{
	$r = image_editor_area_node(
		'var frame = { x: 0, y: 0, w: 100, h: 80 };' . "\n"
		. 'var disc = M.selectionRings({ ops: [{ op: "add", kind: "ellipse", ring: M.areaEllipse([20, 20], [60, 60]) }] }, [], frame);' . "\n"
		. 'var all = M.selectionRings({ ops: [{ op: "add", kind: "rect", ring: M.areaRect([0, 0], [100, 80]) }] }, [], frame);' . "\n"
		. 'console.log(JSON.stringify({ disc: area(disc), rest: area(M.areaRest(disc, frame)), none: area(M.areaRest(all, frame)), empty: area(M.areaRest([], frame)) }));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not installed; skipped');
		return;
	}

	pg_assert_true(!isset($r['error']), 'the script runs: ' . (isset($r['error']) ? $r['error'] : ''));
	pg_assert_same(8000 - $r['disc'], $r['rest'], 'the frame less the disc');
	pg_assert_same(0, $r['none'], 'nothing outside a selection of the whole frame');
	pg_assert_same(8000, $r['empty'], 'everything outside no selection');
}
