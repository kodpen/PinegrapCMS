<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Who get_file.php serves a file of a Workspace channel's folder to
 * (pg_ws_channel_file_verdict()): the whole team a public channel's file,
 * the readers of a private, guest or mailbox channel its file (its members,
 * staff who opened it for inspection), no other member of staff, and any
 * other user the folder's own access.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

$pg_ws_file_gate = PG_FUNCTIONS_DIR . '/includes/fn/workspace_file_gate.php';

if (is_file($pg_ws_file_gate)) {
	require_once($pg_ws_file_gate);
}

unset($pg_ws_file_gate);

function test_workspace_channel_file_gate_module_exists()
{
	pg_assert_true(function_exists('pg_ws_channel_file_verdict'), 'includes/fn/workspace_file_gate.php defines pg_ws_channel_file_verdict()');
}

function test_workspace_channel_file_gate_public_channel_opens_to_the_team()
{
	if (!function_exists('pg_ws_channel_file_verdict')) {
		pg_assert_true(false, 'pg_ws_channel_file_verdict() is missing');
		return;
	}

	pg_assert_same('open', pg_ws_channel_file_verdict('public', 1, true, false, false), 'a team member outside a public channel opens its file');
	pg_assert_same('open', pg_ws_channel_file_verdict('public', 3, true, false, false), 'a user with the Workspace right opens a public channel file');
	pg_assert_same('folder', pg_ws_channel_file_verdict('public', 3, false, false, false), 'a site member is left to the private folder');
}

function test_workspace_channel_file_gate_staff_outside_a_private_channel_refused()
{
	if (!function_exists('pg_ws_channel_file_verdict')) {
		pg_assert_true(false, 'pg_ws_channel_file_verdict() is missing');
		return;
	}

	// Staff pass every private folder of the file manager, so the folder
	// check alone handed them every private channel's file by its address.
	pg_assert_same('refuse', pg_ws_channel_file_verdict('private', 0, true, false, false), 'an administrator outside a private channel opened its file');
	pg_assert_same('refuse', pg_ws_channel_file_verdict('private', 2, true, false, false), 'a manager outside a private channel opened its file');
	pg_assert_same('refuse', pg_ws_channel_file_verdict('guest', 1, true, false, false), 'staff outside a guest room opened its file');
	pg_assert_same('open', pg_ws_channel_file_verdict('private', 1, true, true, false), 'a member of the private channel was refused');
	pg_assert_same('open', pg_ws_channel_file_verdict('private', 1, true, false, true), 'staff who opened the channel for inspection were refused');
}

function test_workspace_channel_file_gate_mailbox_room_never_inspected()
{
	if (!function_exists('pg_ws_channel_file_verdict')) {
		pg_assert_true(false, 'pg_ws_channel_file_verdict() is missing');
		return;
	}

	pg_assert_same('refuse', pg_ws_channel_file_verdict('mailbox', 0, true, false, true), 'a mailbox room opened for inspection');
	pg_assert_same('open', pg_ws_channel_file_verdict('mailbox', 0, true, true, false), 'a person chosen on the mailbox was refused');
}

function test_workspace_channel_file_gate_staff_out_of_the_team_refused()
{
	if (!function_exists('pg_ws_channel_file_verdict')) {
		pg_assert_true(false, 'pg_ws_channel_file_verdict() is missing');
		return;
	}

	// "Not in the team" (ws_profiles.excluded): no channel is theirs to read.
	pg_assert_same('refuse', pg_ws_channel_file_verdict('public', 1, false, false, false), 'staff out of the team opened a public channel file');
	pg_assert_same('refuse', pg_ws_channel_file_verdict('private', 1, false, true, false), 'staff out of the team opened a private channel file');
}

function test_workspace_channel_file_gate_user_left_to_the_folder()
{
	if (!function_exists('pg_ws_channel_file_verdict')) {
		pg_assert_true(false, 'pg_ws_channel_file_verdict() is missing');
		return;
	}

	// A user's view right on the folder follows the channel's members, and
	// one given by hand on Edit User stays theirs.
	pg_assert_same('folder', pg_ws_channel_file_verdict('private', 3, true, false, false), 'a user was not left to the folder access');
	pg_assert_same('folder', pg_ws_channel_file_verdict('private', 3, false, true, false), 'a site member was let past the folder access');
}

function test_workspace_channel_file_gate_member_opens_without_a_folder_right()
{
	if (!function_exists('pg_ws_channel_file_verdict')) {
		pg_assert_true(false, 'pg_ws_channel_file_verdict() is missing');
		return;
	}

	// The folder's view right is given to users only: a manager made a
	// user stays in their channels with no right on its folder, and the
	// pictures of their own channel answered 403.
	pg_assert_same('open', pg_ws_channel_file_verdict('private', 3, true, true, false), 'a user in the private channel was left to a folder right they may not hold');
	pg_assert_same('open', pg_ws_channel_file_verdict('guest', 3, true, true, false), 'a user in the guest room was left to a folder right they may not hold');
}

function test_workspace_channel_file_gate_called_by_get_file()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/get_file.php');
	$start = strpos($source, '$workspace_file = false;');
	$end = strpos($source, '// A file of a note behind its reader');

	pg_assert_true(($start !== false) && ($end !== false) && ($start < $end), 'get_file.php has its workspace block');

	$block = substr($source, (int) $start, (int) $end - (int) $start);

	pg_assert_true(strpos($block, "includes/fn/workspace_file_gate.php") !== false, 'the workspace block loads the gate module');
	pg_assert_true(strpos($block, 'pg_ws_channel_file_verdict(') !== false, 'the workspace block asks pg_ws_channel_file_verdict()');
}

function test_workspace_note_file_gate_staff_outside_the_note_refused()
{
	if (!function_exists('pg_ws_note_file_verdict')) {
		pg_assert_true(false, 'pg_ws_note_file_verdict() is missing');
		return;
	}

	// A note's folder is private and staff pass every private folder, so a
	// manager opened anybody's note pictures by their address; a note gives
	// administrators nothing by rank.
	pg_assert_same('refuse', pg_ws_note_file_verdict(0, false), 'an administrator who does not read the note opened its file');
	pg_assert_same('refuse', pg_ws_note_file_verdict(2, false), 'a manager who does not read the note opened its file');
	pg_assert_same('folder', pg_ws_note_file_verdict(1, true), 'staff who read the note were refused');
	pg_assert_same('folder', pg_ws_note_file_verdict(3, false), 'a user was not left to the folder access');
}

function test_workspace_note_file_gate_called_by_get_file()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/get_file.php');
	$start = strpos($source, '$workspace_file = false;');
	$end = strpos($source, '// A file of a note behind its reader');
	$block = substr($source, (int) $start, (int) $end - (int) $start);

	pg_assert_true(strpos($block, 'pg_ws_note_file_verdict(') !== false, 'the workspace block asks pg_ws_note_file_verdict()');
}
