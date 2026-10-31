<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a field's visible caption reaches the field: the code fields of
 * Edit Theme CSS, Edit JavaScript, Edit File and Generate Layout, the Create
 * File name and type, the submitted form pick lists, file uploads and choice
 * groups, the calendar event "Up To" pair and the order tracking numbers.
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

	pg_assert_contains('<label class="col fg-secondary mt-5" for="\' . h($shipment_numbers_field) . \'">', $source, 'the tracking numbers caption is a label for the chips field');
}
