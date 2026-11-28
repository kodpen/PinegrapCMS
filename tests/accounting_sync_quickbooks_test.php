<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/accounting_sync_quickbooks.php,
 * QuickBooks Online's driver for the accounting sync: ids and quoted query
 * values, the customer and its second name, the item, quantities that come
 * to the amount, the lines, the tax given as the site charged it, the
 * document and payment bodies, the record read from an answer and the
 * spacing of calls.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync_quickbooks.php');

function test_accounting_sync_quickbooks_ids_and_quotes()
{
	pg_assert_true(pg_accounting_sync_quickbooks_id_valid('123'), 'digits');
	pg_assert_true(pg_accounting_sync_quickbooks_id_valid(7), 'a number');
	pg_assert_false(pg_accounting_sync_quickbooks_id_valid(''), 'nothing');
	pg_assert_false(pg_accounting_sync_quickbooks_id_valid('0b7a1c2e-xero'), 'a Xero id');

	pg_assert_same("'ana@example.com'", pg_accounting_sync_quickbooks_quoted(' ana@example.com '), 'quoted');
	pg_assert_same('', pg_accounting_sync_quickbooks_quoted("o'neil@example.com"), 'a quote is not put in');
	pg_assert_same('', pg_accounting_sync_quickbooks_quoted('a\\b'), 'a backslash is not put in');
	pg_assert_same('', pg_accounting_sync_quickbooks_quoted(''), 'empty');
}

function test_accounting_sync_quickbooks_customer()
{
	$buyer = array('number' => 'pg-contact-4', 'company' => '', 'first_name' => 'Ana', 'last_name' => 'Lee', 'email' => 'ana@example.com');

	pg_assert_same(array(
		'DisplayName'      => 'Ana Lee',
		'GivenName'        => 'Ana',
		'FamilyName'       => 'Lee',
		'PrimaryEmailAddr' => array('Address' => 'ana@example.com'),
	), pg_accounting_sync_quickbooks_customer($buyer), 'a person');

	$second = pg_accounting_sync_quickbooks_customer($buyer, 'pg-contact-4');
	pg_assert_same('Ana Lee (pg-contact-4)', $second['DisplayName'], 'the second name');

	$company = pg_accounting_sync_quickbooks_customer(array('company' => 'Acme Ltd', 'email' => 'not an address'));
	pg_assert_same('Acme Ltd', $company['DisplayName'], 'a company');
	pg_assert_same('Acme Ltd', $company['CompanyName'], 'its company name');
	pg_assert_false(isset($company['PrimaryEmailAddr']), 'no address that is not one');

	pg_assert_true(pg_accounting_sync_quickbooks_name_taken(array('Fault' => array('Error' => array(array('code' => '6240'))))), 'name taken');
	pg_assert_false(pg_accounting_sync_quickbooks_name_taken(array('Fault' => array('Error' => array(array('code' => '6000'))))), 'another refusal');
	pg_assert_false(pg_accounting_sync_quickbooks_name_taken(null), 'no answer');
}

function test_accounting_sync_quickbooks_item()
{
	pg_assert_same('SKU-1', pg_accounting_sync_quickbooks_item_name(' SKU-1 ', 9), 'the SKU');
	pg_assert_same('pg-9', pg_accounting_sync_quickbooks_item_name('Shoes:Red', 9), 'a colon names a sub-item');
	pg_assert_same('pg-9', pg_accounting_sync_quickbooks_item_name("Tom's", 9), 'a quote');
	pg_assert_same('pg-9', pg_accounting_sync_quickbooks_item_name(str_repeat('a', 101), 9), 'too long');
	pg_assert_same('pg-9', pg_accounting_sync_quickbooks_item_name('', 9), 'none');

	pg_assert_same(array(
		'Name'             => 'SKU-1',
		'Type'             => 'NonInventory',
		'IncomeAccountRef' => array('value' => '79'),
		'Description'      => 'Red shoes',
	), pg_accounting_sync_quickbooks_item('SKU-1', ' Red shoes ', '79'), 'not tracked as stock');
}

