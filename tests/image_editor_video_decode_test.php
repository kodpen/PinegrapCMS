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
		. 'try { var r = (function () { ' . $script . ' }()); if (r !== undefined) { console.log(JSON.stringify(r)); } } catch (e) { console.log(JSON.stringify({ error: String(e && e.stack || e) })); }'
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
	pg_assert_same('avc1.4d400a', $r['codec'], 'codec from avcC');
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

// The VP9 WebM: blocks of known-size Clusters, key frames from the
// SimpleBlock flags, times from TimecodeScale, the length from Info.
function test_image_editor_video_webm_vp9_table()
{
	$r = image_editor_video_decode_node('vp9.webm',
		'var d = M.videoDemux(file), v = M.videoTrack(d, "video");'
		. ' return { kind: d.kind, ms: d.duration, codec: v.codec, size: [v.width, v.height], desc: v.description, rows: table(v), audio: M.videoTrack(d, "audio") };'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('webm', 1200, 'vp09.00.10.08', array(32, 16), null, null), array($r['kind'], $r['ms'], $r['codec'], $r['size'], $r['desc'], $r['audio']), 'file and track');
	pg_assert_same(array(
		array(491, 357, 0, 100000, 1), array(854, 42, 100000, 100000, 0), array(902, 50, 200000, 100000, 0),
		array(958, 57, 300000, 100000, 0), array(1021, 62, 400000, 100000, 0), array(1090, 361, 500000, 100000, 1),
		array(1457, 52, 600000, 100000, 0), array(1515, 68, 700000, 100000, 0), array(1589, 68, 800000, 100000, 0),
		array(1663, 72, 900000, 100000, 0), array(1742, 354, 1000000, 100000, 1), array(2102, 65, 1100000, 100000, 0),
	), $r['rows'], 'sample table');
}

