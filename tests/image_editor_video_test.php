<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's video output (pg_image_editor_encode.js and
 * the model, run under Node): the dialog's video formats and limits.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/image_editor_encode_test.php';

// MP4 and WebM where the browser can make them; even sides, 24 or 30 fps,
// up to 1920 and 60 s; switching from GIF snaps the edge to a video one.
function test_image_editor_video_formats()
{
	$r = image_editor_encode_node(
		'out({' . "\n"
		. ' video: M.animFormats({ deflate: true, mp4: "codecs", webm: "recorder" }), still: M.animFormats({ deflate: true }),' . "\n"
		. ' limits: [M.ANIM_FORMATS.mp4.maxMs, M.ANIM_FORMATS.webm.rates, M.ANIM_FORMATS.mp4.edges[M.ANIM_FORMATS.mp4.edges.length - 1]],' . "\n"
		. ' even: M.animSize({ w: 1001, h: 751 }, 1920, true), odd: M.animSize({ w: 1001, h: 751 }, 1920), tiny: M.animSize({ w: 1, h: 1 }, 480, true),' . "\n"
		. ' toMp4: M.animSwitch({ format: "gif", edge: 800, fps: 15, loop: 0, dither: true }, "mp4"),' . "\n"
		. ' own: M.animSwitch({ format: "gif", edge: 700, fps: 25, loop: 0, dither: true }, "webm"),' . "\n"
		. ' back: M.animSwitch({ format: "mp4", edge: 1920, fps: 30, loop: 0, dither: true }, "gif"),' . "\n"
		. ' rate: [M.animBitrate({ w: 1280, h: 720 }, 30), M.animBitrate({ w: 2, h: 2 }, 24)],' . "\n"
		. ' name: M.animName("a.jpg", M.ANIM_FORMATS.webm.ext)' . "\n"
		. '});'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('gif', 'apng', 'mp4', 'webm'), $r['video'], 'video where the browser has a way');
	pg_assert_same(array('gif', 'apng'), $r['still'], 'no video without one');
	pg_assert_same(array(60000, array(24, 30), 1920), $r['limits'], 'limits');
	pg_assert_same(array('w' => 1000, 'h' => 750), $r['even'], 'even sides');
	pg_assert_same(array('w' => 1001, 'h' => 751), $r['odd'], 'odd kept for images');
	pg_assert_same(array('w' => 2, 'h' => 2), $r['tiny'], 'at least 2');
	pg_assert_same(array('format' => 'mp4', 'edge' => 720, 'fps' => 30, 'loop' => 0, 'dither' => true), $r['toMp4'], 'snapped to a video edge and rate');
	pg_assert_same(700, $r['own']['edge'], 'the picture\'s own edge kept');
	pg_assert_same(array('format' => 'gif', 'edge' => 800, 'fps' => 15, 'loop' => 0, 'dither' => true), $r['back'], 'back under the GIF limit');
	pg_assert_same(array(2764800, 250000), $r['rate'], 'bit rate');
	pg_assert_same('a.webm', $r['name'], 'extension');
}
