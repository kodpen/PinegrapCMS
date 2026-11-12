<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how a change of the Workspace notes lock reaches a client that
 * keeps notes and reads what changed (includes/workspace/note_lock.php,
 * note_groups.php, note_group_shares.php): a note that goes behind a
 * person's lock, or comes out from behind it, moves its updated_at on, so
 * GET /workspace/notes?updated_since= carries it with `locked` as it now is.
 * Also the lock as a condition of a query, for the API's search.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/note_lock.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

/**
 * Every function of the workspace's files, by name, with its source.
 *
 * @return array name => array(file, body)
 */
function workspace_note_lock_changes_functions()
{
	static $functions = null;

	if ($functions !== null) {
		return $functions;
	}

	$functions = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php') as $file) {
		$parts = preg_split('/^function &?([a-z0-9_]+)\(/m', (string) file_get_contents($file), -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($index = 1; $index + 1 < count($parts); $index += 2) {
			// Up to the function's closing brace, so the comment above the
			// next function is not taken for its code.
			$end = strpos($parts[$index + 1], "\n}\n");

			$functions[$parts[$index]] = array(
				'file' => basename($file),
				'body' => ($end === false) ? $parts[$index + 1] : substr($parts[$index + 1], 0, $end + 3),
			);
		}
	}

	return $functions;
}

// The lock as a query's condition says what ws_note_lock_behind() says.
function test_workspace_note_lock_changes_behind_sql()
{
	pg_assert_same('', ws_note_lock_behind_sql(7, false, array(3)), 'no PIN, nothing is behind a lock');
	pg_assert_same('', ws_note_lock_behind_sql(0, true, array(3)), 'nobody');
	pg_assert_same("((n.user_id = '7' AND n.locked = 1))", ws_note_lock_behind_sql(7, true, array()), 'the person\'s own locked notes');
	pg_assert_same("((n.user_id = '7' AND n.locked = 1) OR n.id IN (3,9))", ws_note_lock_behind_sql('7', true, array('3', 9, 3, 0, 'x')),
		'and the notes they keep in their locked groups, as numbers, each once');
}

// A group takes behind the lock the notes its owner keeps in it, but for
// their own notes locked one by one: those are behind it either way.
function test_workspace_note_lock_changes_group_follow_ids()
{
	$rows = array(
		array('id' => '1', 'user_id' => '7', 'locked' => '1'),
		array('id' => '2', 'user_id' => '7', 'locked' => '0'),
		array('id' => '3', 'user_id' => '9', 'locked' => '1'),
		array('id' => '4', 'user_id' => '9', 'locked' => '0'),
		false,
	);

	pg_assert_same(array(2, 3, 4), ws_note_lock_group_follow_ids($rows, 7), 'somebody else\'s mark is not the owner\'s lock');
	pg_assert_same(array(), ws_note_lock_group_follow_ids(array(), 7));
}

// Only the moment of the last change moves: the words did not change, so
// the revision and who wrote them stay.
function test_workspace_note_lock_changes_moved_writes_the_moment_alone()
{
	$body = workspace_note_lock_changes_functions()['ws_note_lock_moved']['body'];

	pg_assert_true(preg_match('/db\("UPDATE ws_notes SET (.*?) WHERE id IN/s', $body, $match) === 1, 'the write is found');
	pg_assert_same("updated_at = '\" . time() . \"'", trim($match[1]), 'no revision, no updated_by');
	pg_assert_same(1, substr_count($body, 'db('), 'and no other write');
	pg_assert_contains('array_chunk($ids, 500)', $body, 'in slices');
}

