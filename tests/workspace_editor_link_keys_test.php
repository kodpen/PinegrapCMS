<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the link tip's Text field and the Ctrl+/ shortcut sheet of the
 * Workspace writing boxes (assets/js/workspace_editor.js). Run under Node:
 * linkText() answers which words a link shows, keyLabel() how a shortcut is
 * written on a Mac and elsewhere, shortcutRows() what the sheet lists. The
 * rest holds the wiring: the tip's Text field and its change inside one undo
 * step, the sheet caught in shortcut() for every surface, its labels sent
 * from ws_editor_js_strings().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/workspace_editor_lists_test.php');

// Runs the expression given (over cases) after the named functions of the editor under
// Node and answers what it printed as JSON; null when Node is not installed.
function workspace_editor_link_keys_test_run($names, $tail, $cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\n";

	foreach ($names as $name) {
		$part = workspace_editor_lists_test_function($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	$script .= "\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(" . $tail . "));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwslink');
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

// The words a link shows: what is typed, else the address; null keeps the
// selection's or the link's own words (and their marks) as they are.
function test_workspace_editor_link_text()
{
	$cases = array(
		array('', '', 'https://kodpen.com'),
		array('Kodpen', '', 'https://kodpen.com'),
		array('  two   words ', '', 'https://kodpen.com'),
		array('', '', 'mailto:info@kodpen.com'),
		array('chosen words', 'chosen words', 'https://kodpen.com'),
		array('chosen  words ', "chosen\nwords", 'https://kodpen.com'),
		array('other words', 'chosen words', 'https://kodpen.com'),
		array('', 'chosen words', 'https://kodpen.com'),
		array("line one\nline two", '', 'https://kodpen.com'),
		array('', '   ', 'https://kodpen.com'),
	);
	$answer = workspace_editor_link_keys_test_run(array('linkText'), 'cases.map(function (one) { return linkText(one[0], one[1], one[2]); })', $cases);

	if ($answer === null) {
		return;
	}

	pg_assert_same('https://kodpen.com', $answer[0], 'nothing typed at a caret: the address');
	pg_assert_same('Kodpen', $answer[1], 'the typed words');
	pg_assert_same('two words', $answer[2], 'spaces folded and trimmed');
	pg_assert_same('info@kodpen.com', $answer[3], 'an e-mail address without mailto:');
	pg_assert_same(null, $answer[4], 'the prefilled words kept: the selection is linked as it is');
	pg_assert_same(null, $answer[5], 'the same words in other spacing are kept');
	pg_assert_same('other words', $answer[6], 'changed words replace the selection');
	pg_assert_same('https://kodpen.com', $answer[7], 'the field emptied: the address');
	pg_assert_same('line one line two', $answer[8], 'a link is one line');
	pg_assert_same('https://kodpen.com', $answer[9], 'a blank selection counts as a caret');
}

// A shortcut as the toolbar titles write it: Ctrl elsewhere, ⌘ on a Mac.
function test_workspace_editor_key_label()
{
	$cases = array(
		array('Control+B', false),
		array('Control+B', true),
		array('Control+Shift+X', true),
		array('Control+/', false),
		array('Shift+Enter', true),
		array('Enter', false),
	);
	$answer = workspace_editor_link_keys_test_run(array('keyLabel'), 'cases.map(function (one) { return keyLabel(one[0], one[1]); })', $cases);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('Ctrl+B', '⌘B', '⌘Shift+X', 'Ctrl+/', 'Shift+Enter', 'Enter'), $answer);
}

// What the sheet lists: the marks, undo and redo, Enter as the box takes it,
// and itself.
function test_workspace_editor_shortcut_rows()
{
	$answer = workspace_editor_link_keys_test_run(array('shortcutRows'), '[shortcutRows(true, true), shortcutRows(false, true), shortcutRows(false, false)]', array());

	if ($answer === null) {
		return;
	}

	$keys = function ($rows) {
		return array_map(function ($row) { return $row[0]; }, $rows);
	};

	pg_assert_same(array('Control+B', 'Control+I', 'Control+Shift+X', 'Control+Shift+H', 'Control+E', 'Control+K', 'Control+Z', 'Control+Y', 'Enter', 'Shift+Enter', 'Control+/'), $keys($answer[0]), 'the composer');
	pg_assert_same(array('Enter', 'keys_send'), $answer[0][8], 'Enter sends in the composer');
	pg_assert_same(array('Shift+Enter', 'keys_newline'), $answer[0][9], 'Shift+Enter is a new line there');
	pg_assert_same(array('Enter', 'keys_newline'), $answer[1][8], 'Enter is a new line in a note');
	pg_assert_same(array('Shift+Enter', 'keys_newline'), $answer[1][9], 'and so is Shift+Enter');
	pg_assert_same(array('Control+B', 'Control+I', 'Control+Shift+X', 'Control+Shift+H', 'Control+E', 'Control+K', 'Control+Z', 'Control+Y', 'Control+/'), $keys($answer[2]), 'a cell: no Enter rows');
	pg_assert_same(array('Control+B', 'fmt_bold'), $answer[0][0], 'the mark\'s own label');
}

// The wiring in the editor and the labels on the screen.
function test_workspace_editor_link_keys_wiring()
{
	$open = workspace_editor_lists_test_function('openLink');
	pg_assert_contains('linkText(', $open, 'the tip asks linkText() for the words');
	pg_assert_contains("t('link_text')", $open, 'the Text field');
	pg_assert_contains('anchor.textContent', $open, 'an existing link shows its words');
	pg_assert_contains('changed(surface', $open, 'one undo step');
	pg_assert_true(strpos($open, 'execCommand') === false, 'no execCommand');

	$shortcut = workspace_editor_lists_test_function('shortcut');
	pg_assert_contains("'/'", $shortcut, 'Ctrl+/ is caught');
	pg_assert_contains('openKeys(surface', $shortcut, 'it opens the sheet');

	$sheet = workspace_editor_lists_test_function('openKeys');
	pg_assert_contains('shortcutRows(', $sheet, 'the sheet lists shortcutRows()');
	pg_assert_contains('keyLabel(', $sheet, 'in the toolbar\'s symbols');
	pg_assert_contains("'Escape'", $sheet, 'Escape closes it');
	pg_assert_contains('surface.focus()', $sheet, 'the focus goes back to the surface');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	foreach (array('link_text', 'keys_title', 'keys_close', 'keys_undo', 'keys_redo', 'keys_send', 'keys_newline') as $key) {
		pg_assert_contains("'" . $key . "'", $screen, $key . ' is sent to the editor');
	}
}
