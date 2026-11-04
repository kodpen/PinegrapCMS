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

function test_user_form_field_problems()
{
	pg_assert_same(array(), pg_user_form_field_problems(array('reward_points' => '12', 'username' => 'ann', 'badge_label' => '')), 'valid fields');
	pg_assert_same(array(), pg_user_form_field_problems(array()), 'nothing sent');
	pg_assert_same(array('reward_points' => 'invalid'), pg_user_form_field_problems(array('reward_points' => '-5')), 'negative points');
	pg_assert_same(array('reward_points' => 'invalid'), pg_user_form_field_problems(array('reward_points' => '12,50')), 'decimal points');
	pg_assert_same(array('reward_points' => 'invalid'), pg_user_form_field_problems(array('reward_points' => '99999999999')), 'points above the column');
	pg_assert_same(
		array('username' => 'too_long', 'badge_label' => 'too_long'),
		pg_user_form_field_problems(array('username' => str_repeat('a', 101), 'badge_label' => str_repeat('b', 101))),
		'texts longer than their column');
}

function test_user_form_contact_problem()
{
	pg_assert_same('', pg_user_form_contact_problem('', array('found' => false, 'username' => null)), 'no contact sent');
	pg_assert_same('', pg_user_form_contact_problem('abc', array('found' => false, 'username' => null)), 'not an id');
	pg_assert_same('', pg_user_form_contact_problem('269', array('found' => true, 'username' => null)), 'a free contact');
	pg_assert_same('contact_missing', pg_user_form_contact_problem('269', array('found' => false, 'username' => null)), 'a missing contact');
	pg_assert_same('contact_taken', pg_user_form_contact_problem('269', array('found' => true, 'username' => 'ann')), 'a contact of another user');
	pg_assert_same('contact_taken', pg_user_form_contact_problem(269, array('found' => true, 'username' => '0')), 'a username that reads as zero');
}

function test_user_form_refill()
{
	$html = '<input type="hidden" name="id" value="5" />'
		. '<input type="text" name="username" value="old" class="form-control" required />'
		. '<input type="email" name="email" id="email"/>'
		. '<input type="number" name="reward_points"/>'
		. '<input type="checkbox" name="create_pages" value="1" checked="checked" />'
		. '<input type="checkbox" name="badge" value="1" />'
		. '<input type="radio" name="role" value="2" checked="checked" />'
		. '<input type="radio" name="role" value="3" />'
		. '<input type="radio" name="role" value="0" disabled="disabled" checked="checked" />'
		. '<select name="home_page"><option value="0" selected="selected">None</option><option value="7">Home</option></select>';

	$out = pg_user_form_refill($html, array(
		'id' => '9',
		'username' => 'a"b',
		'email' => 'x@example.com',
		'reward_points' => '-5',
		'badge' => '1',
		'role' => '3',
		'home_page' => '7',
	));

	pg_assert_contains('<input type="hidden" name="id" value="5" />', $out, 'a hidden field keeps its value');
	pg_assert_contains('name="username" value="a&quot;b" class="form-control" required />', $out, 'a text field takes the sent value, escaped');
	pg_assert_contains('<input type="email" name="email" id="email" value="x@example.com" />', $out, 'a field without a value gets one');
	pg_assert_contains('<input type="number" name="reward_points" value="-5" />', $out, 'the refused number is shown');
	pg_assert_contains('<input type="checkbox" name="create_pages" value="1" />', $out, 'an unsent checkbox is unchecked');
	pg_assert_contains('<input type="checkbox" name="badge" value="1" checked="checked" />', $out, 'a sent checkbox is checked');
	pg_assert_contains('<input type="radio" name="role" value="2" />', $out, 'the drawn role is cleared');
	pg_assert_contains('<input type="radio" name="role" value="3" checked="checked" />', $out, 'the sent role is checked');
	pg_assert_contains('value="0" disabled="disabled" checked="checked" />', $out, 'a disabled input keeps its state');
	pg_assert_contains('<option value="0">None</option><option value="7" selected="selected">Home</option>', $out, 'the sent option is selected');
	pg_assert_same('<input type="text" name="x" value="$1 \\1" />', pg_user_form_refill('<input type="text" name="x" value="" />', array('x' => '$1 \\1')), 'replacement references are taken literally');
}

function test_user_screens_send_errors_back_to_the_form()
{
	foreach (array('add_user', 'edit_user') as $screen) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');

		pg_assert_contains('pg_user_form_field_problems($_POST)', $source, $screen . ' checks points and lengths');
		pg_assert_contains('$liveform->output_errors()', $source, $screen . ' prints its errors');
		pg_assert_contains('$liveform->remove_form()', $source, $screen . ' clears its form once drawn');
		pg_assert_false(strpos($source, 'output_error(pg_user_write_problem_message(') !== false, $screen . ' does not leave the panel for a refused save');
	}

	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_user.php');
	pg_assert_contains('pg_user_form_contact_problem(', $add, 'the contact is checked before the account is created');
	pg_assert_contains('$liveform_view_users->add_warning(', $add, 'an unsent new account mail is a warning');
}

function test_user_form_layout_columns_in_order()
{
	$html = pg_user_form_layout('<i>FORM</i>', '<i>STATUS</i>', '<b>BUTTONS</b>');

	$form = strpos($html, '<div class="col-12 lg:col-8 2xl:col-9 order-1"><i>FORM</i>');
	$status = strpos($html, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$sticky = strpos($html, '<div class="position-sticky" style="top:3.3rem;"><i>STATUS</i>');
	$bar = strpos($html, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');
	$buttons = strpos($html, '<b>BUTTONS</b>');

	pg_assert_true($form !== false, 'form column with its cards');
	pg_assert_true($status !== false && $status > $form, 'status column after the form column');
	pg_assert_true($sticky !== false && $sticky > $status, 'sticky box inside the status column');
	pg_assert_true($bar !== false && $bar > $sticky, 'save bar last');
	pg_assert_true($buttons !== false && $buttons > $bar, 'buttons in the save bar');

	$plain = pg_user_form_layout('<i>FORM</i>', '<i>STATUS</i>', '');
	pg_assert_true(strpos(pg_user_form_layout('F', 'S', 'B', false), 'top:3.3rem') === false, 'a tall status box is not pinned');
	pg_assert_contains('<nav class="buttons navigation text-center mb-7"', $plain, 'shared save bar component');
}

function test_user_form_screens_use_the_layout()
{
	foreach (array('add_user.php', 'edit_user.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('pg_user_form_layout(', $source, $file . ': wide layout');
		pg_assert_true(strpos($source, 'xl:col-8') === false, $file . ': no older two-column row');
	}
}
