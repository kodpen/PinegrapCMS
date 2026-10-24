<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for repeating expenses (includes/erp/expense_recurring.php): the
 * first date a recurrence is due, started fresh or started again after it
 * was stopped, and which till an active repeat written as paid may move to.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/fx.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/expenses.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/expense_recurring.php');

// Started for the first time, the next one is one period after the expense.
function test_erp_expense_recurring_first_date_fresh()
{
	pg_assert_same('2026-08-15', erp_expense_recurring_first_date('2026-07-15', 1, 15, '', '2026-10-22'), 'monthly');
	pg_assert_same('2026-10-15', erp_expense_recurring_first_date('2026-07-15', 3, 15, '', '2026-10-22'), 'every three months');
	pg_assert_same('2026-02-28', erp_expense_recurring_first_date('2026-01-31', 1, 31, '', '2026-10-22'), 'month end');
	pg_assert_same('2026-08-15', erp_expense_recurring_first_date('2026-07-15', 1, 15, '0000-00-00', '2026-10-22'), 'no date to go on from');
}

// Started again, it goes on from where the stopped one was due and leaves out
// the dates that passed while it was stopped: they were written already or
// were not wanted.
function test_erp_expense_recurring_first_date_goes_on_after_a_stop()
{
	pg_assert_same('2026-11-15', erp_expense_recurring_first_date('2026-07-15', 1, 15, '2026-11-15', '2026-10-22'), 'due date still ahead');
	pg_assert_same('2026-11-15', erp_expense_recurring_first_date('2026-07-15', 1, 15, '2026-08-15', '2026-10-22'), 'months passed while stopped');
	pg_assert_same('2026-10-22', erp_expense_recurring_first_date('2026-07-22', 1, 22, '2026-09-22', '2026-10-22'), 'due today');
	pg_assert_same('2026-11-30', erp_expense_recurring_first_date('2026-01-31', 1, 31, '2026-04-30', '2026-11-01'), 'keeps the month end');
	pg_assert_same('2026-11-15', erp_expense_recurring_first_date('2026-07-15', 3, 15, '2026-08-15', '2026-10-22'), 'every three months from the old date');
	pg_assert_same('2027-02-15', erp_expense_recurring_first_date('2026-07-15', 6, 15, '2026-08-15', '2026-10-22'), 'every six months from the old date');
}

// Only an active repeat written as paid takes money from a till, so only it
// may move to another one; the till goes through the same gate as every
// expense payment, then the method is checked.
function test_erp_expense_recurrence_till_refusal()
{
	$paid = array('status' => 'active', 'pay_mode' => 'paid', 'currency' => 'TRY');
	$till = array('id' => 4, 'currency' => '', 'is_active' => 1);

	pg_assert_same('', erp_expense_recurrence_till_refusal($paid, $till, 'transfer'), 'an active paid repeat moves to an active till');
	pg_assert_same(lang('Only an active repeat can change its till.'),
		erp_expense_recurrence_till_refusal(array('status' => 'stopped') + $paid, $till, 'transfer'), 'a stopped repeat');
	pg_assert_same(lang('Only a repeat written as paid takes money from a till.'),
		erp_expense_recurrence_till_refusal(array('pay_mode' => 'unpaid') + $paid, $till, 'transfer'), 'a repeat written to pay');
	pg_assert_same(lang('That till or bank account is not active.'),
		erp_expense_recurrence_till_refusal($paid, array('is_active' => 0) + $till, 'transfer'), 'an inactive till');
	pg_assert_same(lang('Choose a till or bank account.'), erp_expense_recurrence_till_refusal($paid, null, 'transfer'), 'no till');
	pg_assert_same(lang('Choose a payment method.'), erp_expense_recurrence_till_refusal($paid, $till, 'barter'), 'an unknown method');
	pg_assert_same(lang('Choose a payment method.'), erp_expense_recurrence_till_refusal($paid, $till, ''), 'no method');
}
