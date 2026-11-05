<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP bank statements screen layout (erp_bank_statements.php):
 * the statements taken in so far in a form column, the Take in form and a
 * Summary of the statements in a sticky status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_bank_statements_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_bank_statements.php');
}

function test_erp_bank_statements_has_form_and_status_columns()
{
	$source = erp_bank_statements_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'form column before status column');
	pg_assert_contains('<div class="position-sticky my-7" style="top:3.3rem;">', $source, 'the status column sticks below the top bar');

	$list = strpos($source, 'id="erp_bank_statements"');
	pg_assert_true($list !== false && $list > $form && $list < $status, 'the statements list sits in the form column');

	foreach (array('name="cash_account_id"', 'name="statement"', 'data-erp-send-once-status') as $part) {
		$at = strpos($source, $part);
		pg_assert_true($at !== false && $at > $status, $part . ' sits in the status column');
	}
}

function test_erp_bank_statements_status_column_reads_the_counts()
{
	$source = erp_bank_statements_layout_source();
	$status = substr($source, (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">'));

	pg_assert_contains("lang('Summary')", $status, 'the summary card has its title');
	foreach (array('$summary_statements', '$summary_lines', '$summary_open') as $count) {
		pg_assert_contains($count, $status, $count . ' is shown');
	}
	pg_assert_contains('<span class="pg-btn-label">', $status, 'the Take in label is in its span');
}
