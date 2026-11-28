<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reading a Workspace channel's file groups as one message
 * (includes/fn/ws_file_groups.php): the head a file stands under, a page
 * made whole, counting a group once and a forward taking the group.
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

function test_ws_file_group_target_id_lands_on_the_head()
{
	pg_assert_same(41, ws_file_group_target_id(array('id' => 41, 'file_group' => 0)), 'alone');
	pg_assert_same(40, ws_file_group_target_id(array('id' => 40, 'file_group' => 40)), 'the head');
	pg_assert_same(40, ws_file_group_target_id(array('id' => 43, 'file_group' => 40)), 'a member');
}

function test_ws_file_group_is_member_leaves_the_head_out()
{
	pg_assert_false(ws_file_group_is_member(array('id' => 41, 'file_group' => 0)), 'alone');
	pg_assert_false(ws_file_group_is_member(array('id' => 40, 'file_group' => 40)), 'the head');
	pg_assert_true(ws_file_group_is_member(array('id' => 43, 'file_group' => 40)), 'a member');
	pg_assert_false(ws_file_group_is_member(array('id' => 43)), 'a row from before the column');
}

function test_ws_file_group_heads_of_lists_each_group_once()
{
	pg_assert_same(array(40, 50), ws_file_group_heads_of(array(
		array('id' => 39, 'file_group' => 0),
		array('id' => 41, 'file_group' => 40),
		array('id' => 42, 'file_group' => 40),
		array('id' => 50, 'file_group' => 50),
		array('id' => 51),
	)));
}

function test_ws_file_group_merge_adds_the_cut_rows_in_order()
{
	$page = array(array('id' => 42, 'file_group' => 40), array('id' => 44, 'file_group' => 0));
	$extra = array(array('id' => 40, 'file_group' => 40), array('id' => 41, 'file_group' => 40), array('id' => 42, 'file_group' => 40), array('id' => 43, 'file_group' => 40));
	$ids = array_map(function ($row) {
		return $row['id'];
	}, ws_file_group_merge($page, $extra));

	pg_assert_same(array(40, 41, 42, 43, 44), $ids);
}

function test_ws_file_group_expand_ids_takes_the_whole_group()
{
	$groups = array(40 => array(40, 41, 42));
	$heads = array(40 => 40, 41 => 40, 42 => 40);

	pg_assert_same(array(40, 41, 42, 45), ws_file_group_expand_ids(array(40, 45), $groups, $heads, 20), 'the head chosen');
	pg_assert_same(array(30, 40, 41, 42), ws_file_group_expand_ids(array(30, 41, 42), $groups, $heads, 20), 'members chosen, each once');
	pg_assert_same(array(30, 40), ws_file_group_expand_ids(array(30, 41), $groups, $heads, 2), 'bounded');
	pg_assert_same(array(45), ws_file_group_expand_ids(array(45), $groups, array(), 20), 'alone');
}
