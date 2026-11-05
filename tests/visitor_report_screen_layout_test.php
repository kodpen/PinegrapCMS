<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor report edit form layout (view_visitor_report.php): the
 * report, layout and filter cards in a form column, how a report works in a
 * sticky status column written after it, Save & Run in the save bar column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function visitor_report_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor_report.php');
}

function test_visitor_report_screen_has_form_status_and_bar_columns()
{
	$source = visitor_report_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($source, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($bar !== false, 'the save bar sticks on its own column (a sticky box is held inside its parent, and the column is only as tall as the bar)');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in source order');
	pg_assert_contains('<div class="card my-7 position-sticky" style="top:3.3rem;">', $source, 'the status card sticks below the top bar');

	foreach (array("'name'=>'name'", "'name'=>'detail'", "'name'=>'summarize_by_1'", "'name'=>'order_by_3'", 'id="filter_table"', 'onclick="create_filter()"') as $field) {
		$at = strpos($source, $field);
		pg_assert_true($at !== false && $at > $form && $at < $status, $field . ' sits in the form column');
	}

	$save = strpos($source, 'id="submit_save"');
	pg_assert_true($save !== false && $save > $bar, 'Save & Run sits in the save bar column');
}

function test_visitor_report_screen_status_column_has_nothing_to_fill()
{
	$source = visitor_report_screen_layout_source();
	$start = (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$status = substr($source, $start, (int) strpos($source, '<div class="col-12 order-3 position-sticky"') - $start);

	pg_assert_contains("lang('How It Works')", $status, 'the status card has its title');
	pg_assert_false(strpos($status, '<input') !== false || strpos($status, 'output_field') !== false, 'nothing to fill in the status column');
}
