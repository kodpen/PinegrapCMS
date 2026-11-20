<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The stock plan's forecast (includes/erp/stock_plan.php): out-of-stock days
 * left out of the speed, the season capped and left out on thin sales, the
 * lead time's median, the supplier from the invoice and then the order, open
 * orders taken off the quantity, dead stock and not enough history.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/stock_plan.php');

// Rows for the days from $from on, each with $sold and $stock.
function erp_stock_plan_forecast_test_days($from, $count, $sold, $stock = null)
{
	$days = array();

	for ($i = 0; $i < $count; $i++) {
		$days[erp_stock_plan_shift_day($from, $i)] = array('sold' => $sold, 'stock' => $stock);
	}

	return $days;
}

function erp_stock_plan_forecast_test_settings()
{
	return array('safety' => 7, 'cover' => 30, 'dead' => 90);
}

function test_erp_stock_plan_speed_skips_out_of_stock_days()
{
	$today = '2026-11-20';
	$from = erp_stock_plan_shift_day($today, -56);

	// 28 days selling 2 a day, then 28 days out of stock (stock 0) with a
	// unit sold on the day each ran out.
	$days = erp_stock_plan_forecast_test_days($from, 28, 2, 5)
		+ erp_stock_plan_forecast_test_days(erp_stock_plan_shift_day($from, 28), 28, 1, 0);

	pg_assert_same(2.0, erp_stock_plan_speed($days, $today, '2025-01-01'), 'out-of-stock days and their units are left out');

	// A day with no row was in stock and sold nothing.
	$sparse = erp_stock_plan_forecast_test_days($from, 28, 2, 5);
	pg_assert_same(1.0, erp_stock_plan_speed($sparse, $today, '2025-01-01'), 'days without a row count as in stock with no sale');

	// Stock unknown (the filled days) counts as in stock.
	$filled = erp_stock_plan_forecast_test_days($from, 56, 3, null);
	pg_assert_same(3.0, erp_stock_plan_speed($filled, $today, '2025-01-01'), 'unknown stock counts as in stock');

	// Days before the record holds anything are not counted.
	$young = erp_stock_plan_forecast_test_days(erp_stock_plan_shift_day($today, -20), 20, 4, null);
	pg_assert_same(4.0, erp_stock_plan_speed($young, $today, erp_stock_plan_shift_day($today, -20)), 'only the days the record holds');
}

function test_erp_stock_plan_not_enough_history()
{
	$today = '2026-11-20';
	$from = erp_stock_plan_shift_day($today, -56);

	// 13 in-stock days only.
	$days = erp_stock_plan_forecast_test_days($from, 43, 0, 0) + erp_stock_plan_forecast_test_days(erp_stock_plan_shift_day($from, 43), 13, 5, 3);
	pg_assert_same(null, erp_stock_plan_speed($days, $today, '2025-01-01'), 'under 14 in-stock days');

	pg_assert_same(null, erp_stock_plan_speed(array(), $today, '2025-01-01'), 'no sale in the window');
	pg_assert_same(null, erp_stock_plan_speed(array(), $today, erp_stock_plan_shift_day($today, -10)), 'a record of 10 days');

	$plan = erp_stock_plan_forecast(array('speed' => null, 'season' => 1.0, 'stock' => 4, 'open' => 0, 'lead' => 10, 'today' => $today), erp_stock_plan_forecast_test_settings());
	pg_assert_same('no_history', $plan['state'], 'Not enough history');
	pg_assert_same(0, $plan['quantity'], 'nothing to order: the minimum stock rules');
	pg_assert_same(null, $plan['runout_day'], 'no run-out day');
}

function test_erp_stock_plan_season()
{
	$today = '2026-11-20';
	$year_ago = erp_stock_plan_shift_day($today, -364);
	$before = erp_stock_plan_shift_day($year_ago, -56);
	$history = erp_stock_plan_shift_day($today, -420);

	$days = erp_stock_plan_forecast_test_days($before, 56, 1) + erp_stock_plan_forecast_test_days($year_ago, 56, 3);
	pg_assert_same(2.0, erp_stock_plan_season($days, $today, $history), 'capped at 2');

	$days = erp_stock_plan_forecast_test_days($before, 56, 4) + erp_stock_plan_forecast_test_days($year_ago, 56, 1);
	pg_assert_same(0.5, erp_stock_plan_season($days, $today, $history), 'capped at 0.5');

	$days = erp_stock_plan_forecast_test_days($before, 20, 1) + erp_stock_plan_forecast_test_days($year_ago, 28, 1);
	pg_assert_same(1.4, erp_stock_plan_season($days, $today, $history), 'ahead over before');

	$days = erp_stock_plan_forecast_test_days($before, 9, 1) + erp_stock_plan_forecast_test_days($year_ago, 56, 3);
	pg_assert_same(1.0, erp_stock_plan_season($days, $today, $history), 'left out when a window sold under 10');

	$days = erp_stock_plan_forecast_test_days($before, 56, 1) + erp_stock_plan_forecast_test_days($year_ago, 56, 3);
	pg_assert_same(1.0, erp_stock_plan_season($days, $today, erp_stock_plan_shift_day($today, -400)), 'left out when the record does not reach back');
}

