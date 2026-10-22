<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the default value _pg_cf_controls() (includes/fn/custom_form.php)
 * reads from a form tree: the value of a text control, the text of a text
 * area, and the answer a single pick list marks selected - what an
 * office-use-only field stores for a visitor who never sees it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_cf_default_node($tag, $attrs, $children = array(), $props = array())
{
	$a = array();
	foreach ($attrs as $k => $v) $a[] = array('name' => $k, 'value' => $v);
	return array('type' => 'semantic', 'props' => array_merge(array('tag' => $tag, '_attrs' => $a), $props), 'children' => $children);
}

function _test_cf_default_option($value, $selected = false)
{
	$attrs = array('value' => $value);
	if ($selected) $attrs['selected'] = '';
	return _test_cf_default_node('option', $attrs, array(), array('text' => $value));
}

function _test_cf_default_fields($controls)
{
	return _pg_cf_controls(array('type' => 'semantic', 'props' => array('tag' => 'form'), 'children' => $controls));
}

// The selected answer of a single pick list is its default.
function test_custom_form_default_select_selected_option()
{
	$fields = _test_cf_default_fields(array(
		_test_cf_default_node('select', array('name' => 'status'), array(
			_test_cf_default_option('Requested', true),
			_test_cf_default_option('Confirmed'),
		), array('_cf' => array('office_use_only' => true))),
	));
	pg_assert_same('Requested', $fields['status']['default_value'], 'selected answer');
	pg_assert_true($fields['status']['office_use_only'], 'office use only kept');
	pg_assert_true($fields['status']['options'][0]['default'], 'option still marked default');
}

// No answer selected, a prompt only, or a multiple list: no default.
function test_custom_form_default_select_without_selection()
{
	$fields = _test_cf_default_fields(array(
		_test_cf_default_node('select', array('name' => 'topic'), array(
			_test_cf_default_option(''),
			_test_cf_default_option('Billing'),
		)),
		_test_cf_default_node('select', array('name' => 'days', 'multiple' => ''), array(
			_test_cf_default_option('Monday', true),
			_test_cf_default_option('Tuesday', true),
		)),
	));
	pg_assert_same('', $fields['topic']['default_value'], 'nothing selected');
	pg_assert_same('', $fields['days']['default_value'], 'multiple list');
}

// An option without a value attribute answers with its text.
function test_custom_form_default_select_option_text()
{
	$fields = _test_cf_default_fields(array(
		_test_cf_default_node('select', array('name' => 'size'), array(
			array('type' => 'semantic', 'props' => array('tag' => 'option', 'text' => 'Large', '_attrs' => array(array('name' => 'selected', 'value' => ''))), 'children' => array()),
		)),
	));
	pg_assert_same('Large', $fields['size']['default_value']);
}

// Text controls keep their own rule.
function test_custom_form_default_text_controls()
{
	$fields = _test_cf_default_fields(array(
		_test_cf_default_node('input', array('type' => 'text', 'name' => 'city', 'value' => 'Istanbul')),
		_test_cf_default_node('textarea', array('name' => 'note'), array(), array('text' => 'Hello')),
	));
	pg_assert_same('Istanbul', $fields['city']['default_value']);
	pg_assert_same('Hello', $fields['note']['default_value']);
}

// A body the operator wrote is sent as written.
function test_custom_form_default_email_body_kept()
{
	$fields = array(array('name' => 'email', 'label' => 'E-mail'));
	pg_assert_same("Hi ^^email^^", pg_cf_email_body_fallback("Hi ^^email^^", $fields));
	pg_assert_same('Hi', pg_cf_email_body_or_default(0, 'Hi'), 'no field read for a written body');
}

// An empty body lists the form's fields; a label left empty or ending in a
// colon still reads as one label.
function test_custom_form_default_email_body_from_fields()
{
	$fields = array(
		array('name' => 'first_name', 'label' => 'First Name:'),
		array('name' => 'email', 'label' => ''),
		array('name' => '', 'label' => 'Intro'),
	);
	pg_assert_same("First Name: ^^first_name^^\nemail: ^^email^^", pg_cf_email_body_fallback(" \n ", $fields));
}

// A form without a named field still says which submission the e-mail is about.
function test_custom_form_default_email_body_without_fields()
{
	$body = pg_cf_email_body_fallback('', array());
	pg_assert_contains('^^reference_code^^', $body);
	pg_assert_contains('^^submitted_date_and_time^^', $body);
	pg_assert_same($body, pg_cf_email_body_or_default(0, ''), 'no page, no fields');
}

// The message is shown unless a next page is chosen.
function test_custom_form_default_confirmation_shows_message()
{
	pg_assert_true(pg_cf_confirmation_shows_message('message', 5), 'message type');
	pg_assert_true(pg_cf_confirmation_shows_message('', 5), 'no type');
	pg_assert_true(pg_cf_confirmation_shows_message('page', 0), 'next page never chosen');
	pg_assert_true(pg_cf_confirmation_shows_message('page', ''), 'next page empty');
}

