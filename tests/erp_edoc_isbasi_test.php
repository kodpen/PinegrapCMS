<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the İşbaşı e-document driver
 * (includes/erp/edoc/isbasi.php): how a line's discount is sent, so that
 * İşbaşı works the line out to the ERP's figure, and how the invoice's
 * description names the order.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/isbasi.php');

// A line with a campaign discount goes with the amount of both discounts
// (the ERP takes the campaign first and the typed rate from what is left);
// a line whose only discount is the typed rate goes with the rate, as
// before; a line without one with neither.
function test_erp_edoc_isbasi_line_discount()
{
	pg_assert_same(array('rate' => 0.0, 'value' => 145.0), erp_edoc_isbasi_line_discount(array('discount_rate' => 5, 'offer_discount_rate' => 10, 'discount_amount' => 14500)), 'campaign and typed discount: the amount');
	pg_assert_same(array('rate' => 0.0, 'value' => 100.0), erp_edoc_isbasi_line_discount(array('discount_rate' => 0, 'offer_discount_rate' => 10, 'discount_amount' => 10000)), 'campaign alone: the amount');
	pg_assert_same(array('rate' => 5.0, 'value' => 0.0), erp_edoc_isbasi_line_discount(array('discount_rate' => 5, 'offer_discount_rate' => 0, 'discount_amount' => 5000)), 'typed rate alone: the rate');
	pg_assert_same(array('rate' => 5.0, 'value' => 0.0), erp_edoc_isbasi_line_discount(array('discount_rate' => 5, 'discount_amount' => 5000)), 'a line without the campaign column');
	pg_assert_same(array('rate' => 0.0, 'value' => 0.0), erp_edoc_isbasi_line_discount(array('discount_rate' => 0, 'discount_amount' => 0)), 'no discount');
	pg_assert_same(array('rate' => 0.0, 'value' => 12.34), erp_edoc_isbasi_line_discount(array('discount_rate' => 0, 'discount_amount' => 1234)), 'an amount typed as such');
}

// An invoice row as erp_edoc_isbasi_send_invoice() hands it to the payload,
// a person's card passed in (no account lookup) and a line with no product
// (no SKU lookup).
function erp_edoc_isbasi_test_description($invoice_overrides)
{
	$invoice = array_merge(array(
		'id' => 9, 'direction' => 'sales', 'doc_type' => 'invoice', 'invoice_type' => 'SATIS',
		'full_number' => 'PGF2026000000009', 'account_id' => 7, 'order_id' => 0,
		'issue_date' => '2026-10-19', 'currency' => 'TRY', 'exchange_rate' => '1.000000', 'is_internet_sale' => 0,
	), $invoice_overrides);

	$party = array(
		'title' => 'Ayşe Yılmaz', 'is_person' => true, 'tax_number' => '', 'tax_office' => '', 'email' => 'ayse@example.com',
		'address' => 'Atatürk Cad. 1', 'postcode' => '34710', 'city' => 'İstanbul', 'district' => 'Kadıköy', 'country_code' => 'TR',
	);

	$lines = array(
		array('product_id' => 0, 'description' => 'Hizmet', 'quantity' => '1.0000', 'unit_price' => 50000, 'discount_rate' => '0.000', 'discount_amount' => 0,
			'tax_rate' => '20.000', 'unit_code' => 'C62', 'withholding_rate' => '0.000', 'withholding_code' => '', 'vat_exemption_code' => ''),
	);

	$payload = erp_edoc_isbasi_invoice_payload($invoice, $lines, array('party' => $party));

	return isset($payload['description']) ? $payload['description'] : 'error: ' . (isset($payload['error']) ? $payload['error'] : '?');
}

