<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the document a receipt closes (erp_post_receipt(),
 * includes/erp/cash.php, and POST /erp/receipts): only an invoice. A return
 * read without its doc_type looked like an invoice with its whole total open,
 * so the money settled the return and the invoice stayed open.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The open amount of a return is nothing, once its doc_type is read.
function test_erp_receipt_return_has_nothing_open()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/erp/settlement.php');

	pg_assert_same(0, erp_invoice_open_amount(array('id' => 0, 'doc_type' => 'return', 'status' => 'issued', 'grand_total' => 1000, 'paid_total' => 0)), 'a return');
}

// Both ways in read the doc_type and refuse anything but an invoice.
function test_erp_receipt_refuses_a_document_that_is_not_an_invoice()
{
	$cash = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/api_resources.php');

	$post = substr($cash, strpos($cash, 'function erp_post_receipt('));
	$post = substr($post, 0, strpos($post, 'erp_invoice_open_amount($invoice)'));
	pg_assert_contains('SELECT id, account_id, direction, doc_type,', $post, 'erp_post_receipt() reads the doc_type');
	pg_assert_contains("if ((string) \$invoice['doc_type'] !== 'invoice') {", $post, 'and refuses what is not an invoice');

	$create = substr($api, strpos($api, "\$invoice = db_item(\"SELECT id, account_id, doc_type, status FROM erp_invoices"));
	pg_assert_contains("api_fail_validation(lang('That document is not an invoice; a receipt does not close it.'), 'invoice_id');", substr($create, 0, 800), 'POST /erp/receipts answers 422');
}
