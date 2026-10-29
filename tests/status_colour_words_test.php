<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for states and series that were told apart by colour alone:
 * the dashboard System Status chips and their detail rows carry the state
 * word, the orders list's Payment methods doughnut names its counts in the
 * legend and the canvas name, and the Search Console day chart draws
 * Impressions dashed with titled axes and marks the current period link.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function status_colour_words_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_status_colour_words_system_status_chips()
{
	$source = status_colour_words_source('includes/dashboard/widgets/widget_2.php');

	// Warn and fail chips print the generic word; OK and info chips keep it
	// for screen readers only.
	pg_assert_contains("in_array(\$health_check['state'], array('warn', 'fail'), true)", $source, 'generic word shown on warn and fail chips');
	pg_assert_contains('<span class="visually-hidden">\' . h($health_check[\'value\']) . \'</span>', $source, 'generic word hidden on calm chips');
	pg_assert_true(strpos($source, "? ''\n                : '<span class=\"pg-check-value\">'") === false, 'the generic word is no longer dropped');
}

function test_status_colour_words_system_status_detail_rows()
{
	$source = status_colour_words_source('includes/dashboard/widgets/widget_2.php');

	pg_assert_same(0, substr_count($source, 'pg-health-dot fg-\' . h(($detail_row'), 'both detail loops go through the shared row');
	pg_assert_contains('pg-health-dot fg-\' . h($row_tone) . \'" aria-hidden="true"', $source, 'the dot is decoration');
	pg_assert_contains("'ok'   => lang('OK')", $source, 'OK word');
	pg_assert_contains("'warn' => lang('Warning')", $source, 'Warning word');
	pg_assert_contains("'fail' => lang('Problem')", $source, 'Problem word');
	pg_assert_same(2, substr_count($source, '$status_detail_row($detail_row)'), 'chip and job panels both use it');
}

function test_status_colour_words_payment_methods_chart()
{
	$source = status_colour_words_source('view_orders.php');

	pg_assert_contains("aria-label=\"' . h(\$payment_summary) . '\"", $source, 'canvas named with method: count');
	pg_assert_contains('pg_format_number($payment_row[\'count\'], 0)', $source, 'counts in the panel number format');
	pg_assert_contains('generateLabels: function (chart)', $source, 'legend carries the counts');
	pg_assert_contains('Chart.overrides.doughnut.plugins.legend.labels.generateLabels(chart)', $source, 'built on the doughnut default');
}

function test_status_colour_words_search_console_chart()
{
	$source = status_colour_words_source('search_console.php');

	pg_assert_contains('borderDash: [6, 4]', $source, 'Impressions dashed');
	pg_assert_contains('pointStyle: "rectRot"', $source, 'Impressions has its own point shape');
	pg_assert_contains('usePointStyle: true', $source, 'legend shows the point shape');
	pg_assert_contains("title: { display: true, text: ' . json_encode(lang('Clicks'))", $source, 'Clicks axis titled');
	pg_assert_contains("title: { display: true, text: ' . json_encode(lang('Impressions'))", $source, 'Impressions axis titled');
	pg_assert_contains("' aria-current=\"page\"'", $source, 'current period link marked');
}
