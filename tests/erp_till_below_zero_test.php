<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the balance check on money leaving a till (includes/erp/cash.php,
 * erp_till_outflow_row_refusal() and erp_till_outflow_refusal()): a till of
 * kind cash (the drawer, a received-cheque portfolio) never goes below zero
 * on a transfer, a payment or an expense, and one already below zero pays
 * nothing until money comes in; bank, POS and credit card tills may run
 * below zero. The cheque module's own postings skip the check, because the
 * given-cheques till is below zero between the giving and the bank's payment.
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

// The body of one function in a source file, up to the next top-level function.
function erp_till_below_zero_test_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_erp_till_below_zero_cash_till_pays_only_what_it_holds()
{
	$refused = lang('Not enough balance in the till.');

	pg_assert_same($refused, erp_till_outflow_row_refusal(array('kind' => 'cash', 'balance' => 71200), 500000), 'a transfer larger than the drawer is refused');
	pg_assert_same('', erp_till_outflow_row_refusal(array('kind' => 'cash', 'balance' => 71200), 71200), 'the drawer may be emptied to zero');
	pg_assert_same('', erp_till_outflow_row_refusal(array('kind' => 'cash', 'balance' => 71200), 100), 'a smaller amount passes');
	pg_assert_same($refused, erp_till_outflow_row_refusal(array('kind' => 'cash', 'balance' => 0), 100), 'an empty portfolio pays nothing');
	pg_assert_same($refused, erp_till_outflow_row_refusal(array('kind' => 'cash', 'balance' => -428800), 1), 'a till already below zero refuses its next outflow');
	pg_assert_same($refused, erp_till_outflow_row_refusal(array('kind' => 'cash', 'balance' => '71200'), '71201'), 'database strings are compared as numbers');
}

function test_erp_till_below_zero_bank_pos_and_card_tills_stay_free()
{
	foreach (array('bank', 'pos', 'credit_card') as $kind) {
		pg_assert_same('', erp_till_outflow_row_refusal(array('kind' => $kind, 'balance' => 0), 500000), $kind . ' may go below zero');
		pg_assert_same('', erp_till_outflow_row_refusal(array('kind' => $kind, 'balance' => -100), 100), $kind . ' already below zero still pays');
	}

	pg_assert_same('', erp_till_outflow_row_refusal(null, 100), 'a missing till is refused by the till checks, not here');
}

// The till row is read locked, so two outflows at once cannot both pass on
// the same balance.
function test_erp_till_below_zero_reads_the_till_locked()
{
	$body = erp_till_below_zero_test_body('includes/erp/cash.php', 'erp_till_outflow_refusal');

	pg_assert_contains('SELECT kind, balance FROM erp_cash_accounts', $body, 'kind and balance are read');
	pg_assert_contains('FOR UPDATE', $body, 'with the row locked');
	pg_assert_contains('erp_till_outflow_row_refusal(', $body, 'and judged by the pure rule');
}

function test_erp_till_below_zero_transfer_is_checked_inside_its_transaction()
{
	$body = erp_till_below_zero_test_body('includes/erp/cash.php', 'erp_post_transfer');
	$begin = strpos($body, 'erp_tx_begin()');
	$check = strpos($body, 'erp_till_outflow_refusal($from_id, $amount)');
	$write = strpos($body, "erp_cash_post(\$shared + array('cash_account_id' => \$from_id");

	pg_assert_true($check !== false, 'the from-till balance is checked');
	pg_assert_true(($begin !== false) && ($check > $begin) && ($check < $write), 'after the transaction starts and before the first movement');
	pg_assert_contains("empty(\$data['allow_below_zero'])", $body, 'the cheque module may skip it');
}

function test_erp_till_below_zero_payment_is_checked_before_and_inside_its_transaction()
{
	$check = erp_till_below_zero_test_body('includes/erp/cash.php', 'erp_post_receipt_check');
	pg_assert_contains('erp_till_outflow_refusal($cash_account_id, $amount)', $check, 'a rehearsal meets the refusal');
	pg_assert_contains('!$is_collection', $check, 'only money going out is checked');
	pg_assert_contains("empty(\$data['allow_below_zero'])", $check, 'the cheque module may skip it');

	$post = erp_till_below_zero_test_body('includes/erp/cash.php', 'erp_post_receipt');
	$begin = strpos($post, 'erp_tx_begin()');
	$again = strpos($post, 'erp_till_outflow_refusal($cash_account_id, $amount)');
	$write = strpos($post, '$cash_id = erp_cash_post(');

	pg_assert_true(($again !== false) && ($again > $begin) && ($again < $write), 'asked again under the lock before the movement');
}

function test_erp_till_below_zero_expense_payment_is_checked()
{
	$refusal = erp_till_below_zero_test_body('includes/erp/expenses.php', 'erp_expense_payment_refusal');
	pg_assert_contains("erp_till_outflow_refusal((int) (\$payment['cash_account_id'] ?? 0), (int) (\$expense['total_amount'] ?? 0))", $refusal, 'the payment is held against the till balance');

	// Paying on the spot names the amount the payment will take.
	$save = erp_till_below_zero_test_body('includes/erp/expenses.php', 'erp_expense_save_check');
	pg_assert_contains("'total_amount' => \$columns['total_amount']", $save, 'the save check passes the total');

	// The same refusal is asked inside the transaction, under the expense's lock.
	$post = erp_till_below_zero_test_body('includes/erp/expenses.php', 'erp_expense_post_payment');
	pg_assert_contains('erp_expense_payment_refusal($expense, $payment)', $post, 'and again inside the transaction');
}

function test_erp_till_below_zero_cheque_postings_skip_the_check()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cheques.php');
	$doors = substr_count($source, '= erp_post_receipt(array(') + substr_count($source, '= erp_post_transfer(array(');

	pg_assert_same(3, $doors, 'the cheque module posts through three calls');
	pg_assert_same($doors, substr_count($source, "'allow_below_zero' => true,"), 'every one of them skips the balance check');
}

// The API refuses the same and cannot ask for the skip.
function test_erp_till_below_zero_api_gives_the_same_refusal()
{
	$transfer = erp_till_below_zero_test_body('includes/erp/api_cash.php', 'erp_api_cash_transfers_create');
	$check = strpos($transfer, 'erp_till_outflow_row_refusal($from, $amount)');
	$stop = strpos($transfer, 'api_dry_run_stop(');

	pg_assert_true(($check !== false) && ($check < $stop), 'a transfer is checked before the dry run stops');

	foreach (array('includes/erp/api_cash.php', 'includes/erp/api_resources.php') as $file) {
		pg_assert_false(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), 'allow_below_zero') !== false, $file . ' never skips the check');
	}
}
