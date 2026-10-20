<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the Paraşüt e-document driver
 * (includes/erp/edoc/parasut.php): money and currency, what the buyer and
 * the document must carry, the bodies sent to Paraşüt, and the reading of
 * its status words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');

// A line without a product goes on the store's default product/service.
if (!defined('PARASUT_DEFAULT_PRODUCT_ID')) {
	define('PARASUT_DEFAULT_PRODUCT_ID', '9001');
}

function pg_test_parasut_invoice($overrides = array())
{
	return array_merge(array(
		'id' => 42,
		'direction' => 'sales',
		'doc_type' => 'invoice',
		'invoice_type' => 'SATIS',
		'full_number' => 'PGF2026000000042',
		'account_id' => 7,
		'order_id' => 0,
		'issue_date' => '2026-10-19',
		'due_date' => '2026-10-29',
		'currency' => 'TRY',
		'exchange_rate' => '1.000000',
		'subtotal' => 100000,
		'discount_total' => 10000,
		'tax_total' => 18000,
		'withholding_total' => 0,
		'grand_total' => 108000,
		'is_internet_sale' => 0,
		'payment_method' => '',
		'payment_date' => '0000-00-00',
		'shipment_date' => '0000-00-00',
		'carrier_title' => '',
		'carrier_vkn' => '',
		'web_address' => '',
		'edoc_scenario' => 'basic',
		'notes' => '',
	), $overrides);
}

function pg_test_parasut_party($overrides = array())
{
	return array_merge(array(
		'title' => 'Deneme Ticaret A.Ş.',
		'is_person' => false,
		'tax_number' => '1234567808',
		'tax_office' => 'Konak',
		'email' => 'muhasebe@example.com',
		'phone' => '0232 000 00 00',
		'address' => 'Cumhuriyet Bulvarı 1',
		'district' => 'Konak',
		'city' => 'İzmir',
		'country_code' => 'TR',
		'postcode' => '35210',
	), $overrides);
}

// Kurus to Paraşüt's decimals and back; the lira is TRL to Paraşüt.
function test_erp_edoc_parasut_money_and_currency()
{
	pg_assert_same(118.0, erp_edoc_parasut_money(11800));
	pg_assert_same(0.05, erp_edoc_parasut_money(5));
	pg_assert_same(11800, erp_edoc_parasut_kurus('118.0'));
	pg_assert_same(11800, erp_edoc_parasut_kurus('118,00'));
	pg_assert_same(123457, erp_edoc_parasut_kurus(1234.5678));
	pg_assert_same('TRL', erp_edoc_parasut_currency('TRY'));
	pg_assert_same('TRL', erp_edoc_parasut_currency(''));
	pg_assert_same('USD', erp_edoc_parasut_currency('usd'));
}

// One total is expected: the amount payable, net plus VAT less the VAT
// withheld (net_total is read after the withholding on the live API).
function test_erp_edoc_parasut_expected_totals()
{
	pg_assert_same(array(108000), erp_edoc_parasut_expected_totals(pg_test_parasut_invoice()));
	pg_assert_same(array(100800), erp_edoc_parasut_expected_totals(pg_test_parasut_invoice(array('withholding_total' => 7200, 'grand_total' => 100800))));
}

// A complete company card passes; a company needs its tax office; a wrong
// check digit is caught; the counter sale takes the final consumer's number.
function test_erp_edoc_parasut_party_missing()
{
	pg_assert_same(array(), erp_edoc_parasut_party_missing(pg_test_parasut_party()));
	pg_assert_same(array('tax_office'), array_keys(erp_edoc_parasut_party_missing(pg_test_parasut_party(array('tax_office' => '')))));
	pg_assert_same(array('tax_number'), array_keys(erp_edoc_parasut_party_missing(pg_test_parasut_party(array('tax_number' => '1234567802')))));
	pg_assert_same(array('address', 'city', 'district'), array_keys(erp_edoc_parasut_party_missing(pg_test_parasut_party(array('address' => '', 'city' => '', 'district' => '')))));

	// A person with no number is the final consumer; one word of a name is
	// enough for Paraşüt (no surname rule here).
	$person = pg_test_parasut_party(array('title' => 'Ayşe', 'is_person' => true, 'tax_number' => '', 'tax_office' => ''));
	pg_assert_same(array(), erp_edoc_parasut_party_missing($person));

	// A company with no number is a card not filled in.
	pg_assert_same(array('tax_number'), array_keys(erp_edoc_parasut_party_missing(pg_test_parasut_party(array('tax_number' => '')))));
}

// The internet sale needs the web address and the carrier on an e-Arşiv
// document, and on one whose kind is not known yet; a registered buyer's
// e-Fatura carries neither (the e_invoices request has no such block).
function test_erp_edoc_parasut_document_missing()
{
	pg_assert_same(array(), erp_edoc_parasut_document_missing(pg_test_parasut_invoice(), ''));
	pg_assert_same(array('web_address', 'carrier_title', 'carrier_vkn'), array_keys(erp_edoc_parasut_document_missing(pg_test_parasut_invoice(array('is_internet_sale' => 1)), '')));
	pg_assert_same(array('web_address', 'carrier_title', 'carrier_vkn'), array_keys(erp_edoc_parasut_document_missing(pg_test_parasut_invoice(array('is_internet_sale' => 1)), 'earchive')));
	pg_assert_same(array(), erp_edoc_parasut_document_missing(pg_test_parasut_invoice(array('is_internet_sale' => 1)), 'einvoice'), 'an e-Fatura has no internet-sale block');
	pg_assert_same(array(), erp_edoc_parasut_document_missing(pg_test_parasut_invoice(array(
		'is_internet_sale' => 1, 'web_address' => 'https://magaza.example.com', 'carrier_title' => 'Kargo A.Ş.', 'carrier_vkn' => '1234567808',
	)), 'earchive'));
}

