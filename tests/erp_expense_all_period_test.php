<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "all dates" period of the expenses list
 * (erp_expense_all_period(), includes/erp/expenses.php): the dashboard's
 * "Expenses to pay" card counts unpaid expenses of any date, so the list it
 * opens must reach the earliest of them.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/expenses.php');

function test_erp_expense_all_period_reaches_the_first_expense()
{
	$period = erp_expense_all_period('1999-06-01', '2026-03-10', '2026-11-04');
	pg_assert_same('1999-06-01', $period['from'], 'an expense before 2000 is inside');
	pg_assert_same('2026-11-04', $period['to'], 'up to today');
	pg_assert_same('all', $period['range']);
	pg_assert_same('', $period['error'], 'never capped at two years');
}

function test_erp_expense_all_period_edges()
{
	pg_assert_same('2026-12-01', erp_expense_all_period('2020-01-01', '2026-12-01', '2026-11-04')['to'], 'a later expense stays inside');
	$none = erp_expense_all_period('', '', '2026-11-04');
	pg_assert_same(array('2026-11-04', '2026-11-04'), array($none['from'], $none['to']), 'no expense: today');
	pg_assert_same('2026-11-04', erp_expense_all_period('2026-02-30', '', '2026-11-04')['from'], 'an invalid date is not used');
}
