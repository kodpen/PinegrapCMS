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