// A company card and a person's card: one identity field, the type decides.
function test_erp_edoc_parasut_contact_attributes()
{
	$company = erp_edoc_parasut_contact_attributes(pg_test_parasut_party(), '1234567808');
	pg_assert_same('company', $company['contact_type']);
	pg_assert_same('customer', $company['account_type']);
	pg_assert_same('1234567808', $company['tax_number']);
	pg_assert_same('Konak', $company['tax_office']);
	pg_assert_same('İzmir', $company['city']);
	pg_assert_false($company['is_abroad']);
	pg_assert_same('muhasebe@example.com', $company['email']);

	// The postal code travels too: the account sync compares it, and a card
	// opened without it would differ from its account on that field alone.
	pg_assert_same('35210', $company['postal_code']);

	$person = erp_edoc_parasut_contact_attributes(pg_test_parasut_party(array('title' => 'Ayşe Yılmaz', 'is_person' => true, 'tax_office' => '', 'email' => '', 'phone' => '', 'country_code' => 'DE', 'postcode' => ' 10115 ')), '11111111111');
	pg_assert_same('person', $person['contact_type']);
	pg_assert_same('11111111111', $person['tax_number']);
	pg_assert_true($person['is_abroad']);
	pg_assert_false(isset($person['email']));
	pg_assert_same('10115', $person['postal_code']);

	// A buyer with no postal code is written without one.
	$none = erp_edoc_parasut_contact_attributes(pg_test_parasut_party(array('postcode' => '')), '1234567808');
	pg_assert_false(isset($none['postal_code']));
}

// The sales invoice body: lines with their rate or amount discounts, the
// product every line names, the currency and the dates.
function test_erp_edoc_parasut_invoice_payload_lines_and_header()
{
	$lines = array(
		array('product_id' => 0, 'description' => 'Hizmet', 'quantity' => '2.0000', 'unit_price' => 50000, 'discount_rate' => '10.000', 'discount_amount' => 10000, 'tax_rate' => '20.000', 'withholding_rate' => '0.000', 'withholding_code' => '', 'vat_exemption_code' => ''),
		array('product_id' => 0, 'description' => 'Kargo', 'quantity' => '1.0000', 'unit_price' => 2500, 'discount_rate' => '0.000', 'discount_amount' => 500, 'tax_rate' => '20.000', 'withholding_rate' => '0.000', 'withholding_code' => '', 'vat_exemption_code' => ''),
	);

	$payload = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(array('order_id' => 438, 'order_number' => '1172', 'order_date' => '2026-10-18')), $lines, '555');

	pg_assert_same('sales_invoices', $payload['data']['type']);
	pg_assert_same('invoice', $payload['data']['attributes']['item_type']);
	pg_assert_same('TRL', $payload['data']['attributes']['currency']);
	pg_assert_same('2026-10-19', $payload['data']['attributes']['issue_date']);
	pg_assert_same('2026-10-29', $payload['data']['attributes']['due_date']);

	// The order goes by the number its buyer knows, not by its row id.
	pg_assert_same('1172', $payload['data']['attributes']['order_no'], 'order number, not the row id');
	pg_assert_same('2026-10-18', $payload['data']['attributes']['order_date']);
	pg_assert_contains('1172', $payload['data']['attributes']['description']);
	pg_assert_false(strpos($payload['data']['attributes']['description'], '438') !== false, 'no row id in the description');
	pg_assert_false(isset($payload['data']['attributes']['exchange_rate']));
	pg_assert_false(isset($payload['data']['attributes']['vat_withholding_rate']));
	pg_assert_same('555', $payload['data']['relationships']['contact']['data']['id']);

	$details = $payload['data']['relationships']['details']['data'];
	pg_assert_same(2, count($details));
	pg_assert_same(2.0, $details[0]['attributes']['quantity']);
	pg_assert_same(500.0, $details[0]['attributes']['unit_price']);
	pg_assert_same(20.0, $details[0]['attributes']['vat_rate']);
	pg_assert_same('percentage', $details[0]['attributes']['discount_type']);
	pg_assert_same(10.0, $details[0]['attributes']['discount_value']);
	pg_assert_same('amount', $details[1]['attributes']['discount_type']);
	pg_assert_same(5.0, $details[1]['attributes']['discount_value']);
	pg_assert_same('9001', $details[1]['relationships']['product']['data']['id']);
	pg_assert_false(isset($details[0]['attributes']['vat_withholding_rate']));

	// A campaign discount and a typed one apply one after the other, which no
	// single rate says: the line goes with the amount they came to (99.99,
	// campaign 10% then 5% = 14.50; 5% alone would be 5.00).
	$two = array(array_merge($lines[0], array('quantity' => '3.0000', 'unit_price' => 3333, 'offer_discount_rate' => '10.000', 'discount_rate' => '5.000', 'discount_amount' => 1450)));
	$detail = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(), $two, '555')['data']['relationships']['details']['data'][0]['attributes'];
	pg_assert_same('amount', $detail['discount_type'], 'campaign and typed discount');
	pg_assert_same(14.5, $detail['discount_value']);
	$campaign = array(array_merge($two[0], array('discount_rate' => '0.000', 'discount_amount' => 1000)));
	$detail = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(), $campaign, '555')['data']['relationships']['details']['data'][0]['attributes'];
	pg_assert_same('amount', $detail['discount_type'], 'campaign discount alone');
	pg_assert_same(10.0, $detail['discount_value']);

	// The order number without its date would stop the conversion; neither
	// is sent then.
	$payload = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(array('order_id' => 438, 'order_number' => '1172', 'order_date' => '')), $lines, '555');
	pg_assert_false(isset($payload['data']['attributes']['order_no']));
	pg_assert_false(isset($payload['data']['attributes']['order_date']));

	// Without the order's number nothing names the order: its row id is no
	// reference the buyer could look up.
	$payload = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(array('order_id' => 438, 'order_date' => '2026-10-18')), $lines, '555');
	pg_assert_false(isset($payload['data']['attributes']['order_no']));
	pg_assert_same('PGF2026000000042', $payload['data']['attributes']['description']);

	// A foreign currency carries its rate.
	$payload = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(array('currency' => 'EUR', 'exchange_rate' => '48.250000')), array($lines[0]), '555');
	pg_assert_same('EUR', $payload['data']['attributes']['currency']);
	pg_assert_same(48.25, $payload['data']['attributes']['exchange_rate']);

	// Withholding travels on each line, so two rates share one document.
	$withheld = $lines;
	$withheld[0]['withholding_rate'] = '40.000';
	$withheld[1]['withholding_rate'] = '50.000';
	$payload = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(), $withheld, '555');
	pg_assert_false(isset($payload['error']));
	pg_assert_same(40.0, $payload['data']['relationships']['details']['data'][0]['attributes']['vat_withholding_rate']);
	pg_assert_same(50.0, $payload['data']['relationships']['details']['data'][1]['attributes']['vat_withholding_rate']);
	pg_assert_false(isset($payload['data']['attributes']['vat_withholding_rate']));
}

