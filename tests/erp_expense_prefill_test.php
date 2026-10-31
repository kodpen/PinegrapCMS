<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the first-visit defaults of a new ERP expense
 * (add_erp_expense.php): the till of the last expense is preselected only
 * when the save would take it in the currency the form starts in, so a Save
 * made with the defaults is not refused while foreign currency is on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_erp_expense_prefill_till_follows_the_save_gate()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_erp_expense.php');

	// The new-expense branch of the first-visit defaults.
	$start = strpos($source, '// A new receipt:');
	$end = strpos($source, '$category_options = array(');
	pg_assert_true(($start !== false) && ($end !== false) && ($end > $start), 'the new-expense defaults are found');
	$branch = substr($source, (int) $start, (int) $end - (int) $start);

	pg_assert_contains("\$liveform->assign_field_value('currency', \$base);", $branch, 'the form still starts in the base currency');
	pg_assert_contains('FROM erp_cash_accounts WHERE id = ', $branch, 'the last till is read with its currency and state');
	pg_assert_contains('erp_expense_till_row_refusal($last_till, $base) === \'\'', $branch, 'the till is preselected only when the save would take it');
	pg_assert_false(strpos($branch, "(string) (int) \$last['cash_account_id'] : ''") !== false, 'the last till is no longer preselected whatever its currency');
}
