<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the users list's quick edit (includes/fn/user_list_edit.php):
 * which cells are fields for whom and in which view, and what one change
 * decides by the user screen's rules.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

function test_user_quick_edit_columns_follow_the_editor_role()
{
	$modules = array('calendars' => true, 'forms' => true, 'ecommerce' => true);
	$pages = array('0' => '[None]', '4' => 'Home');

	$admin = pg_user_quick_edit_columns(0, 'all_my_users', $pages, $modules);

	pg_assert_same(array('username', 'email', 'role', 'start_page', 'manage_calendars', 'manage_forms', 'view_visitors', 'manage_contacts', 'manage_email', 'manage_ecommerce'), array_keys($admin), 'an administrator changes every value of the user screen the list shows');
	pg_assert_same(array('0', '1', '2', '3'), array_map('strval', array_keys($admin['role']['options'])), 'an administrator hands out every role');
	pg_assert_true(!empty($admin['username']['required']) && ($admin['username']['max'] === 100), 'a username is required, at most 100 characters');
	pg_assert_same($pages, $admin['start_page']['options'], 'the start pages offered');

	pg_assert_same(array('2', '3'), array_map('strval', array_keys(pg_user_quick_edit_columns(1, 'default', null, $modules)['role']['options'])), 'a designer hands out the roles below their own');

	$manager = pg_user_quick_edit_columns(2, 'default', null, $modules);

	pg_assert_false(isset($manager['role']), 'a manager sets no role, as on the user screen');
	pg_assert_false(isset($manager['start_page']), 'no start page choices for a view without the column');
	pg_assert_same(array(), pg_user_quick_edit_columns(3, 'default', null, $modules), 'a User may not open the list');

	$off = pg_user_quick_edit_columns(0, 'default', null, array());

	pg_assert_false(isset($off['manage_calendars']) || isset($off['manage_forms']) || isset($off['manage_ecommerce']), 'a module that is off takes its right along');
	pg_assert_true(isset($off['view_visitors'], $off['manage_contacts'], $off['manage_email']), 'the rights without a module stay');
}

function test_user_list_marks_the_editable_columns()
{
	$editable = function ($filter) {
		$keys = array();

		foreach (pg_list_table_columns(pg_user_list_columns($filter)) as $key => $column) {
			if ($column['editable'] !== '') {
				$keys[] = $key;
			}
		}

		return $keys;
	};

	pg_assert_same(array('username', 'email', 'start_page'), $editable('all_my_users'));
	pg_assert_same(array('username', 'email', 'start_page', 'manage_email'), $editable('my_contact_managers'), 'Send Campaigns is a switch there; the group names stay text');
	pg_assert_same(array('username', 'email', 'start_page'), $editable('my_campaign_managers'), 'Send Campaigns Only is computed from two rights');
	pg_assert_same(array('username', 'email', 'start_page'), $editable('my_calendar_managers'), 'calendar names stay text');

	// Every other column of the default view is computed: the role's site
	// rights, the folder rights, the member link and the times.
	$default = pg_list_table_columns(pg_user_list_columns('default'));
	$text = array('private_folder', 'member_user', 'manage_content', 'site_manager', 'site_designer', 'last_online', 'last_modified', 'actions');

	foreach ($default as $key => $column) {
		pg_assert_same(in_array($key, $text, true) ? '' : (($key === 'username') ? 'text' : 'select'), $column['editable'], $key);
	}
}

