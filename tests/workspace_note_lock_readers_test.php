<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Guard of the Workspace notes lock (includes/workspace/note_lock.php): every
 * file that reads ws_notes, or a note through ws_note() / ws_note_for(), is
 * listed here with the gate it calls or the reason it needs none, so a new
 * reader cannot hand out the text of a locked note unseen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * Every reader of notes: file => the gate it calls (a string that must be
 * in the file), or 'none: <reason>'.
 *
 * @return array
 */
function workspace_note_lock_readers()
{
	return array(
		'get_file.php'                              => 'ws_note_lock_folder_closed(',
		'workspace_file_save.php'                   => 'ws_note_lock_gate(',
		'workspace_notes_export.php'                => 'ws_note_lock_closed(',
		'includes/workspace/actions.php'            => 'ws_action_note_locked(',
		'includes/workspace/ai.php'                 => 'ws_note_locked_by_owner(',
		'includes/workspace/api_claude.php'         => 'ws_note_locked_by_owner(',
		'includes/workspace/api_resources.php'      => 'ws_api_note_locked_stop(',
		'includes/workspace/api_note_groups.php'    => 'ws_api_note_locked_stop(',
		'includes/workspace/blocks.php'             => 'ws_note_lock_closed(',
		'includes/workspace/notes.php'              => 'ws_note_lock_present(',
		'includes/workspace/notes_io.php'           => 'ws_note_lock_leave_out(',
		'includes/workspace/refs.php'               => 'ws_note_lock_closed(',
		'includes/workspace/wtable_import.php'      => 'ws_note_lock_closed(',
		'includes/workspace/note_lock.php'          => 'none: the lock itself',
		'includes/workspace/note_groups.php'        => 'none: groups and places, no text; ws_note_groups() redacts a closed group',
		'includes/workspace/note_group_shares.php'  => 'none: share rows only; a locked note or group is never shared',
		'includes/workspace/notes_import.php'       => 'none: writes new notes from a file',
		'includes/workspace/api_day.php'            => 'none: search hits come from ws_notes_list(), which keeps a closed note\'s title alone',
		'includes/workspace/forward.php'            => 'none: writes through ws_note_save(), whose check holds the gate',
		'includes/workspace/scheduled.php'          => 'none: the note\'s title on the action; a write goes through ws_note_save()',
		'includes/workspace/meetings.php'           => 'none: finds the note id; it opens through ws_note_get and is transferred through ws_meeting_transfer, both gated',
		'includes/workspace/templates.php'          => 'none: copies notes shared in a channel, and a locked note is never shared',
		'includes/workspace/notify.php'             => 'none: inbox lines carry the title only',
		'includes/workspace/changes.php'            => 'none: names the table for a new note',
		'includes/fn/image.php'                     => 'none: folder ids for image cleanup, no text',
		'includes/panel/actions.php'                => 'none: action names',
	);
}

function test_workspace_note_lock_every_reader_is_listed()
{
	$root = PG_FUNCTIONS_DIR;
	$found = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

	foreach ($iterator as $file) {
		$path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

		if ((substr($path, -4) !== '.php') || preg_match('#^(install|includes/migrations|includes/local|vendor|data)/#', $path)) {
			continue;
		}

		if (preg_match('/ws_notes\b|ws_note\(|ws_note_for\(/', (string) file_get_contents($file->getPathname()))) {
			$found[] = $path;
		}
	}

	sort($found);
	$listed = array_keys(workspace_note_lock_readers());
	sort($listed);

	pg_assert_same(array(), array_values(array_diff($found, $listed)), 'a new reader of notes: give it the lock\'s gate and list it here');
	pg_assert_same(array(), array_values(array_diff($listed, $found)), 'a listed reader no longer reads notes: take it off the list');
}

function test_workspace_note_lock_readers_call_their_gate()
{
	foreach (workspace_note_lock_readers() as $path => $gate) {
		if (strpos($gate, 'none: ') === 0) {
			continue;
		}

		pg_assert_contains($gate, (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path), $path . ' calls ' . $gate);
	}
}

function test_workspace_note_lock_api_says_locked()
{
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');

	pg_assert_contains("'locked'      => \$locked,", $api, 'the API note carries locked');
	pg_assert_contains("api_fail(423, 'note_locked'", $api, 'writes answer 423 note_locked');
	pg_assert_contains('ws_api_note_locked_stop($viewer, $note);', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_threads.php'), 'versions answer 423 too');
}
