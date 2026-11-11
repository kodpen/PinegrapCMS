<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for layers over a video in the image editor: a
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

// A layer with its own end: the exit ends there, nothing is drawn after it,
// and a document grown or cut by a clip edit leaves it in place; an exit
// counted back from the end still moves with the end.
function test_image_editor_clip_over_layer_end()
{
	$r = image_editor_clip_over_node(
		'var own = { id: "t", type: "text", w: 100, h: 40, anim: { "in": { kind: "fade", ms: 400, delay: 1200 }, out: { kind: "fade", ms: 500, at: 5000 } } };' . "\n"
		. 'var rel = { id: "r", type: "text", w: 100, h: 40, anim: { out: { kind: "fade", ms: 500, delay: 1000 } } };' . "\n"
		. 'var sp = function (l, end) { var s = M.motionSpans(l, end); return [s.outStart, s.outEnd]; };' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. ' own: [sp(own, 12000), sp(own, 9000), sp(own, 4000)], rel: [sp(rel, 12000), sp(rel, 9000)],' . "\n"
		. ' shown: [M.motionAt(own, 3000, 12000).opacity, M.motionAt(own, 4750, 12000).opacity, M.motionAt(own, 5000, 12000).opacity, M.motionAt(own, 8000, 12000).opacity, M.motionAt(own, null, 12000).opacity],' . "\n"
		. ' until: [M.layerUntil(own, 12000), M.layerUntil(own, 4000), M.layerUntil(rel, 12000), M.layerUntil(null, 7000)],' . "\n"
		. ' bars: M.trackBars(own, 12000),' . "\n"
		. ' drag: M.trackEdit(rel, "out", "move", -2000, 12000, { targets: [], slack: 0 }),' . "\n"
		. ' need: [M.clipMotionNeed(own), M.clipMotionNeed(rel)],' . "\n"
		. ' length: M.clipLength({ layers: [{ id: "v", type: "video", length: 20000, from: 0, to: 3000, start: 0, speed: 1 }, own] })' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array(4500, 5000), array(4500, 5000), array(3500, 4000)), $r['own'], 'its own end stays when the document grows or shrinks, inside the timeline');
	pg_assert_same(array(array(10500, 11000), array(7500, 8000)), $r['rel'], 'an exit counted back from the end moves with it');
	pg_assert_same(array(1.0, 0.5, 0.0, 0.0, 1.0), array_map(function ($n) { return round((float) $n, 2); }, $r['shown']), 'shown, leaving, gone at its end and after; at rest whole');
	pg_assert_same(array(5000, 4000, 12000, 7000), $r['until'], 'the time it is last drawn');
	pg_assert_same(array(array('phase' => 'in', 'from' => 1200, 'to' => 1600), array('phase' => 'out', 'from' => 4500, 'to' => 5000)), $r['bars'], 'its bars on the tracks');
	pg_assert_same(array('delay' => 3000, 'ms' => 500, 'at' => 9000), $r['drag'], 'a dragged exit keeps where it was dropped');
	pg_assert_same(array(5000, 1500), $r['need'], 'the document never ends before its own end');
	pg_assert_same(5000, $r['length'], 'a 3 s clip leaves the layer its 5 s');
}

