<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lines and totals of a typed document,
 * erp_manual_lines_build() (includes/erp/invoice_manual.php): the VAT and the
 * withholding taken once per group of lines with one VAT rate and one
 * withholding rate (erp_rate_vat()), the discounts before them, and the
 * totals the document is written with. Invoices, purchase invoices, quotes,
 * purchase orders, the external API and the incoming e-invoices all build
 * their lines here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/withholding.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/products.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/ubl.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/inbox.php');

// Lines as erp_manual_lines_build() takes them: [unit price, VAT %, withholding
// code], one of each.
function pg_test_erp_manual_lines($rows)
{
	$lines = array();

	foreach ($rows as $index => $row) {
		$lines[] = array(
			'description' => 'Line ' . ($index + 1),
			'quantity' => 1,
			'unit_price' => $row[0],
			'tax_rate' => $row[1],
			'withholding_code' => $row[2] ?? '',
		);
	}

	return $lines;
}

// Two lines of 1.02 at 20% carry 0.41 of VAT, taken on 2.04, not 0.20 + 0.20;
// the lines add up to it and the document's total is 2.45.
function test_erp_invoice_manual_takes_vat_on_the_rate_total()
{
	$built = erp_manual_lines_build(pg_test_erp_manual_lines(array(array(102, 20), array(102, 20))));

	pg_assert_same('', $built['error']);
	pg_assert_same(array(21, 20), array($built['lines'][0]['tax_total'], $built['lines'][1]['tax_total']));
	pg_assert_same(41, $built['totals']['tax_total']);
	pg_assert_same(245, $built['totals']['grand_total']);
}

// The invoices Paraşüt worked out in the demo company (2026-10-21), as
// [lines, VAT, withholding, total]: the same figures come out here.
function test_erp_invoice_manual_reproduces_the_provider_totals()
{
	$cases = array(
		array(array(array(105, 20, '601'), array(110, 20, '603')), 43, 24, 234),
		array(array(array(105, 20, '601'), array(110, 10, '601')), 32, 13, 234),
		array(array(array(107, 20, '601'), array(107, 20)), 42, 8, 248),
		array(array(array(103, 20, '601'), array(103, 20, '603')), 42, 23, 225),
		array(array(array(103, 20, '601'), array(103, 20, '601')), 41, 16, 231),
		array(array(array(1245, 10), array(1250, 1)), 138, 0, 2633),
	);

	foreach ($cases as $index => $case) {
		$built = erp_manual_lines_build(pg_test_erp_manual_lines($case[0]));

		pg_assert_same('', $built['error'], 'error, case ' . $index);
		pg_assert_same($case[1], $built['totals']['tax_total'], 'VAT, case ' . $index);
		pg_assert_same($case[2], $built['totals']['withholding_total'], 'withholding, case ' . $index);
		pg_assert_same($case[3], $built['totals']['grand_total'], 'total, case ' . $index);
	}
}

// The lines carry the document's figures: their VAT and withholding add up to
// the totals, and a line stays within a kurus of what it would carry alone.
function test_erp_invoice_manual_lines_add_up_to_the_totals()
{
	$built = erp_manual_lines_build(pg_test_erp_manual_lines(array(
		array(105, 20, '601'), array(110, 20, '603'), array(107, 20), array(107, 20), array(999, 10), array(1, 20, '601'),
	)));

	$vat = 0;
	$withholding = 0;

	foreach ($built['lines'] as $line) {
		$vat += $line['tax_total'];
		$withholding += $line['withholding_amount'];
		pg_assert_true(abs($line['tax_total'] - erp_apply_rate($line['line_total'] - $line['discount_amount'], $line['tax_rate'])) <= 1, 'line VAT of ' . $line['description']);
	}

	pg_assert_same($built['totals']['tax_total'], $vat);
	pg_assert_same($built['totals']['withholding_total'], $withholding);
	pg_assert_same($built['totals']['subtotal'] - $built['totals']['discount_total'] + $vat - $withholding, $built['totals']['grand_total']);
}

