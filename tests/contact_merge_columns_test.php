<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the columns a contact merge reads (pg_contact_merge_select()):
 * every data column of the contact, so the contact that stays is filled
 * from the merged one, and both merge screens reading the same list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_contact_merge_select_carries_every_data_column()
{
	$select = pg_contact_merge_select();

	foreach (array('tax_number', 'tax_office', 'parasut_contact_id', 'image', 'file_id', 'company', 'expiration_date', 'affiliate_commission_rate', 'opt_in') as $column) {
		pg_assert_contains('contacts.' . $column . ',', $select, $column . ' is read');
	}

	pg_assert_contains('contact_user.user_id as user_id,', $select, 'the user id names a child contact');
	pg_assert_true(substr(rtrim($select), -18) === 'contacts.timestamp', 'the last modified time comes last, without a comma');
	pg_assert_false(strpos($select, 'iys_email_status') !== false, 'a column of the open version is read only when the schema has it');
	pg_assert_false(strpos($select, 'contacts.erp_account_id') !== false, 'the ERP mirror is set by the holders move, not copied');
}

function test_contact_merge_select_optional_columns()
{
	$select = pg_contact_merge_select(array('iys_email_status', 'iys_sms_status'));

	pg_assert_contains('contacts.iys_email_status,', $select, 'the e-mail consent status is read when present');
	pg_assert_contains('contacts.iys_sms_status,', $select, 'the SMS consent status is read when present');
	pg_assert_same(array(), pg_contact_merge_optional_columns(array()), 'no optional column without the schema');
	pg_assert_same(array('iys_sms_status'), pg_contact_merge_optional_columns(array('iys_sms_status' => true, 'iys_email_status' => false)), 'only the columns the schema has');
}

function test_contact_merge_screens_share_the_select()
{
	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_contacts.php');
	$selected = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contacts.php');

	pg_assert_contains('"SELECT " . pg_contact_merge_select(pg_contact_merge_optional_columns(pg_contact_merge_schema_columns())) . "', $list, 'Merge Contacts reads the shared list');
	pg_assert_contains('"SELECT " . pg_contact_merge_select(pg_contact_merge_optional_columns(pg_contact_merge_schema_columns())) . "', $selected, 'Merge Selected reads the shared list');
	pg_assert_false(strpos($selected, 'contacts.affiliate_commission_rate,') !== false, 'Merge Selected keeps no column list of its own');
}
