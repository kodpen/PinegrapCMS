<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP minimum stock screen layout (erp_stock_minimums.php):
 * the toolbar and the list in a form column, a Summary of the list in a
 * sticky status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_stock_minimums_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_stock_minimums.php');
}

function test_erp_stock_minimums_has_form_and_status_columns()
{
	$source = erp_stock_minimums_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'form column before status column');
	pg_assert_true(strpos($source, '<div class="col-12 2xl:col-9">') === false, 'the single column is gone');
	pg_assert_contains('<div class="position-sticky my-7" style="top:3.3rem;">', $source, 'the status column sticks below the top bar');

	foreach (array('id="button_bar"', 'role="search"', 'pg_list_table_html(') as $part) {
		$at = strpos($source, $part);
		pg_assert_true($at !== false && $at > $form && $at < $status, $part . ' sits in the form column');
	}
}

function test_erp_stock_minimums_status_column_reads_the_counts()
{
	$source = erp_stock_minimums_layout_source();
	$status = substr($source, (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">'));

	pg_assert_contains("lang('Summary')", $status, 'the summary card has its title');
	foreach (array('$summary_products', '$summary_set', '$low_count') as $count) {
		pg_assert_contains($count, $status, $count . ' is shown');
	}
}
