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

function pg_test_parasut_vat_line($base, $vat, $overrides = array())
{
	return array_merge(array('product_id' => 0, 'description' => 'Satır', 'quantity' => '1.0000', 'unit_price' => $base, 'line_total' => $base, 'discount_rate' => '0.000', 'discount_amount' => 0, 'tax_rate' => '20.000', 'tax_total' => $vat, 'tax2_amount' => 0, 'withholding_rate' => '0.000'), $overrides);
}

// Paraşüt takes the VAT on each rate's whole amount, the ERP line by line.
// Where the two differ, the rate's amount at Paraşüt moves a kurus or so so
// that amount and VAT come to the ERP's total there.
function test_erp_edoc_parasut_vat_shift()
{
	// Agreeing VAT: nothing moves.
	pg_assert_same(array('lines' => array(), 'extra' => array(), 'refusal' => array()), erp_edoc_parasut_vat_shift(array(pg_test_parasut_vat_line(3995, 799), pg_test_parasut_vat_line(1250, 250))));

	// 1.02 + 1.02 at 20%: 0.40 line by line, 0.41 on 2.04. The amount goes
	// down a kurus (2.03 + 0.41 = 2.44) off the largest line.
	$down = erp_edoc_parasut_vat_shift(array(pg_test_parasut_vat_line(102, 20), pg_test_parasut_vat_line(102, 20)));
	pg_assert_same(array(0 => array('discount' => 1, 'unit_price' => 0)), $down['lines'], 'a kurus off');
	pg_assert_same(array(), $down['refusal']);

	// 1.03 + 1.03: 0.42 line by line, 0.41 on 2.06. The amount goes up a
	// kurus (2.07 + 0.41 = 2.48): on the price of a single unit.
	$up = erp_edoc_parasut_vat_shift(array(pg_test_parasut_vat_line(103, 21), pg_test_parasut_vat_line(103, 21)));
	pg_assert_same(array(0 => array('discount' => 0, 'unit_price' => 1), ), $up['lines'], 'a kurus on');

	// Up again, but a discounted line gives back a kurus of its discount.
	$discounted = pg_test_parasut_vat_line(2000, 360, array('quantity' => '2.0000', 'unit_price' => 1000, 'line_total' => 2000, 'discount_amount' => 200));
	$discounted['line_total'] = 2000;
	$back = erp_edoc_parasut_vat_shift(array($discounted, pg_test_parasut_vat_line(103, 21), pg_test_parasut_vat_line(103, 21)));
	pg_assert_same(-1, $back['lines'][0]['discount'] ?? null, 'discount given back');

	// Up with only several units and no discount: a rounding line at the rate.
	$units = erp_edoc_parasut_vat_shift(array(pg_test_parasut_vat_line(206, 42, array('quantity' => '2.0000', 'unit_price' => 103))));
	pg_assert_same(array(), $units['lines']);
	pg_assert_same(1, $units['extra'][0]['kurus'] ?? null, 'rounding line');
	pg_assert_same(20.0, $units['extra'][0]['rate'] ?? null);

	// 39.95 + 7.99 and shipping 8.33 + 1.66: 57.93, three kurus off a
	// multiple of six, which no amount at 20% comes to.
	$out = erp_edoc_parasut_vat_shift(array(pg_test_parasut_vat_line(3995, 799), pg_test_parasut_vat_line(833, 166)));
	pg_assert_same(array('rate' => 20.0, 'total' => 5793, 'below' => 5792, 'above' => 5794), $out['refusal'], 'unreachable');

	// Each rate on its own; a withheld or untaxed rate is left alone.
	$rates = erp_edoc_parasut_vat_shift(array(pg_test_parasut_vat_line(102, 20), pg_test_parasut_vat_line(102, 20), pg_test_parasut_vat_line(1250, 125, array('tax_rate' => '10.000')), pg_test_parasut_vat_line(500, 0, array('tax_rate' => '0.000'))));
	pg_assert_same(array(0), array_keys($rates['lines']), 'only the 20% lines move');
	$withheld = erp_edoc_parasut_vat_shift(array(pg_test_parasut_vat_line(102, 20, array('withholding_rate' => '40.000')), pg_test_parasut_vat_line(102, 20)));
	pg_assert_same(array(), $withheld['lines'], 'withheld rate left alone');
	pg_assert_same(array(), $withheld['refusal']);

	// A VAT further off than line roundings take it is no rounding: nothing
	// moves, and the total check says what differs.
	$far = erp_edoc_parasut_vat_shift(array(pg_test_parasut_vat_line(1000, 260)));
	pg_assert_same(array('lines' => array(), 'extra' => array(), 'refusal' => array()), $far, 'VAT 0.60 off');
	$nothing = erp_edoc_parasut_vat_shift(array(pg_test_parasut_vat_line(0, 1, array('line_total' => 0))));
	pg_assert_same(array('lines' => array(), 'extra' => array(), 'refusal' => array()), $nothing, 'no amount');
}

