<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP receipt and expense forms and the receipt cancel: amount
 * boxes filled with a plain figure (erp_money_input()), an invoice the
 * movement cannot close refused before the form is drawn, a cancelled
 * invoice named as cancelled, and a cancelled payment worded as a payment.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/money.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');

function erp_receipt_form_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The box holds the figure only; its adornment names the currency.
function test_erp_money_input_has_no_symbol()
{
	$separators = erp_number_separators();
	$expected = number_format(5536.40, 2, $separators['decimal'], $separators['thousands']);

	pg_assert_same($expected, erp_money_input(553640), 'a plain figure with the store separators');
	pg_assert_same($expected, erp_money_input(-553640), 'no minus either');
	pg_assert_same(erp_kurus($expected), 553640, 'erp_kurus() reads it back');
}

function test_erp_amount_boxes_are_filled_without_a_symbol()
{
	foreach (array('add_erp_receipt.php', 'add_erp_expense.php') as $file) {
		$src = erp_receipt_form_source($file);

		pg_assert_false((bool) preg_match("/assign_field_value\\('(amount|tax_amount)', erp_money_out_currency\\(/", $src), $file . ': no amount box takes the symbol text');
		pg_assert_contains("assign_field_value('amount', erp_money_input(", $src, $file . ': the amount box takes the plain figure');
	}
}

// A cancelled invoice is named as cancelled, before "already closed".
function test_erp_receipt_invoice_refusal_names_a_cancelled_invoice()
{
	$invoice = array('account_id' => 7, 'status' => 'cancelled', 'doc_type' => 'invoice', 'direction' => 'sales',
		'currency' => 'TRY', 'grand_total' => 1000, 'paid_total' => 0);

	pg_assert_same(lang('That invoice has been cancelled.'), erp_receipt_invoice_refusal($invoice, 7, 'TRY', true), 'cancelled');
}

// The form opened from an invoice asks the save's refusal before drawing.
function test_erp_receipt_form_refuses_an_invoice_it_cannot_close()
{
	$src = erp_receipt_form_source('add_erp_receipt.php');
	$refusal = (int) strpos($src, '$preset_refusal = ');

	pg_assert_true($refusal > 0, 'the form works out a refusal');
	pg_assert_contains('erp_receipt_invoice_refusal($preset_invoice, ', substr($src, $refusal, 300), 'with the save\'s own check');
	pg_assert_true($refusal < (int) strpos($src, '<form name="form" action="add_erp_receipt.php"'), 'before the form is drawn');
}

// A cancelled payment is stored as a payment's cancellation.
function test_erp_receipt_cancel_words_a_payment_as_a_payment()
{
	$src = erp_receipt_form_source('includes/erp/cash.php');
	$cancel = substr($src, (int) strpos($src, 'function erp_receipt_cancel('));

	pg_assert_contains("'Cancellation of payment #{var:1} dated {var:2}: {var:3}'", $cancel, 'the payment wording');
	pg_assert_contains("((string) \$receipt['direction'] === 'out')", $cancel, 'chosen by the money\'s direction');
}

// The invoice's settlement icon names a payment as a payment.
function test_erp_invoice_settlement_icon_names_a_payment()
{
	$src = erp_receipt_form_source('edit_erp_invoice.php');

	pg_assert_contains("(((string) \$settlement['source_type'] === 'payment') ? lang('Payment') : lang('Receipt'))", $src, 'the tooltip follows the source');
}
