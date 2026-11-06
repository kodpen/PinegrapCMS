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

// A changed expense hands its new figures to the active repeat of its family:
// what it is, what it costs and its tax; how often, the day and the end stay.
function test_erp_expense_recurrence_changes()
{
	$recurrence = array('id' => 7, 'status' => 'active', 'currency' => 'TRY', 'category_id' => 2, 'supplier' => 'Kira A',
		'supplier_tax_number' => '', 'description' => 'Rent', 'amount' => 100000, 'tax_rate' => '20.000', 'tax_deductible' => 1);
	$expense = array('category_id' => 5, 'supplier' => 'Kira B', 'supplier_tax_number' => '1234567890', 'description' => 'Rent',
		'currency' => 'TRY', 'total_amount' => 150000, 'tax_rate' => '10.000', 'tax_deductible' => 0);

	pg_assert_same(array(
		'category_id' => 5,
		'supplier' => 'Kira B',
		'supplier_tax_number' => '1234567890',
		'tax_deductible' => 0,
		'amount' => 150000,
		'tax_rate' => '10.000',
	), erp_expense_recurrence_changes($expense, $recurrence), 'the corrected supplier, amount, category and tax reach the repeat');

	$same = array('category_id' => 2, 'supplier' => 'Kira A', 'supplier_tax_number' => '', 'description' => 'Rent',
		'currency' => 'TRY', 'total_amount' => 100000, 'tax_rate' => '20.000', 'tax_deductible' => 1);
	pg_assert_same(array(), erp_expense_recurrence_changes($same, $recurrence), 'unchanged values: nothing to write');
	pg_assert_same(array(), erp_expense_recurrence_changes($expense, array('status' => 'stopped') + $recurrence), 'a stopped repeat is left alone');
	pg_assert_same(array(), erp_expense_recurrence_changes($expense, null), 'no repeat');

	// Another currency is not a corrected amount, and a repeat written as
	// paid takes it from a till in its own currency: the figures stay.
	$changes = erp_expense_recurrence_changes(array('currency' => 'EUR') + $expense, $recurrence);
	pg_assert_same(false, array_key_exists('amount', $changes), 'another currency keeps the amount');
	pg_assert_same(false, array_key_exists('tax_rate', $changes), 'another currency keeps the tax rate');
	pg_assert_same('Kira B', $changes['supplier'] ?? null, 'another currency still corrects the supplier');
}

// A repeat's first and end dates are calendar days: a typed 30 February or
// month 13 passed the pattern and was stored as 0000-00-00 or refused by the
// server, so the repeat never ran or the end was silently dropped.
function test_erp_recurring_date_ok()
{
	pg_assert_same(true, erp_recurring_date_ok('2026-01-31'), 'a real day');
	pg_assert_same(true, erp_recurring_date_ok('2028-02-29'), '29 February of a leap year');
	pg_assert_same(false, erp_recurring_date_ok('2027-02-29'), '29 February of a common year');
	pg_assert_same(false, erp_recurring_date_ok('2026-02-30'), '30 February');
	pg_assert_same(false, erp_recurring_date_ok('2026-13-01'), 'month 13');
	pg_assert_same(false, erp_recurring_date_ok('0000-00-00'), 'the empty date');
	pg_assert_same(false, erp_recurring_date_ok('2026-1-5'), 'not Y-m-d');
	pg_assert_same(false, erp_recurring_date_ok(array('2026-01-31')), 'an array');
}
