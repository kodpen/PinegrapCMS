<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor report's period presets (includes/fn/visitor_report.php)
 * and the screens around it: each preset covers the days the overview's
 * period of the same key covers, a saved start_* / stop_* address still
 * opens its range, the order columns are drawn only with e-commerce on, and
 * the saved reports list and the visit screen lead to the overview.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function visitor_report_presets_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function visitor_report_presets_range($from, $to)
{
	return pg_list_period_range_from_dates($from, $to);
}

// Wednesday 15 April 2026, 14:30.
function visitor_report_presets_now()
{
	return mktime(14, 30, 0, 4, 15, 2026);
}

function test_visitor_report_preset_range_days()
{
	$now = visitor_report_presets_now();

	pg_assert_same(visitor_report_presets_range('2026-04-15', '2026-04-15'), pg_visitor_report_preset_range('today', $now), 'today');
	pg_assert_same(visitor_report_presets_range('2026-04-14', '2026-04-14'), pg_visitor_report_preset_range('yesterday', $now), 'yesterday');
	pg_assert_same(visitor_report_presets_range('2026-04-09', '2026-04-15'), pg_visitor_report_preset_range('7d', $now), 'seven days end today');
	pg_assert_same(visitor_report_presets_range('2026-03-17', '2026-04-15'), pg_visitor_report_preset_range('30d', $now), 'thirty days');
	pg_assert_same(visitor_report_presets_range('2026-01-16', '2026-04-15'), pg_visitor_report_preset_range('90d', $now), 'ninety days');
	pg_assert_same(visitor_report_presets_range('2026-04-01', '2026-04-15'), pg_visitor_report_preset_range('month', $now), 'this month through today');
	pg_assert_same(visitor_report_presets_range('2026-03-01', '2026-03-31'), pg_visitor_report_preset_range('last_month', $now), 'last month');
	pg_assert_same(visitor_report_presets_range('2025-12-01', '2025-12-31'), pg_visitor_report_preset_range('last_month', mktime(9, 0, 0, 1, 3, 2026)), 'last month across the year');
	pg_assert_same(null, pg_visitor_report_preset_range('week', $now), 'unknown key');
	pg_assert_same(null, pg_visitor_report_preset_range(array('7d'), $now), 'an array is no key');
}

function test_visitor_report_preset_keys_follow_the_overview()
{
	pg_assert_same(array('today', 'yesterday', '7d', '30d', '90d', 'month', 'last_month'), pg_visitor_report_preset_keys(), 'keys and order');
}

function test_visitor_report_requested_range()
{
	$now = visitor_report_presets_now();

	pg_assert_same(pg_visitor_report_preset_range('7d', $now), pg_visitor_report_requested_range(array('p' => '7d', 'id' => '4'), $now), 'preset');
	pg_assert_same(visitor_report_presets_range('2026-02-01', '2026-02-10'), pg_visitor_report_requested_range(array('p' => 'custom', 'from' => '2026-02-10', 'to' => '2026-02-01'), $now), 'custom range, reversed ends swapped');
	pg_assert_same(visitor_report_presets_range('2026-02-10', '2026-02-10'), pg_visitor_report_requested_range(array('p' => 'custom', 'from' => '2026-02-10'), $now), 'custom with one end is one day');
	pg_assert_same(null, pg_visitor_report_requested_range(array('p' => 'custom', 'from' => '2026-02-30'), $now), 'no real date');
	pg_assert_same(null, pg_visitor_report_requested_range(array('p' => 'nonsense'), $now), 'unknown preset keeps the range');

	$saved = array('start_month' => '3', 'start_day' => '5', 'start_year' => '2026', 'stop_month' => '03', 'stop_day' => '09', 'stop_year' => '2026');
	pg_assert_same(visitor_report_presets_range('2026-03-05', '2026-03-09'), pg_visitor_report_requested_range($saved, $now), 'a saved start_* / stop_* address');

	$saved['stop_day'] = array('9');
	pg_assert_same(null, pg_visitor_report_requested_range($saved, $now), 'an array part is not given');
	pg_assert_same(null, pg_visitor_report_requested_range(array(), $now), 'nothing asked');
}

