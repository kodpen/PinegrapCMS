<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guests of Channel info (includes/workspace/guests.php):
 * which shares are open, and how a guest's row reads - read only or read
 * and write, who shared and when, the last seen only for a manager.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_agents_panel.php');

function test_ws_guest_share_closed_reads_the_end_the_link_and_its_time()
{
	$now = 1000000;

	pg_assert_same(false, ws_guest_share_closed(array('ended_at' => 0), array('expires_at' => 0), $now));
	pg_assert_same(false, ws_guest_share_closed(array('ended_at' => 0), array('expires_at' => $now + 60), $now));
	pg_assert_same(true, ws_guest_share_closed(array('ended_at' => 0), array('expires_at' => $now), $now));
	pg_assert_same(true, ws_guest_share_closed(array('ended_at' => 0), null, $now));
	pg_assert_same(true, ws_guest_share_closed(array('ended_at' => $now - 5), array('expires_at' => 0), $now));
}

function ws_guest_channel_info_test_row($access, $seen)
{
	return array('id' => 4, 'name' => 'Zeynep Kaya', 'access' => $access, 'created_at' => mktime(12, 0, 0, 11, 13, 2026), 'last_seen_at' => $seen);
}

function test_ws_channel_info_guest_names_access_sharer_and_day()
{
	$row = ws_channel_info_guest(ws_guest_channel_info_test_row('read', 0), array('initials' => 'ZK', 'tone' => 3, 'avatar_kind' => 'contact'), 'Erdal', false, 2000000000);

	pg_assert_same(4, $row['id']);
	pg_assert_same('Zeynep Kaya', $row['name']);
	pg_assert_same('read', $row['access']);
	pg_assert_same('ZK', $row['initials']);
	pg_assert_same(true, strpos($row['line'], '13.11.2026') !== false);
	pg_assert_same(true, strpos($row['line'], 'Erdal') !== false);

	$write = ws_channel_info_guest(ws_guest_channel_info_test_row('write', 0), array(), '', false, 2000000000);
	pg_assert_same('write', $write['access']);
	pg_assert_same(false, strpos($write['line'], 'Erdal') !== false);
}

function test_ws_channel_info_guest_last_seen_only_for_a_manager()
{
	$now = 2000000000;

	$member = ws_channel_info_guest(ws_guest_channel_info_test_row('write', $now - 30), array(), 'Erdal', false, $now);
	pg_assert_same('', $member['seen']);
	pg_assert_same('', $member['seen_state']);

	$manager = ws_channel_info_guest(ws_guest_channel_info_test_row('write', $now - 30), array(), 'Erdal', true, $now);
	pg_assert_same('on', $manager['seen_state']);
	pg_assert_same(true, $manager['seen'] !== '');

	$earlier = ws_channel_info_guest(ws_guest_channel_info_test_row('write', $now - 7200), array(), 'Erdal', true, $now);
	pg_assert_same('', $earlier['seen_state']);
	pg_assert_same(true, $earlier['seen'] !== '');
}
