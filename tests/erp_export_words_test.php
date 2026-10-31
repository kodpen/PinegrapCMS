<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the words of the generic ERP CSV files (includes/erp/export.php):
 * the receipts file writes the payment method and the invoices file writes
 * the status in the panel's words, not as the stored code; an unknown code
 * is written as it is.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/settlement.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/export.php');

function erp_export_words_test_profile()
{
	return array('format' => 'csv', 'decimal' => '.', 'date' => 'Y-m-d', 'currency_map' => array());
}

function test_erp_export_receipts_write_the_payment_method_in_words()
{
	$base = array('doc_date' => '2026-10-31', 'doc_type' => 'collection', 'account_title' => 'A', 'account_tax_number' => '1', 'till_name' => 'T', 'amount' => 10000, 'currency' => 'TRY', 'exchange_rate' => 1, 'amount_base' => 10000, 'description' => '', 'id' => 1);
	$receipts = array();

	foreach (array('cash', 'transfer', 'card', 'cheque', 'other', 'barter') as $method) {
		$receipts[] = array_merge($base, array('payment_method' => $method));
	}

	$rows = erp_export_rows_csv_receipts($receipts, erp_export_words_test_profile());

	pg_assert_same(lang('Cash'), $rows[0][5], 'cash');
	pg_assert_same(lang('Bank transfer'), $rows[1][5], 'transfer');
	pg_assert_same(lang('Card'), $rows[2][5], 'card');
	pg_assert_same(lang('Cheque'), $rows[3][5], 'cheque');
	pg_assert_same(lang('Other'), $rows[4][5], 'other');
	pg_assert_same('barter', $rows[5][5], 'an unknown code stays');
}

function test_erp_export_invoices_write_the_status_in_words()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/export.php');
	$start = strpos($source, 'function erp_export_rows_csv_invoices(');
	pg_assert_true($start !== false, 'erp_export_rows_csv_invoices() exists');
	$end = strpos($source, "\nfunction ", $start + 10);
	$body = substr($source, $start, $end - $start);

	pg_assert_contains("erp_invoice_status_label(\$i['status'])", $body, 'the status goes through the panel label');
	pg_assert_false(strpos($body, "(string) \$i['status']") !== false, 'no raw status code');
	pg_assert_same(lang('Paid'), erp_invoice_status_label('paid'), 'paid in words');
	pg_assert_same(lang('Issued'), erp_invoice_status_label('issued'), 'issued in words');
}
