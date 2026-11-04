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
		pg_assert_contains('includes/fn/user_write.php', $source, $file . ' loads the shared writes');
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
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');

	pg_assert_contains('pg_user_form_module_columns(', $source, 'pg_user_update() picks the module columns by the drawn rows');
	pg_assert_same(2, substr_count($source, 'pg_user_form_save_access('), 'the create and the update save access the shared way');

	foreach (array('user_password_hint', 'user_manage_forms', 'user_manage_calendars', 'user_publish_calendar_events', 'user_manage_ecommerce', 'user_view_card_data', 'manage_ecommerce_reports', 'manage_erp', 'manage_erp_cash', 'manage_erp_settings', 'user_set_offline_payment') as $column) {
		pg_assert_false((bool) preg_match('/' . preg_quote($column, '/') . '\s*=\s*\'"\s*\.\s*(e|escape)\(/', $source), 'pg_user_update() no longer writes ' . $column . ' from every post');
	}

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/user_form.php');
	pg_assert_true((bool) preg_match('/CALENDARS\s*==\s*true\)\s*\{\s*if \(\$replace\)\s*\{\s*db_q\("DELETE FROM users_calendars_xref/', $form), 'the calendar grants are rewritten only while Calendars is on');
}

function test_user_form_reward_points()
{
	pg_assert_same(0, pg_user_form_reward_points(null), 'not sent');
	pg_assert_same(0, pg_user_form_reward_points(''), 'blank');
	pg_assert_same(0, pg_user_form_reward_points('  '), 'spaces');
	pg_assert_same(12, pg_user_form_reward_points('12'), 'a whole number');
	pg_assert_same(12, pg_user_form_reward_points(12), 'an integer');
	pg_assert_same(4294967295, pg_user_form_reward_points('4294967295'), 'the column maximum');
	pg_assert_same(7, pg_user_form_reward_points('0007'), 'leading zeros');
	pg_assert_same(null, pg_user_form_reward_points('4294967296'), 'above the column');
	pg_assert_same(null, pg_user_form_reward_points('99999999999'), 'far above the column');
	pg_assert_same(null, pg_user_form_reward_points('-5'), 'negative');
	pg_assert_same(null, pg_user_form_reward_points('12,50'), 'a decimal comma');
	pg_assert_same(null, pg_user_form_reward_points('12.5'), 'a decimal point');
	pg_assert_same(null, pg_user_form_reward_points('abc'), 'not a number');
	pg_assert_same(null, pg_user_form_reward_points(array('1')), 'an array');
}

function test_user_form_text_fits()
{
	pg_assert_same(true, pg_user_form_text_fits(str_repeat('a', 100)), '100 characters');
	pg_assert_same(false, pg_user_form_text_fits(str_repeat('a', 101)), '101 characters');
	pg_assert_same(true, pg_user_form_text_fits(str_repeat('ş', 100)), '100 multibyte characters');
	pg_assert_same(true, pg_user_form_text_fits(''), 'empty');
}

function test_import_users_checks_points_and_lengths()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_users.php');

	pg_assert_contains('pg_user_form_reward_points($_POST[\'reward_points\'] ?? null)', $source, 'the points are checked');
	pg_assert_contains('pg_user_form_text_fits($_POST[\'badge_label\'] ?? \'\')', $source, 'the badge label length is checked');
	pg_assert_false(strpos($source, "escape(\$_POST['reward_points']") !== false, 'the raw points are not written');
}

// The values a form left in its liveform session, as name => value: field
// entries only, not the message buckets or the prefill flag.
function test_user_form_session_values()
{
	$session = array(
		'username' => array('value' => 'ada', 'error' => true, 'error_message' => 'x'),
		'edit_7' => array('value' => '1'),
		'email' => array('error' => true, 'error_message' => 'y'),
		'notices' => array('a notice'),
		'__prefill' => false,
	);

	pg_assert_same(array('username' => 'ada', 'edit_7' => '1'), pg_user_form_session_values($session), 'only the stored field values');
	pg_assert_same(array(), pg_user_form_session_values(null), 'no session');
}

function test_user_form_posted_ids()
{
	$post = array('edit_7' => '1', 'edit_9' => '', 'edit_12' => '1', 'view_7' => '1', 'view_7_expiration_date' => '1', 'edit_x' => '1');

	pg_assert_same(array(7, 12), pg_user_form_posted_ids($post, 'edit_'), 'the ticked edit boxes');
	pg_assert_same(array(7), pg_user_form_posted_ids($post, 'view_'), 'a date field is not a box');
	pg_assert_same(array(), pg_user_form_posted_ids($post, 'menu_'), 'nothing ticked');
}

