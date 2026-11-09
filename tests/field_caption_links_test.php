<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a field's visible caption reaches the field: the code fields of
 * Edit Theme CSS, Edit JavaScript, Edit File and Generate Layout, the Create
 * File name and type, the submitted form pick lists, file uploads and choice
 * groups, the calendar event "Up To" pair, the order tracking numbers and
 * the Duplicate Products options.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_field_caption_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The code editor names its content from the textarea's label
// (pgCeTextareaName() in pg_code_editor.js), so the label must point at it.
function test_field_caption_code_field_labels()
{
	foreach (array('edit_theme_css.php', 'edit_javascript.php', 'edit_file.php') as $file) {
		pg_assert_contains('<label class="form-label" for="code">', pg_test_field_caption_source($file), $file . ' ties its caption to #code');
	}

	pg_assert_contains("'aria-label' => lang('Layout')", pg_test_field_caption_source('includes/templates/generate_layout.php'), 'Generate Layout names its layout field');
}

function test_field_caption_create_file()
{
	$source = pg_test_field_caption_source('create_file.php');

	pg_assert_contains('id="name" aria-label="\' . h(lang(\'File Name\')) . \'"', $source, 'the file name box is named');
	pg_assert_contains('id="type" aria-label="\' . h(lang(\'File Type\')) . \'"', $source, 'the extension select is named');
}

function test_field_caption_submitted_form_fields()
{
	$source = pg_test_field_caption_source('includes/forms/submitted_form_form.php');

	pg_assert_contains("output_field(array('type'=>'select', 'name'=>\$name, 'id'=>\$field['id']", $source, 'the pick list gets the field id');
	pg_assert_contains("\$file_attributes = array('type'=>'file', 'name'=>\$field['id'], 'id'=>\$field['id']", $source, 'the file upload gets the field id');
	pg_assert_true(substr_count($source, '<label class="form-label" for="\' . h($field[\'id\']) . \'">') >= 4, 'text box, text area, pick list and file upload captions point at the field');
	pg_assert_same(2, substr_count($source, '<legend class="form-label fs-md">\' . h($field[\'label\']) . \'</legend>'), 'radio and check box groups carry their question as a legend');
}

function test_field_caption_calendar_event_up_to()
{
	$source = pg_test_field_caption_source('includes/forms/calendar_event_form.php');

	pg_assert_contains("'id'=>'total_recurrence_number', 'name'=>'total_recurrence_number', 'aria-label'=>lang('Up To')", $source, 'the repeat count is named');
	pg_assert_contains("'id'=>'recurrence_type', 'name'=>'recurrence_type', 'aria-label'=>lang('Repeat')", $source, 'the repeat unit is named');
}

// drawChips() names the typed box only from the hidden input's <label>.
function test_field_caption_order_tracking_numbers()
{
	$source = pg_test_field_caption_source('view_order.php');

	pg_assert_contains('<label class="form-label" for="\' . h($shipment_numbers_field) . \'">', $source, 'the tracking numbers caption is a label for the chips field');
}

// The ship and delivery date boxes have captions that name their fields.
function test_field_caption_order_ship_dates()
{
	$source = pg_test_field_caption_source('view_order.php');

	pg_assert_contains('<label class="form-label" for="\' . $ship_dates_field_id . \'_\' . $date_column . \'">', $source, 'each date caption is a label for its field');
	pg_assert_contains("\$ship_dates_field_id = 'ship_to_id_' . (int) \$ship_tos[\$key]['ship_to_id'];", $source, 'the field ids the liveform fields use');
}

// Duplicate Products: each copy option sits in a labelled form-field box and
// the letter count's help text is read with its field.
function test_field_caption_duplicate_products_options()
{
	$source = pg_test_field_caption_source('duplicate_products.php');

	foreach (array('lettertodelete', 'newletter', 'option_id') as $id) {
		pg_assert_contains('<label class="form-label" for="' . $id . '">', $source, 'the ' . $id . ' caption points at its field');
	}

	pg_assert_contains('aria-describedby="lettertodelete_help"', $source, 'the letter count names its help text');
	pg_assert_contains('<div class="form-text" id="lettertodelete_help">', $source, 'the help text carries the id');
	pg_assert_same(3, substr_count($source, '<div class="form-field">'), 'the three options are form-field boxes');
}

function test_field_caption_edit_booking_form_fields()
{
	$source = pg_test_field_caption_source('edit_booking.php');

	foreach (array('choice_staff', 'choice_service', 'choice_date') as $id) {
		pg_assert_contains('<label class="form-label" for="' . $id . '">', $source, 'the ' . $id . ' caption points at its field');
	}

	pg_assert_contains('<div class="form-field mb-5">', $source, 'the booking fields are form-field boxes');
	pg_assert_same(3, substr_count($source, '<div class="form-field">'), 'the three choices are form-field boxes');
	pg_assert_same(4, substr_count($source, "'aria-describedby' => 'booking_reach_help'"), 'e-mail and phone name the reach hint on both screens');
	pg_assert_same(2, substr_count($source, '<div class="form-text mb-5" id="booking_reach_help">'), 'the reach hint is a form-text with its id');
	pg_assert_contains("<div class=\"form-text\" id=\"' . h(\$name) . '_help\">", $source, 'a field help text is a form-text with the id the field names');
	pg_assert_contains("lang('Kept in the notes when the status becomes Cancelled.'))", $source, 'the cancel reason passes its help text to the field');
}
