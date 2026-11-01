<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the note a cheque or note step leaves (includes/erp/cheques.php,
 * erp_cheque_step_note()): it says what the step did, so handing a cheque to
 * the bank does not claim a movement it never wrote.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/cheques.php');

function test_erp_cheque_step_note_per_step()
{
	$moved = lang('Done. The movement is in the till and on the account.');
	$transfer = lang('Done. The amount moved between your tills as a transfer.');

	pg_assert_same(lang('Handed to the bank for collection. The money moves when the bank collects it.'), erp_cheque_step_note('deposit'), 'deposit moves no money');
	pg_assert_same($transfer, erp_cheque_step_note('collect'), 'collect is a transfer');
	pg_assert_same($transfer, erp_cheque_step_note('pay'), 'paid by the bank is a transfer');
	pg_assert_same($moved, erp_cheque_step_note('endorse'), 'endorse moves the account');
	pg_assert_same($moved, erp_cheque_step_note('bounce'), 'bounce moves the account');
}

// The cheque screen shows the step's own note.
function test_erp_cheque_screen_uses_step_note()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_cheque.php');

	pg_assert_contains("add_notice(h(erp_cheque_step_note(\$step, count(\$done['reopened'] ?? array()))))", $screen, 'the notice comes from the step and counts the reopened invoices');
}

// A bounce says how many invoices the cheque had closed and are open again.
function test_erp_cheque_step_note_bounce_counts_reopened_invoices()
{
	$moved = lang('Done. The movement is in the till and on the account.');

	pg_assert_same($moved . ' ' . lang('The invoice the cheque or note had closed is open again.'), erp_cheque_step_note('bounce', 1), 'one invoice reopened');
	pg_assert_same($moved . ' ' . lang(array('string' => 'The {var:1} invoices the cheque or note had closed are open again.', 'vars' => 2)), erp_cheque_step_note('bounce', 2), 'two invoices reopened');
	pg_assert_same($moved, erp_cheque_step_note('endorse', 3), 'only a bounce reopens');
}

// The bounce takes the allocations off the cheque's receipt the way a
// cancelled receipt does, inside the step's transaction.
function test_erp_cheque_bounce_unsettles_the_receipt()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cheques.php');
	$cash = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');
	$step = substr($src, (int) strpos($src, 'function erp_cheque_step('));
	$cancel = substr($cash, (int) strpos($cash, 'function erp_receipt_cancel('));

	pg_assert_contains("erp_receipt((int) \$cheque['open_cash_id'])", $step, 'the bounce reads the cheque\'s receipt');
	pg_assert_contains("erp_txn_unsettle_all((int) \$opened['ledger_id'], (int) \$user_id)", $step, 'the bounce takes its allocations off');
	pg_assert_true(strpos($step, 'erp_txn_unsettle_all(') < strpos($step, 'erp_tx_commit()'), 'before the step commits');
	pg_assert_contains('erp_txn_unsettle_all($ledger_id, $created_by)', $cancel, 'a cancelled receipt goes the same way');
}
