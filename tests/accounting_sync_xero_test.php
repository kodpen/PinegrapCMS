<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/accounting_sync_xero.php, Xero's
 * driver for the accounting sync: amounts and quantities, the payment's
 * account, refunds paid out, the contact, the item code, the lines and the
 * unmatched tax rates, a refund shared over the rates, the invoice, credit
 * note and payment bodies, the id read from an answer, the limit headers
 * and the answers the queue reads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync_xero.php');

function test_accounting_sync_xero_amounts()
{
	pg_assert_same(12.34, pg_accounting_sync_xero_amount(1234), 'minor units to two places');
	pg_assert_same(0.0, pg_accounting_sync_xero_amount(0), 'nothing');
	pg_assert_same(-0.05, pg_accounting_sync_xero_amount(-5), 'below zero');
	pg_assert_same(3, pg_accounting_sync_xero_quantity('3.0000'), 'a whole quantity');
	pg_assert_same(1.5, pg_accounting_sync_xero_quantity('1.5000'), 'a part');
	pg_assert_same(0.1235, pg_accounting_sync_xero_quantity(0.12345), 'four places');
}

function test_accounting_sync_xero_roles()
{
	pg_assert_same('bank', pg_accounting_sync_xero_payment_role('Offline Payment'), 'an offline order');
	pg_assert_same('bank', pg_accounting_sync_xero_payment_role('transfer'), 'an ERP transfer');
	pg_assert_same('bank', pg_accounting_sync_xero_payment_role('cash'), 'ERP cash');
	pg_assert_same('clearing', pg_accounting_sync_xero_payment_role('Credit/Debit Card'), 'a card');
	pg_assert_same('clearing', pg_accounting_sync_xero_payment_role('PayPal Express Checkout'), 'a payment service');
	pg_assert_same('clearing', pg_accounting_sync_xero_payment_role('card'), 'an ERP card receipt');

	pg_assert_true(pg_accounting_sync_xero_refund_paid_out('refund'), 'Refund on View Order');
	pg_assert_true(pg_accounting_sync_xero_refund_paid_out('return'), 'a return refunded by the payment service');
	pg_assert_true(pg_accounting_sync_xero_refund_paid_out('return_manual'), 'a return refunded by hand');
	pg_assert_false(pg_accounting_sync_xero_refund_paid_out('return_credit'), 'a return as store credit');
	pg_assert_false(pg_accounting_sync_xero_refund_paid_out('return_gift_card'), 'back onto a gift card');
	pg_assert_false(pg_accounting_sync_xero_refund_paid_out('store_credit'), 'a cancelled order as store credit');
}

function test_accounting_sync_xero_contact()
{
	pg_assert_same(
		array('Name' => 'Ada Lovelace', 'FirstName' => 'Ada', 'LastName' => 'Lovelace', 'EmailAddress' => 'ada@example.com', 'ContactNumber' => 'pg-contact-5'),
		pg_accounting_sync_xero_contact(array('number' => 'pg-contact-5', 'first_name' => ' Ada ', 'last_name' => 'Lovelace', 'email' => 'ada@example.com')),
		'a person');
	pg_assert_same('Analytical Ltd', pg_accounting_sync_xero_contact(array('company' => 'Analytical Ltd', 'first_name' => 'Ada'))['Name'], 'the company first');
	pg_assert_same(array('Name' => 'x@example.com', 'EmailAddress' => 'x@example.com'), pg_accounting_sync_xero_contact(array('email' => 'x@example.com')), 'only an e-mail');
	pg_assert_same(array('Name' => 'Customer'), pg_accounting_sync_xero_contact(array('email' => 'not an address')), 'nothing usable');
	pg_assert_same(50, mb_strlen(pg_accounting_sync_xero_contact(array('number' => str_repeat('n', 80)))['ContactNumber']), 'ContactNumber within 50');

	pg_assert_same('EmailAddress=="ada@example.com"', pg_accounting_sync_xero_email_where(' ada@example.com '), 'the filter');
	pg_assert_same('', pg_accounting_sync_xero_email_where('nobody'), 'no address, no filter');
	pg_assert_same('', pg_accounting_sync_xero_email_where('"a"@example.com'), 'a quote never reaches the filter');
}

function test_accounting_sync_xero_item()
{
	pg_assert_same('SKU-1', pg_accounting_sync_xero_item_code(' SKU-1 ', 9), 'the SKU');
	pg_assert_same('pg-9', pg_accounting_sync_xero_item_code(str_repeat('a', 31), 9), 'a SKU over 30 characters');
	pg_assert_same('pg-9', pg_accounting_sync_xero_item_code('', 9), 'no SKU');
	pg_assert_same('pg-9', pg_accounting_sync_xero_item_code('a"b', 9), 'a quote');

	$item = pg_accounting_sync_xero_item('SKU-1', str_repeat('b', 60));
	pg_assert_same(50, mb_strlen($item['Name']), 'Name within 50');
	pg_assert_true($item['IsSold'], 'sold');
	pg_assert_false(isset($item['InventoryAssetAccountCode']), 'not tracked as stock');
	pg_assert_same('SKU-1', pg_accounting_sync_xero_item('SKU-1', ' ')['Name'], 'the code as the name');
}

