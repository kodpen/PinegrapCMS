<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the phone keyboards of the contact form shared by the create and
 * the edit screens (includes/forms/contact_form.php): the phone and fax boxes
 * open the telephone keypad, the Website box the web address keyboard without
 * capitalising or spellchecking, and every box stays type="text".
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

function test_contact_form_keyboards_attributes()
{
	pg_assert_same(' inputmode="tel"', pg_contact_form_keyboard_attributes('tel'), 'tel');
	pg_assert_same(' inputmode="url" spellcheck="false" autocapitalize="off"', pg_contact_form_keyboard_attributes('url'), 'url');
	pg_assert_same('', pg_contact_form_keyboard_attributes(''), 'none');
	pg_assert_same('', pg_contact_form_keyboard_attributes('email'), 'unknown');
}

function test_contact_form_keyboards_phone_field()
{
	$html = pg_contact_form_text_field(array('business_phone' => '+90 (212) 555 00 00'), 'business_phone', 'Business Phone', 50, 'tel');

	pg_assert_contains('type="text"', $html, 'stays text');
	pg_assert_contains(' inputmode="tel" />', $html, 'tel keypad');
	pg_assert_contains('value="+90 (212) 555 00 00"', $html, 'value kept');
	pg_assert_contains('maxlength="50"', $html, 'maxlength kept');
}

function test_contact_form_keyboards_plain_field()
{
	$html = pg_contact_form_text_field(array('company' => 'Kodpen'), 'company', 'Company', 50);

	pg_assert_contains('type="text"', $html, 'text box');
	pg_assert_false(strpos($html, 'inputmode') !== false, 'no inputmode');
}

function test_contact_form_keyboards_calls()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

	foreach (array('business_phone', 'business_fax', 'home_phone', 'home_fax', 'mobile_phone') as $name) {
		pg_assert_true((bool) preg_match("/pg_contact_form_text_field\\(\\\$values, '" . $name . "', [^\\n]*, 'tel'\\)/", $source), $name . ' tel');
	}

	pg_assert_true((bool) preg_match("/pg_contact_form_text_field\\(\\\$values, 'website', [^\\n]*, 'url'\\)/", $source), 'website url');
}

function test_contact_form_keyboards_email_boxes()
{
	foreach (array('add_contact.php', 'edit_contact.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true((bool) preg_match('/name="email_address"[^>]*inputmode="email"/', $source), $file . ' email');
	}
}
