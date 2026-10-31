<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a saved Order or Visitor Report filter value and the Edit Field
 * quiz Correct Answer leave prepare_form_data_for_output() raw where they go
 * on to a query, JavaScript or h(), so they are escaped once, and that the
 * Order Report breadcrumb label carries no markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function report_filter_values_escaping_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_report_filter_values_escaping_raw_value_round_trips()
{
	pg_assert_same("Ali'nin & Co", prepare_form_data_for_output("Ali'nin & Co", '', false));
	pg_assert_same('Ali&#039;nin &amp; Co', prepare_form_data_for_output("Ali'nin & Co", ''), 'default still escapes for HTML');
}

function test_report_filter_values_escaping_saved_filters_load_raw()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php') as $file) {
		$source = report_filter_values_escaping_test_source($file);

		pg_assert_contains("'value' => prepare_form_data_for_output(\$row['value'], \$field_type, false),", $source, $file);
		pg_assert_false(strpos($source, "'value' => prepare_form_data_for_output(\$row['value'], \$field_type),") !== false, $file . ' escaped loader');
	}
}

function test_report_filter_values_escaping_order_report_label_has_no_markup()
{
	$source = report_filter_values_escaping_test_source('view_order_report.php');

	pg_assert_false(strpos($source, '<strong>') !== false, 'breadcrumb labels are escaped by output_header()');
}

function test_report_filter_values_escaping_quiz_answer_escaped_once()
{
	$source = report_filter_values_escaping_test_source('includes/forms/field_form.php');

	pg_assert_contains("h(prepare_form_data_for_output(\$field['quiz_answer'], \$field['type'], false))", $source);
}
