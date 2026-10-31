<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the invoice status words (erp_invoice_status_labels(),
 * includes/erp/settlement.php): the invoice list, the invoice screen and the
 * receipt screen's allocations print the same translated label, never the
 * stored code ("partially_paid").
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/settlement.php');

function test_erp_invoice_status_labels_cover_every_status()
{
	$labels = erp_invoice_status_labels();

	pg_assert_same(array('draft', 'issued', 'partially_paid', 'paid', 'cancelled'), array_keys($labels), 'every stored status has a label');
	pg_assert_same(lang('Partly paid'), $labels['partially_paid'], 'partially_paid reads as Partly paid');
	pg_assert_same(lang('Paid'), $labels['paid'], 'paid reads as Paid');
}

function test_erp_invoice_status_label_falls_back_to_the_code()
{
	pg_assert_same(lang('Issued'), erp_invoice_status_label('issued'), 'a known status is translated');
	pg_assert_same('odd', erp_invoice_status_label('odd'), 'an unknown code is shown as it is');
}

// The screens share the one list instead of keeping their own copies.
function test_erp_invoice_status_labels_used_by_the_screens()
{
	foreach (array('erp_invoices.php', 'edit_erp_invoice.php', 'erp_receipt.php', 'includes/erp/files.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_false(strpos($source, "'partially_paid' => lang('Partly paid')") !== false, $file . ' keeps no copy of the labels');
		pg_assert_true(strpos($source, 'erp_invoice_status_label') !== false, $file . ' asks the shared function');
	}

	$receipt = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_receipt.php');
	pg_assert_false(strpos($receipt, "h((string) \$settlement['invoice_status'])") !== false, 'the allocations print no raw status code');
	pg_assert_contains("h(erp_invoice_status_label((string) \$settlement['invoice_status']))", $receipt, 'the allocations print the translated status');
}