// The amount Paraşüt's VAT comes to a total from; at 20% one total in six,
// at 10% one in eleven, has none.
function test_erp_edoc_parasut_rate_amount()
{
	pg_assert_same(203, erp_edoc_parasut_rate_amount(244, 20));
	pg_assert_same(4827, erp_edoc_parasut_rate_amount(5792, 20));
	pg_assert_same(4828, erp_edoc_parasut_rate_amount(5794, 20));
	pg_assert_same(null, erp_edoc_parasut_rate_amount(5793, 20), '57.93 at 20%');
	pg_assert_same(1245, erp_edoc_parasut_rate_amount(1370, 10), '12.45 + 1.25');
	pg_assert_same(1250, erp_edoc_parasut_rate_amount(1263, 1), '12.50 + 0.13');
	pg_assert_same(0, erp_edoc_parasut_rate_amount(0, 20));

	foreach (array(20 => 1000, 10 => 546, 1 => 59) as $rate => $missed) {
		$none = 0;

		for ($total = 1; $total <= 6000; $total++) {
			$none += (erp_edoc_parasut_rate_amount($total, $rate) === null) ? 1 : 0;
		}

		pg_assert_same($missed, $none, 'totals none reaches at ' . $rate . '%');
	}
}

// Paraşüt's arithmetic on a sales_invoices body, as seen live: each line's
// amount less its discount, then each rate's VAT on the sum of its lines,
// half up to the kurus.
function pg_test_parasut_simulated_total($details)
{
	$bases = array();

	foreach ($details as $detail) {
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

// Over many invoices of a few lines - typed prices with or without a
// discount, VAT-inclusive prices split as an order's are - the lines as they
// go to Paraşüt come to the ERP's total by Paraşüt's arithmetic, unless a
// rate's total is one no amount reaches.
function test_erp_edoc_parasut_vat_shift_reaches_the_total()
{
	mt_srand(20261021);
	$refused = 0;
	$moved = 0;

	for ($run = 0; $run < 4000; $run++) {
		$lines = array();
		$erp_total = 0;

		for ($i = 0, $count = mt_rand(1, 4); $i < $count; $i++) {
			$rate = array(1, 10, 20, 20, 20)[mt_rand(0, 4)];

			if (mt_rand(0, 2) === 0) {
				$split = erp_split_gross(mt_rand(50, 30000), $rate);
				$line = pg_test_parasut_vat_line($split['net'], $split['tax'], array('tax_rate' => (string) $rate));
			} else {
				$quantity = mt_rand(1, 3);
				$price = (mt_rand(0, 9) === 0) ? mt_rand(1, 30) : mt_rand(100, 20000);
				$line_total = $price * $quantity;
				$discount_rate = (mt_rand(0, 2) === 0) ? mt_rand(1, 30) : 0;
				$discount = erp_apply_rate($line_total, $discount_rate);
				$line = pg_test_parasut_vat_line($line_total, erp_apply_rate($line_total - $discount, $rate), array(
					'quantity' => $quantity . '.0000',
					'unit_price' => $price,
					'discount_rate' => $discount_rate . '.000',
					'discount_amount' => $discount,
					'tax_rate' => (string) $rate,
				));
			}

			$lines[] = $line;
			$erp_total += $line['line_total'] - $line['discount_amount'] + $line['tax_total'];
		}

		$shift = erp_edoc_parasut_vat_shift($lines);

		if ($shift['refusal'] !== array()) {
			pg_assert_same(null, erp_edoc_parasut_rate_amount($shift['refusal']['total'], $shift['refusal']['rate']), 'refused only when unreachable');
			$refused++;
			continue;
		}

		$moved += (($shift['lines'] !== array()) || ($shift['extra'] !== array())) ? 1 : 0;
		$payload = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(), $lines, '555');
		pg_assert_false(isset($payload['error']), 'run ' . $run);
		pg_assert_same($erp_total, pg_test_parasut_simulated_total($payload['data']['relationships']['details']['data']), 'run ' . $run);
	}

	pg_assert_true(($refused > 0) && ($moved > 0), 'both paths taken: ' . $moved . ' moved, ' . $refused . ' refused');
}

// The payload carries the moved kurus: a discount amount, a unit price, a
// rounding line after every line.
function test_erp_edoc_parasut_invoice_payload_vat_shift()
{
	$down = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(), array(pg_test_parasut_vat_line(102, 20), pg_test_parasut_vat_line(102, 20)), '555')['data']['relationships']['details']['data'];
	pg_assert_same(2, count($down));
	pg_assert_same('amount', $down[0]['attributes']['discount_type']);
	pg_assert_same(0.01, $down[0]['attributes']['discount_value'], 'a kurus off the first');
	pg_assert_same(0.0, $down[1]['attributes']['discount_value']);

	$up = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(), array(pg_test_parasut_vat_line(103, 21), pg_test_parasut_vat_line(103, 21)), '555')['data']['relationships']['details']['data'];
	pg_assert_same(1.04, $up[0]['attributes']['unit_price'], 'a kurus on the first price');
	pg_assert_same(1.03, $up[1]['attributes']['unit_price']);

	// A line that moves keeps its discount as the amount: 15% of 0.04 would
	// be a kurus where 15% of 0.03 was none.
	$rated = array(pg_test_parasut_vat_line(3, 1, array('discount_rate' => '15.000')), pg_test_parasut_vat_line(42339, 8468, array('quantity' => '3.0000', 'unit_price' => 14113)), pg_test_parasut_vat_line(4, 1, array('quantity' => '2.0000', 'unit_price' => 2, 'discount_rate' => '7.000')));
	$detail = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(), $rated, '555')['data']['relationships']['details']['data'][0]['attributes'];
	pg_assert_same(0.04, $detail['unit_price']);
	pg_assert_same('amount', $detail['discount_type']);
	pg_assert_same(0.0, $detail['discount_value']);

	$units = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(), array(pg_test_parasut_vat_line(206, 42, array('quantity' => '2.0000', 'unit_price' => 103))), '555')['data']['relationships']['details']['data'];
	pg_assert_same(2, count($units), 'rounding line after the line');
	pg_assert_same(0.01, $units[1]['attributes']['unit_price']);
	pg_assert_same(20.0, $units[1]['attributes']['vat_rate']);
	pg_assert_same('9001', $units[1]['relationships']['product']['data']['id']);

	// A rate that can be reached is no refusal (the unreachable one is
	// test_erp_edoc_parasut_vat_shift's; its words need the currency table).
	pg_assert_same('', erp_edoc_parasut_total_refusal(array(pg_test_parasut_vat_line(102, 20), pg_test_parasut_vat_line(102, 20)), 'TRY'));
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

