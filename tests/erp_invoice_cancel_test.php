<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for cancelling an issued invoice from the invoice screen
 * (edit_erp_invoice.php): what erp_invoice_cancel() refuses (an invoice
 * cancelled already, money allocated to it, a return against it) is asked on
 * the row as it is at the POST and before the e-document provider is asked to
 * take the document back, so a refusal here never follows a cancel there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The part of the screen's cancel action that runs before the provider.
function pg_test_erp_invoice_cancel_screen_head()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_invoice.php');

	$start = strpos($screen, "if ((\$_POST['erp_action'] ?? '') === 'cancel') {");

	if ($start === false) {
		return '';
	}

	$branch = substr($screen, $start);
	$provider = strpos($branch, 'erp_edoc_invoice_provider_cancel(');

	return ($provider === false) ? '' : substr($branch, 0, $provider);
}

// Each refusal of erp_invoice_cancel() is asked before the provider.
function test_erp_invoice_cancel_screen_asks_before_the_provider()
{
	$head = pg_test_erp_invoice_cancel_screen_head();

	pg_assert_true($head !== '', 'the cancel action reaches the provider');
	pg_assert_contains("(string) \$invoice['status'] === 'cancelled'", $head, 'an invoice cancelled already');
	pg_assert_contains('erp_invoice_receipt_count($invoice_id) > 0', $head, 'money allocated after the page was drawn');
	pg_assert_contains('!empty(erp_invoice_returns($invoice_id))', $head, 'a return written after the page was drawn');
	pg_assert_contains("\$liveform->mark_error('_error', \$cancel_refusal);", $head, 'the refusal is shown');
	pg_assert_contains("go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . \$invoice_id . \$send_to_query);", $head, 'and the screen goes back without asking the provider');
}

// The screen refuses in the words erp_invoice_cancel() uses.
function test_erp_invoice_cancel_screen_uses_the_cancel_messages()
{
	$head = pg_test_erp_invoice_cancel_screen_head();
	$returns = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/returns.php');
	$cancel = substr($returns, (int) strpos($returns, 'function erp_invoice_cancel('));
	$cancel = substr($cancel, 0, (int) strpos($cancel, 'erp_tx_begin()'));

	$messages = array(
		"lang('That invoice has been cancelled.')",
		"lang('Money has been allocated to this invoice, so it cannot be cancelled. Undo the settlement first.')",
		"lang('There is a return against this invoice, so it cannot be cancelled.')",
	);

	foreach ($messages as $message) {
		pg_assert_contains($message, $cancel, 'erp_invoice_cancel() refuses with ' . $message);
		pg_assert_contains($message, $head, 'the screen refuses with ' . $message);
	}
}
