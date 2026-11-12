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

function test_field_form_posted_field()
{
	$stored = pg_field_form_new_field('7');
	$stored['id'] = '12';
	$stored['name'] = 'Terms';
	$stored['label'] = 'Old label';
	$stored['type'] = 'text box';
	$stored['multiple'] = 1;
	$stored['information'] = '<p>Old</p>';

	// A sent-back form: the boxes keep what was typed, a check box that was
	// not posted is off and one that was posted is on.
	$field = pg_field_form_posted_field($stored, array(
		'name' => 'Terms & Conditions',
		'type' => 'pick list',
		'label' => 'New label',
		'size' => '30',
		'default_value' => 'yes',
		'options' => "Yes\nNo",
		'information' => '<p>New</p>',
		'spacing_above' => '1',
	));

	pg_assert_same('12', $field['id'], 'the id stays the stored one');
	pg_assert_same('Terms & Conditions', $field['name'], 'name');
	pg_assert_same('pick list', $field['type'], 'type');
	pg_assert_same('New label', $field['label'], 'label');
	pg_assert_same('30', $field['size'], 'size');
	pg_assert_same('yes', $field['default_value'], 'default value');
	pg_assert_same("Yes\nNo", $field['options'], 'choices');
	pg_assert_same('<p>New</p>', $field['information'], 'information');
	pg_assert_same(1, $field['spacing_above'], 'a posted check box is on');
	pg_assert_same(0, $field['multiple'], 'a check box left out of the post is off');
	pg_assert_same(0, $field['required'], 'required left out of the post is off');
	pg_assert_same('7', $field['upload_folder_id'], 'a box that was not on the form keeps its value');
	pg_assert_same(0, $field['translate_values'], 'translation left out of the post is off');

	// A multiple value (never posted by these boxes) falls back to its first entry.
	$field = pg_field_form_posted_field($stored, array('label' => array('A', 'B')));
	pg_assert_same('A', $field['label'], 'an array value gives its first entry');
}

function test_field_form_name_taken()
{
	// $names_in_use are the names the database compares equal to the posted
	// one (form_fields.name is utf8mb4_unicode_ci: no case, no diacritics).
	pg_assert_same(true, pg_field_form_name_taken('klasor', array('klasor')), 'a name another field of the form has');
	pg_assert_same(true, pg_field_form_name_taken('Klasör', array('KLASÖR')), 'compared without case, multibyte too');
	pg_assert_same(true, pg_field_form_name_taken('klasör', array('klasor')), 'a name that differs only by a diacritic');
	pg_assert_same(true, pg_field_form_name_taken('şehir', array('sehir')), 'ş and s are one letter to the database');
	pg_assert_same(false, pg_field_form_name_taken('klasor', array()), 'a free name');
	pg_assert_same(false, pg_field_form_name_taken('klasor', array('klasor'), 'Klasor'), 'an existing duplicate saved under its own name keeps working');
	pg_assert_same(false, pg_field_form_name_taken('klasör', array('klasor'), 'klasör'), 'an existing diacritic twin saved under its own name keeps working');
	pg_assert_same(true, pg_field_form_name_taken('klasor', array('klasor'), 'dosya'), 'renamed onto a name in use');
	pg_assert_same(true, pg_field_form_name_taken('klasor', array('klasor'), 'klasör'), 'renamed onto a diacritic twin in use');
	pg_assert_same(false, pg_field_form_name_taken('', array('')), 'a blank name is the blank check\'s business');
}

function test_field_form_pick_by_name()
{
	$rows = array(
		array('id' => '851', 'name' => 'klasor'),
		array('id' => '852', 'name' => 'klasör'),
	);

	pg_assert_same('852', pg_field_form_pick_by_name($rows, 'klasör')['id'], 'the exact name wins over a diacritic twin read first');
	pg_assert_same('851', pg_field_form_pick_by_name($rows, 'klasor')['id'], 'the exact name');
	pg_assert_same('852', pg_field_form_pick_by_name(array_reverse($rows), 'klasör')['id'], 'whatever the row order');
	pg_assert_same('851', pg_field_form_pick_by_name($rows, 'KLASOR')['id'], 'the same name in other case before a twin');
	pg_assert_same('852', pg_field_form_pick_by_name($rows, 'KLASÖR')['id'], 'other case, multibyte too');
	pg_assert_same('851', pg_field_form_pick_by_name($rows, 'klasőr')['id'], 'no closer name: the first row, as the database matched it');
	pg_assert_same(null, pg_field_form_pick_by_name(array(), 'klasor'), 'no row');
}

