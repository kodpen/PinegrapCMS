<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checks of the user screens: the posted role, the private
 * folders' expiration dates, the notice after a new user's e-mail, the
 * registered users filter, the default view's own filter, the import's
 * repeated headings, a bulk delete of a user already gone and the private
 * folder tree's labels.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/user_form.php');

// Only a whole number from 0 to 3 is a role; text that casts to 0 is not
// an administrator.
function test_user_form_role_accepts_only_whole_numbers()
{
	pg_assert_same(0, pg_user_form_role('0'));
	pg_assert_same(3, pg_user_form_role('3'));
	pg_assert_same(2, pg_user_form_role(2));
	pg_assert_same(null, pg_user_form_role('abc'), 'text is no role');
	pg_assert_same(null, pg_user_form_role('user'));
	pg_assert_same(null, pg_user_form_role(''), 'empty is no role');
	pg_assert_same(null, pg_user_form_role('4'), 'out of range');
	pg_assert_same(null, pg_user_form_role('-1'));
	pg_assert_same(null, pg_user_form_role('1.5'));
	pg_assert_same(null, pg_user_form_role(array('0')));
	pg_assert_same(null, pg_user_form_role(null), 'missing without a default');
	pg_assert_same(3, pg_user_form_role(null, 3), 'missing takes the default');
	pg_assert_same(null, pg_user_form_role('abc', 3), 'the default is only for a missing role');
}

