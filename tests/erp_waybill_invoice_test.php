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

// A draft opened from a delivery note goes to the note's account only.
function test_erp_waybill_account_refusal_names_the_note()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/erp/waybills.php');

	$notes = array(array('id' => 7, 'full_number' => 'PGFS2026000000007', 'account_id' => 23, 'account_title' => 'pg-test Şirket'));

	pg_assert_same('', erp_waybill_account_refusal($notes, 23), 'the note\'s own account');
	pg_assert_same('', erp_waybill_account_refusal(array(), 18), 'a draft from no note');

	$refusal = erp_waybill_account_refusal($notes, 18);
	pg_assert_contains('PGFS2026000000007', $refusal, 'another account is refused, naming the note');
	pg_assert_contains('pg-test Şirket', $refusal, 'and the account it was written for');
}

// Issuing a draft made from a note asks the account first, takes a number
// only after, and writes the note's ship date and carrier onto the invoice
// before it commits, so the e-document is built with them.
function test_erp_invoice_issue_of_a_waybill_draft_writes_the_waybill_back()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');
	$issue = substr($source, (int) strpos($source, 'function erp_invoice_issue('));
	$issue = substr($issue, 0, (int) strpos($issue, 'function erp_invoice_draft_delete('));

	pg_assert_contains("erp_waybill_account_refusal(erp_waybills_for_invoice(\$invoice_id), (int) \$invoice['account_id'])", $issue, 'the issue asks the note\'s account');
	pg_assert_true(strpos($issue, 'erp_waybill_account_refusal(') < strpos($issue, 'erp_next_number('), 'before the number is taken');
	pg_assert_contains("erp_waybill_write_back((int) \$waybill['id'])", $issue, 'the note is written back');
	pg_assert_true(strpos($issue, 'erp_waybill_write_back(') < strpos($issue, 'erp_tx_commit()'), 'inside the issue\'s transaction');
	pg_assert_contains("'written_back' => \$written_back", $issue, 'and the caller is told what');

	$save = substr($source, (int) strpos($source, 'function erp_invoice_draft_save('));
	$save = substr($save, 0, (int) strpos($save, 'erp_tx_begin()'));
	pg_assert_contains("erp_waybill_account_refusal(erp_waybills_for_invoice(\$invoice_id), (int) \$header['account_id'])", $save, 'saving the draft to another account is refused');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_invoice_draft.php');
	pg_assert_contains("\$issued['written_back']", $screen, 'the draft screen reads what was written');
	pg_assert_contains('The shipment date and the carrier were written onto the invoice, which had none.', $screen, 'and says so as the order path does');

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/api_documents.php');
	$route = substr($api, (int) strpos($api, 'function erp_api_invoices_issue('));
	$route = substr($route, 0, (int) strpos($route, 'api_dry_run_stop('));
	pg_assert_contains('erp_waybill_account_refusal(', $route, 'an API rehearsal meets the same refusal');
}
