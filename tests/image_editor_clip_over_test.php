<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for layers over a video (V6 of the image editor's video plan): a
 * clip's rounded corners and border for picture in picture, a layer's own
 * end on the timeline, and the Effects tab as its screen has it.
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
function image_editor_clip_over_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieover');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Corners at most half the shorter side, one of four borders, the border's
// width a share of the shorter side and never under 2.
function test_image_editor_clip_over_frame()
{
	$r = image_editor_clip_over_node(
		'var box = { type: "video", w: 640, h: 360 };' . "\n"
		. 'console.log(JSON.stringify({ plain: M.clipFrame(box), round: M.clipFrame(Object.assign({ radius: 24, border: "#ffffff" }, box)),' . "\n"
		. ' big: M.clipFrame(Object.assign({ radius: 900 }, box)), odd: M.clipFrame(Object.assign({ radius: -4, border: "#123456" }, box)),' . "\n"
		. ' tiny: M.clipFrame({ w: 40, h: -60, border: "#000000" }), none: M.clipFrame(null), borders: M.CLIP_BORDERS }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('radius' => 0, 'border' => '', 'width' => 0), $r['plain'], 'square, no border');
	pg_assert_same(array('radius' => 24, 'border' => '#ffffff', 'width' => 5), $r['round'], 'rounded with a white border');
	pg_assert_same(180, $r['big']['radius'], 'at most half the shorter side');
	pg_assert_same(array('radius' => 0, 'border' => '', 'width' => 0), $r['odd'], 'a negative radius and a colour off the list are none');
	pg_assert_same(2, $r['tiny']['width'], 'never thinner than 2');
	pg_assert_same(array('radius' => 0, 'border' => '', 'width' => 0), $r['none'], 'no layer');
	pg_assert_same(array('#ffffff', '#000000', '#e53935', '#1e88e5'), $r['borders'], 'white, black, red, blue');
}
