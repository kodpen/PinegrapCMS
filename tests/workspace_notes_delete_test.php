<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for deleting a note and remembering it (ws_note_deletions): for whom
 * a deletion is kept, and the schema step and the API that carry it.
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

function test_workspace_notes_delete_rows_for_everyone_who_kept_it()
{
	$note = array('id' => 40, 'user_id' => 3);
	$shares = array(
		array('user_id' => 7, 'channel_id' => 0),
		array('user_id' => 0, 'channel_id' => 12),
		array('user_id' => 7, 'channel_id' => 0),
		array('user_id' => 3, 'channel_id' => 0),
		array('user_id' => 0, 'channel_id' => 12),
		array('user_id' => 0, 'channel_id' => 0),
	);

	$rows = ws_note_deletion_rows($note, $shares, 3, 1700000000);

	pg_assert_same(array(
		array('note_id' => 40, 'user_id' => 3, 'channel_id' => 0, 'deleted_by' => 3, 'deleted_at' => 1700000000),
		array('note_id' => 40, 'user_id' => 7, 'channel_id' => 0, 'deleted_by' => 3, 'deleted_at' => 1700000000),
		array('note_id' => 40, 'user_id' => 0, 'channel_id' => 12, 'deleted_by' => 3, 'deleted_at' => 1700000000),
	), $rows, 'the owner, each person once and each channel once; an empty share is nobody');

	pg_assert_same(array(
		array('note_id' => 41, 'user_id' => 5, 'channel_id' => 0, 'deleted_by' => 5, 'deleted_at' => 1),
	), ws_note_deletion_rows(array('id' => 41, 'user_id' => 5), array(), 5, 1), 'a note shared with nobody: its owner alone');
}
