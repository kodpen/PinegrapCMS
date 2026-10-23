<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order screen's own Paraşüt invoice (includes/fn/parasut.php,
 * parasut_create_invoice()): its lines are the ones the order's ERP invoice
 * carries (parasut_order_invoice_lines()), written as the ERP's Paraşüt
 * driver writes them, so that Paraşüt, taking the VAT once per rate on the
 * whole amount, comes to what the customer was charged.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');

// The lines below name no Pinegrap product, so they go on the default one.
if (!defined('PARASUT_DEFAULT_PRODUCT_ID')) {
	define('PARASUT_DEFAULT_PRODUCT_ID', '9001');
}

// An order line as the checkout writes it, joined with its product.
function pg_test_parasut_order_item($price, $quantity, $tax_total, $rate, $overrides = array())
{
	return array_merge(array(
		'id' => 0,
		'product_id' => 0,
		'product_name' => 'Deri Kalem Kılıfı',
		'short_description' => '',
		'quantity' => $quantity,
		'price' => $price,
		'tax_total' => $tax_total,
		'tax_rate' => $rate,
		'product_tax_rate' => null,
		'recurring_payment_period' => '',
		'recurring_start_date' => '',
		'product_start' => 0,
	), $overrides);
}

// The sales_invoices body the order's lines go to Paraşüt with.
function pg_test_parasut_order_payload($built)
{
	return erp_edoc_parasut_invoice_payload(array(
		'order_id' => 446,
		'order_number' => '1180',
		'order_date' => '2026-10-22',
		'full_number' => '',
		'issue_date' => '2026-10-22',
		'due_date' => '2026-10-22',
		'currency' => 'TRY',
		'exchange_rate' => 0,
		'notes' => '',
	), $built['lines'], '1073897582');
}

// Paraşüt's arithmetic on a body: each line's amount less its discount, each
// rate's VAT on the sum of its lines, half up to the kurus (seen live, see
// pg_test_parasut_simulated_total() in erp_edoc_parasut_test.php).
function pg_test_parasut_order_paid_total($payload)
{
	$bases = array();

	foreach ($payload['data']['relationships']['details']['data'] as $detail) {
		$attributes = $detail['attributes'];
		$amount = (int) round($attributes['quantity'] * $attributes['unit_price'] * 100);
		$discount = ($attributes['discount_type'] === 'percentage')
			? erp_apply_rate($amount, $attributes['discount_value'])
			: (int) round($attributes['discount_value'] * 100);
		$rate = number_format((float) $attributes['vat_rate'], 3, '.', '');
		$bases[$rate] = ($bases[$rate] ?? 0) + $amount - $discount;
	}

	$total = 0;

	foreach ($bases as $rate => $base) {
		$total += $base + erp_apply_rate($base, (float) $rate);
	}

	return $total;
}

// The buttons are the store's way to Paraşüt once the integration is on and
// both its company and its client are set.
function test_parasut_order_buttons_on_rule()
{
	pg_assert_true(_parasut_order_buttons_on(1, '830533', 'Lk-abc'));
	pg_assert_true(_parasut_order_buttons_on('1', '830533', 'Lk-abc'));
	pg_assert_false(_parasut_order_buttons_on(0, '830533', 'Lk-abc'));
	pg_assert_false(_parasut_order_buttons_on('0', '830533', 'Lk-abc'));
	pg_assert_false(_parasut_order_buttons_on(1, '', 'Lk-abc'));
	pg_assert_false(_parasut_order_buttons_on(1, '830533', ''));
}

