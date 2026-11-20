<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The daily stock record (includes/erp/stock_plan.php): which sales count,
 * a bundle as its components, the day by the site's time zone, sparse rows,
 * the 14-day recount that keeps a past day's stock, the 365-day fill run
 * once, and nothing written with the Accounting module off.
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

function erp_stock_plan_test_order($status, $type, $ts, $product_id, $quantity, $components = array())
{
	return array(
		'source'     => 'order',
		'status'     => $status,
		'type'       => $type,
		'ts'         => $ts,
		'product_id' => $product_id,
		'quantity'   => $quantity,
		'components' => $components,
	);
}

function erp_stock_plan_test_invoice($status, $order_id, $date, $product_id, $quantity, $components = array(), $direction = 'sales', $doc_type = 'invoice')
{
	return array(
		'source'     => 'invoice',
		'status'     => $status,
		'direction'  => $direction,
		'doc_type'   => $doc_type,
		'order_id'   => $order_id,
		'date'       => $date,
		'product_id' => $product_id,
		'quantity'   => $quantity,
		'components' => $components,
	);
}

function erp_stock_plan_test_with_zone($zone, $callback)
{
	$was = date_default_timezone_get();
	date_default_timezone_set($zone);

	try {
		return $callback();
	} finally {
		date_default_timezone_set($was);
	}
}

function test_erp_stock_plan_counts_each_source()
{
	erp_stock_plan_test_with_zone('UTC', function () {
		$ts = gmmktime(12, 0, 0, 11, 19, 2026);
		$lines = array(
			erp_stock_plan_test_order('complete', 'online', $ts, 1, 2),
			erp_stock_plan_test_order('exported', 'online', $ts, 1, 1),
			erp_stock_plan_test_order('complete', 'marketplace', $ts, 1, 3),
			erp_stock_plan_test_order('complete', 'local', $ts, 1, 4),
			erp_stock_plan_test_invoice('issued', 0, '2026-11-19', 1, '5.0000'),
			erp_stock_plan_test_invoice('paid', 0, '2026-11-19', 1, '1.0000'),
			erp_stock_plan_test_invoice('partially_paid', 0, '2026-11-19', 1, '1.0000'),
		);
		$units = erp_stock_plan_count_units($lines, array(1 => true), array());
		pg_assert_same(array('2026-11-19' => array(1 => 17)), $units, 'web, marketplace, counter and ERP invoice all counted');
	});
}

function test_erp_stock_plan_skips_carts_and_cancelled()
{
	erp_stock_plan_test_with_zone('UTC', function () {
		$ts = gmmktime(12, 0, 0, 11, 19, 2026);
		$lines = array(
			erp_stock_plan_test_order('incomplete', 'online', $ts, 1, 2),
			erp_stock_plan_test_order('cancelled', 'marketplace', $ts, 1, 3),
			erp_stock_plan_test_invoice('cancelled', 0, '2026-11-19', 1, '4'),
			erp_stock_plan_test_invoice('draft', 0, '2026-11-19', 1, '4'),
			erp_stock_plan_test_invoice('issued', 0, '2026-11-19', 1, '4', array(), 'sales', 'return'),
			erp_stock_plan_test_invoice('issued', 0, '2026-11-19', 1, '4', array(), 'sales', 'proforma'),
			erp_stock_plan_test_invoice('issued', 0, '2026-11-19', 1, '4', array(), 'purchase'),
			erp_stock_plan_test_order('complete', 'online', $ts, 1, 1),
		);
		$units = erp_stock_plan_count_units($lines, array(1 => true), array());
		pg_assert_same(array('2026-11-19' => array(1 => 1)), $units, 'only the complete order counts; a return is not taken off');
	});
}

function test_erp_stock_plan_order_invoice_not_twice()
{
	erp_stock_plan_test_with_zone('UTC', function () {
		$ts = gmmktime(12, 0, 0, 11, 19, 2026);
		$lines = array(
			erp_stock_plan_test_order('complete', 'online', $ts, 1, 2),
			erp_stock_plan_test_invoice('issued', 55, '2026-11-19', 1, '2'),
		);
		$units = erp_stock_plan_count_units($lines, array(1 => true), array());
		pg_assert_same(array('2026-11-19' => array(1 => 2)), $units, 'the invoice raised from the order is not counted again');
	});
}

