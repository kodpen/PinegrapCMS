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

// A cheque handed to a bank that was taken off later is collected there: the
// screen lists that bank, so the collect form does not post the first active
// till in its place.
function test_erp_cheque_bank_till_choices_keep_the_stored_bank()
{
	$where = erp_cheque_bank_till_choices_where(array('bank_till_id' => 7, 'portfolio_till_id' => 2));

	pg_assert_contains("(is_active = 1 OR id = '7')", $where, 'the stored bank is listed even when inactive');
	pg_assert_contains("id <> '2'", $where, 'the portfolio is not listed');
	pg_assert_contains("is_active = 1", erp_cheque_bank_till_choices_where(array('bank_till_id' => 0, 'portfolio_till_id' => 2)), 'without a stored bank only active tills are listed');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_cheque.php');
	pg_assert_contains('erp_cheque_bank_till_choices_where(', $screen, 'the cheque screen lists the tills by it');
}

// The API asks the screen's bank check: it refused a stored bank taken off
// later, which the screen still collects at, and let a deposit into a till of
// another currency through its dry run.
function test_erp_api_cheque_step_asks_the_bank_till_refusal()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/api_cash.php');
	$step = substr($src, (int) strpos($src, 'function erp_api_cheques_step('));
	$step = substr($step, 0, (int) strpos($step, 'api_dry_run_stop('));

	pg_assert_contains('erp_cheque_bank_till_id_refusal(', $step, 'the API step checks the bank till as the screen does');
	pg_assert_false(strpos($step, 'erp_api_cash_till(') !== false, 'the API step does not refuse every inactive till');
}

// A date the screen cannot read is refused, not taken as today: the money
// moved on a day nobody chose.
function test_erp_cheque_typed_step_date()
{
	pg_assert_same(date('Y-m-d'), erp_cheque_typed_step_date(''), 'an empty date is today');
	pg_assert_same('2026-03-05', erp_cheque_typed_step_date('2026-03-05'), 'an ISO date is kept');
	pg_assert_true(erp_cheque_step_date_refusal(erp_cheque_typed_step_date('31.31.2026'), '2026-01-01') !== '', 'an unreadable date is refused by the step');
	pg_assert_true(erp_cheque_step_date_refusal(erp_cheque_typed_step_date('soon'), '2026-01-01') !== '', 'text is refused by the step');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_cheque.php');
	pg_assert_contains('erp_cheque_typed_step_date(', $screen, 'the cheque screen reads the date by it');
}

// The API asks the step's date check before its dry run, so a rehearsal of a
// step dated before the cheque is refused on the date field, as the step is.
function test_erp_api_cheque_step_asks_the_date_refusal()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/api_cash.php');
	$step = substr($src, (int) strpos($src, 'function erp_api_cheques_step('));
	$step = substr($step, 0, (int) strpos($step, 'api_dry_run_stop('));

	pg_assert_contains('erp_cheque_step_date_refusal(', $step, 'the API step checks the date before the dry run');
}
