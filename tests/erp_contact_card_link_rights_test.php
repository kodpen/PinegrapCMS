<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contact card of the ERP account form
 * (erp_account_form_contact_summary(), includes/erp/account_form.php): the
 * Contact, order count and User buttons link only when the target screen
 * admits the viewer, and erp_contact_summary() reads the role the user check
 * needs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_erp_contact_card_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/account_form.php');
	$start = strpos($source, 'function erp_account_form_contact_summary(');
	$end = strpos($source, 'function erp_account_form_contact_card(');
	pg_assert_true(($start !== false) && ($end !== false) && ($end > $start), 'the card function is found');

	return substr($source, $start, $end - $start);
}

function test_erp_contact_card_checks_the_contact_screen_door()
{
	$source = pg_test_erp_contact_card_source();
	pg_assert_contains('$can_open_contact = pg_can_open_contact(null, (int) $contact[\'id\']);', $source, 'the contact rights are asked once');

	foreach (array('href="edit_contact.php', 'href="view_orders_for_contact.php') as $link) {
		$at = strpos($source, $link);
		pg_assert_true($at !== false, $link . ' is still offered');
		$before = substr($source, max(0, $at - 400), 400);
		pg_assert_contains('$can_open_contact', $before, $link . ' sits behind the contact rights');
	}
}

function test_erp_contact_card_checks_the_user_screen_door()
{
	$source = pg_test_erp_contact_card_source();
	$at = strpos($source, 'href="edit_user.php');
	pg_assert_true($at !== false, 'the user link is still offered');
	pg_assert_contains('pg_can_open_user($viewer, $contact[\'user_role\']', substr($source, max(0, $at - 300), 300), 'the user link sits behind pg_can_open_user()');
}

function test_erp_contact_summary_reads_the_user_role()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/accounts.php');
	pg_assert_contains('SELECT user_id, user_username, user_role FROM user WHERE user_contact', $source, 'the role of the user behind the contact is read');
	pg_assert_contains("'user_role' => is_array(\$user) ? (int) \$user['user_role'] : 3,", $source, 'and handed to the card');
}
