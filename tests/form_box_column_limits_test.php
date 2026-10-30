<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the contact group, contact, e-mail campaign, form field
 * and Edit User boxes: each box takes no more than its column holds (the
 * session is not strict, so a longer value is cut without a warning), and
 * a contact group name over its column is refused before the duplicate
 * check, so two long names sharing their first 100 characters are not both
 * saved as one name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');

function form_box_column_limits_test_read($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_form_box_column_limits_contact_group_boxes()
{
	$source = form_box_column_limits_test_read('includes/forms/contact_group_form.php');

	pg_assert_contains("'id'=>'name', 'placeholder'=>lang('Please enter a name'), 'maxlength'=>'100'", $source, 'group name holds VARCHAR(100)');
	pg_assert_contains("'id'=>'description', 'class'=>'form-control', 'rows'=>'5', 'cols'=>'50', 'maxlength'=>'255'", $source, 'group description holds VARCHAR(255)');
}

function test_form_box_column_limits_contact_group_long_name_refused_before_lookup()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	// 101 characters, multibyte: refused on characters, not bytes, and
	// before the duplicate query (no database here).
	$form = new liveform('form_box_column_limits_test');
	$form->set('name', str_repeat('ş', 101));
	pg_contact_group_form_validate_name($form, null);
	pg_assert_true($form->check_field_error('name'), 'a 101-character name is refused');

	$form = new liveform('form_box_column_limits_test_edit');
	$form->set('name', str_repeat('ş', 101));
	pg_contact_group_form_validate_name($form, '7');
	pg_assert_true($form->check_field_error('name'), 'edit refuses it too');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

function test_form_box_column_limits_contact_boxes()
{
	$source = form_box_column_limits_test_read('includes/forms/contact_form.php');

	pg_assert_contains("'business_zip_code', lang('Business Zip Code'), 10)", $source, 'business zip holds VARCHAR(10)');
	pg_assert_contains("'home_zip_code', lang('Home Zip Code'), 10)", $source, 'home zip holds VARCHAR(10)');
	pg_assert_contains("'mobile_phone', lang('Mobile Phone'), 50,", $source, 'mobile phone holds VARCHAR(50)');
	pg_assert_true((bool) preg_match('/<input\b[^>]*\bid="member_id"[^>]*\bmaxlength="50"/', $source), 'member id holds VARCHAR(50)');
}

function test_form_box_column_limits_campaign_from_name()
{
	$source = form_box_column_limits_test_read('includes/forms/email_campaign_form.php');

	pg_assert_true((bool) preg_match('/<input\b[^>]*\bid="from_name"[^>]*\bmaxlength="100"/', $source), 'campaign from name holds VARCHAR(100)');
}

function test_form_box_column_limits_field_name_and_label()
{
	$source = form_box_column_limits_test_read('includes/forms/field_form.php');

	pg_assert_contains("'placeholder' => lang('Field Name'), 'maxlength' => '100'", $source, 'field name holds VARCHAR(100)');
	pg_assert_true((bool) preg_match('/<input\b[^>]*\bid="label"[^>]*\bmaxlength="255"/', $source), 'field label holds VARCHAR(255)');
}

function test_form_box_column_limits_edit_user_password_hint()
{
	$source = form_box_column_limits_test_read('edit_user.php');

	pg_assert_true((bool) preg_match('/<input\b[^>]*\bid="password_hint"[^>]*\bmaxlength="100"/', $source), 'password hint holds VARCHAR(100)');
}