function test_erp_stock_plan_bundle_as_components()
{
	erp_stock_plan_test_with_zone('UTC', function () {
		$ts = gmmktime(12, 0, 0, 11, 19, 2026);
		$tracked = array(2 => true, 3 => true);
		$bundles = array(9 => array(2 => 1, 3 => 2));
		$lines = array(
			// Snapshot taken at the sale: one of 2 and four of 3 per bundle.
			erp_stock_plan_test_order('complete', 'online', $ts, 9, 2, array(2 => 1, 3 => 4)),
			// No snapshot: the bundle's contents today.
			erp_stock_plan_test_order('complete', 'local', $ts, 9, 1),
			erp_stock_plan_test_invoice('issued', 0, '2026-11-19', 9, '1', array(2 => 3)),
		);
		$units = erp_stock_plan_count_units($lines, $tracked, $bundles);
		pg_assert_same(array('2026-11-19' => array(2 => 6, 3 => 10)), $units, 'units go to the components, the bundle itself is not recorded');

		$untracked = erp_stock_plan_count_units(array(erp_stock_plan_test_order('complete', 'online', $ts, 9, 1, array(2 => 1, 4 => 1))), $tracked, $bundles);
		pg_assert_same(array('2026-11-19' => array(2 => 1)), $untracked, 'an untracked component is left out');
	});
}

function test_erp_stock_plan_day_by_site_zone()
{
	$late = gmmktime(22, 30, 0, 11, 19, 2026);

	$istanbul = erp_stock_plan_test_with_zone('Europe/Istanbul', function () use ($late) {
		return erp_stock_plan_count_units(array(erp_stock_plan_test_order('complete', 'online', $late, 1, 1)), array(1 => true), array());
	});
	pg_assert_same(array('2026-11-20' => array(1 => 1)), $istanbul, '22:30 UTC is the next day in Istanbul');

	$utc = erp_stock_plan_test_with_zone('UTC', function () use ($late) {
		return erp_stock_plan_count_units(array(erp_stock_plan_test_order('complete', 'online', $late, 1, 1)), array(1 => true), array());
	});
	pg_assert_same(array('2026-11-19' => array(1 => 1)), $utc, 'the same moment is still the 19th in UTC');

	$bounds = erp_stock_plan_test_with_zone('Europe/Istanbul', function () {
		return erp_stock_plan_day_bounds('2026-11-20');
	});
	pg_assert_same(array(gmmktime(21, 0, 0, 11, 19, 2026), gmmktime(21, 0, 0, 11, 20, 2026)), $bounds, 'a day starts at the site\'s midnight');

	$dst = erp_stock_plan_test_with_zone('Europe/Berlin', function () {
		return erp_stock_plan_day_bounds('2026-10-25');
	});
	pg_assert_same(25 * 3600, $dst[1] - $dst[0], 'the day the clocks go back has 25 hours');
}

function test_erp_stock_plan_rows_are_sparse()
{
	$rows = erp_stock_plan_rows(array(1 => 3, 2 => 0), array(1 => 10, 2 => 5, 3 => 0, 4 => -2, 5 => 7));
	pg_assert_same(array(
		array('product_id' => 1, 'sold' => 3, 'stock' => 10),
		array('product_id' => 3, 'sold' => 0, 'stock' => 0),
		array('product_id' => 4, 'sold' => 0, 'stock' => 0),
	), $rows, 'a row only when it sold or its stock was 0; a negative count is 0');

	$unknown = erp_stock_plan_rows(array(1 => 2, 2 => 0), null);
	pg_assert_same(array(array('product_id' => 1, 'sold' => 2, 'stock' => null)), $unknown, 'without stock (the fill) only sales, stock unknown');
}

function test_erp_stock_plan_recount_keeps_stock()
{
	$existing = array(
		1 => array('sold' => 3, 'stock' => 4),
		2 => array('sold' => 2, 'stock' => 0),
		3 => array('sold' => 1, 'stock' => 6),
		4 => array('sold' => 5, 'stock' => null),
		5 => array('sold' => 1, 'stock' => 2),
	);
	// 1 got a late marketplace order, 2 and 3 were cancelled, 4 unchanged, 5 cancelled, 6 new.
	$plan = erp_stock_plan_recount($existing, array(1 => 4, 4 => 5, 6 => 2));
	pg_assert_same(array(1 => 4, 2 => 0, 6 => 2), $plan['set'], 'only the sold units change');
	pg_assert_same(array(3, 5), $plan['delete'], 'a row with no sale and stock above 0 goes');
	pg_assert_false(isset($plan['stock']), 'the recount names no stock');
	pg_assert_contains('stock = stock', erp_stock_plan_upsert_sql('2026-11-10', array(array('product_id' => 1, 'sold' => 4, 'stock' => null)), false), 'the recount writes leave stock as it was');
	pg_assert_contains('stock = IF(stock IS NULL, VALUES(stock), stock)', erp_stock_plan_upsert_sql('2026-11-19', array(array('product_id' => 1, 'sold' => 4, 'stock' => 3)), true), 'the night\'s row fills only an unknown stock');
}

