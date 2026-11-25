<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Set focus" on the designer's canvas (assets/js/pg_image_focus.js and the
 * part 'focus'): the size a picture is drawn at in its frame and whether the
 * frame cuts it, where the mode starts, a drag and the arrows moving the
 * picture under its frame, where the whole picture is drawn; and the wiring
 * of the toolbar button and the mode. Runs in Node through
 * image_focus_control_test_node(); skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// "Cover" fills the frame and cuts the longer side; a picture drawn at its
// own size is cut where it is larger; "Contain" and "Fill" show it whole.
function test_image_focus_mode_drawn_size()
{
	$res = image_focus_control_test_node('out = ['
		. 'M.drawn("cover", 600, 200, 1200, 800),'
		. 'M.drawn("cover", 300, 400, 1200, 800),'
		. 'M.drawn("none", 300, 200, 1200, 800),'
		. 'M.drawn("auto", 300, 200, 100, 80),'
		. 'M.drawn("contain", 300, 200, 1200, 800),'
		. 'M.drawn("fill", 300, 200, 1200, 800),'
		. 'M.drawn("scale-down", 300, 200, 1200, 800),'
		. 'M.drawn("cover", 300, 200, 0, 0)];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array('w' => 600, 'h' => 400, 'ox' => 0, 'oy' => 200, 'cut' => true), $res[0], 'cover, a wide frame');
	pg_assert_same(array('w' => 600, 'h' => 400, 'ox' => 300, 'oy' => 0, 'cut' => true), $res[1], 'cover, a tall frame');
	pg_assert_same(array('w' => 1200, 'h' => 800, 'ox' => 900, 'oy' => 600, 'cut' => true), $res[2], 'own size, larger');
	pg_assert_same(false, $res[3]['cut'], 'own size, smaller: not cut');
	pg_assert_same(false, $res[4]['cut'], 'contain shows it whole');
	pg_assert_same(false, $res[5]['cut'], 'fill shows it whole');
	pg_assert_same(false, $res[6]['cut'], 'scale-down shrinks it like contain');
	pg_assert_same(false, $res[7]['cut'], 'no size known yet');
}

// The mode starts at the value of the screen shown; with none, where the
// canvas draws the picture now, else in the middle.
function test_image_focus_mode_start()
{
	$res = image_focus_control_test_node('out = ['
		. 'M.start({ desktop: "70% 32%", tablet: "", phone: "" }, "phone", "0% 0%"),'
		. 'M.start({ desktop: "", tablet: "", phone: "" }, "desktop", "0% 0%"),'
		. 'M.start({ desktop: "", tablet: "", phone: "" }, "desktop", "10px 0px"),'
		. 'M.start({}, "desktop", "")];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(array(70, 32), array(0, 0), array(50, 50), array(50, 50)), $res);
}

// The picture follows the pointer: a drag to the right shows more of its
// left side. A side the frame does not cut stays; the edges hold.
function test_image_focus_mode_drag()
{
	$res = image_focus_control_test_node('var s = M.drawn("cover", 600, 200, 1200, 800);'
		. 'var t = M.drawn("cover", 300, 400, 1200, 800);'
		. 'out = [M.drag([50, 50], s, 0, 20), M.drag([50, 50], s, 0, -40), M.drag([50, 50], s, 80, 0),'
		. ' M.drag([50, 50], s, 0, 500), M.drag([50, 50], t, -30, 0)];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(array(50, 40), array(50, 70), array(50, 50), array(50, 0), array(60, 50)), $res);
}

// The arrows move the picture by 1 %: the right arrow shows more of its
// left side. Other keys do nothing.
function test_image_focus_mode_arrows()
{
	$res = image_focus_control_test_node('out = [M.nudge([70, 32], "ArrowRight"), M.nudge([70, 32], "ArrowLeft"),'
		. ' M.nudge([70, 32], "ArrowDown"), M.nudge([70, 32], "ArrowUp"), M.nudge([0, 100], "ArrowRight"),'
		. ' M.nudge([0, 100], "ArrowUp"), M.nudge([70, 32], "Home")];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(array(69, 32), array(71, 32), array(70, 31), array(70, 33), array(0, 100), array(0, 100), null), $res);
}

// The whole picture is drawn so the frame shows the cut of the point.
function test_image_focus_mode_offset()
{
	$res = image_focus_control_test_node('var s = M.drawn("cover", 600, 200, 1200, 800);'
		. 'out = [M.offset([50, 50], s), M.offset([0, 100], s), M.offset([70, 25], M.drawn("none", 300, 200, 1200, 800))];');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(array('left' => 0, 'top' => -100), array('left' => 0, 'top' => -200), array('left' => -630, 'top' => -150)), $res);
}

// The button sits in the selected element's toolbar where a picture is cut
// and starts the mode through the part; the bars get no button.
function test_image_focus_mode_wiring()
{
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_focus.js');

	pg_assert_contains("focusBtn.className = 'sd-tb-focus-btn bi bi-bullseye';", $core, 'the toolbar button');
	pg_assert_contains("focusBtn.title = _sdT('Set focus');", $core, 'its tooltip');
	pg_assert_contains('var _focusKind = _sdFocusKindOf(node);', $core, 'only where a picture is cut');
	pg_assert_contains("_sdPartCall('focus', 'mode', [host]).catch(_sdPartFailed);", $core, 'the mode is the part\'s');
	pg_assert_contains('return { draw: draw, mode: mode };', $part, 'the part answers the mode');
	pg_assert_contains("_sdT('Drag the picture under its frame. Arrows move it by 1%.')", $part, 'the mode\'s line');
	pg_assert_contains("_sdT('This screen shows the whole picture.')", $part, 'an uncut screen');
	pg_assert_same(1, substr_count($core, 'sd-tb-focus-btn'), 'one button, in the element toolbar only');
}
