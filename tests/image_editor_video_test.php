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

// A stand-in VideoEncoder and VideoFrame for Node: each frame becomes a
// chunk of its first pixel's bytes and its index, key when asked; the
// first output carries a decoder description.
function image_editor_video_fake()
{
	return 'var seen = { configs: [], keys: [], formats: [] };' . "\n"
		. 'function VF(data, init) { this.data = data; this.init = init; } VF.prototype.close = function () {};' . "\n"
		. 'function VE(o) { this.o = o; this.encodeQueueSize = 0; this.n = 0; }' . "\n"
		. 'VE.isConfigSupported = function (c) { seen.configs.push(c.codec); return Promise.resolve({ supported: c.codec !== "avc1.42E028" }); };' . "\n"
		. 'VE.prototype.configure = function (c) { this.c = c; seen.avc = c.avc; };' . "\n"
		. 'VE.prototype.encode = function (f, opt) { var self = this, d = new Uint8Array([f.data[0], f.data[1], f.data[2], f.data[3], self.n]); seen.keys.push(!!opt.keyFrame); seen.formats.push(f.init.format + ":" + f.init.timestamp); this.o.output({ type: opt.keyFrame ? "key" : "delta", byteLength: d.length, copyTo: function (b) { b.set(d); } }, self.n++ ? {} : { decoderConfig: { description: new Uint8Array([1, 66, 0, 40]).buffer } }); };' . "\n"
		. 'VE.prototype.flush = function () { return Promise.resolve(); }; VE.prototype.close = function () {};' . "\n"
		. 'var codec = { VideoEncoder: VE, VideoFrame: VF };' . "\n";
}

// ISO boxes of $bytes from $at to $end as list(type, body offset, body).
function image_editor_video_boxes($bytes, $at, $end)
{
	$out = array();

	while ($at + 8 <= $end) {
		$n = unpack('N', substr($bytes, $at, 4));
		pg_assert_true($n[1] >= 8 && $at + $n[1] <= $end, 'box size');
		$out[] = array(substr($bytes, $at + 4, 4), $at + 8, substr($bytes, $at + 8, $n[1] - 8));
		$at += $n[1];
	}

	pg_assert_same($end, $at, 'boxes fill their parent');

	return $out;
}

function image_editor_video_find($bytes, $path)
{
	$at = 0;
	$end = strlen($bytes);

	foreach (explode('/', $path) as $type) {
		$hit = null;

		foreach (image_editor_video_boxes($bytes, $at, $end) as $b) {
			if ($b[0] === $type) {
				$hit = $b;
			}
		}

		pg_assert_true($hit !== null, $path . ' has ' . $type);
		$at = $hit[1];
		$end = $hit[1] + strlen($hit[2]);
		$body = $hit[2];
		$at += ($type === 'stsd') ? 8 : 0;
	}

	return $body;
}