function test_field_form_types()
{
	$custom = pg_field_form_types('custom', false);
	pg_assert_true(in_array('text box', $custom, true), 'custom form: text box');
	pg_assert_true(in_array('file upload', $custom, true), 'custom form: file upload');
	pg_assert_true(!in_array('signature', $custom, true), 'no signature column: no signature');
	pg_assert_true(in_array('signature', pg_field_form_types('custom', true), true), 'signature column: signature');

	$product = pg_field_form_types('product', true);
	pg_assert_true(!in_array('file upload', $product, true), 'product form: no file upload');
	pg_assert_true(!in_array('signature', $product, true), 'product form: no signature');
	pg_assert_true(in_array('time', $product, true), 'product form: time');
	pg_assert_true(!in_array('', $product, true), 'no empty type');
}

function test_field_form_number()
{
	pg_assert_same(array(true, 0), pg_field_form_number('', 1000), 'empty is 0');
	pg_assert_same(array(true, 0), pg_field_form_number('  ', 1000), 'blank is 0');
	pg_assert_same(array(true, 25), pg_field_form_number(' 25 ', 1000), 'a number');
	pg_assert_same(array(true, 1000), pg_field_form_number('1000', 1000), 'the maximum');
	pg_assert_same(array(false, 0), pg_field_form_number('1001', 1000), 'above the maximum');
	pg_assert_same(array(false, 0), pg_field_form_number('-5', 1000), 'negative');
	pg_assert_same(array(false, 0), pg_field_form_number('12.50', 1000), 'decimal');
	pg_assert_same(array(false, 0), pg_field_form_number('12,50', 1000), 'decimal comma');
	pg_assert_same(array(false, 0), pg_field_form_number('99999999999', 65535), 'too large');
	pg_assert_same(array(false, 0), pg_field_form_number('abc', 1000), 'not a number');
	pg_assert_same(array(false, 0), pg_field_form_number(array('1'), 1000), 'an array');
}

function test_field_form_option_lines_skip_blank()
{
	$options = pg_field_form_option_lines("Yes\n\n  \r\nNo\n");
	pg_assert_same(2, count($options), 'blank lines skipped');
	pg_assert_same('Yes', $options[0]['label'], 'first');
	pg_assert_same('No', $options[1]['label'], 'second');

	$options = pg_field_form_option_lines("Select…|\n|\nRed|red|on");
	pg_assert_same(2, count($options), 'a labelled empty value stays, a bare bar goes');
	pg_assert_same('', $options[0]['value'], 'labelled empty value');
	pg_assert_same(1, $options[1]['default_selected'], 'third part kept');

	pg_assert_same(array(), pg_field_form_option_lines(''), 'nothing');
}

function test_field_form_number_boxes()
{
	pg_assert_same(array('size', 'maxlength'), pg_field_form_number_boxes('text box'), 'text box');
	pg_assert_same(array('maxlength', 'rows', 'cols'), pg_field_form_number_boxes('text area'), 'text area');
	pg_assert_same(array('size'), pg_field_form_number_boxes('pick list'), 'pick list');
	pg_assert_same(array(), pg_field_form_number_boxes('radio button'), 'radio button');
	pg_assert_same(array(), pg_field_form_number_boxes(''), 'no type');
}

function test_field_form_wide_layout()
{
	$row = pg_field_form_status_row('Name', '<span>x</span>', false);
	pg_assert_contains('<dt class="fw-normal">Name</dt>', $row, 'status row label');
	pg_assert_contains('<span>x</span></dd>', $row, 'status row value as given');
	pg_assert_contains('border-bottom', $row, 'a row that is not the last draws its line');
	pg_assert_true(strpos(pg_field_form_status_row('A', 'b'), 'border-bottom') === false, 'the last row draws no line');

	$layout = pg_field_form_layout('<!--cards-->', '<button>Save</button>');
	pg_assert_contains('<!--cards-->', $layout, 'layout holds the columns');
	pg_assert_contains('col-12 order-3 position-sticky', $layout, 'sticky save bar column');
	pg_assert_contains('<button>Save</button>', $layout, 'save bar holds the buttons');

	$form = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/field_form.php');
	pg_assert_contains('col-12 lg:col-8 2xl:col-9 order-1', $form, 'form column');
	pg_assert_contains('col-12 lg:col-4 2xl:col-3 order-2', $form, 'status column after the form column');
	pg_assert_contains('<div class="card mb-7 collapse" id="options_field_row">', $form, 'the settings card waits for a type, straight in the form column');

	foreach (array('add_field.php', 'edit_field.php') as $file) {
		$screen = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_field_form_layout(', $screen, $file . ' draws the shared layout');
		pg_assert_contains("'form_label' =>", $screen, $file . ' names the form in the Summary');
	}

	$edit = file_get_contents(PG_FUNCTIONS_DIR . '/edit_field.php');
	pg_assert_contains("lang('Last Modified')", $edit, 'edit shows Last Modified');
	pg_assert_contains('theme-danger" formnovalidate', $edit, 'an emptied box does not block Delete');
}
