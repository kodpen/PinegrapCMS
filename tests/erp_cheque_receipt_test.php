<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the receipts of cheques and notes (includes/erp/cheques.php):
 * the collection that took a cheque in, or the payment that passed it on or
 * sent it back, is not cancelled on its own, because the cheque would stay
 * where it was and its next step would move the money a second time. The
 * receipt screen, the receipt cancel and the API all ask the same question.
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

// The reason names the cheque or note by its kind and serial number.
function test_erp_cheque_receipt_refusal_names_the_cheque()
{
	$cheque = erp_cheque_receipt_refusal(array('kind' => 'cheque', 'serial_no' => 'A-1062'));
	$note = erp_cheque_receipt_refusal(array('kind' => 'note', 'serial_no' => ''));

	pg_assert_contains(lang('Cheque') . ' A-1062', $cheque, 'a cheque by its serial number');
	pg_assert_contains(lang('Promissory note') . ',', $note, 'a note without a number');
}

// Every way to cancel a receipt asks whether it belongs to a cheque.
function test_erp_cheque_receipt_every_cancel_asks()
{
	$cash = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/api_resources.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_receipt.php');

	$check = substr($cash, strpos($cash, 'function erp_receipt_cancel_check('));
	$check = substr($check, 0, strpos($check, 'function erp_receipt_cancel('));
	pg_assert_contains('erp_cheque_for_cash((int) $receipt[\'id\'])', $check, 'erp_receipt_cancel_check() asks');
	pg_assert_contains('return $refuse(erp_cheque_receipt_refusal($cheque));', $check, 'and refuses with the reason');

	$cancel = substr($cash, strpos($cash, 'function erp_receipt_cancel('));
	$cancel = substr($cancel, 0, strpos($cancel, 'erp_tx_begin()'));
	pg_assert_contains('erp_receipt_cancel_check(', $cancel, 'erp_receipt_cancel() asks before it writes');

	$route = substr($api, strpos($api, 'function erp_api_receipts_cancel('));
	$route = substr($route, 0, strpos($route, 'erp_receipt_cancel('));
	pg_assert_contains("api_fail(409, 'receipt_belongs_to_cheque', erp_cheque_receipt_refusal(\$cheque));", $route, 'the API answers 409');

	pg_assert_contains('erp_cheque_for_cash($cash_id)', $screen, 'the receipt screen asks');
	pg_assert_contains('erp_cheque.php?id=', $screen, 'and leads to the cheque');
}
