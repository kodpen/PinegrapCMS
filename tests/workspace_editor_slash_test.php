<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "/" list of inserts in a note's writing box
 * (assets/js/workspace_editor.js). Run under Node: slashInserts() answers
 * whether the list may open at the caret and which of the box's own tools
 * match the word typed after "/". The rest holds the wiring: only a box
 * made with slashInserts opens it, the composer's command picker is left
 * alone, the inserts go through the tools' own actions.
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

// Runs slashInserts() under Node on each [before, blocked] and answers what
// it gave; null when Node is not installed.
function workspace_editor_slash_test_run($cases, $labels)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\n";
	$part = workspace_editor_lists_test_var('SLASH');
	pg_assert_true($part !== '', 'SLASH is found');
	$script .= $part;

	foreach (array('slashFold', 'slashInserts') as $name) {
		$part = workspace_editor_lists_test_function($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	$script .= "\nvar labels = " . json_encode($labels) . ";\n"
		. "var cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (one) { return slashInserts(one[0], one[1], labels); })));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsslash');
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

function workspace_editor_slash_test_labels()
{
	return array(
		'bullets' => 'Bulleted list',
		'numbers' => 'Numbered list',
		'quote' => 'Quote',
		'checklist' => 'Checklist',
		'table' => 'Table',
		'calc' => 'Calculation',
		'codeblock' => 'Code block',
		'highlight' => 'Highlight (Ctrl+Shift+H)',
	);
}

// Where the list opens: at a line's start or after a space, never in a
// word, a path, a URL, inside code or a link (blocked), or after "//".
function test_workspace_editor_slash_where_it_opens()
{
	$cases = array(
		array('/', false),
		array("first line\n/", false),
		array('some words /', false),
		array("a chip \u{FFFC}/", false),
		array('and/or', false),
		array('/var/www', false),
		array('http://x', false),
		array('//', false),
		array('/', true),
		array('/ ', false),
		array('/table now', false),
		array('', false),
	);
	$answer = workspace_editor_slash_test_run($cases, workspace_editor_slash_test_labels());

	if ($answer === null) {
		return;
	}

	pg_assert_same(0, $answer[0]['start'], 'the slash at the start of the box');
	pg_assert_same('', $answer[0]['word'], 'nothing typed yet');
	pg_assert_same(8, count($answer[0]['names']), 'every insert offered');
	pg_assert_same(11, $answer[1]['start'], 'at the start of a line');
	pg_assert_same(11, $answer[2]['start'], 'after a space');
	pg_assert_same(8, $answer[3]['start'], 'after a chip');

	foreach (array(4, 5, 6, 8, 9, 10, 11) as $index) {
		pg_assert_same(null, $answer[$index], 'no list: ' . json_encode($cases[$index]));
	}

	pg_assert_same(array('start' => 0, 'plain' => true), $answer[7], '"//" asks for a plain slash');
}

// Which inserts match the typed word: a word of the label or the tool's
// name begins with it, in either case; nothing matching closes the list.
function test_workspace_editor_slash_filter()
{
	$labels = workspace_editor_slash_test_labels();
	$turkish = array_merge($labels, array('table' => 'Tablo', 'checklist' => 'Yapılacaklar listesi', 'bullets' => 'Madde işaretli liste', 'highlight' => 'İşaretle (Ctrl+Shift+H)'));
	$answer = workspace_editor_slash_test_run(array(
		array('/tab', false),
		array('/TAB', false),
		array('/list', false),
		array('/code', false),
		array('/calc', false),
		array('/zzz', false),
		array('/ctrl', false),
	), $labels);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('table'), $answer[0]['names'], '/tab is the table');
	pg_assert_same('tab', $answer[0]['word'], 'the typed word');
	pg_assert_same(array('table'), $answer[1]['names'], 'in either case');
	pg_assert_same(array('bullets', 'numbers', 'checklist'), $answer[2]['names'], 'a later word of the label, and the name');
	pg_assert_same(array('codeblock'), $answer[3]['names'], 'the code block');
	pg_assert_same(array('calc'), $answer[4]['names'], 'the tool name');
	pg_assert_same(null, $answer[5], 'nothing matches: no list');
	pg_assert_same(null, $answer[6], 'the shortcut in the label is no word of it');

	$answer = workspace_editor_slash_test_run(array(
		array('/tab', false),
		array('/liste', false),
		array('/işa', false),
		array('/İŞA', false),
		array('/table', false),
	), $turkish);

	pg_assert_same(array('table'), $answer[0]['names'], 'a Turkish label');
	pg_assert_same(array('bullets', 'checklist'), $answer[1]['names'], 'Turkish label words');
	pg_assert_same(array('bullets', 'highlight'), $answer[2]['names'], 'Turkish letters fold');
	pg_assert_same(array('bullets', 'highlight'), $answer[3]['names'], 'Turkish capitals fold');
	pg_assert_same(array('table'), $answer[4]['names'], 'the English name on a Turkish screen');
}

// The wiring: only a box made with slashInserts opens the list; the inserts
// are the tools' own actions; no heading or rule is offered.
function test_workspace_editor_slash_wiring()
{
	$slash = workspace_editor_lists_test_var('SLASH');

	foreach (array('bullets', 'numbers', 'quote', 'checklist', 'table', 'calc', 'codeblock', 'highlight') as $name) {
		pg_assert_contains("'" . $name . "'", $slash, $name . ' is offered');
	}

	pg_assert_true(strpos($slash, 'heading') === false, 'no heading');

	$check = workspace_editor_lists_test_method('slashCheck');
	pg_assert_contains('this.options.slashInserts', $check, 'only where asked for');
	pg_assert_contains('isComposing', $check, 'not while composing');

	$pick = workspace_editor_lists_test_method('slashPick');
	pg_assert_contains('toggleList(', $pick, 'lists through toggleList()');
	pg_assert_contains('insertFrom(', $pick, 'cards through the tools\' windows');
	pg_assert_contains('applyMark(', $pick, 'the highlight through applyMark()');
	pg_assert_true(strpos($pick, 'execCommand') === false, 'no execCommand');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_contains('slashInserts: options.slashInserts', $screen, 'richField hands the option on');
	pg_assert_true(substr_count($screen, 'slashInserts:') === 2, 'the note box alone asks for it');
}