// Dev order #1180: 39.95 with 7.99 of VAT and shipping 9.98. The shipping
// holds its VAT at the goods' rate (it went at 0% before), taken with the
// goods on their sum: Paraşüt's 48.27 + 9.65 = 57.92, the order's total.
function test_parasut_order_invoice_lines_carry_the_shipping_at_the_goods_rate()
{
	$order = array('id' => 446, 'order_date' => mktime(12, 0, 0, 10, 22, 2026), 'discount' => 0, 'tax' => 799, 'shipping' => 998, 'surcharge' => 0, 'installment_charges' => 0, 'total' => 5792, 'gift_card_discount' => 0);
	$built = parasut_order_invoice_lines($order, array(pg_test_parasut_order_item(3995, 1, 799, '20.000')), true, false);

	pg_assert_same(1, $built['charged']);
	pg_assert_same(5792, $built['expected']);
	pg_assert_same(5792, $built['totals']['grand_total']);
	pg_assert_same(2, count($built['lines']));
	pg_assert_same(20.0, (float) $built['lines'][1]['tax_rate'], 'shipping at the goods rate');
	pg_assert_same(832, $built['lines'][1]['line_total'], 'shipping before VAT');
	pg_assert_same(166, $built['lines'][1]['tax_total'], 'its VAT inside the 9.98');
	pg_assert_same('', parasut_order_invoice_refusal($built['lines'], 'TRY', '9001'));

	$payload = pg_test_parasut_order_payload($built);
	pg_assert_false(isset($payload['error']));
	$details = $payload['data']['relationships']['details']['data'];
	pg_assert_same(20.0, $details[1]['attributes']['vat_rate']);
	pg_assert_same(8.32, $details[1]['attributes']['unit_price']);
	pg_assert_same('9001', $details[1]['relationships']['product']['data']['id']);
	pg_assert_same('1180', $payload['data']['attributes']['order_no']);
	pg_assert_same(5792, pg_test_parasut_order_paid_total($payload));
}

// Dev order #1176, placed before the checkout settled its shipping: 57.93 at
// 20%, a total no amount reaches. The invoice takes the shipping a kurus
// lower, 9.98 (57.92, Paraşüt's 48.27 + 9.65), and the kurus stays with the
// payment; the lines as charged would be refused before anything is sent.
function test_parasut_order_invoice_lines_settle_an_unreachable_shipping()
{
	$order = array('id' => 442, 'order_date' => mktime(12, 0, 0, 10, 21, 2026), 'discount' => 0, 'tax' => 799, 'shipping' => 999, 'surcharge' => 0, 'installment_charges' => 0, 'total' => 5793, 'gift_card_discount' => 0);
	$item = pg_test_parasut_order_item(3995, 1, 799, '20.000');
	$built = parasut_order_invoice_lines($order, array($item), true, false);

	pg_assert_same(5792, $built['expected']);
	pg_assert_same(5792, $built['totals']['grand_total']);
	pg_assert_same(1, $built['totals']['charges_left']);
	pg_assert_same(array(), erp_edoc_parasut_vat_shift($built['lines'])['refusal']);
	pg_assert_same(5792, pg_test_parasut_order_paid_total(pg_test_parasut_order_payload($built)));

	$charged = erp_order_lines($order, array($item), true);
	$refusal = erp_edoc_parasut_vat_shift($charged['lines'])['refusal'];
	pg_assert_same(5793, $refusal['total']);
	pg_assert_same(5792, $refusal['below']);
	pg_assert_same(5794, $refusal['above']);
}

// A discounted order was refused outright; its discount is now spread over
// the lines and the VAT taken again per rate on what it leaves, as the
// checkout charged it.
function test_parasut_order_invoice_lines_take_a_discounted_order()
{
	$lines = array(
		501 => array('amount' => 10000, 'rate' => '20.000'),
		502 => array('amount' => 4995, 'rate' => '10.000'),
	);
	$taxes = pg_order_line_taxes($lines);
	$charged = array();
	$items = array();

	foreach ($lines as $id => $line) {
		$charged[$id] = array('amount' => $line['amount'], 'rate' => $line['rate'], 'tax' => $taxes[$id]);
		$items[] = pg_test_parasut_order_item($line['amount'], 1, $taxes[$id], $line['rate'], array('id' => $id));
	}

	$discount = 1500;
	$tax = pg_order_charged_tax($charged, $discount);
	$order = array('id' => 447, 'order_date' => mktime(12, 0, 0, 10, 22, 2026), 'discount' => $discount, 'tax' => $tax, 'shipping' => 0, 'surcharge' => 0, 'installment_charges' => 0, 'total' => 14995 - $discount + $tax, 'gift_card_discount' => 0);
	$built = parasut_order_invoice_lines($order, $items, true, false);

	pg_assert_same($order['total'], $built['totals']['grand_total']);
	pg_assert_same($discount, $built['lines'][0]['discount_amount'] + $built['lines'][1]['discount_amount']);

	$payload = pg_test_parasut_order_payload($built);
	pg_assert_same('amount', $payload['data']['relationships']['details']['data'][0]['attributes']['discount_type']);
	pg_assert_same($order['total'], pg_test_parasut_order_paid_total($payload));
}