// The sales invoice has a line for each ERP line, then the rounding lines of
// erp_edoc_parasut_vat_shift(). The hand-over checks Paraşüt's line ids
// against that count, so a withheld invoice with a rounding line still goes.
function test_erp_edoc_parasut_detail_count()
{
	pg_assert_same(2, erp_edoc_parasut_detail_count(array(pg_test_parasut_vat_line(3995, 799), pg_test_parasut_vat_line(1250, 250))));
	pg_assert_same(0, erp_edoc_parasut_detail_count(array()));

	// A withheld line at 20% (601, 4/10) and two lines of 5 x 0.19 at 10%
	// with their VAT taken line by line: 0.10 + 0.10 against 0.19 on 1.90,
	// so the 10% rate needs a rounding line.
	$lines = array(
		pg_test_parasut_vat_line(10000, 2000, array('withholding_rate' => '40.000', 'withholding_code' => '601')),
		pg_test_parasut_vat_line(95, 10, array('quantity' => '5.0000', 'unit_price' => 19, 'tax_rate' => '10.000')),
		pg_test_parasut_vat_line(95, 10, array('quantity' => '5.0000', 'unit_price' => 19, 'tax_rate' => '10.000')),
	);
	$details = erp_edoc_parasut_invoice_payload(pg_test_parasut_invoice(array('invoice_type' => 'TEVKIFAT')), $lines, '555')['data']['relationships']['details']['data'];
	pg_assert_same(4, count($details), 'the rounding line is written');
	pg_assert_same(4, erp_edoc_parasut_detail_count($lines), 'and counted');

	// The ERP lines keep the first ids; the rounding line's comes last.
	$attributes = erp_edoc_parasut_edoc_attributes(pg_test_parasut_invoice(), $lines, 'einvoice', 'urn:mail:defaultpk@example.com', array('5002', '5003', '5004', '5005'));
	pg_assert_same(array(array('detail_id' => 5002, 'vat_withholding_code' => '601')), $attributes['vat_withholding_params'] ?? null);

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');
	$from = (int) strpos($source, 'function erp_edoc_parasut_submit_invoice(');
	$section = substr($source, $from, strpos($source, "\n}\n", $from) - $from);
	pg_assert_contains('count($detail_ids) !== erp_edoc_parasut_detail_count($lines)', $section, 'the hand-over counts the rounding lines');
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

	// A failed conversion job's words come first, as their own sentence.
	$failed = erp_edoc_parasut_unconverted_message(pg_test_parasut_invoice(array('edoc_status' => 'sent', 'parasut_job_id' => '1234')), 'Alıcı adresi GİB tarafından reddedildi');
	pg_assert_same('Alıcı adresi GİB tarafından reddedildi. ', mb_substr($failed, 0, 40), 'the reason, closed with a full stop');
	pg_assert_contains('hand it to GİB again', $failed);
	pg_assert_same(0, strpos(erp_edoc_parasut_unconverted_message(pg_test_parasut_invoice(array('edoc_status' => 'sent')), 'Done.'), 'Done. The'), 'no second full stop');
}

