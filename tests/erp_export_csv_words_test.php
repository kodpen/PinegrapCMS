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
	$profile = array('format' => 'csv', 'decimal' => '.', 'date' => 'Y-m-d', 'currency_map' => array());
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

function test_erp_export_receipts_csv_writes_a_cancellation_signed()
{
	$profile = array('format' => 'csv', 'decimal' => '.', 'date' => 'Y-m-d', 'currency_map' => array());
	$rows = erp_export_rows_csv_receipts(array(
		array('doc_date' => '2026-11-01', 'doc_type' => 'payment', 'account_title' => 'A', 'account_tax_number' => '1', 'till_name' => 'Bank',
			'payment_method' => 'transfer', 'amount' => 5000, 'currency' => 'TRY', 'exchange_rate' => 1, 'amount_base' => 5000,
			'description' => 'part', 'id' => 23, 'doc_id' => 0, 'cancelled_doc_type' => null),
		array('doc_date' => '2026-11-01', 'doc_type' => 'cancel', 'account_title' => 'A', 'account_tax_number' => '1', 'till_name' => 'Bank',
			'payment_method' => 'transfer', 'amount' => 5000, 'currency' => 'TRY', 'exchange_rate' => 1, 'amount_base' => 5000,
			'description' => 'wrong', 'id' => 24, 'doc_id' => 23, 'cancelled_doc_type' => 'payment'),
	), $profile);

	pg_assert_same(lang('Cancellation'), $rows[1][1], 'the cancel row says what it is');
	pg_assert_same('-50.00', $rows[1][6], 'it takes the amount back');
	pg_assert_same('-50.00', $rows[1][9], 'and the base amount');
	pg_assert_same('23', $rows[1][12], 'it names the receipt it cancels');
	pg_assert_same('', $rows[0][12], 'a receipt cancels nothing');
	pg_assert_same(count(erp_export_headers('csv_receipts')), count($rows[1]), 'one header per column');
}

function test_erp_export_receipts_fetch_takes_their_cancellations()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/export.php');
	$start = strpos($src, 'function erp_export_fetch_receipts(');
	$body = substr($src, $start, strpos($src, "\nfunction ", $start + 10) - $start);

	pg_assert_contains("c.doc_type = 'cancel' AND o.doc_type IN ('collection','payment')", $body, 'the cancel of a receipt is in the file');
}