// The instalment charge is in orders.total, so it is a line: the invoice used
// to fall short of every instalment order by it. It holds its VAT at the
// goods' rate, as the shipping does: 100.00 + 20.00 and 3.00 of instalment
// charge go as 102.50 + 20.50 = 123.00.
function test_parasut_order_invoice_lines_carry_the_installment_charge()
{
	$order = array('id' => 448, 'order_date' => mktime(12, 0, 0, 10, 22, 2026), 'discount' => 0, 'tax' => 2000, 'shipping' => 0, 'surcharge' => 0, 'installment_charges' => 300, 'total' => 12300, 'gift_card_discount' => 0);
	$built = parasut_order_invoice_lines($order, array(pg_test_parasut_order_item(10000, 1, 2000, '20.000')), true, false);

	pg_assert_same(12300, $built['totals']['grand_total']);
	pg_assert_same(250, $built['lines'][1]['line_total']);
	pg_assert_same(50, $built['lines'][1]['tax_total']);
	pg_assert_same(12300, pg_test_parasut_order_paid_total(pg_test_parasut_order_payload($built)));
}

// A gift card pays part of the sale; the invoice states the whole sale.
function test_parasut_order_invoice_lines_add_the_gift_card_back()
{
	$order = array('id' => 449, 'order_date' => mktime(12, 0, 0, 10, 22, 2026), 'discount' => 0, 'tax' => 799, 'shipping' => 0, 'surcharge' => 0, 'installment_charges' => 0, 'total' => 2794, 'gift_card_discount' => 2000);
	$built = parasut_order_invoice_lines($order, array(pg_test_parasut_order_item(3995, 1, 799, '20.000')), true, false);

	pg_assert_same(4794, $built['expected']);
	pg_assert_same(4794, $built['totals']['grand_total']);
}

// A recurring line that starts after the order day was not charged with it
// and stays off the invoice; with no line charged there is nothing to send.
function test_parasut_order_invoice_lines_leave_out_lines_not_charged()
{
	$order = array('id' => 450, 'order_date' => mktime(12, 0, 0, 10, 22, 2026), 'discount' => 0, 'tax' => 0, 'shipping' => 0, 'surcharge' => 0, 'installment_charges' => 0, 'total' => 0, 'gift_card_discount' => 0);
	$later = pg_test_parasut_order_item(9900, 1, 1980, '20.000', array('recurring_payment_period' => 'Monthly', 'recurring_start_date' => '2026-11-15'));
	$built = parasut_order_invoice_lines($order, array($later), true, false);

	pg_assert_same(0, $built['charged']);
	pg_assert_same(array(), $built['lines']);
}

// A rate Paraşüt does not take (18% before 2023) is refused before anything
// is sent; the rates it takes are not.
function test_parasut_order_invoice_refusal_names_a_rate_parasut_does_not_take()
{
	$refusal = parasut_order_invoice_refusal(array(array('tax_rate' => '18.000', 'line_total' => 1000, 'discount_amount' => 0, 'tax_total' => 180, 'product_id' => 0)), 'TRY', '9001');
	pg_assert_contains('18', $refusal);

	pg_assert_same('', parasut_order_invoice_refusal(array(array('tax_rate' => '1.000', 'line_total' => 1250, 'discount_amount' => 0, 'tax_total' => 13, 'product_id' => 0)), 'TRY', '9001'));
}

