<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Focus control of the designer's Options panel
 * (assets/js/pg_image_focus.js, window.PGImageFocus, over the _sdFocus*()
 * twins cut out of assets/js/style_designer.src.js): what it shows on each
 * screen, a value typed before kept, a move written to the screen shown,
 * the keys, the pointer, Reset and "Same on every screen", the frames'
 * shapes; and the part's wiring. Runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with the twins and window.PGImageFocus(F) as M; its
// `out` comes back decoded. Null when Node cannot run.
function image_focus_control_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, '    function _sdFocusParse(');
	$to = strpos($source, '    function _sdNoImageUrl(', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the twins');

	$script = "var window = {};\n"
		. substr($source, $from, $to - $from) . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_focus.js') . "\n"
		. "var M = window.PGImageFocus({ parse: _sdFocusParse, format: _sdFocusFormat, forScreen: _sdFocusForScreen, set: _sdFocusSet, same: _sdFocusSameEverywhere }), out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgfc');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// No focus set: the point rests in the middle, nothing is set or typed.
function test_image_focus_control_none_set()
{
	$res = image_focus_control_test_node('var v = M.view({ desktop: "", tablet: "", phone: "" }, "desktop");'
		. 'out = [v.set, v.xy, v.own, v.typed, v.any, v.frames.phone.xy];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(false, array(50, 50), false, '', false, array(50, 50)), $res);
}

// A value set on the desktop shows on every screen; each frame follows it.
function test_image_focus_control_desktop_value_everywhere()
{
	$res = image_focus_control_test_node('var vals = { desktop: "70% 32%", tablet: "", phone: "" };'
		. 'out = ["desktop", "tablet", "phone"].map(function (s) { var v = M.view(vals, s); return [v.set, v.xy, v.own]; });');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(array(true, array(70, 32), false), array(true, array(70, 32), false), array(true, array(70, 32), false)), $res);
}

// A move while the bar shows a phone is the phone's own; the wider screens
// keep theirs, the chip and the foot line name them.
function test_image_focus_control_phone_own()
{
	$res = image_focus_control_test_node('var vals = M.move({ desktop: "70% 32%", tablet: "", phone: "" }, "phone", 48.2, 30);'
		. 'var v = M.view(vals, "phone"), d = M.view(vals, "desktop");'
		. 'out = { vals: vals, own: v.own, wider: v.wider, xy: v.xy, dxy: d.xy, marks: [v.frames.phone.own, v.frames.tablet.own], same: M.same(vals) };');
	if ($res === null) {
		return;
	}
	pg_assert_same(array('desktop' => '70% 32%', 'tablet' => '', 'phone' => '48% 30%'), $res['vals'], 'written to the phone');
	pg_assert_true($res['own'], 'the phone\'s own');
	pg_assert_same(array(70, 32), $res['wider'], 'wider screens keep the desktop\'s');
	pg_assert_same(array(48, 30), $res['xy'], 'the phone shows its own');
	pg_assert_same(array(70, 32), $res['dxy'], 'the desktop is unchanged');
	pg_assert_same(array(true, false), $res['marks'], 'the phone frame\'s mark');
	pg_assert_same(array('desktop' => '70% 32%', 'tablet' => '', 'phone' => ''), $res['same'], 'Same on every screen keeps the widest');
}

// A tablet's own value is the phone's too, and its wider screen is the desktop.
function test_image_focus_control_tablet_own()
{
	$res = image_focus_control_test_node('var vals = M.move({ desktop: "10% 10%", tablet: "", phone: "" }, "tablet", 40, 60);'
		. 'var t = M.view(vals, "tablet"), p = M.view(vals, "phone");'
		. 'out = [t.own, t.wider, p.own, p.xy];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(true, array(10, 10), false, array(40, 60)), $res);
}

// A value typed before is shown where it points and kept as typed; a move
// writes whole percentages.
function test_image_focus_control_typed_before()
{
	$res = image_focus_control_test_node('var vals = { desktop: "center top", tablet: "", phone: "" };'
		. 'var v = M.view(vals, "desktop");'
		. 'out = { xy: v.xy, typed: v.typed, set: v.set, mine: M.typed("70% 32%"), moved: M.move(vals, "desktop", 50, 0) };');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(50, 0), $res['xy'], 'shown where it points');
	pg_assert_same('center top', $res['typed'], 'named as typed');
	pg_assert_true($res['set'], 'it is set');
	pg_assert_same(false, $res['mine'], 'the control\'s own form is not typed');
	pg_assert_same('50% 0%', $res['moved']['desktop'], 'a move writes percentages');
}

// The arrows move the point by 1 %, Home puts it in the middle, the edges
// hold; other keys do nothing.
function test_image_focus_control_keys()
{
	$res = image_focus_control_test_node('out = [M.key([70, 32], "ArrowLeft"), M.key([70, 32], "ArrowRight"), M.key([70, 32], "ArrowUp"),'
		. ' M.key([70, 32], "ArrowDown"), M.key([70, 32], "Home"), M.key([0, 100], "ArrowLeft"), M.key([0, 100], "ArrowDown"), M.key([70, 32], "a")];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(array(69, 32), array(71, 32), array(70, 31), array(70, 33), array(50, 50), array(0, 100), array(0, 100), null), $res);
}

// A press on the picture puts the point under the pointer, inside its edges.
function test_image_focus_control_pointer()
{
	$res = image_focus_control_test_node('var r = { left: 10, top: 20, width: 200, height: 100 };'
		. 'out = [M.fromPointer(r, 150, 52), M.fromPointer(r, 0, 500), M.fromPointer(r, 210, 20)];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(array(70, 32), array(0, 100), array(100, 0)), $res);
}

// Reset removes every screen's value.
function test_image_focus_control_reset()
{
	$res = image_focus_control_test_node('out = M.reset();');
	if ($res === null) {
		return;
	}
	pg_assert_same(array('desktop' => '', 'tablet' => '', 'phone' => ''), $res);
}

// A fixed ratio holds on every frame; else the screen shown takes the shape
// measured on the canvas.
function test_image_focus_control_frame_shapes()
{
	$res = image_focus_control_test_node('out = [M.shapes("16x9", "desktop", 3), M.shapes("", "phone", 0.6), M.shapes("", "desktop", 0), M.shapes("4 / 5", "desktop", 3), M.shapes("1.5", "phone", 0.6)];');
	if ($res === null) {
		return;
	}
	pg_assert_same(round(16 / 9, 4), round($res[0]['phone'], 4), 'a ratio on the phone');
	pg_assert_same(round(16 / 9, 4), round($res[0]['desktop'], 4), 'a ratio on the desktop');
	pg_assert_same(0.6, $res[1]['phone'], 'the phone shown is measured');
	pg_assert_same(2.4, $res[1]['desktop'], 'the desktop keeps its typical shape');
	pg_assert_same(2.4, $res[2]['desktop'], 'nothing measured');
	pg_assert_same(0.8, $res[3]['desktop'], 'an aspect-ratio pair holds on every frame');
	pg_assert_same(1.5, $res[4]['phone'], 'an aspect-ratio of one number holds too');
}

// The frames under the picture: phone and tablet share a row, the desktop
// has the next; a row's frames have one height, the largest that fits the
// width, at most 120 px. One frame when every screen is the same picture.
function test_image_focus_control_frame_sizes()
{
	$res = image_focus_control_test_node('var typ = { phone: 0.8, tablet: 4 / 3, desktop: 2.4 };'
		. 'var diff = M.view({ desktop: "70% 32%", tablet: "", phone: "48% 30%" }, "phone").frames;'
		. 'var same = M.view({ desktop: "70% 32%", tablet: "", phone: "" }, "desktop").frames;'
		. 'var sq = { phone: 1, tablet: 1, desktop: 1 };'
		. 'out = [M.sizes(288, typ, same), M.sizes(288, { phone: 0.8, tablet: 4 / 3, desktop: 3 }, same), M.sizes(200, typ, same),'
		. ' M.sizes(288, sq, same), M.sizes(100, sq, same), M.sizes(288, sq, diff), M.sizes(0, typ, same), M.sizes(288, { phone: 16 / 9, tablet: 16 / 9, desktop: 16 / 9 }, same)];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array('one' => false, 'rows' => array(
		array(array('s' => 'phone', 'w' => 96, 'h' => 120), array('s' => 'tablet', 'w' => 160, 'h' => 120)),
		array(array('s' => 'desktop', 'w' => 288, 'h' => 120)),
	)), $res[0], 'the typical shapes at 288 px reach the 120 px cap');
	pg_assert_same(array('s' => 'desktop', 'w' => 288, 'h' => 96), $res[1]['rows'][1][0], 'a 3:1 desktop takes the width');
	pg_assert_same(array(array('s' => 'phone', 'w' => 72, 'h' => 90), array('s' => 'tablet', 'w' => 120, 'h' => 90)), $res[2]['rows'][0], 'a narrow row shrinks to fit with its gap');
	pg_assert_same(array('s' => 'desktop', 'w' => 199, 'h' => 83), $res[2]['rows'][1][0], 'never wider than the control');
	pg_assert_same(array('one' => true, 'rows' => array(array(array('s' => 'every', 'w' => 140, 'h' => 140)))), $res[3], 'one shape, one focus: one frame at most 140 px tall');
	pg_assert_same(array('s' => 'every', 'w' => 100, 'h' => 100), $res[4]['rows'][0][0], 'the one frame never wider than the control');
	pg_assert_same(false, $res[5]['one'], 'a screen\'s own focus brings the three frames back');
	pg_assert_same(120, $res[6]['rows'][0][0]['h'], 'no width measured: the panel\'s 288 px');
	pg_assert_same(array('s' => 'every', 'w' => 248, 'h' => 140), $res[7]['rows'][0][0], 'a wide one frame keeps the 140 px cap');
}

// The whole picture takes the control's width, at most 200 px tall.
function test_image_focus_control_whole_size()
{
	$res = image_focus_control_test_node('out = [M.whole(288, 1200, 800), M.whole(288, 600, 1200), M.whole(288, 0, 0), M.whole(288, 100, 50)];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array('w' => 288, 'h' => 192), $res[0], 'a landscape picture across the control');
	pg_assert_same(array('w' => 100, 'h' => 200), $res[1], 'a tall picture at 200 px, narrower');
	pg_assert_same(null, $res[2], 'not loaded yet');
	pg_assert_same(array('w' => 288, 'h' => 144), $res[3], 'a small picture grows to the width');
}

// The control is a part loaded on first use, drawn in the place the panel
// keeps for it; without a picture the lists stay.
function test_image_focus_control_is_a_part()
{
	$parts = pg_designer_parts();
	$bundles = pg_js_bundles();
	pg_assert_same('assets/js/style_designer_focus.min.js', isset($parts['focus']) ? $parts['focus'] : null, 'focus is a part');
	pg_assert_same(array('assets/js/pg_image_focus.js', 'assets/js/style_designer_focus.js'), $bundles['assets/js/style_designer_focus.min.js'], 'the module, then the part');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_sdPartCall('focus', 'draw', [_sdFocusHost(el, node, el.getAttribute('data-sd-focus'))])", $core, 'the core draws it through the part');
	pg_assert_contains("if (_sdFocusTakes(n, 'image')) h += _sdFocusPlace('image');", $core, 'the Image section');
	pg_assert_contains("(_sdFocusTakes(n, 'background') ? _sdFocusPlace('background') :", $core, 'the background group');
	pg_assert_contains('_sdFocusBind(selectedNode);', $core, 'the single element\'s panel binds it');
	pg_assert_true(strpos($core, 'PGImageFocus') === false, 'the module is not in the core');
}
