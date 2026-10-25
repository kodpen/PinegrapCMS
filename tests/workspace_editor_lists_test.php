<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for lists and quotes in the Workspace writing boxes
 * (assets/js/workspace_editor.js): the lines read as items (listLine(), the
 * twin of ws_list_line()), how a stored text is laid out in the box
 * (parseBlocks(), the twin of ws_text_blocks()), how the box writes a list
 * or a quote back (listText()), run under Node; and the wiring the browser
 * runs: the toolbar's buttons, format(), the keys, typed "- " / "1. " / "> ",
 * no execCommand. The behaviour in a browser was run in headless Chromium.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');

function workspace_editor_lists_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_editor.js');
}

// The text of one function of workspace_editor.js, from its line to the
// closing brace at its own indent; '' when it is not found.
function workspace_editor_lists_test_function($source, $name)
{
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// One "var NAME = ...;" of the file, a line or an object literal.
function workspace_editor_lists_test_var($source, $name)
{
	$start = strpos($source, "\n    var " . $name . ' = ');

	if ($start === false) {
		return '';
	}

	$line_end = strpos($source, "\n", (int) $start + 1);
	$line = substr($source, (int) $start, (int) $line_end - (int) $start);

	if (substr(rtrim($line), -1) !== '{') {
		return $line . "\n";
	}

	$end = strpos($source, "\n    };\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    };\n";
}

// Runs the box's pure helpers under Node on the cases given and answers
// what each printed; null when Node is not installed (the test is skipped).
// Output that is not JSON fails.
function workspace_editor_lists_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = workspace_editor_lists_test_source();
	$script = "'use strict';\n";

	foreach (array('CALC_LANGUAGES', 'CHECK', 'TITLE', 'LISTS') as $name) {
		$part = workspace_editor_lists_test_var($source, $name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	foreach (array('fenceOpen', 'fenceInfo', 'fenceClose', 'tableRow', 'tableAlignments', 'tableCells', 'startsBlock', 'listLine', 'parseBlocks', 'listText') as $name) {
		$part = workspace_editor_lists_test_function($source, $name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	// The steps start on a line of their own (a cut may end inside a comment).
	$script .= "\nvar steps = " . json_encode($steps) . ";\n"
		. "console.log(JSON.stringify(steps.map(function (step) {\n"
		. "  if (step[0] === 'line') { return listLine(step[1]); }\n"
		. "  if (step[0] === 'blocks') { return parseBlocks(step[1]); }\n"
		. "  if (step[0] === 'text') { return listText(step[1], step[2]); }\n"
		. "  return null;\n"
		. "})));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwslist');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(($printed !== '') && (json_last_error() === JSON_ERROR_NONE) && is_array($answer), 'Node printed JSON: ' . (($printed === '') ? '(nothing)' : $printed));

	return $answer;
}

// The box reads a line as the server does.
function test_workspace_editor_lists_line_twin()
{
	$lines = array('- milk', '* bread', '   - indented', '1. first', '12. twelfth', '> said', '>', '>  spaced',
		'-', '- ', '-milk', '**bold**', '- [ ] task', '- [x] done', '- [ ]', '* * *', '- - -', '2026. yılında',
		'1.5 kg', '>quoted', '>> nested', 'text - dash', '', "\t- tabbed");
	$steps = array();

	foreach ($lines as $line) {
		$steps[] = array('line', $line);
	}

	$answer = workspace_editor_lists_test_run($steps);

	if ($answer === null) {
		return;
	}

	$kinds = array('bullet' => 'bullets', 'number' => 'numbers', 'quote' => 'quote');

	foreach ($lines as $index => $line) {
		$server = ws_list_line($line);
		$expected = ($server === null) ? null : array('kind' => $kinds[$server['kind']], 'number' => $server['number'], 'text' => $server['text']);

		pg_assert_same($expected, $answer[$index], 'the same reading of "' . $line . '"');
	}
}

// A stored text is laid out in the box as the server groups it.
function test_workspace_editor_lists_blocks_twin()
{
	$texts = array(
		"Shopping:\n- milk\n* bread\nThanks",
		"1. one\n2. two\n7. three",
		"1. one\n\n2. two",
		"- a\n\ntext",
		"He came\n3. in the race",
		"> a\n>\n> b\nanswer",
		"> a\n\n> b",
		"- a\n1. b\n- c",
		"- a\n- [ ] task\n- b",
	);
	$steps = array();

	foreach ($texts as $text) {
		$steps[] = array('blocks', $text);
	}

	$answer = workspace_editor_lists_test_run($steps);

	if ($answer === null) {
		return;
	}

	$kinds = array('bullets' => 'bullet', 'numbers' => 'number', 'quote' => 'quote');

	foreach ($texts as $index => $text) {
		// The box's blocks as the server's: a line of text a line of a text
		// run; a checklist (a card in the box) is the server's main loop's,
		// read here as its lines of text.
		$box = array();

		foreach ($answer[$index] as $block) {
			if ($block['kind'] === 'list') {
				$box[] = array('kind' => $kinds[$block['list']], 'lines' => $block['items']);
				continue;
			}

			$last = count($box) - 1;
			$lines = explode("\n", $block['markup']);

			if (($last >= 0) && ($box[$last]['kind'] === 'text')) {
				$box[$last]['lines'] = array_merge($box[$last]['lines'], $lines);
			} else {
				$box[] = array('kind' => 'text', 'lines' => $lines);
			}
		}

		$server = ws_text_blocks(explode("\n", $text));

		// The server reads a checklist line as text in this function (its
		// main loop takes it first), the box as a card of its own.
		pg_assert_same($server, $box, 'the same blocks for ' . json_encode($text));
	}
}

// The box writes a list or a quote back as the markup it read.
function test_workspace_editor_lists_written_back()
{
	$answer = workspace_editor_lists_test_run(array(
		array('text', 'bullets', array('milk', '  bread  ', '')),
		array('text', 'numbers', array('one', "two\nthree", '')),
		array('text', 'quote', array('', 'a', '', 'b', '')),
		array('text', 'bullets', array('', "\n")),
		array('blocks', "- milk\n- bread"),
		array('blocks', "1. one\n2. two\n3. three"),
		array('blocks', "> a\n>\n> b"),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same("- milk\n- bread", $answer[0], 'an item a line, its words trimmed, an empty one left out');
	pg_assert_same("1. one\n2. two\n3. three", $answer[1], 'a line break in an item (a paste) is an item of its own, numbered on');
	pg_assert_same("> a\n>\n> b", $answer[2], 'an empty line of a quote between two lines with words');
	pg_assert_same('', $answer[3], 'nothing written for a list left empty');

	foreach (array(4 => "- milk\n- bread", 5 => "1. one\n2. two\n3. three", 6 => "> a\n>\n> b") as $index => $markup) {
		pg_assert_same(1, count($answer[$index]), 'one block: ' . $markup);
	}
}

function test_workspace_editor_lists_toolbar_and_format()
{
	$source = workspace_editor_lists_test_source();
	$tools = workspace_editor_lists_test_var($source, 'TOOLS');

	pg_assert_contains("bullets: { icon: 'bi-list-ul', label: 'tool_bullets', list: 'bullets' }", $tools);
	pg_assert_contains("numbers: { icon: 'bi-list-ol', label: 'tool_numbers', list: 'numbers' }", $tools);
	pg_assert_contains("quote: { icon: 'bi-quote', label: 'tool_quote', list: 'quote' }", $tools);

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	foreach (array("'tool_bullets'", "'tool_numbers'", "'tool_quote'") as $key) {
		pg_assert_contains($key, $screen, $key . ' is sent to the box');
	}

	// A list button changes the box as a step of its own, on the nodes.
	pg_assert_contains('return self.change(function () { return toggleList(self.node, name); });', $source);

	foreach (array('toggleList', 'wrapLine', 'liftItem', 'convertItem', 'itemEnter', 'joinLists', 'normaliseLists', 'isolateBreaks') as $name) {
		$body = workspace_editor_lists_test_function($source, $name);

		pg_assert_true($body !== '', $name . ' is found');
		pg_assert_true((strpos($body, 'command(') === false) && (strpos($body, 'execCommand') === false), $name . ' does not use execCommand');
	}
}

function test_workspace_editor_lists_keys()
{
	$source = workspace_editor_lists_test_source();
	$editor = substr($source, (int) strpos($source, "\n    function Editor("), 6000);
	$send = strpos($editor, 'self.options.onEnter(event);');
	$continue = strpos($editor, 'if (self.listEnter()) {');
	$break = strpos($editor, 'lineBreak();', (int) $continue);

	pg_assert_true(($send !== false) && ($continue !== false) && ($break !== false), 'all three found');
	pg_assert_true(($send < $continue) && ($continue < $break), 'Enter sends in the composer first; Shift+Enter (and Enter elsewhere) goes on with a list, else a line break');

	// "- ", "* ", "1. " and "> " typed at the start of a line, only as typing.
	$check = substr($source, (int) strpos($source, "\n        listCheck: function"), 1800);

	pg_assert_contains("event.inputType !== 'insertText'", $check);
	pg_assert_contains("/(^|\\n)([-*]|1\\.|>) $/", $check);
	pg_assert_contains('self.listCheck(event);', $editor, 'the box checks what was typed');
}
