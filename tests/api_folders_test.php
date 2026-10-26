<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's folder rules in
 * includes/api/resources/folders.php: the access a folder is served under,
 * what counts as opening it up, and the walk that keeps a folder from being
 * moved into its own branch.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/folders.php');

// Top folder 1 (public); 10 private below it, 11 inheriting below 10, 12
// inheriting below 11; 20 inheriting straight below the top.
function api_folders_test_tree()
{
	return array(
		1  => array('parent' => 0,  'access' => 'public'),
		10 => array('parent' => 1,  'access' => 'private'),
		11 => array('parent' => 10, 'access' => ''),
		12 => array('parent' => 11, 'access' => ''),
		20 => array('parent' => 1,  'access' => ''),
	);
}

function test_api_folders_access_is_inherited_from_the_nearest_folder_that_sets_one()
{
	$tree = api_folders_test_tree();

	pg_assert_same('private', api_folder_access_walk($tree, 12), 'two levels below a private folder');
	pg_assert_same('public', api_folder_access_walk($tree, 20), 'below the public top folder');
	pg_assert_same('public', api_folder_access_walk($tree, 999), 'an unknown folder reads as the root does');
}

function test_api_folders_access_walk_answers_for_the_tree_after_a_change()
{
	$tree = api_folders_test_tree();

	// 11 moved under 20: it now inherits public.
	pg_assert_same('public', api_folder_access_walk($tree, 11, array(11 => array('parent' => 20, 'access' => ''))), 'moved out from under the private folder');

	// 20 given its own type.
	pg_assert_same('membership', api_folder_access_walk($tree, 20, array(20 => array('parent' => 1, 'access' => 'membership'))), 'own type wins');
}

function test_api_folders_access_walk_survives_a_broken_chain()
{
	$tree = array(
		5 => array('parent' => 6, 'access' => ''),
		6 => array('parent' => 5, 'access' => ''),
	);

	pg_assert_same('public', api_folder_access_walk($tree, 5), 'a parent loop ends instead of spinning');
}

function test_api_folders_only_a_more_open_type_widens()
{
	pg_assert_same(true, api_folder_access_widens('private', 'public'), 'private to public');
	pg_assert_same(true, api_folder_access_widens('membership', 'registration'), 'membership to registration');
	pg_assert_same(false, api_folder_access_widens('public', 'guest'), 'public to guest narrows');
	pg_assert_same(false, api_folder_access_widens('registration', 'registration'), 'unchanged');
	pg_assert_same(true, api_folder_access_widens('', 'membership'), 'an unknown type before counts as the most closed');
	pg_assert_same(true, api_folder_access_widens('guest', 'nonsense'), 'an unknown type after is never taken for safe');
}

function test_api_folders_within_finds_the_folder_and_its_branch()
{
	$tree = api_folders_test_tree();

	pg_assert_same(true, api_folder_within($tree, 10, 10), 'the folder itself');
	pg_assert_same(true, api_folder_within($tree, 12, 10), 'two levels down');
	pg_assert_same(false, api_folder_within($tree, 20, 10), 'a sibling branch');
	pg_assert_same(false, api_folder_within($tree, 1, 10), 'its parent');
}

// aclfolder rows by folder and user, as api_folder_acl() loads them: user 7
// reads 10, user 8 edits 10, user 9 reads 20 until 2026-12-31 and is shut out
// of 12 by an expired row.
function api_folders_test_acl()
{
	return array(
		10 => array(
			7 => array('rights' => 1, 'expires' => '0000-00-00'),
			8 => array('rights' => 2, 'expires' => '0000-00-00'),
		),
		12 => array(
			9 => array('rights' => 1, 'expires' => '2026-01-01'),
		),
		20 => array(
			9 => array('rights' => 1, 'expires' => '2026-12-31'),
		),
	);
}

function test_api_folders_readers_come_from_the_nearest_row_of_each_user()
{
	$tree = api_folders_test_tree();
	$acl = api_folders_test_acl();

	pg_assert_same(array(7 => '9999-12-31', 8 => '9999-12-31'), api_folder_readers_walk($tree, $acl, 11, '2026-10-26'), 'inherited from 10');
	pg_assert_same(array(7 => '9999-12-31', 8 => '9999-12-31'), api_folder_readers_walk($tree, $acl, 12, '2026-10-26'), 'an expired row shuts its user out and stops the walk for them only');
	pg_assert_same(array(9 => '2026-12-31'), api_folder_readers_walk($tree, $acl, 20, '2026-10-26'), 'a row with an end date');
	pg_assert_same(array(), api_folder_readers_walk($tree, $acl, 20, '2027-01-01'), 'that row once it has ended');
	pg_assert_same(array(9 => '2026-12-31'), api_folder_readers_walk($tree, $acl, 11, '2026-10-26', array(11 => array('parent' => 20, 'access' => ''))), 'after 11 is moved under 20');
}

function test_api_folders_readers_widen_when_a_user_gains_or_keeps_access_longer()
{
	$both = array(7 => '9999-12-31', 8 => '9999-12-31');

	pg_assert_same(false, api_folder_readers_widen($both, $both), 'the same list');
	pg_assert_same(false, api_folder_readers_widen($both, array(7 => '9999-12-31')), 'fewer readers');
	pg_assert_same(false, api_folder_readers_widen($both, array()), 'nobody but managers');
	pg_assert_same(true, api_folder_readers_widen($both, array(7 => '9999-12-31', 9 => '9999-12-31')), 'a reader the source does not have');
	pg_assert_same(true, api_folder_readers_widen(array(9 => '2026-12-31'), array(9 => '9999-12-31')), 'the same reader for longer');
	pg_assert_same(false, api_folder_readers_widen(array(9 => '9999-12-31'), array(9 => '2026-12-31')), 'the same reader for less long');
}
