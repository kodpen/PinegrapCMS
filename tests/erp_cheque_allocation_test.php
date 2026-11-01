<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the movements of a bounced cheque or note
 * (includes/erp/cheques.php, erp_cheque_allocation_refusal()): the
 * collection that took it in and the payment that sent it back cancel each
 * other out, so neither is allocated to an invoice afterwards. The receipt
 * screen and erp_settlement_allocate() ask the same question, and the
 * cheque screen names allocations a bounce left behind.
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

// Only a bounced cheque or note refuses, and the reason names it.
function test_erp_cheque_allocation_refusal_only_when_bounced()
{
	$bounced = erp_cheque_allocation_refusal(array('kind' => 'cheque', 'serial_no' => 'PGT-0007', 'status' => 'bounced'));
	$note = erp_cheque_allocation_refusal(array('kind' => 'note', 'serial_no' => '', 'status' => 'bounced'));

	pg_assert_contains(lang('Cheque') . ' PGT-0007', $bounced, 'a bounced cheque by its serial number');
	pg_assert_contains(lang('Promissory note'), $note, 'a bounced note');

	foreach (array('portfolio', 'deposited', 'collected', 'endorsed', 'given', 'paid') as $status) {
		pg_assert_same('', erp_cheque_allocation_refusal(array('kind' => 'cheque', 'serial_no' => 'A-1', 'status' => $status)), $status . ' takes allocations');
	}

	pg_assert_same('', erp_cheque_allocation_refusal(null), 'a receipt of no cheque takes allocations');
}

// The only way to allocate after the fact refuses before it writes.
function test_erp_settlement_allocate_asks_the_cheque()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/settlement.php');
	$allocate = substr($src, (int) strpos($src, 'function erp_settlement_allocate('));
	$allocate = substr($allocate, 0, (int) strpos($allocate, 'erp_tx_begin()'));

	pg_assert_contains("erp_cheque_for_cash((int) \$txn['doc_id'])", $allocate, 'it finds the cheque of the movement');
	pg_assert_contains('erp_cheque_allocation_refusal($cheque)', $allocate, 'and asks whether it bounced');
}

// The receipt screen offers no allocation for a bounced cheque's movement,
// says why, and shows nothing as unallocated.
function test_erp_receipt_screen_allocation_closed_for_bounced_cheque()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_receipt.php');

	pg_assert_contains('erp_cheque_allocation_refusal($receipt_cheque)', $screen, 'the screen asks');
	pg_assert_contains('if (!$no_allocation && ($unallocated > 0))', $screen, 'the allocation form stays closed');
	pg_assert_contains('$money($no_allocation ? 0 : $unallocated)', $screen, 'nothing reads as unallocated');
	pg_assert_true(strpos($screen, '$receipt_cheque =') < strpos($screen, '$output_allocations ='), 'the cheque is found before the allocations are drawn');
}

// A cheque bounced before the bounce took its allocations off says so.
function test_erp_cheque_screen_names_allocations_left()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_cheque.php');

	pg_assert_contains("erp_cheque_bounced_allocations(\$cheque)", $screen, 'the screen counts what is left');
	pg_assert_contains("erp_receipt.php?id=' . (int) \$cheque['open_cash_id']", $screen, 'and leads to the receipt to remove it');
}
