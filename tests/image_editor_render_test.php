<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's animation frames and GIF: the encoder
 * (pg_image_editor_encode.js, run under Node) read back by GD, its palette,
 * delays, loop count, LZW and changed rectangles.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the encoder as E and the model as M and answers its
// JSON; null without Node.
function image_editor_render_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgierender');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var E = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_encode.js') . ');' . "\n"
		. 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'function frame(w, h, n, alpha) { var a = new Uint8ClampedArray(w * h * 4); for (var y = 0; y < h; y++) { for (var x = 0; x < w; x++) { var i = 4 * (y * w + x); a[i] = (x * 8 + n * 40) & 255; a[i + 1] = (y * 8) & 255; a[i + 2] = (n * 60) & 255; a[i + 3] = (alpha && (x < 4)) ? 0 : 255; } } return a; }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A GIF of $frames frames at w x h from the encoder, as bytes.
function image_editor_render_gif($w, $h, $frames, $options, $alpha = false)
{
	$r = image_editor_render_node(
		'var enc = E.create(Object.assign({ width: ' . (int) $w . ', height: ' . (int) $h . ' }, ' . json_encode($options) . '));' . "\n"
		. 'for (var n = 0; n < ' . (int) $frames . '; n++) { enc.sample(frame(' . (int) $w . ', ' . (int) $h . ', n, ' . ($alpha ? 'true' : 'false') . ')); }' . "\n"
		. 'for (var n = 0; n < ' . (int) $frames . '; n++) { enc.frame(frame(' . (int) $w . ', ' . (int) $h . ', n, ' . ($alpha ? 'true' : 'false') . ')); }' . "\n"
		. 'console.log(JSON.stringify({ gif: Buffer.from(enc.end()).toString("base64") }));'
	);

	if ($r === null) {
		return null;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	return base64_decode($r['gif']);
}

// The file is a GIF89a GD opens at its size, animated, with its colours.
function test_image_editor_render_gif_reads_back()
{
	$gif = image_editor_render_gif(40, 24, 4, array('fps' => 10, 'loop' => 0, 'dither' => false));

	if ($gif === null) {
		return;
	}

	pg_assert_same('GIF89a', substr($gif, 0, 6), 'signature');
	pg_assert_same(';', substr($gif, -1), 'trailer');
	pg_assert_contains('NETSCAPE2.0', $gif, 'loops forever');

	$file = tempnam(sys_get_temp_dir(), 'pgiegif');
	file_put_contents($file, $gif);

	$size = getimagesize($file);
	pg_assert_same(array(40, 24), array($size[0], $size[1]), 'size');
	pg_assert_true(pg_image_is_animated_gif($file), 'animated');

	// The first frame's pixel (x 10, y 5): red 80, green 40, blue 0, within
	// what 255 colours for about 3,800 allow.
	$image = imagecreatefromstring($gif);
	$colour = imagecolorsforindex($image, imagecolorat($image, 10, 5));
	pg_assert_true(abs($colour['red'] - 80) <= 24 && abs($colour['green'] - 40) <= 24 && $colour['blue'] <= 24, 'first frame colour');

	@unlink($file);
}

// Dithered and see-through frames still decode; one play leaves out the
// loop block.
function test_image_editor_render_gif_options()
{
	$gif = image_editor_render_gif(33, 17, 3, array('fps' => 25, 'loop' => 1, 'dither' => true), true);

	if ($gif === null) {
		return;
	}

	pg_assert_false(strpos($gif, 'NETSCAPE2.0') !== false, 'plays once: no loop block');

	$image = imagecreatefromstring($gif);
	pg_assert_true($image !== false, 'decodes');
	pg_assert_same(0, imagecolortransparent($image), 'transparent index 0');
	pg_assert_same(0, imagecolorat($image, 1, 1), 'see-through pixel');
}

// Delays carry their rounding, never under 2; the loop count is the
// repeats after the first play.
function test_image_editor_render_delays_and_loops()
{
	$r = image_editor_render_node(
		'var d15 = [], d30 = [];' . "\n"
		. 'for (var i = 0; i < 15; i++) { d15.push(E.delayAt(i, 15)); }' . "\n"
		. 'for (var i = 0; i < 4; i++) { d30.push(E.delayAt(i, 60)); }' . "\n"
		. 'console.log(JSON.stringify({ d15: d15, sum15: d15.reduce(function (a, b) { return a + b; }, 0), d60: d30, loops: [E.loopCount(0), E.loopCount(1), E.loopCount(3), E.loopCount(-2)] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(100, $r['sum15'], '15 frames at 15 fps last one second');
	pg_assert_true(min($r['d15']) >= 6 && max($r['d15']) <= 7, 'delays of 6 or 7 hundredths');
	pg_assert_same(array(2, 2, 2, 2), $r['d60'], 'never under 2');
	pg_assert_same(array(0, null, 2, 0), $r['loops'], 'loop counts');
}

// Median cut keeps distinct colours apart; the changed rectangle covers
// only what differs; LZW of a run is short.
function test_image_editor_render_palette_rect_lzw()
{
	$r = image_editor_render_node(
		'var a = new Uint8ClampedArray([255, 0, 0, 255, 0, 0, 255, 255, 0, 255, 0, 255, 255, 255, 255, 255]);' . "\n"
		. 'var p = E.palette([a], 255, false), f = E.nearest(p);' . "\n"
		. 'var prev = new Uint8Array(30), cur = new Uint8Array(30); cur[2 * 6 + 3] = 1; cur[3 * 6 + 4] = 1;' . "\n"
		. 'var run = new Uint8Array(1000);' . "\n"
		. 'console.log(JSON.stringify({ count: p.count, red: Array.from(p.colors.slice(3 * f(250, 5, 5), 3 * f(250, 5, 5) + 3)), rect: E.changed(prev, cur, 6, 5), same: E.changed(prev, prev, 6, 5), lzw: E.lzw(run, 2).length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(4, $r['count'], 'four colours');
	pg_assert_same(array(255, 0, 0), $r['red'], 'red stays red');
	pg_assert_same(array('x' => 3, 'y' => 2, 'w' => 2, 'h' => 2), $r['rect'], 'changed rectangle');
	pg_assert_same(null, $r['same'], 'nothing changed');
	pg_assert_true($r['lzw'] < 60, 'a run compresses');
}
