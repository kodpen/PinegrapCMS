<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the receipt and payment record screen layout
 * (add_erp_receipt.php): the account, till, amount and description in a
 * form column, the date, the method and the invoice being closed in a
 * sticky status column written after it, the save button in a sticky save
 * bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_receipt_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_erp_receipt.php');
}

function test_erp_receipt_screen_has_form_and_status_columns()
{
	$source = erp_receipt_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($source, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($bar !== false, 'the save bar column is there');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in source order');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');

	foreach (array("'id' => 'account_id'", "'id' => 'cash_account_id'", "'id' => 'amount'", "'id' => 'description'") as $field) {
		$at = strpos($source, $field);
		pg_assert_true($at !== false && $at > $form && $at < $status, $field . ' sits in the form column');
	}
}

function test_erp_receipt_screen_status_column_holds_date_method_and_invoice()
{
	$source = erp_receipt_screen_layout_source();
	$start = (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$status = substr($source, $start, (int) strpos($source, 'order-3 position-sticky') - $start);

	foreach (array("'id' => 'doc_date'", "'id' => 'payment_method'", "lang('Summary')", "lang('Invoice Total')", "lang('Outstanding')") as $part) {
		pg_assert_contains($part, $status, $part . ' sits in the status column');
	}

	pg_assert_contains("(\$preset_invoice !== null)\n                            ? '<div class=\"card my-7\">", $status, 'the invoice summary shows only when an invoice is being closed');
}

function test_erp_receipt_screen_save_button_in_save_bar()
{
	$source = erp_receipt_screen_layout_source();
	$bar = substr($source, (int) strpos($source, 'order-3 position-sticky'));

	pg_assert_contains('name="submit_create"', $bar, 'the save button sits in the save bar');
	pg_assert_false(strpos($source, 'style="bottom:.5rem;"') !== false, 'the stickiness moved from the nav to its column');
}
