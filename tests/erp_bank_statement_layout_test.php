<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the one bank statement screen layout (erp_bank_statement.php):
 * the column choice or the lines in a work column, the statement facts or a
 * Summary of the lines in a sticky status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_bank_statement_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_bank_statement.php');
}

function test_erp_bank_statement_has_work_and_status_columns()
{
	$source = erp_bank_statement_layout_source();
	$shell = substr($source, (int) strpos($source, '<main id="content" class="container-fluid">'));

	$notices = strpos($shell, '$liveform->output_notices()');
	$form = strpos($shell, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($shell, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the work column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($notices !== false && $notices < $form, 'notices sit above both columns');
	pg_assert_true($form < $status, 'work column before status column');

	$body = strpos($shell, '$output_body');
	pg_assert_true($body > $form && $body < $status, 'the column choice and the lines sit in the work column');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">\' . $output_status . \'', $shell, 'the status box sticks below the top bar');
}

function test_erp_bank_statement_status_column_reads_the_statement()
{
	$source = erp_bank_statement_layout_source();

	// While the columns are chosen: the bank account, the file, when it came
	// and how many lines it holds.
	$mapping = substr($source, (int) strpos($source, "if ((string) \$statement['status'] === 'mapping') {"));
	$mapping = substr($mapping, 0, (int) strpos($mapping, '$output_body = '));
	foreach (array("lang('Statement')", "lang('Bank account')", "lang('File')", "lang('Uploaded')", "\$status_line(lang('Lines'), (string) count(\$rows))") as $part) {
		pg_assert_contains($part, $mapping, $part . ' in the statement card');
	}

	// Once taken in: the lines by status and the money in and out.
	pg_assert_contains("\$status_counts[(string) \$line['status']]++;", $source, 'lines counted by status');
	pg_assert_contains("\$money_out += abs((int) \$line['amount']);", $source, 'money out summed as a positive amount');
	$summary = substr($source, (int) strpos($source, '$output_status_counts = \'\';'), 1600);
	foreach (array("lang('Summary')", "lang('Money in')", "lang('Money out')", '$output_status_counts', "(string) count(\$lines)") as $part) {
		pg_assert_contains($part, $summary, $part . ' in the Summary');
	}
}