// A conversion job's answer: running, done, or failed with Paraşüt's words,
// whatever shape its errors come in.
function test_erp_edoc_parasut_job_reading()
{
	pg_assert_same(array('state' => 'running', 'message' => ''), erp_edoc_parasut_job_reading(array('status' => 'pending')));
	pg_assert_same(array('state' => 'running', 'message' => ''), erp_edoc_parasut_job_reading(array('status' => 'running')));
	pg_assert_same(array('state' => 'running', 'message' => ''), erp_edoc_parasut_job_reading(array()));
	pg_assert_same(array('state' => 'done', 'message' => ''), erp_edoc_parasut_job_reading(array('status' => 'done', 'errors' => array())));

	pg_assert_same(
		array('state' => 'error', 'message' => 'Alıcı adresi GİB tarafından reddedildi'),
		erp_edoc_parasut_job_reading(array('status' => 'error', 'errors' => array('Alıcı adresi GİB tarafından reddedildi')))
	);
	pg_assert_same(
		array('state' => 'error', 'message' => 'Geçersiz alan: VKN hatalı | Zaman aşımı'),
		erp_edoc_parasut_job_reading(array('status' => 'error', 'errors' => array(
			array('title' => 'Geçersiz alan', 'detail' => 'VKN hatalı', 'source' => array('pointer' => '/data/attributes/vkn')),
			array('title' => 'Zaman aşımı', 'detail' => 'Zaman aşımı'),
			'Zaman aşımı',
		))),
		'JSON:API error objects, a repeated line once, no notice from a nested array'
	);
	pg_assert_same('error', erp_edoc_parasut_job_reading(array('status' => 'error', 'errors' => array(array('code' => 'E1', 'meta' => array('x' => 1)))))['state']);
	pg_assert_same('E1', erp_edoc_parasut_job_reading(array('status' => 'error', 'errors' => array(array('code' => 'E1', 'meta' => array('x' => 1)))))['message']);
	pg_assert_same('Paraşüt could not convert the invoice into an e-document.', erp_edoc_parasut_job_reading(array('status' => 'failed'))['message']);
}

// A create Paraşüt may have written all the same: no answer, a 5xx, a
// success without a record; its own refusals (4xx, 429) wrote nothing.
function test_erp_edoc_parasut_answer_unsure()
{
	pg_assert_true(erp_edoc_parasut_answer_unsure(array('http_code' => 0, 'error' => 'Operation timed out after 40000 milliseconds')), 'a timeout');
	pg_assert_true(erp_edoc_parasut_answer_unsure(array('http_code' => 0, 'error' => '')), 'no answer');
	pg_assert_true(erp_edoc_parasut_answer_unsure(array('http_code' => 504, 'error' => '')), 'a gateway timeout');
	pg_assert_true(erp_edoc_parasut_answer_unsure(array('http_code' => 500, 'error' => '')));
	pg_assert_true(erp_edoc_parasut_answer_unsure(array('http_code' => 503, 'error' => '')));
	pg_assert_true(erp_edoc_parasut_answer_unsure(array('http_code' => 201, 'error' => '', 'body' => null, 'raw' => '<html>')), 'a success without a record');
	pg_assert_true(erp_edoc_parasut_answer_unsure(array('http_code' => 200, 'error' => 'transfer closed with outstanding read data remaining')), 'a cut answer');
	pg_assert_false(erp_edoc_parasut_answer_unsure(array('http_code' => 422, 'error' => '')), 'a refusal');
	pg_assert_false(erp_edoc_parasut_answer_unsure(array('http_code' => 400, 'error' => '')));
	pg_assert_false(erp_edoc_parasut_answer_unsure(array('http_code' => 429, 'error' => '')), 'refused unprocessed');
}

