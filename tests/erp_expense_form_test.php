<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP expense form: typed amounts and rates that are not
 * numbers are refused, a refusal names the field it is about, a paid
 * expense's cancel needs the right to the tills, the filtered list rides
 * along a change, and a repeat ending before its first date is refused.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/fx.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/expenses.php');

function test_erp_expense_number_typed()
{
	foreach (array('', '100', '1.234,56', '1,234.56', '100,5', '1 250', '-50') as $value) {
		pg_assert_true(erp_expense_number_typed($value, true), 'an amount reads: ' . $value);
	}
	foreach (array('1e3', 'abc', '100 TL', '--5', '5-') as $value) {
		pg_assert_true(!erp_expense_number_typed($value, true), 'an amount is refused: ' . $value);
	}
	foreach (array('', '20', '18,5', '%20', '20 %', '0') as $value) {
		pg_assert_true(erp_expense_number_typed($value, false), 'a rate reads: ' . $value);
	}
	foreach (array('yirmi', '-18', '2O', '1e1') as $value) {
		pg_assert_true(!erp_expense_number_typed($value, false), 'a rate is refused: ' . $value);
	}
}

function test_erp_expense_figures_name_the_field()
{
	pg_assert_same('amount', erp_expense_figures(0, true, 20)['field'], 'no amount marks the amount');
	pg_assert_same('tax_rate', erp_expense_figures(10000, true, 100)['field'], 'a rate of 100 marks the rate');
	pg_assert_same('tax_amount', erp_expense_figures(10000, true, 20, -500)['field'], 'a negative tax marks the tax amount');
	pg_assert_same('tax_amount', erp_expense_figures(10000, true, 0, 500)['field'], 'a tax without a rate marks the tax amount');
	pg_assert_same('', erp_expense_figures(12000, true, 20)['field'], 'figures that add up mark nothing');

	$field = '';
	erp_expense_payment_refusal(array('expense_date' => '2026-01-01', 'currency' => 'TRY'), array('payment_method' => 'barter', 'cash_account_id' => 1), $field);
	pg_assert_same('payment_method', $field, 'a wrong method marks the method');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/expenses.php');
	pg_assert_contains('return $fail($figures[\'error\'], $figures[\'field\']);', $source, 'the save marks the field the figures name');
	pg_assert_contains('return $fail($refusal, $refusal_field);', $source, 'and the field the payment refusal names');
}

function test_erp_expense_screens_cancel_send_to_and_repeat()
{
	$view = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_expense.php');
	pg_assert_contains("if (((string) \$expense['status'] === 'paid') && !\$can_cash) {", $view, 'a paid expense is cancelled only with the right to the tills');
	pg_assert_contains("href=\"add_erp_expense.php?id=' . \$id . h(\$send_to_query) . '\"", $view, 'Change keeps the filtered list');

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_erp_expense.php');
	pg_assert_contains("'/edit_erp_expense.php?id=' . \$saved_id . \$send_to_query", $form, 'the save goes back with the filtered list');
	pg_assert_contains('erp_expense_number_typed(', $form, 'the form refuses a value that is not a number');
	pg_assert_contains('id="erp_expense_repeat_pay"', $form, 'the paid repeat switch can be hidden with pay later');

	$recurring = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/expense_recurring.php');
	pg_assert_contains("), 'repeat_end');", $recurring, 'an end before the first repeat is refused on the end field');
	pg_assert_true(strpos($recurring, "'stopped' : 'active'") === false, 'a new repeat is never saved stopped');
}

function test_erp_expense_categories_blank_row_is_found_before_any_write()
{
	$rows = array(
		16 => array('name' => 'Rent changed'),
		17 => array('name' => '   '),
		18 => array('name' => 'Fuel'),
	);
	pg_assert_same(17, erp_expense_categories_blank_row($rows, array(16, 17, 18)), 'a blank second row is named');
	pg_assert_same(0, erp_expense_categories_blank_row($rows, array(16, 18)), 'a row the list does not know is skipped');
	pg_assert_same(0, erp_expense_categories_blank_row(array(16 => array('name' => 'Rent')), array(16)), 'named rows pass');
	pg_assert_same(16, erp_expense_categories_blank_row(array(16 => 'x'), array('16')), 'a row that is not a list of fields counts as blank');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/expenses.php');
	$start = (int) strpos($source, 'function erp_expense_categories_save(');
	$body = substr($source, $start, 4000);
	pg_assert_true(strpos($body, 'erp_expense_categories_blank_row(') < strpos($body, 'UPDATE erp_expense_categories'), 'every row is checked before the first write');
	pg_assert_contains('erp_tx_begin()', $body, 'the writes run in one transaction');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_expense_categories.php');
	pg_assert_contains("\$_SESSION['erp_expense_categories_sent']", $screen, 'a refused save keeps what was typed');
	pg_assert_contains("(defined('USER_ERP_READONLY') && USER_ERP_READONLY && !validate_erp_access(\$user, 'write'))", $screen, 'the read-only accountant is refused at the door');
}