function test_user_screens_check_the_role_strictly()
{
	foreach (array('add_user.php', 'edit_user.php', 'import_users.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('pg_user_form_role(', $source, $file . ' reads the role through pg_user_form_role()');
		pg_assert_false(strpos($source, "\$_POST['role'] = (int) \$_POST['role'];") !== false, $file . ' no longer casts text to 0');
	}
}

// A checked private folder with a date that is not one stops the save; a
// date already gone is named as a warning.
function test_user_form_expiry_problems()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	$folders = array(7 => 'private', 8 => 'shop', 9 => 'cafe', 10 => 'other');
	$post = array(
		'view_7' => '1', 'view_7_expiration_date' => prepare_form_data_for_output('2027-12-31', 'date'),
		'view_8' => '1', 'view_8_expiration_date' => prepare_form_data_for_output('2020-01-01', 'date'),
		'view_9' => '1', 'view_9_expiration_date' => '32/13/2027',
		'view_10_expiration_date' => 'not a date',
	);

	$problems = pg_user_form_expiry_problems($post, $folders, '2026-11-02');

	pg_assert_same(array('cafe'), $problems['invalid'], 'the invalid date of a checked folder');
	pg_assert_same(array('shop'), $problems['past'], 'the date already gone');

	$edit = pg_user_form_expiry_problems(array('edit_9' => '1', 'view_9' => '1', 'view_9_expiration_date' => 'x'), $folders, '2026-11-02');
	pg_assert_same(array('invalid' => array(), 'past' => array()), $edit, 'edit rights carry no date');
}

function test_user_screens_check_expiry_dates()
{
	foreach (array('add_user.php', 'edit_user.php', 'import_users.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('pg_user_form_check_expiry_dates(', $source, $file . ' checks the dates before it writes');
	}

	$import = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_users.php');
	pg_assert_contains('pg_user_form_folder_rights(', $import, 'import uses the shared folder rights');
}

// The notice follows what email() returned and never carries the password
// of a notified user.
function test_new_user_notice_follows_the_send()
{
	$failed = pg_new_user_notice(true, false, 'https://example.com/set-password?k=abc', 'Secret123!');
	$linked = pg_new_user_notice(true, true, 'https://example.com/set-password?k=abc', 'Secret123!');
	$plain = pg_new_user_notice(true, true, '', 'Secret123!');
	$silent = pg_new_user_notice(false, false, '', 'Secret123!');

	pg_assert_contains('could not be sent', $failed);
	pg_assert_contains('Forgot password', $failed);
	pg_assert_contains('A link to set a password has been emailed', $linked);
	pg_assert_contains('Forgot password', $plain);
	pg_assert_contains('Secret123!', $silent, 'a user not notified is shown the password');
	pg_assert_false(strpos($failed . $linked . $plain, 'Secret123!') !== false, 'a notified user\'s password is never shown');
	pg_assert_false(strpos($failed . $linked . $plain, 'k=abc') !== false, 'no link in the notice');
}

function test_user_create_and_import_read_the_send()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_user.php');
	$write = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');
	pg_assert_contains('$sent = (bool) email(', $write, 'pg_user_new_account_mail() reads what email() returned');
	pg_assert_contains('$sent = $mail[\'sent\'];', $add, 'add_user.php reads what the mail returned');
	pg_assert_contains('pg_new_user_notice(', $add);

	$import = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_users.php');
	pg_assert_contains('$unsent_users[] = $username', $import, 'import counts the users whose e-mail failed');
}

// A user created without a contact stores NULL, and the registered filter
// asks for a real contact.
function test_user_without_contact_is_not_registered()
{
	pg_assert_same('(user.user_contact > 0)', pg_user_list_filter_rule('my_registered_users')[0]);

	$write = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');
	pg_assert_contains("pg_user_contact_sql(\$fields['contact_id'] ?? '')", $write, 'user_contact through pg_user_contact_sql()');
	pg_assert_false(strpos($write, "escape(\$fields['contact_id'] ?? '')") !== false, 'no empty text in user_contact');
}

// The default view has its own option, selected while it is shown, and its
// own heading.
function test_user_list_default_view_has_its_option()
{
	$filters = pg_user_list_filters(0);

	pg_assert_same('default', array_keys($filters)[0], 'the default view comes first');
	pg_assert_same('Roles and Permissions', $filters['default']);
	pg_assert_contains('value="default" selected="selected"', get_filter_options($filters, 'default'));
	pg_assert_same('default', pg_user_list_filter('default'));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_list.php');
	pg_assert_false(strpos($source, 'have access too') !== false, 'the subheading reads "access to"');
}

// Two headings that fill the same contact field leave the second out
// before anything is written.
function test_import_users_leaves_repeated_headings_out()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_users.php');

	pg_assert_contains('pg_import_unique_columns(', $source);
	pg_assert_contains('These columns fill the same field as an earlier column and were left out', $source);

	$unique = pg_import_unique_columns(array(0 => 'email_address', 1 => 'email_address', 2 => 'username'));
	pg_assert_same(array(0 => 'email_address', 2 => 'username'), $unique['columns']);
	pg_assert_same(array(1), $unique['repeated']);
}

// The check loop reads the e-mail address and user name trimmed, as the
// writing loop does: " name@example.com" pasted from a spreadsheet refused
// the whole file as an invalid address.
function test_import_users_checks_the_cells_it_writes()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_users.php');
	$check = substr($source, (int) strpos($source, 'while ($row = fgetcsv('), 6000);

	pg_assert_contains('$row_email_address = trim($import_cell($row, $email_address_key));', $check);
	pg_assert_contains('$row_username = trim($import_cell($row, $username_key));', $check);
	pg_assert_contains('validate_email_address($row_email_address)', $check, 'the trimmed address is validated');
	pg_assert_contains('array($row_username, $row_username)', $check, 'the user name lookup is trimmed');
	pg_assert_contains('array($row_email_address, $row_email_address)', $check, 'the e-mail lookup is trimmed');
	pg_assert_false(strpos($check, 'validate_email_address($import_cell(') !== false, 'no untrimmed cell is validated');
	pg_assert_contains('$email_address = trim($import_cell($user_to_be_imported, $email_address_key));', $source, 'the writing loop reads it the same way');

	pg_assert_true(validate_email_address('pg-test@example.com'), 'a trimmed address is valid');
	pg_assert_false(validate_email_address(' pg-test@example.com'), 'the untrimmed one is not');
}

// A user already deleted in another tab is skipped, never counted.
function test_delete_users_skips_a_missing_user()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/delete_users.php');

	pg_assert_true((bool) preg_match('/if \(!\$row\) \{\s*continue;/', $source), 'a missing row is skipped');
}

// The private folder tree names the parents it leaves out.
function test_acl_folder_tree_names_left_out_parents()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');

	pg_assert_true((bool) preg_match('/function get_acl_folder_tree\(.*\$left_out_parents = array\(\)\)/', $source), 'the tree carries the parents it left out');
	pg_assert_contains('pg_folder_option_label($folder[\'name\'], $left_out_parents)', $source);
}

// No contact is NULL, never an empty text a lenient server stores as 0.
function test_user_contact_sql()
{
	pg_assert_same('NULL', pg_user_contact_sql(''));
	pg_assert_same('NULL', pg_user_contact_sql('0'));
	pg_assert_same('NULL', pg_user_contact_sql('abc'));
	pg_assert_same('NULL', pg_user_contact_sql(null));
	pg_assert_same("'42'", pg_user_contact_sql('42'));
	pg_assert_same("'42'", pg_user_contact_sql(42));
}
