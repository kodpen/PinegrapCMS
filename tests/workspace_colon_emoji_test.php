<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ":" emoji suggestions of the Workspace writing boxes
 * (assets/js/workspace.js). Run under Node: colonEmojiAt() answers whether
 * the list may open at the caret, findEmoji() which emoji match the typed
 * word, colonEmojiList() puts the recent ones first. The rest holds the
 * wiring: only boxes with the emoji button open it, never in code, a link
 * or a card nor while a word is composed, and the pick is one undo step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_colon_emoji_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// One "function name(" of workspace.js up to the closing brace at its own
// indent, or one "var NAME = …;" (a line, or an object or array literal).
function workspace_colon_emoji_test_part($kind, $name)
{
	$source = workspace_colon_emoji_test_source();
	$start = strpos($source, "\n    " . $kind . ' ' . $name . (($kind === 'function') ? '(' : ' = '));

	if ($start === false) {
		return '';
	}

	if ($kind === 'function') {
		$end = strpos($source, "\n    }\n", (int) $start);

		return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
	}

	$line_end = strpos($source, "\n", (int) $start + 1);
	$line = substr($source, (int) $start, (int) $line_end - (int) $start);
	$close = substr(rtrim($line), -1);

	if (($close !== '{') && ($close !== '[')) {
		return $line . "\n";
	}

	$end = strpos($source, "\n    " . (($close === '{') ? '}' : ']') . ";\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    " . (($close === '{') ? '}' : ']') . ";\n";
}

// Runs the expression on each case under Node and answers what it gave;
// null when Node is not installed.
function workspace_colon_emoji_test_run($expression, $cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\nfunction t(key) { return key === 'emoji_people' ? 'People' : ''; }\n";

	foreach (array(array('var', 'EMOJI'), array('var', 'EMOJI_WORDS'), array('var', 'EMOJI_TABS'), array('function', 'foldText'), array('var', 'emojiIndex'), array('function', 'findEmoji'), array('function', 'colonEmojiAt'), array('function', 'colonEmojiList')) as $part) {
		$text = workspace_colon_emoji_test_part($part[0], $part[1]);
		pg_assert_true($text !== '', $part[1] . ' is found');
		$script .= $text;
	}

	$script .= "\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (one) { return " . $expression . "; })));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsemoji');
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

// Where the list may open: ":" and two letters at a line's start, after a
// space or a chip; never after a letter or digit, after "::", in a time or
// a URL, with one letter, or once a space ends the word.
function test_workspace_colon_emoji_where_it_opens()
{
	$cases = array(
		':gu',
		"first line\n:thu",
		'some words :gül',
		"a chip \u{FFFC}:fire",
		':g',
		':',
		'a:gu',
		'::gu',
		'10:30',
		'at 10:3',
		'https://x',
		'see http:ab',
		':gu ',
		':gu2',
		'',
		':GÜL',
	);
	$answer = workspace_colon_emoji_test_run('colonEmojiAt(one)', $cases);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('start' => 0, 'word' => 'gu'), $answer[0], 'at the start of the box');
	pg_assert_same(array('start' => 11, 'word' => 'thu'), $answer[1], 'at the start of a line');
	pg_assert_same(array('start' => 11, 'word' => 'gül'), $answer[2], 'after a space, Turkish letters');
	pg_assert_same(array('start' => 8, 'word' => 'fire'), $answer[3], 'after a chip');
	pg_assert_same(array('start' => 0, 'word' => 'GÜL'), $answer[15], 'capitals');

	foreach (array(4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14) as $index) {
		pg_assert_same(null, $answer[$index], 'no list: ' . json_encode($cases[$index]));
	}
}

// Which emoji match: a word of the emoji's English or Turkish words begins
// with the typed one, Turkish letters folded, in the picker's order.
function test_workspace_colon_emoji_matches()
{
	$answer = workspace_colon_emoji_test_run('findEmoji(one)', array('thu', 'gul', 'GÜL', 'kalp', 'zzzq', 'peop'));

	if ($answer === null) {
		return;
	}

	pg_assert_true(in_array('👍', $answer[0], true), ':thu finds the thumbs up');
	pg_assert_true(in_array('😄', $answer[1], true), ':gul finds gül');
	pg_assert_same($answer[1], $answer[2], 'capitals and Turkish letters fold');
	pg_assert_true(in_array('❤️', $answer[3], true), 'a Turkish word');
	pg_assert_same(array(), $answer[4], 'nothing matches');
	pg_assert_true(in_array('👍', $answer[5], true), 'the group name counts');
}

// The order: the recent ones that match first, in the recent order, then
// the rest in the picker's order; a recent one that does not match is left
// out; the list is cut at the limit.
function test_workspace_colon_emoji_recent_first()
{
	$answer = workspace_colon_emoji_test_run('colonEmojiList(one[0], one[1], one[2])', array(
		array(array('a', 'b', 'c', 'd'), array('c', 'x', 'a'), 8),
		array(array('a', 'b', 'c', 'd'), array(), 3),
		array(array('a', 'b', 'c', 'd'), array('d'), 2),
		array(array(), array('a'), 8),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('c', 'a', 'b', 'd'), $answer[0], 'recent first, the rest after');
	pg_assert_same(array('a', 'b', 'c'), $answer[1], 'cut at the limit');
	pg_assert_same(array('d', 'a'), $answer[2], 'a recent one survives the cut');
	pg_assert_same(array(), $answer[3], 'no match, no list');
}

// The wiring: the picker opens it only where the emoji button is, not
// while a word is composed nor where nothing may be inserted; the pick goes
// through the box's undo as one step and is remembered as recent.
function test_workspace_colon_emoji_wiring()
{
	$source = workspace_colon_emoji_test_source();
	$check = substr($source, (int) strpos($source, "\n        pickerCheck: function ("), 4000);
	$check = substr($check, 0, (int) strpos($check, "\n        },\n"));

	pg_assert_contains('colonEmojiAt(', $check, 'pickerCheck asks colonEmojiAt()');
	pg_assert_contains('self.colonEmoji', $check, 'only where the emoji button is');
	pg_assert_contains('wsComposing', $check, 'not while composing');
	pg_assert_contains('caretBlocked()', $check, 'not in code, a link, a cell or a card');
	pg_assert_same(2, substr_count($source, 'colonEmoji = true'), 'the composer and richField().tools() turn it on');

	$pick = substr($source, (int) strpos($source, "\n        pick: function ("), 3000);
	pg_assert_contains('rememberEmoji(', $pick, 'a pick is remembered');
	pg_assert_contains('.change(', $pick, 'one step of the box\'s undo');

	$editor = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_editor.js');
	pg_assert_contains("\n        caretBlocked: function (", $editor, 'the editor answers where nothing may be inserted');
	pg_assert_true(substr_count(substr($editor, (int) strpos($editor, "\n        slashCheck: function (")), 'this.caretBlocked(') > 0, 'the slash list asks the same');
}