// An order line of a product priced below zero cannot go to Paraşüt (it
// answers 422 for a negative unit price); the order is refused before
// anything is sent, as the ERP's driver refuses its invoice.
function test_parasut_order_invoice_refusal_names_a_line_below_zero()
{
	$goods = array('description' => 'Deri Kalem Kılıfı', 'tax_rate' => '20.000', 'unit_price' => 3995, 'line_total' => 3995, 'discount_amount' => 0, 'tax_total' => 799, 'product_id' => 0);
	$credit = array('description' => 'Sadakat indirimi', 'tax_rate' => '20.000', 'unit_price' => -500, 'line_total' => -500, 'discount_amount' => 0, 'tax_total' => -100, 'product_id' => 0);

	$refusal = parasut_order_invoice_refusal(array($goods, $credit), 'TRY', '9001');
	pg_assert_same(erp_edoc_parasut_negative_line_refusal(array($goods, $credit)), $refusal);
	pg_assert_contains('"Sadakat indirimi"', $refusal);

	pg_assert_same('', parasut_order_invoice_refusal(array($goods), 'TRY', '9001'));
}

// Create Invoice asks for a line below zero before it compares the lines
// with the order total: an order whose VAT the checkout held at zero would
// otherwise be refused by its totals without the line being named.
function test_parasut_create_invoice_names_a_line_below_zero_before_the_totals()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function parasut_create_invoice(');
	pg_assert_true($start !== false, 'parasut_create_invoice() is found');

	$asked = strpos($source, 'erp_edoc_parasut_negative_line_refusal($built[\'lines\'])', $start);
	$compared = strpos($source, '$built[\'totals\'][\'grand_total\'] !== $built[\'expected\']', $start);

	pg_assert_true(($asked !== false) && ($compared !== false) && ($asked < $compared), 'the line is asked for before the totals are compared');
}

// On random orders as the checkout charges them - several rates, a discount,
// shipping and a surcharge settled so that every rate's total is reachable,
// sometimes a gift card - the lines as they go to Paraşüt come, by
// Paraşüt's arithmetic, to what the customer was charged.
function test_parasut_order_invoice_meets_the_order_on_random_orders()
{
	mt_srand(20261022);
	$rates = array('0.000', '1.000', '10.000', '20.000', '20.000', '20.000');

	for ($run = 0; $run < 1200; $run++) {
		$lines = array();

		for ($i = 0, $count = mt_rand(1, 5); $i < $count; $i++) {
			$lines[700 + $i] = array(
				'amount' => mt_rand(1, (mt_rand(0, 1) ? 3000 : 90000)),
				'rate' => $rates[mt_rand(0, count($rates) - 1)],
			);
		}

		$taxes = pg_order_line_taxes($lines);
		$charged = array();
		$items = array();

		foreach ($lines as $id => $line) {
			$charged[$id] = array('amount' => $line['amount'], 'rate' => $line['rate'], 'tax' => $taxes[$id]);
			$items[] = pg_test_parasut_order_item($line['amount'], 1, $taxes[$id], $line['rate'], array('id' => $id));
		}

		$subtotal = array_sum(array_column($lines, 'amount'));
		$discount = mt_rand(0, 2) ? 0 : mt_rand(1, $subtotal);
		$tax = ($discount > 0) ? pg_order_charged_tax($charged, $discount) : array_sum($taxes);
		$settled = pg_order_charges_reachable($charged, $discount, array(
			'shipping' => mt_rand(0, 2) ? mt_rand(1, 5000) : 0,
			'surcharge' => mt_rand(0, 4) ? 0 : mt_rand(1, 900),
		));
		$sale = $subtotal - $discount + $tax + $settled['shipping'] + $settled['surcharge'];
		$gift_card = mt_rand(0, 5) ? 0 : mt_rand(0, $sale);

		$order = array(
			'id' => 900 + $run, 'order_date' => mktime(12, 0, 0, 10, 22, 2026),
			'discount' => $discount, 'tax' => $tax,
			'shipping' => $settled['shipping'], 'surcharge' => $settled['surcharge'], 'installment_charges' => 0,
			'total' => $sale - $gift_card, 'gift_card_discount' => $gift_card,
		);
		$built = parasut_order_invoice_lines($order, $items, true, false);

		pg_assert_same($built['expected'], $built['totals']['grand_total'], 'run ' . $run . ' total');
		pg_assert_same(array(), erp_edoc_parasut_vat_shift($built['lines'])['refusal'], 'run ' . $run . ' reachable');
		pg_assert_same('', parasut_order_invoice_refusal($built['lines'], 'TRY', '9001'), 'run ' . $run . ' refusal');

		$payload = pg_test_parasut_order_payload($built);
		pg_assert_false(isset($payload['error']), 'run ' . $run . ' payload');
		pg_assert_same($sale, pg_test_parasut_order_paid_total($payload), 'run ' . $run . ' Paraşüt total');
	}
}

