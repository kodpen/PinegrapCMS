<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the draft invoice made from a delivery note written without an
 * order (erp_waybill_invoice(), includes/erp/waybills.php): its lines start
 * with the price and the rate the invoice editor and the API give the same
 * product for the same account, so a product left to the tax zone is not
 * invoiced at 0%.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_erp_waybill_invoice_lines_use_the_editor_rate()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/waybills.php');
	$from = strpos($source, 'function erp_waybill_invoice(');
	pg_assert_true($from !== false, 'the function is there');

	$body = substr($source, $from, strpos($source, 'erp_invoice_draft_save(', $from) - $from);

	pg_assert_contains("erp_product_for_line(\$product, (int) \$waybill['account_id'])", $body, 'the line starts as the editor\'s does, for the waybill\'s account');
	pg_assert_contains("'tax_rate' => is_array(\$start) ? (float) \$start['tax_rate'] : 0", $body, 'its rate from there');
	pg_assert_false(strpos($body, "\$product['tax_rate'] !== null") !== false, 'no NULL rate read as 0%');
}

function test_erp_waybill_invoice_of_an_order_writes_the_waybill_back()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/waybills.php');
	$from = strpos($source, 'function erp_waybill_invoice(');
	$body = substr($source, $from, strpos($source, '$lines = array();', $from) - $from);

	pg_assert_contains('erp_waybill_write_back($waybill_id)', $body, 'the ship date and the carrier go onto the order\'s invoice');
	pg_assert_contains("'written_back' =>", $body, 'the screen is told what was written');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_waybill.php');
	pg_assert_contains("The shipment date and the carrier were written onto the invoice, which had none.", $screen, 'and says so');
}
