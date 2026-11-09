<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where the @ and # lists of the Workspace writing boxes open
 * (assets/js/workspace.js). Run under Node: pickerPlace() answers where a
 * list at the caret goes: under the caret line or over it, as wide as it
 * may be, inside the field's column when that is wide enough and always
 * inside the visible window. The rest holds the wiring: placePicker() sets
 * every edge itself, so a stylesheet rule that stretches the list from
 * edge to edge cannot reach a list at the caret.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_picker_place_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// One "function name(" of workspace.js up to the closing brace at its own
// indent.
function workspace_picker_place_test_function($name)
{
	$source = workspace_picker_place_test_source();
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// One method of the app object ("        name: function (") up to the
// closing brace at its own indent.
function workspace_picker_place_test_method($name)
{
	$source = workspace_picker_place_test_source();
	$start = strpos($source, "\n        " . $name . ': function (');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        },\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start);
}

// Runs pickerPlace() on each case under Node and answers what it gave;
// null when Node is not installed.
function workspace_picker_place_test_run($cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$text = workspace_picker_place_test_function('pickerPlace');
	pg_assert_true($text !== '', 'pickerPlace is found');

	if ($text === '') {
		return array();
	}

	$script = "'use strict';\n" . $text
		. "\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (one) { return pickerPlace(one.caret, one.field, one.view, one.size); })));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwspick');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return is_array($answer) ? $answer : array();
}

function workspace_picker_place_test_case($caret, $field, $view, $size)
{
	return array(
		'caret' => array('left' => $caret[0], 'top' => $caret[1], 'bottom' => $caret[2]),
		'field' => array('left' => $field[0], 'right' => $field[1]),
		'view' => array('left' => $view[0], 'top' => $view[1], 'right' => $view[2], 'bottom' => $view[3]),
		'size' => array('width' => $size[0], 'height' => $size[1]),
	);
}

function workspace_picker_place_test_expect($left, $top, $width, $height, $up)
{
	return array('left' => $left, 'top' => $top, 'width' => $width, 'height' => $height, 'up' => $up);
}