// The record an earlier try wrote: same description, date and contact, not
// cancelled; nothing without a description to go by.
function test_erp_edoc_parasut_written_matches()
{
	$payload = array('data' => array('attributes' => array('description' => 'PGF2026000000042 / Sipariş #1001', 'issue_date' => '2026-10-19')));
	$item = function ($id, $attributes, $contact = '7001') {
		return array('id' => $id, 'type' => 'sales_invoices', 'attributes' => $attributes + array('description' => 'PGF2026000000042 / Sipariş #1001', 'issue_date' => '2026-10-19', 'item_type' => 'invoice', 'net_total' => '1080.0'),
			'relationships' => array('contact' => array('data' => array('id' => $contact, 'type' => 'contacts'))));
	};
	$body = array('data' => array(
		$item('5001', array()),
		$item('5002', array('description' => 'PGF2026000000043')),
		$item('5003', array('issue_date' => '2026-10-20')),
		$item('5004', array('item_type' => 'cancelled')),
		$item('5005', array(), '7002'),
		$item('', array()),
		'x',
	));

	$found = erp_edoc_parasut_written_matches($body, $payload, '7001');
	pg_assert_same(array('5001'), array_map('strval', array_keys($found)));
	pg_assert_same('1080.0', $found['5001']['net_total'], 'the attributes come along for the total check');

	pg_assert_same(array(), erp_edoc_parasut_written_matches($body, array('data' => array('attributes' => array('description' => '', 'issue_date' => '2026-10-19'))), '7001'));
	pg_assert_same(array(), erp_edoc_parasut_written_matches(null, $payload, '7001'));
	pg_assert_same(array('5001'), array_map('strval', array_keys(erp_edoc_parasut_written_matches(array('data' => array($item('5001', array('issue_date' => '2026-10-19T00:00:00+03:00')))), $payload, '7001'))), 'a date with a time');

	// A record that is already an e-document (the issued one of a rejected
	// invoice sent again) and the row's own earlier record are not the
	// unanswered try's.
	$issued = $item('5006', array());
	$issued['relationships']['active_e_document'] = array('data' => array('id' => '8001', 'type' => 'e_archives'));
	$no_document = $item('5007', array());
	$no_document['relationships']['active_e_document'] = array('data' => null);
	$body = array('data' => array($issued, $no_document, $item('5008', array())));
	pg_assert_same(array('5007'), array_map('strval', array_keys(erp_edoc_parasut_written_matches($body, $payload, '7001', '5008'))), 'issued and own earlier records are skipped');
	pg_assert_same(array('5007', '5008'), array_map('strval', array_keys(erp_edoc_parasut_written_matches($body, $payload, '7001'))), 'no id to skip');
}

// The unanswered-try mark keeps the date and contact the request carried, so
// the search does not follow a redate or a party fix made in between.
function test_erp_edoc_parasut_unsure_mark()
{
	$mark = erp_edoc_parasut_unsure_mark('2026-10-19', '7001');
	pg_assert_same('?2026-10-19:7001', $mark);
	pg_assert_same(array('date' => '2026-10-19', 'contact_id' => '7001'), erp_edoc_parasut_unsure_read($mark), 'round trip');
	pg_assert_same(array('date' => '', 'contact_id' => ''), erp_edoc_parasut_unsure_read('?'), 'a bare mark from before');
	pg_assert_same(null, erp_edoc_parasut_unsure_read(''), 'no mark');
	pg_assert_same(null, erp_edoc_parasut_unsure_read('5001'), 'a real id');
	pg_assert_same(null, erp_edoc_parasut_unsure_read(null));

	pg_assert_true(strlen(erp_edoc_parasut_unsure_mark('2026-10-19', str_repeat('9', 40))) <= 32, 'fits the column');
	pg_assert_same(array('date' => '2026-10-19', 'contact_id' => ''), erp_edoc_parasut_unsure_read(erp_edoc_parasut_unsure_mark('2026-10-19', str_repeat('9', 40))), 'a contact id that would be cut is left out, not cut');
	pg_assert_same(array('date' => '', 'contact_id' => '7001'), erp_edoc_parasut_unsure_read(erp_edoc_parasut_unsure_mark('19.10.2026', '7001')), 'a malformed date is left out');
	pg_assert_same(array('date' => '', 'contact_id' => '7001'), erp_edoc_parasut_unsure_read('?2026-1-9:7001'), 'a malformed stored date is ignored');
	pg_assert_same(array('date' => '2026-10-19', 'contact_id' => ''), erp_edoc_parasut_unsure_read('?2026-10-19:70a1'), 'a malformed stored contact is ignored');
}

// Only a draft waiting at Paraşüt is handed to GİB: an error row that keeps
// a record whose total is not the ERP's, a rejected or a sent one is not.
function test_erp_edoc_parasut_submit_refusal()
{
	pg_assert_same('', erp_edoc_parasut_submit_refusal(array('edoc_status' => 'created')));

	foreach (array('error', 'rejected', 'sent', 'accepted', 'none', '') as $status) {
		pg_assert_true(erp_edoc_parasut_submit_refusal(array('edoc_status' => $status)) !== '', $status);
	}

	pg_assert_true(erp_edoc_parasut_submit_refusal(array()) !== '', 'no status');
}

