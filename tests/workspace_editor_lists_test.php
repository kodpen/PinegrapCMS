<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for lists and quotes in the Workspace writing boxes
 * (assets/js/workspace_editor.js). Run under Node: listLine() and
 * textBlocks() read lines as ws_list_line() and ws_text_blocks() do
 * (includes/workspace/render.php), parseBlocks() lays a stored text out with
 * them, and listMarkup() writes a list back with the lines nobody touched
 * exactly as stored. The rest holds the wiring the browser runs: the tools,
 * the keys, a pick inside a mark, an emptied box.
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

// One function of workspace_editor.js, from its line to the closing brace at
// its own indent; '' when it is not found.
function workspace_editor_lists_test_function($name)
{
	$source = workspace_editor_lists_test_source();
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// One method of the Editor ("        name: function (" up to "        },").
function workspace_editor_lists_test_method($name)
{
	$source = workspace_editor_lists_test_source();
	$start = strpos($source, "\n        " . $name . ': function (');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        },\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start);
}

// One "var NAME = ...;" of the file: a line, or an object literal.
function workspace_editor_lists_test_var($name)
{
	$source = workspace_editor_lists_test_source();
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

// Runs the box's pure helpers under Node on the steps given and answers what
// each gave; null when Node is not installed.
function workspace_editor_lists_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\n";

	foreach (array('CALC_LANGUAGES', 'CHECK', 'TITLE', 'LISTS') as $name) {
		$part = workspace_editor_lists_test_var($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	foreach (array('fenceOpen', 'fenceInfo', 'fenceClose', 'tableRow', 'tableAlignments', 'tableCells', 'startsBlock', 'listLine', 'textBlocks', 'parseBlocks', 'listMarkup') as $name) {
		$part = workspace_editor_lists_test_function($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	$script .= "\nvar steps = " . json_encode($steps) . ";\n"
		. "console.log(JSON.stringify(steps.map(function (step) {\n"
		. "  if (step[0] === 'line') { return listLine(step[1]); }\n"
		. "  if (step[0] === 'groups') { return textBlocks(step[1]); }\n"
		. "  if (step[0] === 'blocks') { return parseBlocks(step[1]); }\n"
		. "  if (step[0] === 'write') { return listMarkup(step[1], step[2]); }\n"
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
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return is_array($answer) ? $answer : array();
}

// The box reads a line as the server does.
function test_workspace_editor_lists_line_twin()
{
	$lines = array('- milk', '* bread', '   - indented', '1. first', '12. twelfth', '999. last', '1000. over', '> said', '>', '>  spaced',
		'-', '- ', '-milk', '**bold**', '- [ ] task', '- [x] done', '- [ ]', '* * *', '- - -', '___', '2026. yılında',
		'1.5 kg', '>quoted', '>> nested', 'text - dash', '', "\t- tabbed", '01. Intro', '0. zero', '10. ten', '- trailing  ',
		"-\tTab", '  > indented quote', '- çay ve şeker', '1.  two spaces');
	$steps = array();

	foreach ($lines as $line) {
		$steps[] = array('line', $line);
	}

	$answer = workspace_editor_lists_test_run($steps);

	if ($answer === null) {
		return;
	}

	foreach ($lines as $index => $line) {
		$server = ws_list_line($line);
		$box = $answer[$index];

		if ($box !== null) {
			pg_assert_same($line, $box['mark'] . $box['words'], 'mark and words give the line back: ' . $line);
			$box = array('kind' => $box['kind'], 'number' => $box['number'], 'indent' => $box['indent'], 'text' => $box['text']);
		}

		pg_assert_same($server, $box, 'read alike: ' . json_encode($line));
	}
}

// The box groups lines into lists as the server does, every shape that
// stays text on the server staying text in the box.
function test_workspace_editor_lists_groups_twin()
{
	$cases = array(
		array('- a', '- b'),
		array('Shopping:', '- milk', '* bread'),
		array('- a', 'after'),
		array('- a', '  - sub', '- b'),
		array('1. one', '2. two', '3. three'),
		array('1. one', '3. three'),
		array('2. two', '3. three'),
		array('1. Lig'),
		array('- a', '1. one', '2. two'),
		array('> said', '>', '> more'),
		array('>', '>'),
		array('- a', '', '- b'),
		array('  - a', '  - b'),
		array('- a', null, '- b'),
		array('intro', 'more', '- a', '- b', '', 'end'),
		array('> a', 'b'),
	);
	$steps = array();

	foreach ($cases as $lines) {
		$steps[] = array('groups', $lines);
	}

	$answer = workspace_editor_lists_test_run($steps);

	if ($answer === null) {
		return;
	}

	foreach ($cases as $index => $lines) {
		$server = ws_text_blocks($lines);
		$box = array_map(function ($group) {
			$out = array('kind' => $group['kind'], 'lines' => $group['lines']);

			if (isset($group['items'])) {
				$out['items'] = array_map(function ($item) {
					return array('kind' => $item['kind'], 'number' => $item['number'], 'indent' => $item['indent'], 'text' => $item['text']);
				}, $group['items']);
			}

			return $out;
		}, $answer[$index]);

		pg_assert_same($server, $box, 'grouped alike: ' . json_encode($lines));
	}
}

// A stored text laid out in the box and written back unchanged: a list's
// lines keep their marks, indentation and numbers; blocks end paragraphs.
function test_workspace_editor_lists_untouched_lines_kept()
{
	$texts = array(
		"Shopping:\n* milk\n-   bread",
		"  - indented\n  - both",
		"1. one\n2.  two\n3.\tthree",
		"> said\n>\n>  more",
		"- a\n- b\n```\ncode\n```",
	);
	$steps = array();

	foreach ($texts as $text) {
		$steps[] = array('blocks', $text);
	}

	$answer = workspace_editor_lists_test_run($steps);

	if ($answer === null) {
		return;
	}

	$writes = array();
	$expected = array();

	foreach ($answer as $index => $blocks) {
		foreach ($blocks as $block) {
			if ($block['kind'] === 'list') {
				$writes[] = array('write', $block['list'], array_map(function ($item) {
					return array('mark' => $item['mark'], 'words' => $item['words']);
				}, $block['items']));
				$expected[] = $block['markup'];
			}
		}
	}

	pg_assert_same(array('text', 'list'), array_column($answer[0], 'kind'), 'lead-in, then the list');
	pg_assert_same('bullet', $answer[0][1]['list'], 'a bulleted list');
	pg_assert_same("* milk\n-   bread", $answer[0][1]['markup'], 'its lines as stored');
	pg_assert_same(array('list', 'code'), array_column($answer[4], 'kind'), 'code ends the list');

	$written = workspace_editor_lists_test_run($writes);
	pg_assert_same($expected, $written, 'written back as stored');
}

// Items the box made: the mark of the item before (its indent too), numbers
// counted again, empty items left out, a line break an item of its own.
function test_workspace_editor_lists_new_items()
{
	$answer = workspace_editor_lists_test_run(array(
		array('write', 'bullet', array(array('mark' => '  * ', 'words' => 'a'), array('mark' => '', 'words' => 'b'))),
		array('write', 'number', array(array('mark' => '1. ', 'words' => 'a'), array('mark' => '1. ', 'words' => 'new'), array('mark' => '2. ', 'words' => 'b'))),
		array('write', 'number', array(array('mark' => '', 'words' => 'a'), array('mark' => '', 'words' => ''), array('mark' => '', 'words' => 'b'))),
		array('write', 'bullet', array(array('mark' => '', 'words' => "a\nb"), array('mark' => '', 'words' => '   '))),
		array('write', 'quote', array(array('mark' => '', 'words' => ''), array('mark' => '', 'words' => 'a'), array('mark' => '', 'words' => ''), array('mark' => '', 'words' => 'b'), array('mark' => '', 'words' => ''))),
		array('write', 'bullet', array(array('mark' => '', 'words' => ''))),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same("  * a\n  * b", $answer[0], 'the indent and mark of the item before');
	pg_assert_same("1. a\n2. new\n3. b", $answer[1], 'numbered again from 1');
	pg_assert_same("1. a\n2. b", $answer[2], 'an empty item left out');
	pg_assert_same("- a\n- b", $answer[3], 'a line break makes an item');
	pg_assert_same("> a\n>\n> b", $answer[4], 'empty quote lines kept between words only');
	pg_assert_same('', $answer[5], 'nothing for an empty list');
}

function test_workspace_editor_lists_tools_and_labels()
{
	$tools = workspace_editor_lists_test_var('TOOLS');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	foreach (array('bullets', 'numbers', 'quote') as $name) {
		pg_assert_contains($name . ": { icon: '", $tools, $name . ' is a tool');
		pg_assert_contains("label: 'tool_" . $name . "', list: '", $tools, $name . ' has its label and list');
		pg_assert_contains("'tool_" . $name . "'", $screen, $name . ' label is sent');
	}

	pg_assert_contains('state[name] = ', workspace_editor_lists_test_method('formatState'), 'formatState reports the lists');
	pg_assert_contains('toggleList(', workspace_editor_lists_test_method('list'), 'the tools go through toggleList()');
}

// The keys: Enter (Shift+Enter in the composer, whose Enter sends) goes on
// in an item; Tab, Shift+Tab, Backspace at an item's start.
function test_workspace_editor_lists_keys()
{
	$source = workspace_editor_lists_test_source();
	$keys = substr($source, (int) strpos($source, "node.addEventListener('keydown'"), 2000);
	$send = strpos($keys, 'self.options.onEnter(event);');
	$enter = strpos($keys, 'self.listEnter()');

	pg_assert_true(($send !== false) && ($enter !== false) && ($send < $enter), 'Enter sends first in the composer, then the list goes on');
	pg_assert_contains('self.listKey(event)', $keys, 'Tab, Shift+Tab and Backspace');
	pg_assert_contains('itemEnter(', workspace_editor_lists_test_method('listEnter'), 'Enter in an item');

	$enter_body = workspace_editor_lists_test_function('itemEnter');
	pg_assert_contains('liftItem(', $enter_body, 'an empty item ends the list');

	foreach (array('toggleList', 'itemEnter', 'liftItem', 'toItem', 'outOfMarks') as $name) {
		pg_assert_true(strpos(workspace_editor_lists_test_function($name), 'execCommand') === false, $name . ' makes no execCommand');
	}
}

// A person or record picked inside a mark stands outside it (a chip in
// <code> was lost, in bold its mark showed as characters).
function test_workspace_editor_lists_pick_outside_marks()
{
	$body = workspace_editor_lists_test_method('replaceTyped');

	pg_assert_contains('outOfMarks(', $body, 'the marks are split at the caret');
	pg_assert_true(strpos($body, "insertHTML") === false, 'no insertHTML for the chip');
}

// Select all and delete empties the box: no empty item stays behind.
function test_workspace_editor_lists_select_all_delete()
{
	$source = workspace_editor_lists_test_source();

	pg_assert_contains('wholeBox(', $source, 'the whole box is seen');
	pg_assert_contains("ul, ol, blockquote", workspace_editor_lists_test_method('isEmpty'), 'a list made empty on purpose stays');
}

// A card put in from a list item stands below the list: insertHTML next to
// a list draws it into the last item, where it was saved as "- - [ ] one".
// Letters typed with a mark waiting right after a list that ends the box go
// into its last item, as letters without one do.
function test_workspace_editor_lists_card_after_list()
{
	$body = workspace_editor_lists_test_method('insertBlock');
	$placed = strpos($body, 'this.node.insertBefore(card, list.nextSibling)');
	$html = strpos($body, "command('insertHTML'");

	pg_assert_true($placed !== false, 'the card is put in after the list as a node');
	pg_assert_true(($html !== false) && ($placed < $html) && (strpos($body, '} else {', (int) $placed) < $html), 'insertHTML only without a list item at the caret');
	pg_assert_contains('boundaryItem(surface, range.startContainer, range.startOffset)', workspace_editor_lists_test_function('typeMarked'), 'typed marks follow a caret after a list');
}
