<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's access rules in the model
 * (assets/js/pg_image_editor_model.js, run under Node): the tool letters,
 * Tab kept inside a box, the two-finger twist, the help sheet's groups,
 * the timeline's keys and the long-motion warning.
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
function image_editor_access_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieaccess');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A letter picks the first of its tools the call has; a tool missing from
// the call, or an unknown letter, picks nothing.
function test_image_editor_access_tool_letters()
{
	$r = image_editor_access_node(
		'var photo = M.PROFILES.photo, mini = M.PROFILES.annotate;' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. ' v: M.toolForKey("v", photo), upper: M.toolForKey("C", photo), aPhoto: M.toolForKey("a", photo), aMini: M.toolForKey("a", mini),' . "\n"
		. ' rPhoto: M.toolForKey("r", photo), pMini: M.toolForKey("p", mini), cropMini: M.toolForKey("c", mini), unknown: M.toolForKey("z", photo),' . "\n"
		. ' none: M.toolForKey("v", null), keyText: M.toolKey("text", photo), keyRect: M.toolKey("rect", mini),' . "\n"
		. ' uMini: M.toolForKey("u", mini), keyCrop: M.toolKey("crop", mini),' . "\n"
		. ' mPhoto: M.toolForKey("m", photo), mMini: M.toolForKey("m", mini), kPhoto: M.toolForKey("k", photo)' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('select', $r['v'], 'V is Select');
	pg_assert_same('crop', $r['upper'], 'a capital letter picks the same tool');
	pg_assert_same('adjust', $r['aPhoto'], 'A is Adjust on the rail');
	pg_assert_same('arrow', $r['aMini'], 'A is Arrow on the mini bar');
	pg_assert_same('redact', $r['rPhoto'], 'R is Redact on the rail');
	pg_assert_same('pen', $r['pMini'], 'P is Pen on the mini bar');
	pg_assert_same(null, $r['cropMini'], 'a tool the call lacks is not picked');
	pg_assert_same(null, $r['unknown'], 'an unknown letter picks nothing');
	pg_assert_same('motion', $r['mPhoto'], 'M is Animate on the rail');
	pg_assert_same(null, $r['mMini'], 'the mini bar has no Animate tool');
	pg_assert_same(null, $r['kPhoto'], 'K picks no tool: the Animate tool plays and pauses with it');
	pg_assert_same(null, $r['none'], 'no tools, no pick');
	pg_assert_same('T', $r['keyText'], 'Text shows T');
	pg_assert_same('U', $r['keyRect'], 'Rectangle shows U');
	pg_assert_same('rect', $r['uMini'], 'U is Rectangle on the mini bar');
	pg_assert_same('', $r['keyCrop'], 'a tool the call lacks shows no letter');
}

// Tab goes round inside a box: from the last to the first, Shift+Tab from
// the first to the last, from outside to an end.
function test_image_editor_access_trap_focus()
{
	$r = image_editor_access_node(
		'console.log(JSON.stringify([M.trapFocus(3, 0, false), M.trapFocus(3, 2, false), M.trapFocus(3, 0, true), M.trapFocus(3, 1, true),' . "\n"
		. ' M.trapFocus(3, -1, false), M.trapFocus(3, -1, true), M.trapFocus(0, 0, false), M.trapFocus(3, 7, false)]));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(1, 0, 2, 0, 0, 2, -1, 0), $r, 'Tab steps and goes round');
}

// The twist adds the fingers' turn to the layer's angle, stays within -180
// to 180 and holds on right and half angles within 4 degrees.
function test_image_editor_access_twist_angle()
{
	$r = image_editor_access_node(
		'var d = Math.PI / 180;' . "\n"
		. 'console.log(JSON.stringify([M.twistAngle(10, 0, 20 * d), M.twistAngle(170, 0, 30 * d), M.twistAngle(0, 0, 88 * d),' . "\n"
		. ' M.twistAngle(0, 0, 47.5 * d), M.twistAngle(0, 0, 30 * d), M.twistAngle(-10, 5 * d, 1 * d), M.twistAngle(0, 0, -190 * d),' . "\n"
		. ' M.twistAngle(0, 0, 2 * d), M.twistAngle(undefined, 0, 12.34 * d)]));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(30, -160, 90, 45, 30, -14, 170, 0, 12.3), $r, 'twist angles');
}

// The help sheet lists the call's tool letters; the layer keys and the
// twist show only with a tool that makes layers.
function test_image_editor_access_key_map()
{
	$r = image_editor_access_node(
		'function titles(g) { return g.map(function (x) { return x.title; }); }' . "\n"
		. 'function labels(g, t) { var o = []; g.forEach(function (x) { if (x.title === t) { x.rows.forEach(function (r) { o.push(r.label); }); } }); return o; }' . "\n"
		. 'var photo = M.keyMap(M.PROFILES.photo), opt = M.keyMap(["export"]), ann = M.keyMap(M.PROFILES.annotate);' . "\n"
		. 'console.log(JSON.stringify({ photo: titles(photo), opt: titles(opt), photoTools: labels(photo, "keysTools"),' . "\n"
		. ' annTools: labels(ann, "keysTools"), optTouch: labels(opt, "keysTouch"), photoTouch: labels(photo, "keysTouch"),' . "\n"
		. ' undoKeys: photo[1].rows[0].keys, empty: titles(M.keyMap()), photoEdit: labels(photo, "keysEdit"), canvasEdit: labels(M.keyMap(M.PROFILES.canvas), "keysEdit") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('keysTools', 'keysEdit', 'keysLayers', 'tool_area', 'tool_puppet', 'tool_builder', 'keysTimeline', 'keysView', 'keysTouch'), $r['photo'], 'photo groups, a group per tool with keys of its own, the timeline with the Animate tool');
	pg_assert_same(array('keysTools', 'keysEdit', 'keysView', 'keysTouch'), $r['opt'], 'no layer keys for export only');
	pg_assert_same(array('tool_select', 'tool_crop', 'tool_artboard', 'tool_adjust', 'tool_draw', 'tool_text', 'tool_redact', 'tool_area', 'tool_sticker', 'tool_frame', 'tool_vector', 'tool_scissors', 'tool_warp', 'tool_puppet', 'tool_builder', 'tool_motion'), $r['photoTools'], 'every photo tool has a letter, the photo canvas, the Animate tool and the photo and vector modes included');
	pg_assert_same(array('tool_arrow', 'tool_rect', 'tool_text', 'tool_pen', 'tool_redact'), $r['annTools'], 'every annotate tool has a letter');
	pg_assert_same(array('touchPan', 'touchFit'), $r['optTouch'], 'no twist without layers');
	pg_assert_same(array('touchPan', 'touchFit', 'touchTwist'), $r['photoTouch'], 'twist with layers');
	pg_assert_same(array('Mod', 'Z'), $r['undoKeys'], 'undo is Mod+Z');
	pg_assert_same(array('keysEdit', 'keysView', 'keysTouch'), $r['empty'], 'no tools, no tool group');
	pg_assert_same(array('undo', 'redo', 'save', 'keyApply', 'keyEscape', 'keyHelp'), $r['photoEdit'], 'Enter applies the crop on a photo');
	pg_assert_same(array('undo', 'redo', 'save', 'keyEscape', 'keyHelp'), $r['canvasEdit'], 'no crop row without Crop');
}

// The arrows step the playhead by a frame (Shift: a second) on the frame or
// second grid, never past either end; Home and End jump to the ends.
function test_image_editor_access_timeline_keys()
{
	$r = image_editor_access_node(
		'function k(now, key, shift, end, fps) { return M.timelineKey(now, key, shift, end, fps); }' . "\n"
		. 'var walk = [0], i; for (i = 0; i < 4; i++) { walk.push(k(walk[walk.length - 1], "ArrowRight", false, 5000)); }' . "\n"
		. 'var back = [133], j; for (j = 0; j < 4; j++) { back.push(k(back[back.length - 1], "ArrowLeft", false, 5000)); }' . "\n"
		. 'console.log(JSON.stringify({ fps: M.TIMELINE_FPS, walk: walk, back: back, between: k(50, "ArrowRight", false, 5000), betweenBack: k(50, "ArrowLeft", false, 5000),' . "\n"
		. ' sec: k(1000, "ArrowRight", true, 5000), secMid: k(1250, "ArrowRight", true, 5000), secBack: k(1250, "ArrowLeft", true, 5000), secBackOn: k(2000, "ArrowLeft", true, 5000),' . "\n"
		. ' atEnd: k(5000, "ArrowRight", false, 5000), nearEnd: k(4900, "ArrowRight", true, 5000), atStart: k(0, "ArrowLeft", true, 5000),' . "\n"
		. ' home: k(2400, "Home", false, 5000), end: k(2400, "End", true, 5000), up: k(100, "ArrowUp", false, 5000), letter: k(100, "j", false, 5000),' . "\n"
		. ' fps10: k(0, "ArrowRight", false, 5000, 10), past: k(9000, "ArrowLeft", false, 5000), nothing: k(null, "ArrowRight", false, 5000) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(30, $r['fps'], 'thirty frames a second');
	pg_assert_same(array(0, 33, 67, 100, 133), $r['walk'], 'right steps one frame each, a rounded frame never repeats');
	pg_assert_same(array(133, 100, 67, 33, 0), $r['back'], 'left steps back one frame each');
	pg_assert_same(67, $r['between'], 'between frames, right goes to the next frame');
	pg_assert_same(33, $r['betweenBack'], 'between frames, left goes to the previous frame');
	pg_assert_same(2000, $r['sec'], 'Shift+right on a second goes to the next');
	pg_assert_same(2000, $r['secMid'], 'Shift+right inside a second goes to its end');
	pg_assert_same(1000, $r['secBack'], 'Shift+left inside a second goes to its start');
	pg_assert_same(1000, $r['secBackOn'], 'Shift+left on a second goes to the one before');
	pg_assert_same(5000, $r['atEnd'], 'not past the end');
	pg_assert_same(5000, $r['nearEnd'], 'a second near the end stops there');
	pg_assert_same(0, $r['atStart'], 'not before the start');
	pg_assert_same(0, $r['home'], 'Home to the start');
	pg_assert_same(5000, $r['end'], 'End to the end');
	pg_assert_same(null, $r['up'], 'up is not a timeline key');
	pg_assert_same(null, $r['letter'], 'a letter is not a timeline key');
	pg_assert_same(100, $r['fps10'], 'another frame rate');
	pg_assert_same(4967, $r['past'], 'a playhead past the end steps back from the end');
	pg_assert_same(33, $r['nothing'], 'no playhead counts as the start');
}

// Longer than 5 seconds of motion, or a repeat forever, asks for a way to
// pause it (WCAG 2.2.2); nothing to play asks nothing.
function test_image_editor_access_motion_long()
{
	$r = image_editor_access_node(
		'console.log(JSON.stringify({ forever: M.motionLong(3000, 0), once: M.motionLong(5000, 1), onceLong: M.motionLong(5100, 1),' . "\n"
		. ' twice: M.motionLong(2500, 2), thrice: M.motionLong(2000, 3), empty: M.motionLong(0, 0), junk: M.motionLong("x", "y") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['forever'], 'a repeat forever is long');
	pg_assert_false($r['once'], 'exactly 5 seconds once is not');
	pg_assert_true($r['onceLong'], 'over 5 seconds once is');
	pg_assert_false($r['twice'], 'twice 2.5 seconds is 5 seconds');
	pg_assert_true($r['thrice'], 'three times 2 seconds is 6 seconds');
	pg_assert_false($r['empty'], 'nothing to play');
	pg_assert_false($r['junk'], 'no numbers, no warning');
}

// The modes of the Photo and Vector rail items have letters of their own,
// none taken from another tool or from Animate's K.
function test_image_editor_access_mode_letters()
{
	$r = image_editor_access_node(
		'var photo = M.PROFILES.photo, canvas = M.PROFILES.canvas, seen = {}, clash = [];' . "\n"
		. 'Object.keys(M.KEY_TOOLS).forEach(function (k) { M.KEY_TOOLS[k].forEach(function (id) { if (seen[id]) { clash.push(id); } seen[id] = k; }); });' . "\n"
		. 'console.log(JSON.stringify({ q: M.toolForKey("q", photo), x: M.toolForKey("x", canvas), g: M.toolForKey("G", photo), w: M.toolForKey("w", photo),' . "\n"
		. ' n: M.toolForKey("n", canvas), qCanvas: M.toolForKey("q", canvas), k: M.toolForKey("k", photo), keyWarp: M.toolKey("warp", photo),' . "\n"
		. ' clash: clash, letters: Object.keys(M.KEY_TOOLS).length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('area', $r['q'], 'Q is Select area');
	pg_assert_same('scissors', $r['x'], 'X is Scissors');
	pg_assert_same('builder', $r['g'], 'G is Shape builder');
	pg_assert_same('warp', $r['w'], 'W is Warp');
	pg_assert_same('puppet', $r['n'], 'N is Free deformation');
	pg_assert_same(null, $r['qCanvas'], 'the canvas profile has no Select area');
	pg_assert_same(null, $r['k'], 'K stays with Animate');
	pg_assert_same('W', $r['keyWarp'], 'Warp shows W');
	pg_assert_same(array(), $r['clash'], 'no tool has two letters');
	pg_assert_same(21, $r['letters'], 'twenty-one letters');
}

// A tool's own keys show only with that tool; a finger reaches 22 px, a
// mouse the tool's reach.
function test_image_editor_access_tool_groups_and_reach()
{
	$r = image_editor_access_node(
		'function titles(g) { return g.map(function (x) { return x.title; }); }' . "\n"
		. 'var area = M.keyMap(["area"]), ann = M.keyMap(M.PROFILES.annotate), canvas = M.keyMap(M.PROFILES.canvas);' . "\n"
		. 'var a = area.filter(function (g) { return g.title === "tool_area"; })[0];' . "\n"
		. 'console.log(JSON.stringify({ area: titles(area), ann: titles(ann), canvas: titles(canvas), areaRows: a.rows.map(function (r) { return r.label; }),' . "\n"
		. ' add: a.rows[0].keys, own: a.rows !== M.KEY_MODES.area,' . "\n"
		. ' reach: [M.touchReach("touch", 10), M.touchReach("mouse", 10), M.touchReach("pen", 6), M.touchReach("touch", 30), M.touchReach(undefined, 8)] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('keysTools', 'keysEdit', 'keysLayers', 'tool_area', 'keysView', 'keysTouch'), $r['area'], 'Select area alone has its group');
	pg_assert_same(array('keysTools', 'keysEdit', 'keysLayers', 'keysView', 'keysTouch'), $r['ann'], 'the mini bar has no tool group');
	pg_assert_same(array('keysTools', 'keysEdit', 'keysLayers', 'tool_puppet', 'tool_builder', 'keysTimeline', 'keysView', 'keysTouch'), $r['canvas'], 'canvas: deformation, builder and the timeline');
	pg_assert_same(array('keyAreaAdd', 'keyAreaSubtract', 'keyAreaClose', 'touchAreaClose'), $r['areaRows'], 'area rows');
	pg_assert_same(array('Shift', 'Drag'), $r['add'], 'Shift and drag adds');
	pg_assert_true($r['own'], 'the sheet gets a copy of the rows');
	pg_assert_same(array(22, 10, 6, 30, 8), $r['reach'], 'touch reach');
}
