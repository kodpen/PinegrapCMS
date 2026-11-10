<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for find and replace in the Workspace writing boxes
 * (assets/js/workspace_editor.js). Run under Node: findFold() answers how a
 * search without "Match case" compares letters, findMatches() where a query
 * occurs, findReplace() what the text nodes hold after a replacement. The
 * rest holds the wiring: Ctrl+F only in a writing box, the tip's labelled
 * fields, its live count, Escape, one undo step and the labels sent from
 * ws_editor_js_strings().
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

// Letters as a search without "Match case" compares them, one for one.
function test_workspace_editor_find_fold()
{
	$answer = workspace_editor_link_keys_test_run(array('findFold'), 'cases.map(function (one) { return findFold(one); })', array('Kodpen', 'İSTANBUL', 'ılık', 'Iğdır', 'ÇAĞ', ''));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('kodpen', 'istanbul', 'ilik', 'iğdir', 'çağ', ''), $answer);

	foreach (array('Kodpen', 'İSTANBUL', 'ılık', 'Iğdır', 'ÇAĞ') as $i => $word) {
		pg_assert_same(mb_strlen($word, 'UTF-8'), mb_strlen($answer[$i], 'UTF-8'), $word . ' keeps its length');
	}
}

// Where a query occurs: left to right, never overlapping, case as asked.
function test_workspace_editor_find_matches()
{
	$cases = array(
		array('one two one', 'one', false),
		array('One two ONE', 'one', false),
		array('One two ONE', 'one', true),
		array('aaaa', 'aa', false),
		array('abc', '', false),
		array('abc', 'x', false),
		array('İstanbul istanbul', 'istanbul', false),
		array("one\ntwo", "e\nt", false),
	);
	$answer = workspace_editor_link_keys_test_run(array('findFold', 'findMatches'), 'cases.map(function (one) { return findMatches(one[0], one[1], one[2]); })', $cases);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(array(0, 3), array(8, 11)), $answer[0], 'both words');
	pg_assert_same(array(array(0, 3), array(8, 11)), $answer[1], 'case folded');
	pg_assert_same(array(), $answer[2], 'Match case finds none');
	pg_assert_same(array(array(0, 2), array(2, 4)), $answer[3], 'not overlapping');
	pg_assert_same(array(), $answer[4], 'an empty query finds nothing');
	pg_assert_same(array(), $answer[5], 'nothing found');
	pg_assert_same(array(array(0, 8), array(9, 17)), $answer[6], 'the dotted capital I as i');
	pg_assert_same(array(array(2, 5)), $answer[7], 'pieces are plain text to the matcher');
}

// The text nodes after a replacement: the new words in the piece a match
// starts in, the rest of the match cut from the pieces after it.
function test_workspace_editor_find_replace()
{
	$cases = array(
		// One node, two matches.
		array(array('one two one'), array(array(0, 3), array(8, 11)), '1'),
		// A match across a mark: "he" in <b>, "llo" after it.
		array(array('say he', 'llo there'), array(array(4, 9)), 'hi'),
		// A match over a whole middle node.
		array(array('a', 'bc', 'd'), array(array(0, 4)), 'X'),
		// A line break piece is never touched.
		array(array('foo', "\n", 'foo'), array(array(0, 3), array(4, 7)), 'bar'),
		// Replaced with nothing.
		array(array('x', 'yz'), array(array(1, 3)), ''),
		// Replacement longer and containing the query.
		array(array('ab ab'), array(array(0, 2), array(3, 5)), 'abab'),
	);
	$answer = workspace_editor_link_keys_test_run(array('findReplace'), 'cases.map(function (one) { return findReplace(one[0], one[1], one[2]); })', $cases);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('1 two 1'), $answer[0]);
	pg_assert_same(array('say hi', ' there'), $answer[1], 'the replacement stays where the match starts');
	pg_assert_same(array('X', '', ''), $answer[2], 'the nodes it covered are emptied');
	pg_assert_same(array('bar', "\n", 'bar'), $answer[3]);
	pg_assert_same(array('x', ''), $answer[4]);
	pg_assert_same(array('abab abab'), $answer[5], 'each match replaced once');
}

// The wiring in the editor and the labels on the screen.
function test_workspace_editor_find_wiring()
{
	$shortcut = workspace_editor_lists_test_function('shortcut');
	pg_assert_contains("key === 'f'", $shortcut, 'Ctrl+F is caught');
	pg_assert_contains('surface.wsEditor', $shortcut, 'only in a writing box; the browser\'s find elsewhere');
	pg_assert_contains('openFind(surface', $shortcut, 'it opens the find tip');

	$find = workspace_editor_lists_test_function('openFind');
	pg_assert_contains('findMatches(', $find, 'matches from findMatches()');
	pg_assert_contains('findReplace(', $find, 'replacements from findReplace()');
	pg_assert_contains('editor.change(', $find, 'a replacement is one undo step');
	pg_assert_contains("'aria-live', 'polite'", $find, 'the count is a live region');
	pg_assert_contains("'role', 'status'", $find, 'and a status');
	pg_assert_contains("setAttribute('aria-label', t('find_title'))", $find, 'the Find field is labelled');
	pg_assert_contains("setAttribute('aria-label', t('find_replace_with'))", $find, 'the Replace field is labelled');
	pg_assert_contains('caseLabel.htmlFor', $find, 'Match case has its label');
	pg_assert_contains("'Escape'", $find, 'Escape closes it');
	pg_assert_contains('surface.focus()', $find, 'the focus goes back to the box');
	pg_assert_true(strpos($find, 'execCommand') === false, 'no execCommand');
	pg_assert_true(strpos($find, 'innerHTML') === false, 'nodes only, no markup');

	$hide = workspace_editor_lists_test_function('hideTip');
	pg_assert_contains('findPaint(null, null)', $hide, 'the paint goes with the tip');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	foreach (array('find_title', 'find_replace_with', 'find_case', 'find_prev', 'find_next', 'find_close', 'find_replace', 'find_replace_all', 'find_count', 'find_none') as $key) {
		pg_assert_contains("'" . $key . "'", $screen, $key . ' is sent to the editor');
	}

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains('::highlight(ws-find)', $css, 'matches are painted');
	pg_assert_contains('::highlight(ws-find-current)', $css, 'and the current one');
}
