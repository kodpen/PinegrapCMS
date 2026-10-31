<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the user screens: Create Contact on Edit User saves the form
 * before it creates the contact, the new contact's heading has a name, the
 * role column and filters name the role their condition selects, the
 * contact and password words are one family, and the Recent Activity button
 * counts every entry rather than the 100 the drawer lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function user_screens_words_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function user_screens_words_test_tr()
{
	$tr = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/local/tr/*.json') as $file) {
		$tr += (array) json_decode((string) file_get_contents($file), true);
	}

	return $tr;
}

function test_user_screens_create_contact_runs_after_the_save()
{
	$source = user_screens_words_test_source('edit_user.php');
	$save = strpos($source, 'pg_user_form_save_access($_POST[\'id\']');
	pg_assert_true($save !== false, 'the save branch found');

	$first_read = strpos($source, '$_POST[\'submit_create_contact\']');
	pg_assert_true($first_read !== false, 'Create Contact is still handled');
	pg_assert_true($first_read > $save, 'the button is read only after the user has been saved');

	$insert = strpos($source, 'INSERT INTO contacts');
	pg_assert_true($insert > $save, 'the contact is created after the save');
	pg_assert_true(strpos($source, 'name="submit_create_contact"') !== false, 'the button stays in the form');
}

function test_user_screens_contact_heading_name_falls_back_to_email()
{
	pg_assert_same('Ada Lovelace', pg_contact_heading_name(array('first_name' => 'Ada', 'last_name' => 'Lovelace', 'company' => 'X', 'email_address' => 'a@example.com')));
	pg_assert_same('Kodpen', pg_contact_heading_name(array('first_name' => ' ', 'last_name' => '', 'company' => 'Kodpen', 'email_address' => 'a@example.com')));
	pg_assert_same('a@example.com', pg_contact_heading_name(array('first_name' => '', 'last_name' => '', 'company' => '', 'email_address' => 'a@example.com')));
	pg_assert_same('a@example.com', pg_contact_heading_name(array('email_address' => 'a@example.com')));
	pg_assert_same('', pg_contact_heading_name(array()));

	$source = user_screens_words_test_source('edit_contact.php');
	pg_assert_contains("h(pg_contact_heading_name(\$row))", $source, 'Edit Contact titles the contact by its heading name');
}

function test_user_screens_role_labels_match_their_conditions()
{
	$source = user_screens_words_test_source('includes/fn/user_list.php');
	$tr = user_screens_words_test_tr();

	// The column and its sort: a mark for role < 3, Manager and above.
	pg_assert_contains("\$heading_link(lang('Site Manager'), 'site_manager')", $source);
	pg_assert_contains("\$cells['manage_users'] = pg_user_list_mark(\$role < 3);", $source);

	// The filters: their condition and their label.
	pg_assert_contains("'all_site_managers' => array('(user.user_role < 3)', lang('All Site Managers')", $source);
	pg_assert_contains("'all_site_administrators' => array('(user.user_role < 1)', lang('All Site Administrators')", $source);

	// Role 2 is "Menajer"; "Yönetici" is only the Administrator.
	foreach (array('Site Manager', 'All Site Managers') as $key) {
		pg_assert_contains('Menajer', (string) ($tr[$key] ?? ''), $key);
		pg_assert_false(strpos((string) ($tr[$key] ?? ''), 'Yönetici') !== false, $key);
	}

	pg_assert_contains('Yönetici', (string) ($tr['All Site Administrators'] ?? ''));
}

function test_user_screens_contact_words_are_one_family()
{
	$tr = user_screens_words_test_tr();

	foreach (array(
		'Contacts',
		'Contact Group',
		'Contact Groups',
		'Contact Manager',
		'My Contact Managers',
		'contact group',
		'contact groups',
		'Contact groups, import and export, e-mail campaigns',
		'Both rights apply only to the contact groups selected here.',
		'All my users that can view and edit all contacts in at least one contact group.',
	) as $key) {
		$value = (string) ($tr[$key] ?? '');
		pg_assert_true(mb_stripos($value, 'rehber', 0, 'UTF-8') !== false, $key . ': ' . $value);
		pg_assert_false(mb_stripos($value, 'iletişim', 0, 'UTF-8') !== false, $key . ': ' . $value);
		pg_assert_false(mb_stripos($value, 'kişi grub', 0, 'UTF-8') !== false, $key . ': ' . $value);
	}

	// A counter reads "3 rehber grubu": both forms use the same word.
	pg_assert_same($tr['contact group'] ?? null, $tr['contact groups'] ?? false);
}

function test_user_screens_password_is_parola()
{
	$tr = user_screens_words_test_tr();

	foreach (array(
		'Password',
		'User Password Hint',
		'You cannot reset your own password here.',
		'Update this user\'s privileges, or email a new password.',
		'The password is \'{var:1}\'. The user has not been notified.',
		'Reset Password & Send Link',
	) as $key) {
		$value = (string) ($tr[$key] ?? '');
		pg_assert_true(mb_stripos($value, 'parola', 0, 'UTF-8') !== false, $key . ': ' . $value);
		pg_assert_false(mb_stripos($value, 'şifre', 0, 'UTF-8') !== false, $key . ': ' . $value);
	}
}

function test_user_screens_activity_button_counts_every_entry()
{
	$source = user_screens_words_test_source('edit_user.php');
	pg_assert_contains('db_q("SELECT COUNT(*) AS total FROM log WHERE log_user = ?", array((string) $username))', $source);
	pg_assert_contains("(\$log_total > \$log_count) ? lang(array('string' => 'Last {var:1} of {var:2}'", $source);
}