// The channel summary on a wide window beside the open main menu (250px,
// under the 50px top bar): the list opens under the caret at its own
// width, not stretched from the window's left edge to its right one.
function test_workspace_picker_place_at_the_caret_in_a_wide_field()
{
	$answer = workspace_picker_place_test_run(array(
		workspace_picker_place_test_case(array(400.4, 197.03, 219.03), array(266, 2184), array(0, 50, 2200, 900), array(416, 95)),
		// The caret near the field's right edge: moved left, its right
		// edge on the field's.
		workspace_picker_place_test_case(array(2100, 197, 219), array(266, 2184), array(0, 50, 2200, 900), array(416, 95)),
		// A caret rect left of the field (the field's own rect when the
		// caret gave none): never left of the field, so never under the menu.
		workspace_picker_place_test_case(array(100, 197, 219), array(266, 2184), array(0, 50, 2200, 900), array(416, 95)),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(workspace_picker_place_test_expect(400, 225, 416, 95, false), $answer[0], 'under the caret, at its own width');
	pg_assert_same(workspace_picker_place_test_expect(1768, 225, 416, 95, false), $answer[1], 'kept inside the field at its right edge');
	pg_assert_same(workspace_picker_place_test_expect(266, 225, 416, 95, false), $answer[2], 'kept inside the field at its left edge');
}

// A phone, a drawer and a window narrower than the list: the list is as
// wide as the field's column, or as the window less 8px each side.
function test_workspace_picker_place_narrow_widths()
{
	$answer = workspace_picker_place_test_run(array(
		// A phone (375px): the summary's field runs 16..359.
		workspace_picker_place_test_case(array(300, 300, 320), array(16, 359), array(0, 50, 375, 667), array(359, 200)),
		// A 300px window, the field as wide as it.
		workspace_picker_place_test_case(array(200, 100, 120), array(0, 300), array(0, 0, 300, 600), array(416, 150)),
		// A drawer at the window's right edge (1280px): the list stays in
		// the drawer's field instead of reaching over the page.
		workspace_picker_place_test_case(array(1200, 400, 420), array(900, 1248), array(0, 50, 1280, 800), array(416, 200)),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(workspace_picker_place_test_expect(16, 326, 343, 200, false), $answer[0], 'a phone: the field\'s width');
	pg_assert_same(workspace_picker_place_test_expect(8, 126, 284, 150, false), $answer[1], 'a narrow window: the window less its margins');
	pg_assert_same(workspace_picker_place_test_expect(900, 426, 348, 200, false), $answer[2], 'a drawer: inside its field');
}

// A field narrower than 15rem (240px) is no column to keep to: the list
// keeps its width and stays inside the window.
function test_workspace_picker_place_small_field_uses_the_window()
{
	$answer = workspace_picker_place_test_run(array(
		workspace_picker_place_test_case(array(1000, 400, 420), array(900, 1050), array(0, 50, 1280, 800), array(416, 200)),
		workspace_picker_place_test_case(array(20, 400, 420), array(0, 150), array(0, 50, 1280, 800), array(416, 200)),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(workspace_picker_place_test_expect(856, 426, 416, 200, false), $answer[0], 'moved left to stay in the window');
	pg_assert_same(workspace_picker_place_test_expect(20, 426, 416, 200, false), $answer[1], 'at the caret near the left edge');
}

// Over the caret line when there is no room under it, on the side with
// more room when neither fits, cut to that room; never over the top bar
// (the view starts under it) nor outside the visual viewport.
function test_workspace_picker_place_up_and_down()
{
	$answer = workspace_picker_place_test_run(array(
		// Near the window's foot: over the line, whole.
		workspace_picker_place_test_case(array(300, 700, 720), array(266, 1264), array(0, 50, 1280, 800), array(416, 288)),
		// A short window, more room under the line: under it, cut.
		workspace_picker_place_test_case(array(100, 180, 200), array(16, 784), array(0, 50, 800, 450), array(416, 288)),
		// A short window, more room over the line: over it, cut, its top
		// 8px under the top bar.
		workspace_picker_place_test_case(array(100, 330, 350), array(16, 784), array(0, 50, 800, 450), array(416, 288)),
		// A phone with the keyboard up: the visual viewport is 385px high
		// and scrolled 120px down.
		workspace_picker_place_test_case(array(50, 450, 470), array(8, 382), array(0, 120, 390, 505), array(374, 200)),
		// No room on either side: 6rem (96px) at least, kept inside the
		// window over the caret line.
		workspace_picker_place_test_case(array(100, 110, 130), array(16, 784), array(0, 50, 800, 200), array(416, 288)),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(workspace_picker_place_test_expect(300, 406, 416, 288, true), $answer[0], 'over the line at the window\'s foot');
	pg_assert_same(workspace_picker_place_test_expect(100, 206, 416, 236, false), $answer[1], 'under the line, cut to the room there');
	pg_assert_same(workspace_picker_place_test_expect(100, 58, 416, 266, true), $answer[2], 'over the line, cut to the room there');
	pg_assert_same(workspace_picker_place_test_expect(8, 244, 374, 200, true), $answer[3], 'above the on-screen keyboard');
	pg_assert_same(workspace_picker_place_test_expect(100, 96, 416, 96, false), $answer[4], 'at least 96px, inside the window');
}

// placePicker() measures the list at the stylesheet's size, asks
// pickerPlace() and sets every edge and the height itself.
function test_workspace_picker_place_wiring()
{
	$place = workspace_picker_place_test_method('placePicker');
	pg_assert_true($place !== '', 'placePicker is found');
	pg_assert_contains('pickerPlace(', $place, 'placePicker asks pickerPlace()');
	pg_assert_contains('pickerView()', $place, 'inside the part of the window that can be seen');
	pg_assert_contains("'ws-picker-caret'", $place, 'measured at the caret list\'s size');
	pg_assert_contains('style.width', $place, 'its width set');
	pg_assert_contains('style.maxHeight', $place, 'its height cut to the room');
	pg_assert_contains("style.right = 'auto'", $place, 'no right edge left to stretch it');

	$view = workspace_picker_place_test_function('pickerView');
	pg_assert_contains('visualViewport', $view, 'the on-screen keyboard counts');
	pg_assert_contains('.software-navbar', $view, 'never under the panel\'s top bar');

	// Every formatted box opens its list at the caret.
	$source = workspace_picker_place_test_source();
	$rich = substr($source, (int) strpos($source, "\n    function richField("), 6000);
	pg_assert_contains('caretPicker: true', $rich, 'richField() boxes open the list at the caret');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$start = strpos($css, "\n.ws-picker.ws-picker-caret {");
	pg_assert_true($start !== false, 'the caret list has its rule');
	$rule = substr($css, (int) $start, (int) strpos($css, '}', (int) $start) - (int) $start);
	pg_assert_contains('position: fixed;', $rule, 'fixed to the window');
	pg_assert_contains('right: auto;', $rule, 'not stretched to the right edge');
	pg_assert_contains('width: min(26rem', $rule, 'its own width');
	pg_assert_true(strpos($css, "\n.ws-note-compose .ws-picker {") < $start, 'after the box rules it overrides');
}

// A list over its box (the channel's writing box) keeps its place and gets
// no taller than the window leaves it.
function test_workspace_picker_fit_wiring()
{
	$draw = workspace_picker_place_test_method('drawPicker');
	pg_assert_contains('self.fitPicker()', $draw, 'a list over or under its box is fitted');

	$fit = workspace_picker_place_test_method('fitPicker');
	pg_assert_true($fit !== '', 'fitPicker is found');
	pg_assert_contains('pickerView()', $fit, 'inside the part of the window that can be seen');
	pg_assert_contains('style.maxHeight', $fit, 'its height cut to the room');
}
