<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the File Manager's ERP purchase invoices
 * (erp_files_invoice_payload() in includes/erp/files.php): the store keeps no
 * rendering of a purchase invoice, so its entry is no '<number>.pdf' served by
 * get_erp_invoice_pdf.php, which answers it with a notice page. It opens the
 * invoice record, has no type, download or size, and an old kept PDF does not
 * show it as kept. A sales invoice keeps its PDF.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/files.php');

function erp_files_purchase_record_test_row($direction, $kept)
{
	return array(
		'id' => 59, 'status' => 'issued', 'full_number' => 'PGFA2026000000002', 'currency' => '',
		'account_title' => 'Supplier', 'created_at' => 0, 'updated_at' => 0, 'direction' => $direction,
		'doc_type' => 'invoice', 'issue_date' => '2026-10-31', 'account_id' => 1, 'order_id' => 0,
		'grand_total' => 10000, 'kept_file_id' => $kept ? 7 : 0, 'kept_size' => 1234, 'kept_at' => 1,
	);
}

function test_erp_files_purchase_invoice_opens_its_record()
{
	$item = erp_files_invoice_payload(erp_files_purchase_record_test_row('purchase', true));

	pg_assert_same('PGFA2026000000002', $item['name'], 'no .pdf name');
	pg_assert_same('', $item['type'], 'not a PDF');
	pg_assert_same('', $item['download_url'], 'no download');
	pg_assert_true(strpos($item['url'], 'edit_erp_invoice.php?id=59') !== false, 'opens the invoice record');
	pg_assert_false(strpos($item['url'], 'get_erp_invoice_pdf.php') !== false, 'not the PDF endpoint');
	pg_assert_false($item['kept'], 'an old kept PDF is not shown as kept');
	pg_assert_same(null, $item['size'], 'no size');
	pg_assert_same('record', $item['source'], 'the record source');
}

function test_erp_files_sales_invoice_keeps_its_pdf()
{
	$item = erp_files_invoice_payload(erp_files_purchase_record_test_row('sales', true));

	pg_assert_same('PGFA2026000000002.pdf', $item['name'], 'a PDF name');
	pg_assert_same('pdf', $item['type'], 'a PDF');
	pg_assert_true(strpos($item['download_url'], 'get_erp_invoice_pdf.php?id=59&download=1') !== false, 'downloads the PDF');
	pg_assert_true($item['kept'], 'kept');
	pg_assert_same('kept', $item['source'], 'the kept source');
}