// The search answer: more pages than were read and nothing found is not
// "never written".
function test_erp_edoc_parasut_search_incomplete()
{
	pg_assert_false(erp_edoc_parasut_search_incomplete(array(), 3, 3), 'every page read');
	pg_assert_false(erp_edoc_parasut_search_incomplete(array(), 1, 1));
	pg_assert_true(erp_edoc_parasut_search_incomplete(array(), 20, 21), 'pages left unread');
	pg_assert_false(erp_edoc_parasut_search_incomplete(array('5001' => array()), 20, 21), 'found among the newest');
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

// Paraşüt takes no line below zero: a sales invoice with a -0.52 line was
// answered 422 ("the unit price must be equal to or greater than 0", the same
// for its VAT and discount). Such an invoice is refused before anything is
// sent, naming the line.
function test_erp_edoc_parasut_negative_line_refusal()
{
	$goods = pg_test_parasut_vat_line(103, 21, array('description' => 'Deri Kalem Kılıfı'));
	$credit = pg_test_parasut_vat_line(-52, -10, array('description' => 'Sadakat indirimi'));

	pg_assert_same('', erp_edoc_parasut_negative_line_refusal(array($goods)));
	pg_assert_same('', erp_edoc_parasut_negative_line_refusal(array()));
	pg_assert_same('', erp_edoc_parasut_negative_line_refusal(array(pg_test_parasut_vat_line(0, 0))), 'a free line');

	$refusal = erp_edoc_parasut_negative_line_refusal(array($goods, $credit));
	pg_assert_contains('"Sadakat indirimi"', $refusal);
	pg_assert_true(strpos($refusal, 'Deri Kalem') === false, 'only the line below zero is named');

	// A discount below zero is refused as well (a credit's share of the
	// order discount), even on a line priced above zero.
	$share = pg_test_parasut_vat_line(1000, 199, array('discount_amount' => -3));
	pg_assert_true(erp_edoc_parasut_negative_line_refusal(array($share)) !== '');
}

// The send stops at that refusal: no buyer card is looked for or opened.
function test_erp_edoc_parasut_send_invoice_refuses_a_line_below_zero()
{
	$invoice = array('id' => 1, 'direction' => 'sales', 'doc_type' => 'invoice', 'invoice_type' => 'SATIS', 'currency' => 'TRY');
	$lines = array(pg_test_parasut_vat_line(103, 21), pg_test_parasut_vat_line(-52, -10, array('description' => 'Kupon')));

	$result = erp_edoc_parasut_send_invoice($invoice, $lines);

	pg_assert_same(false, $result['success']);
	pg_assert_same(erp_edoc_parasut_negative_line_refusal($lines), $result['error']);
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

	// A rounding line goes on the default product/service too.
	$units = pg_test_parasut_vat_line(206, 42, array('product_id' => 543, 'quantity' => '2.0000', 'unit_price' => 103));
	pg_assert_contains('"Rounding difference"', erp_edoc_parasut_product_refusal(array($units), ''));
	pg_assert_same('', erp_edoc_parasut_product_refusal(array($units), '9001'));
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

// Many consumers share the final consumer's number, and the e-Arşiv is
// printed with the card's address and mailed to the card's e-mail: a card
// of that number is the buyer's only when it holds the buyer's name,
// address, district, city and e-mail. Another person of the same name gets
// a card of their own.
function test_erp_edoc_parasut_final_consumer_card_matches()
{
	$party = pg_test_parasut_party(array(
		'title' => 'Ayşe Demir', 'is_person' => true, 'tax_number' => '', 'tax_office' => '', 'email' => 'ayse.k@example.com',
		'address' => 'Kızılay Mah. 1. Sok. 3', 'district' => 'Çankaya', 'city' => 'Ankara', 'postcode' => '06420',
	));
	$card = erp_edoc_parasut_contact_attributes($party, '11111111111');

	pg_assert_true(erp_edoc_parasut_final_consumer_card_matches($card, $party, '11111111111'), 'the card opened for this buyer');
	pg_assert_true(erp_edoc_parasut_final_consumer_card_matches(array_merge($card, array('name' => ' ayşe demir ', 'email' => 'Ayse.K@Example.COM')), $party, '11111111111'), 'case and outer spaces');

	// The same name at another address, district or city, or with another
	// e-mail, is another buyer.
	pg_assert_false(erp_edoc_parasut_final_consumer_card_matches(array_merge($card, array('address' => 'Alsancak Mah. 1453 Sok. 9', 'district' => 'Konak', 'city' => 'İzmir')), $party, '11111111111'), 'another city');
	pg_assert_false(erp_edoc_parasut_final_consumer_card_matches(array_merge($card, array('address' => 'Kızılay Mah. 2. Sok. 5')), $party, '11111111111'), 'another address');
	pg_assert_false(erp_edoc_parasut_final_consumer_card_matches(array_merge($card, array('district' => 'Keçiören')), $party, '11111111111'), 'another district');
	pg_assert_false(erp_edoc_parasut_final_consumer_card_matches(array_merge($card, array('email' => 'aysed@example.org')), $party, '11111111111'), 'another e-mail');
	pg_assert_false(erp_edoc_parasut_final_consumer_card_matches(array_merge($card, array('name' => 'Ayşe Yılmaz')), $party, '11111111111'), 'another name');

	// A card with an e-mail the buyer did not give would mail the document
	// there; with none on either side the card is the buyer's.
	$no_email = array_merge($party, array('email' => ''));
	pg_assert_false(erp_edoc_parasut_final_consumer_card_matches($card, $no_email, '11111111111'), 'an e-mail the buyer did not give');
	pg_assert_true(erp_edoc_parasut_final_consumer_card_matches(array_merge($card, array('email' => null)), $no_email, '11111111111'), 'no e-mail on either side');
}

// The search by number takes a final-consumer card only after that check.
function test_erp_edoc_parasut_contact_for_checks_final_consumer_card()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');
	$from = strpos($source, 'function erp_edoc_parasut_contact_for(');
	$section = substr($source, $from, strpos($source, "\n}\n", $from) - $from);

	pg_assert_contains('if ($final_consumer && !erp_edoc_parasut_final_consumer_card_matches($attributes, $party, $tax_number)) {', $section);
}

// A stand-in for erp_edoc_parasut_request() that answers GET contacts out of
// $cards, a page of the size asked for at a time, and POST contacts with a
// new card 7999. Every call is written into $calls as "METHOD path". Page
// $fail_page, when given, answers 500; $pages, when given, is the page count
// it claims in meta.
function pg_test_parasut_contacts_request($cards, &$calls, $fail_page = 0, $pages = 0)
{
	return function ($method, $path, $body = null, $context = array()) use ($cards, &$calls, $fail_page, $pages) {
		$calls[] = $method . ' ' . $path;
		$answer = array('http_code' => 200, 'body' => null, 'raw' => '', 'error' => '', 'duration_ms' => 1, 'login_error' => '');

		if ($method === 'POST') {
			$answer['http_code'] = 201;
			$answer['body'] = array('data' => array('id' => '7999', 'type' => 'contacts', 'attributes' => $body['data']['attributes']));
		} else {
			$query = array();
			parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
			$page = (int) $query['page']['number'];
			$size = (int) $query['page']['size'];

			if ($page === $fail_page) {
				$answer['http_code'] = 500;
				$answer['body'] = array('errors' => array(array('title' => 'Sunucu hatası')));
			} else {
				$answer['body'] = array(
					'data' => array_slice($cards, ($page - 1) * $size, $size),
					'meta' => array('current_page' => $page, 'total_count' => count($cards),
						'total_pages' => ($pages > 0) ? $pages : max(1, (int) ceil(count($cards) / $size))),
				);
			}
		}

		$answer['raw'] = json_encode($answer['body']);

		return $answer;
	};
}

// $count final-consumer cards of other buyers, newest (highest id) first, as
// Paraşüt lists them with sort=-id.
function pg_test_parasut_consumer_cards($count, $first_id)
{
	$cards = array();

	for ($i = 0; $i < $count; $i++) {
		$party = pg_test_parasut_party(array('title' => 'Tüketici ' . $i, 'is_person' => true, 'tax_number' => '', 'tax_office' => '', 'email' => 'tuketici' . $i . '@example.com'));
		$cards[] = array('id' => (string) ($first_id - $i), 'type' => 'contacts', 'attributes' => erp_edoc_parasut_contact_attributes($party, '11111111111') + array('archived' => false));
	}

	return $cards;
}

function pg_test_parasut_consumer_party()
{
	return pg_test_parasut_party(array(
		'title' => 'Ayşe Demir', 'is_person' => true, 'tax_number' => '', 'tax_office' => '', 'email' => 'ayse.k@example.com',
		'address' => 'Kızılay Mah. 1. Sok. 3', 'district' => 'Çankaya', 'city' => 'Ankara', 'postcode' => '06420',
	));
}

function pg_test_parasut_calls_of($calls, $method)
{
	return count(array_filter($calls, function ($call) use ($method) {
		return strpos($call, $method . ' ') === 0;
	}));
}

// With more final-consumer cards than one page holds, the buyer's own card
// on a later page is found and no second card is opened; the reading stops
// at the page that holds it.
function test_erp_edoc_parasut_contact_for_reads_later_pages()
{
	$party = pg_test_parasut_consumer_party();
	$cards = pg_test_parasut_consumer_cards(70, 9000);
	array_splice($cards, 30, 0, array(array('id' => '5005', 'type' => 'contacts', 'attributes' => erp_edoc_parasut_contact_attributes($party, '11111111111') + array('archived' => false))));
	$calls = array();

	$contact = erp_edoc_parasut_contact_for($party, '11111111111', array(), pg_test_parasut_contacts_request($cards, $calls));

	pg_assert_same(array('id' => '5005', 'error' => ''), $contact);
	pg_assert_same(2, count($calls), 'pages 1 and 2, not 3');
	pg_assert_same(0, pg_test_parasut_calls_of($calls, 'POST'), 'no card opened');
	pg_assert_contains('filter[tax_number]=11111111111&sort=-id&page[number]=1&page[size]=25', $calls[0]);
	pg_assert_contains('page[number]=2&page[size]=25', $calls[1]);
}

// No page holds the buyer's card: every page is read, then a card is opened.
function test_erp_edoc_parasut_contact_for_opens_a_card_after_the_last_page()
{
	$calls = array();

	$contact = erp_edoc_parasut_contact_for(pg_test_parasut_consumer_party(), '11111111111', array(), pg_test_parasut_contacts_request(pg_test_parasut_consumer_cards(60, 9000), $calls));

	pg_assert_same(array('id' => '7999', 'error' => ''), $contact);
	pg_assert_same(3, pg_test_parasut_calls_of($calls, 'GET'));
	pg_assert_same(1, pg_test_parasut_calls_of($calls, 'POST'));
}

// The reading stops after ERP_EDOC_PARASUT_FIND_PAGES pages; the card is
// then opened as for a buyer Paraşüt does not know.
function test_erp_edoc_parasut_contact_for_stops_at_the_page_cap()
{
	$calls = array();

	$contact = erp_edoc_parasut_contact_for(pg_test_parasut_consumer_party(), '11111111111', array(), pg_test_parasut_contacts_request(pg_test_parasut_consumer_cards(60, 9000), $calls, 0, (int) ERP_EDOC_PARASUT_FIND_PAGES + 5));

	pg_assert_same('7999', $contact['id']);
	pg_assert_same((int) ERP_EDOC_PARASUT_FIND_PAGES, pg_test_parasut_calls_of($calls, 'GET'));
	pg_assert_same(1, pg_test_parasut_calls_of($calls, 'POST'));
}

// A page that cannot be read refuses the send, as the first page does: the
// buyer's card may be on it, so no card is opened.
function test_erp_edoc_parasut_contact_for_refuses_on_a_failed_page()
{
	$party = pg_test_parasut_consumer_party();
	$cards = pg_test_parasut_consumer_cards(60, 9000);
	$cards[55] = array('id' => '5005', 'type' => 'contacts', 'attributes' => erp_edoc_parasut_contact_attributes($party, '11111111111'));

	foreach (array(1, 2) as $fail_page) {
		$calls = array();
		$contact = erp_edoc_parasut_contact_for($party, '11111111111', array(), pg_test_parasut_contacts_request($cards, $calls, $fail_page));

		pg_assert_same('', $contact['id'], 'page ' . $fail_page);
		pg_assert_contains('Paraşüt would not search its contacts: Sunucu hatası', $contact['error']);
		pg_assert_same($fail_page, count($calls), 'nothing read or written after page ' . $fail_page);
	}

	// A sign-in that fails on a later page is said as it is.
	$calls = array();
	$base = pg_test_parasut_contacts_request($cards, $calls);
	$login = function ($method, $path, $body = null, $context = array()) use ($base) {
		$answer = $base($method, $path, $body, $context);

		return (strpos($path, 'page[number]=2') !== false) ? array_merge($answer, array('http_code' => 0, 'login_error' => 'Oturum açılamadı')) : $answer;
	};

	pg_assert_same(array('id' => '', 'error' => 'Oturum açılamadı'), erp_edoc_parasut_contact_for($party, '11111111111', array(), $login));
	pg_assert_same(0, pg_test_parasut_calls_of($calls, 'POST'));
}

// No card under the number (404) opens one; the card of a real tax number
// is taken on the number alone, as before.
function test_erp_edoc_parasut_contact_for_number_rules()
{
	$calls = array();
	$none = function ($method, $path, $body = null, $context = array()) use (&$calls) {
		$calls[] = $method . ' ' . $path;

		return ($method === 'POST')
			? array('http_code' => 201, 'body' => array('data' => array('id' => '7999')), 'raw' => '{}', 'error' => '', 'duration_ms' => 1, 'login_error' => '')
			: array('http_code' => 404, 'body' => array('errors' => array()), 'raw' => '{}', 'error' => '', 'duration_ms' => 1, 'login_error' => '');
	};

	pg_assert_same(array('id' => '7999', 'error' => ''), erp_edoc_parasut_contact_for(pg_test_parasut_consumer_party(), '11111111111', array(), $none));
	pg_assert_same(2, count($calls));

	$company = array('id' => '6001', 'type' => 'contacts', 'attributes' => array('name' => 'Başka Unvan A.Ş.', 'tax_number' => '1234567808', 'archived' => false));
	$archived = array('id' => '6002', 'type' => 'contacts', 'attributes' => array('name' => 'Deneme Ticaret A.Ş.', 'tax_number' => '1234567808', 'archived' => true));
	$calls = array();

	pg_assert_same(array('id' => '6001', 'error' => ''), erp_edoc_parasut_contact_for(pg_test_parasut_party(), '1234567808', array(), pg_test_parasut_contacts_request(array($archived, $company), $calls)));
	pg_assert_same(1, count($calls));
}
