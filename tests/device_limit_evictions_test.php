<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_device_limit_pick_evictions(): which sessions the device limit
 * signs out when a member confirms a new device. Locked sessions count toward
 * the room kept but are never chosen; when they leave too little room,
 * nothing is chosen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Rows newest first, as the query ranks them: name => pinned.
function device_limit_evictions_test_rows(array $rows)
{
	$out = array();
	foreach ($rows as $selector => $pinned) {
		$out[] = array('selector' => $selector, 'pinned' => $pinned);
	}
	return $out;
}

function test_device_limit_signs_out_the_oldest_unpinned_sessions()
{
	$rows = device_limit_evictions_test_rows(array('a' => 0, 'b' => 0, 'c' => 0));
	pg_assert_same(array('c', 'b'), pg_device_limit_pick_evictions($rows, 1), 'limit 2, three sessions');
	pg_assert_same(array(), pg_device_limit_pick_evictions($rows, 3), 'room already');
}

function test_device_limit_counts_a_locked_session_toward_the_room_kept()
{
	// Limit 2: A newest, B locked. A goes, B stays.
	$rows = device_limit_evictions_test_rows(array('a' => 0, 'b' => 1));
	pg_assert_same(array('a'), pg_device_limit_pick_evictions($rows, 1), 'locked older session');

	// A newest, B locked, D oldest: both A and D go.
	$rows = device_limit_evictions_test_rows(array('a' => 0, 'b' => 1, 'd' => 0));
	pg_assert_same(array('d', 'a'), pg_device_limit_pick_evictions($rows, 1), 'locked middle session');
}

function test_device_limit_signs_out_nothing_when_too_many_are_locked()
{
	$rows = device_limit_evictions_test_rows(array('a' => 1, 'b' => 1));
	pg_assert_same(array(), pg_device_limit_pick_evictions($rows, 1), 'all locked');

	// Limit 2 with two locked and one free: freeing the one is not enough.
	$rows = device_limit_evictions_test_rows(array('a' => 1, 'b' => 0, 'c' => 1));
	pg_assert_same(array(), pg_device_limit_pick_evictions($rows, 1), 'not enough room to free');
}

function test_device_limit_reads_a_row_without_pinned_as_unpinned()
{
	pg_assert_same(array('b'), pg_device_limit_pick_evictions(array(array('selector' => 'a'), array('selector' => 'b')), 1), 'no pin column');
}