// A WebM as a recorder writes it: the Segment and Clusters of unknown
// size, no length in Info (taken from the last sample), VP8 and Opus
// blocks interleaved.
function test_image_editor_video_webm_live()
{
	$r = image_editor_video_decode_node('vp8_opus_live.webm',
		'var d = M.videoDemux(file), v = M.videoTrack(d, "video"), a = M.videoTrack(d, "audio");'
		. ' return { ms: d.duration, codec: v.codec, keys: v.samples.map(function (s) { return s.key ? 1 : 0; }).join(""), first: table(v)[0], last: table(v)[11],'
		. ' audio: [a.codec, a.rate, a.channels, a.description.length, a.samples.length, table(a)[1]] };'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(1221, $r['ms'], 'length from the last sample');
	pg_assert_same('vp8', $r['codec'], 'VP8');
	pg_assert_same('100001000010', $r['keys'], 'key frames');
	pg_assert_same(array(671, 452, 0, 100000, 1), $r['first'], 'first frame');
	pg_assert_same(array(5668, 116, 1100000, 100000, 0), $r['last'], 'last frame, the next Cluster found without a size');
	pg_assert_same(array('opus', 48000, 1, 19, 61, array(1129, 59, 21000, 20000, 1)), $r['audio'], 'Opus with its OpusHead');
}

// H.265: its RFC 6381 codec string (the name a message gives when the
// browser has no decoder for it) and hvcC as the description.
function test_image_editor_video_hevc()
{
	$r = image_editor_video_decode_node('hevc.mp4',
		'var v = M.videoTrack(M.videoDemux(file), "video"), c = M.videoConfig(v);'
		. ' return { codec: c.codec, size: [c.codedWidth, c.codedHeight], desc: c.description.length, keys: v.samples.map(function (s) { return s.key ? 1 : 0; }).join(""),'
		. ' names: ["avc1.4d400a", c.codec, "vp8", "vp09.00.10.08", "av01.0.04M.08", "xyz"].map(M.videoCodecName) };'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('hvc1.1.6.L30.90', $r['codec'], 'codec string');
	pg_assert_same(array(32, 16), $r['size'], 'size');
	pg_assert_same(2408, $r['desc'], 'hvcC');
	pg_assert_same('10000', $r['keys'], 'stss');
	pg_assert_same(array('H.264', 'H.265 (HEVC)', 'VP8', 'VP9', 'AV1', 'xyz'), $r['names'], 'names');
}

// The seek point for a time: the frame shown then (B-frames by their
// presentation time) and the key frame decoding starts from.
function test_image_editor_video_seek_point()
{
	$r = image_editor_video_decode_node('h264_bframes.mp4',
		'var v = M.videoTrack(M.videoDemux(file), "video");'
		. ' return [-5, 0, 99999, 100000, 250000, 450000, 500000, 650000, 1150000, 9e9].map(function (us) { var p = M.videoSeek(v, us); return [p.frame, p.from]; }).concat([M.videoSeek({ samples: [] }, 0)]);'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(
		array(0, 0), array(0, 0), array(0, 0), array(2, 0), array(3, 0), array(4, 0), array(5, 5), array(7, 5), array(11, 10), array(11, 10), null,
	), $r, 'frame and key frame');
}

// The frame cache keeps the most recently used frames and closes the
// rest; its size follows the picture size within its limits.
function test_image_editor_video_cache()
{
	$r = image_editor_video_decode_node('vp9.webm',
		'var closed = []; function F(n) { return { n: n, close: function () { closed.push(n); } }; }'
		. ' var c = M.videoCache(3); c.put(1, F(1)); c.put(2, F(2)); c.put(3, F(3)); c.get(1); c.put(4, F(4)); c.put(4, F(44));'
		. ' var kept = [1, 2, 3, 4].map(function (i) { return c.has(i) ? c.get(i).n : 0; }); c.clear();'
		. ' return { kept: kept, closed: closed, empty: c.size(), sizes: [M.videoCacheSize(32, 16), M.videoCacheSize(1920, 1080), M.videoCacheSize(3840, 2160), M.videoCacheSize(7680, 4320)] };'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(1, 0, 3, 44), $r['kept'], 'least recently used dropped');
	pg_assert_same(array(2, 4, 1, 3, 44), $r['closed'], 'closed when dropped, replaced or cleared');
	pg_assert_same(0, $r['empty'], 'cleared');
	pg_assert_same(array(90, 24, 8, 8), $r['sizes'], 'frames kept by picture size');
}

// What is not a video, or a cut-off one, fails with a code the editor
// turns into a message.
function test_image_editor_video_bad_files()
{
	$r = image_editor_video_decode_node('h264_bframes.mp4',
		'function code(b) { try { M.videoDemux(b); return "read"; } catch (e) { return e.message; } }'
		. ' return [code(new Uint8Array([137, 80, 78, 71, 13, 10, 26, 10, 0, 0, 0, 0])), code(file.subarray(0, 900)), code(file.subarray(0, 40)), code(file.subarray(0, 4000)),'
		. ' code(new Uint8Array([0x1A, 0x45, 0xDF, 0xA3, 0x80])), code(file), M.videoKind(file), M.videoKind(new Uint8Array(3))];'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('video-format', 'video-broken', 'video-broken', 'video-broken', 'video-broken', 'read', 'mp4', ''), $r, 'codes');
}

// The part's source over a stand-in VideoDecoder that gives frames out in
// presentation order two samples late: the first frame decoded from the
// key frame with the lookahead, the next ones decoded on without a seek,
// one far ahead from its key frame after a reset, the last after a
// flush, a frame asked twice from the cache, H.265 refused with its name.
function test_image_editor_video_source()
{
	$r = image_editor_video_decode_node('h264_bframes.mp4',
		'var log = [];'
		. ' function VD(o) { this.o = o; this.held = []; this.state = "unconfigured"; }'
		. ' VD.isConfigSupported = function (c) { return Promise.resolve({ supported: !/^hvc1/.test(c.codec) }); };'
		. ' VD.prototype.configure = function () { this.state = "configured"; };'
		. ' VD.prototype.out = function (n) { var o = this.o; this.held.sort(function (a, b) { return a - b; }); while (this.held.length > n) { var ts = this.held.shift(); o.output({ timestamp: ts, close: function () {}, clone: function () { return { timestamp: this.timestamp, close: function () {} }; } }); } };'
		. ' VD.prototype.decode = function (c) { log.push((c.type === "key" ? "K" : "") + c.timestamp / 100000); this.held.push(c.timestamp); this.out(2); };'
		. ' VD.prototype.flush = function () { log.push("flush"); this.out(0); return Promise.resolve(); };'
		. ' VD.prototype.reset = function () { log.push("reset"); this.held = []; this.state = "unconfigured"; };'
		. ' VD.prototype.close = function () { this.state = "closed"; };'
		. ' global.window = { VideoDecoder: VD, EncodedVideoChunk: function (o) { Object.assign(this, o); }, PgImageEditor: { parts: { Editor: function () {}, M: M, t: function (k) { return { videoCodec: "No {var:1}" }[k] || k; } } } };'
		. ' require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_video.js') . ');'
		. ' var V = window.PgImageEditor.parts.video, res = {}, src;'
		. ' function step(name, ms) { return src.frameAt(ms).then(function (f) { res[name] = [f && f.timestamp / 100000, log.join(" ")]; log = []; }); }'
		. ' V.open(file).then(function (s) { src = s; res.size = [s.width, s.height, s.duration, s.codec]; return step("first", 0); })'
		. '.then(function () { return step("next", 150); }).then(function () { return step("on", 750); }).then(function () { return step("cached", 150); })'
		. '.then(function () { return step("last", 1199); }).then(function () { return step("back", 0); })'
		. '.then(function () { return V.open(require("fs").readFileSync(' . json_encode(__DIR__ . '/fixtures/video/hevc.mp4') . ')); })'
		. '.then(null, function (e) { res.hevc = V.message(e); return V.open(new Uint8Array(20)); }).then(null, function (e) { res.png = V.message(e); console.log(JSON.stringify(res)); });'
		. ' return undefined;'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(32, 16, 1200, 'avc1.4d400a'), $r['size'], 'source');
	pg_assert_same(array(0, 'reset K0 3 1 2 4'), $r['first'], 'from the key frame with the lookahead');
	pg_assert_same(array(1, ''), $r['next'], 'decoded already');
	pg_assert_same(array(7, 'K5 8 6 7 9 K10 11'), $r['on'], 'decoded on, no seek');
	pg_assert_same(array(1, ''), $r['cached'], 'from the cache');
	pg_assert_same(array(11, 'flush'), $r['last'], 'flushed out at the end');
	pg_assert_same(array(0, ''), $r['back'], 'still cached');
	pg_assert_same('No H.265 (HEVC)', $r['hevc'], 'no decoder: the codec named');
	pg_assert_same('videoFormat', $r['png'], 'not a video');
}