// The grand total Paraşüt computed is net_total ("Genel Toplam"); gross_total
// is the subtotal before VAT and is not compared.
function test_erp_edoc_parasut_total_check()
{
	$invoice = pg_test_parasut_invoice();

	pg_assert_same(array('ok' => true, 'total' => 108000), erp_edoc_parasut_total_check(array('gross_total' => '900.0', 'total_vat' => '180.0', 'net_total' => '1080.0'), $invoice));
	pg_assert_same(array('ok' => false, 'total' => 108001), erp_edoc_parasut_total_check(array('net_total' => '1080.01'), $invoice));
	pg_assert_same(array('ok' => false, 'total' => 0), erp_edoc_parasut_total_check(array('gross_total' => '1080.0'), $invoice));

	// With withholding net_total is the amount payable; a total that still
	// holds the withheld VAT means Paraşüt did not apply the withholding.
	$withheld = pg_test_parasut_invoice(array('withholding_total' => 7200, 'grand_total' => 100800));
	pg_assert_true(erp_edoc_parasut_total_check(array('net_total' => '1008.0'), $withheld)['ok']);
	pg_assert_false(erp_edoc_parasut_total_check(array('net_total' => '1080.0'), $withheld)['ok']);

	// The live tevkifat draft (601, 4/10): 79.95 + 15.99 VAT - 6.40 withheld.
	$live = pg_test_parasut_invoice(array('subtotal' => 7995, 'discount_total' => 0, 'tax_total' => 1599, 'withholding_total' => 640, 'grand_total' => 8954));
	pg_assert_true(erp_edoc_parasut_total_check(array('gross_total' => '79.95', 'before_taxes_total' => '79.95', 'total_vat' => '15.99', 'total_vat_withholding' => '6.4', 'net_total' => '89.54'), $live)['ok']);
}

// The line ids of a sales invoice, in the order it lists them.
function test_erp_edoc_parasut_detail_ids()
{
	$body = array('data' => array('id' => '77', 'relationships' => array('details' => array('data' => array(
		array('id' => '501', 'type' => 'sales_invoice_details'),
		array('id' => '502', 'type' => 'sales_invoice_details'),
	)))));

	pg_assert_same(array('501', '502'), erp_edoc_parasut_detail_ids($body));
	pg_assert_same(array(), erp_edoc_parasut_detail_ids(array('data' => array())));
}

// The e-document request: scenario and alias for e-Fatura; the internet
// sale block for an e-Arşiv invoice; one exemption, and each withheld line's
// code against Paraşüt's line id.
function test_erp_edoc_parasut_edoc_attributes()
{
	$lines = array(
		array('tax_rate' => '0.000', 'vat_exemption_code' => '301', 'withholding_rate' => '0.000', 'withholding_code' => ''),
		array('tax_rate' => '20.000', 'vat_exemption_code' => '', 'withholding_rate' => '40.000', 'withholding_code' => '601'),
		array('tax_rate' => '20.000', 'vat_exemption_code' => '', 'withholding_rate' => '90.000', 'withholding_code' => '612'),
	);

	$einvoice = erp_edoc_parasut_edoc_attributes(pg_test_parasut_invoice(array('edoc_scenario' => 'commercial', 'notes' => 'Teşekkürler')), $lines, 'einvoice', 'urn:mail:defaultpk@example.com', array('501', '502', '503'));
	pg_assert_same('commercial', $einvoice['scenario']);
	pg_assert_same('urn:mail:defaultpk@example.com', $einvoice['to']);
	pg_assert_same('301', $einvoice['vat_exemption_reason_code']);
	pg_assert_false(isset($einvoice['vat_exemption_reason']));
	pg_assert_same(array(
		array('detail_id' => 502, 'vat_withholding_code' => '601'),
		array('detail_id' => 503, 'vat_withholding_code' => '612'),
	), $einvoice['vat_withholding_params']);
	pg_assert_false(isset($einvoice['note']));
	pg_assert_false(isset($einvoice['internet_sale']));

	// Without Paraşüt's line ids a withheld line cannot be named.
	pg_assert_true(isset(erp_edoc_parasut_edoc_attributes(pg_test_parasut_invoice(), $lines, 'earchive', '')['error']));

	$earchive = erp_edoc_parasut_edoc_attributes(pg_test_parasut_invoice(array(
		'is_internet_sale' => 1, 'web_address' => 'https://magaza.example.com', 'payment_method' => 'KREDIKARTI/BANKAKARTI',
		'payment_date' => '2026-10-18', 'shipment_date' => '2026-10-19', 'carrier_title' => 'Kargo A.Ş.', 'carrier_vkn' => '1234567808',
	)), array(), 'earchive', '');
	pg_assert_false(isset($earchive['scenario']));
	pg_assert_same('https://magaza.example.com', $earchive['internet_sale']['url']);
	pg_assert_same('KREDIKARTI/BANKAKARTI', $earchive['internet_sale']['payment_type']);
	pg_assert_same('2026-10-18', $earchive['internet_sale']['payment_date']);
	pg_assert_same('Kargo A.Ş.', $earchive['shipment']['title']);
	pg_assert_same('1234567808', $earchive['shipment']['vkn']);
	pg_assert_same('2026-10-19', $earchive['shipment']['date']);
	pg_assert_false(isset($earchive['shipment']['tckn']));

	// An individual carrier travels with a TCKN; an unknown payment method
	// is "other".
	$earchive = erp_edoc_parasut_edoc_attributes(pg_test_parasut_invoice(array(
		'is_internet_sale' => 1, 'web_address' => 'https://magaza.example.com', 'payment_method' => 'nakit',
		'carrier_title' => 'Ali Veli', 'carrier_vkn' => '10000000146',
	)), array(), 'earchive', '');
	pg_assert_same('DIGER', $earchive['internet_sale']['payment_type']);
	pg_assert_same('10000000146', $earchive['shipment']['tckn']);
	pg_assert_same('2026-10-19', $earchive['internet_sale']['payment_date']);

	// Two exemption codes cannot be named on one document.
	$mixed = array(
		array('tax_rate' => '0.000', 'vat_exemption_code' => '301', 'withholding_rate' => '0.000', 'withholding_code' => ''),
		array('tax_rate' => '0.000', 'vat_exemption_code' => '350', 'withholding_rate' => '0.000', 'withholding_code' => ''),
	);
	pg_assert_true(isset(erp_edoc_parasut_edoc_attributes(pg_test_parasut_invoice(), $mixed, 'earchive', '')['error']));

	// "Other" exemptions need the reason in words, taken from the note.
	$other = array(array('tax_rate' => '0.000', 'vat_exemption_code' => '350', 'withholding_rate' => '0.000', 'withholding_code' => ''));
	pg_assert_true(isset(erp_edoc_parasut_edoc_attributes(pg_test_parasut_invoice(), $other, 'earchive', '')['error']));
	$with_reason = erp_edoc_parasut_edoc_attributes(pg_test_parasut_invoice(array('notes' => 'KDVK 17/4-s')), $other, 'earchive', '');
	pg_assert_same('350', $with_reason['vat_exemption_reason_code']);
	pg_assert_same('KDVK 17/4-s', $with_reason['vat_exemption_reason']);
}