function test_visitor_report_active_preset()
{
	$now = visitor_report_presets_now();

	pg_assert_same('30d', pg_visitor_report_active_preset(pg_visitor_report_preset_range('30d', $now), $now), 'thirty days');
	pg_assert_same('today', pg_visitor_report_active_preset(array('start_month' => '4', 'start_day' => '15', 'start_year' => '2026', 'stop_month' => '4', 'stop_day' => '15', 'stop_year' => '2026'), $now), 'unpadded parts compare as dates');
	pg_assert_same('', pg_visitor_report_active_preset(visitor_report_presets_range('2026-02-01', '2026-02-10'), $now), 'a range no preset covers');
	pg_assert_same('', pg_visitor_report_active_preset(array(), $now), 'no range');
}

function test_visitor_report_screen_uses_presets()
{
	$source = visitor_report_presets_source('view_visitor_report.php');

	pg_assert_contains('pg_visitor_report_requested_range($_GET', $source, 'the screen reads the asked range');
	pg_assert_contains('pg_visitor_report_preset_keys()', $source, 'the screen draws the presets');
	pg_assert_false(strpos($source, 'pg_list_period_week_steps') !== false, 'no week stepper');
	pg_assert_false(strpos($source, 'pg_list_period_month_steps') !== false, 'no month stepper');
}

// Every order cell of the table (heading, group rows, visit rows, grand
// total) and the export's order columns are drawn only with e-commerce on.
function test_visitor_report_order_columns_need_ecommerce()
{
	$source = visitor_report_presets_source('view_visitor_report.php');
	$lines = explode("\n", $source);
	$blocks = 0;
	$run = array();

	foreach ($lines as $number => $line) {
		$text = trim($line);

		if ((strpos($text, '<td class="align-middle text-end">') === 0) || (strpos($text, '<th class="text-end text-nowrap">') === 0)) {
			$run[] = $number;
			continue;
		}

		if (count($run) > 0) {
			$size = count($run);

			// The figures (5 cells, 3 on a visit row) open the order block;
			// its 7 cells close it.
			if ($size === 7) {
				$blocks++;
				pg_assert_same("' : '') . '", $text, 'order block closes at line ' . ($number + 1));
			} else {
				pg_assert_true(($size === 5) || ($size === 3), 'figure cells left outside the order block at line ' . ($run[0] + 1) . ': ' . $size);
				pg_assert_same("' . (\$output_order_cells ? '", $text, 'order block follows the figures at line ' . ($run[0] + 1));
			}

			$run = array();
		}
	}

	pg_assert_true($blocks >= 9, 'heading, group, visit and total rows found');
	pg_assert_contains('$output_order_cells = (ECOMMERCE == true);', $source, 'one switch for the order cells');
	pg_assert_contains('pg_visitor_report_export_columns(', $source, 'export columns follow the switch');
}

function test_visitor_report_export_columns()
{
	$row = array('id' => 1, 'date' => 'd', 'page_views' => 2, 'custom_form_submitted' => 1, 'order_created' => 1, 'order_retrieved' => 0, 'order_checked_out' => 0, 'order_completed' => 1, 'order_total' => '12.00');

	pg_assert_same(array_values($row), pg_visitor_report_export_columns($row, true), 'with e-commerce');
	pg_assert_same(array(1, 'd', 2, 1), pg_visitor_report_export_columns($row, false), 'without e-commerce');
}

function test_visitor_report_lists_lead_to_the_overview()
{
	$list = visitor_report_presets_source('view_visitor_reports.php');
	pg_assert_contains("lang('Saved visitor reports')", $list, 'list heading');
	pg_assert_contains("'url' => 'visitors.php'", $list, 'list links the overview');

	$visit = visitor_report_presets_source('view_visitor.php');
	pg_assert_contains("'/visitors.php'", $visit, 'visit breadcrumb starts at the overview');
	pg_assert_contains("lang('Saved visitor reports')", $visit, 'visit breadcrumb names the saved reports');

	$report = visitor_report_presets_source('view_visitor_report.php');
	pg_assert_contains("'/visitors.php'", $report, 'report breadcrumb starts at the overview');
	pg_assert_contains("h(lang('Filters'))", $report, 'filters heading in the settings drawer');
}
