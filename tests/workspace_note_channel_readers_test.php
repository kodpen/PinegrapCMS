<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Guard of a channel's note (ws_notes.owner_channel_id, includes/workspace/
 * notes.php): every query that reads a person's own notes by user_id adds
 * ws_note_own_sql(), so a note written in a channel never lands on anybody's
 * My Notes, export, import, template or limit. A statement that needs no
 * such condition is listed below with the reason. Also: who reaches a
 * channel's note, its two writers, its limit and its channel's delete.
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

/**
 * The statements that read ws_notes by its writer and may leave a channel's
 * note in: file => array(a string of the statement => the reason).
 *
 * @return array
 */
function workspace_note_channel_readers_exempt()
{
	return array(
		'includes/workspace/notes.php' => array(
			'source_message_id' => 'the copy of a message kept in the person\'s notes; a channel\'s note is never one',
		),
		'includes/workspace/note_lock.php' => array(
			'locked = 1' => 'the person\'s locked notes; a channel\'s note is never locked (ws_note_lock_note())',
		),
		'includes/workspace/meetings.php' => array(
			'ORDER BY (user_id' => 'the writer only sorts the hits; each is opened through ws_note_for()',
		),
	);
}

/**
 * Every statement reading ws_notes with "user_id = '": "path:line" =>
 * the statement's text, up to its closing quote and bracket.
 *
 * @return array
 */