// What an e-document's own attributes say: a signed e-Archive invoice is
// issued whatever its delivery word; an e-Invoice by status and the buyer's
// answer.
function test_erp_edoc_parasut_edoc_state()
{
	pg_assert_same('accepted', erp_edoc_parasut_edoc_state('e_archives', array('status' => 'sent', 'is_signed' => true))['status']);
	pg_assert_same('accepted', erp_edoc_parasut_edoc_state('e_archives', array('status' => 'legalized'))['status']);
	pg_assert_same('accepted', erp_edoc_parasut_edoc_state('e_archives', array('status' => 'bounced'))['status']);
	pg_assert_contains('came back', erp_edoc_parasut_edoc_state('e_archives', array('status' => 'bounced'))['message']);
	pg_assert_same('sent', erp_edoc_parasut_edoc_state('e_archives', array('status' => '', 'is_signed' => false))['status']);

	pg_assert_same('sent', erp_edoc_parasut_edoc_state('e_invoices', array('status' => 'waiting'))['status']);
	pg_assert_same('accepted', erp_edoc_parasut_edoc_state('e_invoices', array('status' => 'successful'))['status']);
	pg_assert_same('error', erp_edoc_parasut_edoc_state('e_invoices', array('status' => 'failed'))['status']);
	pg_assert_same('rejected', erp_edoc_parasut_edoc_state('e_invoices', array('status' => 'successful', 'response_type' => 'rejected'))['status']);
	pg_assert_contains('return', erp_edoc_parasut_edoc_state('e_invoices', array('status' => 'successful', 'response_type' => 'refunded'))['message']);
}

// GİB's cancellation window of an e-Archive invoice.
function test_erp_edoc_parasut_cancel_window_open()
{
	$now = strtotime('2026-10-19 12:00:00');

	pg_assert_true(erp_edoc_parasut_cancel_window_open(array(), $now));
	pg_assert_true(erp_edoc_parasut_cancel_window_open(array('cancellable_until' => '2026-10-26T23:59:59+03:00'), $now));
	pg_assert_false(erp_edoc_parasut_cancel_window_open(array('cancellable_until' => '2026-10-18T23:59:59+03:00'), $now));
}

// Paraşüt's status words, read by their stems; the unknown counts as sent.
function test_erp_edoc_parasut_document_state()
{
	pg_assert_same('sent', erp_edoc_parasut_document_state(''));
	pg_assert_same('sent', erp_edoc_parasut_document_state('waiting'));
	pg_assert_same('sent', erp_edoc_parasut_document_state('bekliyor'));
	pg_assert_same('accepted', erp_edoc_parasut_document_state('successful'));
	pg_assert_same('accepted', erp_edoc_parasut_document_state('Kabul edildi'));
	pg_assert_same('rejected', erp_edoc_parasut_document_state('rejected'));
	pg_assert_same('rejected', erp_edoc_parasut_document_state('Reddedildi'));
	pg_assert_same('cancelled', erp_edoc_parasut_document_state('cancelled'));
	pg_assert_same('cancelled', erp_edoc_parasut_document_state('İptal edildi'));
	pg_assert_same('error', erp_edoc_parasut_document_state('failed'));
	pg_assert_same('error', erp_edoc_parasut_document_state('Hata'));
}

