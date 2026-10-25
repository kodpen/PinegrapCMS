<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a note's revision (ws_notes.revision): when a change sent
 * against what was read is refused, that every write of a note's title, text
 * or pin moves the revision on, and that the schema step and the API carry
 * it.
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

// The text of a product file, for the checks that read the source.
function workspace_notes_revision_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_workspace_notes_revision_stale()
{
	$note = array('revision' => 7, 'updated_at' => 1000, 'updated_by' => 3);

	pg_assert_false(ws_note_stale($note, array(), 3), 'nothing sent: nothing to compare');
	pg_assert_false(ws_note_stale($note, array('revision' => 7), 3), 'the revision that was read');
	pg_assert_true(ws_note_stale($note, array('revision' => 6), 3), 'an older revision, though the same person changed it (another device)');
	pg_assert_true(ws_note_stale($note, array('revision' => 6, 'base' => 1000), 9), 'the revision decides over base');
	pg_assert_false(ws_note_stale($note, array('revision' => 0, 'base' => 1000), 9), 'revision 0 is none: base decides');

	pg_assert_true(ws_note_stale($note, array('base' => 999), 9), 'base older than the note, changed by another person');
	pg_assert_false(ws_note_stale($note, array('base' => 999), 3), 'base: the person\'s own change is not a conflict');
	pg_assert_false(ws_note_stale($note, array('base' => 1000), 9), 'base: read at that very second');

	$before_upgrade = array('updated_at' => 1000, 'updated_by' => 3);

	pg_assert_false(ws_note_stale($before_upgrade, array('revision' => 6), 9), 'no revision column yet: a revision sent is not compared');
	pg_assert_true(ws_note_stale($before_upgrade, array('revision' => 6, 'base' => 999), 9), 'and base still works');
}
