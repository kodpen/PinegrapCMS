<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the Workspace writing boxes join same marks left side by side
 * (assets/js/workspace_editor.js, joinMarks()): letters typed into marks of
 * their own and a mark taken off part of a run both leave <i>y</i><i>def</i>,
 * which the markup writes as _y__def_ and every reader shows as underscores.
 * The behaviour itself was run in headless Chromium; these hold the wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of one function of workspace_editor.js, from its line to the
// closing brace at its own indent.
function workspace_editor_join_marks_test_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_editor.js');
	$start = strpos($source, "\n    function " . $name . '(');

	pg_assert_true($start !== false, $name . ' is found');

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start);
}

function test_workspace_editor_join_marks_walks_every_mark()
{
	$body = workspace_editor_join_marks_test_function('joinMarks');

	pg_assert_contains('child = joinMark(child);', $body, 'the mark left after a join is walked on');
	pg_assert_contains('joinMarks(child);', $body, 'inner marks too');
	pg_assert_contains('return mark;', workspace_editor_join_marks_test_function('joinMark'), 'joinMark answers the mark left');
}

// Typed letters: joined before the caret is put back after them.
function test_workspace_editor_join_marks_after_typed_letters()
{
	$body = workspace_editor_join_marks_test_function('typeMarked');
	$inserted = strpos($body, 'spot.insertNode(top);');
	$joined = strpos($body, 'joinMarks(surface);');
	$selected = strpos($body, 'select(typed, typed.nodeValue.length');

	pg_assert_true(($inserted !== false) && ($joined !== false) && ($selected !== false), 'all three found');
	pg_assert_true(($inserted < $joined) && ($joined < $selected), 'insert, join, then the caret');
}

// A mark taken off, and a link put over a selection, join what is left.
function test_workspace_editor_join_marks_after_a_mark_comes_off()
{
	$apply = workspace_editor_join_marks_test_function('applyMark');
	$lifted = strpos($apply, 'liftOut(node, mark);');
	$joined = strpos($apply, 'joinMarks(surface);');
	$on = strpos($apply, '} else {', (int) $lifted);

	pg_assert_true(($lifted !== false) && ($joined !== false) && ($lifted < $joined) && ($joined < $on), 'in the off branch, after the lift');

	$link = workspace_editor_join_marks_test_function('linkRange');

	pg_assert_true(strpos($link, 'joinMarks(surface);') > strpos($link, 'made.forEach(joinMark);'), 'after the link goes on');
}