// The refusal text: JSON:API errors, the OAuth description, the transport
// error, or the code alone.
function test_erp_edoc_parasut_error_text()
{
	pg_assert_same('Geçersiz: tax_number boş olamaz', erp_edoc_parasut_error_text(array('http_code' => 422, 'body' => array('errors' => array(array('title' => 'Geçersiz', 'detail' => 'tax_number boş olamaz'))))));
	// The live API repeats the detail as the title; it is said once.
	pg_assert_same('Bu firmaya ait aktif bir e-Arşiv entegrasyonu bulunmamaktadır.', erp_edoc_parasut_error_text(array('http_code' => 422, 'body' => array('errors' => array(array('source' => array('pointer' => '/data/attributes/base'), 'title' => 'Bu firmaya ait aktif bir e-Arşiv entegrasyonu bulunmamaktadır.', 'detail' => 'Bu firmaya ait aktif bir e-Arşiv entegrasyonu bulunmamaktadır.'))))));
	pg_assert_same('KDV oranı (18.0) artık kullanılamaz.', erp_edoc_parasut_error_text(array('http_code' => 422, 'body' => array('errors' => array(array('detail' => 'KDV oranı (18.0) artık kullanılamaz.'))))));
	pg_assert_same('invalid_grant: expired', erp_edoc_parasut_error_text(array('http_code' => 401, 'body' => array('error' => 'invalid_grant', 'error_description' => 'invalid_grant: expired'))));
	pg_assert_same('curl: timed out', erp_edoc_parasut_error_text(array('http_code' => 0, 'error' => 'curl: timed out', 'body' => null)));
	pg_assert_same('HTTP 500', erp_edoc_parasut_error_text(array('http_code' => 500, 'body' => null)));
	pg_assert_same('no token', erp_edoc_parasut_error_text(array('login_error' => 'no token')));

	pg_assert_true(erp_edoc_parasut_ok(array('http_code' => 204, 'raw' => '', 'body' => null, 'error' => '')));
	pg_assert_true(erp_edoc_parasut_ok(array('http_code' => 201, 'raw' => '{"data":{}}', 'body' => array('data' => array()), 'error' => '')));
	pg_assert_false(erp_edoc_parasut_ok(array('http_code' => 200, 'raw' => '<html>', 'body' => null, 'error' => '')));
	pg_assert_false(erp_edoc_parasut_ok(array('http_code' => 422, 'raw' => '{}', 'body' => array(), 'error' => '')));
}

// What a document may be taken back for, from the row alone.
function test_erp_edoc_parasut_cancellable()
{
	$draft = erp_edoc_parasut_cancellable(array('edoc_status' => 'created', 'edoc_external_id' => '77', 'edoc_kind' => 'none'));
	pg_assert_true($draft['ok']);
	pg_assert_false($draft['manual']);

	$archive = erp_edoc_parasut_cancellable(array('edoc_status' => 'accepted', 'edoc_external_id' => '77', 'edoc_kind' => 'earchive'));
	pg_assert_true($archive['ok']);
	pg_assert_false($archive['manual']);

	$einvoice = erp_edoc_parasut_cancellable(array('edoc_status' => 'accepted', 'edoc_external_id' => '77', 'edoc_kind' => 'einvoice'));
	pg_assert_false($einvoice['ok']);
}

// The totals of a sales invoice as Paraşüt answers them, in kurus.
function test_erp_edoc_parasut_totals()
{
	pg_assert_same(array(), erp_edoc_parasut_totals(array('gross_total' => '79.95')));
	pg_assert_same(
		array('before_taxes' => 7995, 'vat' => 1599, 'vat_withholding' => 640, 'net' => 8954),
		erp_edoc_parasut_totals(array('gross_total' => '79.95', 'before_taxes_total' => '79.95', 'total_vat' => '15.99', 'total_vat_withholding' => '6.4', 'net_total' => '89.54'))
	);
	pg_assert_same(7995, erp_edoc_parasut_totals(array('gross_total' => '79.95', 'net_total' => '95.94'))['before_taxes']);
}

// A status question on an invoice with no e-document yet: a draft that was
// never handed to GİB is not told to hand it over "again".
function test_erp_edoc_parasut_unconverted_message()
{
	$draft = erp_edoc_parasut_unconverted_message(pg_test_parasut_invoice(array('edoc_status' => 'created', 'edoc_kind' => 'none', 'parasut_job_id' => '')));
	pg_assert_contains('waiting as a draft at Paraşüt', $draft, 'never handed over');
	pg_assert_false(strpos($draft, 'again') !== false, 'a draft is not handed over again');

	$handed_over = array(
		'marked sent' => array('edoc_status' => 'sent'),
		'job kept' => array('edoc_status' => 'created', 'parasut_job_id' => '1234'),
		'kind decided' => array('edoc_status' => 'created', 'edoc_kind' => 'earchive'),
	);

	foreach ($handed_over as $label => $row) {
		pg_assert_contains('hand it to GİB again', erp_edoc_parasut_unconverted_message(pg_test_parasut_invoice($row)), $label);
	}
}

// An e_invoice_inboxes answer: every mailbox once, and the registered name
// from the first entry that carries one.
function test_erp_edoc_parasut_inboxes_read()
{
	pg_assert_same(array('aliases' => array(), 'title' => ''), erp_edoc_parasut_inboxes_read(array('data' => array())));
	pg_assert_same(array('aliases' => array(), 'title' => ''), erp_edoc_parasut_inboxes_read(null));
	pg_assert_same(array('aliases' => array(), 'title' => ''), erp_edoc_parasut_inboxes_read(array('data' => 'x')));

	$read = erp_edoc_parasut_inboxes_read(array('data' => array(
		array('id' => '1', 'type' => 'e_invoice_inboxes', 'attributes' => array('vkn' => '1234567890', 'e_invoice_address' => 'urn:mail:defaultpk@ornek.com.tr', 'name' => '  ÖRNEK   YAZILIM A.Ş. ', 'inbox_type' => 'PK')),
		array('id' => '2', 'type' => 'e_invoice_inboxes', 'attributes' => array('vkn' => '1234567890', 'e_invoice_address' => 'urn:mail:faturapk@ornek.com.tr', 'name' => 'ÖRNEK YAZILIM A.Ş.')),
		array('id' => '3', 'type' => 'e_invoice_inboxes', 'attributes' => array('vkn' => '1234567890', 'e_invoice_address' => 'urn:mail:defaultpk@ornek.com.tr')),
		'not an entry',
	)));

	pg_assert_same(array('urn:mail:defaultpk@ornek.com.tr', 'urn:mail:faturapk@ornek.com.tr'), $read['aliases']);
	pg_assert_same('ÖRNEK YAZILIM A.Ş.', $read['title']);

	// A mailbox without a name still counts; the name may come later.
	$read = erp_edoc_parasut_inboxes_read(array('data' => array(
		array('attributes' => array('e_invoice_address' => 'urn:mail:a@b.com', 'name' => '')),
		array('attributes' => array('e_invoice_address' => 'urn:mail:a@b.com', 'name' => 'B LTD. ŞTİ.')),
	)));
	pg_assert_same(array('urn:mail:a@b.com'), $read['aliases']);
	pg_assert_same('B LTD. ŞTİ.', $read['title']);
}

