<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's sounds (pg_image_editor_sound.js and the
 * SOUND section of the model, run under Node): the document's sounds as the
 * editor reads them, a new sound cut to the timeline, the gain with its
 * fades, what plays from a moment, a drag on the bar, a split, the
 * waveform's peaks, and the sound files' listing (pg_image_editor_sound_*).
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
function image_editor_sound_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiesound');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'function snd(o) { return Object.assign({ id: "s1", src: "/a.mp3", name: "a.mp3", length: 20000, start: 1000, from: 2000, to: 6000, volume: 1, fadeIn: 0, fadeOut: 0, muted: false }, o || {}); }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Only the site's own addresses; numbers in range; no object, no id or
// another origin left out.
function test_image_editor_sound_list()
{
	$r = image_editor_sound_node(
		'var doc = { sounds: [snd(), snd({ id: "s2", src: "https://evil.example/x.mp3" }), snd({ id: "s3", src: "//evil.example/x.mp3" }), null, snd({ id: "" }),' . "\n"
		. ' snd({ id: "s4", src: "https://site.test/b.mp3", from: 9000, to: 4000, volume: 3, fadeIn: 99999 })] };' . "\n"
		. 'console.log(JSON.stringify({ list: M.soundList(doc, "https://site.test"), none: M.soundList({}, ""),' . "\n"
		. ' ok: [M.soundSrcOk("/f/a.mp3", ""), M.soundSrcOk("https://site.test/a.mp3", "https://site.test"), M.soundSrcOk("https://site.test.evil/a.mp3", "https://site.test"), M.soundSrcOk("/\\\\evil", ""), M.soundSrcOk("javascript:x", "")] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('s1', 's4'), array_map(function ($s) { return $s['id']; }, $r['list']), 'other origins, a protocol-relative address, no object and no id are left out');
	pg_assert_same(9000, $r['list'][1]['from'], 'the cut start stays in the file');
	pg_assert_same(9000, $r['list'][1]['to'], 'the cut end is never before its start');
	pg_assert_same(1, $r['list'][1]['volume'], 'the volume is at most 1');
	pg_assert_same(0, $r['list'][1]['fadeIn'], 'a fade is never longer than the cut');
	pg_assert_same(array(), $r['none'], 'no sounds');
	pg_assert_same(array(true, true, false, false, false), $r['ok'], 'the site\'s own addresses only');
}

// A new sound starts at the playhead, cut to what is left of the timeline,
// with an unused id.
function test_image_editor_sound_new()
{
	$r = image_editor_sound_node(
		'var f = { id: 7, name: "m.mp3", url: "/m.mp3" };' . "\n"
		. 'console.log(JSON.stringify({ a: M.soundNew({ sounds: [{ id: "s1" }] }, f, 20000, 2000, 10000), b: M.soundNew({}, f, 3000, 0, 10000), c: M.soundNew({}, f, 5000, 10000, 10000) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('s2', $r['a']['id'], 'an unused id');
	pg_assert_same(array(2000, 0, 8000), array($r['a']['start'], $r['a']['from'], $r['a']['to']), 'cut to the rest of the timeline');
	pg_assert_same(7, $r['a']['fileId'], 'the file');
	pg_assert_same(3000, $r['b']['to'], 'a short file whole');
	pg_assert_same(5000, $r['c']['to'], 'at the very end the whole file');
}

// The gain: volume, fades in and out, 0 outside or muted; what plays from
// a moment.
function test_image_editor_sound_gain_and_plan()
{
	$r = image_editor_sound_node(
		'var s = snd({ volume: 0.5, fadeIn: 1000, fadeOut: 2000 });' . "\n"
		. 'console.log(JSON.stringify({ g: [0, 1000, 1500, 2000, 3000, 4000, 5000, 6000].map(function (t) { return M.soundGain(s, t); }), m: M.soundGain(snd({ muted: true }), 2000),' . "\n"
		. ' before: M.soundPlan(s, 0), inside: M.soundPlan(s, 2500), after: M.soundPlan(s, 5000), muted: M.soundPlan(snd({ muted: true }), 0) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 0, 0.25, 0.5, 0.5, 0.25, 0, 0), $r['g'], 'silent before, rising, the volume, falling, silent after');
	pg_assert_same(0, $r['m'], 'muted');
	pg_assert_same(array('wait' => 1000, 'offset' => 2000, 'dur' => 4000), $r['before'], 'before the start: wait, then the whole cut');
	pg_assert_same(array('wait' => 0, 'offset' => 3500, 'dur' => 2500), $r['inside'], 'inside: at once, from the file\'s matching moment');
	pg_assert_same(null, $r['after'], 'nothing after the end');
	pg_assert_same(null, $r['muted'], 'nothing muted');
}

// A drag: move snaps to 0.1 s and stops at 0; a start trim keeps the rest
// in place and the file's start; an end trim stays in the file; 0.1 s stays.
function test_image_editor_sound_edit()
{
	$r = image_editor_sound_node(
		'var s = snd();' . "\n"
		. 'console.log(JSON.stringify({ move: M.soundEdit(s, "move", 1234), fine: M.soundEdit(s, "move", 1234, true), left: M.soundEdit(s, "move", -5000),' . "\n"
		. ' startIn: M.soundEdit(s, "start", 500), startOut: M.soundEdit(s, "start", -5000), startMax: M.soundEdit(s, "start", 9000),' . "\n"
		. ' endOut: M.soundEdit(s, "end", 99999), endIn: M.soundEdit(s, "end", -99999) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('start' => 2200), $r['move'], 'moved by a tenth of a second');
	pg_assert_same(array('start' => 2230), $r['fine'], 'Shift: a hundredth');
	pg_assert_same(array('start' => 0), $r['left'], 'never before 0');
	pg_assert_same(array('start' => 1500, 'from' => 2500), $r['startIn'], 'the start trimmed, the rest in place');
	pg_assert_same(array('start' => 0, 'from' => 1000), $r['startOut'], 'back to the timeline\'s start at most');
	pg_assert_same(array('start' => 4900, 'from' => 5900), $r['startMax'], '0.1 s stays');
	pg_assert_same(array('to' => 20000), $r['endOut'], 'the end stays in the file');
	pg_assert_same(array('to' => 2100), $r['endIn'], '0.1 s stays');
}

// A split at the playhead: two cuts of the same file meeting there, the
// fades kept at the outer ends; none at the edges.
function test_image_editor_sound_split()
{
	$r = image_editor_sound_node(
		'var s = snd({ fadeIn: 500, fadeOut: 800 });' . "\n"
		. 'console.log(JSON.stringify({ two: M.soundSplit(s, 3000, "s2"), edge: M.soundSplit(s, 1050, "s2"), out: M.soundSplit(s, 9000, "s2") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	$a = $r['two'][0];
	$b = $r['two'][1];
	pg_assert_same(array('s1', 1000, 2000, 4000, 500, 0), array($a['id'], $a['start'], $a['from'], $a['to'], $a['fadeIn'], $a['fadeOut']), 'the first part keeps its id and fade in');
	pg_assert_same(array('s2', 3000, 4000, 6000, 0, 800), array($b['id'], $b['start'], $b['from'], $b['to'], $b['fadeIn'], $b['fadeOut']), 'the second starts at the cut with the fade out');
	pg_assert_same(null, $r['edge'], 'not within 0.1 s of an end');
	pg_assert_same(null, $r['out'], 'not outside the sound');
}

// The waveform's peaks over a range of samples; the file types.
function test_image_editor_sound_peaks_and_types()
{
	$r = image_editor_sound_node(
		'var x = [0, 0.5, -1, 0.25, 0, 0, -0.2, 0.1];' . "\n"
		. 'console.log(JSON.stringify({ p: M.soundPeaks(x, 4), part: M.soundPeaks(x, 2, 4, 8), empty: M.soundPeaks(null, 3), types: [M.soundType("A.MP3"), M.soundType("b.ogg"), M.soundType("c.mp4"), M.soundType("")] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0.5, 1, 0, 0.2), $r['p'], 'the loudest sample of each bin');
	pg_assert_same(array(0, 0.2), $r['part'], 'only the cut part');
	pg_assert_same(array(0, 0, 0), $r['empty'], 'nothing loaded: flat');
	pg_assert_same(array('mp3', 'ogg', '', ''), $r['types'], 'sound types only');
}

// The sound files: the sound types only, a role-3 user's folders; the
// query leaves out the Recycle Bin and ERP documents.
function test_image_editor_sound_files()
{
	$rows = array(
		array('id' => 1, 'name' => 'a.mp3', 'type' => 'mp3', 'folder' => 2, 'size' => 2048),
		array('id' => 2, 'name' => 'b.jpg', 'type' => 'jpg', 'folder' => 2, 'size' => 10),
		array('id' => 3, 'name' => 'c.WAV', 'type' => 'WAV', 'folder' => 9, 'size' => 10),
	);

	$all = pg_image_editor_sound_rows($rows, null);
	pg_assert_same(array(1, 3), array_map(function ($f) { return $f['id']; }, $all), 'sounds only, every folder above role 3');
	pg_assert_contains('a.mp3', $all[0]['url'], 'the address');
	pg_assert_same(array(1), array_map(function ($f) { return $f['id']; }, pg_image_editor_sound_rows($rows, array(2))), 'a role-3 user\'s folders only');

	$where = pg_image_editor_sound_where('', null, array(7), true);
	pg_assert_contains("'mp3'", $where, 'the sound types');
	pg_assert_contains("folder NOT IN ('7')", $where, 'the Recycle Bin is left out');
	pg_assert_contains("erp_doc_type = ''", $where, 'ERP documents are left out');
	pg_assert_contains("folder IN ('2')", pg_image_editor_sound_where('', array(2, 7), array(7), false), 'a role-3 user\'s folders');
	pg_assert_same(null, pg_image_editor_sound_where('', array(7), array(7), false), 'only the bin, no query');
}
