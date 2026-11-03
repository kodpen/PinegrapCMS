<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bank statements: a statement none of whose lines is recorded or matched
 * can be deleted with its new and set-aside lines; one with a recorded or
 * matched line refuses and names those lines; a file with no dated amount is
 * refused when it is taken in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bank_import.php');

function erp_bank_statement_delete_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_erp_bank_statement_delete_open_lines_pass()
{
	pg_assert_same('', erp_bank_statement_delete_refusal(array(), ''), 'a statement with no lines');
	pg_assert_same('', erp_bank_statement_delete_refusal(array(
		array('id' => 1, 'doc_date' => '2026-10-01', 'amount' => 1000, 'status' => 'new'),
		array('id' => 2, 'doc_date' => '2026-10-02', 'amount' => -500, 'status' => 'ignored'),
	), ''), 'new and set-aside lines go with it');
}

function test_erp_bank_statement_delete_names_recorded_and_matched_lines()
{
	$refusal = erp_bank_statement_delete_refusal(array(
		array('id' => 1, 'doc_date' => '2026-10-01', 'amount' => 1000, 'status' => 'new'),
		array('id' => 2, 'doc_date' => '2026-10-02', 'amount' => 12345, 'status' => 'recorded'),
		array('id' => 3, 'doc_date' => '2026-10-03', 'amount' => -800, 'status' => 'matched'),
	), '');

	pg_assert_true($refusal !== '', 'refused');
	pg_assert_contains(prepare_form_data_for_output('2026-10-02', 'date', false), $refusal, 'the recorded line is named by its date');
	pg_assert_contains(erp_money_out_currency(12345, ''), $refusal, 'and its amount');
	pg_assert_contains(prepare_form_data_for_output('2026-10-03', 'date', false), $refusal, 'the matched line is named');
	pg_assert_true(strpos($refusal, prepare_form_data_for_output('2026-10-01', 'date', false)) === false, 'an open line is not named');
}

function test_erp_bank_statement_read_needs_a_dated_amount()
{
	pg_assert_false(erp_bank_read_has_movement(array('rows' => array(array('name', 'surname'), array('Ali', 'Veli')), 'error' => '')), 'a sheet with no dated amount');
	pg_assert_true(erp_bank_read_has_movement(array('rows' => array(array('Date', 'Amount'), array('02.10.2026', '-800,00')), 'error' => '')), 'a sheet with one');
	pg_assert_false(erp_bank_read_has_movement(array('rows' => array(), 'csv_lines' => array('hello', 'world'), 'error' => '')), 'a text CSV');
	pg_assert_true(erp_bank_read_has_movement(array('rows' => array(), 'csv_lines' => array('Tarih|Tutar', '02.10.2026|-800,00'), 'error' => '')), 'a CSV read on any of the separators');
}

function test_erp_bank_statement_delete_wiring()
{
	$import = erp_bank_statement_delete_source('includes/erp/bank_import.php');
	pg_assert_contains('erp_bank_read_has_movement($read)', $import, 'the upload asks for a dated amount');

	$screen = erp_bank_statement_delete_source('erp_bank_statement.php');
	pg_assert_contains("\$action === 'delete'", $screen, 'the screen has a delete action');
	pg_assert_contains('erp_bank_statement_delete($statement_id)', $screen, 'through the one function');
	pg_assert_contains('log_activity(', $screen, 'logged');
}
