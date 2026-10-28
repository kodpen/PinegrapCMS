<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for named ERP fields: the return quantity, the receipt
 * allocation, the bank statement column mapping and expense category, the
 * cheque step fields, the account notes, labels of fixed account and currency
 * boxes, and the translated save bar names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_field_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_erp_field_names_return_lines()
{
	$source = erp_field_names_source('add_erp_return.php');

	pg_assert_contains("'aria-label' => lang('Returning')", $source);
	pg_assert_contains('<div class="card-body p-0 position-relative table-responsive">', $source, 'the lines table scrolls in its card');
}

function test_erp_field_names_receipt_allocation()
{
	$source = erp_field_names_source('erp_receipt.php');

	pg_assert_contains("id=\"allocate_' . \$invoice_id . '\" aria-label=\"' . h(lang(array('string' => 'Allocate to {var:1}', 'vars' => \$invoice['full_number']))) . '\"", $source);
}

function test_erp_field_names_bank_statement()
{
	$source = erp_field_names_source('erp_bank_statement.php');

	pg_assert_contains("<label class=\"form-label small\" for=\"' . \$name . '\">", $source, 'mapping label points at its select');
	pg_assert_contains("name=\"' . \$name . '\" id=\"' . \$name . '\"", $source, 'mapping select has the id');
	pg_assert_contains('<label class="form-label small" for="date_order">', $source);
	pg_assert_contains('id="date_order" name="date_order"', $source);
	pg_assert_contains("name=\"category_id\" style=\"max-width:16rem\" required aria-label=\"' . h(lang('Expense category')) . '\"", $source);
}

function test_erp_field_names_cheque_steps()
{
	$source = erp_field_names_source('erp_cheque.php');

	// The bank field repeats across the step forms, so it is named, not tied by id.
	pg_assert_contains("name=\"bank_till_id\" aria-label=\"' . h(lang('Bank account')) . '\"", $source);
	pg_assert_contains("name=\"reason\" maxlength=\"150\" aria-label=\"' . h(lang('Reason')) . '\"", $source);
}

function test_erp_field_names_fixed_boxes()
{
	$source = erp_field_names_source('includes/erp/account_form.php');

	pg_assert_contains("'type' => 'textarea', 'id' => 'notes', 'name' => 'notes', 'aria-label' => lang('Notes'),", $source);
	pg_assert_contains('<input class="form-control" type="text" id="currency_locked"', $source);
	pg_assert_contains("<label for=\"' . (\$locked ? 'currency_locked' : 'currency') . '\"", $source);

	$source = erp_field_names_source('add_erp_receipt.php');

	pg_assert_contains('<input class="form-control" type="text" id="account_id_locked"', $source);
	pg_assert_contains("<label for=\"' . ((\$preset_invoice !== null) ? 'account_id_locked' : 'account_id') . '\"", $source);
	pg_assert_contains('<input class="form-control" type="text" id="currency_locked"', $source);
	pg_assert_contains("<label for=\"' . ((\$preset_invoice !== null) ? 'currency_locked' : 'currency') . '\"", $source);
}

function test_erp_field_names_save_bars()
{
	foreach (array('add_erp_return.php', 'erp_receipt.php', 'add_erp_receipt.php') as $path) {
		$source = erp_field_names_source($path);

		pg_assert_contains("aria-label=\"' . h(lang('data edit buttons')) . '\"", $source, $path);
		pg_assert_true(strpos($source, 'aria-label="data edit buttons"') === false, $path . ': no literal name');
	}
}
