<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's video layer (pg_image_editor_clip.js and the
 * CLIP section of the model, run under Node): the layer's timing in range,
 * its span and the source time at a moment, a new clip fitted to the
 * canvas, Replace, the document's latest clip end, the clip's own sound and
 * Detach sound; and the server's side: a video told by its bytes and the
 * videos' listing (pg_image_editor_clip_*, pg_image_editor_sound_* with the
 * video types).
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
function image_editor_clip_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieclip');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'function clip(o) { return Object.assign({ id: "l1", type: "video", src: "/v.mp4", name: "v.mp4", length: 20000, width: 1920, height: 1080, sound: true, from: 2000, to: 6000, start: 1000, speed: 1, volume: 1, muted: false, x: 0, y: 0, w: 1920, h: 1080, rot: 0 }, o || {}); }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Numbers in range, the speed one of the six, at least 0.1 s of source.
function test_image_editor_clip_layer()
{
	$r = image_editor_clip_node(
		'console.log(JSON.stringify({ a: M.clipLayer(clip({ from: 30000, to: -5, speed: 3.1, volume: 7, start: -2 })), b: M.clipLayer(clip({ speed: "x", to: 1 })),' . "\n"
		. ' c: M.clipLayer({}), speeds: M.CLIP_SPEEDS }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(19900, $r['a']['from'], 'the source in leaves 0.1 s of the file');
	pg_assert_same(20000, $r['a']['to'], 'the source out is at least 0.1 s after the source in');
	pg_assert_same(4, $r['a']['speed'], 'a speed off the list takes the nearest');
	pg_assert_same(1, $r['a']['volume'], 'the volume is at most 1');
	pg_assert_same(0, $r['a']['start'], 'the start is never before 0');
	pg_assert_same(1, $r['b']['speed'], 'no number is 1×');
	pg_assert_same(2100, $r['b']['to'], 'the cut keeps 0.1 s');
	pg_assert_same(array(0, 0, 0), array($r['c']['length'], $r['c']['from'], $r['c']['to']), 'an empty layer');
	pg_assert_same(array(0.25, 0.5, 1, 1.5, 2, 4), $r['speeds'], 'the speed chips');
}

// The span lasts the cut over the speed; the source time at a moment, the
// source in at rest, nothing off the span.
function test_image_editor_clip_span_and_at()
{
	$r = image_editor_clip_node(
		'var c = clip(), fast = clip({ speed: 2 });' . "\n"
		. 'console.log(JSON.stringify({ span: M.clipSpan(c), fast: M.clipSpan(fast), slow: M.clipSpan(clip({ speed: 0.5 })),' . "\n"
		. ' at: [null, 0, 999, 1000, 2500, 4999, 5000, 9000].map(function (t) { return M.clipAt(c, t); }),' . "\n"
		. ' atFast: [1000, 2000, 2999, 3000].map(function (t) { return M.clipAt(fast, t); }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('from' => 1000, 'to' => 5000), $r['span'], 'four seconds of source from 1 s');
	pg_assert_same(3000, $r['fast']['to'], '2× lasts half as long');
	pg_assert_same(9000, $r['slow']['to'], '0.5× lasts twice as long');
	pg_assert_same(array(2000, null, null, 2000, 3500, 5999, null, null), $r['at'], 'at rest the source in; nothing before the start or from the end');
	pg_assert_same(array(2000, 4000, 5998, null), $r['atFast'], 'the source runs at the speed');
}

// A new clip keeps the source's ratio inside the canvas, centred, all of the
// file from the playhead; Replace keeps the box's width and centre.
function test_image_editor_clip_new_and_replace()
{
	$r = image_editor_clip_node(
		'var box = { x: 0, y: 0, w: 1080, h: 1350 };' . "\n"
		. 'var l = M.clipNew({ id: 7, name: "a.mp4", url: "/a.mp4" }, { length: 12400.4, width: 1920, height: 1080, sound: true }, box, 1500.2);' . "\n"
		. 'var r = M.clipReplace(clip({ x: 0, y: 100, w: 1000, h: 500, from: 2000, to: 9000 }), { id: 8, name: "b.webm", url: "/b.webm" }, { length: 5000, width: 1000, height: 1000, sound: false });' . "\n"
		. 'console.log(JSON.stringify({ l: l, r: r, fit: M.clipFit(1000, 1000, { x: 10, y: 20, w: 400, h: 200 }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('video', $r['l']['type'], 'a video layer');
	pg_assert_same(array(7, '/a.mp4', 12400, 0, 12400, 1500, 1, true), array($r['l']['fileId'], $r['l']['src'], $r['l']['length'], $r['l']['from'], $r['l']['to'], $r['l']['start'], $r['l']['speed'], $r['l']['sound']), 'the whole file from the playhead at 1×');
	pg_assert_same(array(0, 371.25, 1080, 607.5), array($r['l']['x'], $r['l']['y'], $r['l']['w'], $r['l']['h']), 'the ratio kept inside the canvas, centred');
	pg_assert_same(array('x' => 110, 'y' => 20, 'w' => 200, 'h' => 200), $r['fit'], 'a square in a wide box');
	pg_assert_same(array(2000, 5000), array($r['r']['from'], $r['r']['to']), 'the cut kept inside the shorter file');
	pg_assert_same(array(-150, 1000), array($r['r']['y'], $r['r']['h']), 'the box keeps its centre, the new ratio');
	pg_assert_same(false, $r['r']['sound'], 'the new file\'s sound');
}

// The latest clip end; the clip's own sound and Detach sound.
function test_image_editor_clip_sound()
{
	$r = image_editor_clip_node(
		'var doc = { layers: [clip(), clip({ id: "l2", start: 4000, speed: 0.5 }), { id: "l3", type: "text" }] };' . "\n"
		. 'console.log(JSON.stringify({ end: M.clipEnd(doc), none: M.clipEnd({ layers: [] }), s: M.clipSound(clip({ volume: 0.5 }), 3000),' . "\n"
		. ' muted: [clip({ muted: true }), clip({ speed: 2 }), clip({ sound: false }), clip({ hidden: true })].map(function (l) { return M.clipSound(l, 0).muted; }),' . "\n"
		. ' plan: M.soundPlan(M.clipSound(clip(), 0), 2000), d: M.clipDetach(clip({ muted: true, volume: 0.4 }), "s3", 500), d2: M.clipDetach(clip({ speed: 2 }), "s3", 0),' . "\n"
		. ' d3: M.clipDetach(clip({ sound: false }), "s3", 0), list: M.soundList({ sounds: [M.clipDetach(clip(), "s1", 0)] }, "").length, type: [M.clipType("A.MP4"), M.clipType("b.webm"), M.clipType("c.mov")] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(12000, $r['end'], 'the slow clip ends last');
	pg_assert_same(0, $r['none'], 'no clip');
	pg_assert_same(array(4000, 2000, 6000, 0.5, false), array($r['s']['start'], $r['s']['from'], $r['s']['to'], $r['s']['volume'], $r['s']['muted']), 'the cut on the whole timeline from the scene\'s start');
	pg_assert_same(array(true, true, true, true), $r['muted'], 'muted, off 1×, without sound or hidden: silent');
	pg_assert_same(array('wait' => 0, 'offset' => 3000, 'dur' => 3000), $r['plan'], 'the sound part plays it in step with the pictures');
	pg_assert_same(array('s3', 1500, false, 0.4), array($r['d']['id'], $r['d']['start'], $r['d']['muted'], $r['d']['volume']), 'a detached sound plays where the clip was muted');
	pg_assert_same(null, $r['d2'], 'not off 1×');
	pg_assert_same(null, $r['d3'], 'not without a sound track');
	pg_assert_same(1, $r['list'], 'a detached sound is a valid entry of doc.sounds');
	pg_assert_same(array('mp4', 'webm', ''), $r['type'], 'the video types by name');
}

// The server tells a video by its bytes and lists videos as it lists sounds.
function test_image_editor_clip_server()
{
	pg_assert_same('mp4', pg_image_editor_clip_format("\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2"), 'an ftyp box first');
	pg_assert_same('webm', pg_image_editor_clip_format("\x1a\x45\xdf\xa3\x9f\x42\x86\x81\x01\x42\x82\x84webm"), 'EBML with the webm doctype');
	pg_assert_same('', pg_image_editor_clip_format('GIF89a'), 'an animation is not a video to add');
	pg_assert_same('', pg_image_editor_clip_format("\x1a\x45\xdf\xa3\x9f\x42\x82\x88matroska"), 'plain Matroska is refused');
	pg_assert_same('', pg_image_editor_clip_format(''), 'nothing');

	$rows = array(
		array('id' => 1, 'name' => 'a.mp4', 'type' => 'mp4', 'folder' => 2, 'size' => 10),
		array('id' => 2, 'name' => 'b.mp3', 'type' => 'mp3', 'folder' => 2, 'size' => 10),
		array('id' => 3, 'name' => 'c.webm', 'type' => 'WEBM', 'folder' => 5, 'size' => 10));

	pg_assert_same(array(1, 3), array_map(function ($f) { return $f['id']; }, pg_image_editor_sound_rows($rows, null, pg_image_editor_clip_types())), 'videos only');
	pg_assert_same(array(2), array_map(function ($f) { return $f['id']; }, pg_image_editor_sound_rows($rows, null)), 'sounds by default');
	pg_assert_contains("type IN ('mp4', 'm4v', 'webm')", pg_image_editor_sound_where('', null, array(), true, pg_image_editor_clip_types()), 'the video types');
	pg_assert_contains("'mp3'", pg_image_editor_sound_where('', null, array(), true), 'the sound types by default');
}
