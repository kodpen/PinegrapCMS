<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the writes of a panel account (includes/fn/user_write.php): the
 * role an editor may give, the page types a save writes, the problem
 * sentences, and that the three user screens write through the module.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');

function test_user_write_role_writable()
{
	foreach (array(0, 1, 2, 3) as $role) {
		pg_assert_true(pg_user_role_writable(0, $role), 'an administrator gives role ' . $role);
	}

	pg_assert_true(pg_user_role_writable('0', null), 'an administrator saves without a role');

	pg_assert_false(pg_user_role_writable(1, 0), 'a designer never makes an administrator');
	pg_assert_false(pg_user_role_writable(1, 1), 'nor another designer');
	pg_assert_true(pg_user_role_writable(1, 2));
	pg_assert_true(pg_user_role_writable('1', 3), 'the role read from the session row is text');
	pg_assert_false(pg_user_role_writable(2, 2), 'a manager never makes a manager');
	pg_assert_true(pg_user_role_writable(2, 3));
	pg_assert_false(pg_user_role_writable(2, null), 'a save without a role needs an administrator');
	pg_assert_false(pg_user_role_writable(3, 3));
}

function test_user_write_page_types()
{
	pg_assert_same(array('email_a_friend', 'folder_view', 'photo_gallery'), pg_user_write_page_types(false, false, false), 'the three that are always there');

	$all = pg_user_write_page_types(true, true, true);
	pg_assert_same(20, count($all), 'every page type with every module on');
	pg_assert_same(array(), array_diff(api_user_page_types_for_test(), $all), 'the same set the API reads');

	pg_assert_true(in_array('calendar_view', pg_user_write_page_types(false, true, false), true));
	pg_assert_false(in_array('catalog', pg_user_write_page_types(true, true, false), true), 'no commerce page type while commerce is off');
}

// The page types GET /users reads (includes/api/resources/users.php).
function api_user_page_types_for_test()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/users.php');
	$start = strpos($source, 'function api_user_page_types()');
	preg_match_all("/'([a-z_]+)'/", substr($source, $start, strpos($source, '}', $start) - $start), $match);

	return $match[1];
}

function test_user_write_problem_messages()
{
	foreach (array('username_required', 'email_invalid', 'username_taken', 'email_taken') as $problem) {
		pg_assert_true(pg_user_write_problem_message($problem) !== '', $problem . ' has a sentence');
	}

	pg_assert_same('', pg_user_write_problem_message(''));
}

function test_user_write_screens_use_the_module()
{
	foreach (array('add_user.php' => 'pg_user_create(', 'edit_user.php' => 'pg_user_update(', 'delete_users.php' => 'pg_user_delete(') as $file => $call) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("/includes/fn/user_write.php'", $source, $file . ' loads the module');
		pg_assert_contains($call, $source, $file . ' writes through ' . $call);
		pg_assert_false(strpos($source, 'INSERT INTO user ') !== false || strpos($source, "UPDATE user\n            SET\n                user_username") !== false, $file . ' has no account write of its own');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_user.php');
	pg_assert_contains('pg_user_delete($audit_user_id)', $edit, 'the edit screen deletes through the module');

	foreach (array('add_user.php', 'edit_user.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_user_role_writable($user[\'role\']', $source, $file . ' checks the role the shared way');
		pg_assert_contains('pg_user_write_problem($_POST[\'username\'], $_POST[\'email\']', $source, $file . ' checks the username and e-mail the shared way');
	}
}

// The workspace and read-only ERP rights are read from the fields handed in,
// not from $_POST, so the API can write them.
function test_user_write_rights_read_the_given_fields()
{
	$write = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');

	pg_assert_false(strpos($write, '$_POST') !== false, 'the module never reads $_POST');
	pg_assert_contains("pg_user_permission_ws_sql('set', \$fields)", $write);
	pg_assert_contains("pg_user_erp_readonly_sql('set', \$fields)", $write);
	pg_assert_contains("pg_user_permission_ws_sql('values', \$fields)", $write);
	pg_assert_contains("pg_user_erp_readonly_sql('values', \$fields)", $write);
}

// A deleted account's own connected accounts are ended with it, through the
// disconnect that revokes them, so a later account given the same id never
// signs in to the old owner's Drive, calendar or notes.
function test_user_delete_ends_personal_connections()
{
	$write = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');
	$delete = substr($write, (int) strpos($write, 'function pg_user_delete('));

	pg_assert_contains('pg_connect_forget_user($user_id)', $delete, 'pg_user_delete() ends the account\'s connections');

	$connect = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/connect.php');
	$forget = substr($connect, (int) strpos($connect, 'function pg_connect_forget_user('));
	$forget = substr($forget, 0, (int) strpos($forget, "\n}\n"));

	pg_assert_contains("pg_connect_accounts(array('user_id' => \$user_id))", $forget, 'only the person\'s own rows');
	pg_assert_contains('pg_connect_disconnect($account[\'id\'])', $forget, 'each is revoked and deleted');
	pg_assert_contains('$user_id <= 0', $forget, 'user 0 (the site\'s accounts) is never ended');
}
