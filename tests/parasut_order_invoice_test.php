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

require_once(PG_FUNCTIONS_DIR . '/includes/erp/accounts.php');
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

// What the order keeps after Create Invoice's POST sales_invoices: the
// record's id when Paraşüt names one, nothing when it refused the request,
// and the unsure mark when no answer, a 5xx or a success without a record
// leaves open that Paraşüt wrote it.
function test_parasut_order_invoice_id_after_create()
{
	$written = array('http_code' => 201, 'body' => array('data' => array('id' => '5001', 'type' => 'sales_invoices')), 'raw' => '{}');
	pg_assert_same('5001', parasut_order_invoice_id_after_create($written));
	pg_assert_same('5001', parasut_order_invoice_id_after_create(array('http_code' => 200, 'body' => array('data' => array('id' => 5001)), 'raw' => '{}')));

	pg_assert_same(ERP_EDOC_PARASUT_UNSURE, parasut_order_invoice_id_after_create(array('http_code' => 0, 'body' => null, 'raw' => '', 'curl_error' => 'Operation timed out after 30001 milliseconds')), 'a timeout');
	pg_assert_same(ERP_EDOC_PARASUT_UNSURE, parasut_order_invoice_id_after_create(array('http_code' => 502, 'body' => null, 'raw' => '<html>')), 'a gateway error');
	pg_assert_same(ERP_EDOC_PARASUT_UNSURE, parasut_order_invoice_id_after_create(array('http_code' => 201, 'body' => null, 'raw' => '<html>')), 'a success without a record');

	pg_assert_same('', parasut_order_invoice_id_after_create(array('http_code' => 422, 'body' => array('errors' => array()), 'raw' => '{}')), 'a refusal');
	pg_assert_same('', parasut_order_invoice_id_after_create(array('http_code' => 429, 'body' => null, 'raw' => '')), 'refused unprocessed');
	pg_assert_same('', parasut_order_invoice_id_after_create(array('http_code' => 401, 'body' => null, 'raw' => '')));
}

// The unsure mark is told from a record's id without the driver loaded: the
// order list and the order screen read it.
function test_parasut_order_invoice_unsure()
{
	pg_assert_true(parasut_order_invoice_unsure(ERP_EDOC_PARASUT_UNSURE), 'the bare mark');
	pg_assert_true(parasut_order_invoice_unsure(' ? '), 'read trimmed');
	pg_assert_true(parasut_order_invoice_unsure('?2026-10-22:7001'), 'the ERP\'s longer mark');
	pg_assert_false(parasut_order_invoice_unsure(''), 'not sent');
	pg_assert_false(parasut_order_invoice_unsure('5001'), 'a record');
	pg_assert_false(parasut_order_invoice_unsure(null));
}

// A sales invoice in Paraşüt's list answer, as GET sales_invoices with
// include=active_e_document gives it.
function pg_test_parasut_listed_invoice($id, $overrides = array(), $contact_id = '7001', $edoc_id = '')
{
	return array(
		'id' => $id,
		'type' => 'sales_invoices',
		'attributes' => array_merge(array(
			'description' => 'Order #1180',
			'issue_date' => '2026-10-22',
			'item_type' => 'invoice',
			'net_total' => '57.92',
		), $overrides),
		'relationships' => array(
			'contact' => array('data' => array('id' => $contact_id, 'type' => 'contacts')),
			'active_e_document' => array('data' => ($edoc_id === '') ? null : array('id' => $edoc_id, 'type' => 'e_archives')),
		),
	);
}

function pg_test_parasut_search_payload()
{
	return array('data' => array('type' => 'sales_invoices', 'attributes' => array('description' => 'Order #1180', 'issue_date' => '2026-10-22')));
}

// The order's record in a list answer: its description, issue date and
// contact, not cancelled. One already an e-document (converted on Paraşüt's
// screen after the unanswered press) is still the order's, named as issued.
function test_parasut_order_invoice_matches()
{
	$body = array('data' => array(
		pg_test_parasut_listed_invoice('5003'),
		pg_test_parasut_listed_invoice('5002', array(), '7001', '9100'),
		pg_test_parasut_listed_invoice('5004', array('description' => 'Order #1181')),
		pg_test_parasut_listed_invoice('5005', array('issue_date' => '2026-10-21')),
		pg_test_parasut_listed_invoice('5006', array(), '7002'),
		pg_test_parasut_listed_invoice('5007', array('item_type' => 'cancelled')),
		'not a record',
	), 'meta' => array('total_pages' => 1));

	$matches = parasut_order_invoice_matches($body, pg_test_parasut_search_payload(), '7001');

	pg_assert_same(array('5003', '5002'), array_map('strval', array_keys($matches)), 'the order\'s records only');
	pg_assert_same('', $matches['5003']['edoc_id'], 'a draft');
	pg_assert_same('57.92', $matches['5003']['attributes']['net_total'], 'with its attributes for the total check');
	pg_assert_same('9100', $matches['5002']['edoc_id'], 'already issued');
	pg_assert_same(array(), parasut_order_invoice_matches(array('data' => null), pg_test_parasut_search_payload(), '7001'), 'an empty answer');
}