// The order is named by the number its buyer and the panel know
// (orders.order_number), not by its row id; an order without a number, and
// an invoice without an order, carry the invoice number alone.
function test_erp_edoc_isbasi_invoice_description_order_number()
{
	$description = erp_edoc_isbasi_test_description(array('order_id' => 438, 'order_number' => '1172'));
	pg_assert_contains('PGF2026000000009 / ', $description);
	pg_assert_contains('1172', $description, 'the order number');
	pg_assert_false(strpos($description, '438') !== false, 'no row id in the description');

	pg_assert_same('PGF2026000000009', erp_edoc_isbasi_test_description(array('order_id' => 438, 'order_number' => '')), 'an order without a number');
	pg_assert_same('PGF2026000000009', erp_edoc_isbasi_test_description(array('order_id' => 438, 'order_number' => '  ')), 'a blank number');
	pg_assert_same('PGF2026000000009', erp_edoc_isbasi_test_description(array('order_id' => 0, 'order_number' => '1172')), 'no order');
	pg_assert_same('PGF2026000000009', erp_edoc_isbasi_test_description(array()), 'no order at all');
}

// A line with VAT withholding gets a product card of its own at İşbaşı,
// named by the code and the rate: İşbaşı reads the withholding from the
// card, and a card keeps what the first line that named it carried.
function test_erp_edoc_isbasi_item_code()
{
	pg_assert_same('', erp_edoc_isbasi_item_code('', 0), 'a line without a product: no code');
	pg_assert_same('PG-12', erp_edoc_isbasi_item_code('', 12), 'a product without an SKU');
	pg_assert_same('ABC-1', erp_edoc_isbasi_item_code(' ABC-1 ', 12), 'the SKU');
	pg_assert_same('ABC-1', erp_edoc_isbasi_item_code('ABC-1', 12, '624', 0), 'a code without a rate is no withholding');
	pg_assert_same('ABC-1-T624-20', erp_edoc_isbasi_item_code('ABC-1', 12, '624', 20), 'the SKU with withholding');
	pg_assert_same('PG-12-T616-50', erp_edoc_isbasi_item_code('', 12, '616', '50.000'), 'a product without an SKU, with withholding');
	pg_assert_same('PG-T624-20', erp_edoc_isbasi_item_code('', 0, '624', 20), 'a line without a product, with withholding');
	pg_assert_same('PG-T650-45.5', erp_edoc_isbasi_item_code('', 0, '650', 45.5), 'a rate with a fraction');

	$long = str_repeat('Ş', 60);
	pg_assert_same(50, mb_strlen(erp_edoc_isbasi_item_code($long, 1)), 'a long SKU is cut to 50');
	$code = erp_edoc_isbasi_item_code($long, 1, '624', 20);
	pg_assert_same(50, mb_strlen($code), 'a long SKU with withholding is cut to 50');
	pg_assert_same('-T624-20', mb_substr($code, -8), 'the withholding survives the cut');
}

// An internet-sale invoice with the given carrier, through the payload.
function erp_edoc_isbasi_test_internet_sale($carrier_vkn, $carrier_title)
{
	$invoice = array(
		'id' => 9, 'direction' => 'sales', 'doc_type' => 'invoice', 'invoice_type' => 'SATIS',
		'full_number' => 'PGF2026000000009', 'account_id' => 7, 'order_id' => 0,
		'issue_date' => '2026-10-19', 'currency' => 'TRY', 'exchange_rate' => '1.000000', 'is_internet_sale' => 1,
		'shipment_date' => '2026-10-20', 'payment_date' => '2026-10-18', 'payment_method' => 'KREDIKARTI/BANKAKARTI',
		'web_address' => 'https://example.com', 'carrier_vkn' => $carrier_vkn, 'carrier_title' => $carrier_title,
	);

	$party = array(
		'title' => 'Ayşe Yılmaz', 'is_person' => true, 'tax_number' => '', 'tax_office' => '', 'email' => 'ayse@example.com',
		'address' => 'Atatürk Cad. 1', 'postcode' => '34710', 'city' => 'İstanbul', 'district' => 'Kadıköy', 'country_code' => 'TR',
	);

	$lines = array(
		array('product_id' => 0, 'description' => 'Hizmet', 'quantity' => '1.0000', 'unit_price' => 50000, 'discount_rate' => '0.000', 'discount_amount' => 0,
			'tax_rate' => '20.000', 'unit_code' => 'C62', 'withholding_rate' => '0.000', 'withholding_code' => '', 'vat_exemption_code' => ''),
	);

	return erp_edoc_isbasi_invoice_payload($invoice, $lines, array('party' => $party));
}

