<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for files sent together in a Workspace channel
 * (includes/fn/ws_file_groups.php): when a message may take one more file
 * into its group, and the panel's two file routes passing the group on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/ws_file_groups.php');

// A head that may take a file from user 7 in channel 3 at time 10000.
function ws_file_groups_test_head($changes = array())
{
	return array_merge(array(
		'id'          => 40,
		'channel_id'  => 3,
		'sender_kind' => 'user',
		'sender_id'   => 7,
		'file_id'     => 90,
		'deleted_at'  => 0,
		'created_at'  => 10000 - 30,
		'file_group'  => 0,
	), $changes);
}

function ws_file_groups_test_sender($changes = array())
{
	return array_merge(array('channel_id' => 3, 'sender_kind' => 'user', 'sender_id' => 7), $changes);
}

function test_ws_file_group_check_takes_a_fresh_head()
{
	pg_assert_true(ws_file_group_check(ws_file_groups_test_head(), ws_file_groups_test_sender(), 1, 10000), 'unmarked head');
	pg_assert_true(ws_file_group_check(ws_file_groups_test_head(array('file_group' => 40)), ws_file_groups_test_sender(), 5, 10000), 'marked head');
	pg_assert_true(ws_file_group_check(ws_file_groups_test_head(array('sender_kind' => 'app', 'sender_id' => 2)),
		ws_file_groups_test_sender(array('sender_kind' => 'app', 'sender_id' => 2)), 1, 10000), 'an application');
}

function test_ws_file_group_check_refuses_another_channel_or_sender()
{
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(), ws_file_groups_test_sender(array('channel_id' => 4)), 1, 10000), 'another channel or discussion');
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(), ws_file_groups_test_sender(array('sender_id' => 8)), 1, 10000), 'another person');
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(), ws_file_groups_test_sender(array('sender_kind' => 'app')), 1, 10000), 'an application with the same id');
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(array('channel_id' => 0)), ws_file_groups_test_sender(array('channel_id' => 0)), 1, 10000), 'no channel');
}

function test_ws_file_group_check_refuses_a_head_without_a_file_or_deleted()
{
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(array('file_id' => 0)), ws_file_groups_test_sender(), 1, 10000), 'words only');
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(array('deleted_at' => 9990)), ws_file_groups_test_sender(), 1, 10000), 'deleted');
	pg_assert_false(ws_file_group_check(null, ws_file_groups_test_sender(), 1, 10000), 'missing');
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(array('id' => 0)), ws_file_groups_test_sender(), 1, 10000), 'no id');
}

function test_ws_file_group_check_refuses_an_old_head()
{
	pg_assert_true(ws_file_group_check(ws_file_groups_test_head(array('created_at' => 10000 - 599)), ws_file_groups_test_sender(), 1, 10000), 'just under ten minutes');
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(array('created_at' => 10000 - 600)), ws_file_groups_test_sender(), 1, 10000), 'ten minutes');
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(array('created_at' => 0)), ws_file_groups_test_sender(), 1, 10000), 'no time');
}

function test_ws_file_group_check_refuses_a_member_or_a_full_group()
{
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(array('file_group' => 12)), ws_file_groups_test_sender(), 2, 10000), 'a member of another group');
	pg_assert_true(ws_file_group_check(ws_file_groups_test_head(array('file_group' => 40)), ws_file_groups_test_sender(), 9, 10000), 'nine files');
	pg_assert_false(ws_file_group_check(ws_file_groups_test_head(array('file_group' => 40)), ws_file_groups_test_sender(), 10, 10000), 'ten files');
}

function test_ws_file_group_routes_pass_the_group()
{
	$actions = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$files = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');

	pg_assert_true(strpos($actions, "'file_group' => \$product_file ? 0 : (int) (\$request['group'] ?? 0)") !== false, 'ws_attach passes group, never for /create');
	pg_assert_true(strpos($actions, "'file_group' => (int) (\$request['group'] ?? 0),\n            ));") !== false, 'ws_library_attach passes group');
	pg_assert_true(strpos($files, 'ws_file_group_head(') !== false && strpos($files, 'ws_file_group_mark(') !== false, 'the send reads and writes the group');
}
