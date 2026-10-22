<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the till an expense is paid from (includes/erp/expenses.php):
 * a till that is missing or no longer active takes no payment, so a repeat
 * set to pay from a closed bank account stops instead of paying from it.
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

function test_erp_expense_till_row_refusal()
{
	pg_assert_same('', erp_expense_till_row_refusal(array('id' => 3, 'currency' => '', 'is_active' => 1), 'TRY'), 'an active till pays');
	pg_assert_same(lang('That till or bank account is not active.'), erp_expense_till_row_refusal(array('id' => 3, 'currency' => '', 'is_active' => 0), 'TRY'), 'an inactive till does not');
	pg_assert_same(lang('Choose a till or bank account.'), erp_expense_till_row_refusal(null, 'TRY'), 'no till');
}