// The search an unsure press makes before it writes: the order's contact
// and issue date, newest first, with the e-document named, page by page.
function test_parasut_order_invoice_find_reads_the_contact_s_invoices_of_the_day()
{
	$asked = array();
	$get = function ($path) use (&$asked) {
		$asked[] = $path;
		return array('http_code' => 200, 'raw' => '{}', 'body' => array(
			'data' => (count($asked) === 2) ? array(pg_test_parasut_listed_invoice('5003')) : array(pg_test_parasut_listed_invoice('4990', array('description' => 'Order #1179'))),
			'meta' => array('total_pages' => 3),
		));
	};

	$found = parasut_order_invoice_find(pg_test_parasut_search_payload(), '7001', $get);

	pg_assert_same('', $found['error']);
	pg_assert_same(array('5003'), array_map('strval', array_keys($found['records'])), 'found on the second page');
	pg_assert_same(3, count($asked), 'every page read');
	pg_assert_contains('/sales_invoices?filter[contact_id]=7001&filter[issue_date]=2026-10-22&sort=-id&include=active_e_document', $asked[0]);
	pg_assert_contains('&page[number]=2&page[size]=' . ERP_EDOC_PARASUT_PAGE_SIZE, $asked[1]);
}

// None found is an answer (the press then writes the invoice); a page
// Paraşüt does not answer, or pages left unread with nothing matched, is
// not, and the mark stays.
function test_parasut_order_invoice_find_tells_none_from_unknown()
{
	$none = parasut_order_invoice_find(pg_test_parasut_search_payload(), '7001', function ($path) {
		return array('http_code' => 200, 'raw' => '{}', 'body' => array('data' => array(), 'meta' => array('total_pages' => 1)));
	});
	pg_assert_same(array('records' => array(), 'error' => ''), $none, 'none written');

	$timeout = parasut_order_invoice_find(pg_test_parasut_search_payload(), '7001', function ($path) {
		return array('http_code' => 0, 'body' => null, 'raw' => '', 'curl_error' => 'Operation timed out after 30001 milliseconds');
	});
	pg_assert_same(array(), $timeout['records']);
	pg_assert_contains('Operation timed out', $timeout['error'], 'no answer to the search');

	$refused = parasut_order_invoice_find(pg_test_parasut_search_payload(), '7001', function ($path) {
		return array('http_code' => 503, 'body' => null, 'raw' => '<html>');
	});
	pg_assert_true($refused['error'] !== '', 'a 5xx');

	$pages = 0;
	$crowded = parasut_order_invoice_find(pg_test_parasut_search_payload(), '7001', function ($path) use (&$pages) {
		$pages++;
		return array('http_code' => 200, 'raw' => '{}', 'body' => array('data' => array(), 'meta' => array('total_pages' => 999)));
	});
	pg_assert_same((int) ERP_EDOC_PARASUT_FIND_PAGES, $pages, 'stops at the page limit');
	pg_assert_same(array(), $crowded['records']);
	pg_assert_true($crowded['error'] !== '', 'pages left unread');
}

// The order screen's badge for the invoice: "Sent" with the record's id
// once its e-document was seen to finish (an unconfirmed one:
// parasut_order_gates_test.php), and for the unsure mark a warning to check
// Paraşüt, never "Sent" or the mark shown as an id.
function test_parasut_order_invoice_status_badge()
{
	$sent = parasut_order_invoice_status_badge('5001', false, true);
	pg_assert_contains(lang('Sent') . '</span>', $sent);
	pg_assert_contains(lang('Parasut ID') . ': 5001', $sent);
	pg_assert_contains(lang('Parasut ID') . ': 5001', parasut_order_invoice_status_badge('5001', true, true));

	pg_assert_contains(lang('Not Sent'), parasut_order_invoice_status_badge(''));
	pg_assert_same('', parasut_order_invoice_status_badge('', true), 'no badge on a button not pressed');

	foreach (array(false, true) as $in_button) {
		$unsure = parasut_order_invoice_status_badge(ERP_EDOC_PARASUT_UNSURE, $in_button);

		pg_assert_contains('theme-warning', $unsure);
		pg_assert_contains(h(lang('Check Paraşüt')), $unsure);
		pg_assert_false(strpos($unsure, '>' . lang('Sent') . '<') !== false, 'not "Sent"');
		pg_assert_false(strpos($unsure, lang('Parasut ID')) !== false, 'the mark is not an id');
	}
}