// Pay with iyzico sets the instalment charge itself: 57.92 and 2.05 come to
// 59.97, which Paraşüt never reaches at 20%. The order goes with the charge a
// kurus lower, 59.96, and the kurus stays with the payment.
function test_parasut_order_invoice_lines_settle_an_installment_out_of_reach()
{
	$order = array('id' => 451, 'order_date' => mktime(12, 0, 0, 10, 22, 2026), 'discount' => 0, 'tax' => 799, 'shipping' => 998, 'surcharge' => 0, 'installment_charges' => 205, 'total' => 5997, 'gift_card_discount' => 0);
	$built = parasut_order_invoice_lines($order, array(pg_test_parasut_order_item(3995, 1, 799, '20.000')), true, false);

	pg_assert_same(5996, $built['expected']);
	pg_assert_same(5996, $built['totals']['grand_total']);
	pg_assert_same(1, $built['totals']['charges_left']);
	pg_assert_same(array(), erp_edoc_parasut_vat_shift($built['lines'])['refusal']);
	pg_assert_same(5996, pg_test_parasut_order_paid_total(pg_test_parasut_order_payload($built)));
}

// The sale an order's e-Archive states (erp_order_sale_fields()): dev order
// #1180, paid by card, shipped with ABC Kargo.
function pg_test_parasut_order_sale($overrides = array())
{
	return array_merge(array(
		'is_internet_sale' => 1,
		'web_address' => 'https://dev.pinegrap.com',
		'payment_method' => 'KREDIKARTI/BANKAKARTI',
		'payment_date' => '2026-10-22',
		'shipment_date' => '2026-10-23',
		'carrier_title' => 'ABC Kargo A.Ş.',
		'carrier_vkn' => '1234567808',
	), $overrides);
}

// A counter sale is not an internet sale and states no address; anything
// else is one, at the ERP's web address or, without it, the site's own.
function test_parasut_order_edoc_internet_sale_rule()
{
	pg_assert_same(array('is_internet_sale' => 0, 'web_address' => ''), _erp_order_internet_sale('local', 'https://shop.example', 'https://dev.pinegrap.com'));
	pg_assert_same(array('is_internet_sale' => 1, 'web_address' => 'https://dev.pinegrap.com'), _erp_order_internet_sale('online', '', 'https://dev.pinegrap.com'));
	pg_assert_same(array('is_internet_sale' => 1, 'web_address' => 'https://shop.example'), _erp_order_internet_sale('marketplace', ' https://shop.example ', 'https://dev.pinegrap.com'));
	pg_assert_same(array('is_internet_sale' => 1, 'web_address' => ''), _erp_order_internet_sale('', '', ''), 'an older row without a type');
}

