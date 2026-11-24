<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Hot Contacts view's "Followed by" column
 * (includes/fn/contact_list.php): the people holding an open follow-up task
 * of each contact, read for the whole page, and the cell that names them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Behind their gates these files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

function test_contact_list_followers_map_groups_open_tasks()
{
	$rows = array(
		array('contact_id' => '7', 'user_id' => '3', 'status' => 'todo'),
		array('contact_id' => '9', 'user_id' => '3', 'status' => 'doing'),
		array('contact_id' => '9', 'user_id' => '4', 'status' => 'waiting'),
		array('contact_id' => '9', 'user_id' => '3', 'status' => 'todo'),
		array('contact_id' => '9', 'user_id' => '5', 'status' => 'todo'),
		array('contact_id' => '9', 'user_id' => '6', 'status' => 'todo'),
		array('contact_id' => '11', 'user_id' => '8', 'status' => 'done'),
		array('contact_id' => '7', 'user_id' => '8', 'status' => 'cancelled'),
	);

	$map = pg_contact_list_followers_map($rows);

	pg_assert_same(array(3), $map[7], 'one open follow-up; the cancelled one left out');
	pg_assert_same(array(3, 4, 5, 6), $map[9], 'each person once, in the order of their first task');
	pg_assert_false(isset($map[11]), 'a done task no longer counts');
	pg_assert_same(array(), pg_contact_list_followers_map(array()), 'no follow-up');
}

function test_contact_list_followers_off_without_the_workspace()
{
	pg_assert_same(array(), pg_contact_list_followers(array(7, 9), false), 'the Workspace off reads nothing');
	pg_assert_same(array(), pg_contact_list_followers(array(), true), 'an empty page reads nothing');
}

function test_contact_list_followed_cell()
{
	$people = array(
		3 => array('name' => 'Ayşe Demir', 'initials' => 'AD', 'tone' => 1),
		4 => array('name' => 'Mehmet <b>Kaya</b>', 'initials' => 'MK', 'tone' => 2),
		5 => array('name' => 'Elif Şahin', 'initials' => 'EŞ', 'tone' => 0),
	);

	pg_assert_same('', pg_contact_list_followed_cell(array(), $people, 3), 'empty when nobody follows the contact up');

	$one = pg_contact_list_followed_cell(array(4), $people, 3);
	pg_assert_contains('Mehmet &lt;b&gt;Kaya&lt;/b&gt;', $one, 'the name escaped');
	pg_assert_contains('<span class="pg-initials pg-initials-3" aria-hidden="true">MK</span>', $one, 'the person\'s letters in their tone');
	pg_assert_false(strpos($one, '+') !== false, 'no rest count for one person');

	$three = pg_contact_list_followed_cell(array(4, 5, 3), $people, 3);
	pg_assert_true(strpos($three, h(lang('You'))) < strpos($three, 'Mehmet'), 'the reader first, as You');
	pg_assert_false(strpos($three, 'Ayşe') !== false, 'the reader\'s own name is not written');
	pg_assert_false(strpos($three, 'Elif') !== false, 'two chips at most');
	pg_assert_contains('>+1</span>', $three, 'then the rest as +N');

	pg_assert_contains(h(lang('Former member')), pg_contact_list_followed_cell(array(99), $people, 3), 'an account no longer there');
}

function test_contact_list_hot_followed_by_column()
{
	$picture = array('picture' => array('heading' => 'Image'));
	$on = pg_list_table_columns(pg_contact_list_hot_columns($picture, false, true));

	pg_assert_same(array('picture', 'contact', 'score', 'reason', 'last_visit', 'followed_by', 'actions'), array_keys($on), 'after Last visit');
	pg_assert_false($on['followed_by']['sortable'], 'not sortable');
	pg_assert_false(isset(pg_contact_list_hot_columns($picture, false, false)['followed_by']), 'not shown with the Workspace off');
}
