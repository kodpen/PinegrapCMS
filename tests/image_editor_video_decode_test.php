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
// presentation order two samples late, fed four samples ahead: the first
// frame decoded from the key frame with the lookahead, a cached frame
// tops the decoder up, one further on is decoded on without a reset and,
// with nothing more to feed, flushed at once; frames asked again come
// from the cache; H.265 refused with its name.
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
		. ' M.videoFeedAhead = function () { return 4; };'
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
	pg_assert_same(array(1, 'K5 8'), $r['next'], 'decoded already, the decoder topped up');
	pg_assert_same(array(7, '6 7 9 K10 11 flush'), $r['on'], 'decoded on, no reset; the end flushed at once');
	pg_assert_same(array(1, ''), $r['cached'], 'from the cache');
	pg_assert_same(array(11, ''), $r['last'], 'given out by the flush');
	pg_assert_same(array(0, ''), $r['back'], 'still cached');
	pg_assert_same('No H.265 (HEVC)', $r['hevc'], 'no decoder: the codec named');
	pg_assert_same('videoFormat', $r['png'], 'not a video');
}

// Plays seek points through M.videoFeedPlan() and M.videoOnWait() over a
// stand-in decoder that gives frames out in presentation order once more
// than `depth` samples are held (a reordering decoder; a hardware one
// holds more). Returns the script's helpers: streams of n samples with
// and without B-frames and run(track, depth, cache, ahead, times, st)
// counting each sample fed, resets, flushes (with whether all samples
// were fed by then) and frames not given.
function image_editor_video_plan_sim()
{
	return 'function stream(n, bframes, gop) { var s = [], d = 33333, i, k;'
		. ' if (!bframes) { for (i = 0; i < n; i++) { s.push({ ts: i * d, key: (i % gop) === 0 }); } return { samples: s }; }'
		. ' s.push({ ts: 0, key: true }); for (i = 1; i < n; i += 4) { k = Math.min(n - 1, i + 3); s.push({ ts: k * d, key: false });'
		. ' [i + 1, i, i + 2].forEach(function (j) { if (j < k) { s.push({ ts: j * d, key: false }); } }); } return { samples: s }; }'
		. ' function run(tr, depth, cacheMax, ahead, times, st) { var n = tr.samples.length, held = [], fed = [], ev = { resets: 0, flushes: [], missed: 0, waits: 0, asked: [] };'
		. ' st = st || { next: -1, pending: {}, ahead: ahead, cache: M.videoCache(cacheMax), fed: fed, ev: ev };'
		. ' function out(k) { held.sort(function (a, b) { return tr.samples[a].ts - tr.samples[b].ts; }); while (held.length > k) { var i = held.shift(); delete st.pending[i]; st.cache.put(i, { close: function () {} }); } }'
		. ' function feed(a, b) { for (var i = a; i <= b; i++) { st.fed[i] = (st.fed[i] || 0) + 1; st.pending[i] = true; held.push(i); out(depth); } if (a <= b) { st.next = b + 1; } }'
		. ' function flush() { st.ev.flushes.push(st.next >= n); out(0); st.next = -1; st.pending = {}; }'
		. ' times.forEach(function (us) { var p = M.videoSeek(tr, us), plan = M.videoFeedPlan(st, p, n, st.cache.has(p.frame)), what;'
		. ' st.ev.asked.push(p.frame); if (plan.reset) { st.ev.resets++; held = []; st.next = -1; st.pending = {}; } feed(plan.from, plan.to);'
		. ' if (plan.wait && plan.flush) { flush(); } else if (plan.wait) { while (!st.cache.has(p.frame)) { st.ev.waits++; what = M.videoOnWait(st, n, 0, 0);'
		. ' if (what === "flush") { flush(); break; } feed(st.next, Math.min(n - 1, st.next + st.ahead - 1)); } }'
		. ' if (!st.cache.has(p.frame)) { st.ev.missed++; } }); return st; }'
		. ' function sum(st, n) { var once = true, i; for (i = 0; i < n; i++) { once = once && (st.fed[i] === 1); } return { once: once, resets: st.ev.resets, flushes: st.ev.flushes, missed: st.ev.missed }; }'
		. ' function play(from, to, step) { var t = [], ms; for (ms = from; ms <= to; ms += step) { t.push(Math.round(ms * 1000)); } return t; }';
}

