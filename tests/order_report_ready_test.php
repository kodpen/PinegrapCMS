<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ready reports (includes/fn/ready_reports.php): the
 * registry's shape, reading the address parameter, and sales by region
 * counting what the dashboard's Sales map card counts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

// Every entry carries its key, screen, title, levels, filters and period.
function test_ready_reports_registry_shape()
{
	$reports = pg_ready_reports();

	pg_assert_true(count($reports) > 0, 'at least one entry');

	foreach ($reports as $key => $report) {
		pg_assert_same($key, $report['key'], $key . ' key');
		pg_assert_same('order_report', $report['screen'], $key . ' screen');
		pg_assert_true(is_string($report['title']) && ($report['title'] !== ''), $key . ' title');
		pg_assert_true(is_array($report['levels']) && (count($report['levels']) >= 1) && (count($report['levels']) <= 3), $key . ' one to three levels');

		foreach ($report['levels'] as $level) {
			pg_assert_same(array('by', 'order'), array_keys($level), $key . ' level');
			pg_assert_true(in_array($level['order'], array('alphabet', 'number of orders', 'total'), true), $key . ' order by');
		}

		foreach ($report['filters'] as $filter) {
			pg_assert_same(array('field', 'operator', 'value'), array_keys($filter), $key . ' filter');
		}

		pg_assert_true(is_int($report['period']) && ($report['period'] >= 0), $key . ' period');
	}
}

// A known key opens its entry; anything else opens the plain new report.
function test_ready_report_requested_reads_the_parameter()
{
	$report = pg_ready_report_requested('sales_by_region', 'order_report');

	pg_assert_same('sales_by_region', $report['key'] ?? null, 'a known key');
	pg_assert_same(null, pg_ready_report_requested('nope', 'order_report'), 'an unknown key');
	pg_assert_same(null, pg_ready_report_requested('', 'order_report'), 'empty');
	pg_assert_same(null, pg_ready_report_requested(null, 'order_report'), 'not sent');
	pg_assert_same(null, pg_ready_report_requested(array('sales_by_region'), 'order_report'), 'an array (?ready[]=x)');
	pg_assert_same(null, pg_ready_report_requested('Sales_By_Region', 'order_report'), 'keys are exact');
	pg_assert_same(null, pg_ready_report_requested('sales_by_region', 'visitor_report'), 'another screen');
}

// Sales by region: billing country, then billing state, both by total, so
// both of the card's views (its home country's regions, or its countries)
// are in the report; the billing state level exists on the screen already.
function test_ready_report_sales_by_region_levels()
{
	$report = pg_ready_reports()['sales_by_region'];

	pg_assert_same(array('billing_country', 'billing_state'), array_column($report['levels'], 'by'));
	pg_assert_same(array('total', 'total'), array_column($report['levels'], 'order'));

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order_report.php');

	pg_assert_contains("\$summarize_by_options[lang('Billing State')] = 'billing_state';", $screen, 'billing state level');
	pg_assert_contains("\$summarize_by_options[lang('Billing Country')] = 'billing_country';", $screen, 'billing country level');

	// The card reads the same two columns.
	$map = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

	pg_assert_contains("orders.billing_country,\n            orders.billing_state,", $map, 'the card groups by the billing address');
}

// The card sums orders complete or exported; the entry leaves out the
// other two statuses an order can have.
function test_ready_report_sales_by_region_counts_the_cards_orders()
{
	$report = pg_ready_reports()['sales_by_region'];
	$map = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

	pg_assert_contains("WHERE orders.status IN ('complete', 'exported')", $map, 'the card\'s statuses');

	$statuses = array('incomplete', 'complete', 'exported', 'cancelled');
	$kept = array();

	foreach ($statuses as $status) {
		$keep = true;

		foreach ($report['filters'] as $filter) {
			pg_assert_same('order_status', $filter['field'], 'status filters only');
			pg_assert_same('is not equal to', $filter['operator']);

			if ($filter['value'] === $status) {
				$keep = false;
			}
		}

		if ($keep) {
			$kept[] = $status;
		}
	}

	pg_assert_same(array('complete', 'exported'), $kept, 'the orders the card sums');
}

// Last 30 days: the order date from 29 days before today, as the card's
// d30 period starts at today's midnight minus 29 days.
function test_ready_report_sales_by_region_period()
{
	$report = pg_ready_reports()['sales_by_region'];

	pg_assert_same(30, $report['period']);

	$filters = pg_ready_report_filters($report);
	$date = end($filters);

	pg_assert_same(count($report['filters']) + 1, count($filters), 'the period is one more filter');
	pg_assert_same('order_date', $date['field']);
	pg_assert_same('is greater than or equal to', $date['operator']);
	pg_assert_same('', $date['value']);
	pg_assert_same('days ago', $date['dynamic_value']);
	pg_assert_same('29', $date['dynamic_value_attribute']);

	// The report compares the order's day in site time with this date; the
	// card compares the order's time with the start of that same day.
	$card_from = pg_sales_map_periods()['d30']['from'];

	pg_assert_same(date('Y-m-d', $card_from), pg_report_dynamic_value('days ago', $date['dynamic_value_attribute'], 'date'), 'the first day counted');
	pg_assert_same(strtotime(date('Y-m-d', $card_from)), $card_from, 'the card starts at midnight');

	// Late in the evening and early in the morning, and over a clock change.
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');

	foreach (array('2026-03-30 00:10:00', '2026-03-30 23:50:00', '2026-11-11 12:00:00') as $now_text) {
		$now = strtotime($now_text);
		$midnight = strtotime(date('Y-m-d', $now));

		pg_assert_same(date('Y-m-d', strtotime('-29 days', $midnight)), pg_report_dynamic_value('days ago', '29', 'date', $now), $now_text);
	}

	date_default_timezone_set($zone);

	// Statuses come through as plain filters.
	pg_assert_same('incomplete', $filters[0]['value']);
	pg_assert_same('', $filters[0]['dynamic_value']);
}

function test_ready_report_without_a_period_adds_no_date()
{
	$filters = pg_ready_report_filters(array('filters' => array(), 'period' => 0));

	pg_assert_same(array(), $filters);
}

function test_ready_report_url()
{
	pg_assert_same('view_order_report.php?ready=sales_by_region', pg_ready_report_url('sales_by_region'));
}