// The operator's sentence about a tax number, the registered name included
// when the provider gave one.
function test_erp_edoc_taxpayer_message()
{
	$user = erp_edoc_taxpayer_message('1234567890', array('is_einvoice_user' => true, 'aliases' => array('urn:mail:a@b.com', ''), 'title' => 'ÖRNEK A.Ş.'));
	pg_assert_contains('1234567890 is registered for e-Invoice at GİB (urn:mail:a@b.com).', $user);
	pg_assert_contains('“ÖRNEK A.Ş.”', $user);

	$plain = erp_edoc_taxpayer_message('1234567890', array('is_einvoice_user' => true, 'aliases' => array()));
	pg_assert_contains('is registered for e-Invoice at GİB.', $plain);
	pg_assert_same(false, strpos($plain, '“'));

	$not = erp_edoc_taxpayer_message('12345678901', array('is_einvoice_user' => false));
	pg_assert_contains('is not registered for e-Invoice', $not);

	pg_assert_contains('e-Delivery notes', erp_edoc_taxpayer_message('1234567890', array('is_einvoice_user' => true, 'is_edispatch_user' => true)));
}

// Paraşüt takes 0, 1, 10 and 20 percent; any other rate is refused before
// anything is sent, each refused rate named once.
function test_erp_edoc_parasut_vat_rate_refusal()
{
	pg_assert_same('', erp_edoc_parasut_vat_rate_refusal(array(array('tax_rate' => 20), array('tax_rate' => '10.000'), array('tax_rate' => 0), array('tax_rate' => 1))));
	pg_assert_same('', erp_edoc_parasut_vat_rate_refusal(array()));

	$refusal = erp_edoc_parasut_vat_rate_refusal(array(array('tax_rate' => 18), array('tax_rate' => 20), array('tax_rate' => '18.000'), array('tax_rate' => 8)));
	pg_assert_contains('18%', $refusal);
	pg_assert_contains('8%', $refusal);
	pg_assert_same(1, substr_count($refusal, '18%'));
	pg_assert_contains('0%, 1%, 10%, 20%', $refusal);
}

// A line without a Pinegrap product goes on the default product/service;
// with none set the invoice is refused before anything reaches Paraşüt.
function test_erp_edoc_parasut_product_refusal()
{
	$product = array('product_id' => 543, 'description' => 'Deri Kalem Kılıfı');
	$free = array('product_id' => 0, 'description' => 'Deneme hizmeti');

	pg_assert_same('', erp_edoc_parasut_product_refusal(array($product, $free), '9001'));
	pg_assert_same('', erp_edoc_parasut_product_refusal(array($product), ''));
	pg_assert_same('', erp_edoc_parasut_product_refusal(array(), ''));
	pg_assert_contains('"Deneme hizmeti"', erp_edoc_parasut_product_refusal(array($product, $free), ' '));

	// Without a default given, the setting answers (the test sets one).
	pg_assert_same('', erp_edoc_parasut_product_refusal(array($free)));
	pg_assert_same(erp_edoc_parasut_no_product_text($free), erp_edoc_parasut_product_refusal(array($free), ''));
}

// The driver hands over its customer and supplier cards: the account sync
// screens ask for the capability and the three operations.
function test_erp_edoc_parasut_offers_its_cards()
{
	pg_assert_true(in_array('accounts', erp_edoc_parasut_info()['capabilities'], true));

	foreach (array('accounts', 'account', 'account_save') as $operation) {
		pg_assert_true(function_exists('erp_edoc_parasut_' . $operation), $operation);
	}
}

// A contact in the card shape: one name, the type from contact_type, the
// contact's id as its code, a mistyped number left mistyped, no balance.
function test_erp_edoc_parasut_card()
{
	$company = erp_edoc_parasut_card(array(
		'id' => '7001',
		'type' => 'contacts',
		'attributes' => array(
			'name' => 'Pg  Tevkifat Test A.Ş.',
			'contact_type' => 'company',
			'account_type' => 'customer',
			'tax_number' => '1234567808',
			'tax_office' => 'Konak',
			'email' => 'muhasebe@example.com',
			'phone' => '0232 000 00 00',
			'address' => 'Cumhuriyet Bulvarı 1',
			'district' => 'Konak',
			'city' => 'İzmir',
			'postal_code' => '35210',
			'country' => null,
			'is_abroad' => false,
			'archived' => false,
			'balance' => '1180.0',
			'updated_at' => '2026-10-19T17:52:44.000+03:00',
		),
	));

	pg_assert_same('7001', $company['external_id']);
	pg_assert_same('7001', $company['code']);
	pg_assert_same('Pg Tevkifat Test A.Ş.', $company['title']);
	pg_assert_false($company['is_person']);
	pg_assert_same('1234567808', $company['tax_number']);
	pg_assert_same('Konak', $company['tax_office']);
	pg_assert_same('35210', $company['postcode']);
	pg_assert_same('TR', $company['country_code']);
	pg_assert_same('customer', $company['kind']);
	pg_assert_true($company['is_active']);
	pg_assert_same(strtotime('2026-10-19T17:52:44.000+03:00'), $company['updated_at']);
	pg_assert_false(isset($company['balance']));

	$person = erp_edoc_parasut_card(array('id' => '7002', 'attributes' => array(
		'name' => 'Ayşe Yılmaz', 'contact_type' => 'person', 'account_type' => 'supplier',
		'tax_number' => '1234567890ab', 'country' => 'Türkiye', 'is_abroad' => false, 'archived' => true,
	)));

	pg_assert_true($person['is_person']);
	pg_assert_same('1234567890AB', $person['tax_number']);
	pg_assert_same('supplier', $person['kind']);
	pg_assert_false($person['is_active']);
	pg_assert_same('TR', $person['country_code']);
	pg_assert_same(0, $person['updated_at']);
}