function test_accounting_sync_quickbooks_quantity_and_line()
{
	pg_assert_same(array('qty' => 3, 'unit_price' => 500, 'description' => 'Cup'),
		pg_accounting_sync_quickbooks_quantity('3.0000', 1500, 'Cup'), 'divides');
	pg_assert_same(array('qty' => 1, 'unit_price' => 1400, 'description' => '3 × Cup'),
		pg_accounting_sync_quickbooks_quantity(3, 1400, 'Cup'), 'a discount that does not divide');
	pg_assert_same(array('qty' => 1, 'unit_price' => 750, 'description' => '1.5 × Cloth'),
		pg_accounting_sync_quickbooks_quantity('1.5', 750, 'Cloth'), 'a part');

	pg_assert_same(array(
		'DetailType'          => 'SalesItemLineDetail',
		'Amount'              => 18.0,
		'Description'         => 'Cup',
		'SalesItemLineDetail' => array(
			'ItemRef'    => array('value' => '5'),
			'Qty'        => 2,
			'UnitPrice'  => 9.0,
			'TaxCodeRef' => array('value' => '3'),
		),
	), pg_accounting_sync_quickbooks_line(array('description' => 'Cup', 'quantity' => 2, 'line_total' => 2000,
		'discount_amount' => 200, 'tax_rate' => 20, 'tax_total' => 360), '5', '3'), 'a discounted line without tax');

	$line = pg_accounting_sync_quickbooks_line(array('description' => '', 'quantity' => 1, 'unit_price' => 499), '1', 'NON');
	pg_assert_same(4.99, $line['Amount'], 'from the unit price');
	pg_assert_same('-', $line['Description'], 'a line without words');
	pg_assert_same('NON', $line['SalesItemLineDetail']['TaxCodeRef']['value'], 'a US code');
}

function test_accounting_sync_quickbooks_tax_detail()
{
	$lines = array(
		array('line_total' => 1000, 'discount_amount' => 0, 'tax_rate' => 20, 'tax_total' => 200),
		array('line_total' => 500, 'discount_amount' => 100, 'tax_rate' => 20, 'tax_total' => 80),
		array('line_total' => 300, 'tax_rate' => 0, 'tax_total' => 0),
	);

	pg_assert_same(array('TotalTax' => 2.8), pg_accounting_sync_quickbooks_tax_detail($lines, true, array()), 'a US company: the total');

	$detail = pg_accounting_sync_quickbooks_tax_detail($lines, false, array(20000 => '11', 0 => '4'));
	pg_assert_same(2.8, $detail['TotalTax'], 'the total');
	pg_assert_same(2, count($detail['TaxLine']), 'one line per rate');
	pg_assert_same(array(
		'Amount'        => 2.8,
		'DetailType'    => 'TaxLineDetail',
		'TaxLineDetail' => array(
			'TaxRateRef'       => array('value' => '11'),
			'PercentBased'     => true,
			'TaxPercent'       => 20.0,
			'NetAmountTaxable' => 14.0,
		),
	), $detail['TaxLine'][0], 'the rate, its tax and what it was charged on');

	pg_assert_same(null, pg_accounting_sync_quickbooks_tax_detail($lines, false, array(20000 => '11', 0 => '')),
		'a code without one rate: QuickBooks works it out');
}

