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

// The text of a product file, for the checks that read the source.
function workspace_notes_delete_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_workspace_notes_delete_schema_step_and_table_list()
{
	$migration = workspace_notes_delete_test_source('includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_workspace_note_deletions(); // 11.0.85', $migration, 'the step is called');
	pg_assert_contains("install_create_table('ws_note_deletions'", $migration, 'it creates the table');
	pg_assert_contains("'ws_note_deletions',", workspace_notes_delete_test_source('install/index.php'), 'a reinstall drops it with the other tables (get_tables())');
}

function test_workspace_notes_delete_keeps_its_record_before_the_shares_go()
{
	$source = workspace_notes_delete_test_source('includes/workspace/notes.php');
	$start = strpos($source, 'function ws_note_delete($viewer, $note)');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains('ws_note_delete_check($viewer, $note)', $body, 'the screen and the API meet one check');
	pg_assert_true(strpos($body, 'INSERT INTO ws_note_deletions') < strpos($body, 'DELETE FROM ws_note_shares'), 'who kept it is written before the shares are deleted');
	pg_assert_contains('WS_NOTE_DELETIONS_KEEP', $body, 'older records go at the same time');
}

function test_workspace_notes_delete_unshare_row_is_for_the_person()
{
	pg_assert_same(
		array('note_id' => 40, 'user_id' => 7, 'channel_id' => 0, 'deleted_by' => 3, 'deleted_at' => 1700000000),
		ws_note_unshare_deletion_row(array('id' => 40, 'user_id' => 3), array('user_id' => 7, 'channel_id' => 0), 3, 1700000000),
		'the owner takes a person share back'
	);
	pg_assert_same(
		array('note_id' => 40, 'user_id' => 7, 'channel_id' => 0, 'deleted_by' => 7, 'deleted_at' => 5),
		ws_note_unshare_deletion_row(array('id' => 40), array('user_id' => '7'), 7, 5),
		'the person leaves the note'
	);
	pg_assert_same(null, ws_note_unshare_deletion_row(array('id' => 40), array('user_id' => 0, 'channel_id' => 12), 3, 5), 'a channel share keeps nothing: its notes are not in the list');
}

function test_workspace_notes_delete_unkept_drops_the_notes_still_in_the_list()
{
	$deletions = array(
		array('id' => 40, 'deleted_at' => 10),
		array('id' => 41, 'deleted_at' => 11),
		array('id' => 42, 'deleted_at' => 12),
	);

	pg_assert_same(array(
		array('id' => 40, 'deleted_at' => 10),
		array('id' => 42, 'deleted_at' => 12),
	), ws_note_deletions_unkept($deletions, array('41', 99)), 'a note owned or shared again is not deleted for them');
	pg_assert_same($deletions, ws_note_deletions_unkept($deletions, array()), 'deleted notes have no rows left and stay listed');
	pg_assert_same(array(), ws_note_deletions_unkept(array(), array(40)), 'nothing deleted');
}

function test_workspace_notes_delete_unshare_keeps_its_record_before_the_share_goes()
{
	$source = workspace_notes_delete_test_source('includes/workspace/notes.php');
	$start = strpos($source, 'function ws_note_unshare($viewer, $note, $share_id = 0)');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains('ws_note_unshare_deletion_row($note, $share', $body, 'the row comes from the pure helper');
	pg_assert_contains('ws_note_deletions_ready()', $body, 'only once the table exists');
	pg_assert_true(strpos($body, 'INSERT INTO ws_note_deletions') < strpos($body, 'DELETE FROM ws_note_shares'), 'written before the share is deleted');

	$start = strpos($source, 'function ws_note_deletions_since(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains('ws_note_deletions_unkept($out, $kept)', $body, 'the notes still in the list are dropped');
	pg_assert_contains("FROM ws_note_shares WHERE user_id = '", $body, 'by a person share only, not a channel the person reads');
}

function test_workspace_notes_delete_api_routes()
{
	$api = workspace_notes_delete_test_source('includes/workspace/api.php');

	foreach (array('workspace.notes.delete', 'workspace.notes.delete_post') as $id) {
		pg_assert_true((bool) preg_match("/'id' +=> '" . preg_quote($id, '/') . "',(?:(?!'id' +=>).)*?'dry_run' +=> true,\\s*'mcp' +=> false,/s", $api), $id . ': rehearsable, and not an assistant\'s tool');
	}

	$resources = workspace_notes_delete_test_source('includes/workspace/api_resources.php');
	$start = strpos($resources, 'function ws_api_notes_delete($params)');
	$body = substr($resources, $start, strpos($resources, "\n}\n", $start) - $start);

	pg_assert_true(strpos($body, 'ws_note_delete_check(') < strpos($body, 'api_dry_run_stop(') && strpos($body, 'api_dry_run_stop(') < strpos($body, 'ws_note_delete($viewer'), 'check, then the dry run stop, then the delete');
	pg_assert_contains("array('deleted' => \$deleted)", $resources, 'the list answers the deletions');
}
