<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The single e-mail address boxes of Site Settings (Website, Security,
 * Ecommerce) and of Create/Edit Page (custom form From, To and BCC, the
 * comments moderator) refuse an address validate_email_address() refuses
 * before anything is written, so "info@sitecom" is no longer stored and
 * reported as saved. A blank box stays allowed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_email_addresses_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Only a box holding something that is not one address is named.
function test_settings_email_addresses_errors_name_the_broken_boxes()
{
	$errors = pg_email_field_errors(array(
		'a' => 'info@sitecom',
		'b' => '',
		'c' => '  ',
		'd' => 'info@firma.istanbul',
		'e' => ' ayse@example.com ',
		'f' => 'a@example.com, b@example.com',
		'g' => null,
		'h' => array('x@example.com'),
	));
	pg_assert_same(array('a', 'f', 'h'), array_keys($errors));
	pg_assert_contains('info@sitecom', $errors['a']);
}

// The typed text goes into the message escaped, like the Alerts recipients.
function test_settings_email_addresses_error_escapes_the_value()
{
	$errors = pg_email_field_errors(array('a' => '<b>x</b>'));
	pg_assert_contains(h('<b>x</b>'), $errors['a']);
	pg_assert_false(strpos($errors['a'], '<b>') !== false, 'no markup');
}

// Each save module checks its boxes and returns before its UPDATE.
function test_settings_email_addresses_save_modules_check_before_the_update()
{
	$modules = array(
		'includes/settings/general.save.php' => array('email_address'),
		'includes/settings/security.save.php' => array('registration_email_address', 'membership_email_address'),
		'includes/settings/commerce.save.php' => array('ecommerce_email_address', 'ecommerce_reward_program_email_bcc_email_address', 'affiliate_email_address'),
	);

	foreach ($modules as $file => $fields) {
		$source = settings_email_addresses_test_source($file);
		$check = strpos($source, 'pg_email_field_errors(');
		pg_assert_true($check !== false, $file . ' checks its addresses');
		$update = strpos($source, 'db("UPDATE config');
		pg_assert_true($check < $update, $file . ' checks before the UPDATE');
		$return = strpos($source, 'check_form_errors()', $check);
		pg_assert_true($return !== false && $return < $update, $file . ' returns on a refused address');

		foreach ($fields as $field) {
			pg_assert_contains("'" . $field . "' => post_value('" . $field . "')", $source, $field);
		}
	}
}

function _test_settings_email_addresses_cf($overrides = array())
{
	return array_merge(array(
		'submitter_email' => '0', 'submitter_email_from_email_address' => '', 'submitter_email_format' => 'plain_text', 'submitter_email_body' => 'Thanks', 'submitter_email_page_id' => '',
		'administrator_email' => '0', 'administrator_email_to_email_address' => '', 'administrator_email_bcc_email_address' => '', 'administrator_email_format' => 'plain_text', 'administrator_email_body' => 'New', 'administrator_email_page_id' => '',
		'confirmation_type' => 'message', 'confirmation_page_id' => '',
	), $overrides);
}

function _test_settings_email_addresses_page_exists($page_id)
{
	return true;
}

// A custom form address is refused only while its e-mail is switched on.
function test_settings_email_addresses_custom_form_addresses()
{
	$broken = array(
		'submitter_email_from_email_address' => 'info@sitecom',
		'administrator_email_to_email_address' => 'admin@sitecom',
		'administrator_email_bcc_email_address' => 'a@example.com, b@example.com',
	);

	pg_assert_same(array(), pg_cf_legacy_settings_errors(_test_settings_email_addresses_cf($broken), '_test_settings_email_addresses_page_exists'), 'hidden boxes are not refused');

	$errors = pg_cf_legacy_settings_errors(_test_settings_email_addresses_cf(array_merge($broken, array(
		'submitter_email' => '1', 'administrator_email' => '1',
	))), '_test_settings_email_addresses_page_exists');
	pg_assert_same(array(
		'custom_form_submitter_email_from_email_address',
		'custom_form_administrator_email_to_email_address',
		'custom_form_administrator_email_bcc_email_address',
	), array_keys($errors));

	pg_assert_same(array(), pg_cf_legacy_settings_errors(_test_settings_email_addresses_cf(array(
		'submitter_email' => '1', 'submitter_email_from_email_address' => 'info@firma.istanbul',
		'administrator_email' => '1', 'administrator_email_to_email_address' => 'admin@example.com',
	)), '_test_settings_email_addresses_page_exists'), 'real and blank addresses pass');
}

// Edit Page refuses a broken comments moderator address before its UPDATE.
function test_settings_email_addresses_comments_moderator()
{
	$source = settings_email_addresses_test_source('edit_page.php');
	$check = strpos($source, "pg_email_field_errors(array('comments_administrator_email_to_email_address'");
	pg_assert_true($check !== false, 'the moderator address is checked');
	pg_assert_true($check < strpos($source, "comments_administrator_email_to_email_address = '"), 'before the UPDATE');
}