// The Effects tab as screen 38: Bulanıklık, Yok, the tiles none, fade,
// slide, wipe, zoom (the scenes keep theirs), the note's two sentences,
// the slider rows apart.
function test_image_editor_clip_over_effects_tab()
{
	$r = image_editor_clip_over_node('console.log(JSON.stringify({ clip: M.CLIP_TRANSITIONS, scenes: M.SCENE_TRANSITIONS }));');

	if ($r !== null) {
		pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
		pg_assert_same(array('none', 'fade', 'slide', 'wipe', 'zoom'), $r['clip'], 'the clip panel\'s order');
		pg_assert_same(array('none', 'fade', 'slide', 'zoom', 'wipe'), $r['scenes'], 'the scenes keep theirs');
	}

	$strings = pg_image_editor_strings_clip();

	pg_assert_same(lang('Blurriness'), $strings['clipFxBlurLabel'], 'blur label');
	pg_assert_same(lang('No transition'), $strings['clipTransitionOff'], 'none tile');
	pg_assert_same(lang('Off 1× speed the clip\'s sound is muted.'), $strings['clipFxNoteSound'], 'the note\'s second sentence');
	pg_assert_same(lang('Corners'), $strings['clipCorners'], 'corners');
	pg_assert_same(lang('Border'), $strings['clipBorder'], 'border');

	$tr = json_decode((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr/b.json'), true);
	pg_assert_same('Bulanıklık', isset($tr['Blurriness']) ? $tr['Blurriness'] : null, 'Bulanıklık');
	$tr = json_decode((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr/n.json'), true);
	pg_assert_same('Yok', isset($tr['No transition']) ? $tr['No transition'] : null, 'Yok');

	$clip = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_clip.js');
	$fx = substr($clip, strpos($clip, 'Editor.prototype.clipFxPanel'), 4000);

	pg_assert_true(strpos($fx, 'M.CLIP_TRANSITIONS.forEach') !== false && strpos($fx, 'M.SCENE_TRANSITIONS') === false, 'the tiles in the clip panel\'s order');
	pg_assert_true(strpos($fx, "t('clipFxBlurLabel')") !== false && strpos($fx, "t('clipTransitionOff')") !== false, 'its labels');
	pg_assert_true(strpos($fx, "t('clipFxNote') + ' ' + t('clipFxNoteSound')") !== false, 'both sentences of the note');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_image_editor.src.css');
	pg_assert_true((bool) preg_match('/\.pgie-clipfx \{[^}]*row-gap: \d+px;/', $css), 'the slider rows apart');
}

// The stage and a save draw the corners and border from M.clipFrame(); the
// tracks' line stops at a layer's own end; the exit's delay in the panel
// takes the own end off; the kept document brings the new fields back.
function test_image_editor_clip_over_wired()
{
	$clip = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_clip.js');
	$node = substr($clip, strpos($clip, 'function clipNode('), 1500);
	$paint = substr($clip, strpos($clip, 'function clipPaint('), 2200);
	$stage = substr($clip, strpos($clip, 'function clipFrameStage('), 800);

	pg_assert_true(strpos($node, 'clipFrameStage(box, l)') !== false, 'the stage draws the frame');
	pg_assert_true(strpos($stage, 'M.clipFrame(l)') !== false && strpos($paint, 'M.clipFrame(l)') !== false, 'stage and save from the same numbers');
	pg_assert_true(strpos($paint, 'roundPath(g, -w / 2, -h / 2, w, h, f.radius)') !== false && strpos($paint, 'g.stroke()') !== false, 'a save cuts the corners and draws the border');

	$tracks = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_tracks.js');
	pg_assert_true(strpos($tracks, 'M.layerUntil(l, end)') !== false, 'the tracks\' line stops at the layer\'s end');

	$motion = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_motion.js');
	pg_assert_true(strpos($motion, "if ((phase === 'out') && (field === 'delay')) {\n                patch.at = 0;") !== false, 'the exit\'s delay counts from the end again');

	$doc = json_decode('{"v":1,"source":null,"geometry":{},"artboard":{"width":1920,"height":1080,"background":"#000"},"layers":['
		. '{"id":"v2","type":"video","fileId":3,"src":"/b.mp4","radius":24,"border":"#ffffff","x":1200,"y":600,"w":560,"h":315,"rot":0},'
		. '{"id":"t1","type":"text","text":"Hi","anim":{"in":{"kind":"fade","ms":400,"delay":1200},"out":{"kind":"fade","ms":500,"at":5000}}}]}');
	$kept = json_decode(pg_image_editor_animation_doc_json($doc, 0, 0, 'webm'), true);

	pg_assert_same(array(24, '#ffffff'), array($kept['layers'][0]['radius'], $kept['layers'][0]['border']), 'corners and border kept');
	pg_assert_same(5000, $kept['layers'][1]['anim']['out']['at'], 'a layer\'s own end kept');
}