// Five frames at 30 fps, key every two seconds: an MP4 with ftyp, the
// index before mdat, the sample sizes and the chunk offset pointing at
// the first sample's bytes; transparent pixels flattened over white; the
// first supported codec configured with the avc format.
function test_image_editor_video_mp4()
{
	$r = image_editor_encode_node(
		image_editor_video_fake()
		. 'function main() {' . "\n"
		. ' var enc = E.create({ format: "mp4", width: 8, height: 6, fps: 30, bitrate: 500000, codecs: M.ANIM_FORMATS.mp4.codecs, codec: codec }), f = [];' . "\n"
		. ' for (var n = 0; n < 5; n++) { f.push(frame(8, 6, n, true)); }' . "\n"
		. ' f[0][3] = 0; f[0][0] = 0;' . "\n"
		. ' return run(enc, f).then(function (bytes) { out({ mp4: b64(bytes), mime: enc.mime, seen: seen, est: enc.estimate() }); });' . "\n"
		. '}'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('video/mp4', $r['mime'], 'mime');
	pg_assert_same(array('avc1.42E028', 'avc1.4D0028'), $r['seen']['configs'], 'codecs tried in order');
	pg_assert_same(array('format' => 'avc'), $r['seen']['avc'], 'avcC description asked');
	pg_assert_same(array(true, false, false, false, false), $r['seen']['keys'], 'first frame key');
	pg_assert_same('RGBX:33333', $r['seen']['formats'][1], 'timestamps in microseconds');
	pg_assert_same(array('header' => 1000, 'frame' => 2083), $r['est'], 'estimate from the bit rate');

	$mp4 = base64_decode($r['mp4']);
	$top = image_editor_video_boxes($mp4, 0, strlen($mp4));
	pg_assert_same(array('ftyp', 'moov', 'mdat'), array_map(function ($b) { return $b[0]; }, $top), 'index first');
	pg_assert_same('isom', substr($top[0][2], 0, 4), 'brand');
	pg_assert_same(25, strlen($top[2][2]), 'five samples of five bytes');
	pg_assert_same("\xff\xff\xff\xff\x00", substr($top[2][2], 0, 5), 'transparent over white, first sample');

	$stco = unpack('N', substr(image_editor_video_find($mp4, 'moov/trak/mdia/minf/stbl/stco'), 8, 4));
	pg_assert_same($top[2][1], $stco[1], 'chunk offset at mdat data');

	$stsz = unpack('N3', substr(image_editor_video_find($mp4, 'moov/trak/mdia/minf/stbl/stsz'), 4, 12));
	pg_assert_same(array(1 => 0, 2 => 5, 3 => 5), $stsz, 'sample count and first size');
	pg_assert_same("\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x01", image_editor_video_find($mp4, 'moov/trak/mdia/minf/stbl/stss'), 'one sync sample');

	$stts = unpack('N3', substr(image_editor_video_find($mp4, 'moov/trak/mdia/minf/stbl/stts'), 4, 12));
	pg_assert_same(array(1 => 1, 2 => 5, 3 => 3000), $stts, 'constant duration at 90 kHz');

	$avc1 = image_editor_video_find($mp4, 'moov/trak/mdia/minf/stbl/stsd/avc1');
	$wh = unpack('n2', substr($avc1, 24, 4));
	pg_assert_same(array(1 => 8, 2 => 6), $wh, 'size');
	pg_assert_same("\x00\x00\x00\x0c" . 'avcC' . "\x01\x42\x00\x28", substr($avc1, 78), 'description as avcC');

	$mvhd = unpack('N2', substr(image_editor_video_find($mp4, 'moov/mvhd'), 12, 8));
	pg_assert_same(array(1 => 1000, 2 => 167), $mvhd, 'duration in ms');
}

// An EBML element of $bytes at $at as array(id, size, body offset);
// image_editor_video_children() adds its start.
function image_editor_video_ebml($bytes, $at)
{
	$first = ord($bytes[$at]);
	$len = 1;

	while ($len < 4 && !($first & (0x80 >> ($len - 1)))) {
		$len++;
	}

	$id = 0;

	for ($i = 0; $i < $len; $i++) {
		$id = $id * 256 + ord($bytes[$at + $i]);
	}

	$at += $len;
	$first = ord($bytes[$at]);
	$n = 1;

	while ($n < 8 && !($first & (0x80 >> ($n - 1)))) {
		$n++;
	}

	$size = $first & ((0x80 >> ($n - 1)) - 1);

	for ($i = 1; $i < $n; $i++) {
		$size = $size * 256 + ord($bytes[$at + $i]);
	}

	return array($id, $size, $at + $n);
}

function image_editor_video_children($bytes, $at, $end)
{
	$out = array();

	while ($at < $end) {
		$e = image_editor_video_ebml($bytes, $at);
		$e[] = $at;
		$out[] = $e;
		$at = $e[2] + $e[1];
	}

	pg_assert_same($end, $at, 'elements fill their parent');

	return $out;
}

// 70 frames at 30 fps (keys at 0 and 2 s): a webm doctype, Info, Tracks
// with V_VP9, the Cues before two Clusters whose positions they name, and
// key flags on the blocks.
function test_image_editor_video_webm()
{
	$r = image_editor_encode_node(
		image_editor_video_fake()
		. 'function main() {' . "\n"
		. ' var enc = E.create({ format: "webm", width: 8, height: 6, fps: 30, bitrate: 500000, codecs: M.ANIM_FORMATS.webm.codecs, codec: codec }), f = [];' . "\n"
		. ' for (var n = 0; n < 70; n++) { f.push(frame(8, 6, n)); }' . "\n"
		. ' return run(enc, f).then(function (bytes) { out({ webm: b64(bytes), mime: enc.mime }); });' . "\n"
		. '}'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('video/webm', $r['mime'], 'mime');

	$webm = base64_decode($r['webm']);
	$top = image_editor_video_children($webm, 0, strlen($webm));
	pg_assert_same(array(0x1A45DFA3, 0x18538067), array($top[0][0], $top[1][0]), 'header and segment');
	pg_assert_true(strpos(substr($webm, 0, $top[0][2] + $top[0][1]), "\x42\x82\x84webm") !== false, 'doctype webm');

	$seg = $top[1][2];
	$parts = image_editor_video_children($webm, $seg, $seg + $top[1][1]);
	pg_assert_same(array(0x1549A966, 0x1654AE6B, 0x1C53BB6B, 0x1F43B675, 0x1F43B675), array_map(function ($e) { return $e[0]; }, $parts), 'info, tracks, cues, clusters');
	pg_assert_true(strpos(substr($webm, $parts[1][2], $parts[1][1]), "\x86\x85V_VP9") !== false, 'codec');

	$points = image_editor_video_children($webm, $parts[2][2], $parts[2][2] + $parts[2][1]);
	pg_assert_same(2, count($points), 'a cue per cluster');

	foreach ($points as $k => $p) {
		$at = strpos(substr($webm, $p[2], $p[1]), "\xF1\x88");
		$pos = unpack('J', substr($webm, $p[2] + $at + 2, 8));
		pg_assert_same($parts[3 + $k][3] - $seg, $pos[1], 'cue ' . $k . ' names its cluster');
	}

	$blocks = image_editor_video_children($webm, $parts[4][2], $parts[4][2] + $parts[4][1]);
	pg_assert_same(array(0xE7, 0xA3), array($blocks[0][0], $blocks[1][0]), 'timecode then blocks');
	pg_assert_same(11, count($blocks), 'frames 60 to 69');
	pg_assert_same("\x81\x00\x00\x80", substr($webm, $blocks[1][2], 4), 'track 1, time 0, key');
	pg_assert_same("\x81\x00\x21\x00", substr($webm, $blocks[2][2], 4), 'next at 33 ms, not key');
}
