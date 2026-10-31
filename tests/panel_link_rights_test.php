<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_can_open_user() and pg_can_open_contact() in
 * includes/fn/auth.php: a user or contact name links to its edit screen only
 * when that screen would let the viewer in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');

function test_link_rights_administrator_opens_every_account()
{
	foreach (array(0, 1, 2, 3) as $target) {
		pg_assert_true(pg_can_open_user(array('role' => 0), $target), 'target ' . $target);
	}
}

function test_link_rights_designer_and_manager_open_only_lower_roles()
{
	pg_assert_false(pg_can_open_user(array('role' => 1), 0));
	pg_assert_false(pg_can_open_user(array('role' => 1), 1));
	pg_assert_true(pg_can_open_user(array('role' => 1), 2));
	pg_assert_true(pg_can_open_user(array('role' => 1), 3));
	pg_assert_false(pg_can_open_user(array('role' => 2), 0));
	pg_assert_false(pg_can_open_user(array('role' => 2), 1));
	pg_assert_false(pg_can_open_user(array('role' => 2), 2));
	pg_assert_true(pg_can_open_user(array('role' => 2), 3));
	pg_assert_true(pg_can_open_user(array('role' => '2'), '3'), 'string roles from a row');
}

function test_link_rights_user_role_never_opens_an_account()
{
	pg_assert_false(pg_can_open_user(array('role' => 3, 'manage_ecommerce' => true), 3));
	pg_assert_false(pg_can_open_user(array('role' => 3), 4));
}

function test_link_rights_staff_roles_open_every_contact_without_a_query()
{
	// Roles below 3 return before any database call, so this runs without one.
	foreach (array(0, 1, 2) as $role) {
		pg_assert_true(pg_can_open_contact(array('role' => $role), 5), 'role ' . $role);
		pg_assert_true(pg_can_open_contact(array('role' => $role), 5, false), 'role ' . $role . ' ignores a precomputed no');
	}
}

function test_link_rights_user_role_without_contacts_access_gets_no_link()
{
	// A precomputed "no contacts access" answers before the group lookup.
	pg_assert_false(pg_can_open_contact(array('role' => 3, 'manage_contacts' => false), 5, false));
	pg_assert_false(pg_can_open_contact(array('role' => 3, 'manage_contacts' => false), 5));
}

function test_link_rights_no_contact_gets_no_link()
{
	pg_assert_false(pg_can_open_contact(array('role' => 3), 0, true));
	pg_assert_false(pg_can_open_contact(array('role' => 0), ''));
}

function test_link_rights_view_order_guards_its_user_and_contact_links()
{
	$lines = file(PG_FUNCTIONS_DIR . '/view_order.php');
	$found = 0;

	foreach ($lines as $i => $line) {
		foreach (array('edit_user.php' => 'pg_can_open_user(', 'edit_contact.php' => 'pg_can_open_contact(') as $screen => $helper) {
			if (strpos($line, 'href="' . $screen) === false) {
				continue;
			}

			$found++;
			$context = implode('', array_slice($lines, max(0, $i - 2), 3));
			pg_assert_true(strpos($context, $helper) !== false, 'view_order.php:' . ($i + 1) . ' links ' . $screen . ' without ' . $helper . ')');
		}
	}

	pg_assert_true($found >= 3, 'the User, Contact and affiliate links are still there');
	pg_assert_true(strpos(implode('', $lines), 'user.user_role AS username_role') !== false, 'the order query reads the buyer account role');
}
