<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an animation's sound in its video (pg_image_editor_mix.js, the
 * MIX section of the model and the muxers of pg_image_editor_encode.js, run
 * under Node): the mix plan with its gain corners, the sound's share of the
 * estimate, the AAC track of an MP4 and the Opus track of a WebM interleaved
 * with the frames, and the encoder's audio message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/image_editor_video_test.php';

// Heard sounds only, cut at the end, the fades as corners; each file once;
// the names after Add sound; the formats and settings that carry sound.
function test_image_editor_mix_plan()
{
	$r = image_editor_encode_node(
		'var s = M.soundList({ sounds: [' . "\n"
		. ' { id: "a", src: "/f/song.mp3", name: "Song.mp3", length: 10000, start: 1000, from: 500, to: 6500, volume: 0.8, fadeIn: 1000, fadeOut: 2000 },' . "\n"
		. ' { id: "b", src: "/f/bell.wav", name: "Bell.wav", length: 3000, start: 0, from: 0, to: 2000, volume: 1, fadeOut: 1000 },' . "\n"
		. ' { id: "c", src: "/f/song.mp3", name: "Song.mp3", length: 10000, start: 3000, from: 0, to: 1000, volume: 0.5 },' . "\n"
		. ' { id: "d", src: "/f/quiet.mp3", name: "Quiet.mp3", length: 3000, start: 0, from: 0, to: 3000, muted: true },' . "\n"
		. ' { id: "e", src: "/f/late.mp3", name: "Late.mp3", length: 3000, start: 5000, from: 0, to: 3000 },' . "\n"
		. ' { id: "f", src: "/f/zero.mp3", name: "Zero.mp3", length: 3000, start: 0, from: 0, to: 3000, volume: 0 }' . "\n"
		. ']}, "");' . "\n"
		. 'var plan = M.mixPlan(s, 5000);' . "\n"
		. 'out({ plan: plan, sources: M.mixSources(plan), names: M.mixNames(s, 5000), none: M.mixPlan([], 5000),' . "\n"
		. ' frames: [M.mixFrames(2500, 48000), M.mixFrames(0, 48000), M.mixFrames(1, 48000)],' . "\n"
		. ' bytes: [M.mixBytes(1000, "mp4"), M.mixBytes(1000, "webm"), M.mixBytes(0, "mp4")],' . "\n"
		. ' carries: ["gif", "apng", "webp", "pdf", "mp4", "webm"].map(M.mixCarries),' . "\n"
		. ' webm: M.mixConfig("webm"), mp4: M.mixConfig("mp4"), gif: M.mixConfig("gif") });'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(3, count($r['plan']), 'muted, silent and late sounds left out');
	pg_assert_same(array('src' => '/f/song.mp3', 'when' => 1000, 'offset' => 500, 'dur' => 4000, 'gains' => array(array(1000, 0), array(2000, 0.8), array(5000, 0.8))), $r['plan'][0], 'cut at the end, fade in as a corner');
	pg_assert_same(array(array(0, 1), array(1000, 1), array(2000, 0)), $r['plan'][1]['gains'], 'fade out to silence');
	pg_assert_same(array(array(3000, 0.5), array(4000, 0.5)), $r['plan'][2]['gains'], 'no fades: the volume at both ends');
	pg_assert_same(array('/f/song.mp3', '/f/bell.wav'), $r['sources'], 'each file once');
	pg_assert_same(array('Song.mp3', 'Bell.wav'), $r['names'], 'names of what is heard, once');
	pg_assert_same(array(), $r['none'], 'nothing to mix');
	pg_assert_same(array(120000, 1, 48), $r['frames'], 'sample frames');
	pg_assert_same(array(16376, 16400, 0), $r['bytes'], 'bit rate and packet index');
	pg_assert_same(array(false, false, false, false, true, true), $r['carries'], 'only the video formats carry sound');
	pg_assert_same(array('codec' => 'opus', 'kind' => 'opus', 'rate' => 48000, 'channels' => 2, 'bitrate' => 128000), $r['webm'], 'Opus for WebM');
	pg_assert_same('mp4a.40.2', $r['mp4']['codec'], 'AAC-LC for MP4');
	pg_assert_same(null, $r['gif'], 'no sound settings for GIF');
}

// A stand-in AudioEncoder and AudioData: each piece handed in becomes one
// packet (its index, then 7) with the piece's time and length; the first
// carries an AudioSpecificConfig when $desc.
function image_editor_mix_fake($desc)
{
	return 'seen.pieces = [];' . "\n"
		. 'function AD(init) { this.init = init; } AD.prototype.close = function () {};' . "\n"
		. 'function AE(o) { this.o = o; this.encodeQueueSize = 0; this.n = 0; }' . "\n"
		. 'AE.isConfigSupported = function (c) { seen.audio = c; return Promise.resolve({ supported: true }); };' . "\n"
		. 'AE.prototype.configure = function () {};' . "\n"
		. 'AE.prototype.encode = function (d) { var self = this, i = d.init, n = self.n++; seen.pieces.push([i.timestamp, i.numberOfFrames, i.format, i.numberOfChannels, i.data.length, i.data[i.numberOfFrames]]); this.o.output({ byteLength: 3, timestamp: i.timestamp, duration: Math.round(i.numberOfFrames * 1e6 / i.sampleRate), copyTo: function (b) { b.set([0xa0 + n, n, 7]); } }, (n || !' . ($desc ? 'true' : 'false') . ') ? {} : { decoderConfig: { description: new Uint8Array([0x11, 0x90]).buffer } }); };' . "\n"
		. 'AE.prototype.flush = function () { return Promise.resolve(); }; AE.prototype.close = function () {};' . "\n"
		. 'codec.AudioEncoder = AE; codec.AudioData = AD;' . "\n"
		. 'function planes(n) { var l = new Float32Array(n), r = new Float32Array(n); for (var i = 0; i < n; i++) { l[i] = 0.25; r[i] = -0.5; } return [l, r]; }' . "\n";
}

// 2.5 s at 30 fps with 2.5 s of AAC: a second trak (soun, 48 kHz, mp4a
// with the encoder's config in esds), a chunk of each track per second in
// mdat, the chunk offsets pointing at their own bytes, the longer length.
function test_image_editor_mix_mp4()
{
	$r = image_editor_encode_node(
		image_editor_video_fake() . image_editor_mix_fake(true)
		. 'function main() {' . "\n"
		. ' var enc = E.create({ format: "mp4", width: 8, height: 6, fps: 30, bitrate: 500000, codecs: M.ANIM_FORMATS.mp4.codecs, codec: codec, audio: M.mixConfig("mp4") }), f = [];' . "\n"
		. ' for (var n = 0; n < 75; n++) { f.push(frame(8, 6, n)); }' . "\n"
		. ' return enc.sound(planes(120000)).then(function () { return run(enc, f); }).then(function (bytes) { out({ mp4: b64(bytes), seen: seen }); });' . "\n"
		. '}'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('codec' => 'mp4a.40.2', 'sampleRate' => 48000, 'numberOfChannels' => 2, 'bitrate' => 128000), $r['seen']['audio'], 'AAC configured');
	pg_assert_same(array(array(0, 48000, 'f32-planar', 2, 96000, -0.5), array(1000000, 48000, 'f32-planar', 2, 96000, -0.5), array(2000000, 24000, 'f32-planar', 2, 48000, -0.5)), $r['seen']['pieces'], 'a second at a time, planar');

	$mp4 = base64_decode($r['mp4']);
	pg_assert_same('mp4', pg_image_editor_animation_type($mp4), 'still told by its bytes');
	$top = image_editor_video_boxes($mp4, 0, strlen($mp4));
	pg_assert_same(array('ftyp', 'moov', 'mdat'), array_map(function ($b) { return $b[0]; }, $top), 'index first');
	pg_assert_same(75 * 5 + 3 * 3, strlen($top[2][2]), 'frames and packets in mdat');

	$moov = image_editor_video_boxes($mp4, $top[1][1], $top[1][1] + strlen($top[1][2]));
	pg_assert_same(array('mvhd', 'trak', 'trak'), array_map(function ($b) { return $b[0]; }, $moov), 'two tracks');
	$mvhd = unpack('N2', substr($moov[0][2], 12, 8));
	pg_assert_same(array(1 => 1000, 2 => 2500), $mvhd, 'length in ms');
	$next = unpack('N', substr($moov[0][2], 96, 4));
	pg_assert_same(3, $next[1], 'next track id');

	$tables = array();

	foreach (array(1, 2) as $k) {
		$trak = $moov[$k];
		$sub = substr($mp4, 0, $trak[1] + strlen($trak[2]));
		$tables[$k] = array(
			'hdlr' => substr(image_editor_video_find(substr($sub, $trak[1]), 'mdia/hdlr'), 8, 4),
			'mdhd' => unpack('N2', substr(image_editor_video_find(substr($sub, $trak[1]), 'mdia/mdhd'), 12, 8)),
			'stsc' => array_values(unpack('N*', substr(image_editor_video_find(substr($sub, $trak[1]), 'mdia/minf/stbl/stsc'), 8))),
			'stco' => array_values(unpack('N*', substr(image_editor_video_find(substr($sub, $trak[1]), 'mdia/minf/stbl/stco'), 8))),
			'stsd' => image_editor_video_find(substr($sub, $trak[1]), 'mdia/minf/stbl/stsd'),
		);
	}

	pg_assert_same('vide', $tables[1]['hdlr'], 'picture first');
	pg_assert_same('soun', $tables[2]['hdlr'], 'then sound');
	pg_assert_same(array(1 => 48000, 2 => 120000), $tables[2]['mdhd'], 'sound timescale and length');
	pg_assert_same(array(1, 30, 1, 3, 15, 1), $tables[1]['stsc'], 'thirty frames a chunk, fifteen in the last');
	pg_assert_same(array(1, 1, 1), $tables[2]['stsc'], 'a packet a chunk');

	$data = $top[2][1];
	pg_assert_same(array($data, $data + 153, $data + 306), $tables[1]['stco'], 'frame chunks between the packets');
	pg_assert_same(array($data + 150, $data + 303, $data + 381), $tables[2]['stco'], 'packet chunks after their second\'s frames');

	foreach ($tables[2]['stco'] as $k => $at) {
		pg_assert_same(chr(0xa0 + $k) . chr($k) . "\x07", substr($mp4, $at, 3), 'packet ' . $k . ' where its offset says');
	}

	pg_assert_same(substr($mp4, $data, 5), substr($mp4, $tables[1]['stco'][0], 5), 'first frame at its offset');
	pg_assert_same(30, ord($mp4[$tables[1]['stco'][1] + 4]), 'frame 30 opens the second chunk');

	$mp4a = substr($tables[2]['stsd'], 12);
	pg_assert_same('mp4a', substr($mp4a, 0, 4), 'AAC sample entry');
	$ch = unpack('n2', substr($mp4a, 20, 4));
	pg_assert_same(array(1 => 2, 2 => 16), $ch, 'stereo, 16 bit');
	$rate = unpack('N', substr($mp4a, 28, 4));
	pg_assert_same(48000 * 65536, $rate[1], 'rate as 16.16');
	pg_assert_true(strpos($mp4a, 'esds') !== false && strpos($mp4a, "\x05\x02\x11\x90") !== false, 'the encoder\'s config in esds');
	pg_assert_true(strpos($mp4a, "\x04\x11\x40\x15") !== false, 'MPEG-4 audio stream');
}

// 2.5 s at 30 fps with Opus: an A_OPUS track entry with an OpusHead of its
// own (no description handed), CodecDelay and the rate; packets among the
// frames by time, a frame first at the same millisecond; the longer length.
function test_image_editor_mix_webm()
{
	$r = image_editor_encode_node(
		image_editor_video_fake() . image_editor_mix_fake(false)
		. 'function main() {' . "\n"
		. ' var enc = E.create({ format: "webm", width: 8, height: 6, fps: 30, bitrate: 500000, codecs: M.ANIM_FORMATS.webm.codecs, codec: codec, audio: M.mixConfig("webm") }), f = [];' . "\n"
		. ' for (var n = 0; n < 75; n++) { f.push(frame(8, 6, n)); }' . "\n"
		. ' return enc.sound(planes(120000)).then(function () { return run(enc, f); }).then(function (bytes) { out({ webm: b64(bytes), seen: seen }); });' . "\n"
		. '}'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('opus', $r['seen']['audio']['codec'], 'Opus configured');

	$webm = base64_decode($r['webm']);
	pg_assert_same('webm', pg_image_editor_animation_type($webm), 'still told by its bytes');
	$top = image_editor_video_children($webm, 0, strlen($webm));
	$seg = $top[1][2];
	$parts = image_editor_video_children($webm, $seg, $seg + $top[1][1]);
	pg_assert_same(array(0x1549A966, 0x1654AE6B, 0x1C53BB6B, 0x1F43B675, 0x1F43B675), array_map(function ($e) { return $e[0]; }, $parts), 'info, tracks, cues, two clusters');

	$info = substr($webm, $parts[0][2], $parts[0][1]);
	$at = strpos($info, "\x44\x89\x88");
	$dur = unpack('E', substr($info, $at + 3, 8));
	pg_assert_same(2500.0, $dur[1], 'length in ms');

	$entries = image_editor_video_children($webm, $parts[1][2], $parts[1][2] + $parts[1][1]);
	pg_assert_same(2, count($entries), 'two track entries');
	$audio = substr($webm, $entries[1][2], $entries[1][1]);
	pg_assert_true(strpos($audio, "\xD7\x81\x02") !== false && strpos($audio, "\x83\x81\x02") !== false, 'track 2 of type audio');
	pg_assert_true(strpos($audio, "\x86\x86A_OPUS") !== false, 'codec');
	pg_assert_true(strpos($audio, "\x63\xA2\x93OpusHead\x01\x02\x38\x01\x80\xBB\x00\x00\x00\x00\x00") !== false, 'own OpusHead: stereo, pre-skip 312, 48 kHz');
	pg_assert_true(strpos($audio, "\x56\xAA\x83\x63\x2E\xA0") !== false, 'CodecDelay of the pre-skip');
	pg_assert_true(strpos($audio, "\xB5\x88" . pack('E', 48000.0)) !== false && strpos($audio, "\x9F\x81\x02") !== false, 'rate and channels');

	$first = image_editor_video_children($webm, $parts[3][2], $parts[3][2] + $parts[3][1]);
	pg_assert_same(1 + 60 + 2, count($first), 'timecode, frames 0-59, packets at 0 and 1 s');
	pg_assert_same("\x81\x00\x00\x80", substr($webm, $first[1][2], 4), 'frame 0 first');
	pg_assert_same("\x82\x00\x00\x80\xA0\x00\x07", substr($webm, $first[2][2], 7), 'then the packet at 0, key');

	$heads = array();

	foreach (array_slice($first, 1) as $b) {
		$heads[] = bin2hex(substr($webm, $b[2], 3));
	}

	pg_assert_same('8203e8', $heads[array_search('8203e8', $heads)], 'packet at 1 s in the first cluster');
	pg_assert_same('8103e8', $heads[array_search('8203e8', $heads) - 1], 'after the frame of the same time');

	$second = image_editor_video_children($webm, $parts[4][2], $parts[4][2] + $parts[4][1]);
	pg_assert_same(1 + 15 + 1, count($second), 'frames 60-74 and the packet at 2 s');
	pg_assert_same("\x81\x00\x00\x80", substr($webm, $second[1][2], 4), 'the key frame opens it');
	pg_assert_same("\x82\x00\x00\x80\xA2\x02\x07", substr($webm, $second[2][2], 7), 'the packet of its time next');
}

// Through the session as the worker runs it: the audio message is answered
// with audioed, the file then carries the sound; a video without sound
// settings ignores the message.
function test_image_editor_mix_session()
{
	$r = image_editor_encode_node(
		image_editor_video_fake() . image_editor_mix_fake(false)
		. 'function main() {' . "\n"
		. ' var got = [], plain = [], done, wait = new Promise(function (go) { done = go; });' . "\n"
		. ' var s = E.session(function (m) { got.push(m.type === "file" ? m.type + ":" + m.data.byteLength : m.type); if (m.type === "file") { done(); } });' . "\n"
		. ' s.receive({ type: "begin", format: "webm", width: 8, height: 6, fps: 30, bitrate: 500000, codecs: M.ANIM_FORMATS.webm.codecs, codec: codec, audio: M.mixConfig("webm") });' . "\n"
		. ' for (var n = 0; n < 3; n++) { s.receive({ type: "frame", data: frame(8, 6, n).buffer }); }' . "\n"
		. ' s.receive({ type: "audio", data: planes(4800).map(function (p) { return p.buffer; }) });' . "\n"
		. ' s.receive({ type: "end" });' . "\n"
		. ' var p = E.session(function (m) { plain.push(m.type); });' . "\n"
		. ' p.receive({ type: "begin", format: "webm", width: 8, height: 6, fps: 30, bitrate: 500000, codecs: M.ANIM_FORMATS.webm.codecs, codec: codec });' . "\n"
		. ' p.receive({ type: "frame", data: frame(8, 6, 0).buffer });' . "\n"
		. ' p.receive({ type: "audio", data: planes(4800).map(function (x) { return x.buffer; }) });' . "\n"
		. ' return wait.then(function () { return new Promise(function (go) { setTimeout(go, 20); }); }).then(function () { out({ got: got, plain: plain, pieces: seen.pieces.length }); });' . "\n"
		. '}'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('framed', 'framed', 'framed', 'audioed'), array_slice($r['got'], 0, 4), 'audioed after the frames');
	pg_assert_true(strpos($r['got'][4], 'file:') === 0, 'then the file');
	pg_assert_same(1, $r['pieces'], 'one piece encoded');
	pg_assert_same(array('framed', 'audioed'), $r['plain'], 'no sound settings: nothing encoded, still answered');
}
