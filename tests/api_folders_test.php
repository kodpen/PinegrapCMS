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