function test_user_quick_cells_are_fields_where_the_screen_sets_them()
{
	$columns = pg_user_quick_edit_columns(1, 'default', array('0' => '[None]', '4' => 'Home'), array('calendars' => true, 'forms' => true, 'ecommerce' => true));
	$user = array('role' => '3', 'username' => 'ali', 'email' => 'a@example.com', 'user_home' => '4', 'manage_contacts' => 'yes', 'manage_emails' => '');
	$manager = array('role' => '2') + $user;

	pg_assert_true(pg_user_quick_cell_editable('manage_contacts', $user, 'default', $columns), 'a User\'s right is a switch');
	pg_assert_false(pg_user_quick_cell_editable('manage_contacts', $manager, 'default', $columns), 'a manager has every right: the mark stays text');
	pg_assert_false(pg_user_quick_cell_editable('manage_contacts', $user, 'my_contact_managers', $columns), 'there the column names the groups');
	pg_assert_true(pg_user_quick_cell_editable('manage_email', $user, 'my_contact_managers', $columns), 'Send Campaigns is the right itself');
	pg_assert_false(pg_user_quick_cell_editable('manage_email', $user, 'my_campaign_managers', $columns), 'Send Campaigns Only is not');

	pg_assert_true(pg_user_quick_cell_editable('role', $manager, 'default', $columns), 'a designer moves a manager');
	pg_assert_false(pg_user_quick_cell_editable('role', array('role' => '1') + $user, 'default', $columns), 'but not another designer');
	pg_assert_true(pg_user_quick_cell_editable('start_page', $user, 'default', $columns), 'a start page among the choices');
	pg_assert_false(pg_user_quick_cell_editable('start_page', array('user_home' => '9') + $user, 'default', $columns), 'one the picker does not list stays text');

	pg_assert_same('1', pg_user_quick_edit_value('manage_contacts', $user));
	pg_assert_same('0', pg_user_quick_edit_value('manage_email', $user), 'Campaign Manager reads user_manage_emails');
	pg_assert_same('3', pg_user_quick_edit_value('role', $user));
	pg_assert_same('4', pg_user_quick_edit_value('start_page', $user));
}

function test_user_quick_change_follows_the_user_screen()
{
	$user = array('role' => '3', 'username' => 'ali', 'email' => 'a@example.com');
	$admin = array('role' => '0') + $user;

	pg_assert_same(array('ok' => true, 'column' => 'user_manage_contacts', 'value' => 'yes', 'message' => ''), pg_user_quick_change($user, 'manage_contacts', '1', 2, false));
	pg_assert_same('', pg_user_quick_change($user, 'manage_email', '0', 2, false)['value'], 'off stores an empty value, as the form does');
	pg_assert_same('user_manage_visitors', pg_user_quick_change($user, 'view_visitors', '1', 2, false)['column']);
	pg_assert_false(pg_user_quick_change(array('role' => '2') + $user, 'manage_contacts', '1', 1, false)['ok'], 'a manager has every right already');

	pg_assert_same(array('ok' => true, 'column' => 'user_role', 'value' => '2', 'message' => ''), pg_user_quick_change($user, 'role', '2', 1, false));
	pg_assert_false(pg_user_quick_change($user, 'role', '1', 1, false)['ok'], 'a designer makes no designer');
	pg_assert_false(pg_user_quick_change($user, 'role', 'abc', 0, false)['ok'], 'text is no role');
	pg_assert_false(pg_user_quick_change($admin, 'role', '3', 0, true)['ok'], 'the last administrator keeps the role');
	pg_assert_true(pg_user_quick_change($admin, 'role', '3', 0, false)['ok'], 'while another one is left, an administrator moves');

	pg_assert_false(pg_user_quick_change($user, 'username', str_repeat('a', 101), 0, false)['ok'], 'a username longer than its column');
	pg_assert_same('user_email', pg_user_quick_change($user, 'email', 'b@example.com', 0, false)['column']);
	pg_assert_same(array('ok' => true, 'column' => 'user_home', 'value' => '4', 'message' => ''), pg_user_quick_change($user, 'start_page', '4', 0, false));
	pg_assert_false(pg_user_quick_change($user, 'last_online', '1', 0, false)['ok'], 'a computed value is not written');
}

function test_user_quick_rights_take_their_grants_as_the_screen_does()
{
	pg_assert_same('users_contact_groups_xref', pg_user_quick_dropped_grants('manage_contacts', array('manage_contacts' => '', 'manage_emails' => ''), true), 'neither contacts nor campaigns: the groups go');
	pg_assert_same('', pg_user_quick_dropped_grants('manage_contacts', array('manage_contacts' => '', 'manage_emails' => 'yes'), true), 'campaigns keep the groups');
	pg_assert_same('users_contact_groups_xref', pg_user_quick_dropped_grants('manage_email', array('manage_contacts' => '', 'manage_emails' => ''), true));
	pg_assert_same('users_calendars_xref', pg_user_quick_dropped_grants('manage_calendars', array('manage_calendars' => ''), true));
	pg_assert_same('', pg_user_quick_dropped_grants('manage_calendars', array('manage_calendars' => ''), false), 'while Calendars is off the grants wait');
	pg_assert_same('', pg_user_quick_dropped_grants('manage_calendars', array('manage_calendars' => 'yes'), true), 'switched on, nothing goes');
	pg_assert_same('', pg_user_quick_dropped_grants('view_visitors', array(), true), 'a right without a list');
}
