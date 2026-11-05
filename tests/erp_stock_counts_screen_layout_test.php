<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP stock counts screen layout (erp_stock_counts.php): the
 * new count field and the counts table in a form column, a sticky Summary of
 * the counts by status in a status column written after it, Start counting in
 * the save bar column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_stock_counts_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_stock_counts.php');
}

function test_erp_stock_counts_screen_has_form_and_status_columns()
{
	$source = erp_stock_counts_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($source, '<div class="col-12 order-3">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($bar !== false, 'the save bar has its own column');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in source order');
	pg_assert_contains('<div class="card my-7 position-sticky" style="top:3.3rem;">', $source, 'the status card sticks below the top bar');
	pg_assert_false(strpos($source, 'col-12 2xl:col-9"') !== false, 'the single capped column is gone');

	foreach (array('name="title"', '<table class="table table-hover align-middle mb-0" data-pg-sort>') as $part) {
		$at = strpos($source, $part);
		pg_assert_true($at !== false && $at > $form && $at < $status, $part . ' sits in the form column');
	}

	$button = strpos($source, "lang('Start counting')");
	pg_assert_true($button !== false && $button > $bar, 'Start counting sits in the save bar');
}

function test_erp_stock_counts_status_column_reads_the_counts()
{
	$source = erp_stock_counts_screen_layout_source();
	$start = (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$status = substr($source, $start, (int) strpos($source, '<div class="col-12 order-3">') - $start);

	pg_assert_contains("lang('Summary')", $status, 'the status card has its title');
	pg_assert_contains('$summary_total', $status, 'the number of counts is shown');
	pg_assert_contains('$summary_rows', $status, 'the counts by status are shown');
	pg_assert_false(strpos($status, '<input') !== false, 'nothing to fill in the status column');
}
