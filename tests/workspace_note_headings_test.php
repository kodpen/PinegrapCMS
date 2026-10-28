<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for headings ("#" to "###") and rules ("---") in Workspace notes
 * (includes/workspace/render.php): which lines are one, that a note draws
 * them and a message never does, and that the rest of a note's text is
 * drawn as before around them.
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

// The lines that are headings, and the ones that look alike but are not.
function test_workspace_note_heading_line()
{
	pg_assert_same(array('level' => 1, 'text' => 'Plan'), ws_heading_line('# Plan'), 'first level');
	pg_assert_same(array('level' => 2, 'text' => 'Plan'), ws_heading_line('## Plan  '), 'second level, trailing spaces off');
	pg_assert_same(array('level' => 3, 'text' => 'A **b**'), ws_heading_line("###\tA **b**"), 'third level, a tab, marks kept');

	foreach (array('#Plan', '#1045', '#### Deep', ' # Indented', '#', '# ', '#  ', 'a # b', '<#order:5>') as $line) {
		pg_assert_same(null, ws_heading_line($line), 'no heading: ' . $line);
	}
}

function test_workspace_note_rule_line()
{
	foreach (array('---', '----------', '  ---  ', "---\t") as $line) {
		pg_assert_true(ws_rule_line($line), 'a rule: ' . json_encode($line));
	}

	foreach (array('--', '- - -', '***', '___', '--- x', '-->', '') as $line) {
		pg_assert_true(!ws_rule_line($line), 'no rule: ' . json_encode($line));
	}
}

// A note draws them; a message, and a note drawn without the flag, never.
function test_workspace_note_headings_drawn_in_notes_only()
{
	$body = "# Plan\nWords **here**\n---\n## Next\n- a\n- b";
	$note = ws_render_body($body, array(), null, false, null, null, true);

	pg_assert_contains('<h2 class="ws-heading">Plan</h2>', $note, 'a first-level heading');
	pg_assert_contains('<hr class="ws-rule">', $note, 'a rule');
	pg_assert_contains('<h3 class="ws-heading">Next</h3>', $note, 'a second-level heading');
	pg_assert_contains('<strong>here</strong>', $note, 'the text around it as before');
	pg_assert_contains('<ul class="ws-list">', $note, 'a list right under a heading');
	pg_assert_true(strpos($note, '# Plan') === false, 'the signs are gone');

	$message = ws_render_body($body, array());
	pg_assert_true(strpos($message, 'ws-heading') === false && strpos($message, 'ws-rule') === false, 'a message draws no heading or rule');
	pg_assert_contains('# Plan', $message, 'a message keeps the signs as words');

	// A text without headings is drawn the same either way.
	foreach (array("plain words\nsecond", "- a\n- b", "| a | b |\n|---|---|\n| 1 | 2 |", "#tag and <#order:5>") as $text) {
		pg_assert_same(ws_render_body($text, array()), ws_render_body($text, array(), null, false, null, null, true), 'unchanged: ' . $text);
	}
}

// In a note a heading line ends the paragraph above it; a table's row of
// dashes is still the table's.
function test_workspace_note_headings_around_blocks()
{
	$note = ws_render_body("Shopping:\n- milk\n- bread\n### Done", array(), null, false, null, null, true);
	pg_assert_contains('<ul class="ws-list">', $note, 'a list ends where the heading starts');
	pg_assert_contains('<h4 class="ws-heading">Done</h4>', $note, 'third level');

	$table = ws_render_body("| a | b |\n|---|---|\n| 1 | 2 |", array(), null, false, null, null, true);
	pg_assert_true(strpos($table, 'ws-rule') === false, 'a table separator is no rule');

	$code = ws_render_body("```\n# not a heading\n---\n```", array(), null, false, null, null, true);
	pg_assert_true(strpos($code, 'ws-heading') === false && strpos($code, 'ws-rule') === false, 'nothing inside code');
}