function test_accounting_sync_xero_lines()
{
	$types = array(20000 => 'OUTPUT2', 0 => 'NONE');
	$lines = array(array('tax_rate' => '20.000'), array('tax_rate' => 0), array('tax_rate' => 8.875), array('tax_rate' => 8.875));

	pg_assert_same(array(8875), pg_accounting_sync_xero_unmatched($lines, $types), 'the rate not matched, once');
	pg_assert_same(array(), pg_accounting_sync_xero_unmatched(array_slice($lines, 0, 2), $types), 'all matched');

	$line = pg_accounting_sync_xero_line(array('description' => 'Mug', 'quantity' => 2, 'unit_price' => 1000, 'discount_amount' => 300,
		'tax_rate' => 20, 'tax_total' => 340, 'product_id' => 4), $types, 'acc-id', 'MUG');
	pg_assert_same(array('Description' => 'Mug', 'Quantity' => 2, 'UnitAmount' => 10.0, 'AccountID' => 'acc-id', 'TaxType' => 'OUTPUT2',
		'TaxAmount' => 3.4, 'DiscountAmount' => 3.0, 'ItemCode' => 'MUG'), $line, 'a goods line');

	$line = pg_accounting_sync_xero_line(array('description' => '', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0, 'tax_total' => 0), $types, 'acc-id', '');
	pg_assert_same('-', $line['Description'], 'a description is required');
	pg_assert_false(isset($line['ItemCode']) || isset($line['DiscountAmount']), 'shipping: no item, no discount');
}

function test_accounting_sync_xero_refund_shares()
{
	$lines = array(
		array('line_total' => 1000, 'discount_amount' => 0, 'tax_total' => 200, 'tax_rate' => 20),
		array('line_total' => 1000, 'discount_amount' => 0, 'tax_total' => 0, 'tax_rate' => 0),
		array('line_total' => 1000, 'discount_amount' => 200, 'tax_total' => 0, 'tax_rate' => 0),
	);

	pg_assert_same(array(20000 => 400, 0 => 600), pg_accounting_sync_xero_refund_shares(1000, $lines), 'shared by what each rate came to');
	pg_assert_same(1001, array_sum(pg_accounting_sync_xero_refund_shares(1001, $lines)), 'the shares come to the refund');
	pg_assert_same(array(20000 => 3000), pg_accounting_sync_xero_refund_shares(3000, array_slice($lines, 0, 1)), 'one rate');
	pg_assert_same(array(), pg_accounting_sync_xero_refund_shares(0, $lines), 'nothing refunded');
	pg_assert_same(array(0 => 500), pg_accounting_sync_xero_refund_shares(500, array()), 'no lines: at no rate');

	pg_assert_same(200, pg_accounting_sync_xero_tax_inside(1200, 20000), '20% inside 12.00');
	pg_assert_same(0, pg_accounting_sync_xero_tax_inside(1200, 0), 'no rate');
	pg_assert_same(98, pg_accounting_sync_xero_tax_inside(1200, 8875), '8.875% inside 12.00');
}

function test_accounting_sync_xero_document()
{
	$invoice = pg_accounting_sync_xero_document(array('type' => 'ACCREC', 'contact_id' => 'c-1', 'date' => '2026-11-27', 'due_date' => '',
		'currency' => 'GBP', 'reference' => '1043', 'number' => '', 'lines' => array(array('Description' => 'Mug'))));
	pg_assert_same(array('Type' => 'ACCREC', 'Contact' => array('ContactID' => 'c-1'), 'Date' => '2026-11-27', 'LineAmountTypes' => 'Exclusive',
		'LineItems' => array(array('Description' => 'Mug')), 'Status' => 'AUTHORISED', 'CurrencyCode' => 'GBP', 'DueDate' => '2026-11-27',
		'Reference' => '1043'), $invoice, 'an order\'s invoice, numbered by Xero');

	$invoice = pg_accounting_sync_xero_document(array('type' => 'ACCREC', 'contact_id' => 'c-1', 'date' => '2026-11-27', 'due_date' => '2026-12-27',
		'number' => 'ABC2026000000001', 'lines' => array()));
	pg_assert_same('2026-12-27', $invoice['DueDate'], 'the ERP due date');
	pg_assert_same('ABC2026000000001', $invoice['InvoiceNumber'], 'the ERP number');

	$note = pg_accounting_sync_xero_document(array('type' => 'ACCRECCREDIT', 'contact_id' => 'c-1', 'date' => '2026-11-27', 'reference' => 'x',
		'number' => 'R1', 'inclusive' => true, 'lines' => array()));
	pg_assert_same('Inclusive', $note['LineAmountTypes'], 'a refund\'s lines hold their tax');
	pg_assert_same('R1', $note['CreditNoteNumber'], 'the return\'s number');
	pg_assert_false(isset($note['DueDate']) || isset($note['Reference']), 'no due date and no reference on a credit note');

	pg_assert_same(array('Invoice' => array('InvoiceID' => 'i-1'), 'Account' => array('AccountID' => 'a-1'), 'Date' => '2026-11-27', 'Amount' => 12.5, 'Reference' => '1043'),
		pg_accounting_sync_xero_payment('Invoice', 'i-1', 'a-1', '2026-11-27', 1250, '1043'), 'an invoice paid');
	pg_assert_same(array('CreditNote' => array('CreditNoteID' => 'n-1'), 'Account' => array('AccountID' => 'a-1'), 'Date' => '2026-11-27', 'Amount' => 1.0),
		pg_accounting_sync_xero_payment('CreditNote', 'n-1', 'a-1', '2026-11-27', 100, ''), 'a credit note paid out');
}

