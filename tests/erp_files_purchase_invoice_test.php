<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the file manager's ERP folders address an invoice
 * (includes/erp/files.php, erp_files_invoice_target()): a sales invoice is
 * its PDF; a purchase invoice has no document of the store's own, so it is
 * listed as its record, opens the invoice screen and offers no download,
 * even when an earlier version kept a PDF for it.
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

require_once(PG_FUNCTIONS_DIR . '/includes/erp/archive.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/files.php');

function erp_files_purchase_test_row($direction, $status = 'issued', $kept = false)
{
	return array(
		'id' => 58,
		'status' => $status,
		'full_number' => ($status === 'draft') ? '' : 'PGFA2026000000001',
		'direction' => $direction,
		'doc_type' => 'invoice',
		'kept_file_id' => $kept ? 7 : 0,
	);
}

function test_erp_files_sales_invoice_is_its_pdf()
{
	$target = erp_files_invoice_target(erp_files_purchase_test_row('sales'));

	pg_assert_same('PGFA2026000000001.pdf', $target['name'], 'named as a PDF');
	pg_assert_same('pdf', $target['type'], 'a PDF');
	pg_assert_same('get_erp_invoice_pdf.php?id=58', $target['url'], 'opens the PDF');
	pg_assert_same('get_erp_invoice_pdf.php?id=58&download=1', $target['download_url'], 'downloads the PDF');
	pg_assert_same('generated', $target['source'], 'rendered when opened');
	pg_assert_true($target['kept_used'], 'a kept copy is shown');
}

function test_erp_files_purchase_invoice_opens_its_record()
{
	foreach (array(false, true) as $kept) {
		$target = erp_files_invoice_target(erp_files_purchase_test_row('purchase', 'issued', $kept));

		pg_assert_same('PGFA2026000000001', $target['name'], 'no .pdf in the name');
		pg_assert_same('', $target['type'], 'not a PDF');
		pg_assert_same('edit_erp_invoice.php?id=58', $target['url'], 'opens the invoice screen');
		pg_assert_same('', $target['download_url'], 'nothing to download');
		pg_assert_same('record', $target['source'], 'listed as the record');
		pg_assert_false($target['kept_used'], 'an old kept PDF is not shown as its file');
	}
}

function test_erp_files_draft_and_edoc_unchanged()
{
	$draft = erp_files_invoice_target(erp_files_purchase_test_row('purchase', 'draft'));
	pg_assert_same('edit_erp_invoice_draft.php?id=58', $draft['url'], 'a draft opens its editor');
	pg_assert_same('draft', $draft['source'], 'a draft');

	$edoc = erp_files_invoice_target(erp_files_purchase_test_row('purchase'), true);
	pg_assert_same('get_erp_edoc_document.php?id=58&format=pdf', $edoc['url'], 'the supplier e-document still opens');
	pg_assert_same('provider', $edoc['source'], 'the provider copy');
}