function test_accounting_sync_quickbooks_documents()
{
	$receipt = pg_accounting_sync_quickbooks_document('SalesReceipt', array(
		'customer_id' => '58', 'date' => '2026-11-20', 'currency' => 'GBP', 'number' => '', 'note' => 'Order 1001',
		'account_id' => '35', 'lines' => array(array('Amount' => 1)), 'tax' => array('TotalTax' => 0.2), 'us' => false,
	));

	pg_assert_same(array('value' => '58'), $receipt['CustomerRef'], 'the customer');
	pg_assert_same(array('value' => 'GBP'), $receipt['CurrencyRef'], 'its currency');
	pg_assert_same(array('value' => '35'), $receipt['DepositToAccountRef'], 'paid into the account');
	pg_assert_same('TaxExcluded', $receipt['GlobalTaxCalculation'], 'amounts without tax');
	pg_assert_same('Order 1001', $receipt['PrivateNote'], 'the order');
	pg_assert_false(isset($receipt['DocNumber']), 'numbered by QuickBooks');
	pg_assert_false(isset($receipt['DueDate']), 'no due date on a receipt');

	$invoice = pg_accounting_sync_quickbooks_document('Invoice', array(
		'customer_id' => '58', 'date' => '2026-11-20', 'due_date' => '2026-12-20', 'number' => 'INV2026000000123',
		'lines' => array(), 'tax' => null, 'us' => true,
	));

	pg_assert_same('INV2026000000123', $invoice['DocNumber'], 'the invoice number');
	pg_assert_same('2026-12-20', $invoice['DueDate'], 'its due date');
	pg_assert_false(isset($invoice['GlobalTaxCalculation']), 'a US company refuses it');
	pg_assert_false(isset($invoice['DepositToAccountRef']), 'an invoice is not paid into an account');
	pg_assert_false(isset($invoice['TxnTaxDetail']), 'no tax given');

	$long = pg_accounting_sync_quickbooks_document('CreditMemo', array('customer_id' => '1', 'date' => '2026-11-20',
		'number' => str_repeat('9', 22), 'lines' => array()));
	pg_assert_false(isset($long['DocNumber']), 'a number over 21 characters is left out');

	pg_assert_same(array(
		'CustomerRef'         => array('value' => '58'),
		'TotalAmt'            => 120.0,
		'TxnDate'             => '2026-11-21',
		'DepositToAccountRef' => array('value' => '35'),
		'Line'                => array(array('Amount' => 120.0, 'LinkedTxn' => array(array('TxnId' => '130', 'TxnType' => 'Invoice')))),
		'CurrencyRef'         => array('value' => 'EUR'),
		'PaymentRefNum'       => 'Receipt 7',
	), pg_accounting_sync_quickbooks_payment('58', '130', '35', '2026-11-21', 12000, 'EUR', 'Receipt 7'), 'a payment of the invoice');
}

function test_accounting_sync_quickbooks_answers()
{
	pg_assert_same(array('id' => '130', 'sync_token' => '2', 'record' => array('Id' => '130', 'SyncToken' => '2')),
		pg_accounting_sync_quickbooks_record(array('Invoice' => array('Id' => '130', 'SyncToken' => '2'), 'time' => 'x'), 'Invoice'), 'a record');
	pg_assert_same(null, pg_accounting_sync_quickbooks_record(array('Invoice' => array('Id' => 'x')), 'Invoice'), 'no valid id');
	pg_assert_same(null, pg_accounting_sync_quickbooks_record(array('Fault' => array()), 'Invoice'), 'a refusal');

	pg_assert_same('7', pg_accounting_sync_quickbooks_first_id(array('QueryResponse' => array('Customer' => array(array('Id' => '7')))), 'Customer'), 'found');
	pg_assert_same('', pg_accounting_sync_quickbooks_first_id(array('QueryResponse' => array()), 'Customer'), 'none found');

	$failure = pg_accounting_sync_quickbooks_failure(array('status' => 429, 'error' => 'Too many', 'headers' => array()));
	pg_assert_same(60, $failure['retry_after'], 'a 429 waits a minute');
}

function test_accounting_sync_quickbooks_pause()
{
	pg_assert_same(0, pg_accounting_sync_quickbooks_pause(array(), 1000.0), 'the first call');
	pg_assert_same(0, pg_accounting_sync_quickbooks_pause(array(999.0), 1000.0), 'long enough since the last');

	$wait = pg_accounting_sync_quickbooks_pause(array(999.95), 1000.0);
	pg_assert_true(($wait > 60000) && ($wait <= 70000), 'the rest of the gap');

	$minute = array_fill(0, PG_ACCOUNTING_SYNC_QUICKBOOKS_MINUTE, 990.0);
	pg_assert_same(-1, pg_accounting_sync_quickbooks_pause($minute, 1000.0), 'the calls of the minute are made');
	pg_assert_same(0, pg_accounting_sync_quickbooks_pause($minute, 1051.0), 'a minute later');
}
