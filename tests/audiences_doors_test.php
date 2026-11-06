<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the hand doors of contact groups filled by rules
 * (includes/fn/audience_doors.php): the id split, the SQL test, and that
 * every door that writes a membership by hand asks the guard first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_audiences_doors_split_keeps_hand_groups_in_order()
{
	pg_assert_same(
		array('hand' => array(5, 2), 'rule' => array(7)),
		pg_audience_hand_ids(array('5', 7, '2'), array(7, 9))
	);
}

function test_audiences_doors_split_drops_bad_and_repeated_ids()
{
	pg_assert_same(
		array('hand' => array(3), 'rule' => array(4)),
		pg_audience_hand_ids(array('', '0', -2, 'x', '3 OR 1=1', array(1), '3', 3, ' 4 ', 4, null), array('4'))
	);
}

function test_audiences_doors_split_without_rule_groups_keeps_all()
{
	pg_assert_same(
		array('hand' => array(1, 2), 'rule' => array()),
		pg_audience_hand_ids(array(1, 2), array())
	);
	pg_assert_same(array('hand' => array(), 'rule' => array()), pg_audience_hand_ids(array(), array(1)));
}

function test_audiences_doors_where_before_and_after_the_upgrade()
{
	pg_assert_same('(1 = 1)', pg_audience_hand_where(false));
	pg_assert_same("(kind <> 'dynamic')", pg_audience_hand_where(true));
	pg_assert_same("(contact_groups.kind <> 'dynamic')", pg_audience_hand_where(true, 'contact_groups.kind'));
	pg_assert_same('(1 = 1)', pg_audience_hand_where(true, "kind) OR (1"), 'a column that is not a name is never written');
}

function test_audiences_doors_message_is_one_text()
{
	pg_assert_true(pg_audience_door_message() !== '', 'message');
}

// Every hand door names the guard; a new door that writes a membership
// without it fails here.
function test_audiences_doors_every_hand_door_asks_the_guard()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$doors = array(
		'edit_contacts.php' => 'pg_audience_hand_ids(',
		'import_contacts.php' => 'pg_audience_hand_sql(',
		'import_users.php' => 'pg_audience_hand_sql(',
		'custom_form.php' => 'pg_audience_hand_sql(',
		'submit_order.php' => 'pg_audience_group_is_dynamic(',
		'express_order.php' => 'pg_audience_group_is_dynamic(',
		'billing_information.php' => 'pg_audience_group_is_dynamic(',
		'affiliate_sign_up_form.php' => 'pg_audience_hand_sql(',
		'email_preferences.php' => 'pg_audience_door_join(',
		'my_account_profile.php' => 'pg_audience_door_join(',
		'includes/fn/order_completion.php' => 'pg_audience_hand_sql(',
		'includes/fn/form_subscription.php' => 'pg_audience_group_is_dynamic(',
		'includes/fn/signin.php' => 'pg_audience_door_join(',
		'includes/fn/auth.php' => 'pg_audience_door_join(',
		'includes/fn/contacts.php' => 'pg_audience_door_join(',
		'includes/api/resources/contact_groups.php' => 'api_contact_group_hand_or_fail(',
		'includes/api/resources/customers.php' => 'api_contact_group_hand_or_fail(',
	);

	foreach ($doors as $file => $guard) {
		$source = (string) file_get_contents($root . $file);
		pg_assert_true(strpos($source, $guard) !== false, $file . ' asks ' . $guard);
	}
}