// What İşbaşı reads on an internet sale: the payment date as the date alone,
// a company carrier by its title, a person carrier by name and surname.
function test_erp_edoc_isbasi_internet_sale_fields()
{
	$payload = erp_edoc_isbasi_test_internet_sale('9999999994', 'Örnek Kargo A.Ş.');
	pg_assert_same('2026-10-18', $payload['eGovernmentInvoice']['eArchivePaymentDate'] ?? null, 'the payment date alone');
	pg_assert_same(3, $payload['eGovernmentInvoice']['invoiceTypeForEinvoice'] ?? null, 'internet sale');
	pg_assert_same(1, $payload['shipmentAgentItem']['firmType'] ?? null, 'a VKN carrier is a company');
	pg_assert_same('Örnek Kargo A.Ş.', $payload['shipmentAgentItem']['title'] ?? null, 'a company carrier carries its title');

	$payload = erp_edoc_isbasi_test_internet_sale('10000000146', 'Ali Veli');
	pg_assert_same(0, $payload['shipmentAgentItem']['firmType'] ?? null, 'a TCKN carrier is a person');
	pg_assert_same('Ali', $payload['shipmentAgentItem']['name'] ?? null, 'first name');
	pg_assert_same('Veli', $payload['shipmentAgentItem']['surName'] ?? null, 'surname');
	pg_assert_false(isset($payload['shipmentAgentItem']['title']), 'a person carrier goes by name');
}

// A withholding line without a product goes with the card code that names
// its withholding; a plain line beside it keeps none.
function test_erp_edoc_isbasi_withholding_line_item_code()
{
	$invoice = array(
		'id' => 9, 'direction' => 'sales', 'doc_type' => 'invoice', 'invoice_type' => 'TEVKIFAT',
		'full_number' => 'PGF2026000000009', 'account_id' => 7, 'order_id' => 0,
		'issue_date' => '2026-10-19', 'currency' => 'TRY', 'exchange_rate' => '1.000000', 'is_internet_sale' => 0,
	);
	$party = array(
		'title' => 'Örnek Ltd', 'is_person' => false, 'tax_number' => '9999999994', 'tax_office' => 'Kadıköy', 'email' => '',
		'address' => 'Atatürk Cad. 1', 'postcode' => '34710', 'city' => 'İstanbul', 'district' => 'Kadıköy', 'country_code' => 'TR',
	);
	$lines = array(
		array('product_id' => 0, 'description' => 'Taşıma', 'quantity' => '1.0000', 'unit_price' => 50000, 'discount_rate' => '0.000', 'discount_amount' => 0,
			'tax_rate' => '20.000', 'unit_code' => 'C62', 'withholding_rate' => '20.000', 'withholding_code' => '624', 'vat_exemption_code' => ''),
		array('product_id' => 0, 'description' => 'Ambalaj', 'quantity' => '1.0000', 'unit_price' => 1000, 'discount_rate' => '0.000', 'discount_amount' => 0,
			'tax_rate' => '20.000', 'unit_code' => 'C62', 'withholding_rate' => '0.000', 'withholding_code' => '', 'vat_exemption_code' => ''),
	);

	$payload = erp_edoc_isbasi_invoice_payload($invoice, $lines, array('party' => $party));
	pg_assert_same('PG-T624-20', $payload['salesInvoiceDetails'][0]['productDetail']['itemCode'] ?? null, 'the withholding line');
	pg_assert_same(array('code' => '624', 'rateText' => '2/10'), $payload['salesInvoiceDetails'][0]['productDetail']['withholding'] ?? null, 'its withholding');
	pg_assert_same('', $payload['salesInvoiceDetails'][1]['productDetail']['itemCode'] ?? null, 'the plain line');
	pg_assert_same(4, $payload['eGovernmentInvoice']['eGovernmentType'] ?? null, 'TEVKIFAT');
}