// Turkey however a card spells it; no country on a domestic card is Turkey,
// on a foreign one it is not known.
function test_erp_edoc_parasut_country_code()
{
	foreach (array('Türkiye', 'TÜRKİYE', 'Turkey', 'turkiye', 'TURKIYE', 'TR', ' Türkiye  Cumhuriyeti ') as $name) {
		pg_assert_same('TR', erp_edoc_parasut_country_code($name, false), $name);
	}

	pg_assert_same('TR', erp_edoc_parasut_country_code('', false));
	pg_assert_same('', erp_edoc_parasut_country_code('', true));
	pg_assert_same('DE', erp_edoc_parasut_country_code('de', true));
	pg_assert_same('Türkiye', erp_edoc_parasut_country_name('TR'));
	pg_assert_same('Türkiye', erp_edoc_parasut_country_name(''));
}

// A page of the list: the cards and the counts Paraşüt gives in meta, or
// the page's own count when it gives none.
function test_erp_edoc_parasut_contacts_read()
{
	$page = erp_edoc_parasut_contacts_read(array(
		'data' => array(
			array('id' => '1', 'type' => 'contacts', 'attributes' => array('name' => 'A')),
			array('id' => '', 'type' => 'contacts', 'attributes' => array('name' => 'No id')),
			array('id' => '2', 'type' => 'contacts', 'attributes' => array('name' => 'B')),
		),
		'meta' => array('current_page' => 1, 'total_pages' => 3, 'total_count' => 52),
	), 25);

	pg_assert_same(2, count($page['items']));
	pg_assert_same('B', $page['items'][1]['title']);
	pg_assert_same(52, $page['total']);
	pg_assert_same(3, $page['pages']);

	$empty = erp_edoc_parasut_contacts_read(array('data' => array(), 'meta' => array('total_pages' => 0, 'total_count' => 0)), 25);
	pg_assert_same(array(), $empty['items']);
	pg_assert_same(0, $empty['total']);
	pg_assert_same(1, $empty['pages']);

	$no_meta = erp_edoc_parasut_contacts_read(array('data' => array(array('id' => '9', 'attributes' => array('name' => 'C')))), 25);
	pg_assert_same(1, $no_meta['total']);
	pg_assert_same(1, $no_meta['pages']);
	pg_assert_same(1, erp_edoc_parasut_contacts_read(null, 25)['pages']);
}

// A read goes through the listed cards and the archived ones, which
// Paraşüt's list leaves out and filter[archived]=true lists alone.
function test_erp_edoc_parasut_contact_lists()
{
	$lists = erp_edoc_parasut_contact_lists();

	pg_assert_same(array('listed', 'archived'), array_keys($lists));
	pg_assert_same('', $lists['listed']);
	pg_assert_same('filter[archived]=true&', $lists['archived']);
}

// A page of a read holds the same page of each list: the cards of both,
// both counts, the pages of the longer list. The archived list adds only
// archived cards, so an unfiltered answer brings no card twice.
function test_erp_edoc_parasut_contacts_merge()
{
	$listed = erp_edoc_parasut_contacts_read(array(
		'data' => array(
			array('id' => '1', 'attributes' => array('name' => 'A', 'archived' => false)),
			array('id' => '2', 'attributes' => array('name' => 'B', 'archived' => false)),
		),
		'meta' => array('total_pages' => 3, 'total_count' => 52),
	), 25);
	$archived = erp_edoc_parasut_contacts_read(array(
		'data' => array(array('id' => '9', 'attributes' => array('name' => 'Z', 'archived' => true))),
		'meta' => array('total_pages' => 1, 'total_count' => 1),
	), 25);

	$page = erp_edoc_parasut_contacts_merge(array('listed' => $listed, 'archived' => $archived));

	pg_assert_same(array('1', '2', '9'), array_column($page['items'], 'external_id'));
	pg_assert_true($page['items'][0]['is_active']);
	pg_assert_false($page['items'][2]['is_active']);
	pg_assert_same(53, $page['total']);
	pg_assert_same(3, $page['pages']);

	// Past its last page a list adds no card and is still counted.
	$later = erp_edoc_parasut_contacts_merge(array('listed' => $listed, 'archived' => array('items' => array(), 'total' => 1, 'pages' => 1)));
	pg_assert_same(array('1', '2'), array_column($later['items'], 'external_id'));
	pg_assert_same(53, $later['total']);
	pg_assert_same(3, $later['pages']);

	$long_archive = erp_edoc_parasut_contacts_merge(array(
		'listed' => array('items' => array(), 'total' => 3, 'pages' => 1),
		'archived' => array('items' => array(), 'total' => 60, 'pages' => 3),
	));
	pg_assert_same(63, $long_archive['total']);
	pg_assert_same(3, $long_archive['pages']);

	$unfiltered = erp_edoc_parasut_contacts_merge(array('listed' => $listed, 'archived' => $listed));
	pg_assert_same(array('1', '2'), array_column($unfiltered['items'], 'external_id'));

	pg_assert_same(array('items' => array(), 'total' => 0, 'pages' => 1), erp_edoc_parasut_contacts_merge(array()));
}

