<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_audience_door_join() (includes/fn/audience_doors.php): the
 * storefront doors that put a new account's contact into the registration
 * or membership group announce the join, so "joined a group" campaign
 * profiles, flows and the contact_group.members_changed webhook hear a
 * sign-up as they hear every other door.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Only a row the door wrote is a join: a contact already in the group, a
// group filled by rules or gone, and a failed insert announce nothing.
function test_audiences_door_join_announces_only_a_new_row()
{
	pg_assert_same(array(5), pg_audience_door_joined(5, 9, true, false, 1), 'a new membership row');
	pg_assert_same(array(), pg_audience_door_joined(5, 9, true, true, 0), 'already a member: nothing written');
	pg_assert_same(array(), pg_audience_door_joined(5, 9, false, false, 0), 'group filled by rules or gone');
	pg_assert_same(array(), pg_audience_door_joined(5, 9, true, false, -1), 'failed insert');
	pg_assert_same(array(), pg_audience_door_joined(0, 9, true, false, 1), 'no contact');
	pg_assert_same(array(), pg_audience_door_joined(5, 0, true, false, 1), 'no group');
}

// The sign-up, order, checkout and affiliate doors join through the helper, never with their own insert,
// so none of them skips the announce again.
function test_audiences_door_join_sign_up_doors_use_the_helper()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$doors = array(
		'includes/fn/auth.php' => 2,
		'includes/fn/signin.php' => 1,
		'includes/fn/contacts.php' => 1,
		'my_account_profile.php' => 1,
		'email_preferences.php' => 2,
		'includes/fn/order_completion.php' => 2,
		'submit_order.php' => 1,
		'billing_information.php' => 1,
		'express_order.php' => 1,
		'affiliate_sign_up_form.php' => 1,
	);

	foreach ($doors as $file => $count) {
		$source = (string) file_get_contents($root . $file);
		pg_assert_same($count, substr_count($source, 'pg_audience_door_join('), $file . ' joins through pg_audience_door_join()');
	}

	// The order, checkout and affiliate doors keep no insert of their own.
	foreach (array('includes/fn/order_completion.php', 'submit_order.php', 'billing_information.php', 'express_order.php', 'affiliate_sign_up_form.php') as $file) {
		$source = (string) file_get_contents($root . $file);
		pg_assert_true(strpos($source, 'INSERT INTO contacts_contact_groups_xref') === false, $file . ' inserts no membership row itself');
	}
}
