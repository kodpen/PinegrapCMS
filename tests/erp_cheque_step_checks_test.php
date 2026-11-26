<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checks a cheque or note step makes in includes/erp/cheques.php
 * so the cheque screen and the API refuse alike: the account a cheque is
 * passed on to, the bank a deposited cheque is held at, and the typed date.
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

// Passing a cheque on back to its own customer is a payment to them that
// leaves the invoices it closed reading as paid; an inactive or missing
// account takes no payment.
function test_erp_cheque_endorse_account_refusal()
{
	$cheque = array('account_id' => 5);

	pg_assert_same('', erp_cheque_endorse_account_refusal(array('id' => 9, 'status' => 'active'), $cheque), 'an active supplier is taken');
	pg_assert_same(lang('That account could not be found.'), erp_cheque_endorse_account_refusal(null, $cheque), 'a missing account is refused');
	pg_assert_same(lang('That account is not active.'), erp_cheque_endorse_account_refusal(array('id' => 9, 'status' => 'passive'), $cheque), 'an inactive account is refused');
	pg_assert_same(lang('Pass it on to an account other than the one it came from.'), erp_cheque_endorse_account_refusal(array('id' => 5, 'status' => 'active'), $cheque), 'the cheque\'s own customer is refused');
}

// The step asks it, so a screen POST meets the API's refusal.
function test_erp_cheque_step_asks_the_endorse_account_refusal()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cheques.php');
	$step = substr($src, (int) strpos($src, 'function erp_cheque_step('));

	pg_assert_contains('erp_cheque_endorse_account_refusal(', $step, 'the step checks the account it is passed on to');
}