// A change is written on the card as Paraşüt keeps it: the given fields
// change, every other writable attribute goes back as it came, the balances
// and dates never go.
function test_erp_edoc_parasut_card_attributes_change()
{
	$current = array(
		'balance' => '1180.0', 'trl_balance' => '1180.0', 'created_at' => '2026-10-19', 'updated_at' => '2026-10-19',
		'name' => 'Pg Tevkifat Test A.Ş.', 'short_name' => 'Pg Tevkifat Test A.Ş.', 'contact_type' => 'company', 'account_type' => 'customer',
		'email' => 'eski@example.com', 'tax_office' => 'Konak', 'tax_number' => '1234567808', 'district' => 'Konak', 'city' => 'İzmir',
		'postal_code' => null, 'country' => null, 'address' => 'Cumhuriyet Bulvarı 1', 'phone' => '', 'fax' => null, 'iban' => 'TR00 0000',
		'is_abroad' => false, 'archived' => false, 'untrackable' => false, 'invoicing_preferences' => array(),
	);

	$email = erp_edoc_parasut_card_attributes(array('email' => ' yeni@example.com '), $current);
	pg_assert_same('yeni@example.com', $email['email']);
	pg_assert_same('Pg Tevkifat Test A.Ş.', $email['name']);
	pg_assert_same('customer', $email['account_type']);
	pg_assert_same('1234567808', $email['tax_number']);
	pg_assert_same('TR00 0000', $email['iban']);
	pg_assert_same('', $email['phone']);
	pg_assert_false($email['untrackable']);

	foreach (array('balance', 'trl_balance', 'created_at', 'updated_at', 'postal_code', 'country', 'fax', 'invoicing_preferences') as $key) {
		pg_assert_false(array_key_exists($key, $email), $key);
	}

	// The short name that mirrored the name follows it; the type travels
	// with the name it applies to.
	$renamed = erp_edoc_parasut_card_attributes(array('title' => 'Ayşe  Yılmaz', 'is_person' => true), $current);
	pg_assert_same('Ayşe Yılmaz', $renamed['name']);
	pg_assert_same('Ayşe Yılmaz', $renamed['short_name']);
	pg_assert_same('person', $renamed['contact_type']);

	$own_short = erp_edoc_parasut_card_attributes(array('title' => 'Yeni Unvan A.Ş.'), array_merge($current, array('short_name' => 'Pg Tevkifat')));
	pg_assert_same('Pg Tevkifat', $own_short['short_name']);

	// A field emptied on purpose goes empty; a Turkish number goes as digits.
	$cleared = erp_edoc_parasut_card_attributes(array('email' => '', 'tax_number' => '123 456 78 08', 'is_active' => false, 'kind' => 'supplier'), $current);
	pg_assert_same('', $cleared['email']);
	pg_assert_same('1234567808', $cleared['tax_number']);
	pg_assert_true($cleared['archived']);
	pg_assert_same('supplier', $cleared['account_type']);

	pg_assert_same(array('error' => 'Enter a name.'), erp_edoc_parasut_card_attributes(array('title' => '  '), $current));
}

// A new card from an ERP account: name and type, a Turkish number as
// digits, empty fields left out, an account that is both as a customer,
// the ERP's code and e-Invoice answer not sent.
function test_erp_edoc_parasut_card_attributes_create()
{
	$attributes = erp_edoc_parasut_card_attributes(array(
		'title' => 'Pg Paraşüt Testci', 'is_person' => true, 'tax_number' => '1234567890', 'tax_office' => '',
		'email' => 'testci@example.com', 'phone' => '', 'address' => 'Atatürk Cd. 5', 'district' => 'Kadıköy', 'city' => 'İstanbul',
		'postcode' => '34710', 'country_code' => 'TR', 'kind' => 'both', 'is_active' => true,
		'code' => 'PG-2', 'einvoice_user' => false, 'einvoice_alias' => '',
	), null);

	pg_assert_same('Pg Paraşüt Testci', $attributes['name']);
	pg_assert_same('person', $attributes['contact_type']);
	pg_assert_same('customer', $attributes['account_type']);
	pg_assert_same('1234567890', $attributes['tax_number']);
	pg_assert_same('testci@example.com', $attributes['email']);
	pg_assert_same('34710', $attributes['postal_code']);
	pg_assert_same('Türkiye', $attributes['country']);
	pg_assert_false($attributes['is_abroad']);
	pg_assert_false($attributes['archived']);

	foreach (array('tax_office', 'phone', 'code', 'einvoice_user', 'einvoice_alias', 'short_name') as $key) {
		pg_assert_false(array_key_exists($key, $attributes), $key);
	}

	$supplier = erp_edoc_parasut_card_attributes(array('title' => 'Tedarik Ltd.', 'kind' => 'supplier'), null);
	pg_assert_same('supplier', $supplier['account_type']);
	pg_assert_same('company', $supplier['contact_type']);
}

// The linked card carries the invoice while it is the one asked for, not
// archived and on the buyer's number.
function test_erp_edoc_parasut_linked_contact_usable()
{
	$contact = array('id' => '7001', 'attributes' => array('tax_number' => '1234567808', 'archived' => false));

	pg_assert_true(erp_edoc_parasut_linked_contact_usable($contact, '7001', '1234567808'));
	pg_assert_false(erp_edoc_parasut_linked_contact_usable($contact, '7001', '1111111111'));
	pg_assert_false(erp_edoc_parasut_linked_contact_usable($contact, '7002', '1234567808'));
	pg_assert_false(erp_edoc_parasut_linked_contact_usable(array('id' => '7001', 'attributes' => array('tax_number' => '1234567808', 'archived' => true)), '7001', '1234567808'));
	pg_assert_false(erp_edoc_parasut_linked_contact_usable(null, '7001', '1234567808'));
	pg_assert_false(erp_edoc_parasut_linked_contact_usable($contact, '7001', ''));
}
