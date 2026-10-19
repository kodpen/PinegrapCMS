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

// Without withholding one total is expected; with it two readings, net
// plus VAT first and the amount payable second.
function test_erp_edoc_parasut_expected_totals()
{
	pg_assert_same(array(108000), erp_edoc_parasut_expected_totals(pg_test_parasut_invoice()));
	pg_assert_same(array(108000, 100800), erp_edoc_parasut_expected_totals(pg_test_parasut_invoice(array('withholding_total' => 7200, 'grand_total' => 100800))));
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

// The internet sale needs the web address and the carrier on the invoice.
function test_erp_edoc_parasut_document_missing()
{
	pg_assert_same(array(), erp_edoc_parasut_document_missing(pg_test_parasut_invoice()));
	pg_assert_same(array('web_address', 'carrier_title', 'carrier_vkn'), array_keys(erp_edoc_parasut_document_missing(pg_test_parasut_invoice(array('is_internet_sale' => 1)))));
	pg_assert_same(array(), erp_edoc_parasut_document_missing(pg_test_parasut_invoice(array(
		'is_internet_sale' => 1, 'web_address' => 'https://magaza.example.com', 'carrier_title' => 'Kargo A.Ş.', 'carrier_vkn' => '1234567808',
	))));
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

	$person = erp_edoc_parasut_contact_attributes(pg_test_parasut_party(array('title' => 'Ayşe Yılmaz', 'is_person' => true, 'tax_office' => '', 'email' => '', 'phone' => '', 'country_code' => 'DE')), '11111111111');
	pg_assert_same('person', $person['contact_type']);
	pg_assert_same('11111111111', $person['tax_number']);
	pg_assert_true($person['is_abroad']);
	pg_assert_false(isset($person['email']));
}

// The sales invoice body: lines with their rate or amount discounts, the
// product every line names, the currency and the dates.
function test_erp_edoc_parasut_invoice_payload_lines_and_header()
{
	$lines = array(
		array('product_id' => 0, 'description' => 'Hizmet', 'quantity' => '2.0000', 'unit_price' => 50000, 'discount_rate' => '10.000', 'discount_amount' => 10000, 'tax_rate' => '20.000', 'withholding_rate' => '0.000', 'withholding_code' => '', 'vat_exemption_code' => ''),
		array('product_id' => 0, 'description' => 'Kargo', 'quantity' => '1.0000', 'unit_price' => 2500, 'discount_rate' => '0.000', 'discount_amount' => 500, 'tax_rate' => '20.000', 'withholding_rate' => '0.000', 'withholding_code' => '', 'vat_exemption_code' => ''),
	);

	$payload = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(array('order_id' => 1172, 'order_date' => '2026-10-18')), $lines, '555');

	pg_assert_same('sales_invoices', $payload['data']['type']);
	pg_assert_same('invoice', $payload['data']['attributes']['item_type']);
	pg_assert_same('TRL', $payload['data']['attributes']['currency']);
	pg_assert_same('2026-10-19', $payload['data']['attributes']['issue_date']);
	pg_assert_same('2026-10-29', $payload['data']['attributes']['due_date']);
	pg_assert_same('1172', $payload['data']['attributes']['order_no']);
	pg_assert_same('2026-10-18', $payload['data']['attributes']['order_date']);
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

	// The order number without its date would stop the conversion; neither
	// is sent then.
	$payload = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(array('order_id' => 1172, 'order_date' => '')), $lines, '555');
	pg_assert_false(isset($payload['data']['attributes']['order_no']));
	pg_assert_false(isset($payload['data']['attributes']['order_date']));

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

	// With withholding either reading is taken until the live answer settles it.
	$withheld = pg_test_parasut_invoice(array('withholding_total' => 7200, 'grand_total' => 100800));
	pg_assert_true(erp_edoc_parasut_total_check(array('net_total' => '1080.0'), $withheld)['ok']);
	pg_assert_true(erp_edoc_parasut_total_check(array('net_total' => '1008.0'), $withheld)['ok']);
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
