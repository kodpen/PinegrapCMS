<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// What each ERP event (erp_webhook_events()) carries in data, in the shape of
// the core's api_webhook_event_payloads(): its fields, each with a type and a
// meaning, and one sample. The fields are the keys the announcing functions of
// includes/erp/events.php write; tests/webhook_event_payloads_test.php reads
// them and fails when the two part. Money is in minor units (cents), dates
// are YYYY-MM-DD and moments UTC.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
	exit;
}

function erp_webhook_event_payloads()
{
	$invoice = array(
		'id'                => array('integer', 'The invoice (GET /erp/invoices/{id}).'),
		'number'            => array('string', 'The invoice number, empty for a draft.'),
		'direction'         => array('string', 'sales or purchase.'),
		'doc_type'          => array('string', 'invoice or return.'),
		'status'            => array('string', 'draft, issued, partially_paid, paid or cancelled.'),
		'account_id'        => array('integer', 'The customer or supplier card (GET /erp/accounts/{id}).'),
		'order_id'          => array('integer', 'The store order it was made from, 0 for none.'),
		'parent_invoice_id' => array('integer', 'For a return, the invoice it returns; 0 otherwise.'),
		'issue_date'        => array('string?', 'The issue date, null for none.'),
		'due_date'          => array('string?', 'The due date, null for none.'),
		'currency'          => array('string', 'The currency code.'),
		'grand_total'       => array('integer', 'The total with tax.'),
		'paid_total'        => array('integer', 'What has been paid of it.'),
		'edoc'              => array('object', 'The e-document: provider, status, gib_number and gib_uuid, empty strings for none.')
	);

	$invoice_sample = function ($status, $paid) {
		return array('id' => 318, 'number' => 'ABC2026000000318', 'direction' => 'sales', 'doc_type' => 'invoice', 'status' => $status, 'account_id' => 41, 'order_id' => 1042, 'parent_invoice_id' => 0, 'issue_date' => '2026-11-17', 'due_date' => '2026-12-17', 'currency' => 'TRY', 'grand_total' => 118000, 'paid_total' => $paid, 'edoc' => array('provider' => 'uyumsoft', 'status' => 'accepted', 'gib_number' => 'ABC2026000000318', 'gib_uuid' => '6f1c2a7e-3b9d-4c55-9a0e-2f4d8b7c1e90'));
	};

	$receipt = array(
		'id'              => array('integer', 'The receipt or payment (GET /erp/receipts/{id}).'),
		'direction'       => array('string', 'collection (money in from a customer) or payment (money out to a supplier).'),
		'date'            => array('string?', 'The date of the movement.'),
		'amount'          => array('integer', 'The amount, in its currency.'),
		'currency'        => array('string', 'The currency code.'),
		'amount_base'     => array('integer', 'The amount in the base currency.'),
		'account_id'      => array('integer', 'The customer or supplier card.'),
		'cash_account_id' => array('integer', 'The till or bank account.'),
		'payment_method'  => array('string', 'How it was paid, such as cash, bank or card.'),
		'invoice_id'      => array('integer', 'On a recording, the invoice the money was named for; 0 for none and on a cancellation.'),
		'description'     => array('string', 'The note written with it.')
	);

	$receipt_sample = array('id' => 902, 'direction' => 'collection', 'date' => '2026-11-17', 'amount' => 118000, 'currency' => 'TRY', 'amount_base' => 118000, 'account_id' => 41, 'cash_account_id' => 2, 'payment_method' => 'bank', 'invoice_id' => 318, 'description' => 'Transfer for invoice 318');

	$waybill = array(
		'id'            => array('integer', 'The delivery note (GET /erp/waybills/{id}).'),
		'number'        => array('string', 'Its number.'),
		'status'        => array('string', 'issued or cancelled.'),
		'account_id'    => array('integer', 'The customer or supplier card.'),
		'order_id'      => array('integer', 'The store order, 0 for none.'),
		'invoice_id'    => array('integer', 'The invoice, 0 for none.'),
		'issue_date'    => array('string?', 'The issue date.'),
		'ship_date'     => array('string?', 'The ship date, null for none.'),
		'carrier_title' => array('string', 'The carrier, empty for none.')
	);

	$waybill_sample = function ($status) {
		return array('id' => 77, 'number' => 'IRS2026000000077', 'status' => $status, 'account_id' => 41, 'order_id' => 1042, 'invoice_id' => 318, 'issue_date' => '2026-11-17', 'ship_date' => '2026-11-18', 'carrier_title' => 'Yurtiçi Kargo');
	};

	$expense = array(
		'id'                  => array('integer', 'The expense (GET /erp/expenses/{id}).'),
		'date'                => array('string?', 'The expense date.'),
		'category_id'         => array('integer', 'Its category.'),
		'category_name'       => array('string', 'The category\'s name.'),
		'supplier'            => array('string', 'Who it was paid to.'),
		'supplier_tax_number' => array('string', 'The supplier\'s tax number, empty for none.'),
		'document_no'         => array('string', 'The number of the supplier\'s document.'),
		'description'         => array('string', 'The note written with it.'),
		'currency'            => array('string', 'The currency code.'),
		'net_amount'          => array('integer', 'The amount before tax.'),
		'tax_amount'          => array('integer', 'The tax.'),
		'total_amount'        => array('integer', 'The total.'),
		'total_base'          => array('integer', 'The total in the base currency.'),
		'tax_deductible'      => array('boolean', 'Whether its tax is deductible.'),
		'due_date'            => array('string?', 'The due date, null for none.'),
		'status'              => array('string', 'unpaid, paid or cancelled.'),
		'paid_date'           => array('string?', 'The day it was paid, null while unpaid.'),
		'cash_account_id'     => array('integer', 'The till or bank account it was paid from, 0 while unpaid.'),
		'cash_id'             => array('integer', 'The payment\'s till movement, 0 while unpaid.')
	);

	$expense_sample = function ($status, $paid) {
		return array('id' => 64, 'date' => '2026-11-15', 'category_id' => 3, 'category_name' => 'Rent', 'supplier' => 'Kadıköy Properties', 'supplier_tax_number' => '1234567890', 'document_no' => 'K-2026-11', 'description' => 'Shop rent, November', 'currency' => 'TRY', 'net_amount' => 2500000, 'tax_amount' => 500000, 'total_amount' => 3000000, 'total_base' => 3000000, 'tax_deductible' => true, 'due_date' => '2026-11-20', 'status' => $status, 'paid_date' => $paid ? '2026-11-17' : null, 'cash_account_id' => $paid ? 2 : 0, 'cash_id' => $paid ? 905 : 0);
	};

	$quote = array(
		'id'          => array('integer', 'The quote (GET /erp/quotes/{id}).'),
		'number'      => array('string', 'Its number.'),
		'status'      => array('string', 'The stored status: open, accepted, rejected, invoiced or cancelled.'),
		'state'       => array('string', 'The status as the panel shows it: an open quote past valid_until is expired.'),
		'account_id'  => array('integer', 'The customer card.'),
		'invoice_id'  => array('integer', 'The invoice made from it, 0 for none.'),
		'issue_date'  => array('string?', 'The issue date.'),
		'valid_until' => array('string?', 'The last day it runs, null for no end.'),
		'currency'    => array('string', 'The currency code.'),
		'grand_total' => array('integer', 'The total with tax.')
	);

	$quote_sample = function ($status, $invoice_id) {
		return array('id' => 25, 'number' => 'TKL2026000000025', 'status' => $status, 'state' => $status, 'account_id' => 41, 'invoice_id' => $invoice_id, 'issue_date' => '2026-11-10', 'valid_until' => '2026-11-30', 'currency' => 'TRY', 'grand_total' => 236000);
	};

	$order = array(
		'id'            => array('integer', 'The purchase order (GET /erp/purchase-orders/{id}).'),
		'number'        => array('string', 'Its number.'),
		'status'        => array('string', 'Its status, open, sent, partial (partly received), received or cancelled.'),
		'account_id'    => array('integer', 'The supplier card.'),
		'issue_date'    => array('string?', 'The issue date.'),
		'expected_date' => array('string?', 'The day the goods are expected, null for none.'),
		'currency'      => array('string', 'The currency code.'),
		'grand_total'   => array('integer', 'The total with tax.'),
		'sent_at'       => array('string?', 'When it went to the supplier, null before.')
	);

	$order_sample = function ($status, $sent) {
		return array('id' => 12, 'number' => 'SAS2026000000012', 'status' => $status, 'account_id' => 57, 'issue_date' => '2026-11-12', 'expected_date' => '2026-11-26', 'currency' => 'TRY', 'grand_total' => 590000, 'sent_at' => $sent ? '2026-11-12T10:15:00Z' : null);
	};

	$template = array(
		'id'    => array('integer', 'The quote template (GET /erp/quote-templates/{id}).'),
		'name'  => array('string', 'Its name.'),
		'scope' => array('string', 'personal or site.')
	);

	$payloads = array(

		'erp.account.created' => array(
			'fields' => array(
				'id'         => array('integer', 'The customer or supplier card (GET /erp/accounts/{id}).'),
				'kind'       => array('string', 'customer, supplier or both.'),
				'title'      => array('string', 'The card\'s title.'),
				'tax_number' => array('string', 'The tax or identity number, empty for none.'),
				'contact_id' => array('integer', 'The store contact it is tied to, 0 for none.'),
				'currency'   => array('string', 'The card\'s currency code.')
			),
			'sample' => array('id' => 41, 'kind' => 'customer', 'title' => 'Demir Kırtasiye Ltd.', 'tax_number' => '2930012345', 'contact_id' => 884, 'currency' => 'TRY')
		),

		'erp.invoice.created'      => array('fields' => $invoice, 'sample' => $invoice_sample('issued', 0)),
		'erp.invoice.paid'         => array('fields' => $invoice, 'sample' => $invoice_sample('paid', 118000)),
		'erp.invoice.cancelled'    => array('fields' => $invoice, 'sample' => $invoice_sample('cancelled', 0)),
		'erp.invoice.edoc_changed' => array('fields' => $invoice, 'sample' => $invoice_sample('issued', 0)),

		'erp.invoice.overdue' => array(
			'fields' => $invoice + array('days_overdue' => array('integer', 'How many days past its due date.')),
			'sample' => $invoice_sample('issued', 0) + array('days_overdue' => 3)
		),

		'erp.invoice.note_changed' => array(
			'fields' => $invoice + array('notes' => array('string', 'The internal note now.')),
			'sample' => $invoice_sample('issued', 0) + array('notes' => 'Customer asked for a copy by post.')
		),

		'erp.edoc.received' => array(
			'fields' => array(
				'id'                  => array('integer', 'The row of the e-document inbox.'),
				'gib_uuid'            => array('string', 'The document\'s GİB UUID.'),
				'external_id'         => array('string', 'The provider\'s own id, empty for none.'),
				'gib_number'          => array('string', 'The document number.'),
				'invoice_type'        => array('string', 'The invoice type, such as SATIS or IADE.'),
				'profile'             => array('string', 'The e-invoice profile, such as TEMELFATURA or TICARIFATURA.'),
				'issue_date'          => array('string', 'The issue date, 0000-00-00 when the provider gave none.'),
				'supplier_title'      => array('string', 'The supplier\'s name.'),
				'supplier_tax_number' => array('string', 'The supplier\'s tax number.'),
				'currency'            => array('string', 'The currency code.'),
				'tax_base'            => array('integer', 'The amount before tax.'),
				'total'               => array('integer', 'The total.'),
				'provider_status'     => array('string', 'The status the provider gave, empty for none.')
			),
			'sample' => array('id' => 140, 'gib_uuid' => 'a0b1c2d3-e4f5-4a6b-8c7d-9e0f1a2b3c4d', 'external_id' => '88213', 'gib_number' => 'TED2026000004512', 'invoice_type' => 'SATIS', 'profile' => 'TICARIFATURA', 'issue_date' => '2026-11-16', 'supplier_title' => 'Tedarik A.Ş.', 'supplier_tax_number' => '1112223334', 'currency' => 'TRY', 'tax_base' => 500000, 'total' => 590000, 'provider_status' => 'Waiting for an answer')
		),

		'erp.receipt.created'   => array('fields' => $receipt, 'sample' => $receipt_sample),
		'erp.receipt.cancelled' => array('fields' => $receipt, 'sample' => array_merge($receipt_sample, array('invoice_id' => 0))),

		'erp.waybill.created'   => array('fields' => $waybill, 'sample' => $waybill_sample('issued')),
		'erp.waybill.cancelled' => array('fields' => $waybill, 'sample' => $waybill_sample('cancelled')),

		'erp.expense.created'   => array('fields' => $expense, 'sample' => $expense_sample('unpaid', false)),
		'erp.expense.paid'      => array('fields' => $expense, 'sample' => $expense_sample('paid', true)),
		'erp.expense.cancelled' => array('fields' => $expense, 'sample' => $expense_sample('cancelled', false)),

		'erp.quote.created'   => array('fields' => $quote, 'sample' => $quote_sample('open', 0)),
		'erp.quote.accepted'  => array('fields' => $quote, 'sample' => $quote_sample('accepted', 0)),
		'erp.quote.rejected'  => array('fields' => $quote, 'sample' => $quote_sample('rejected', 0)),
		'erp.quote.cancelled' => array('fields' => $quote, 'sample' => $quote_sample('cancelled', 0)),
		'erp.quote.reopened'  => array('fields' => $quote, 'sample' => $quote_sample('open', 0)),
		'erp.quote.invoiced'  => array('fields' => $quote, 'sample' => $quote_sample('invoiced', 318)),

		'erp.quote.signed' => array(
			'fields' => $quote + array(
				'signer_name' => array('string', 'The name the customer signed with.'),
				'signed_at'   => array('string?', 'When it was signed.')
			),
			'sample' => $quote_sample('accepted', 0) + array('signer_name' => 'Ayşe Demir', 'signed_at' => '2026-11-17T11:02:00Z')
		),

		'erp.quote_template.saved'   => array('fields' => $template, 'sample' => array('id' => 4, 'name' => 'Yearly maintenance', 'scope' => 'site')),
		'erp.quote_template.deleted' => array('fields' => $template, 'sample' => array('id' => 4, 'name' => 'Yearly maintenance', 'scope' => 'site')),

		'erp.stock_count.applied' => array(
			'fields' => array(
				'id'         => array('integer', 'The stock count (GET /erp/stock-counts/{id}).'),
				'title'      => array('string', 'Its title.'),
				'status'     => array('string', 'Its status, applied.'),
				'line_count' => array('integer', 'How many products it counted.'),
				'changed'    => array('integer', 'How many products\' stock it moved.'),
				'applied_at' => array('string?', 'When it was applied.')
			),
			'sample' => array('id' => 6, 'title' => 'Year-end count', 'status' => 'applied', 'line_count' => 240, 'changed' => 17, 'applied_at' => '2026-11-17T18:30:00Z')
		),

		'erp.purchase_order.created'   => array('fields' => $order, 'sample' => $order_sample('open', false)),
		'erp.purchase_order.changed'   => array('fields' => $order, 'sample' => $order_sample('open', false)),
		'erp.purchase_order.sent'      => array('fields' => $order, 'sample' => $order_sample('sent', true)),
		'erp.purchase_order.received'  => array('fields' => $order, 'sample' => $order_sample('received', true)),
		'erp.purchase_order.cancelled' => array('fields' => $order, 'sample' => $order_sample('cancelled', true)),
		'erp.purchase_order.reopened'  => array('fields' => $order, 'sample' => $order_sample('sent', true)),
		'erp.purchase_order.invoiced'  => array('fields' => $order, 'sample' => $order_sample('partial', true))

	);

	return $payloads;
}
