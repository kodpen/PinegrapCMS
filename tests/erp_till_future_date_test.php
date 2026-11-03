<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the date check on till movements (includes/erp/cash.php,
 * erp_till_future_date_refusal()): a receipt, payment, transfer or expense
 * payment dated after today is refused, because the till ledger holds money
 * that moved; money due later is a cheque, a note or a due date. The cheque
 * module's own postings keep their dates.
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

function erp_till_future_date_test_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_erp_till_future_date_refuses_only_after_today()
{
	$refused = erp_till_future_date_refusal('2030-01-01', '2026-11-03');

	pg_assert_same(lang('A till movement cannot be dated after today. Record money that comes or goes later as a cheque or note, or as a due date.'), $refused, 'a date after today is refused, pointing at cheques, notes and due dates');
	pg_assert_same('', erp_till_future_date_refusal('2026-11-03', '2026-11-03'), 'today passes');
	pg_assert_same('', erp_till_future_date_refusal('2026-11-02', '2026-11-03'), 'a past date passes');
	pg_assert_same($refused, erp_till_future_date_refusal('2026-11-04', '2026-11-03'), 'tomorrow is refused with the same words');
	pg_assert_same('', erp_till_future_date_refusal('', '2026-11-03'), 'an empty date is left to the date checks');
	pg_assert_same('', erp_till_future_date_refusal(date('Y-m-d')), 'today by the clock passes');
}

function test_erp_till_future_date_is_asked_on_every_door()
{
	pg_assert_true(strpos(erp_till_future_date_test_body('includes/erp/cash.php', 'erp_post_receipt_check'), 'erp_till_future_date_refusal(') !== false, 'receipts and payments ask it');
	pg_assert_true(strpos(erp_till_future_date_test_body('includes/erp/cash.php', 'erp_post_transfer'), 'erp_till_future_date_refusal(') !== false, 'transfers ask it');
	pg_assert_true(strpos(erp_till_future_date_test_body('includes/erp/expenses.php', 'erp_expense_payment_refusal'), 'erp_till_future_date_refusal(') !== false, 'expense payments ask it');
}

function test_erp_till_future_date_cheque_postings_keep_their_dates()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cheques.php');

	pg_assert_same(3, substr_count($source, "'allow_future_date' => true"), 'the three cheque postings skip the check');

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/erp/api*.php') as $file) {
		pg_assert_true(strpos((string) file_get_contents($file), 'allow_future_date') === false, basename($file) . ' never skips it');
	}
}