function workspace_note_channel_readers_statements()
{
	$root = PG_FUNCTIONS_DIR;
	$out = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

	foreach ($iterator as $file) {
		$path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

		if ((substr($path, -4) !== '.php') || preg_match('#^(install|includes/migrations|includes/local|vendor|data)/#', $path)) {
			continue;
		}

		$source = (string) file_get_contents($file->getPathname());

		if (strpos($source, 'ws_notes') === false) {
			continue;
		}

		// From the table to the statement's end ("), with the writer's
		// column compared for equality (a shared list's "<>" is not mine).
		if (!preg_match_all('/FROM\s+ws_notes(?:\s+n)?\s+WHERE\b(?:(?!"\)).)*?\b(?:n\.)?user_id\s*=\s*\'(?:(?!"\)).)*"\)/s', $source, $matches, PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($matches[0] as $match) {
			$out[$path . ':' . (substr_count(substr($source, 0, $match[1]), "\n") + 1)] = $match[0];
		}
	}

	return $out;
}

function test_workspace_note_channel_readers_leave_a_channel_note_out()
{
	$exempt = workspace_note_channel_readers_exempt();
	$statements = workspace_note_channel_readers_statements();
	$used = array();

	pg_assert_true(count($statements) >= 8, 'the scan finds the readers of a person\'s notes');

	foreach ($statements as $where => $sql) {
		$path = substr($where, 0, strrpos($where, ':'));
		$reason = '';

		foreach ((array) ($exempt[$path] ?? array()) as $marker => $why) {
			if (strpos($sql, $marker) !== false) {
				$reason = $why;
				$used[$path . '|' . $marker] = true;
			}
		}

		if ($reason !== '') {
			continue;
		}

		pg_assert_contains('ws_note_own_sql(', $sql, $where . ': a reader of a person\'s notes adds ws_note_own_sql(), or is listed as exempt with the reason');
	}

	foreach ($exempt as $path => $markers) {
		foreach (array_keys($markers) as $marker) {
			pg_assert_true(isset($used[$path . '|' . $marker]), $path . ' "' . $marker . '": an exemption no statement needs any more; take it off the list');
		}
	}
}

// The readers of the lists that are not SQL of their own: My Notes, the
// dashboard card, the API's pages, Export all.
function test_workspace_note_channel_readers_named()
{
	$notes = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');

	foreach (array('ws_notes_list', 'ws_notes_latest', 'ws_notes_page_rows', 'ws_note_save_check', 'ws_note_deletions_since') as $function) {
		$start = strpos($notes, 'function ' . $function . '(');
		$body = substr($notes, $start, strpos($notes, "\nfunction ", $start + 1) - $start);

		pg_assert_contains('ws_note_own_sql(', $body, $function . ' leaves a channel\'s note out');
	}

	pg_assert_contains('ws_note_own_sql(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php'), 'Export all leaves a channel\'s note out');
}

// Nobody owns a channel's note: who may post edits it (its writer too), who
// reads views it, nobody else; share rows give nothing.
function test_workspace_note_channel_access()
{
	$reach = function ($channel_id, $need) {
		$reach = array(10 => 'post', 20 => 'read', 30 => '');
		$got = $reach[(int) $channel_id] ?? '';

		return (($got === 'post') && ($need !== 'post')) ? 'read' : $got;
	};

	pg_assert_same('edit', ws_note_access_of(7, array('user_id' => 3, 'owner_channel_id' => 10), array(), $reach), 'a poster edits');
	pg_assert_same('view', ws_note_access_of(7, array('user_id' => 3, 'owner_channel_id' => 20), array(), $reach), 'a reader views');
	pg_assert_same('', ws_note_access_of(7, array('user_id' => 3, 'owner_channel_id' => 30), array(), $reach), 'no reach, nothing');
	pg_assert_same('edit', ws_note_access_of(7, array('user_id' => 7, 'owner_channel_id' => 10), array(), $reach), 'the writer edits, owns nothing');
	pg_assert_same('view', ws_note_access_of(7, array('user_id' => 7, 'owner_channel_id' => 20), array(), $reach), 'the writer who may no longer post views');
	pg_assert_same('', ws_note_access_of(7, array('user_id' => 7, 'owner_channel_id' => 30), array(), $reach), 'the writer out of the channel reaches nothing');
	pg_assert_same('', ws_note_access_of(7, array('user_id' => 3, 'owner_channel_id' => 30), array(array('user_id' => 7, 'channel_id' => 0, 'can_edit' => 1)), $reach), 'a share row gives nothing');
	pg_assert_same('owner', ws_note_access_of(7, array('user_id' => 7, 'owner_channel_id' => 0), array(), $reach), 'a person\'s own note is theirs');
	pg_assert_same('owner', ws_note_access_of(7, array('user_id' => 7), array(), $reach), 'before the step: a person\'s');
}

function test_workspace_note_channel_of()
{
	pg_assert_same(0, ws_note_channel_of(null), 'no note');
	pg_assert_same(0, ws_note_channel_of(array('user_id' => 3)), 'no column');
	pg_assert_same(12, ws_note_channel_of(array('owner_channel_id' => '12')), 'a channel\'s');
	pg_assert_same(2000, WS_NOTE_CHANNEL_LIMIT, 'a channel keeps at most 2000 notes');
}

// Both writers (the panel's Write a note and POST /workspace/channels/{id}/
// notes) go through ws_note_channel_write(), which writes the channel's note
// and counts it in the channel's limit; a purged channel takes its notes.
function test_workspace_note_channel_writers_and_delete()
{
	$notes = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');

	pg_assert_contains("'body' => \$body), (int) \$channel['id']);", $notes, 'the write check counts the channel\'s notes');
	pg_assert_contains("(int) (\$data['app_id'] ?? 0)), (int) \$channel['id']);", $notes, 'the write saves the channel\'s note');
	pg_assert_contains("'can_edit'      => false,", $notes, 'the card\'s share row gives no edit');
	pg_assert_contains('WS_NOTE_CHANNEL_LIMIT', $notes, 'the channel\'s limit');
	pg_assert_contains('ws_note_channel_write(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_channel_notes.php'), 'the API writes through it');
	pg_assert_contains('ws_note_channel_purge($thread_id);', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/threads.php'), 'a purged discussion deletes its notes');
	pg_assert_contains("db_items(\"SELECT * FROM ws_notes WHERE owner_channel_id = '\"", $notes, 'the purge reads the channel\'s notes');
}