// An internet sale's e-Archive carries its address, payment and carrier, as
// the ERP's invoice of the order does; before, the old button sent none.
function test_parasut_order_edoc_attributes_of_an_internet_sale()
{
	$lines = array(array('tax_rate' => '20.000', 'vat_exemption_code' => ''));
	$edoc = parasut_order_edoc_attributes(pg_test_parasut_order_sale(), $lines, 'earchive', '', '2026-10-20');

	pg_assert_same('', $edoc['error']);
	pg_assert_same(array(
		'internet_sale' => array(
			'url' => 'https://dev.pinegrap.com',
			'payment_type' => 'KREDIKARTI/BANKAKARTI',
			'payment_platform' => erp_payment_method_labels()['KREDIKARTI/BANKAKARTI'],
			'payment_date' => '2026-10-22',
		),
		'shipment' => array('title' => 'ABC Kargo A.Ş.', 'date' => '2026-10-23', 'vkn' => '1234567808'),
	), $edoc['attributes']);

	// A payment or a shipment the order does not date goes on the invoice's
	// day; a carrier with a TCKN is named as a person.
	$edoc = parasut_order_edoc_attributes(pg_test_parasut_order_sale(array(
		'payment_method' => '',
		'payment_date' => '0000-00-00',
		'shipment_date' => '0000-00-00',
		'carrier_title' => 'Ayşe Kaya',
		'carrier_vkn' => '10000000146',
	)), $lines, 'earchive', '', '2026-10-20');

	pg_assert_same('DIGER', $edoc['attributes']['internet_sale']['payment_type']);
	pg_assert_same('2026-10-20', $edoc['attributes']['internet_sale']['payment_date']);
	pg_assert_same(array('title' => 'Ayşe Kaya', 'date' => '2026-10-20', 'tckn' => '10000000146', 'name' => 'Ayşe Kaya'), $edoc['attributes']['shipment']);
}

// An internet sale whose carrier the order does not give is refused before
// anything is sent; an e-Invoice and a counter sale do not carry one.
function test_parasut_order_edoc_refuses_an_internet_sale_without_its_carrier()
{
	$lines = array(array('tax_rate' => '20.000', 'vat_exemption_code' => ''));

	$edoc = parasut_order_edoc_attributes(pg_test_parasut_order_sale(array('carrier_vkn' => '')), $lines, 'earchive', '', '2026-10-20');
	pg_assert_same(array(), $edoc['attributes']);
	pg_assert_contains(lang('Carrier VKN / TCKN'), $edoc['error']);
	pg_assert_contains('Paraşüt', $edoc['error']);

	// A download or a service: no shipping method, so no carrier at all.
	$edoc = parasut_order_edoc_attributes(pg_test_parasut_order_sale(array('shipment_date' => '0000-00-00', 'carrier_title' => '', 'carrier_vkn' => '')), $lines, 'earchive', '', '2026-10-20');
	pg_assert_contains(lang('Carrier') . ', ' . lang('Carrier VKN / TCKN'), $edoc['error']);

	$edoc = parasut_order_edoc_attributes(pg_test_parasut_order_sale(array('carrier_vkn' => '')), $lines, 'einvoice', 'urn:mail:defaultpk@example.com', '2026-10-20');
	pg_assert_same('', $edoc['error']);
	pg_assert_same(array('scenario' => 'commercial', 'to' => 'urn:mail:defaultpk@example.com'), $edoc['attributes']);

	$edoc = parasut_order_edoc_attributes(pg_test_parasut_order_sale(array('is_internet_sale' => 0, 'web_address' => '', 'carrier_title' => '', 'carrier_vkn' => '')), $lines, 'earchive', '', '2026-10-20');
	pg_assert_same(array('attributes' => array(), 'error' => ''), $edoc);
}

