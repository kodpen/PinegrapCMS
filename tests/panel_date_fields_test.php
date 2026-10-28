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

	pg_assert_contains("\$time_hook = array('data-pg-time' => '');", $row, 'the panel preview hooks the time row');
	pg_assert_contains("\$picker_kinds['time'] = true;", $row, 'and asks for the picker scripts');
	pg_assert_contains("if (\$interface == 'backend')", $row, 'only in the panel');
	pg_assert_contains("+ \$time_hook", $row, 'the hook is printed on the field');
	pg_assert_contains("'id' => \$html_field_name", $row, 'the box is named by an id like the other date rows');
	pg_assert_contains('pg_form_info_picker_format($picker_kinds)', $source, 'the options follow the rows');
}
