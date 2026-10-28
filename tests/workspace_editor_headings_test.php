<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for headings and rules in a note's writing box
 * (assets/js/workspace_editor.js). Run under Node: headingLine() and
 * ruleLine() held against ws_heading_line() and ws_rule_line()
 * (includes/workspace/render.php), autoHeading() for "# " typed at a line's
 * start, and parseBlocks() laying a note's text out with its headings while
 * a message's keeps them as words. The rest holds the wiring: the note's
 * box alone reads them, the tools and their labels.
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

// The box reads a heading and a rule as the server does.
function test_workspace_editor_headings_line_twin()
{
	$lines = array('# Plan', '## Two **b**', '### Three  ', "#\tTab", '#### Four', '#tag', '#', '# ', ' # indented', '\\# escaped',
		'<#order:5>', '# çay ve şeker', '##  two spaces', '', '---', '-----', '  ---  ', '--', '- - -', '***', '--- x', '|---|');
	$answer = workspace_editor_link_keys_test_run(array('headingLine', 'ruleLine'), 'cases.map(function (line) { return [headingLine(line), ruleLine(line)]; })', $lines);

	if ($answer === null) {
		return;
	}

	foreach ($lines as $index => $line) {
		pg_assert_same(ws_heading_line($line), $answer[$index][0], 'headingLine: ' . json_encode($line));
		pg_assert_same(ws_rule_line($line), $answer[$index][1], 'ruleLine: ' . json_encode($line));
	}
}

function test_workspace_editor_headings_autoformat()
{
	$cases = array('# ' => 1, '## ' => 2, '### ' => 3, "#\u{00a0}" => 1, '#### ' => 0, '#' => 0, ' # ' => 0, 'a # ' => 0, '# x' => 0, '#  ' => 0, '' => 0);
	$answer = workspace_editor_link_keys_test_run(array('autoHeading'), 'cases.map(function (line) { return autoHeading(line); })', array_keys($cases));

	if ($answer === null) {
		return;
	}

	$index = 0;

	foreach ($cases as $line => $level) {
		pg_assert_same($level, $answer[$index++], 'autoHeading: ' . json_encode($line));
	}
}

// A note's text: headings and rules are blocks, and end a list's paragraph
// as on the server; a message's text keeps them as words.
function test_workspace_editor_headings_parse_blocks()
{
	$names = array('fenceOpen', 'fenceInfo', 'fenceClose', 'tableRow', 'tableAlignments', 'tableCells', 'startsBlock', 'listLine', 'textBlocks', 'parseBlocks', 'headingLine', 'ruleLine');
	$text = "Intro\n# One\n- a\n- b\n---\n```\n# code\n```\n## Two";
	$vars = '';

	foreach (array('CALC_LANGUAGES', 'CHECK', 'TITLE', 'LISTS') as $name) {
		$vars .= workspace_editor_lists_test_var($name);
	}

	// The constants parseBlocks() reads, made global (an indirect eval).
	$answer = workspace_editor_link_keys_test_run($names, '(function () { (0, eval)(' . json_encode($vars) . '); return cases.map(function (one) { return parseBlocks(one[0], one[1]).map(function (block) { return [block.kind, block.markup, block.level || 0]; }); }); })()', array(array($text, true), array($text, false)));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		array('text', 'Intro', 0),
		array('heading', 'One', 1),
		array('list', "- a\n- b", 0),
		array('rule', '---', 0),
		array('code', "```\n# code\n```", 0),
		array('heading', 'Two', 2),
	), $answer[0], 'a note');

	pg_assert_same(array('text', '# One', 0), $answer[1][1], 'a message keeps "# " as words');
	pg_assert_same(array('text', '---', 0), $answer[1][4], 'and "---"');
}

// The note's box alone reads headings; the tools, keys and labels are wired.
function test_workspace_editor_headings_wiring()
{
	$editor = workspace_editor_lists_test_source();
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$strings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	pg_assert_true(strpos($editor, 'parseBlocks(markup, !!this.options.headings)') !== false, 'a stored text is read with the box\'s own choice');
	pg_assert_true(strpos($screen, "headings: !!options.headings") !== false, 'richField() passes the choice on');
	pg_assert_true(strpos($screen, "headings: true,") !== false, 'the note\'s box makes it');
	pg_assert_true(strpos($screen, "['heading1', 'heading2', 'heading3', 'rule']") !== false, 'the note\'s row has the tools');
	pg_assert_same(1, substr_count($screen, "'heading1'"), 'no other row has them');

	foreach (array('heading1', 'heading2', 'heading3', 'rule') as $name) {
		pg_assert_true(strpos($editor, "label: 'tool_" . $name . "'") !== false, $name . ' is a tool');
		pg_assert_true(strpos($strings, "'tool_" . $name . "'") !== false, $name . ' has a label');
	}

	pg_assert_true(strpos($editor, 'level ? toggleHeading(self.node, level) : toggleList(self.node, kind)') !== false, '"# " autoformats through the same switch as lists');
	pg_assert_true(strpos($editor, 'var level = self.options.headings ? autoHeading(line) : 0;') !== false, 'in a note\'s box only');
	pg_assert_true(strpos($editor, 'self.listEnter() || self.headingEnter()') !== false, 'Enter leaves a heading for a line of words');
}
