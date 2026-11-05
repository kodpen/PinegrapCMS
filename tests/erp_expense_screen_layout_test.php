<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the expense screen layout (edit_erp_expense.php): the details
 * and the forms in a form column, the status and the amounts in a sticky
 * status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_expense_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_expense.php');
}

function test_erp_expense_screen_has_form_and_status_columns()
{
	$source = erp_expense_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'the status column comes after the form column in source order');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');
	pg_assert_false(strpos($source, 'xl:col-7') !== false, 'the old paired card grid is gone');
}

function test_erp_expense_screen_columns_hold_their_parts()
{
	$source = erp_expense_screen_layout_source();
	$form_at = (int) strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status_at = (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$form = substr($source, $form_at, $status_at - $form_at);
	$status = substr($source, $status_at);

	foreach (array("lang('The expense')", "lang('Payment')", "lang('The receipt')", "lang('Repeat')", '$output_cancel') as $part) {
		pg_assert_contains($part, $form, $part . ' sits in the form column');
	}

	foreach (array("lang('Amount and tax')", '$output_badge', '$output_figures', 'Recorded by {var:1} on {var:2}.') as $part) {
		pg_assert_contains($part, $status, $part . ' sits in the status column');
	}

	$toolbar = substr($source, (int) strpos($source, '<nav id="button_bar"'));
	$toolbar = substr($toolbar, 0, (int) strpos($toolbar, '</nav>'));
	pg_assert_false(strpos($toolbar, '$output_badge') !== false, 'the status badge left the toolbar');
}
