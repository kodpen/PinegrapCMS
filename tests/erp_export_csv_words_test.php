<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the words of the generic CSV exports (includes/erp/export.php):
 * the receipts file writes the payment method in words (Cash, Bank
 * transfer...) and the invoices file the status in words
 * (erp_invoice_status_label()), as the panel and the accountant pack do; an
 * unknown code is written as it is.
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

function erp_export_csv_words_receipt_row($method)
{
	$profile = erp_export_profile('csv_receipts');
	$rows = erp_export_rows_csv_receipts(array(array(
		'doc_date' => '2026-10-31',
		'doc_type' => 'collection',
		'account_title' => 'A',
		'account_tax_number' => '1',
		'till_name' => 'Till',
		'payment_method' => $method,
		'amount' => 10000,
		'currency' => 'TRY',
		'exchange_rate' => 1,
		'amount_base' => 10000,
		'description' => '',
		'id' => 1,
	)), $profile);

	return $rows[0];
}

function test_erp_export_receipts_csv_writes_the_method_in_words()
{
	pg_assert_same(lang('Cash'), erp_export_csv_words_receipt_row('cash')[5], 'cash');
	pg_assert_same(lang('Bank transfer'), erp_export_csv_words_receipt_row('transfer')[5], 'transfer');
	pg_assert_same(lang('Card'), erp_export_csv_words_receipt_row('card')[5], 'card');
	pg_assert_same(lang('Cheque'), erp_export_csv_words_receipt_row('cheque')[5], 'cheque');
	pg_assert_same(lang('Other'), erp_export_csv_words_receipt_row('other')[5], 'other');
	pg_assert_same('odd', erp_export_csv_words_receipt_row('odd')[5], 'an unknown code stays as it is');
}

function test_erp_export_payment_method_label()
{
	pg_assert_same(lang('Bank transfer'), erp_export_payment_method_label('transfer'), 'a known method');
	pg_assert_same('odd', erp_export_payment_method_label('odd'), 'an unknown method');
}

function test_erp_export_invoices_csv_writes_the_status_in_words()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/export.php');
	$start = strpos($src, 'function erp_export_rows_csv_invoices(');
	pg_assert_true($start !== false, 'the invoices row builder exists');
	$body = substr($src, $start, strpos($src, "\nfunction ", $start + 10) - $start);

	pg_assert_contains("erp_invoice_status_label(\$i['status'])", $body, 'the status goes through erp_invoice_status_label()');
	pg_assert_false(strpos($body, "(string) \$i['status'],") !== false, 'the raw status code is not written');
}
