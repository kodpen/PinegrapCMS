<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's access rules in the model
 * (assets/js/pg_image_editor_model.js, run under Node): the tool letters,
 * Tab kept inside a box, the two-finger twist and the help sheet's groups.
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
		. ' uMini: M.toolForKey("u", mini), keyCrop: M.toolKey("crop", mini)' . "\n"
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
	pg_assert_same(array('keysTools', 'keysEdit', 'keysLayers', 'keysView', 'keysTouch'), $r['photo'], 'photo groups');
	pg_assert_same(array('keysTools', 'keysEdit', 'keysView', 'keysTouch'), $r['opt'], 'no layer keys for export only');
	pg_assert_same(array('tool_select', 'tool_crop', 'tool_adjust', 'tool_draw', 'tool_text', 'tool_redact', 'tool_sticker', 'tool_frame', 'tool_vector'), $r['photoTools'], 'every photo tool has a letter');
	pg_assert_same(array('tool_arrow', 'tool_rect', 'tool_text', 'tool_pen', 'tool_redact'), $r['annTools'], 'every annotate tool has a letter');
	pg_assert_same(array('touchPan', 'touchFit'), $r['optTouch'], 'no twist without layers');
	pg_assert_same(array('touchPan', 'touchFit', 'touchTwist'), $r['photoTouch'], 'twist with layers');
	pg_assert_same(array('Mod', 'Z'), $r['undoKeys'], 'undo is Mod+Z');
	pg_assert_same(array('keysEdit', 'keysView', 'keysTouch'), $r['empty'], 'no tools, no tool group');
	pg_assert_same(array('undo', 'redo', 'save', 'keyApply', 'keyEscape', 'keyHelp'), $r['photoEdit'], 'Enter applies the crop on a photo');
	pg_assert_same(array('undo', 'redo', 'save', 'keyEscape', 'keyHelp'), $r['canvasEdit'], 'no crop row without Crop');
}
