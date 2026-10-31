<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where a link's address ends in the Workspace writing boxes
 * (assets/js/workspace_editor.js). Run under Node: inlineNodes() reads
 * [text](address), pastedUrl() a pasted address alone and cleanUrl() the
 * link tip's address. Each stops where a quote, an angle bracket or an
 * apostrophe starts, as ws_text_address_char() does on the server
 * (includes/workspace/render.php), so a Turkish suffix (kodpen.com'da) never
 * enters a link the box draws.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/workspace_editor_highlight_test.php');

// Runs the box's link readers under Node on the steps given and answers what
// each gave; null when Node is not installed. A step is ['links', line] (the
// line as text with each link written <href|words>), ['paste', text] or
// ['clean', address].
function workspace_editor_links_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	// A stand-in for the page's nodes: enough for building and reading them.
	$script = "'use strict';\n"
		. "function Node(tag) { this.nodeType = 1; this.tagName = tag.toUpperCase(); this.childNodes = []; this.attrs = {}; this.className = ''; }\n"
		. "Node.prototype.appendChild = function (child) { this.childNodes.push(child); return child; };\n"
		. "Node.prototype.setAttribute = function (name, value) { this.attrs[name] = String(value); };\n"
		. "Object.defineProperty(Node.prototype, 'href', { get: function () { return this.attrs.href; }, set: function (value) { this.attrs.href = String(value); } });\n"
		. "Object.defineProperty(Node.prototype, 'textContent', { get: function () { return this.childNodes.map(function (child) { return (child.nodeType === 3) ? child.nodeValue : child.textContent; }).join(''); },\n"
		. "  set: function (value) { this.childNodes = [{ nodeType: 3, nodeValue: String(value) }]; } });\n"
		. "var document = { createElement: function (tag) { return new Node(tag); }, createTextNode: function (value) { return { nodeType: 3, nodeValue: String(value) }; } };\n"
		. "function shown(node) { return node.childNodes.map(function (child) { if (child.nodeType === 3) { return child.nodeValue; }\n"
		. "  return (child.tagName === 'A') ? '<' + child.href + '|' + child.textContent + '>' : shown(child); }).join(''); }\n";

	$part = workspace_editor_highlight_test_var('PASTE_LINK');
	pg_assert_true($part !== '', 'PASTE_LINK is found');
	$script .= $part;

	foreach (array('el', 'cleanUrl', 'inlineNodes', 'pastedUrl') as $name) {
		$part = workspace_editor_highlight_test_function($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	$script .= "\nvar steps = " . json_encode($steps) . ";\n"
		. "console.log(JSON.stringify(steps.map(function (step) {\n"
		. "  if (step[0] === 'links') { return shown(inlineNodes(step[1], {}, el('div'))); }\n"
		. "  if (step[0] === 'paste') { return pastedUrl(step[1]); }\n"
		. "  if (step[0] === 'clean') { return cleanUrl(step[1]); }\n"
		. "  return null;\n"
		. "})));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwslinks');
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

// The source's address classes leave out the apostrophe and the quote.
function test_workspace_editor_links_source_stops_at_quotes()
{
	$source = workspace_editor_highlight_test_source();

	pg_assert_true(strpos($source, '(?:https?:\/\/|mailto:)[^\s()<>"\']{1,1000})\)') !== false, 'inlineNodes() link address stops at quotes');
	pg_assert_true(strpos($source, 'var PASTE_LINK = /^(?:https?:\/\/|mailto:)[^\s()<>"\']{1,1000}$/;') !== false, 'PASTE_LINK stops at quotes');
	pg_assert_true(strpos($source, 'return /^https?:\/\/[^\s<>"\']+$/i.test(url)') !== false, 'cleanUrl() refuses quotes');
	pg_assert_true(strpos($source, '[^\s()<>"]{1,1000}') === false, 'no address class lets the apostrophe in');
}

// The box draws [text](address) only where the server does: a suffix after
// an apostrophe or a quote keeps the whole shape as text on both sides.
function test_workspace_editor_links_turkish_suffixes()
{
	$lines = array(
		"[sipariş](https://a.test/siparis/12)'de görebilirsiniz.",
		"Ayrıntılar [burada](https://kodpen.com)'da.",
		"[see](https://a.test'da)",
		"[see](https://a.test/x'e)",
		'[see](https://a.test/x"y)',
		'[see](https://a.test/?a=1&b=2)',
	);
	$steps = array();

	foreach ($lines as $line) {
		$steps[] = array('links', $line);
	}

	$answer = workspace_editor_links_test_run($steps);

	if ($answer === null) {
		return;
	}

	pg_assert_same("<https://a.test/siparis/12|sipariş>'de görebilirsiniz.", $answer[0], "'de after a link");
	pg_assert_same("Ayrıntılar <https://kodpen.com|burada>'da.", $answer[1], "'da after a link");
	pg_assert_same("[see](https://a.test'da)", $answer[2], 'an apostrophe inside stays text');
	pg_assert_same("[see](https://a.test/x'e)", $answer[3], "'e inside stays text");
	pg_assert_same('[see](https://a.test/x"y)', $answer[4], 'a quote inside stays text');
	pg_assert_same('<https://a.test/?a=1&b=2|see>', $answer[5], '& stays in a query');

	// The server agrees: a [text](address) link only where the box draws one.
	pg_assert_true(strpos(ws_format_text($lines[0]), '<a href="https://a.test/siparis/12" target="_blank" rel="noopener noreferrer">sipariş</a>&#039;de') !== false, "server: 'de after a link");
	pg_assert_true(strpos(ws_format_text($lines[1]), '<a href="https://kodpen.com" target="_blank" rel="noopener noreferrer">burada</a>&#039;da.') !== false, "server: 'da after a link");
	pg_assert_true(strpos(ws_format_text($lines[5]), '<a href="https://a.test/?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">see</a>') !== false, 'server: & stays in a query');

	foreach (array(2, 3, 4) as $index) {
		pg_assert_true(strpos(ws_format_text($lines[$index]), '">see</a>') === false, 'server: no [text] link for ' . $lines[$index]);
	}
}

// A pasted address and the link tip's address never take a suffix along.
function test_workspace_editor_links_paste_and_tip()
{
	$answer = workspace_editor_links_test_run(array(
		array('paste', 'https://kodpen.com'),
		array('paste', "https://kodpen.com'da"),
		array('paste', 'https://a.test/?a=1&b=2'),
		array('clean', 'kodpen.com'),
		array('clean', "https://kodpen.com'da"),
		array('clean', 'https://a.test/"x"'),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same('https://kodpen.com', $answer[0], 'a plain address pastes as a link');
	pg_assert_same('', $answer[1], 'an address with a suffix pastes as text');
	pg_assert_same('https://a.test/?a=1&b=2', $answer[2], '& stays in a pasted query');
	pg_assert_same('https://kodpen.com', $answer[3], 'a bare domain gets https://');
	pg_assert_same('', $answer[4], 'the tip refuses an apostrophe');
	pg_assert_same('', $answer[5], 'the tip refuses a quote');
}