// A zero-rated line names its exemption on either kind of document. Paraşüt
// takes one reason per document, and an "other" exemption needs the words
// an order cannot give.
function test_parasut_order_edoc_names_the_exemption_of_a_zero_rated_line()
{
	$sale = pg_test_parasut_order_sale(array('is_internet_sale' => 0, 'web_address' => ''));
	$lines = array(
		array('tax_rate' => '0.000', 'vat_exemption_code' => '301'),
		array('tax_rate' => '20.000', 'vat_exemption_code' => ''),
	);

	pg_assert_same(array('attributes' => array('vat_exemption_reason_code' => '301'), 'error' => ''), parasut_order_edoc_attributes($sale, $lines, 'earchive', '', '2026-10-20'));
	pg_assert_same(array('vat_exemption_reason_code' => '301', 'scenario' => 'commercial', 'to' => 'urn:mail:defaultpk@example.com'), parasut_order_edoc_attributes($sale, $lines, 'einvoice', 'urn:mail:defaultpk@example.com', '2026-10-20')['attributes']);

	$lines[1] = array('tax_rate' => '0.000', 'vat_exemption_code' => '302');
	pg_assert_contains('one VAT exemption reason', parasut_order_edoc_attributes($sale, $lines, 'earchive', '', '2026-10-20')['error']);

	$edoc = parasut_order_edoc_attributes($sale, array(array('tax_rate' => '0.000', 'vat_exemption_code' => '350')), 'earchive', '', '2026-10-20');
	pg_assert_same(array(), $edoc['attributes']);
	pg_assert_contains('350', $edoc['error']);
}

// The buyer card the order's invoice goes on keeps the identity number in
// tax_number, the one field Paraşüt has for a company's VKN and a person's
// TCKN; contact_type says which. An 11-digit number is a TCKN, so a sole
// proprietor who gave a trade name is a person too, as the ERP's driver
// opens such a card (erp_edoc_parasut_contact_attributes()).
function test_parasut_order_contact_attributes_keep_the_identity_in_tax_number()
{
	$order = array(
		'billing_company' => '',
		'billing_first_name' => 'Ayşe',
		'billing_last_name' => 'Kaya',
		'billing_email_address' => 'a@example.test',
		'billing_phone_number' => '',
		'billing_address_1' => 'Cad. 1',
		'billing_city' => 'İstanbul',
		'billing_country' => 'TR',
	);

	$person = parasut_order_contact_attributes($order, '10000000146');
	pg_assert_same(array(
		'account_type' => 'customer',
		'contact_type' => 'person',
		'name' => 'Ayşe Kaya',
		'email' => 'a@example.test',
		'phone' => '',
		'tax_number' => '10000000146',
		'address' => 'Cad. 1, İstanbul, TR',
	), $person);
	pg_assert_false(isset($person['tckn']), 'no attribute the documentation does not list');

	$order['billing_company'] = 'Kaya Ticaret A.Ş.';
	$company = parasut_order_contact_attributes($order, '1234567808');
	pg_assert_same('company', $company['contact_type']);
	pg_assert_same('Kaya Ticaret A.Ş.', $company['name']);
	pg_assert_same('1234567808', $company['tax_number']);

	$order['billing_company'] = 'Kaya Kırtasiye';
	$proprietor = parasut_order_contact_attributes($order, '10000000146');
	pg_assert_same('person', $proprietor['contact_type']);
	pg_assert_same('Kaya Kırtasiye', $proprietor['name']);
	pg_assert_same('10000000146', $proprietor['tax_number']);

	// No number configured or filled in: the card is opened without one.
	pg_assert_false(array_key_exists('tax_number', parasut_order_contact_attributes($order, '')));
}

// The order screen's buttons open the buyer card with those attributes.
function test_parasut_sync_contact_writes_the_card_with_the_order_attributes()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function parasut_sync_contact(');
	pg_assert_true($start !== false, 'parasut_sync_contact() is found');

	$end = strpos($source, "\n}\n", $start);
	$body = substr($source, $start, $end - $start);

	pg_assert_contains('parasut_order_contact_attributes($order, _parasut_order_identity($order))', $body);
	pg_assert_false(strpos($body, "'tckn'") !== false, 'no tckn attribute');
}
