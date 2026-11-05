<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP account CSV import layout (erp_accounts_import.php): the
 * upload and mapping steps lay their cards out as a form column, a sticky
 * status column written after it and a save bar column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_accounts_import_layout_step($name, $next)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_accounts_import.php');
	$start = (int) strpos($source, 'function ' . $name . '(');
	$end = strpos($source, 'function ' . $next . '(', $start);

	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

function erp_accounts_import_layout_assert_columns($step, $label)
{
	$form = strpos($step, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($step, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar = strpos($step, '<div class="col-12 order-3">');

	pg_assert_true($form !== false && $status !== false && $bar !== false, $label . ': form, status and save bar columns are there');
	pg_assert_true($form < $status && $status < $bar, $label . ': form column, status column, save bar in source order');
	pg_assert_contains('position-sticky" style="top:3.3rem;"', substr($step, $status, $bar - $status), $label . ': the status box sticks below the top bar');
	pg_assert_contains('<nav class="buttons navigation', substr($step, $bar), $label . ': the save bar sits in its column');

	return array($form, $status, $bar);
}

function test_erp_accounts_import_upload_step_columns()
{
	$step = erp_accounts_import_layout_step('erp_import_step_upload', 'erp_import_step_map');
	list($form, $status) = erp_accounts_import_layout_assert_columns($step, 'upload');

	$file = strpos($step, "'name' => 'file'");
	pg_assert_true($file > $form && $file < $status, 'the file field sits in the form column');
	pg_assert_true(strpos($step, 'template=1') > $status, 'the template download sits in the status column');
}

function test_erp_accounts_import_map_step_columns()
{
	$step = erp_accounts_import_layout_step('erp_import_step_map', 'erp_import_step_result');
	list($form, $status, $bar) = erp_accounts_import_layout_assert_columns($step, 'map');

	$mapping = strpos($step, '$output_mapping . \'');
	$rows = strpos($step, '$output_rows . \'');
	pg_assert_true($mapping > $form && $mapping < $status, 'the column mapping sits in the form column');
	pg_assert_true($rows > $form && $rows < $status, 'the preview table sits in the form column');

	foreach (array('$output_summary', 'name="duplicate_mode"', 'name="default_kind"') as $part) {
		$at = strpos($step, $part, $status);
		pg_assert_true($at !== false && $at < $bar, $part . ' sits in the status column');
	}
}
