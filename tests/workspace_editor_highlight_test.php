<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for ==highlights== in the Workspace writing boxes
 * (assets/js/workspace_editor.js). Run under Node on a small stand-in for the
 * page's nodes: inlineNodes() reads a line's marks as ws_format_text() draws
 * them (includes/workspace/render.php), serializeNode() writes a <mark> back
 * as ==x==, so a stored text goes through the box unchanged, and isFormula()
 * reads a table cell as ws_calc_is_formula() does (includes/workspace/calc.php).
 * The rest holds the wiring: the tool, its label, its shortcut.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');

function workspace_editor_highlight_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_editor.js');
}

// One function of workspace_editor.js, from its line to the closing brace at
// its own indent; '' when it is not found.
function workspace_editor_highlight_test_function($name)
{
	$source = workspace_editor_highlight_test_source();
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// One "var NAME = ...;" of the file: a line, or an object literal.
function workspace_editor_highlight_test_var($name)
{
	$source = workspace_editor_highlight_test_source();
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

// Runs the box's helpers under Node on the steps given and answers what each
// gave; null when Node is not installed. A step is ['html', line] (the nodes
// inlineNodes() makes, as tags), ['back', line] (those nodes written back) or
// ['formula', cell].
function workspace_editor_highlight_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	// A stand-in for the page's nodes: enough for building and reading them.
	$script = "'use strict';\n"
		. "function Node(tag) { var self = this; this.nodeType = 1; this.tagName = tag.toUpperCase(); this.childNodes = []; this.attrs = {}; this.className = '';\n"
		. "  this.classList = { contains: function (name) { return self.className.split(' ').indexOf(name) !== -1; } }; }\n"
		. "Node.prototype.appendChild = function (child) { this.childNodes.push(child); child.parentNode = this; return child; };\n"
		. "Node.prototype.hasAttribute = function (name) { return Object.prototype.hasOwnProperty.call(this.attrs, name); };\n"
		. "Node.prototype.getAttribute = function (name) { return this.hasAttribute(name) ? this.attrs[name] : null; };\n"
		. "Node.prototype.setAttribute = function (name, value) { this.attrs[name] = String(value); };\n"
		. "Object.defineProperty(Node.prototype, 'href', { get: function () { return this.getAttribute('href'); }, set: function (value) { this.setAttribute('href', value); } });\n"
		. "Object.defineProperty(Node.prototype, 'textContent', { get: function () { return this.childNodes.map(function (child) { return (child.nodeType === 3) ? child.nodeValue : child.textContent; }).join(''); },\n"
		. "  set: function (value) { this.childNodes = []; this.appendChild(document.createTextNode(value)); } });\n"
		. "var document = { createElement: function (tag) { return new Node(tag); }, createTextNode: function (value) { return { nodeType: 3, nodeValue: String(value) }; } };\n"
		. "var NAMES = { B: 'strong', S: 'del', I: 'em', MARK: 'mark', CODE: 'code' };\n"
		. "function tags(node) { return node.childNodes.map(function (child) { if (child.nodeType === 3) { return child.nodeValue; }\n"
		. "  var name = NAMES[child.tagName] || child.tagName.toLowerCase(); return '<' + name + '>' + tags(child) + '</' + name + '>'; }).join(''); }\n";

	foreach (array('LISTS', 'BLOCKS') as $name) {
		$part = workspace_editor_highlight_test_var($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	foreach (array('el', 'cleanUrl', 'inlineNodes', 'wrapLines', 'serializeChildren', 'serializeNode', 'headingLevel', 'lineBlock', 'listKind', 'isFormula') as $name) {
		$part = workspace_editor_highlight_test_function($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	$script .= "\nvar steps = " . json_encode($steps) . ";\n"
		. "console.log(JSON.stringify(steps.map(function (step) {\n"
		. "  if (step[0] === 'html') { return tags(inlineNodes(step[1], {}, el('div'))); }\n"
		. "  if (step[0] === 'back') { return serializeChildren(inlineNodes(step[1], {}, el('div')), false); }\n"
		. "  if (step[0] === 'formula') { return isFormula(step[1]); }\n"
		. "  return null;\n"
		. "})));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsmark');
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

// Lines of text the box and the server must read alike: highlights, the old
// shapes that stay text, and highlights beside and inside the other marks.
function workspace_editor_highlight_test_lines()
{
	return array(
		'a ==key point== here', '==**both**==', '**==both==**', '`==x==`', 'a==b and c==d',
		'a == b', 'x === y', '== Title ==', '=====', '===x===', '== x', 'x ==', '==  ==', 'a ==b == c', '== a==',
		'==a====b==', '==a== ==b==', '_==x==_', '==_x_==', 'a=_x_==b==', '~~a=~~==b==', '==x===', '====x==',
		'==ğüş çöı==', '==x==y', 'Kira ==15.000== + aidat 750 =', '=A1+B1', '==A1', '= =',
	);
}

// The box draws a highlight where the server does, and nowhere else.
function test_workspace_editor_highlight_reads_as_the_server()
{
	$lines = workspace_editor_highlight_test_lines();
	$steps = array();

	foreach ($lines as $line) {
		$steps[] = array('html', $line);
	}

	$answer = workspace_editor_highlight_test_run($steps);

	if ($answer === null) {
		return;
	}

	foreach ($lines as $index => $line) {
		pg_assert_same(ws_format_text($line), $answer[$index], 'read alike: ' . $line);
	}

	pg_assert_same('a<mark>b and c</mark>d', $answer[4], 'unspaced comparisons in one line read as one highlight, as the server pins');
}

// A stored text goes through the box and comes back as it was.
function test_workspace_editor_highlight_round_trip()
{
	$lines = workspace_editor_highlight_test_lines();
	$steps = array();

	foreach ($lines as $line) {
		$steps[] = array('back', $line);
	}

	$answer = workspace_editor_highlight_test_run($steps);

	if ($answer === null) {
		return;
	}

	foreach ($lines as $index => $line) {
		pg_assert_same($line, $answer[$index], 'written back as stored: ' . $line);
	}
}

// A table cell is a formula when it starts with =, unless it starts with a
// highlight; the box and the server agree.
function test_workspace_editor_highlight_cell_formula_twin()
{
	$cells = array('=A1+B1', ' =SUM(A1:A3)', '==A1', '==x==', '==x== y', ' ==5==', '==a == b', '=', '===x===', 'x', '', 'a ==x==');
	$steps = array();

	foreach ($cells as $cell) {
		$steps[] = array('formula', $cell);
	}

	$answer = workspace_editor_highlight_test_run($steps);

	if ($answer === null) {
		return;
	}

	foreach ($cells as $index => $cell) {
		pg_assert_same(ws_calc_is_formula($cell), $answer[$index], 'read alike: "' . $cell . '"');
	}

	pg_assert_same(array(true, true, true, false, false, false, true, true, true, false, false, false), $answer, 'formulas and highlighted words');
}

// The tool, its label and shortcut, the mark it makes and keeps waiting.
function test_workspace_editor_highlight_tool()
{
	$source = workspace_editor_highlight_test_source();
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	pg_assert_contains("highlight: { tag: 'mark', match: /^MARK$/ }", workspace_editor_highlight_test_var('MARKS'), 'a mark of the box');
	pg_assert_contains("'highlight'", workspace_editor_highlight_test_var('TYPED_MARKS'), 'it waits at a caret like bold');
	pg_assert_contains("highlight: { icon: 'bi-highlighter', label: 'tool_highlight', keys: 'Control+Shift+H', mark: 'highlight' }", workspace_editor_highlight_test_var('TOOLS'), 'a tool');
	pg_assert_contains("['highlight', 'bi-highlighter', t('fmt_highlight')]", $source, 'on the selection bar');
	pg_assert_contains("(key === 'h') && event.shiftKey", workspace_editor_highlight_test_function('shortcut'), 'Ctrl+Shift+H');
	pg_assert_contains('highlight: false', workspace_editor_highlight_test_function('marksAt'), 'its pressed state');
	pg_assert_contains("'tool_highlight'            => lang('Highlight (Ctrl+Shift+H)')", $screen, 'the tool label is sent');
	pg_assert_contains("'fmt_highlight'             => lang('Highlight')", $screen, 'the bar label is sent');
}
