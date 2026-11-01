<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's stickers and frames: the model's sticker set,
 * outlines, label boxes and label sizes, the frame's outline at a size (run
 * under Node), the labels, and the listing of pictures from Files the sticker
 * panel offers.
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
function image_editor_sticker_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiesticker');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The eight built-in stickers in the panel's order, each with its colours
// and a size; the labelled ones name their label's string key.
function test_image_editor_sticker_set()
{
	$r = image_editor_sticker_node(
		'console.log(JSON.stringify({ kinds: M.STICKERS.map(function (s) { return s.kind; }),'
		. ' labels: M.STICKERS.map(function (s) { return s.label; }),'
		. ' sized: M.STICKERS.every(function (s) { return s.w > 0 && s.h > 0 && /^#[0-9a-f]{6}$/.test(s.fill) && /^#[0-9a-f]{6}$/.test(s.ink); }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('burst', 'pill', 'tag', 'stamp', 'bubble', 'check', 'star', 'heart'), $r['kinds'], 'the sticker set');
	pg_assert_same(array('%20', 'stickerNew', 'stickerSale', 'stickerSold', 'stickerHello', '', '', ''), $r['labels'], 'the labels');
	pg_assert_true($r['sized'] === true, 'every sticker has a size and two colours');

	$strings = pg_image_editor_strings_sticker();

	foreach (array('stickerNew', 'stickerSale', 'stickerSold', 'stickerHello') as $key) {
		pg_assert_true(isset($strings[$key]), 'label ' . $key . ' is in the sticker part');
	}
}

// A sticker's outline lies in its box, and a labelled kind has a label box
// inside it; a check, a star and a heart carry no label.
function test_image_editor_sticker_shapes_and_label_boxes()
{
	$r = image_editor_sticker_node(
		'var out = {};' . "\n"
		. 'M.STICKERS.forEach(function (s) {' . "\n"
		. '  var l = { type: "sticker", kind: s.kind, x: 100, y: 50, w: 240, h: 120 }, g = M.stickerShape(l), xs = [], ys = [];' . "\n"
		. '  (g.d.match(/[MLHVCAZ][^MLHVCAZ]*/g) || []).forEach(function (c) {' . "\n"
		. '    var n = (c.slice(1).match(/-?\d+(\.\d+)?/g) || []).map(Number), i;' . "\n"
		. '    if (c[0] === "H") { xs.push(n[0]); } else if (c[0] === "V") { ys.push(n[0]); } else if (c[0] === "A") { xs.push(n[5]); ys.push(n[6]); }' . "\n"
		. '    else { for (i = 0; i + 1 < n.length; i += 2) { xs.push(n[i]); ys.push(n[i + 1]); } }' . "\n"
		. '  });' . "\n"
		. '  out[s.kind] = { inside: Math.min.apply(null, xs) >= 99 && Math.max.apply(null, xs) <= 341 && Math.min.apply(null, ys) >= 49 && Math.max.apply(null, ys) <= 171,'
		. '    label: g.lb ? (g.lb.x >= 100 && g.lb.y >= 50 && g.lb.x + g.lb.w <= 340.01 && g.lb.y + g.lb.h <= 170.01) : null };' . "\n"
		. '});' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach (array('burst', 'pill', 'tag', 'stamp', 'bubble') as $kind) {
		pg_assert_true($r[$kind]['inside'] === true, $kind . ' stays in its box');
		pg_assert_true($r[$kind]['label'] === true, $kind . ' has a label box inside it');
	}

	foreach (array('check', 'star', 'heart') as $kind) {
		pg_assert_true($r[$kind]['inside'] === true, $kind . ' stays in its box');
		pg_assert_same(null, $r[$kind]['label'], $kind . ' has no label');
	}
}

// A label's size: the box's height bounds a short label, its width a long
// one, so the text never leaves the sticker.
function test_image_editor_sticker_label_size()
{
	$r = image_editor_sticker_node(
		'var lb = { x: 0, y: 0, w: 200, h: 50 };' . "\n"
		. 'var short = M.stickerFont("pill", lb, 0.5, 2), long = M.stickerFont("pill", lb, 0.5, 40);' . "\n"
		. 'console.log(JSON.stringify({ short: short.size, long: long.size, longFits: long.size * 0.5 * 40 <= 200 * 0.84 + 1e-9,'
		. ' bubble: M.stickerFont("bubble", lb, 0.6, 6).weight, empty: M.stickerFont("pill", lb, 0.5, 0).size > 0 }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(28, (int) round($r['short']), 'a short label takes 56 % of the height');
	pg_assert_true($r['long'] < $r['short'], 'a long label is smaller');
	pg_assert_true($r['longFits'] === true, 'a long label fits the width');
	pg_assert_same(700, $r['bubble'], 'a speech bubble writes bold');
	pg_assert_true($r['empty'] === true, 'an empty label still has a size');
}

// A frame's outline at a size: an even-odd ring inside the picture, wider
// at a polaroid's foot; none draws nothing; corners draw four marks.
function test_image_editor_sticker_frames()
{
	$r = image_editor_sticker_node(
		'var box = { x: 0, y: 0, w: 1000, h: 500 };' . "\n"
		. 'var f = function (kind, width) { return M.frameShape({ kind: kind, color: "#ffffff", width: width }, box); };' . "\n"
		. 'console.log(JSON.stringify({ kinds: M.FRAMES, none: f("none", 4), nil: M.frameShape(null, box),'
		. ' line: f("line", 4).band, thick: f("thick", 4).band, wider: f("thick", 8).band,'
		. ' polaroid: f("polaroid", 4).pads, corners: (f("corners", 4).d.match(/M/g) || []).length,'
		. ' ring: /M0 0H1000V500H0Z/.test(f("line", 4).d) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('none', 'line', 'thick', 'polaroid', 'corners'), $r['kinds'], 'the frames');
	pg_assert_same(null, $r['none'], 'none draws nothing');
	pg_assert_same(null, $r['nil'], 'no frame draws nothing');
	pg_assert_true(($r['line'] > 0) && ($r['line'] < $r['thick']), 'a thin line is thinner than a thick edge');
	pg_assert_true($r['wider'] > $r['thick'], 'the width slider widens the edge');
	pg_assert_true($r['polaroid']['b'] > 3 * $r['polaroid']['t'], 'a polaroid is wider at its foot');
	pg_assert_same($r['polaroid']['l'], $r['polaroid']['t'], 'a polaroid has even sides');
	pg_assert_same(4, $r['corners'], 'corners draw four marks');
	pg_assert_true($r['ring'] === true, 'a frame is drawn on the picture\'s own edge');
}

// The pictures the sticker panel lists from Files: images only, the rows a
// role-3 user's folders allow, an address and a size each.
function test_image_editor_sticker_files_filter()
{
	$rows = array(
		array('id' => 1, 'name' => 'a.jpg', 'type' => 'jpg', 'folder' => 2, 'image_width' => 40, 'image_height' => 30),
		array('id' => 2, 'name' => 'b.pdf', 'type' => 'pdf', 'folder' => 2, 'image_width' => 0, 'image_height' => 0),
		array('id' => 3, 'name' => 'c.png', 'type' => 'png', 'folder' => 9, 'image_width' => 0, 'image_height' => 0),
	);

	$all = pg_image_editor_sticker_rows($rows, null);
	pg_assert_same(array(1, 3), array_map(function ($f) { return $f['id']; }, $all), 'images only, every folder for an editor above role 3');
	pg_assert_same(40, $all[0]['width'], 'the width');
	pg_assert_contains('a.jpg', $all[0]['url'], 'the address');

	$basic = pg_image_editor_sticker_rows($rows, array(2));
	pg_assert_same(array(1), array_map(function ($f) { return $f['id']; }, $basic), 'a role-3 user sees only the folders they may reach');
}