function test_erp_stock_plan_lead_time_median()
{
	$orders = array(
		array('id' => 1, 'account_id' => 7, 'issue_date' => '2026-01-01', 'invoice_date' => '2026-03-01'),
		array('id' => 2, 'account_id' => 7, 'issue_date' => '2026-05-01', 'invoice_date' => '2026-05-11'),
		array('id' => 3, 'account_id' => 7, 'issue_date' => '2026-06-01', 'invoice_date' => '2026-06-05'),
		array('id' => 4, 'account_id' => 7, 'issue_date' => '2026-07-01', 'invoice_date' => '2026-07-21'),
		array('id' => 5, 'account_id' => 7, 'issue_date' => '2026-08-01', 'invoice_date' => '2026-08-08'),
		array('id' => 6, 'account_id' => 7, 'issue_date' => '2026-09-01', 'invoice_date' => '2026-09-13'),
		array('id' => 7, 'account_id' => 8, 'issue_date' => '2026-09-01', 'invoice_date' => '2026-09-03'),
		array('id' => 8, 'account_id' => 8, 'issue_date' => '2026-09-10', 'invoice_date' => '2026-09-15'),
	);

	// Supplier 7's last five: 12, 7, 20, 4, 10 -> median 10 (the 59 days of
	// the oldest order are left out). Supplier 8: 2 and 5 -> 4 (3.5 rounded).
	pg_assert_same(array(7 => 10, 8 => 4), erp_stock_plan_lead_measured($orders), 'median of the last five');

	pg_assert_same(array('days' => 10, 'source' => 'measured'), erp_stock_plan_lead(10, 30), 'measured wins over typed');
	pg_assert_same(array('days' => 30, 'source' => 'typed'), erp_stock_plan_lead(null, 30), 'else the typed lead time');
	pg_assert_same(array('days' => null, 'source' => 'none'), erp_stock_plan_lead(null, 0), 'else unknown');

	$plan = erp_stock_plan_forecast(array('speed' => 1.0, 'season' => 1.0, 'stock' => 100, 'open' => 0, 'lead' => null, 'today' => '2026-11-20'), erp_stock_plan_forecast_test_settings());
	pg_assert_same(null, $plan['order_by_day'], 'no order-by day while the lead time is unknown');
}

function test_erp_stock_plan_supplier_from_invoice_then_order()
{
	$invoices = array(
		array('product_id' => 1, 'account_id' => 10, 'issue_date' => '2026-03-01', 'id' => 5),
		array('product_id' => 1, 'account_id' => 11, 'issue_date' => '2026-04-01', 'id' => 6),
	);
	$orders = array(
		array('id' => 40, 'account_id' => 20, 'issue_date' => '2026-10-01', 'lines' => array(array('product_id' => 1), array('product_id' => 2))),
		array('id' => 41, 'account_id' => 21, 'issue_date' => '2026-09-01', 'lines' => array(array('product_id' => 2))),
	);

	pg_assert_same(array(
		1 => array('account_id' => 11, 'source' => 'invoice'),
		2 => array('account_id' => 20, 'source' => 'order'),
	), erp_stock_plan_suppliers($invoices, $orders), 'the latest invoice, else the latest order');

	pg_assert_same(array(), erp_stock_plan_suppliers(array(), array()), 'No supplier yet');
}

function test_erp_stock_plan_open_orders_taken_off()
{
	$open = erp_stock_plan_open_quantities(array(
		array(array('product_id' => 3, 'open' => 10.0), array('product_id' => 4, 'open' => 0.0)),
		array(array('product_id' => 3, 'open' => 5.0)),
	));
	pg_assert_same(array(3 => 15.0), $open, 'open quantities per product');

	$in = array('speed' => 2.0, 'season' => 1.0, 'stock' => 20, 'open' => 0, 'lead' => 10, 'today' => '2026-11-20');
	$settings = erp_stock_plan_forecast_test_settings();

	// 2 a day for 10 + 7 + 30 days = 94, less 20 in stock.
	pg_assert_same(74, erp_stock_plan_forecast($in, $settings)['quantity'], 'quantity before open orders');
	pg_assert_same(59, erp_stock_plan_forecast(array('open' => 15) + $in, $settings)['quantity'], 'open orders taken off');
	pg_assert_same(0, erp_stock_plan_forecast(array('open' => 500) + $in, $settings)['quantity'], 'never below 0');
	pg_assert_same(28, erp_stock_plan_forecast(array('speed' => 1.01) + $in, $settings)['quantity'], 'rounded up');
}

