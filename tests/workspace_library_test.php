<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the folders a person may pick a file of the file manager from
 * when it goes into a Workspace channel (includes/workspace/library.php):
 * a branch of the folder tree, the closed branches taken out of what was
 * granted, where the picker starts, and the way down to a folder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/library.php');

// A tree as the folder table holds it, folder id => parent id:
//
//   1 Files
//   ├── 2 Documents
//   │   └── 3 Contracts
//   │       └── 4 2026
//   ├── 5 Workspace
//   │   └── 6 General
//   ├── 7 Recycle Bin
//   │   └── 8 Old
//   └── 9 Pictures
//       └── 10 Archived shoot
//           └── 11 Day one
function pg_test_workspace_library_tree()
{
	return array(1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 1, 6 => 5, 7 => 1, 8 => 7, 9 => 1, 10 => 9, 11 => 10);
}

function test_workspace_library_branch_takes_everything_below()
{
	$tree = pg_test_workspace_library_tree();

	pg_assert_same(array(2, 3, 4), pg_test_workspace_library_ids(ws_library_branch($tree, array(2))), 'a folder and all below it');
	pg_assert_same(array(5, 6, 7, 8), pg_test_workspace_library_ids(ws_library_branch($tree, array(5, 7))), 'two branches at once');
	pg_assert_same(array(6), pg_test_workspace_library_ids(ws_library_branch($tree, array(6, 99, 0))), 'ids that are not folders are left out');
	pg_assert_same(array(), ws_library_branch($tree, array()), 'no roots, no branch');
}

function test_workspace_library_branch_survives_a_loop()
{
	// A parent pointing back down: the walk must end.
	$tree = array(1 => 0, 2 => 3, 3 => 2);

	pg_assert_same(array(2, 3), pg_test_workspace_library_ids(ws_library_branch($tree, array(2))), 'each folder once');
}

function test_workspace_library_open_set_takes_closed_branches_out()
{
	$tree = pg_test_workspace_library_tree();

	// Staff: every folder granted; the Workspace folder, the bin and an
	// archived folder closed with all below them.
	$open = ws_library_open_set($tree, array_keys($tree), array(5, 7, 10));

	pg_assert_same(array(1, 2, 3, 4, 9), pg_test_workspace_library_ids($open), 'staff');

	// A user granted Contracts (and so 2026) and the Workspace's General.
	$open = ws_library_open_set($tree, array(3, 4, 6), array(5, 7, 10));

	pg_assert_same(array(3, 4), pg_test_workspace_library_ids($open), 'a user keeps only what is granted and not closed');

	// A grant of a folder that no longer exists is no folder.
	pg_assert_same(array(), ws_library_open_set($tree, array(42), array()), 'a missing folder is not open');

	// Nothing closed: the grant as it is.
	pg_assert_same(array(9, 10, 11), pg_test_workspace_library_ids(ws_library_open_set($tree, array('9', '10', '11'), array(0))), 'string ids read as numbers; 0 closes nothing');
}

function test_workspace_library_tops_are_where_the_picker_starts()
{
	$tree = pg_test_workspace_library_tree();

	pg_assert_same(array(1), ws_library_tops($tree, ws_library_open_set($tree, array_keys($tree), array(5, 7))), 'staff start at the top folder');
	pg_assert_same(array(3, 9), ws_library_tops($tree, ws_library_open_set($tree, array(3, 4, 9, 10, 11), array())), 'a user starts at the top of each grant');
	$open = ws_library_open_set($tree, array(3, 4, 9, 10, 11), array(10));

	pg_assert_same(array(3, 4, 9), pg_test_workspace_library_ids($open), 'a closed folder takes what is below it along');
	pg_assert_same(array(3, 9), ws_library_tops($tree, $open), 'and what is left starts where it did');
}

function test_workspace_library_trail_stops_where_the_person_cannot_go()
{
	$tree = pg_test_workspace_library_tree();
	$staff = ws_library_open_set($tree, array_keys($tree), array(5, 7));
	$user = ws_library_open_set($tree, array(3, 4), array());

	pg_assert_same(array(1, 2, 3, 4), ws_library_trail($tree, $staff, 4), 'staff walk down from the top');
	pg_assert_same(array(3, 4), ws_library_trail($tree, $user, 4), 'a user from the top of the grant');
	pg_assert_same(array(), ws_library_trail($tree, $user, 2), 'a folder the person cannot open has no trail');
	pg_assert_same(array(), ws_library_trail($tree, $staff, 6), 'nor has one in a closed branch');
}

// The ids of a set (id => true), in order, for comparing.
function pg_test_workspace_library_ids($set)
{
	$ids = array_keys($set);
	sort($ids);

	return $ids;
}
