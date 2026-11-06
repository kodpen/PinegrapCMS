<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bank side of a cheque step (includes/erp/cheques.php,
 * erp_cheque_bank_till_refusal()): the screen refuses the till the API
 * refuses, so a deposit, collect or pay cannot reach a missing or inactive
 * till, the portfolio itself, or a till in another currency.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/cheques.php');

function test_erp_cheque_bank_till_refusal()
{
	$cheque = array('bank_till_id' => 0, 'portfolio_till_id' => 2);

	pg_assert_same('', erp_cheque_bank_till_refusal(array('id' => 7, 'is_active' => 1), $cheque, 'TRY', 'TRY'), 'an active bank in the same currency is taken');
	pg_assert_same(lang('That till or bank account could not be found.'), erp_cheque_bank_till_refusal(null, $cheque, 'TRY', 'TRY'), 'a missing till is refused');
	pg_assert_same(lang('That till or bank account is not active.'), erp_cheque_bank_till_refusal(array('id' => 7, 'is_active' => 0), $cheque, 'TRY', 'TRY'), 'an inactive till is refused');
	pg_assert_same(lang('Choose two different accounts.'), erp_cheque_bank_till_refusal(array('id' => 2, 'is_active' => 1), $cheque, 'TRY', 'TRY'), 'the portfolio is not a bank');
	pg_assert_contains('USD', erp_cheque_bank_till_refusal(array('id' => 7, 'is_active' => 1), $cheque, 'TRY', 'USD'), 'another currency is refused, naming it');
}

// A bank taken off after the cheque went to it still collects that cheque.
function test_erp_cheque_bank_till_stored_inactive_bank()
{
	$cheque = array('bank_till_id' => 7, 'portfolio_till_id' => 2);

	pg_assert_same('', erp_cheque_bank_till_refusal(array('id' => 7, 'is_active' => 0), $cheque, 'TRY', 'TRY'), 'the stored bank may be inactive');
	pg_assert_same(lang('That till or bank account is not active.'), erp_cheque_bank_till_refusal(array('id' => 8, 'is_active' => 0), $cheque, 'TRY', 'TRY'), 'another inactive bank is refused');
}

// The step asks it, so the screen and the API share the check.
function test_erp_cheque_step_asks_the_bank_till_refusal()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cheques.php');
	$step = substr($src, (int) strpos($src, 'function erp_cheque_step('));

	pg_assert_contains('erp_cheque_bank_till_refusal(', $step, 'the step checks the bank till');
}
