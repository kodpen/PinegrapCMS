<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for when the @ and # lists of the Workspace writing boxes close
 * after their box loses its focus (assets/js/workspace.js). Run under
 * Node: pickerStaysOpen() keeps the list while a press that began inside
 * it is under way and while the focus is in the box or in the list. The
 * rest holds the wiring: the list's root keeps the focus in the box for
 * every press, and both boxes close through blurPicker().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_picker_blur_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// One "function name(" of workspace.js up to the closing brace at its own
// indent.
function workspace_picker_blur_test_function($name)
{
	$source = workspace_picker_blur_test_source();
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// One method of the app object up to the closing brace at its own indent.
function workspace_picker_blur_test_method($name)
{
	$source = workspace_picker_blur_test_source();
	$start = strpos($source, "\n        " . $name . ': function (');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        },\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start);
}

// A press inside the list keeps it open whatever has the focus; without a
// press, only the focus in the box or in the list keeps it.
function test_workspace_picker_stays_open_rule()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$text = workspace_picker_blur_test_function('pickerStaysOpen');
	pg_assert_true($text !== '', 'pickerStaysOpen is found');

	$cases = array(
		array(true, 'outside', true),
		array(true, 'none', true),
		array(true, 'picker', true),
		array(false, 'box', true),
		array(false, 'picker', true),
		array(false, 'outside', false),
		array(false, 'none', false),
	);
	$script = "'use strict';\n" . $text
		. "\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (one) { return pickerStaysOpen(one[0], one[1]); })));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgwsblur');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	foreach ($cases as $index => $case) {
		pg_assert_same($case[2], is_array($answer) ? $answer[$index] : null, 'press ' . ($case[0] ? 'inside' : 'none') . ', focus ' . $case[1]);
	}
}

// The list's root keeps the focus in the box for a press on its scrollbar,
// a gap or its padding, and notes the press; the plain and the formatted
// box close it through blurPicker(), never by a bare timer.
function test_workspace_picker_blur_wiring()
{
	$source = workspace_picker_blur_test_source();
	$open = workspace_picker_blur_test_method('openPicker');

	pg_assert_true(preg_match("/self\\.picker\\.addEventListener\\('mousedown', function \\(event\\) \\{\\s*event\\.preventDefault\\(\\);/", $open) === 1, 'the list root prevents the focus move');
	pg_assert_true(strpos($open, "self.picker.addEventListener('pointerdown'") !== false, 'the list root notes a press');
	pg_assert_true(strpos($source, "closePicker(); }, 150)") === false, 'no box closes the list on a bare timer');
	pg_assert_true(strpos($source, "field.blurPicker(textarea)") !== false, 'the plain box closes through blurPicker');
	pg_assert_true(strpos($source, "field.blurPicker(editor.node)") !== false, 'the formatted box closes through blurPicker');
	pg_assert_true(strpos(workspace_picker_blur_test_method('blurPicker'), 'pickerStaysOpen(') !== false, 'blurPicker asks pickerStaysOpen');
	pg_assert_same(2, substr_count($source, "'closePicker', 'blurPicker', 'pickerPressStart',"), 'both boxes borrow the blur methods');
}