// An empty confirmation message falls back to the thank-you text.
function test_custom_form_default_confirmation_message()
{
	pg_assert_same('<p>Thanks!</p>', pg_cf_confirmation_message_or_default('<p>Thanks!</p>'));
	pg_assert_same('<img src="x.png">', pg_cf_confirmation_message_or_default('<img src="x.png">'), 'an image is content');
	pg_assert_same(lang('Thank you. Your message has been received.'), pg_cf_confirmation_message_or_default('<p>&nbsp;</p>'));
	pg_assert_same(lang('Thank you. Your message has been received.'), pg_cf_confirmation_message_or_default(''));
}

function _test_cf_default_settings($overrides = array())
{
	return array_merge(array(
		'submitter_email' => 0, 'submitter_email_format' => 'plain_text', 'submitter_email_body' => '', 'submitter_email_page_id' => '',
		'administrator_email' => 0, 'administrator_email_format' => 'plain_text', 'administrator_email_body' => '', 'administrator_email_page_id' => '',
		'confirmation_type' => 'message', 'confirmation_page_id' => '',
	), $overrides);
}

function _test_cf_default_page_exists($page_id)
{
	return in_array((int) $page_id, array(7, 9), true);
}

// E-mails that are off and a message confirmation need nothing more.
function test_custom_form_default_settings_clean()
{
	pg_assert_same(array(), pg_cf_legacy_settings_errors(_test_cf_default_settings(), '_test_cf_default_page_exists'));
	pg_assert_same(array(), pg_cf_legacy_settings_errors(_test_cf_default_settings(array(
		'submitter_email' => '1', 'submitter_email_body' => 'Thanks ^^name^^',
		'administrator_email' => '1', 'administrator_email_format' => 'html', 'administrator_email_page_id' => '7',
		'confirmation_type' => 'page', 'confirmation_page_id' => '9',
	)), '_test_cf_default_page_exists'), 'complete settings');
}

// An e-mail that is on needs a body (plain text) or an existing page (HTML).
function test_custom_form_default_settings_email_errors()
{
	$errors = pg_cf_legacy_settings_errors(_test_cf_default_settings(array(
		'submitter_email' => '1', 'submitter_email_body' => "  \n",
		'administrator_email' => '1', 'administrator_email_format' => 'html', 'administrator_email_page_id' => '8',
	)), '_test_cf_default_page_exists');
	pg_assert_same(array('custom_form_submitter_email_body', 'custom_form_administrator_email_page_id'), array_keys($errors));
	pg_assert_contains(lang('E-mail Submitter'), $errors['custom_form_submitter_email_body']);
	pg_assert_contains(lang('E-mail Administrator'), $errors['custom_form_administrator_email_page_id']);
}

// "Next Page" without a page, or with a page that no longer exists, is refused.
function test_custom_form_default_settings_confirmation_page()
{
	foreach (array('', '0', '8') as $page_id) {
		$errors = pg_cf_legacy_settings_errors(_test_cf_default_settings(array(
			'confirmation_type' => 'page', 'confirmation_page_id' => $page_id,
		)), '_test_cf_default_page_exists');
		pg_assert_same(array('custom_form_confirmation_page_id'), array_keys($errors), 'page ' . $page_id);
	}
}

// An empty or missing confirmation type is the message, as on submit, so it
// needs no next page.
function test_custom_form_default_settings_empty_confirmation_type()
{
	pg_assert_same(array(), pg_cf_legacy_settings_errors(_test_cf_default_settings(array(
		'confirmation_type' => '', 'confirmation_page_id' => '',
	)), '_test_cf_default_page_exists'), 'empty type');
	$settings = _test_cf_default_settings();
	unset($settings['confirmation_type'], $settings['confirmation_page_id']);
	pg_assert_same(array(), pg_cf_legacy_settings_errors($settings, '_test_cf_default_page_exists'), 'no type posted');
}

// The submitter's copy leaves out fields hidden from the submitter; the
// administrator's copy keeps them.
function test_custom_form_default_email_body_hidden_fields()
{
	$fields = array(
		array('name' => 'email', 'label' => 'E-mail', 'office_use_only' => '0'),
		array('name' => 'internal_note', 'label' => 'Internal Note', 'office_use_only' => '1'),
	);
	pg_assert_same("E-mail: ^^email^^", pg_cf_email_body_fallback('', $fields, true), 'submitter');
	pg_assert_same("E-mail: ^^email^^\nInternal Note: ^^internal_note^^", pg_cf_email_body_fallback('', $fields, false), 'administrator');
	pg_assert_same("E-mail: ^^email^^\nInternal Note: ^^internal_note^^", pg_cf_email_body_fallback('', $fields), 'administrator by default');

	$hidden_only = array(array('name' => 'internal_note', 'label' => 'Internal Note', 'office_use_only' => 1));
	$body = pg_cf_email_body_fallback('', $hidden_only, true);
	pg_assert_contains('^^reference_code^^', $body, 'only hidden fields');
	pg_assert_true(strpos($body, 'internal_note') === false, 'hidden field not named');
}