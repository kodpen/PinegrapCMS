<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the SMS marketing question on custom forms: the opt_in_sms
 * contact binding, which is no contacts column, and the answer a submission
 * records as a consent_log row on the sms channel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// opt_in_sms is kept only in the consent log, never in contacts.
function test_sms_consent_custom_form_column_skip()
{
	pg_assert_false(pg_cf_contact_column('opt_in_sms'), 'opt_in_sms is no contacts column');
	pg_assert_false(pg_cf_contact_column(''), 'no binding');
	pg_assert_true(pg_cf_contact_column('opt_in'), 'opt_in is a column');
	pg_assert_true(pg_cf_contact_column('mobile_phone'), 'mobile_phone is a column');
}

// The legacy field screen and the designer offer the same list.
function test_sms_consent_custom_form_binding_listed()
{
	$fields = pg_cf_contact_fields();

	pg_assert_true(isset($fields['opt_in_sms']), 'opt_in_sms is a binding');
	pg_assert_true(isset($fields['opt_in']), 'opt_in stays');
}

// Only a visible field bound to mobile_phone asks the number.
function test_sms_consent_custom_form_asks_mobile()
{
	$mobile = array('id' => 7, 'contact_field' => 'mobile_phone', 'office_use_only' => 0);

	pg_assert_true(pg_cf_fields_ask_mobile(array($mobile)), 'visible mobile field');
	pg_assert_false(pg_cf_fields_ask_mobile(array(array('contact_field' => 'home_phone') + $mobile)), 'another phone');
	pg_assert_false(pg_cf_fields_ask_mobile(array(array('office_use_only' => 1) + $mobile)), 'office use only');
	pg_assert_false(pg_cf_fields_ask_mobile(array($mobile), array('show_7' => 'false')), 'hidden through the address');
	pg_assert_true(pg_cf_fields_ask_mobile(array($mobile), array('show_8' => 'false')), 'another field hidden');
	pg_assert_false(pg_cf_fields_ask_mobile(array()), 'no fields');
}

// No SMS, no number, no box.
function test_sms_consent_custom_form_question_drawn()
{
	pg_assert_true(pg_cf_sms_question_drawn(true, '', true), 'form asks the number');
	pg_assert_true(pg_cf_sms_question_drawn(false, '0532 123 45 67', true), 'contact has a mobile');
	pg_assert_false(pg_cf_sms_question_drawn(false, '0212 123 45 67', true), 'a landline is no number');
	pg_assert_false(pg_cf_sms_question_drawn(false, '', true), 'no number');
	pg_assert_false(pg_cf_sms_question_drawn(true, '0532 123 45 67', false), 'SMS not ready');
}

// A form without the SMS question keeps every field and reads nothing.
function test_sms_consent_custom_form_drop_without_question()
{
	$fields = array(3 => array('id' => 3, 'contact_field' => 'opt_in'), 4 => array('id' => 4, 'contact_field' => ''));

	pg_assert_same($fields, pg_cf_drop_sms_question($fields, array()), 'nothing dropped');
}

// One row only when asked and the contact has a mobile number after the save.
function test_sms_consent_custom_form_answers()
{
	pg_assert_same(array('sms:marketing' => true), pg_cf_sms_answers(true, '0532 123 45 67', '1'), 'ticked');
	pg_assert_same(array('sms:marketing' => false), pg_cf_sms_answers(true, '0532 123 45 67', ''), 'unticked');
	pg_assert_same(array('sms:marketing' => false), pg_cf_sms_answers(true, '0532 123 45 67', 'yes'), 'stored as the column would: not 1');
	pg_assert_same(array('sms:marketing' => true), pg_cf_sms_answers(true, '0532 123 45 67', array('1')), 'check box group');
	pg_assert_same(array(), pg_cf_sms_answers(true, '', '1'), 'no number, no answer');
	pg_assert_same(array(), pg_cf_sms_answers(false, '0532 123 45 67', '1'), 'field missing or not shown: silence is no refusal');
}

// Every path that reads or writes contacts.<contact_field> asks the helper.
function test_sms_consent_custom_form_readers_skip()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$checks = array(
		'custom_form.php' => 'pg_cf_contact_column($field[\'contact_field\'])',
		'includes/fn/content.php' => 'pg_cf_contact_column($field[\'contact_field\'])',
		'get_custom_form_screen_content.php' => 'pg_cf_contact_column($field[\'contact_field\'])',
		'includes/fn/widgets.php' => 'pg_cf_contact_column($c[\'contact_field\'])',
		'includes/fn/custom_form.php' => 'pg_cf_contact_column($key)',
	);

	foreach ($checks as $file => $needle) {
		pg_assert_true(strpos((string) file_get_contents($root . $file), $needle) !== false, $file . ' skips opt_in_sms');
	}

	// The submission writes the SMS answer in the same call as the e-mail one.
	$submit = (string) file_get_contents($root . 'custom_form.php');
	pg_assert_true(strpos($submit, '$consent_answers = pg_cf_sms_answers($contact_sms_opt_in_asked') !== false, 'answer built from the asked field');
	pg_assert_same(1, preg_match_all('/pg_consent_log_answers\(\s*array\(/', $submit), 'one consent log call');
	pg_assert_true(strpos($submit, '$consent_answers,') !== false, 'both answers in that call');
}
