<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_shipping_report_period(): the period the shipping report
 * shows from the address's dates and the one kept in the session. A pair
 * that ends before it starts, or holds an impossible date, is not taken, and
 * a long period is cut to the report's longest.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A valid pair from the address is taken.
function test_shipping_report_period_taken()
{
	pg_assert_same(array('2026-11-10', '2026-11-20'), pg_shipping_report_period('2026-11-10', '2026-11-20', '2026-01-01', '2026-01-14', '2026-11-02'));
	pg_assert_same(array('2026-11-10', '2026-11-10'), pg_shipping_report_period('2026-11-10', '2026-11-10', null, null, '2026-11-02'));
}

// Only one date given: the other comes from the session.
function test_shipping_report_period_one_date()
{
	pg_assert_same(array('2026-11-05', '2026-11-14'), pg_shipping_report_period('2026-11-05', null, '2026-11-01', '2026-11-14', '2026-11-02'));
	pg_assert_same(array('2026-11-01', '2026-11-20'), pg_shipping_report_period(null, '2026-11-20', '2026-11-01', '2026-11-14', '2026-11-02'));
}

// An end before the start, or an impossible date, leaves the session's
// period as it was.
function test_shipping_report_period_refused()
{
	pg_assert_same(array('2026-11-01', '2026-11-14'), pg_shipping_report_period('2026-11-20', '2026-11-10', '2026-11-01', '2026-11-14', '2026-11-02'));
	pg_assert_same(array('2026-11-01', '2026-11-14'), pg_shipping_report_period('2026-02-30', '2026-03-05', '2026-11-01', '2026-11-14', '2026-11-02'));
	pg_assert_same(array('2026-11-01', '2026-11-14'), pg_shipping_report_period('x', '2026-03-05', '2026-11-01', '2026-11-14', '2026-11-02'));
	// Taken alone, the end would fall before the session's start.
	pg_assert_same(array('2026-11-01', '2026-11-14'), pg_shipping_report_period(null, '2026-10-01', '2026-11-01', '2026-11-14', '2026-11-02'));
}

// A broken period kept in the session (or none) gives the default two weeks
// from today.
function test_shipping_report_period_default()
{
	pg_assert_same(array('2026-11-02', '2026-11-15'), pg_shipping_report_period(null, null, null, null, '2026-11-02'));
	pg_assert_same(array('2026-11-02', '2026-11-15'), pg_shipping_report_period(null, null, '2026-11-20', '2026-11-10', '2026-11-02'));
	pg_assert_same(array('2026-11-02', '2026-11-15'), pg_shipping_report_period('2026-11-20', '2026-11-10', '2026-11-20', '2026-03-05', '2026-11-02'));
}

// A period longer than the report draws is cut at its longest.
function test_shipping_report_period_longest()
{
	$period = pg_shipping_report_period('2026-01-01', '2026-12-31', null, null, '2026-11-02');

	pg_assert_same('2026-01-01', $period[0]);
	pg_assert_same(date('Y-m-d', strtotime('2026-01-01 +' . (PG_SHIPPING_REPORT_MAX_DAYS - 1) . ' day')), $period[1]);
}

// Cancelled orders do not ship: the report counts the orders its heading
// links open (complete or exported).
function test_shipping_report_counts_complete_or_exported()
{
	$src = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/view_shipping_report.php');

	pg_assert_contains("orders.status IN ('complete', 'exported')", $src);
	pg_assert_true(strpos($src, "orders.status != 'incomplete'") === false);
}
