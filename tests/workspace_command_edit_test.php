<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that / commands stay off while the channel's writing box changes a
 * sent or a scheduled message (assets/js/workspace.js). Run under Node:
 * commandsOff() is true for a box without commands and for an edit of
 * either kind. The rest holds the wiring: the command list and the
 * argument strip ask it, and both edits close what was open.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_command_edit_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// One "function name(" of workspace.js up to the closing brace at its own
// indent.
function workspace_command_edit_test_function($name)
{
	$source = workspace_command_edit_test_source();
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// One method of the app object up to the closing brace at its own indent.
function workspace_command_edit_test_method($name)
{
	$source = workspace_command_edit_test_source();
	$start = strpos($source, "\n        " . $name . ': function (');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        },\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start);
}

function test_workspace_commands_off_rule()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$text = workspace_command_edit_test_function('commandsOff');
	pg_assert_true($text !== '', 'commandsOff is found');

	$cases = array(
		array(array(), false, 'a new message'),
		array(array('noCommands' => true), true, 'a box without commands'),
		array(array('editing' => array('id' => 5)), true, 'a sent message edited'),
		array(array('editingScheduled' => array('id' => 7)), true, 'a scheduled message edited'),
		array(array('editing' => null, 'editingScheduled' => null), false, 'an edit ended'),
	);
	$boxes = array();

	foreach ($cases as $case) {
		$boxes[] = (object) $case[0];
	}

	$script = "'use strict';\n" . $text
		. "\nvar boxes = " . json_encode($boxes) . ";\n"
		. "console.log(JSON.stringify(boxes.map(function (box) { return commandsOff(box); })));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgwscmd');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	foreach ($cases as $index => $case) {
		pg_assert_same($case[1], is_array($answer) ? $answer[$index] : null, $case[2]);
	}
}

function test_workspace_command_edit_wiring()
{
	$check = workspace_command_edit_test_method('pickerCheck');
	$argument = workspace_command_edit_test_method('commandArgument');

	pg_assert_true(strpos($check, "!commandsOff(self) && !(self.rich && self.rich.inListItem()) && (match = /^\\/") !== false, 'the command list asks commandsOff');
	pg_assert_true(strpos($argument, 'if (commandsOff(self) || !BOOT || !BOOT.commands) {') !== false, 'the argument strip asks commandsOff');

	foreach (array('startEdit', 'editScheduled') as $name) {
		$method = workspace_command_edit_test_method($name);
		pg_assert_true(strpos($method, 'self.closePicker();') !== false, $name . ' closes an open list');
		pg_assert_true(strpos($method, 'self.drawCommandStrip(null);') !== false, $name . ' clears the argument strip');
	}
}
