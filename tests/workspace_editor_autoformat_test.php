<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the autoformat of the Workspace writing boxes
 * (assets/js/workspace_editor.js). Run under Node: autoList() answers which
 * list a line's typed start opens, autoMark() which highlight a typed "=="
 * closes, held against ws_format_text() (includes/workspace/render.php) so
 * the box only makes a mark the server draws. The rest holds the wiring:
 * the input listener, the guards (paste, IME, code, the off switch), one
 * undo step, the switch in the shortcut sheet and its labels.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/workspace_editor_link_keys_test.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');

// The list a line's words before the caret open once a space ends them.
function test_workspace_editor_autoformat_list()
{
	$cases = array(
		'- ' => 'bullet', '* ' => 'bullet', "-\u{00a0}" => 'bullet', '1. ' => 'number', '> ' => 'quote', ">\u{00a0}" => 'quote',
		'-' => null, '- x' => null, '  - ' => null, '2. ' => null, '01. ' => null, '1 ' => null, '>> ' => null, '-- ' => null,
		'a - ' => null, '+ ' => null, '-  ' => null, '' => null,
	);

	$answer = workspace_editor_link_keys_test_run(array('autoList'), 'cases.map(function (line) { return autoList(line); })', array_keys($cases));

	if ($answer === null) {
		return;
	}

	$index = 0;

	foreach ($cases as $line => $kind) {
		pg_assert_same($kind, $answer[$index++], 'autoList: ' . json_encode($line));
	}
}

// A typed "==" makes a highlight only where the server draws one ending
// there, with the same words.
function test_workspace_editor_autoformat_mark_twin()
{
	$lines = array(
		'==x==', 'a ==key point==', 'a ==b== c ==d==', '==a==b==', '==a====b==', 'a == b ==', 'x === y ==', '== Title ==',
		'=====', '===x==', '==x ==', 'a==b and c==', 'a ==b == c==', '==ğüş==', 'Kira ==15.000==', '==a== ==b==', '==',
		'a=b==c==', '==a==', '_x_ ==y==', 'one ==two', 'x ==a=b==',
	);
	$cases = array();

	foreach ($lines as $line) {
		$cases[] = array($line, '');
	}

	$cases[] = array('==x==', '=');

	$answer = workspace_editor_link_keys_test_run(array('autoMark'), 'cases.map(function (one) { return autoMark(one[0], one[1]); })', $cases);

	if ($answer === null) {
		return;
	}

	foreach ($lines as $index => $line) {
		$found = $answer[$index];
		$drawn = ws_format_text($line);

		if ($found === null) {
			pg_assert_true(substr($drawn, -7) !== '</mark>', 'no mark where the server draws none at the end: ' . $line . ' → ' . $drawn);
			continue;
		}

		$tail = '<mark>' . htmlspecialchars($found['words'], ENT_QUOTES, 'UTF-8') . '</mark>';
		pg_assert_same($tail, substr($drawn, -strlen($tail)), 'the server draws the same highlight: ' . $line);
		pg_assert_same('==' . $found['words'] . '==', mb_substr($line, -$found['length']), 'length covers the pair: ' . $line);
	}

	pg_assert_same(array('length' => 5, 'words' => 'x'), $answer[0], 'a lone pair');
	pg_assert_same('key point', $answer[1]['words'], 'words with a space');
	pg_assert_same(null, $answer[3], 'the first pair of ==a==b== is the highlight, not the last');
	pg_assert_same(null, $answer[count($lines)], 'a third = after the caret makes no pair');
}

// The wiring: when it runs, what it leaves alone, one undo, the switch.
function test_workspace_editor_autoformat_wiring()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_editor.js');

	pg_assert_contains('!self.history.quiet && self.autoformat(event)', $source, 'the input listener asks the autoformat');

	$start = strpos($source, "\n        autoformat: function (event) {");
	pg_assert_true($start !== false, 'Editor.autoformat() is found');
	$method = (string) substr($source, (int) $start, (int) strpos($source, "\n        },\n", (int) $start) - (int) $start);

	pg_assert_contains("event.inputType !== 'insertText'", $method, 'only typed letters: no paste, drop or composed word');
	pg_assert_contains('event.isComposing', $method, 'not during a composition');
	pg_assert_contains('wsComposing', $method, 'not while an IME composes');
	pg_assert_contains('autoformatOn()', $method, 'the viewer\'s switch');
	pg_assert_contains('caretBlocked()', $method, 'not in code, a link, a cell or a card');
	pg_assert_contains('inListItem()', $method, 'not inside a list already');
	pg_assert_contains("markOf(caret, self.node, 'highlight')", $method, 'not inside a highlight');
	pg_assert_contains('self.change(', $method, 'the typed text is a step, the format another');
	pg_assert_contains('toggleList(', $method, 'lists are made as the tools make them');
	pg_assert_contains("toggleWaiting(self.node, selectionRange(), 'highlight')", $method, 'letters typed next go after the highlight');
	pg_assert_true(strpos($method, 'execCommand') === false, 'no execCommand');

	$on = workspace_editor_lists_test_function('autoformatOn');
	pg_assert_contains('AUTOFORMAT_KEY', $on, 'the switch is kept in this browser');
	pg_assert_contains("'ws-autoformat'", $source, 'under its own key');

	$sheet = workspace_editor_lists_test_function('openKeys');
	pg_assert_contains("'switch'", $sheet, 'the sheet draws the switch');
	pg_assert_contains('autoformatSet(input.checked)', $sheet, 'which saves the choice');

	$typed = workspace_editor_lists_test_function('typeMarked');
	pg_assert_contains("text.replace(/^ | $/g, '\\u00a0')", $typed, 'a space typed out of a mark is kept');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	foreach (array('keys_autoformat', 'keys_autoformat_help') as $key) {
		pg_assert_contains("'" . $key . "'", $screen, $key . ' is sent to the editor');
	}
}
