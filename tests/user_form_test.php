<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the user account parts shared by the create and the edit
 * screens (includes/forms/user_form.php): the folder rights a posted form
 * gives, and that both screens use the shared parts.
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

function test_user_form_folder_rights_edit_and_none()
{
	pg_assert_same(array('rights' => 2, 'expiration_date' => ''), pg_user_form_folder_rights(array('edit_7' => '1', 'view_7' => '1'), 7), 'edit wins over view');
	pg_assert_same(array('rights' => 0, 'expiration_date' => ''), pg_user_form_folder_rights(array(), 7), 'nothing posted');
	pg_assert_same(array('rights' => 0, 'expiration_date' => ''), pg_user_form_folder_rights(array('edit_8' => '1'), 7), 'another folder');
}

function test_user_form_folder_rights_view_and_expiry()
{
	// The date fields read the site's order of day and month.
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	pg_assert_same(array('rights' => 1, 'expiration_date' => ''), pg_user_form_folder_rights(array('view_7' => '1', 'view_7_expiration_date' => '  '), 7), 'view without a date');

	$dated = pg_user_form_folder_rights(array('view_7' => '1', 'view_7_expiration_date' => prepare_form_data_for_output('2030-05-17', 'date')), 7);
	pg_assert_same(1, $dated['rights'], 'view with a valid date');
	pg_assert_same('2030-05-17', $dated['expiration_date'], 'the date in storage format');

	pg_assert_same(array('rights' => 0, 'expiration_date' => ''), pg_user_form_folder_rights(array('view_7' => '1', 'view_7_expiration_date' => 'not a date'), 7), 'an invalid date gives no access');
}

function test_user_form_screens_use_shared_parts()
{
	foreach (array('add_user.php', 'edit_user.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/user_form.php', $source, $file . ' loads the shared parts');
		pg_assert_contains('pg_user_form_rights_cards(', $source, $file . ' prints the shared rights cards');
		pg_assert_contains('pg_user_form_save_access(', $source, $file . ' saves access the shared way');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
	}
}