function test_accounting_sync_xero_answers()
{
	$id = 'A3A3B7D4-5D0F-4A8C-8D62-1C0A3F0B2E11';

	pg_assert_same(strtolower($id), pg_accounting_sync_xero_first_id(array('Invoices' => array(array('InvoiceID' => $id, 'HasErrors' => false))), 'Invoices', 'InvoiceID'), 'the id');
	pg_assert_same('', pg_accounting_sync_xero_first_id(array('Invoices' => array(array('InvoiceID' => $id, 'HasErrors' => true))), 'Invoices', 'InvoiceID'), 'a record with errors');
	pg_assert_same('', pg_accounting_sync_xero_first_id(array('Contacts' => array()), 'Contacts', 'ContactID'), 'none found');
	pg_assert_same('', pg_accounting_sync_xero_first_id(array('Items' => array(array('ItemID' => 'nope'))), 'Items', 'ItemID'), 'no UUID');
	pg_assert_same('', pg_accounting_sync_xero_first_id(null, 'Items', 'ItemID'), 'no answer');

	pg_assert_same(0, pg_accounting_sync_xero_pause(array('x-minlimit-remaining' => '40', 'x-daylimit-remaining' => '900')), 'calls left');
	pg_assert_same(60, pg_accounting_sync_xero_pause(array('x-minlimit-remaining' => '2')), 'the minute nearly spent');
	pg_assert_same(60, pg_accounting_sync_xero_pause(array('x-appminlimit-remaining' => '1')), 'the app\'s minute nearly spent');
	pg_assert_same(3600, pg_accounting_sync_xero_pause(array('x-minlimit-remaining' => '50', 'x-daylimit-remaining' => '10')), 'the day nearly spent');
	pg_assert_same(0, pg_accounting_sync_xero_pause(array()), 'no headers');

	pg_assert_same(array('ok' => false, 'status' => 429, 'error' => 'slow', 'retry_after' => 120),
		pg_accounting_sync_xero_failure(array('status' => 429, 'error' => 'slow', 'headers' => array('retry-after' => '120'))), 'Retry-After kept');
	pg_assert_same(60, pg_accounting_sync_xero_failure(array('status' => 429, 'headers' => array()))['retry_after'], 'a minute without one');
	pg_assert_false(isset(pg_accounting_sync_xero_failure(array('status' => 400, 'error' => 'bad'))['retry_after']), 'a refusal');

	// Read by the queue as it means them.
	$row = array('attempts' => 0);
	$waits = pg_accounting_sync_after_attempt($row, pg_accounting_sync_xero_not_ready('rate'), 1000);
	pg_assert_same('waiting', $waits['status'], 'not ready: waits');
	pg_assert_false(isset($waits['attempts']), 'not ready: no attempt');
	pg_assert_false(pg_accounting_sync_holds(pg_accounting_sync_xero_not_ready('rate')), 'not ready: the pass goes on');
	pg_assert_same('failed', pg_accounting_sync_after_attempt($row, pg_accounting_sync_xero_refused('no'), 1000)['status'], 'refused: failed at once');

	pg_assert_same('2026-11-27', pg_accounting_sync_xero_date('2026-11-27 10:00:00'), 'a datetime');
	pg_assert_same(date('Y-m-d', 1790000000), pg_accounting_sync_xero_date(1790000000), 'a unix time');
	pg_assert_same(date('Y-m-d'), pg_accounting_sync_xero_date('0000-00-00'), 'no date: today');
}

// The driver is found by the queue's pass.
function test_accounting_sync_xero_driver_found()
{
	pg_assert_same('pg_accounting_sync_send_xero', pg_accounting_sync_driver('xero'), 'Xero has a driver');
}
