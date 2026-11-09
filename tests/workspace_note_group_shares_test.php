<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for shared note groups (includes/workspace/note_group_shares.php):
 * which ws_note_shares rows a shared group wants, how they are laid over the
 * rows there are, who reaches a group and who may put a note in, move it or
 * take it out.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/note_groups.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/note_group_shares.php');

function test_workspace_note_group_share_wanted_rows()
{
	$group = array('id' => 7, 'user_id' => 1, 'locked' => 0);
	$notes = array(
		array('note_id' => 10, 'placed_by' => 1, 'owner' => 1, 'locked' => 0),
		array('note_id' => 11, 'placed_by' => 2, 'owner' => 2, 'locked' => 0),
	);
	$shares = array(
		array('user_id' => 2, 'channel_id' => 0, 'can_edit' => 1),
		array('user_id' => 3, 'channel_id' => 0, 'can_edit' => 0),
		array('user_id' => 0, 'channel_id' => 40, 'can_edit' => 0),
	);

	$wanted = ws_note_group_share_wanted($group, $notes, $shares);

	pg_assert_same(array('10:u2', '10:u3', '10:c40', '11:u1', '11:u3', '11:c40'), array_keys($wanted), 'each note to each person and channel but its owner');
	pg_assert_same(1, $wanted['10:u2']['can_edit'], 'a person with edit changes the notes');
	pg_assert_same(0, $wanted['10:u3']['can_edit'], 'a reader reads');
	pg_assert_same(1, $wanted['11:u1']['can_edit'], 'the owner of the group changes a member\'s note');
	pg_assert_same(0, $wanted['10:c40']['can_edit'], 'a channel reads');
	pg_assert_same(array('note_id' => 10, 'user_id' => 0, 'channel_id' => 40, 'can_edit' => 0), $wanted['10:c40'], 'row shape');
}

function test_workspace_note_group_share_wanted_leaves_out()
{
	$group = array('id' => 7, 'user_id' => 1, 'locked' => 0);
	$shares = array(array('user_id' => 2, 'channel_id' => 0, 'can_edit' => 0));

	pg_assert_same(array(), ws_note_group_share_wanted($group, array(array('note_id' => 10, 'placed_by' => 1, 'owner' => 1, 'locked' => 1)), $shares), 'a locked note is never shared');
	pg_assert_same(array(), ws_note_group_share_wanted($group, array(array('note_id' => 10, 'placed_by' => 1, 'owner' => 5, 'locked' => 0)), $shares), 'a note put in by somebody who does not own it is not shared');
	pg_assert_same(array(), ws_note_group_share_wanted(array('id' => 7, 'user_id' => 1, 'locked' => 1), array(array('note_id' => 10, 'placed_by' => 1, 'owner' => 1, 'locked' => 0)), $shares), 'a locked group shares nothing');
	pg_assert_same(array(), ws_note_group_share_wanted($group, array(array('note_id' => 10, 'placed_by' => 1, 'owner' => 1, 'locked' => 0)), array()), 'a group shared with nobody');

	$twice = ws_note_group_share_wanted($group, array(array('note_id' => 10, 'placed_by' => 1, 'owner' => 1, 'locked' => 0)), array(
		array('user_id' => 2, 'channel_id' => 0, 'can_edit' => 0),
		array('user_id' => 2, 'channel_id' => 0, 'can_edit' => 1),
	));

	pg_assert_same(array('10:u2'), array_keys($twice), 'a person shared twice is one row');
	pg_assert_same(1, $twice['10:u2']['can_edit'], 'with the higher right');
}

function test_workspace_note_group_share_diff()
{
	$wanted = array(
		'10:u2'  => array('note_id' => 10, 'user_id' => 2, 'channel_id' => 0, 'can_edit' => 1),
		'10:c40' => array('note_id' => 10, 'user_id' => 0, 'channel_id' => 40, 'can_edit' => 0),
		'11:u2'  => array('note_id' => 11, 'user_id' => 2, 'channel_id' => 0, 'can_edit' => 0),
	);
	$existing = array(
		array('id' => 100, 'note_id' => '10', 'user_id' => '2', 'channel_id' => '0', 'can_edit' => '0'),
		array('id' => 101, 'note_id' => '11', 'user_id' => '2', 'channel_id' => '0', 'can_edit' => '0'),
		array('id' => 102, 'note_id' => '12', 'user_id' => '2', 'channel_id' => '0', 'can_edit' => '1'),
		array('id' => 103, 'note_id' => '11', 'user_id' => '2', 'channel_id' => '0', 'can_edit' => '0'),
	);

	$diff = ws_note_group_share_diff($wanted, $existing);

	pg_assert_same(array('10:c40'), array_keys($diff['insert']), 'missing rows are written');
	pg_assert_same(array(100 => 1), $diff['update'], 'a changed right is changed in place');
	pg_assert_same(array(102, 103), array_map('intval', array_column($diff['delete'], 'id')), 'rows not wanted and repeats go');
	pg_assert_same(array('insert' => array(), 'update' => array(), 'delete' => array()), ws_note_group_share_diff(array(), array()), 'nothing to do');
}

function test_workspace_note_group_reach()
{
	$group = array('id' => 7, 'user_id' => 1);
	$shares = array(
		array('user_id' => 2, 'channel_id' => 0, 'can_edit' => 1),
		array('user_id' => 3, 'channel_id' => 0, 'can_edit' => 0),
		array('user_id' => 0, 'channel_id' => 40, 'can_edit' => 0),
	);

	pg_assert_same('owner', ws_note_group_reach_of(1, $group, $shares, array()), 'the owner');
	pg_assert_same('edit', ws_note_group_reach_of(2, $group, $shares, array(40)), 'edit by name wins over a channel');
	pg_assert_same('view', ws_note_group_reach_of(3, $group, $shares, array()), 'read by name');
	pg_assert_same('view', ws_note_group_reach_of(4, $group, $shares, array(40)), 'through a channel one may read');
	pg_assert_same('', ws_note_group_reach_of(4, $group, $shares, array(41)), 'not through a channel one may not read');
	pg_assert_same('', ws_note_group_reach_of(0, $group, $shares, array(40)), 'nobody');
}

function test_workspace_note_group_place_refusal()
{
	// reach, shared, own note, who put it in the group (0 not in it), viewer
	pg_assert_same('', ws_note_group_place_refusal('owner', false, false, 0, 1), 'a note shared with the owner into their group that nobody else has');
	pg_assert_same('own_only', ws_note_group_place_refusal('owner', true, false, 0, 1), 'only own notes into a shared group');
	pg_assert_same('', ws_note_group_place_refusal('owner', true, true, 0, 1), 'the owner\'s own note into their shared group');
	pg_assert_same('', ws_note_group_place_refusal('owner', true, false, 2, 1), 'the owner moves or takes out a member\'s note');
	pg_assert_same('', ws_note_group_place_refusal('edit', true, true, 0, 2), 'a member with edit puts their own note in');
	pg_assert_same('own_only', ws_note_group_place_refusal('edit', true, false, 0, 2), 'not somebody else\'s');
	pg_assert_same('', ws_note_group_place_refusal('edit', true, true, 2, 2), 'a member moves their own note');
	pg_assert_same('not_yours', ws_note_group_place_refusal('edit', true, false, 1, 2), 'a member never moves the owner\'s note');
	pg_assert_same('no_group', ws_note_group_place_refusal('view', true, true, 0, 3), 'a reader puts nothing in');
	pg_assert_same('no_group', ws_note_group_place_refusal('', false, true, 0, 3), 'nor does a stranger');
}
