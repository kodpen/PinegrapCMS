<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the notes of the Workspace (includes/workspace/notes.php): the
 * parts that need no database - how an assistant's answer is fitted under
 * the note without cutting the note itself, and what the delivery keeps.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');

// A short answer goes in whole; a long one is shortened to what the note has
// left; a full note takes nothing, and the note is never the part that goes.
function test_workspace_notes_answer_fits_under_the_note_without_cutting_it()
{
	$heading = '**Claude:**';

	pg_assert_same('Evet.', ws_note_answer_fit('Soru?', $heading, 'Evet.'), 'whole');

	$body = str_repeat('a', WS_NOTE_MAX - mb_strlen($heading) - 3 - 100);
	$long = str_repeat('ş', 500);
	$fitted = ws_note_answer_fit($body, $heading, $long);

	pg_assert_same(100, mb_strlen($fitted), 'shortened to the room left, ellipsis included');
	pg_assert_same('…', mb_substr($fitted, -1), 'and says so');
	pg_assert_true(mb_strlen($body) + mb_strlen($heading) + 3 + mb_strlen($fitted) <= WS_NOTE_MAX, 'the note with it stays within the limit');

	$full = str_repeat('a', WS_NOTE_MAX - mb_strlen($heading) - 3 - 19);

	pg_assert_same(null, ws_note_answer_fit($full, $heading, 'Evet.'), 'fewer than 20 characters left: nothing is written');
	pg_assert_same(null, ws_note_answer_fit(str_repeat('a', WS_NOTE_MAX), $heading, 'Evet.'), 'a note at the limit');
}

// The delivery keeps the state before the answer as a version, indexes the
// titled blocks like a save, and sets a request aside instead of cutting.
function test_workspace_notes_delivery_keeps_a_version_and_never_cuts()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');
	$start = strpos($source, 'function ws_note_claude_deliver(');
	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	pg_assert_contains('ws_note_version_keep($viewer, $note, $sets);', $body, 'a version before the write');
	pg_assert_true(strpos($body, 'ws_note_version_keep(') < strpos($body, 'UPDATE ws_notes SET body'), 'kept before the body changes');
	pg_assert_contains("ws_blocks_index('note', (int) \$note['id'], \$body, (int) \$note['user_id']);", $body, 'titled blocks indexed');
	pg_assert_contains('$answer = ws_note_answer_fit($before, $heading, $answer);', $body, 'the answer is what is fitted');
	pg_assert_false(strpos($body, 'mb_substr(rtrim(implode("\n", $lines)), 0, WS_NOTE_MAX)') !== false, 'the note is not cut to the limit');
	pg_assert_contains("SET status = 'failed', note_done = 0", $body, 'no room: set aside with a reason, not lost silently');
}