// Create Invoice on an order with the mark looks at Paraşüt before it
// writes: the search goes before the mark and the request, which go only
// when nothing was found; only a real id refuses the press outright.
function test_parasut_create_invoice_looks_before_it_writes_again()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function parasut_create_invoice(');
	pg_assert_true($start !== false, 'parasut_create_invoice() is found');
	$end = strpos($source, "\nfunction ", (int) $start + 1);
	$body = ($end === false) ? substr($source, (int) $start) : substr($source, (int) $start, $end - (int) $start);

	$unsure = strpos($body, '$unsure = parasut_order_invoice_unsure($existing_invoice_id);');
	$refused = strpos($body, "if ((\$existing_invoice_id !== '') && !\$unsure) {");
	$searched = strpos($body, 'parasut_order_invoice_find($invoice_body, $parasut_contact_id,');
	$kept = strpos($body, "SET parasut_invoice_id = '\" . escape(\$sales_invoice_id)");
	$none = strpos($body, "if (\$sales_invoice_id === '') {");
	$marked = strpos($body, 'escape(ERP_EDOC_PARASUT_UNSURE)');
	$posted = strpos($body, "'/sales_invoices', \$invoice_body");
	$answered = strpos($body, 'parasut_order_invoice_id_after_create($inv_result)');
	$checked = strpos($body, 'erp_edoc_parasut_total_check($attributes,');

	pg_assert_true(($unsure !== false) && ($refused !== false) && ($unsure < $refused), 'a real id refuses, the mark does not');
	pg_assert_true(($searched !== false) && ($kept !== false) && ($searched < $kept), 'a record found is kept on the order');
	pg_assert_true(($none !== false) && ($kept < $none) && ($none < $marked), 'the create goes only when none was found');
	pg_assert_true(($marked !== false) && ($posted !== false) && ($marked < $posted), 'the mark is written before the request');
	pg_assert_true(($answered !== false) && ($posted < $answered), 'the answer decides what the order keeps');
	pg_assert_true(($checked !== false) && ($answered < $checked), 'a record found or written meets the total check');
}

// The order screen draws the invoice's badges from that one helper and
// offers Create Invoice again on the mark (the press looks for the record).
// A record's id is told apart from the mark in what the button says; the
// press on a record reads it first (parasut_stale_ids_test.php).
function test_parasut_order_screen_reads_the_mark()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order.php');

	pg_assert_contains('$parasut_invoice_sent = ($parasut_invoice_id !== \'\') && !$parasut_invoice_unsure;', $source);
	pg_assert_contains('$output_parasut_invoice_badge = parasut_order_invoice_status_badge($parasut_invoice_id, true, $parasut_exported);', $source);
	pg_assert_contains('$invoice_row_badge  = parasut_order_invoice_row_badge($parasut_invoice_id, $parasut_exported, $parasut_erp_invoice);', $source);
	pg_assert_true(strpos($source, 'title="\' . ($parasut_invoice_sent') !== false, 'only a record changes what the button says');
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
		'billing_city' => 'Kadıköy',
		'billing_state' => 'İstanbul',
		'billing_zip_code' => '34710',
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
		'address' => 'Cad. 1',
		'city' => 'İstanbul',
		'district' => 'Kadıköy',
		'postal_code' => '34710',
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

