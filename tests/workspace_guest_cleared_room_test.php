<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which messages of a room a guest's own requests reach
 * (includes/workspace/guests.php): the file address, a tick, a reaction and a
 * vote take only messages of the room's current conversation, the ones the
 * guest page lists, never those from before the room was cleared.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in this file runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/guests.php');

function test_ws_guest_message_reach_sql_leaves_out_a_cleared_conversation()
{
	pg_assert_same("m.channel_id = '7' AND m.id > '120' AND m.deleted_at = 0", ws_guest_message_reach_sql(7, 120, 'm.'));
}

function test_ws_guest_message_reach_sql_without_a_clear_takes_the_whole_room()
{
	pg_assert_same("channel_id = '7' AND id > '0' AND deleted_at = 0", ws_guest_message_reach_sql(7, 0));
}

function test_ws_guest_message_reach_sql_reads_numbers_only()
{
	pg_assert_same("channel_id = '7' AND id > '0' AND deleted_at = 0", ws_guest_message_reach_sql("7' OR '1", -5));
}

function test_ws_guest_message_in_reach_refuses_a_message_before_the_clear()
{
	pg_assert_same(false, ws_guest_message_in_reach(120, 120));
	pg_assert_same(false, ws_guest_message_in_reach(0, 0));
	pg_assert_same(true, ws_guest_message_in_reach(121, 120));
	pg_assert_same(true, ws_guest_message_in_reach(5, 0));
}

/**
 * A message row as ws_guest_file_send() reads it, in room 7 after floor 100.
 */
function ws_guest_file_test_row($fields = array())
{
	return $fields + array('id' => 150, 'channel_id' => 7, 'deleted_at' => 0, 'sender_kind' => 'app', 'sender_id' => 9, 'kind' => 'message', 'file_id' => 33);
}

function test_ws_guest_file_reached_serves_an_agents_and_a_persons_file()
{
	pg_assert_same(true, ws_guest_file_reached(ws_guest_file_test_row(), 7, 100, array(9)));
	pg_assert_same(true, ws_guest_file_reached(ws_guest_file_test_row(array('sender_kind' => 'user', 'sender_id' => 3)), 7, 100, array(9)));
	// An MCP agent's answer is kept in its asker's name.
	pg_assert_same(true, ws_guest_file_reached(ws_guest_file_test_row(array('sender_kind' => 'user', 'sender_id' => 3)), 7, 100, array(), true));
	pg_assert_same(true, ws_guest_file_reached(ws_guest_file_test_row(array('kind' => 'decision')), 7, 100, array(9)));
}

function test_ws_guest_file_reached_refuses_a_hidden_applications_file()
{
	// CI or an integration: an application that is no agent.
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(array('sender_id' => 4)), 7, 100, array(9)));
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(), 7, 100, array()));
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(array('sender_kind' => 'system', 'kind' => 'system')), 7, 100, array(9)));
}

function test_ws_guest_file_reached_refuses_another_channels_file()
{
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(array('channel_id' => 8)), 7, 100, array(9)));
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(array('channel_id' => '7')), 0, 100, array(9)));
	pg_assert_same(true, ws_guest_file_reached(ws_guest_file_test_row(array('channel_id' => '7')), '7', 100, array(9)));
}

function test_ws_guest_file_reached_refuses_an_earlier_conversations_file()
{
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(array('id' => 100)), 7, 100, array(9)));
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(array('id' => 42)), 7, 100, array(9)));
	pg_assert_same(true, ws_guest_file_reached(ws_guest_file_test_row(array('id' => 101)), 7, 100, array(9)));
}

function test_ws_guest_file_reached_refuses_a_live_page_a_deleted_line_and_no_file()
{
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(), 7, 100, array(9), false, true));
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(array('deleted_at' => 5)), 7, 100, array(9)));
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(array('file_id' => 0)), 7, 100, array(9)));
	pg_assert_same(false, ws_guest_file_reached(null, 7, 100, array(9)));
	pg_assert_same(false, ws_guest_file_reached(false, 7, 100, array(9)));
}

function test_ws_guest_file_reached_refuses_a_guests_upload()
{
	pg_assert_same(false, ws_guest_file_reached(ws_guest_file_test_row(array('sender_kind' => 'guest', 'sender_id' => 2)), 7, 100, array(9)));
}

function test_ws_guest_canvas_view_is_nothing_for_a_plain_file()
{
	pg_assert_same(array('canvas' => false, 'pending' => false, 'v' => ''), ws_guest_canvas_view(null));
	pg_assert_same(array('canvas' => false, 'pending' => false, 'v' => ''), ws_guest_canvas_view(array('version_no' => 0)));
}

function test_ws_guest_canvas_view_says_a_pending_picture_is_not_ready()
{
	pg_assert_same(array('canvas' => true, 'pending' => true, 'v' => '3p'), ws_guest_canvas_view(array('version_no' => 3, 'pending' => true, 'server' => false)));
}

function test_ws_guest_canvas_view_puts_the_version_into_the_address()
{
	pg_assert_same(array('canvas' => true, 'pending' => false, 'v' => '4s'), ws_guest_canvas_view(array('version_no' => 4, 'pending' => false, 'server' => true)));
	pg_assert_same(array('canvas' => true, 'pending' => false, 'v' => '5'), ws_guest_canvas_view(array('version_no' => 5, 'pending' => false, 'server' => false)));
}