// A form sent back with an error draws what was posted: the user row's
// columns take the posted fields, an unticked box reads as off, and a role
// that was not posted or is not a role keeps the stored one.
function test_user_form_restore_row()
{
	$row = array(
		'user_username' => 'old', 'user_email' => 'old@example.com', 'user_role' => '3', 'user_home' => '4',
		'user_badge' => '1', 'user_badge_label' => 'Old', 'user_reward_points' => '10',
		'user_manage_contacts' => 'yes', 'user_view_card_data' => '1', 'manage_erp' => '1',
		'user_set_page_type_catalog' => '1', 'user_set_page_type_folder_view' => '', 'contact_id' => '5',
	);

	$post = array(
		'username' => '  ', 'email' => 'not-an-email', 'role' => '2', 'home_page' => '0',
		'badge_label' => 'New', 'reward_points' => '-5', 'manage_erp' => '1', 'set_page_type_folder_view' => '1',
	);

	$restored = pg_user_form_restore_row($row, $post);

	pg_assert_same('  ', $restored['user_username'], 'the posted username');
	pg_assert_same('not-an-email', $restored['user_email'], 'the posted e-mail');
	pg_assert_same('2', $restored['user_role'], 'the posted role');
	pg_assert_same('0', $restored['user_home'], 'the posted start page');
	pg_assert_same('', $restored['user_badge'], 'an unticked badge');
	pg_assert_same('New', $restored['user_badge_label'], 'the posted badge label');
	pg_assert_same('-5', $restored['user_reward_points'], 'the posted points, as typed');
	pg_assert_same('', $restored['user_manage_contacts'], 'an unticked right');
	pg_assert_same('', $restored['user_view_card_data'], 'an unticked switch');
	pg_assert_same('1', $restored['manage_erp'], 'a ticked switch');
	pg_assert_same('0', $restored['user_set_page_type_catalog'], 'an unticked page type reads as off, not as never set');
	pg_assert_same('1', $restored['user_set_page_type_folder_view'], 'a ticked page type');
	pg_assert_same('5', $restored['contact_id'], 'the contact is not a form field');

	pg_assert_same('3', pg_user_form_restore_row($row, array())['user_role'], 'no role posted: the stored one');
	pg_assert_same('3', pg_user_form_restore_row($row, array('role' => 'abc'))['user_role'], 'not a role: the stored one');
}

function test_user_form_page_type_values()
{
	$values = pg_user_form_page_type_values(array('set_page_type_catalog' => '1'));

	pg_assert_same('1', $values['set_page_type_catalog'], 'ticked');
	pg_assert_same('0', $values['set_page_type_folder_view'], 'unticked');
	pg_assert_same(20, count($values), 'every page type');
}

function test_user_form_input_problems()
{
	pg_assert_same(array(), pg_user_form_input_problems(array('username' => 'ada', 'badge_label' => '', 'reward_points' => '12')), 'nothing wrong');
	pg_assert_same(array(), pg_user_form_input_problems(array()), 'nothing posted');

	pg_assert_same(
		array('username' => 'too_long', 'badge_label' => 'too_long', 'reward_points' => 'invalid'),
		pg_user_form_input_problems(array('username' => str_repeat('a', 101), 'badge_label' => str_repeat('b', 101), 'reward_points' => '-5')),
		'too long and out of range');

	pg_assert_same(array('reward_points' => 'invalid'), pg_user_form_input_problems(array('reward_points' => '12,50')), 'a decimal comma');
	pg_assert_same(array('reward_points' => 'invalid'), pg_user_form_input_problems(array('reward_points' => '99999999999')), 'above the column');
}

// A contact linked to a new account must be there and belong to nobody yet.
function test_user_form_contact_problem()
{
	pg_assert_same('', pg_user_form_contact_problem('', false, null), 'no contact given');
	pg_assert_same('', pg_user_form_contact_problem('0', false, null), 'zero is no contact');
	pg_assert_same('', pg_user_form_contact_problem('269', true, null), 'a free contact');
	pg_assert_same('contact_missing', pg_user_form_contact_problem('269', false, null), 'a contact that is not there');
	pg_assert_same('contact_taken', pg_user_form_contact_problem('269', true, 'ada'), 'a contact another account holds');
	pg_assert_same('contact_taken', pg_user_form_contact_problem(269, true, ''), 'a holder without a name still holds it');
}

// A notified user whose mail failed has a password nobody knows: the list
// says so as a warning, not as a notice of success.
function test_new_user_notice_is_warning()
{
	pg_assert_same(true, pg_new_user_notice_is_warning(true, false), 'notified, not sent');
	pg_assert_same(false, pg_new_user_notice_is_warning(true, true), 'notified and sent');
	pg_assert_same(false, pg_new_user_notice_is_warning(false, false), 'not notified');
}

// Input errors go back to the screen through its liveform, not to the
// site's error page; output_error() is left for access denial.
function test_user_screens_send_input_errors_back()
{
	foreach (array('add_user.php' => 'add_user', 'edit_user.php' => 'edit_user') as $file => $form) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("new liveform('" . $form . "')", $source, $file . ' has its liveform');
		pg_assert_contains('->output_errors()', $source, $file . ' prints the errors');
		pg_assert_contains('->remove_form()', $source, $file . ' clears the form once drawn');
		pg_assert_contains('pg_user_form_input_problems($_POST)', $source, $file . ' checks the points and lengths');
		pg_assert_false(strpos($source, 'output_error(pg_user_write_problem_message(') !== false, $file . ' no longer leaves the panel for a username or e-mail problem');
		pg_assert_false(strpos($source, "escape(\$_POST['reward_points']") !== false, $file . ' does not write the raw points');
	}

	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_user.php');
	pg_assert_contains('pg_user_contact_link_problem(', $add, 'the contact is checked before it is linked');
	pg_assert_contains('pg_new_user_notice_is_warning(', $add, 'a failed mail is a warning');
}
