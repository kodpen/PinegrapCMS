<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's video reading (the VIDEO section of the
 * model, run under Node): the MP4 and WebM demuxers over small files from
 * tests/fixtures/video (H.264 with B-frames and an edit list, H.265, VP9,
 * a live VP8 + Opus WebM with unknown sizes), the decoder configuration
 * each track gives WebCodecs, the seek point for a time and the frame
 * cache.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/image_editor_render_test.php';

// Runs $script with the model as M and the fixture $name as the bytes
// `file` (a Uint8Array); null without Node.
function image_editor_video_decode_node($name, $script)
{
	return image_editor_render_node(
		'var file = new Uint8Array(require("fs").readFileSync(' . json_encode(__DIR__ . '/fixtures/video/' . $name) . '));' . "\n"
		. 'function table(tr) { return tr.samples.map(function (s) { return [s.at, s.size, s.ts, s.dur, s.key ? 1 : 0]; }); }' . "\n"
		. 'try { console.log(JSON.stringify((function () { ' . $script . ' }()))); } catch (e) { console.log(JSON.stringify({ error: String(e && e.stack || e) })); }'
	);
}

// The H.264 MP4: its sample table in decode order with presentation
// times after the edit list (B-frames shown before the frames decoded
// before them), key frames from stss, offsets through stsc and stco.
function test_image_editor_video_mp4_h264_table()
{
	$r = image_editor_video_decode_node('h264_bframes.mp4',
		'var d = M.videoDemux(file), v = M.videoTrack(d, "video"), a = M.videoTrack(d, "audio");'
		. ' return { kind: d.kind, ms: d.duration, codec: v.codec, w: v.width, h: v.height, desc: v.description.length, rows: table(v), audio: [a.codec, a.rate, a.channels, a.samples.length > 0] };'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('mp4', $r['kind'], 'told by its bytes');
	pg_assert_same(1200, $r['ms'], 'length');
	pg_assert_same('avc1.4d000a', $r['codec'], 'codec from avcC');
	pg_assert_same(array(32, 16), array($r['w'], $r['h']), 'size');
	pg_assert_same(37, $r['desc'], 'avcC as the description');
	pg_assert_same(array(
		array(1723, 937, 0, 100000, 1), array(3017, 48, 300000, 100000, 0), array(3065, 12, 100000, 100000, 0),
		array(3387, 12, 200000, 100000, 0), array(3650, 32, 400000, 100000, 0), array(3911, 278, 500000, 100000, 1),
		array(4415, 53, 800000, 100000, 0), array(4468, 12, 600000, 100000, 0), array(4745, 12, 700000, 100000, 0),
		array(5010, 36, 900000, 100000, 0), array(5286, 250, 1000000, 100000, 1), array(5852, 16, 1100000, 100000, 0),
	), $r['rows'], 'sample table');
	pg_assert_same(array('mp4a.40.2', 8000, 1, true), $r['audio'], 'its sound track');
}
