<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Contacts list's Opt-In Selected and Opt-Out Selected
 * (edit_contacts.php): one write per address, every contact with the address
 * follows, only contacts whose status changes are counted, and Organize
 * Selected skips the groups the user may not manage.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

function test_contact_bulk_opt_in_one_write_per_address()
{
	$contacts = array(
		array('id' => 4, 'email_address' => 'a@b.com'),
		array('id' => 7, 'email_address' => 'A@B.com'),
		array('id' => 9, 'email_address' => ''),
		array('id' => 12, 'email_address' => ''),
		array('id' => 15, 'email_address' => 'c@d.com'),
	);

	pg_assert_same(
		array(
			array('id' => 4, 'email_address' => 'a@b.com'),
			array('id' => 9, 'email_address' => ''),
			array('id' => 12, 'email_address' => ''),
			array('id' => 15, 'email_address' => 'c@d.com'),
		),
		pg_contact_bulk_opt_in_targets($contacts),
		'a second ticked contact with the same address is covered by the first write; contacts without an address are written alone');
}

function test_contact_bulk_opt_in_counts_only_changed()
{
	// Before states of the ticked contacts and every contact sharing their addresses.
	$before = array(4 => '1', 5 => '1', 9 => '0', 12 => 0, 15 => '');

	pg_assert_same(2, pg_contact_bulk_opt_in_changed($before, '0'), 'opt-out counts the two opted-in contacts');
	pg_assert_same(3, pg_contact_bulk_opt_in_changed($before, '1'), 'opt-in counts the three opted-out contacts');
	pg_assert_same(0, pg_contact_bulk_opt_in_changed(array(), '1'), 'nothing to change');
}

function test_contact_bulk_opt_in_screen_syncs_the_address()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_contacts.php');

	pg_assert_true(strpos($source, "includes/forms/contact_form.php") !== false, 'the list loads the shared contact helpers');
	pg_assert_same(2, substr_count($source, 'pg_contact_bulk_set_opt_in($user, $_POST[\'contacts\'], '), 'opt-in and opt-out go through the address-wide writer');
	pg_assert_true(strpos($source, 'SET opt_in = 1') === false && strpos($source, 'SET opt_in = 0') === false, 'no ticked-row-only write is left');
	pg_assert_same(2, substr_count($source, 'validate_contact_group_access($user, $contact_group_id)'), 'organize checks every added and removed group');

	$helpers = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');
	pg_assert_true(strpos($helpers, 'pg_contact_form_sync_opt_in((int) $contact[\'id\'], $contact[\'email_address\'], $opt_in)') !== false, 'the writer reuses the single save\'s address sync');
}
