<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP expense categories screen layout
 * (erp_expense_categories.php): the categories table in a form column, a
 * sticky Summary of the counts and the account code help in a status column
 * written after it, the Save button in the save bar column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_expense_categories_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_expense_categories.php');
}

function test_erp_expense_categories_screen_has_form_and_status_columns()
{
	$source = erp_expense_categories_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($source, '<div class="col-12 order-3">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($bar !== false, 'the save bar has its own column');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in source order');
	pg_assert_contains('<div class="card my-7 position-sticky" style="top:3.3rem;">', $source, 'the status card sticks below the top bar');

	foreach (array('<table class="table align-middle mb-0">', 'name="new_name"', 'name="new_code"') as $field) {
		$at = strpos($source, $field);
		pg_assert_true($at !== false && $at > $form && $at < $status, $field . ' sits in the form column');
	}

	$nav = strpos($source, '<nav class="buttons navigation text-center position-sticky mb-7"');
	pg_assert_true($nav !== false && $nav > $bar, 'the save bar sits in its column');
}

function test_erp_expense_categories_status_column_reads_the_counts()
{
	$source = erp_expense_categories_screen_layout_source();
	$start = (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$status = substr($source, $start, (int) strpos($source, '<div class="col-12 order-3">') - $start);

	pg_assert_contains("lang('Summary')", $status, 'the status card has its title');
	foreach (array('$summary_total', '$summary_offered', '$summary_expenses') as $count) {
		pg_assert_contains($count, $status, $count . ' is shown');
	}
	pg_assert_contains('The account code is optional', $status, 'the account code help moved beside the table');
	pg_assert_false(strpos($status, '<input') !== false, 'nothing to fill in the status column');
}
