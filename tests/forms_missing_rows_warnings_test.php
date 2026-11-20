<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the warnings the PHP 8.5 error log showed from form, calendar and
 * default screens: a date value that is not a stored Y-m-d date, a page type
 * record saved without a collection, the default View Order and Registration
 * Entrance screens that pass no page, a field left without its form, a
 * submitted form edited through a page that is not a form item view, a
 * deletion or export with nothing chosen, and a recurring event instance of a
 * recurrence kind the date switch does not know.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function forms_missing_rows_test_source($file)
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// A date field holding text that is not Y-m-d reads its missing parts empty,
// with no warning, and prints what it printed before.
function test_form_date_output_of_a_value_that_is_not_a_stored_date()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'month_day');
	}

	$warned = false;
	set_error_handler(function () use (&$warned) {
		$warned = true;
		return true;
	});
	$odd = prepare_form_data_for_output('17.11.2026', 'date', false);
	$stored = prepare_form_data_for_output('2026-11-07', 'date', false);
	restore_error_handler();

	pg_assert_same(false, $warned, 'no warning on the way');
	pg_assert_same('//17.11.2026', $odd, 'text date as before');
	pg_assert_same(DATE_FORMAT == 'month_day' ? '11/7/2026' : '7/11/2026', $stored, 'stored date');
}

// A form list or item view record saved without a collection gets A.
function test_page_type_record_without_collection()
{
	$source = forms_missing_rows_test_source('includes/fn/forms.php');

	pg_assert_true(strpos($source, "if (empty(\$properties['collection'])) {") !== false, 'collection may be absent');
	pg_assert_same(0, substr_count($source, "if (!\$properties['collection']) {"), 'no bare read');
}

// The default screens (a site without the page) pass no page: the View Order
// screen then draws the system layout, as the default login screen does.
function test_default_screens_without_a_page()
{
	$view_order = forms_missing_rows_test_source('get_view_order_screen_content.php');

	pg_assert_true(strpos($view_order, "\$page_id = \$properties['page_id'] ?? '';") !== false, 'view order page id');
	pg_assert_true(strpos($view_order, "\$device_type = \$properties['device_type'] ?? '';") !== false, 'view order device type');
	pg_assert_true(strpos($view_order, "if (\$layout_type == 'system' || !\$layout_type) {") !== false, 'view order system layout without a page');
	pg_assert_true(strpos(forms_missing_rows_test_source('includes/fn/forms.php'), 'get_view_order_screen_content(array())') !== false, 'default screen passes no page');

	$entrance = forms_missing_rows_test_source('get_registration_entrance.php');

	pg_assert_true(strpos($entrance, "\$page_id = \$properties['page_id'] ?? '';") !== false, 'entrance page id');
	pg_assert_true(strpos($entrance, "\$device_type = \$properties['device_type'] ?? '';") !== false, 'entrance device type');
}

// A field with no page, product or variant set template is not found: no
// access check of the branches above ever ran for it.
function test_edit_field_without_its_form()
{
	$source = forms_missing_rows_test_source('edit_field.php');
	$product = strpos($source, '} elseif ($product_id != 0) {');
	$orphan = strpos($source, "} else {\n    output_error(lang('Record not found.')", (int) $product);

	if ($orphan === false) {
		$orphan = strpos($source, "} else {\r\n    output_error(lang('Record not found.')", (int) $product);
	}

	pg_assert_true($product !== false && $orphan !== false, 'orphan field refused after the product branch');
	pg_assert_true($orphan < strpos($source, "include_once('liveform.class.php');"), 'before the form is drawn');
	pg_assert_true(strpos($source, "((\$_POST['rss_field'] ?? '') == 'title')") !== false, 'title field may be absent');
}

// A page that is not a form item view has no row; a send-to without a query
// string has no query part.
function test_edit_submitted_form_missing_row_and_query()
{
	$source = forms_missing_rows_test_source('edit_submitted_form.php');

	pg_assert_true(strpos($source, "\$row = mysqli_fetch_assoc(\$result) ?: array();") !== false, 'form item view row fallback');
	pg_assert_true(strpos($source, "\$hook_code = \$row['hook_code'] ?? '';") !== false, 'hook code default');
	pg_assert_true(strpos($source, "((\$send_to_parts['query'] ?? '') != '')") !== false, 'query part may be absent');
}

// Delete with no box ticked, a new field without information, and an export
// with no form chosen read defaults.
function test_nothing_chosen_reads_defaults()
{
	$delete = forms_missing_rows_test_source('delete_fields.php');

	pg_assert_true(strpos($delete, "\$selected_fields = (isset(\$_POST['fields']) && is_array(\$_POST['fields'])) ? \$_POST['fields'] : array();") !== false, 'selected fields');
	pg_assert_same(0, substr_count($delete, "\$_POST['fields'] as"), 'loop over the checked list');

	pg_assert_true(strpos(forms_missing_rows_test_source('add_field.php'), "prepare_rich_text_editor_content_for_input(\$_POST['information'] ?? '')") !== false, 'information may be absent');

	$export = forms_missing_rows_test_source('view_submitted_forms.php');

	pg_assert_true(strpos($export, "['custom_forms'][0] ?? '')") !== false, 'no form in the advanced filter');
	pg_assert_true(strpos($export, "\$quiz = \$row['quiz'] ?? '';") !== false, 'no form row');
}

// A recurring instance starts on the event's own date before the switch.
function test_calendar_instance_date_starts_on_the_event_date()
{
	$source = forms_missing_rows_test_source('includes/fn/calendar.php');
	$start = strpos($source, "\$event_start_date = \$start_date;\n        switch (\$recurrence_type) {");

	if ($start === false) {
		$start = strpos($source, "\$event_start_date = \$start_date;\r\n        switch (\$recurrence_type) {");
	}

	pg_assert_true($start !== false, 'date set before the switch');
}
