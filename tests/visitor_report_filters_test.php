<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/visitor_report.php and the visitor report screen:
 * a filter with no known field adds no SQL, the paid / organic channel
 * follows the daily summary's rule, a "day(s) ago" count takes only whole
 * numbers, and the screen keeps what was entered after a save error.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function visitor_report_filters_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor_report.php');
}

// An empty or unknown field has no column: such a filter is left out of the
// SQL instead of building "IFNULL(, '')".
function test_visitor_report_filter_column_unknown_field()
{
	pg_assert_same('', pg_visitor_report_filter_column(''), 'empty field');
	pg_assert_same('', pg_visitor_report_filter_column('no_such_field'), 'unknown field');
	pg_assert_same('', pg_visitor_report_filter_column(array('country')), 'an array is no field');
	pg_assert_same('visitors.country', pg_visitor_report_filter_column('country'), 'country');
	pg_assert_same('visitors.page_views', pg_visitor_report_filter_column('page_views'), 'page views');
}

// The grouping and the filter read the channel as the daily summary does:
// with no flag set nothing is paid.
function test_visitor_report_channel_sql()
{
	$group = pg_visitor_report_channel_sql(false);
	$filter = pg_visitor_report_channel_sql(true);

	pg_assert_contains(pg_visitor_paid_organic_sql('visitors.'), $group, 'grouping reads the shared channel');
	pg_assert_contains("WHEN 'organic' THEN 'Organic'", $group, 'grouping names organic');
	pg_assert_contains("ELSE '' END", $group, 'grouping leaves the rest empty');
	pg_assert_contains(pg_visitor_paid_organic_sql('visitors.'), $filter, 'filter reads the shared channel');
	pg_assert_contains("'neither'", $filter, 'filter names the rest neither');

	if (!defined('PAY_PER_CLICK_FLAG') || (trim((string) PAY_PER_CLICK_FLAG) === '')) {
		pg_assert_false(strpos($group, "LIKE '%%'") !== false, 'no pattern that matches every visit');
		pg_assert_false(strpos(pg_visitor_paid_organic_sql('visitors.'), 'pay_per_click') !== false, 'no paid branch without a flag');
	}

	$source = visitor_report_filters_source();
	pg_assert_false(strpos($source, 'escape(escape_like(PAY_PER_CLICK_FLAG))') !== false, 'no own flag pattern on the screen');
}

function test_visitor_report_days_ago_valid()
{
	foreach (array('', '0', '5', ' 7 ', '4294967295') as $text) {
		pg_assert_true(pg_visitor_report_days_ago_valid($text), 'valid: ' . $text);
	}

	foreach (array('-5', 'abc', '1.5', '4294967296', '99999999999', array('1'), null) as $text) {
		pg_assert_false(pg_visitor_report_days_ago_valid($text), 'invalid: ' . var_export($text, true));
	}
}

// The form came back from a save when the hidden last_filter_number is in
// the session; the Save button is submit_save, so submit_button (Delete)
// never told it.
function test_visitor_report_form_after_save_error()
{
	$source = visitor_report_filters_source();

	pg_assert_false(strpos($source, "field_in_session('submit_button')") !== false, 'no check on the Delete button name');
	pg_assert_contains("\$form_submitted = \$liveform->field_in_session('last_filter_number');", $source, 'submitted read from the filter count');
	pg_assert_contains('$returned_with_error', $source, 'form opens again on an error');
}

// Every group carries a visitors list, so a page of detail rows that has
// none of a group's visitors prints no warning.
function test_visitor_report_groups_have_visitor_lists()
{
	$source = visitor_report_filters_source();

	pg_assert_same(3, substr_count($source, "'visitors' => array()) + array_fill_keys("), 'each level starts an empty visitors list');
}

// The date box gets the panel's picker; a date is stored as YYYY-MM-DD.
function test_visitor_report_date_filter()
{
	$source = visitor_report_filters_source();

	pg_assert_contains('get_date_picker_format() . pg_fields_script()', $source, 'date picker script printed');
	pg_assert_contains('pg_date_input_iso($filter_value)', $source, 'date stored as ISO');
	pg_assert_contains("lang('Please enter a valid date.')", $source, 'unreadable date rejected');
}

// The report's name is on the tab and in the breadcrumb, escaped.
function test_visitor_report_name_shown()
{
	$source = visitor_report_filters_source();

	pg_assert_contains("'title'=> lang('Visitor Report') . ': ' . h(\$output_visitor_report_name)", $source, 'tab title');
	pg_assert_contains("array('label' => h(\$output_visitor_report_name))", $source, 'breadcrumb');
	pg_assert_false(strpos($source, '$output_screen_name') !== false, 'unused screen name removed');
}

// An empty landing page shows '-' like the other fields, and the IP address
// is written once.
function test_visit_details_landing_page_and_ip()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor.php');

	pg_assert_contains("((\$landing_page_name != '') ? '<a ", $source, 'landing page link only when set');
	pg_assert_same(1, substr_count($source, 'h($ip_address_text)'), 'IP address once');
}
