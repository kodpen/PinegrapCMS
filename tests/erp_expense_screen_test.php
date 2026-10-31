<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP expense screen (edit_erp_expense.php): the stored
 * receipt row and the screen's helper closures keep their own variables, so
 * drawing the screen never reads a closure as an array.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_erp_expense_screen_receipt_variable_is_not_overwritten()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_expense.php');

	pg_assert_contains("\$kept = erp_archive_file('expense', \$id);", $source, 'the stored receipt is read into $kept');
	pg_assert_true(preg_match_all('/^\$kept\s*=/m', $source) === 1, '$kept is assigned only once, by the receipt read');
	pg_assert_true(preg_match('/\$kept\s*\(/', $source) === 0, '$kept is never called as a closure');
}

function test_erp_expense_screen_closures_have_their_own_names()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_expense.php');

	preg_match_all('/^\$(\w+)\s*=\s*function\b/m', $source, $closures);
	pg_assert_true(count($closures[1]) > 0, 'the screen closures are found');

	foreach ($closures[1] as $name) {
		$assignments = preg_match_all('/^\s*\$' . $name . '\s*=(?!=)/m', $source);
		pg_assert_true($assignments === 1, '$' . $name . ' holds only its closure');
	}

	pg_assert_contains("\$sent_form('pay')", $source, 'the Pay form reads back its own choices');
	pg_assert_contains("\$sent_form('repeat')", $source, 'the Repeat form reads back its own choices');
}
