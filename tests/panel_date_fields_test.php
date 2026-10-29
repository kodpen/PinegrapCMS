<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel fields that reach the one date-and-time control
 * outside the shared form wrappers: the form preview's time row and the
 * picker options it prints, the waybill's 24-hour shipment time, the
 * shipping method's excluded dates picked into their chips, the end-of-day
 * time in the commerce settings and the value box of a list filter row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_date_fields_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The picker options a previewed form prints after its fields: the time
// wording when a date and time field is there, the date options for any
// other date or time field, nothing without one.
function test_panel_date_fields_form_preview_picker_options()
{
	pg_assert_same('', pg_form_info_picker_format(array()), 'no date or time field, no options');
	pg_assert_same(get_date_picker_format(), pg_form_info_picker_format(array('time' => true)), 'a time row alone still gets the site date order');
	pg_assert_same(get_date_picker_format(), pg_form_info_picker_format(array('date' => true)), 'a date row');
	pg_assert_same(get_date_time_picker_format(), pg_form_info_picker_format(array('date' => true, 'date and time' => true, 'time' => true)), 'the time wording when a date and time row is there');
}

// get_form_info() reads its fields from the database, so the time row is
// read from the source: the panel preview hooks it, the site keeps a plain
// text box until the site steps move it.
function test_panel_date_fields_form_preview_time_row()
{
	$source = panel_date_fields_test_source('includes/fn/content.php');
	$start = strpos($source, "case 'time':", (int) strpos($source, "case 'information':"));
	pg_assert_true($start !== false, 'the time row is there');
	$row = substr($source, $start, strpos($source, 'break;', $start) - $start);

	pg_assert_contains("'data-pg-time' => ''", $row, 'the time row is hooked, in the panel preview and on the site alike');
	pg_assert_contains("\$picker_kinds['time'] = true;", $row, 'the panel preview asks for the picker scripts');
	pg_assert_contains("if (\$interface == 'backend')", $row, 'only the panel prints them; a site page brings its own');
	pg_assert_contains("'id' => \$html_field_name", $row, 'the box is named by an id like the other date rows');
	pg_assert_contains('pg_form_info_picker_format($picker_kinds)', $source, 'the options follow the rows');
}

function test_panel_date_fields_waybill_ship_time()
{
	$source = panel_date_fields_test_source('includes/erp/waybill_form.php');

	pg_assert_contains("'type' => 'text', 'id' => 'ship_time', 'data-pg-time' => 'hh:mm', 'name' => 'ship_time'", $source, 'the shipment time keeps its HH:MM text on the control');
	pg_assert_false(strpos($source, "'placeholder' => 'HH:MM'") !== false, 'the box shows the site clock, not a fixed hint');
	pg_assert_contains('get_date_picker_format() . pg_fields_script()', $source, 'the screen prints the options with the script');
}

function test_panel_date_fields_excluded_dates_picked_into_chips()
{
	$source = panel_date_fields_test_source('includes/forms/shipping_method_form.php');

	pg_assert_contains('id="excluded_transit_dates" class="form-control tagin min-height-tagin" data-pg-chips', $source, 'the chips stay the posted field');
	pg_assert_contains('<input type="text" id="excluded_transit_date_pick" class="form-control mt-2" data-pg-date data-pg-add-to-chips="excluded_transit_dates" autocomplete="off" placeholder="\' . h(lang(\'Add Date\')) . \'" aria-label="\' . h(lang(\'Add Date\')) . \'"/>', $source, 'a calendar box beside them, not posted');

	$js = panel_date_fields_test_source('assets/js/backend.src.js');
	pg_assert_contains("\$(document).on('change', '[data-pg-add-to-chips]'", $js, 'a picked date is added to its chips');
	pg_assert_contains('window.pgChips.set(chips, list.concat(value))', $js, 'through the chips wrapper');
	pg_assert_contains('list.indexOf(value) === -1', $js, 'a date already in the list is not added twice');
	pg_assert_contains("window.pgDatePicker.set(this, '')", $js, 'the box empties for the next date');
}

function test_panel_date_fields_commerce_settings_print_the_date_order()
{
	$source = panel_date_fields_test_source('includes/settings/commerce.php');

	pg_assert_contains('get_date_picker_format() . pg_fields_script()', $source, 'the end-of-day time prints the options with the script');
}

function test_panel_date_fields_filter_value_box()
{
	$js = panel_date_fields_test_source('assets/js/backend.src.js');

	pg_assert_contains("var FILTER_VALUE_HOOKS = {'date': 'data-pg-date', 'date and time': 'data-pg-datetime', 'time': 'data-pg-time'};", $js, 'each date or time field gets its hook');
	pg_assert_contains("(hook ? ' ' + hook : '')", $js, 'printed on the value box');
	pg_assert_contains('window.pgFields.destroy(old_value)', $js, 'the previous control is removed before the cell is redrawn');
	pg_assert_contains('window.pgFields.init(cell)', $js, 'the new box is drawn');
	pg_assert_contains('window.pgFields.destroy(value_box)', $js, 'a deleted row takes its control with it');
	pg_assert_contains('function pg_filter_value_clear(field)', $js, 'a dynamic value empties the box through its wrapper');
	pg_assert_same(1, substr_count($js, 'function clear_value(filter_number)'), 'one clear_value');

	pg_assert_true(strpos(panel_date_fields_test_source('edit_form_list_view.php'), 'get_date_picker_format() . pg_fields_script()') !== false, 'the form list view prints the options with the script');
}
