<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cash forecast (includes/erp/cash_forecast.php): the columns
 * a horizon is cut into, how the expected movements fold into them and the
 * balance they leave, and the dates a repeating invoice or expense comes
 * round on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/expense_recurring.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/cash_forecast.php');

// Past due, then thirty days a column from today.
function test_erp_cash_forecast_buckets()
{
	$buckets = erp_cash_forecast_buckets('2026-10-13', 90);

	pg_assert_same(array('overdue', 'd30', 'd60', 'd90'), array_keys($buckets), 'columns');
	pg_assert_same(array('start' => '', 'end' => '2026-10-12'), $buckets['overdue'], 'past due');
	pg_assert_same(array('start' => '2026-10-13', 'end' => '2026-11-11'), $buckets['d30'], '1-30');
	pg_assert_same(array('start' => '2026-11-12', 'end' => '2026-12-11'), $buckets['d60'], '31-60');
	pg_assert_same(array('start' => '2026-12-12', 'end' => '2027-01-10'), $buckets['d90'], '61-90');

	pg_assert_same(array('overdue', 'd30'), array_keys(erp_cash_forecast_buckets('2026-10-13', 30)), '30 days');
}

// Only the horizons offered are taken; anything else is 90.
function test_erp_cash_forecast_horizon()
{
	pg_assert_same(30, erp_cash_forecast_horizon('30'));
	pg_assert_same(60, erp_cash_forecast_horizon(60));
	pg_assert_same(90, erp_cash_forecast_horizon('45'));
	pg_assert_same(90, erp_cash_forecast_horizon(''));
}

// Movements fall into their columns, and the balance carries over.
function test_erp_cash_forecast_build_folds_and_carries()
{
	$forecast = erp_cash_forecast_build(array(
		array('kind' => 'sales_invoice', 'date' => '2026-10-01', 'amount' => 10000),
		array('kind' => 'purchase_invoice', 'date' => '2026-10-20', 'amount' => 4000),
		array('kind' => 'expense', 'date' => '2026-11-20', 'amount' => 3000),
		array('kind' => 'cheque_received', 'date' => '2027-01-10', 'amount' => 5000),
		array('kind' => 'recurring_expense', 'date' => '2026-10-13', 'amount' => 1000),
	), '2026-10-13', 90, 20000);

	pg_assert_same(array('overdue' => 10000, 'd30' => 0, 'd60' => 0, 'd90' => 5000, 'total' => 15000), $forecast['in'], 'in');
	pg_assert_same(array('overdue' => 0, 'd30' => 5000, 'd60' => 3000, 'd90' => 0, 'total' => 8000), $forecast['out'], 'out');
	pg_assert_same(array('overdue' => 10000, 'd30' => -5000, 'd60' => -3000, 'd90' => 5000, 'total' => 7000), $forecast['net'], 'net');
	pg_assert_same(array('overdue' => 30000, 'd30' => 25000, 'd60' => 22000, 'd90' => 27000, 'total' => 27000), $forecast['position'], 'balance');
	pg_assert_same(4000, $forecast['kinds']['purchase_invoice']['d30'], 'by kind');
	pg_assert_same(1000, $forecast['kinds']['recurring_expense']['total'], 'today is in the first column');
	pg_assert_same(20000, $forecast['cash'], 'cash');
	pg_assert_same(5, $forecast['count'], 'count');
}

// Past the horizon, an unknown kind, or nothing to move: left out.
function test_erp_cash_forecast_build_leaves_out()
{
	$forecast = erp_cash_forecast_build(array(
		array('kind' => 'sales_invoice', 'date' => '2026-11-12', 'amount' => 10000),
		array('kind' => 'salary', 'date' => '2026-10-20', 'amount' => 4000),
		array('kind' => 'expense', 'date' => '2026-10-20', 'amount' => 0),
		array('kind' => 'expense', 'date' => '2026-10-20', 'amount' => -300),
		array('kind' => 'expense', 'date' => '2026-11-11', 'amount' => 700),
	), '2026-10-13', 30, 0);

	pg_assert_same(1, $forecast['count'], 'count');
	pg_assert_same(700, $forecast['out']['total'], 'the last day of the horizon is in');
	pg_assert_same(0, $forecast['in']['total'], 'the day after is out');
	pg_assert_false(isset($forecast['kinds']['salary']), 'no unknown kind');
}

// The list under the table runs by date, and every item knows its column.
function test_erp_cash_forecast_build_sorts_items()
{
	$forecast = erp_cash_forecast_build(array(
		array('kind' => 'expense', 'date' => '2026-11-01', 'amount' => 100),
		array('kind' => 'sales_invoice', 'date' => '2026-09-01', 'amount' => 200),
		array('kind' => 'cheque_given', 'date' => '2026-11-01', 'amount' => 300),
	), '2026-10-13', 30, 0);

	pg_assert_same(array('2026-09-01', '2026-11-01', '2026-11-01'), array_column($forecast['items'], 'date'), 'dates');
	pg_assert_same(array('sales_invoice', 'cheque_given', 'expense'), array_column($forecast['items'], 'kind'), 'same day by kind');
	pg_assert_same(array('overdue', 'd30', 'd30'), array_column($forecast['items'], 'bucket'), 'columns');
	pg_assert_same(array('in', 'out', 'out'), array_column($forecast['items'], 'direction'), 'directions');
}

// A monthly repetition on the 31st keeps to each month's last day.
function test_erp_cash_forecast_repeats_month_end()
{
	pg_assert_same(
		array('2026-10-31', '2026-11-30', '2026-12-31'),
		erp_cash_forecast_repeats('2026-10-31', 1, 31, '0000-00-00', '2026-10-13', '2027-01-10')
	);
}

// A date already past is written on the next run: it is expected today.
function test_erp_cash_forecast_repeats_past_dates_are_today()
{
	pg_assert_same(
		array('2026-10-13', '2026-10-13', '2026-11-05', '2026-12-05'),
		erp_cash_forecast_repeats('2026-09-05', 1, 5, '0000-00-00', '2026-10-13', '2026-12-11')
	);
}

// The end date and the step in months are kept to.
function test_erp_cash_forecast_repeats_end_and_step()
{
	pg_assert_same(
		array('2026-10-15', '2026-11-15'),
		erp_cash_forecast_repeats('2026-10-15', 1, 15, '2026-11-30', '2026-10-13', '2027-01-10'),
		'ends'
	);

	pg_assert_same(
		array('2026-10-20', '2027-01-20'),
		erp_cash_forecast_repeats('2026-10-20', 3, 20, '', '2026-10-13', '2027-01-20'),
		'every three months'
	);

	pg_assert_same(array(), erp_cash_forecast_repeats('', 1, 1, '', '2026-10-13', '2027-01-10'), 'no next date');
	pg_assert_same(array(), erp_cash_forecast_repeats('2027-02-01', 1, 1, '', '2026-10-13', '2027-01-10'), 'after the horizon');
}
