<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the one-receipt screen layout (erp_receipt.php): the allocations
 * and the allocate form in the work column, the movement's facts in a sticky
 * status column written after it, the cancel button in a sticky save bar and
 * the cheque's note out of that bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_receipt_view_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_receipt.php');
}

function test_erp_receipt_view_has_work_and_status_columns()
{
	$source = erp_receipt_view_layout_source();
	$main = substr($source, (int) strpos($source, '<main id="content"'));

	$work = strpos($main, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($main, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($main, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');

	pg_assert_true($work !== false && $status !== false && $bar !== false, 'the three columns are there');
	pg_assert_true($work < $status && $status < $bar, 'work column, status column, save bar in source order');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">\' . $output_header . \'', $main, 'the movement sticks below the top bar in the status column');

	$work_part = substr($main, (int) $work, (int) $status - (int) $work);
	foreach (array('$output_allocations', '$output_allocate', '$output_cheque_note') as $part) {
		pg_assert_contains($part, $work_part, $part . ' is in the work column');
	}
	pg_assert_contains('$output_cancel', substr($main, (int) $bar), 'the cancel button is in the save bar');
}

function test_erp_receipt_view_bar_is_not_sticky_twice()
{
	$source = erp_receipt_view_layout_source();

	pg_assert_true(strpos($source, 'navigation text-center position-sticky') === false, 'the bar column is sticky, not the nav inside it');
	pg_assert_true(strpos($source, "\$output_cancel = '\n            <div class=\"alert") === false, 'the cheque note is not put in the save bar');
}