// The VAT is taken on what the discounts leave: 3 x 33.34 with a 10%
// campaign then 5% typed comes to 85.52 before VAT on each of two lines, and
// the group's VAT is 34.21 on 171.04, not 17.10 + 17.10.
function test_erp_invoice_manual_takes_vat_after_the_discounts()
{
	$line = array('description' => 'Pen', 'quantity' => 3, 'unit_price' => 3334, 'tax_rate' => 20,
		'offer_id' => 5, 'offer_discount_rate' => 10, 'discount_rate' => 5);
	$built = erp_manual_lines_build(array($line, $line));

	pg_assert_same(array(1000, 450), array($built['lines'][0]['offer_discount_amount'], $built['lines'][0]['discount_amount'] - $built['lines'][0]['offer_discount_amount']));
	pg_assert_same(8552, $built['lines'][0]['line_total'] - $built['lines'][0]['discount_amount']);
	pg_assert_same(3421, $built['totals']['tax_total']);
	pg_assert_same(array(1711, 1710), array($built['lines'][0]['tax_total'], $built['lines'][1]['tax_total']));
	pg_assert_same(17104 + 3421, $built['totals']['grand_total']);
}

// Rows with nothing typed in them are skipped and take no part in a group.
function test_erp_invoice_manual_skips_empty_rows()
{
	$lines = pg_test_erp_manual_lines(array(array(102, 20), array(102, 20)));
	array_splice($lines, 1, 0, array(array('description' => '', 'quantity' => '', 'unit_price' => 0, 'tax_rate' => 20)));

	$built = erp_manual_lines_build($lines);

	pg_assert_same(2, count($built['lines']));
	pg_assert_same(41, $built['totals']['tax_total']);
}

// A quantity with more places than the four it is stored with is priced as
// stored: 1.3333333333 x 1,500.00 is 1.3333 x 1,500.00 = 1,999.95, and the
// lines rebuilt from what was stored, as an issue rebuilds them, come to the
// same totals.
function test_erp_invoice_manual_prices_the_stored_quantity()
{
	$cases = array(
		array('1.3333333333', 150000, 199995, 239994),
		array(0.33333, 30000, 9999, 11999),
	);

	foreach ($cases as $index => $case) {
		$built = erp_manual_lines_build(array(
			array('description' => 'Work', 'quantity' => $case[0], 'unit_price' => $case[1], 'tax_rate' => 20),
		));

		pg_assert_same('', $built['error'], 'error, case ' . $index);
		pg_assert_same($case[2], $built['lines'][0]['line_total'], 'line total, case ' . $index);
		pg_assert_same($case[3], $built['totals']['grand_total'], 'total, case ' . $index);

		$stored = $built['lines'][0];
		$stored['quantity'] = number_format($stored['quantity'], 4, '.', '');
		$rebuilt = erp_manual_lines_build(array($stored));

		pg_assert_same($built['totals'], $rebuilt['totals'], 'rebuilt, case ' . $index);
	}
}

// A quantity that is nothing at four places is refused, not priced at zero.
function test_erp_invoice_manual_refuses_a_quantity_below_the_stored_places()
{
	$built = erp_manual_lines_build(array(
		array('description' => 'Dust', 'quantity' => 0.00004, 'unit_price' => 100, 'tax_rate' => 20),
	));

	pg_assert_same('lines[0][quantity]', $built['field']);
}

// An incoming line of 0.12345 t at 400,000.00 is 49,380.00. Stored at four
// places, 0.1235 x 400,000.00 would be 49,400.00, so the line folds into one
// unit of its amount with the supplier's figures in its text; one whose
// quantity fits four places keeps its quantity and price.
function test_erp_invoice_manual_inbox_line_checks_the_stored_quantity()
{
	$line = function ($no, $quantity, $price, $amount) {
		return array('no' => $no, 'name' => 'Steel', 'description' => '', 'seller_code' => '', 'buyer_code' => '',
			'quantity' => $quantity, 'unit_code' => 'TNE', 'price' => $price, 'line_extension' => $amount,
			'allowances' => array(), 'withholdings' => array(),
			'taxes' => array(array('code' => '0015', 'name' => 'KDV', 'rate' => 20, 'amount' => (int) round($amount / 5),
				'exemption_code' => '', 'exemption_reason' => '')));
	};

	$read = erp_edoc_inbox_lines(array('lines' => array(
		$line('1', 0.12345, '400000.00', 4938000),
		$line('2', 0.125, '400000.00', 5000000),
	)));

	pg_assert_same(array('1'), $read['folded']);
	pg_assert_same(array(1.0, 'C62', 4938000), array($read['lines'][0]['quantity'], $read['lines'][0]['unit_code'], $read['lines'][0]['unit_price']));
	pg_assert_contains('0.12345', $read['lines'][0]['description']);
	pg_assert_same(array(0.125, 'TNE', 40000000), array($read['lines'][1]['quantity'], $read['lines'][1]['unit_code'], $read['lines'][1]['unit_price']));

	$built = erp_manual_lines_build($read['lines']);
	pg_assert_same(array(4938000, 5000000), array($built['lines'][0]['line_total'], $built['lines'][1]['line_total']));
}
