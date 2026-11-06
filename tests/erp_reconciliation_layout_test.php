<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the reconciliation letter screen layout (erp_reconciliation.php):
 * the options and the preview in the work column, the balance and the send
 * card in a sticky status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_reconciliation_layout_main()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_reconciliation.php');
	return substr($source, (int) strpos($source, '<main id="content"'));
}

function test_erp_reconciliation_has_work_and_status_columns()
{
	$main = erp_reconciliation_layout_main();

	$work = strpos($main, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($main, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($work !== false && $status !== false, 'both columns are there');
	pg_assert_true($work < $status, 'the status column comes after the work column');

	$work_part = substr($main, (int) $work, (int) $status - (int) $work);
	pg_assert_contains('name="reply_days"', $work_part, 'the letter options are in the work column');
	pg_assert_contains('<iframe', $work_part, 'the preview is in the work column');

	$status_part = substr($main, (int) $status);
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $status_part, 'the status box sticks below the top bar');
	pg_assert_contains('$output_late_fee', $status_part, 'the late fee panel sits with the balance');
	pg_assert_true(strpos($status_part, '$output_send_card') > strpos($status_part, '$balance_class'), 'the send card follows the balance');
}
