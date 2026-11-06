<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the return create screen layout (add_erp_return.php): the date
 * and the lines in a form column, the invoice's facts in a sticky status
 * column written after it, the create button in a sticky save bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_return_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_erp_return.php');
}

function test_erp_return_screen_has_form_and_status_columns()
{
	$source = erp_return_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($source, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($bar !== false, 'the save bar column is there');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in source order');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');

	$lines = strpos($source, "lang('Lines')");
	$date = strpos($source, "'id' => 'issue_date'");
	pg_assert_true($lines !== false && $lines > $form && $lines < $status, 'the lines card sits in the form column');
	pg_assert_true($date !== false && $date > $form && $date < $status, 'the date field sits in the form column');
}

function test_erp_return_screen_status_column_holds_the_invoice_facts()
{
	$source = erp_return_screen_layout_source();
	$start = (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$status = substr($source, $start, (int) strpos($source, 'order-3 position-sticky') - $start);

	foreach (array("lang('Summary')", "lang('Invoice')", "lang('Account')", "lang('Invoice Date')", "lang('Invoice Total')") as $label) {
		pg_assert_contains($label, $status, $label . ' sits in the status column');
	}

	pg_assert_false(strpos($status, '<input') !== false || strpos($status, 'output_field') !== false, 'nothing to fill in the status column');
}

function test_erp_return_screen_create_button_in_save_bar()
{
	$source = erp_return_screen_layout_source();
	$bar = substr($source, (int) strpos($source, 'order-3 position-sticky'));

	pg_assert_contains('name="submit_create"', $bar, 'the create button sits in the save bar');
	pg_assert_false(strpos($source, '<nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;"') !== false, 'the stickiness moved from the nav to its column');
}
