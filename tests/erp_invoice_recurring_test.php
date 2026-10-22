<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for repeating invoices (includes/erp/invoice_recurring.php): the day
 * of the month a recurrence keeps.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_recurring.php');

// An invoice of the 31st repeats on the last day of every month, not on the
// day the first, shorter month cut it to.
function test_erp_invoice_recurring_day_keeps_the_month_end()
{
	pg_assert_same(31, erp_invoice_recurring_day('2026-01-31', '2026-02-28'), '31 January, first on 28 February');
	pg_assert_same(31, erp_invoice_recurring_day('2026-08-31', '2026-09-30'), '31 August, first on 30 September');
	pg_assert_same(30, erp_invoice_recurring_day('2026-01-30', '2026-02-28'), '30 January keeps the 30th');
	pg_assert_same(29, erp_invoice_recurring_day('2027-01-29', '2027-02-28'), '29 January in a short February');

	$day = erp_invoice_recurring_day('2026-01-31', '2026-02-28');
	$dates = array();
	$date = '2026-02-28';
	for ($i = 0; $i < 4; $i++) {
		$date = erp_expense_recurring_step($date, 1, $day);
		$dates[] = $date;
	}
	pg_assert_same(array('2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30'), $dates, 'the run after the first');
}

// A first date the operator moved to another day keeps that day.
function test_erp_invoice_recurring_day_follows_a_chosen_day()
{
	pg_assert_same(15, erp_invoice_recurring_day('2026-01-31', '2026-03-15'), 'moved into the month');
	pg_assert_same(28, erp_invoice_recurring_day('2026-01-15', '2026-02-28'), 'moved to the month end from an earlier day');
	pg_assert_same(10, erp_invoice_recurring_day('2026-01-10', '2026-02-10'), 'the same day');
	pg_assert_same(31, erp_invoice_recurring_day('2026-01-31', '2026-03-31'), 'a month that has the day');
	pg_assert_same(31, erp_invoice_recurring_day('2026-03-31', '2026-06-30'), 'every three months from the 31st');
}