function test_erp_stock_plan_recount_window()
{
	pg_assert_same(array('2026-11-06', '2026-11-19'), erp_stock_plan_recount_range('2026-11-20'), 'the 14 days before today');
}

function test_erp_stock_plan_fill_once()
{
	pg_assert_same(array('from' => '2025-11-18', 'to' => '2026-11-17'), erp_stock_plan_fill_range('2026-11-18'), 'the 365 days before the record began');

	$slice = erp_stock_plan_fill_slice('2026-11-18', '', 30);
	pg_assert_same(array('from' => '2026-10-19', 'to' => '2026-11-17'), $slice, 'the first slice ends the day before the record');

	$next = erp_stock_plan_fill_slice('2026-11-18', $slice['from'], 30);
	pg_assert_same(array('from' => '2026-09-19', 'to' => '2026-10-18'), $next, 'the next slice carries on backwards');

	$last = erp_stock_plan_fill_slice('2026-11-18', '2025-12-01', 30);
	pg_assert_same(array('from' => '2025-11-18', 'to' => '2025-11-30'), $last, 'the last slice stops at 365 days');

	pg_assert_same(null, erp_stock_plan_fill_slice('2026-11-18', '2025-11-18', 30), 'nothing left once the fill reached its start');

	pg_assert_true(erp_stock_plan_fill_due(array('began' => '2026-11-18', 'filled' => false)), 'due until done');
	pg_assert_false(erp_stock_plan_fill_due(array('began' => '2026-11-18', 'filled' => true)), 'never again once done');
	pg_assert_false(erp_stock_plan_fill_due(array('began' => '', 'filled' => false)), 'not before the record began');
}

function test_erp_stock_plan_day_marks()
{
	pg_assert_same(20261120, erp_stock_plan_day_mark('2026-11-20'), 'a day kept as a number');
	pg_assert_same('2026-11-20', erp_stock_plan_mark_day(20261120), 'and read back');
	pg_assert_same('', erp_stock_plan_mark_day(0), 'no mark is no day');
	pg_assert_same('2026-11-19', erp_stock_plan_shift_day('2026-11-20', -1), 'the day before');
	pg_assert_same('2025-10-16', erp_stock_plan_shift_day('2026-11-20', -400), 'the pruning edge');
}

function test_erp_stock_plan_queries()
{
	$orders = erp_stock_plan_orders_sql(1000, 2000);
	pg_assert_contains("o.status IN ('complete', 'exported')", $orders, 'only complete and exported orders');
	pg_assert_false(strpos($orders, 'o.type') !== false, 'every order type');
	pg_assert_contains('o.order_date >= 1000', $orders, 'from the day\'s start');
	pg_assert_contains('o.order_date < 2000', $orders, 'to the next day\'s start');

	$invoices = erp_stock_plan_invoices_sql('2026-11-01', '2026-11-19');
	pg_assert_contains("i.direction = 'sales'", $invoices, 'sales invoices');
	pg_assert_contains("i.doc_type = 'invoice'", $invoices, 'not returns or proformas');
	pg_assert_contains('i.order_id = 0', $invoices, 'an order\'s invoice is the order');
	pg_assert_contains("i.status IN ('issued', 'partially_paid', 'paid')", $invoices, 'no drafts or cancelled');
}

function test_erp_stock_plan_nothing_with_accounting_off()
{
	$result = erp_stock_plan_run(false);
	pg_assert_false($result['ran'], 'nothing runs with the Accounting module off');
	pg_assert_same('off', $result['reason'], 'and says why');
}

function test_erp_stock_plan_job_catalogue()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/cron.php');
	pg_assert_contains("'erp_stock_plan_job' => array(", $source, 'the job is in the catalogue');
	pg_assert_contains("'script'      => 'erp_stock_plan_job.php',", $source, 'with its script');

	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_stock_plan_job.php');
	pg_assert_contains("pg_cron_ran('erp_stock_plan_job')", $job, 'a finished run is recorded');
	pg_assert_contains("pg_cron_due_again('erp_stock_plan_job'", $job, 'an unfinished run comes back');
}
