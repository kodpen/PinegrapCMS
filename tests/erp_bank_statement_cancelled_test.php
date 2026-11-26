<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bank statement lines whose till movement was cancelled afterwards: the
 * line can be opened again and recorded anew, and the screen says why.
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

function erp_bank_statement_cancelled_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_erp_bank_line_reopenable()
{
	pg_assert_true(erp_bank_line_reopenable(array('status' => 'matched', 'cash_id' => 5, 'cash_cancelled' => 0)), 'a matched line');
	pg_assert_true(erp_bank_line_reopenable(array('status' => 'matched', 'cash_id' => 5, 'cash_cancelled' => 1)), 'a matched line on a cancelled movement');
	pg_assert_true(erp_bank_line_reopenable(array('status' => 'recorded', 'cash_id' => 5, 'cash_cancelled' => 1)), 'a recorded line whose movement was cancelled');
	pg_assert_false(erp_bank_line_reopenable(array('status' => 'recorded', 'cash_id' => 5, 'cash_cancelled' => 0)), 'a recorded line keeps its movement');
	pg_assert_false(erp_bank_line_reopenable(array('status' => 'recorded', 'cash_id' => 5)), 'no cancel read');
	pg_assert_false(erp_bank_line_reopenable(array('status' => 'new', 'cash_id' => 0, 'cash_cancelled' => 0)), 'an open line');
	pg_assert_false(erp_bank_line_reopenable(array('status' => 'ignored', 'cash_id' => 0)), 'a line set aside');
}

function test_erp_bank_line_cancelled_movement_read_and_undone()
{
	$import = erp_bank_statement_cancelled_source('includes/erp/bank_import.php');
	$lines = substr($import, strpos($import, 'function erp_bank_statement_lines('), 900);
	pg_assert_contains("r.doc_type = 'cancel' AND r.doc_id = l.cash_id", $lines, 'lines read whether their movement was cancelled');

	$unmatch = substr($import, strpos($import, 'function erp_bank_line_unmatch('), 900);
	pg_assert_contains("status = 'recorded' AND EXISTS (SELECT 1 FROM erp_cash_transactions r WHERE r.doc_type = 'cancel' AND r.doc_id = erp_bank_statement_lines.cash_id)", $unmatch, 'a recorded line on a cancelled movement opens again');

	$screen = erp_bank_statement_cancelled_source('erp_bank_statement.php');
	pg_assert_contains("if (erp_bank_line_reopenable(\$line) && !\$readonly) {", $screen, 'the button follows the rule');
	pg_assert_contains("lang('Record again')", $screen, 'a recorded line is recorded again');
}
