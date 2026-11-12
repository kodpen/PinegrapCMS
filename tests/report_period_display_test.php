<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how two report screens name the period they were drawn for: the
 * Performance Log's period select marks the period of the list (the default
 * 7 days included), and the visitor report's date range under the period
 * buttons is drawn as dates, not as the text of their markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function report_period_display_test_source($screen)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);
}

// The period keys '1', '7' and '30' are integer keys in PHP, and the period
// read from the address is a string: compared as they are, no option was
// ever selected, the select showed "Last 24 hours" over a 7-day list, and
// changing the area sent range=1.
function test_performance_log_period_select_marks_the_period()
{
	$source = report_period_display_test_source('view_performance_log.php');

	pg_assert_same(array(1, 7, 30), array_keys(array('1' => 'a', '7' => 'b', '30' => 'c')), 'numeric string keys are held as integers');
	pg_assert_contains("\$range = isset(\$_GET['range']) && is_string(\$_GET['range']) && isset(\$range_options[\$_GET['range']]) ? \$_GET['range'] : '7';", $source, 'the period is a string, 7 days by default');
	pg_assert_contains("foreach (\$range_options as \$value => \$label) {\n    \$selected = ((string) \$value === \$range) ? ' selected' : '';", $source, 'the option of the period is selected');
	pg_assert_false(strpos($source, '($value === $range)') !== false, 'no integer key compared with the string period');
	pg_assert_contains('<select id="perf_log_range" name="range" class="form-control form-control-sm w-auto" onchange="this.form.submit()">\' . $range_select . \'</select>', $source, 'the select carries those options');
}

// get_absolute_time() draws a <time> element; the badge prints the range as
// it is, as the order report does, so it reads as two dates.
function test_visitor_report_date_range_reads_as_dates()
{
	$source = report_period_display_test_source('view_visitor_report.php');

	pg_assert_contains('<time datetime="2026-10-15">', get_absolute_time(array('timestamp' => mktime(12, 0, 0, 10, 15, 2026), 'type' => 'date')), 'the date is a time element');
	pg_assert_contains("\$start_date = get_absolute_time(array('timestamp' => \$start_timestamp, 'type' => 'date'));", $source, 'the start date as HTML');
	pg_assert_contains('<span class="badge fg-body fw-light border-2">\' . $output_date_range . \'</span>', $source, 'the range printed as markup');
	pg_assert_false(strpos($source, 'h($output_date_range)') !== false, 'the markup is not escaped into text');
}
