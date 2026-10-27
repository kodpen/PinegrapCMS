<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the form field form shared by the field screens
 * (includes/forms/field_form.php): the form type of a page, the form name
 * fallback, the name check, the choices parser, the new sort
 * order, the stored choices as text and the save notice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/field_form.php');

function test_field_form_page_form_type()
{
	pg_assert_same('custom', pg_field_form_page_form_type('custom form', ''), 'custom form');
	pg_assert_same('shipping', pg_field_form_page_form_type('express order', 'shipping'), 'express order shipping form');
	pg_assert_same('billing', pg_field_form_page_form_type('express order', 'billing'), 'express order billing form');
	pg_assert_same('billing', pg_field_form_page_form_type('express order', null), 'express order without a form type: billing');
	pg_assert_same('shipping', pg_field_form_page_form_type('shipping address and arrival', ''), 'shipping page');
	pg_assert_same('billing', pg_field_form_page_form_type('billing information', 'shipping'), 'billing page ignores the form type');
	pg_assert_same('', pg_field_form_page_form_type('standard', ''), 'a page without a field form');
}

function test_field_form_type_name()
{
	pg_assert_same(lang('custom form'), pg_field_form_type_name('custom'), 'custom');
	pg_assert_same(lang('custom shipping form'), pg_field_form_type_name('shipping'), 'shipping');
	pg_assert_same(lang('custom billing form'), pg_field_form_type_name('billing'), 'billing');
	pg_assert_same('', pg_field_form_type_name(''), 'none');
}

function test_field_form_product_form_name()
{
	pg_assert_same('Form', pg_field_form_product_form_name('Form', 'Short', 'Name'), 'own form name');
	pg_assert_same('Short', pg_field_form_product_form_name('', 'Short', 'Name'), 'short description');
	pg_assert_same('Name', pg_field_form_product_form_name('', '', 'Name'), 'product name');
	pg_assert_same('', pg_field_form_product_form_name('', '', ''), 'nothing');
}

function test_field_form_express_query()
{
	pg_assert_same('&form_type=shipping', pg_field_form_express_query('express order', 'shipping'), 'shipping');
	pg_assert_same('&form_type=billing', pg_field_form_express_query('express order', 'billing'), 'billing');
	pg_assert_same('', pg_field_form_express_query('custom form', 'custom'), 'other page');
	pg_assert_same('', pg_field_form_express_query('', 'product'), 'product form');
}

function test_field_form_name_reserved_chars()
{
	foreach (array('^', '&', '[', ']', '<', '>', '/') as $character) {
		pg_assert_true(pg_field_form_name_has_reserved_chars('a' . $character . 'b'), 'refused: ' . $character);
	}

	pg_assert_false(pg_field_form_name_has_reserved_chars('First Name (çğ) - 2'), 'a plain name');
	pg_assert_false(pg_field_form_name_has_reserved_chars(''), 'empty');
}

function test_field_form_required_value()
{
	pg_assert_same(0, pg_field_form_required_value('information', '1'), 'information is never required');
	pg_assert_same('1', pg_field_form_required_value('text box', '1'), 'checked');
	pg_assert_same(null, pg_field_form_required_value('text box', null), 'unchecked');
}

function test_field_form_parse_option_line()
{
	$option = pg_field_form_parse_option_line('Apple');
	pg_assert_same(array('Apple', 'Apple', 0, '', false, '', ''), array_values($option), 'label only');

	$option = pg_field_form_parse_option_line(" Apple | apple | ON ");
	pg_assert_same(array('Apple', 'apple', 1), array($option['label'], $option['value'], $option['default_selected']), 'label, value and default');

	$option = pg_field_form_parse_option_line('Sales|sales|off|Region=North, South|42 ^^a@example.com, bad, b@example.com^^');
	pg_assert_same('a@example.com, b@example.com', $option['email_address'], 'valid addresses kept');
	pg_assert_true($option['invalid_email'], 'an invalid address is reported');
	pg_assert_same(0, $option['default_selected'], 'off');
	pg_assert_same('Region=North, South', $option['trigger'], 'trigger part');
	pg_assert_same('42', $option['upload_folder_id'], 'folder part');
}

function test_field_form_parse_trigger()
{
	pg_assert_same(array('Region', array('North', 'South')), pg_field_form_parse_trigger('Region = North , South'), 'field and options');
	pg_assert_same(array('Region', array('')), pg_field_form_parse_trigger('Region'), 'no options: one empty option, as before');
	pg_assert_same(array('', array('x')), pg_field_form_parse_trigger('=x'), 'no field name');
}

