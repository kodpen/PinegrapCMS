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