function test_erp_stock_plan_days_left_and_states()
{
	$settings = erp_stock_plan_forecast_test_settings();
	$base = array('speed' => 2.0, 'season' => 1.0, 'open' => 0, 'lead' => 10, 'today' => '2026-11-20');

	$plan = erp_stock_plan_forecast(array('stock' => 100) + $base, $settings);
	pg_assert_same(50, $plan['days_left'], 'stock over units a day');
	pg_assert_same('2027-01-09', $plan['runout_day'], 'today plus days left');
	pg_assert_same('2026-12-23', $plan['order_by_day'], 'less lead time and safety days');
	pg_assert_same('fine', $plan['state'], 'Fine');

	pg_assert_same(25, erp_stock_plan_forecast(array('stock' => 100, 'season' => 2.0) + $base, $settings)['days_left'], 'the season speeds it up');
	pg_assert_same('soon', erp_stock_plan_forecast(array('stock' => 60) + $base, $settings)['state'], 'order-by day within 14 days');
	pg_assert_same('order_now', erp_stock_plan_forecast(array('stock' => 30) + $base, $settings)['state'], 'order-by day today or past');
	pg_assert_same('runs_out', erp_stock_plan_forecast(array('stock' => 18) + $base, $settings)['state'], 'days left under lead time');
	pg_assert_same(0, erp_stock_plan_forecast(array('stock' => -4) + $base, $settings)['days_left'], 'no stock, no days left');
}

function test_erp_stock_plan_dead_stock()
{
	$today = '2026-11-20';

	pg_assert_same(array('dead' => true, 'value' => 1250), erp_stock_plan_dead(5, '2026-08-01', '2025-09-24', $today, 90, 250), 'no sale for 90 days: units times average cost');
	pg_assert_same(array('dead' => true, 'value' => null), erp_stock_plan_dead(5, '2026-08-01', '2025-09-24', $today, 90, 0), 'no cost known');
	pg_assert_same(array('dead' => false, 'value' => null), erp_stock_plan_dead(5, '2026-10-01', '2025-09-24', $today, 90, 250), 'sold within the dead-stock days');
	pg_assert_same(array('dead' => false, 'value' => null), erp_stock_plan_dead(0, '2026-01-01', '2025-09-24', $today, 90, 250), 'not in stock');
	pg_assert_same(array('dead' => true, 'value' => 30), erp_stock_plan_dead(3, null, '2025-09-24', $today, 90, 10), 'never sold since the record holds');
	pg_assert_same(array('dead' => false, 'value' => null), erp_stock_plan_dead(3, null, '2026-11-01', $today, 90, 10), 'a young record says nothing yet');
}

function test_erp_stock_plan_rebuild_due_and_cache_sql()
{
	$marks = array('began' => '2026-11-01', 'fill' => '', 'filled' => true, 'day' => '2026-11-19', 'planned' => '2026-11-18');
	pg_assert_true(erp_stock_plan_rebuild_due($marks, '2026-11-19', true), 'after the night row, once a night');
	pg_assert_false(erp_stock_plan_rebuild_due(array('planned' => '2026-11-19') + $marks, '2026-11-19', true), 'not twice');
	pg_assert_false(erp_stock_plan_rebuild_due(array('filled' => false) + $marks, '2026-11-19', true), 'after the fill');
	pg_assert_false(erp_stock_plan_rebuild_due($marks, '2026-11-19', false), 'not before the upgrade');

	$sql = erp_stock_plan_cache_sql(array(array(
		'product_id' => 4, 'units_day' => 1.23456, 'season' => 1.4, 'supplier_id' => 7, 'lead_days' => null, 'lead_source' => 'none',
		'days_left' => 12, 'runout_day' => '2026-12-02', 'order_by_day' => null, 'quantity' => 30, 'state' => 'soon', 'last_sale' => '2026-11-19',
	)));
	pg_assert_contains("(4, 1.235, 1.40, 7, NULL, 'none', 12, '2026-12-02', NULL, 30, 'soon', '2026-11-19')", $sql, 'a row as written');
	pg_assert_false(strpos($sql, 'notice_at') !== false, 'the notice stamp survives the rebuild');

	pg_assert_same(array('safety' => 7, 'cover' => 30, 'dead' => 90), erp_stock_plan_settings(), 'the default settings');
}