function test_field_form_sorted_ids()
{
	pg_assert_same(array('9', '1', '2'), pg_field_form_sorted_ids(array('1', '2'), '9', 'top'), 'top');
	pg_assert_same(array('1', '9', '2'), pg_field_form_sorted_ids(array('1', '2'), '9', '1'), 'after the first');
	pg_assert_same(array('1', '2', '9'), pg_field_form_sorted_ids(array('1', '2'), '9', '2'), 'last');
	pg_assert_same(array('9'), pg_field_form_sorted_ids(array(), '9', 'top'), 'only field');
}

function test_field_form_saved_notice()
{
	$email = lang(' However, there were one or more e-mail addresses entered for choices that were invalid, so they have been removed.');
	$also = lang(' Also, there were one or more triggers entered for choices that were invalid, so they have been removed.');
	$trigger = lang(' However, there were one or more triggers entered for choices that were invalid, so they have been removed.');

	pg_assert_same('Saved.', pg_field_form_saved_notice('Saved.', false, false), 'clean');
	pg_assert_same('Saved.' . $email, pg_field_form_saved_notice('Saved.', true, false), 'e-mail');
	pg_assert_same('Saved.' . $trigger, pg_field_form_saved_notice('Saved.', false, true), 'trigger');
	pg_assert_same('Saved.' . $email . $also, pg_field_form_saved_notice('Saved.', true, true), 'both');
}

function test_field_form_new_field_defaults()
{
	$field = pg_field_form_new_field('5');
	pg_assert_same('', $field['id'], 'no id');
	pg_assert_same(1, $field['required'], 'required by default');
	pg_assert_same(1, $field['translate_values'], 'included in translation by default');
	pg_assert_same('5', $field['upload_folder_id'], 'the form folder is the upload folder');
	pg_assert_same(' checked="checked"', pg_field_form_checked($field['required']), 'checked');
	pg_assert_same('', pg_field_form_checked($field['wysiwyg']), 'not checked');
}

function test_field_form_add_screen_uses_shared_form()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_field.php');

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/field_form.php')", $source, 'loads the shared form');
	pg_assert_contains('pg_field_form_cards(', $source, 'draws the shared cards');
	pg_assert_contains('get_token_field()', $source, 'prints the token');
	pg_assert_contains('validate_token_field()', $source, 'checks the token');
	pg_assert_contains('pg_field_form_layout_warning(', $source, 'keeps the custom layout warning');
}

function test_field_form_option_line()
{
	$option = array('label' => 'Apple', 'value' => 'Apple', 'default_selected' => 0, 'upload_folder_id' => 0, 'email_address' => '');
	pg_assert_same('Apple', pg_field_form_option_line($option, array()), 'label only');

	$option['value'] = 'apple';
	pg_assert_same('Apple|apple', pg_field_form_option_line($option, array()), 'own value');

	$option['default_selected'] = 1;
	pg_assert_same('Apple|apple|on', pg_field_form_option_line($option, array()), 'selected');

	$option = array('label' => 'North', 'value' => 'North', 'default_selected' => 0, 'upload_folder_id' => 0, 'email_address' => 'a@example.com, b@example.com', 'target_form_field_name' => 'City');
	pg_assert_same('North|North||City=Oslo,Bergen ^^a@example.com, b@example.com^^', pg_field_form_option_line($option, array('Oslo', 'Bergen')), 'trigger and e-mail');

	$option = array('label' => 'CV', 'value' => 'CV', 'default_selected' => 0, 'upload_folder_id' => '42', 'email_address' => '', 'target_form_field_name' => null);
	pg_assert_same('CV|CV|||42', pg_field_form_option_line($option, array()), 'upload folder only');
}

// What the edit screen prints is read back by the save the same way.
function test_field_form_option_line_round_trip()
{
	$option = array('label' => 'Sales', 'value' => 'sales', 'default_selected' => 1, 'upload_folder_id' => '7', 'email_address' => 'a@example.com', 'target_form_field_name' => 'Region');
	$parsed = pg_field_form_parse_option_line(pg_field_form_option_line($option, array('North', 'South')));

	pg_assert_same(array('Sales', 'sales', 1, 'a@example.com', 'Region=North,South', '7'), array($parsed['label'], $parsed['value'], $parsed['default_selected'], $parsed['email_address'], $parsed['trigger'], $parsed['upload_folder_id']), 'round trip');
}

function test_field_form_edit_screen_uses_shared_form()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_field.php');

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/field_form.php')", $source, 'loads the shared form');
	pg_assert_contains('pg_field_form_cards(', $source, 'draws the shared cards');
	pg_assert_contains('get_token_field()', $source, 'prints the token');
	pg_assert_contains('validate_token_field()', $source, 'checks the token');
	pg_assert_contains('pg_field_form_layout_warning(', $source, 'keeps the custom layout warning');
	pg_assert_contains('$designer_form', $source, 'keeps the read-only designer form view');
}
