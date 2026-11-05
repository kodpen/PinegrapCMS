<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP export screen layout (erp_export.php): the format, the
 * date filters and the recent runs in a form column, the export options in a
 * sticky status column written after it, the create button in a sticky
 * save bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_export_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_export.php');
}

function test_erp_export_screen_has_form_and_status_columns()
{
	$source = erp_export_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($source, '<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($bar !== false, 'the save bar column is there');
	pg_assert_true($form < $status && $status < $bar, 'form column, status column, save bar in source order');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');

	foreach (array('id="profile"', "'id' => 'direction'", "'id' => 'from'", "'id' => 'to'", 'id="export_runs"') as $needle) {
		$at = strpos($source, $needle);
		pg_assert_true($at !== false && $at > $form && $at < $status, $needle . ' sits in the form column');
	}
}

function test_erp_export_screen_status_column_holds_the_options()
{
	$source = erp_export_screen_layout_source();
	$start = (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$status = substr($source, $start, (int) strpos($source, 'order-3 position-sticky') - $start);

	pg_assert_contains("lang('Options')", $status, 'the Options card sits in the status column');
	foreach (array('not_exported', 'mark_exported', 'include_drafts', 'include_passive') as $id) {
		pg_assert_contains("'id' => '" . $id . "'", $status, $id . ' sits in the status column');
	}
}

function test_erp_export_screen_create_button_in_save_bar()
{
	$source = erp_export_screen_layout_source();
	$bar = substr($source, (int) strpos($source, 'order-3 position-sticky'));

	pg_assert_contains('name="submit_export"', $bar, 'the create button sits in the save bar');
	pg_assert_false(strpos($source, 'style="bottom:.5rem;"') !== false, 'the stickiness moved from the nav to its column');
	pg_assert_true(strpos($bar, '</form>') !== false, 'the save bar sits inside the form');
}