// Playing forward through 210 samples at the stage's 60 ticks a second:
// with B-frames (one key frame), without (a key frame each second), with
// a decoder holding more than the lookahead and on the B-frame fixture,
// every sample is fed once, nothing is reset but the start, and the one
// flush comes only when every sample was fed.
function test_image_editor_video_plan_forward()
{
	$r = image_editor_video_decode_node('h264_bframes.mp4', image_editor_video_plan_sim()
		. ' var b = stream(210, true), p = stream(210, false, 30), fix = M.videoTrack(M.videoDemux(file), "video"), times = play(0, 6990, 1000 / 60);'
		. ' return { bframes: sum(run(b, 3, 52, 12, times), 210), plain: sum(run(p, 1, 52, 12, times), 210), deep: sum(run(b, 16, 52, 12, times), 210),'
		. ' small: sum(run(b, 3, 8, M.videoFeedAhead(8), times), 210), fixture: sum(run(fix, 2, 90, 12, play(0, 1199, 1000 / 60)), 12),'
		. ' ahead: [M.videoFeedAhead(90), M.videoFeedAhead(52), M.videoFeedAhead(8), M.videoFeedAhead(3)],'
		. ' order: b.samples.slice(0, 9).map(function (s) { return s.ts / 33333; }) };'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 4, 2, 1, 3, 8, 6, 5, 7), $r['order'], 'the stand-in stream reorders');
	$want = array('once' => true, 'resets' => 1, 'flushes' => array(true), 'missed' => 0);
	pg_assert_same($want, $r['bframes'], 'B-frames: each sample once, no flush before the end');
	pg_assert_same($want, $r['plain'], 'no B-frames');
	pg_assert_same($want, $r['deep'], 'a decoder holding sixteen: fed on, never flushed early');
	pg_assert_same($want, $r['small'], 'a cache of eight frames');
	pg_assert_same($want, $r['fixture'], 'the B-frame fixture');
	pg_assert_same(array(12, 12, 6, 2), $r['ahead'], 'lookahead within the cache');
}

// A scrub far back to a frame no longer cached resets once and decodes on
// from there; a frame asked at the end of the stream flushes at once; the
// decision after a wait.
function test_image_editor_video_plan_scrub_and_end()
{
	$r = image_editor_video_decode_node('h264_bframes.mp4', image_editor_video_plan_sim()
		. ' var b = stream(210, true), st = run(b, 3, 20, 12, play(0, 6000, 1000 / 30)), r0 = st.ev.resets;'
		. ' st.ev.flushes = []; run(b, 3, 20, 12, play(1000, 2000, 1000 / 30), st);'
		. ' var end = M.videoFeedPlan({ next: -1, pending: {}, ahead: 12 }, M.videoSeek(b, 6990000), 210, false);'
		. ' var tail = M.videoFeedPlan({ next: 210, pending: { 207: true }, ahead: 12 }, { frame: 207, from: 0 }, 210, false);'
		. ' var held = M.videoFeedPlan({ next: 30, pending: { 20: true }, ahead: 12 }, { frame: 20, from: 0 }, 210, false);'
		. ' var far = M.videoFeedPlan({ next: 150, pending: {}, ahead: 12 }, { frame: 20, from: 0 }, 210, false);'
		. ' var ahead = M.videoFeedPlan({ next: 40, pending: {}, ahead: 12 }, { frame: 90, from: 0 }, 210, false);'
		. ' var seek = M.videoFeedPlan({ next: 40, pending: {}, ahead: 12 }, { frame: 95, from: 90 }, 210, false);'
		. ' var cached = [M.videoFeedPlan({ next: 30, pending: {}, ahead: 12 }, { frame: 25, from: 0 }, 210, true), M.videoFeedPlan({ next: 30, pending: {}, ahead: 12 }, { frame: 60, from: 0 }, 210, true)];'
		. ' var s = { next: 100 }, z = { next: 210 };'
		. ' return { start: r0, scrub: st.ev.resets - r0, flushes: st.ev.flushes, missed: st.ev.missed, end: end, tail: tail, held: held, far: far, ahead: ahead, seek: seek, cached: cached,'
		. ' wait: [M.videoOnWait(s, 210, 3, 300), M.videoOnWait(s, 210, 0, 300), M.videoOnWait(z, 210, 0, 300), M.videoOnWait(s, 210, 5, 2000), M.videoOnWait({ next: -1 }, 210, 0, 0)] };'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(1, $r['start'], 'played to 6 s from one reset');
	pg_assert_same(1, $r['scrub'], 'a scrub back to 1 s resets once, then decodes on');
	pg_assert_same(array(), $r['flushes'], 'no flush while scrubbing');
	pg_assert_same(0, $r['missed'], 'every frame given');
	pg_assert_same(array('reset' => true, 'from' => 0, 'to' => 209, 'wait' => true, 'flush' => true), $r['end'], 'the last frame: fed to the end, flushed at once');
	pg_assert_same(array('reset' => false, 'from' => 210, 'to' => 209, 'wait' => true, 'flush' => true), $r['tail'], 'all fed, the frame inside the decoder: flushed at once');
	pg_assert_same(array('reset' => false, 'from' => 30, 'to' => 32, 'wait' => true, 'flush' => false), $r['held'], 'fed long ago, not out yet: waited for and topped up, no reset');
	pg_assert_same(array('reset' => true, 'from' => 0, 'to' => 32, 'wait' => true, 'flush' => false), $r['far'], 'given out and gone from the cache: reset');
	pg_assert_same(array('reset' => false, 'from' => 40, 'to' => 102, 'wait' => true, 'flush' => false), $r['ahead'], 'ahead in the same run: decoded on');
	pg_assert_same(array('reset' => true, 'from' => 90, 'to' => 107, 'wait' => true, 'flush' => false), $r['seek'], 'ahead past a key frame: reset there');
	pg_assert_same(array(
		array('reset' => false, 'from' => 30, 'to' => 37, 'wait' => false, 'flush' => false),
		array('reset' => false, 'from' => 0, 'to' => -1, 'wait' => false, 'flush' => false),
	), $r['cached'], 'a cached frame tops up only a decoder standing past it');
	pg_assert_same(array('wait', 'feed', 'flush', 'flush', 'flush'), $r['wait'], 'after a wait: queued, more to feed, the end, stalled');
}