// Every write of a note's or a group's lock mark is followed by the move.
function test_workspace_note_lock_changes_every_mark_write_moves_the_notes()
{
	$writers = array();

	foreach (workspace_note_lock_changes_functions() as $name => $function) {
		if (preg_match('/UPDATE ws_notes SET locked = |UPDATE ws_note_groups SET locked = /', $function['body'])) {
			$writers[] = $name;

			pg_assert_contains('ws_note_lock_moved(', $function['body'], $name . '() writes a lock mark');
		}
	}

	sort($writers);

	pg_assert_same(array('ws_note_lock_group', 'ws_note_lock_note', 'ws_note_lock_turn_off'), $writers, 'the writers of the marks');

	$note = workspace_note_lock_changes_functions()['ws_note_lock_note']['body'];
	$group = workspace_note_lock_changes_functions()['ws_note_lock_group']['body'];
	$off = workspace_note_lock_changes_functions()['ws_note_lock_turn_off']['body'];

	// A mark set to what it was is no change.
	pg_assert_contains("\$was = !empty(\$note['locked']);", $note);
	pg_assert_same(2, substr_count($note, "ws_note_lock_moved(array((int) \$note['id']));"), 'locked and unlocked');
	pg_assert_contains("\$was = !empty(\$group['locked']);", $group);
	pg_assert_same(2, substr_count($group, "ws_note_lock_moved(ws_note_lock_group_follows(\$user_id, (int) \$group['id']));"), 'locked and unlocked');

	// Turning the lock off reads what was behind it before the marks go.
	pg_assert_true(strpos($off, 'ws_note_lock_group_note_ids($user_id)') < strpos($off, 'UPDATE ws_note_groups SET locked = 0'), 'the groups\' notes are read while the groups are locked');
	pg_assert_true(strpos($off, 'SELECT id FROM ws_notes WHERE user_id') < strpos($off, 'UPDATE ws_notes SET locked = 0'), 'the locked notes are read while they are locked');
	pg_assert_contains('ws_note_lock_moved($freed);', $off);
}

// Every write of a note's place in a group is followed by the move, or says
// why the lock of the one who kept it there cannot change with it.
function test_workspace_note_lock_changes_every_place_write_moves_the_note()
{
	$exempt = array(
		'ws_note_places_forget_note'   => 'the note is deleted (ws_note_deletions tells the clients)',
		'ws_note_places_forget_reader' => 'the reader no longer has the note (ws_note_deletions tells their clients)',
		'ws_note_group_share_drop'     => 'places of the people a group was shared with: a group of somebody else is never behind their lock',
		'ws_note_group_shares_forget'  => 'places of the people a group was shared with: a group of somebody else is never behind their lock',
	);
	$writers = array();

	foreach (workspace_note_lock_changes_functions() as $name => $function) {
		if (!preg_match('/(INSERT INTO|DELETE FROM) ws_note_places\b/', $function['body'])) {
			continue;
		}

		$writers[] = $name;

		if (isset($exempt[$name])) {
			continue;
		}

		pg_assert_true((strpos($function['body'], 'ws_note_lock_place_moved(') !== false) || (strpos($function['body'], 'ws_note_lock_moved(') !== false),
			$name . '() in ' . $function['file'] . ' writes a place: the note it takes behind a lock or out of one counts as changed, or the function is listed here with the reason');
	}

	foreach (array_keys($exempt) as $name) {
		pg_assert_contains($name, $writers, 'an exemption for a function that writes no place is stale');
	}

	foreach (array('ws_note_place', 'ws_note_group_take_out', 'ws_note_group_delete') as $name) {
		pg_assert_contains($name, $writers);
	}

	$place = workspace_note_lock_changes_functions()['ws_note_place']['body'];

	pg_assert_true(strpos($place, '$behind = $lock && ws_note_behind_lock($me, $note);') < strpos($place, 'DELETE FROM ws_note_places'), 'the lock is asked before the move');
	pg_assert_same(2, substr_count($place, 'ws_note_lock_place_moved($me, $note, $behind);'), 'out of every group, and into one');

	$out = workspace_note_lock_changes_functions()['ws_note_group_take_out']['body'];

	pg_assert_true(strpos($out, '$behind = $lock && ws_note_behind_lock($placed_by, $note);') < strpos($out, 'DELETE FROM ws_note_places'), 'the lock of whoever kept it there');

	$delete = workspace_note_lock_changes_functions()['ws_note_group_delete']['body'];

	pg_assert_true(strpos($delete, 'ws_note_lock_group_follows((int) $viewer[\'id\'], $id)') < strpos($delete, 'DELETE FROM ws_note_places'), 'a deleted group\'s notes are read before their places go');
}

// A move counts only when it changed which side of the lock the note is on.
function test_workspace_note_lock_changes_a_move_counts_when_the_side_changes()
{
	$body = workspace_note_lock_changes_functions()['ws_note_lock_place_moved']['body'];

	pg_assert_contains('ws_note_behind_lock((int) $user_id, $note) !== (bool) $was_behind', $body);
}

// The route says so, for the clients that keep notes.
function test_workspace_note_lock_changes_are_documented()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$list = $routes['workspace.notes.list'];

	pg_assert_contains('counts as a change of the note', $list['description']);
	pg_assert_contains('by its title alone', $list['description']);

	foreach ($list['params'] as $param) {
		if ($param['name'] === 'search') {
			pg_assert_contains('title alone', $param['description']);
		}
	}
}
