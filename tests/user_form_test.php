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

// Edit User writes a module's rights only while their rows were drawn: a
// column whose box was not on the form keeps its stored value.
function test_user_form_module_columns_follow_drawn_rows()
{
	$off = array();
	pg_assert_same(array(), pg_user_form_module_columns($off), 'every module off writes none of their columns');

	$all = pg_user_form_module_columns(array(
		'password_hint' => true, 'forms' => true, 'calendars' => true,
		'ecommerce' => true, 'offline_payment' => true, 'erp' => true,
	));
	pg_assert_same(array(
		'user_password_hint' => 'password_hint',
		'user_manage_forms' => 'manage_forms',
		'user_manage_calendars' => 'manage_calendars',
		'user_publish_calendar_events' => 'publish_calendar_events',
		'user_manage_ecommerce' => 'manage_ecommerce',
		'user_view_card_data' => 'view_card_data',
		'manage_ecommerce_reports' => 'manage_ecommerce_reports',
		'user_set_offline_payment' => 'set_offline_payment',
		'manage_erp' => 'manage_erp',
		'manage_erp_cash' => 'manage_erp_cash',
		'manage_erp_settings' => 'manage_erp_settings',
	), $all, 'every module on writes every column');

	pg_assert_same(array('user_manage_forms' => 'manage_forms'), pg_user_form_module_columns(array('forms' => true)), 'forms alone');
	pg_assert_same(array('user_password_hint' => 'password_hint'), pg_user_form_module_columns(array('password_hint' => true)), 'hints alone');

	// The offline payment switch sits in the Commerce panel: without
	// Commerce it is not drawn, whatever the offline payment setting.
	$offline_only = pg_user_form_module_columns(array('offline_payment' => true));
	pg_assert_same(array(), $offline_only, 'offline payment without commerce writes nothing');

	$commerce = pg_user_form_module_columns(array('ecommerce' => true));
	pg_assert_false(isset($commerce['user_set_offline_payment']), 'commerce without offline payment leaves the offline column');
	pg_assert_same('manage_ecommerce', $commerce['user_manage_ecommerce'], 'commerce writes its gate');
}

function test_user_form_edit_save_guards_module_columns()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_user.php');

	pg_assert_contains('pg_user_form_module_columns(', $source, 'edit_user.php picks the module columns by the drawn rows');

	foreach (array('user_password_hint', 'user_manage_forms', 'user_manage_calendars', 'user_publish_calendar_events', 'user_manage_ecommerce', 'user_view_card_data', 'manage_ecommerce_reports', 'manage_erp', 'manage_erp_cash', 'manage_erp_settings', 'user_set_offline_payment') as $column) {
		pg_assert_false((bool) preg_match('/' . preg_quote($column, '/') . '\s*=\s*\'"\s*\.\s*(e|escape)\(/', $source), 'edit_user.php no longer writes ' . $column . ' from every post');
	}

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/user_form.php');
	pg_assert_true((bool) preg_match('/CALENDARS\s*==\s*true\)\s*\{\s*if \(\$replace\)\s*\{\s*db_q\("DELETE FROM users_calendars_xref/', $form), 'the calendar grants are rewritten only while Calendars is on');
}
