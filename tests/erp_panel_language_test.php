<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for ERP screens in the panel language: the sticky save bar's
 * name is translated, the cash flow method heading reads "Payment method",
 * percentages go through the language's order (erp_percent_text() and the
 * invoice editor's percent template) and counts use the language's
 * thousands separator.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_panel_language_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_erp_panel_language_save_bar_names()
{
	$paths = array(
		'add_erp_account.php', 'add_erp_expense.php', 'add_erp_invoice.php', 'add_erp_manual_invoice.php',
		'add_erp_purchase_order.php', 'add_erp_quote.php', 'add_erp_till.php', 'add_erp_transfer.php',
		'add_erp_waybill.php', 'edit_erp_account.php', 'edit_erp_invoice.php', 'edit_erp_invoice_draft.php',
		'edit_erp_purchase_order.php', 'edit_erp_quote.php', 'edit_erp_till.php', 'edit_erp_waybill.php',
		'erp_account_sync_item.php', 'erp_accounts_import.php', 'erp_expense_categories.php', 'erp_export.php',
		'erp_inbox_document.php', 'erp_stock_count.php', 'add_container.php', 'edit_container.php',
		'create_file.php', 'edit_theme_file.php', 'import_shipments.php', 'private_label.php',
	);

	foreach ($paths as $path) {
		$source = erp_panel_language_source($path);
		pg_assert_false(strpos($source, 'aria-label="data edit buttons"') !== false, $path . ': literal save bar name');
		pg_assert_contains("aria-label=\"' . h(lang('data edit buttons')) . '\"", $source, $path);
	}
}

function test_erp_panel_language_cashflow_method_heading()
{
	$source = erp_panel_language_source('erp_cashflow.php');
	pg_assert_false(strpos($source, "lang('Method')") !== false, 'erp_cashflow.php: Method reads Metod');
	pg_assert_contains("<th>' . lang('Payment method') . '</th>", $source);
}

function test_erp_panel_language_dashboard_share_percent()
{
	$source = erp_panel_language_source('erp_dashboard.php');
	pg_assert_false(strpos($source, "' . \$share . '%</td>") !== false, 'erp_dashboard.php: English percent order');
	pg_assert_contains('erp_percent_text($share)', $source);
}

function test_erp_panel_language_counts_use_language_separators()
{
	foreach (array('erp_cash.php', 'erp_cashflow.php', 'erp_dashboard.php', 'erp_invoices.php') as $path) {
		$source = erp_panel_language_source($path);
		pg_assert_false((bool) preg_match('/number_format\(\$[a-z_]+(\[[^\]]+\])*\)/', $source), $path . ': comma-grouped count');
	}
}

function test_erp_panel_language_invoice_editor_percent()
{
	$form = erp_panel_language_source('includes/erp/invoice_form.php');
	pg_assert_contains("data-text-percent=\"' . h(lang('{var:1}%')) . '\"", $form);
	pg_assert_contains("data-decimal-mark=\"' . h(erp_number_separators()['decimal']) . '\"", $form);

	$script = erp_panel_language_source('assets/js/erp_invoice_editor.js');
	pg_assert_false((bool) preg_match("/trimNumber\([^\n]*\+ '%'/", $script), 'erp_invoice_editor.js: English percent order');
	pg_assert_true(substr_count($script, 'percentText(') >= 4, 'erp_invoice_editor.js: percentText() used at the three places');
}
