<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contact delete refusal (includes/fn/contacts.php): a contact
 * a user account holds, or one an ERP account is linked to, is not deleted,
 * on Edit Contact, the contacts list, Modify Contacts and the external API
 * alike.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_contact_delete_refusal_list()
{
	$users = array(array('contact_id' => '5', 'username' => 'admin'));
	$erp = array(array('contact_id' => '8', 'title' => 'ACME Ltd'), array('contact_id' => '5', 'title' => 'Admin Ledger'));

	$refusals = pg_contact_delete_refusal_list(array(5, 6, 8), $users, $erp);

	pg_assert_same(array(5, 8), array_keys($refusals), 'the held contacts, not the free one');
	pg_assert_same('account', $refusals[5]['reason'], 'a user account wins over an ERP link');
	pg_assert_same('admin', $refusals[5]['holder']);
	pg_assert_same('erp', $refusals[8]['reason']);
	pg_assert_same('ACME Ltd', $refusals[8]['holder']);
	pg_assert_same(array(), pg_contact_delete_refusal_list(array(6), $users, $erp), 'nothing holds it');
	pg_assert_same(array(), pg_contact_delete_refusal_list(array(), $users, $erp), 'no ids');
}

function test_contact_delete_paths_ask_the_refusal()
{
	foreach (array('edit_contact.php', 'edit_contacts.php', 'view_contacts.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$ask = strpos($source, 'pg_contact_delete_refusals(');

		pg_assert_true($ask !== false, $file . ': asks');
		pg_assert_true(preg_match('/DELETE FROM contacts\s+WHERE/', $source, $m, PREG_OFFSET_CAPTURE) === 1, $file . ': deletes');
		pg_assert_true($ask < $m[0][1], $file . ': before the delete');
	}

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/customers.php');
	pg_assert_contains('pg_contact_delete_refusals(array($id))', $api, 'the API asks the same rule');
	pg_assert_contains("'customer_has_account'", $api);
	pg_assert_contains("'customer_has_erp_account'", $api);
}
