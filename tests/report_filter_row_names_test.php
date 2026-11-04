<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the report filter rows (Order Report, Visitor Report, form list
 * view) name every control by its column and row, name the delete button by
 * its action with the icon hidden, ask the "Day(s) Ago" count as a whole
 * number from 0, and that the order report keeps a stored 0 day count.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function report_filter_row_names_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The part of backend.src.js from create_filter() to clear_value().
function report_filter_row_names_test_script()
{
	$source = report_filter_row_names_test_source('assets/js/backend.src.js');
	$start = strpos($source, 'function pg_filter_control_name(');
	$end = strpos($source, 'function clear_value(');

	pg_assert_true($start !== false && $end !== false && $end > $start, 'filter row functions found');

	return substr($source, $start, $end - $start);
}

function test_report_filter_row_names_every_control_named()
{
	$script = report_filter_row_names_test_script();

	foreach (array("lang('Field')", "lang('Operator')", "lang('Value')", "lang('Dynamic Value')", "lang('Day(s) Ago')") as $heading) {
		pg_assert_contains('aria-label="\' + prepare_content_for_html(pg_filter_control_name(' . $heading . ', filter_number))', $script, $heading);
	}

	// both value controls: the pick list and the text or date box
	pg_assert_same(2, substr_count($script, "pg_filter_control_name(lang('Value'), filter_number)"));
}

function test_report_filter_row_names_delete_button_named()
{
	$script = report_filter_row_names_test_script();

	pg_assert_contains('<span class="material-icons" aria-hidden="true">delete</span></button>', $script);
	pg_assert_contains("lang('Delete filter')", $script);
	pg_assert_false(strpos($script, 'material-icons" onclick') !== false, 'no bare icon button');
}

function test_report_filter_row_names_day_count_is_whole_number()
{
	$script = report_filter_row_names_test_script();

	pg_assert_contains('_dynamic_value_attribute" type="number" min="0" step="1"', $script);
	pg_assert_contains("lang('day(s)')", $script);
	pg_assert_contains("_dynamic_value_unit", $script);
}

function test_report_filter_row_names_words_reach_the_script()
{
	$source = report_filter_row_names_test_source('includes/fn/output.php');

	foreach (array('Field', 'Operator', 'Value', 'Dynamic Value', 'Filter {var:1}', 'Delete filter', 'day(s)', 'Day(s) Ago') as $key) {
		pg_assert_contains("'" . $key . "' => lang('" . $key . "'),", $source, $key);
	}
}

function test_report_filter_row_names_order_report_keeps_zero_days()
{
	$source = report_filter_row_names_test_source('view_order_report.php');

	pg_assert_contains("if (\$filter['dynamic_value'] != 'days ago') {", $source);
	pg_assert_false(strpos($source, "if (\$filter['dynamic_value_attribute'] == 0) {") !== false, 'a 0 count is not blanked');
}
