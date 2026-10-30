<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for ERP tables that tie each figure to a heading: the cash
 * forecast grid names its corner and its row labels, the account drawer's
 * latest movements, the waybill drawer lines and the cheque movements carry
 * a heading row, VAT By rate names the document type on every row, and the
 * profit, VAT and expense statements label their rows with row headers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_table_headers_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_erp_table_headers_cash_forecast_rows()
{
	$src = erp_table_headers_source('erp_cashflow.php');
	pg_assert_false(strpos($src, '<th></th>') !== false, 'no empty corner heading');
	pg_assert_contains("<th scope=\"col\">' . lang('Type') . '</th>", $src, 'corner names the kinds');
	pg_assert_contains('<th scope="row" class="fw-normal"><i class="bi', $src, 'kind name is a row header');
	pg_assert_contains('<th scope="row"\' . (($class !== \'\') ? \'\' : \' class="fw-normal"\') . \'>\' . h($label) . \'</th>', $src, 'total labels are row headers keeping a bold row bold');
}

function test_erp_table_headers_drawer_movements_and_waybill_lines()
{
	$src = erp_table_headers_source('includes/erp/drawer.php');
	pg_assert_contains("<thead><tr class=\"fg-secondary\"><th class=\"fw-normal\">' . lang('Date') . '</th><th class=\"fw-normal\">' . lang('Description') . '</th><th class=\"fw-normal text-end\">' . lang('Amount') . '</th><th class=\"fw-normal text-end\">' . lang('Balance') . '</th></tr></thead>", $src, 'account drawer movements');
	pg_assert_contains("<thead><tr class=\"fg-secondary\"><th class=\"fw-normal\">' . lang('Description') . '</th><th class=\"fw-normal text-end\">' . lang('Quantity') . '</th></tr></thead>", $src, 'waybill lines');
}

function test_erp_table_headers_cheque_movements()
{
	$src = erp_table_headers_source('erp_cheque.php');
	pg_assert_contains("<thead><tr class=\"fg-secondary\"><th class=\"fw-normal\">' . lang('Date') . '</th><th class=\"fw-normal\">' . lang('Description') . '</th><th class=\"fw-normal\">' . lang('Till') . '</th><th class=\"fw-normal text-end\">' . lang('Amount') . '</th></tr></thead>", $src, 'cheque movements');
}

function test_erp_table_headers_vat_report()
{
	$src = erp_table_headers_source('erp_vat_report.php');
	pg_assert_contains('<span class="visually-hidden">\' . h($labels[$item[\'key\']]) . \'</span>', $src, 'repeated type stays readable');
	pg_assert_contains('<th scope="row"\' . $weight . \'>\' . $label . \'</th>', $src, 'statement label is a row header');
	pg_assert_false(strpos($src, "'><td>' . \$label . '</td>") !== false, 'no td label left');
}

function test_erp_table_headers_profit_statement()
{
	$src = erp_table_headers_source('erp_profit.php');
	pg_assert_contains("<tr><th scope=\"row\" class=\"fw-normal\">' . lang('Sales') . '</th>", $src, 'plain row');
	pg_assert_contains("<tr class=\"fw-semibold border-top\"><th scope=\"row\" class=\"fw-semibold\">' . lang('Net sales') . '</th>", $src, 'semibold row');
	pg_assert_contains("<tr class=\"fw-bold border-top border-2\"><th scope=\"row\">' . lang('Net profit')", $src, 'bold row');
	pg_assert_contains('<th scope="row" class="ps-7 fg-secondary fw-normal">', $src, 'expense category');
}

function test_erp_table_headers_expense_figures()
{
	$src = erp_table_headers_source('edit_erp_expense.php');
	pg_assert_contains("<tr><th scope=\"row\" class=\"fg-secondary fw-normal\">' . lang('Net') . '</th>", $src, 'net');
	pg_assert_contains("<tr class=\"fw-bold\"><th scope=\"row\">' . lang('Total') . '</th>", $src, 'total');
}