// A Turkish billing address goes on the card in Paraşüt's own fields, as
// the ERP's driver writes an account opened from the order
// (erp_address_from_checkout(), erp_edoc_parasut_contact_attributes()):
// the checkout's state field is the province (city), its city field the
// district, the street lines the address, the postcode postal_code. An
// e-Archive or e-Invoice needs the city and the district; folded into the
// address they reached Paraşüt as null.
function test_parasut_order_contact_attributes_split_a_turkish_address()
{
	$order = array(
		'billing_company' => '',
		'billing_first_name' => 'PG Canlı Test',
		'billing_last_name' => 'Alıcı',
		'billing_email_address' => 'b@example.test',
		'billing_phone_number' => '05550000000',
		'billing_address_1' => 'Test Sokak 1',
		'billing_address_2' => 'Daire 3',
		'billing_city' => 'Konak',
		'billing_state' => 'İzmir',
		'billing_zip_code' => '35000',
		'billing_country' => 'TR',
	);

	$card = parasut_order_contact_attributes($order, '');
	pg_assert_same('Test Sokak 1 Daire 3', $card['address'], 'the street lines, as the ERP account takes them');
	pg_assert_same('İzmir', $card['city'], 'the province');
	pg_assert_same('Konak', $card['district'], 'the district');
	pg_assert_same('35000', $card['postal_code']);
	pg_assert_false(isset($card['state']), 'Paraşüt has no state field');
	pg_assert_false(isset($card['is_abroad']), 'nothing the card did not carry before');

	// The country code is read as the ERP reads an account's.
	$order['billing_country'] = 'tr';
	pg_assert_same('İzmir', parasut_order_contact_attributes($order, '')['city']);

	// No billing country: the store's (erp_account_country()); a Turkish
	// store splits the address, a store that chose none keeps it folded.
	$order['billing_country'] = '';
	$card = parasut_order_contact_attributes($order, '', 'TR');
	pg_assert_same('Test Sokak 1 Daire 3', $card['address']);
	pg_assert_same('Konak', $card['district']);
	pg_assert_same('Test Sokak 1, Daire 3, Konak, İzmir, 35000', parasut_order_contact_attributes($order, '', '')['address']);

	// An empty field is left off rather than sent empty.
	$order['billing_country'] = 'TR';
	$order['billing_address_2'] = '';
	$order['billing_state'] = '';
	$order['billing_zip_code'] = '';
	$card = parasut_order_contact_attributes($order, '');
	pg_assert_same('Test Sokak 1', $card['address']);
	pg_assert_same('Konak', $card['district']);
	pg_assert_false(array_key_exists('city', $card), 'no province given');
	pg_assert_false(array_key_exists('postal_code', $card), 'no postcode given');

	$order['billing_address_1'] = '';
	pg_assert_false(array_key_exists('address', parasut_order_contact_attributes($order, '')), 'no street given');
}

// An address abroad goes as one folded address line (street, city, state,
// postcode, country) with no city, district or postal_code. The identity
// rules do not depend on the country.
function test_parasut_order_contact_attributes_keep_an_address_abroad_folded()
{
	$order = array(
		'billing_company' => 'Muster GmbH',
		'billing_first_name' => 'Max',
		'billing_last_name' => 'Muster',
		'billing_email_address' => 'm@example.test',
		'billing_phone_number' => '',
		'billing_address_1' => 'Hauptstr. 5',
		'billing_address_2' => '',
		'billing_city' => 'Berlin',
		'billing_state' => 'BE',
		'billing_zip_code' => '10115',
		'billing_country' => 'DE',
	);

	pg_assert_same(array(
		'account_type' => 'customer',
		'contact_type' => 'company',
		'name' => 'Muster GmbH',
		'email' => 'm@example.test',
		'phone' => '',
		'tax_number' => '123456789',
		'address' => 'Hauptstr. 5, Berlin, BE, 10115, DE',
	), parasut_order_contact_attributes($order, '123456789'));

	$order['billing_company'] = '';
	$person = parasut_order_contact_attributes($order, '');
	pg_assert_same('person', $person['contact_type']);
	pg_assert_same('Max Muster', $person['name']);
	pg_assert_false(array_key_exists('tax_number', $person));
	pg_assert_false(array_key_exists('city', $person));
	pg_assert_false(array_key_exists('district', $person));
	pg_assert_false(array_key_exists('postal_code', $person));
}

// The order screen's buttons open the buyer card with those attributes.
function test_parasut_sync_contact_writes_the_card_with_the_order_attributes()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function parasut_sync_contact(');
	pg_assert_true($start !== false, 'parasut_sync_contact() is found');

	$end = strpos($source, "\n}\n", $start);
	$body = substr($source, $start, $end - $start);

	pg_assert_contains('$identity = _parasut_order_identity($order);', $body);
	pg_assert_contains('parasut_order_contact_attributes($order, $identity)', $body);
	pg_assert_false(strpos($body, "'tckn'") !== false, 'no tckn attribute');

	// The address is read by the ERP's accounts code, loaded first also for
	// E-Shipment, which does not load it itself.
	$load = strpos($body, '_parasut_order_invoice_load();');
	pg_assert_true($load !== false, 'the ERP code is loaded');
	pg_assert_true($load < strpos($body, 'parasut_order_contact_attributes('), 'before the attributes are built');
}
