<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the receipt screen: a receipt dated inside the locked
 * books shows its cancel step disabled with the lock reason under it, and
 * the reason dialog is not drawn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_erp_receipt_lock_cancel_disabled()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/erp_receipt.php');
	$cancel = substr($source, strpos($source, '$output_cancel = \'\';'), 4000);

	pg_assert_contains("\$cancel_locked = (\$receipt_cheque === null && !\$is_cancelled) ? erp_lock_refusal((string) \$receipt['doc_date']) : '';", $cancel, 'lock asked by the receipt date');
	pg_assert_true(strpos($cancel, 'if ($receipt_cheque !== null) {') < strpos($cancel, "} elseif (\$cancel_locked !== '') {"), 'cheque branch first');
	pg_assert_contains('disabled aria-describedby="erp_receipt_cancel_note"', $cancel, 'disabled button described by the note');
	pg_assert_contains('id="erp_receipt_cancel_note">\' . h($cancel_locked) . \'</p>', $cancel, 'reason printed');

	$locked = substr($cancel, strpos($cancel, "} elseif (\$cancel_locked !== '') {"));
	$locked = substr($locked, 0, strpos($locked, '} elseif (!$is_cancelled) {'));
	pg_assert_true(strpos($locked, 'cancel_receipt_modal') === false, 'no reason dialog for a locked receipt');
}
