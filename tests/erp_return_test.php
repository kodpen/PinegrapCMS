<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lines of a return, erp_return_build()
 * (includes/erp/returns.php): the lines given back in part take their taxes
 * per group of one VAT rate and one withholding rate of the return
 * (erp_rate_vat()), capped by what is left on each line; the piece that
 * empties a line takes exactly what is left of its amount, discount and
 * taxes; and the order's lines, which
 * have no earlier returns to cap them, take the group taxes alone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/returns.php');

// An invoice line as erp_returnable_lines() reads it. $returned is what earlier
// returns gave back: [quantity, tax, withholding, second tax, line total,
// discount], the last two worked out from the quantity when left out; null
// leaves the keys out, the way an order's lines come (erp_order_refund_lines()).
function pg_test_erp_return_line($id, $quantity, $unit_price, $tax_rate, $tax_total, $extra = array(), $returned = array(0, 0, 0, 0))
{
	$line = array_merge(array(
		'id' => $id,
		'product_id' => 0,
		'description' => 'Line ' . $id,
		'quantity' => $quantity,
		'unit_code' => 'C62',
		'unit_price' => $unit_price,
		'discount_amount' => 0,
		'tax_rate' => $tax_rate,
		'tax_total' => $tax_total,
		'tax2_rate' => 0,
		'tax2_amount' => 0,
		'withholding_code' => '',
		'withholding_rate' => 0,
		'withholding_amount' => 0,
		'vat_exemption_code' => '',
		'returned_qty' => ($returned === null) ? 0 : $returned[0],
	), $extra);

	if (!isset($line['line_total'])) {
		$line['line_total'] = (int) round($line['unit_price'] * $line['quantity']);
	}

	$line['remaining_qty'] = (float) $line['quantity'] - (float) $line['returned_qty'];

	if ($returned !== null) {
		$share = ($line['quantity'] > 0) ? ($returned[0] / $line['quantity']) : 0;
		$line['returned_tax'] = $returned[1];
		$line['returned_withholding'] = $returned[2];
		$line['returned_tax2'] = $returned[3];
		$line['returned_line_total'] = $returned[4] ?? (int) round($line['unit_price'] * $returned[0]);
		$line['returned_discount'] = $returned[5] ?? (int) round($line['discount_amount'] * $share);
	}

	return $line;
}

// The taxes of the built lines, in order.
function pg_test_erp_return_taxes($built, $name)
{
	return array_map(function ($line) use ($name) {
		return $line[$name];
	}, $built['lines']);
}

// An invoice of 2 x 1.02 twice at 20% carried 0.82 of VAT (0.41 a line). One
// of each back: 0.41 on 2.04, not 0.20 + 0.20. The rest back afterwards:
// what is left on each line, 0.41 again, and the two returns give back the
// invoice's 0.82.
function test_erp_return_takes_vat_on_the_group()
{
	$parents = array(pg_test_erp_return_line(1, 2, 102, 20, 41), pg_test_erp_return_line(2, 2, 102, 20, 41));
	$built = erp_return_build($parents, array(1 => 1, 2 => 1));

	pg_assert_same('', $built['error']);
	pg_assert_same(array(21, 20), pg_test_erp_return_taxes($built, 'tax_total'));
	pg_assert_same(41, $built['totals']['tax_total']);
	pg_assert_same(245, $built['totals']['grand_total']);
	pg_assert_false($built['is_full']);

	$parents = array(
		pg_test_erp_return_line(1, 2, 102, 20, 41, array(), array(1, 21, 0, 0)),
		pg_test_erp_return_line(2, 2, 102, 20, 41, array(), array(1, 20, 0, 0)),
	);
	$built = erp_return_build($parents, array(1 => 1, 2 => 1));

	pg_assert_same(array(20, 21), pg_test_erp_return_taxes($built, 'tax_total'));
	pg_assert_same(41, $built['totals']['tax_total']);
}

// Withholding per group too: one of 2 x 1.05 (601, 4/10) and one of 2 x 1.10
// (603, 7/10) at 20% withhold 0.24 (8.4 + 15.4 kurus, rounded once), not
// 0.08 + 0.15 - the figures an e-document provider would give the return.
function test_erp_return_takes_withholding_once()
{
	$parents = array(
		pg_test_erp_return_line(1, 2, 105, 20, 42, array('withholding_code' => '601', 'withholding_rate' => 40, 'withholding_amount' => 17)),
		pg_test_erp_return_line(2, 2, 110, 20, 44, array('withholding_code' => '603', 'withholding_rate' => 70, 'withholding_amount' => 31)),
	);
	$built = erp_return_build($parents, array(1 => 1, 2 => 1));

	pg_assert_same(array(21, 22), pg_test_erp_return_taxes($built, 'tax_total'));
	pg_assert_same(array(8, 16), pg_test_erp_return_taxes($built, 'withholding_amount'));
	pg_assert_same(24, $built['totals']['withholding_total']);
	pg_assert_same(215 + 43 - 24, $built['totals']['grand_total']);
}

// The second tax is taken on its own rate's sum: one of each 2 x 1.10 line
// with 5% gives back 0.11 of it, not 0.06 + 0.06, inside each line's tax.
function test_erp_return_takes_the_second_tax_per_rate()
{
	$tax2 = array('tax2_rate' => 5, 'tax2_amount' => 11);
	$parents = array(pg_test_erp_return_line(1, 2, 110, 20, 55, $tax2), pg_test_erp_return_line(2, 2, 110, 20, 55, $tax2));
	$built = erp_return_build($parents, array(1 => 1, 2 => 1));

	pg_assert_same(array(6, 5), pg_test_erp_return_taxes($built, 'tax2_amount'));
	pg_assert_same(array(28, 27), pg_test_erp_return_taxes($built, 'tax_total'));
	pg_assert_same(11, $built['totals']['tax2_total']);
	pg_assert_same(55, $built['totals']['tax_total']);
}

// A line given back in part never gives back more tax than is left on it:
// 4 x 0.03 carried 0.02 and two earlier units took both kurus.
function test_erp_return_caps_a_part_at_what_is_left()
{
	$parents = array(pg_test_erp_return_line(1, 4, 3, 20, 2, array(), array(2, 2, 0, 0)));
	$built = erp_return_build($parents, array(1 => 1));

	pg_assert_same(array(0), pg_test_erp_return_taxes($built, 'tax_total'));
	pg_assert_same(3, $built['totals']['grand_total']);
}

// Everything back at once copies the lines' figures, whatever the groups
// would make of them.
function test_erp_return_of_everything_copies_the_lines()
{
	$parents = array(
		pg_test_erp_return_line(1, 1, 102, 20, 21, array('withholding_code' => '601', 'withholding_rate' => 40, 'withholding_amount' => 9)),
		pg_test_erp_return_line(2, 1, 102, 20, 20, array('withholding_code' => '601', 'withholding_rate' => 40, 'withholding_amount' => 7)),
	);
	$built = erp_return_build($parents, array(1 => 1, 2 => 1));

	pg_assert_true($built['is_full']);
	pg_assert_same(array(21, 20), pg_test_erp_return_taxes($built, 'tax_total'));
	pg_assert_same(array(9, 7), pg_test_erp_return_taxes($built, 'withholding_amount'));
}

// An order's lines carry no earlier returns: the group taxes alone, so two
// whole lines of 1.02 come to 0.41 as the order's invoice would.
function test_erp_return_of_order_lines_takes_the_group()
{
	$parents = array(pg_test_erp_return_line(-1, 1, 102, 20, 20, array(), null), pg_test_erp_return_line(-2, 1, 102, 20, 20, array(), null));
	$built = erp_return_build($parents, array(-1 => 1, -2 => 1));

	pg_assert_same(array(21, 20), pg_test_erp_return_taxes($built, 'tax_total'));
	pg_assert_same(41, $built['totals']['tax_total']);
}

// Pieces of a line, summed, are the line. 2 x 50.00 with 10.01 off at 20%
// (107.99): the first unit takes 5.01 of the discount, the second the 5.00
// left, not 5.01 again; the two returns give back the invoice to the kurus.
function test_erp_return_last_piece_takes_the_rest_of_the_discount()
{
	$parent = pg_test_erp_return_line(1, 2, 5000, 20, 1800, array('discount_amount' => 1001));
	$first = erp_return_build(array($parent), array(1 => 1));

	pg_assert_same(array(501), pg_test_erp_return_taxes($first, 'discount_amount'));
	pg_assert_same(5399, $first['totals']['grand_total']);

	$parent = pg_test_erp_return_line(1, 2, 5000, 20, 1800, array('discount_amount' => 1001),
		array(1, $first['totals']['tax_total'], 0, 0, $first['totals']['subtotal'], $first['totals']['discount_total']));
	$last = erp_return_build(array($parent), array(1 => 1));

	pg_assert_same(array(500), pg_test_erp_return_taxes($last, 'discount_amount'));
	pg_assert_same(array(900), pg_test_erp_return_taxes($last, 'tax_total'));
	pg_assert_same(10799, $first['totals']['grand_total'] + $last['totals']['grand_total']);
}

// The other way round: 3 x 50.00 with 10.00 off. Three single units took
// 3.33 each before and gave back a kurus more than the invoice; the third
// takes the 3.34 left.
function test_erp_return_last_piece_takes_the_rest_of_a_small_discount()
{
	$parent = pg_test_erp_return_line(1, 3, 5000, 20, 2800, array('discount_amount' => 1000), array(2, 1867, 0, 0, 10000, 666));
	$built = erp_return_build(array($parent), array(1 => 1));

	pg_assert_same(array(334), pg_test_erp_return_taxes($built, 'discount_amount'));
	pg_assert_same(array(933), pg_test_erp_return_taxes($built, 'tax_total'));
	pg_assert_same(5000 - 334 + 933, $built['totals']['grand_total']);
}

// A fractional quantity: 2.5 x 3.33 is a line of 8.33 (8.325 rounded); two
// halves of 4.16 (4.1625) left a kurus of it behind. The second half takes
// the 4.17 left.
function test_erp_return_last_piece_takes_the_rest_of_the_line()
{
	$parent = pg_test_erp_return_line(1, 2.5, 333, 20, 167);
	$first = erp_return_build(array($parent), array(1 => 1.25));

	pg_assert_same(array(416), pg_test_erp_return_taxes($first, 'line_total'));

	$parent = pg_test_erp_return_line(1, 2.5, 333, 20, 167, array(),
		array(1.25, $first['totals']['tax_total'], 0, 0, $first['totals']['subtotal'], 0));
	$last = erp_return_build(array($parent), array(1 => 1.25));

	pg_assert_same(array(417), pg_test_erp_return_taxes($last, 'line_total'));
	pg_assert_same(1000, $first['totals']['grand_total'] + $last['totals']['grand_total']);
}

// A piece that does not empty the line is capped by what is left of it.
function test_erp_return_caps_a_part_at_what_is_left_of_the_discount()
{
	$parent = pg_test_erp_return_line(1, 4, 100, 0, 0, array('discount_amount' => 2), array(2, 0, 0, 0, 200, 2));
	$built = erp_return_build(array($parent), array(1 => 1));

	pg_assert_same(array(0), pg_test_erp_return_taxes($built, 'discount_amount'));
	pg_assert_same(100, $built['totals']['grand_total']);
}

// More than is left is refused, nothing chosen is refused.
function test_erp_return_refuses_too_much_and_nothing()
{
	$parents = array(pg_test_erp_return_line(1, 2, 102, 20, 41, array(), array(1, 20, 0, 0)));

	pg_assert_true(erp_return_build($parents, array(1 => 2))['error'] !== '');
	pg_assert_true(erp_return_build($parents, array())['error'] !== '');
}

// An invoice with a credit line, from a product priced below zero: 2 x 50.00
// and 2 x -15.00 at 20% (70.00, 14.00 of VAT, 84.00). With $discount 7.00 off
// the order, spread over the lines and the VAT taken again on what is left
// (erp_order_lines()): 10.00 off the goods, -3.00 off the credit, 18.00 and
// -5.40 of VAT (63.00, 12.60, 75.60). $returned is what earlier returns gave
// back of each line, as pg_test_erp_return_line() takes it.
function pg_test_erp_return_credit_invoice($discount = false, $returned = array(array(0, 0, 0, 0), array(0, 0, 0, 0)))
{
	return array(
		pg_test_erp_return_line(1, 2, 5000, 20, $discount ? 1800 : 2000, array('discount_amount' => $discount ? 1000 : 0), $returned[0]),
		pg_test_erp_return_line(2, 2, -1500, 20, $discount ? -540 : -600, array('discount_amount' => $discount ? -300 : 0), $returned[1]),
	);
}

// Everything back is the invoice, the credit line with its own figures:
// 84.00, and 75.60 after the order discount. The credit line's net and VAT
// were taken down to zero and the return came to 120.00 and 108.00.
function test_erp_return_of_everything_gives_back_the_credit_line()
{
	$built = erp_return_build(pg_test_erp_return_credit_invoice(), array(1 => 2, 2 => 2));

	pg_assert_same('', $built['error']);
	pg_assert_true($built['is_full']);
	pg_assert_same(array(10000, -3000), pg_test_erp_return_taxes($built, 'line_total'));
	pg_assert_same(array(2000, -600), pg_test_erp_return_taxes($built, 'tax_total'));
	pg_assert_same(7000, $built['totals']['subtotal']);
	pg_assert_same(1400, $built['totals']['tax_total']);
	pg_assert_same(8400, $built['totals']['grand_total']);

	$built = erp_return_build(pg_test_erp_return_credit_invoice(true), array(1 => 2, 2 => 2));

	pg_assert_same('', $built['error']);
	pg_assert_same(array(1000, -300), pg_test_erp_return_taxes($built, 'discount_amount'));
	pg_assert_same(array(1800, -540), pg_test_erp_return_taxes($built, 'tax_total'));
	pg_assert_same(700, $built['totals']['discount_total']);
	pg_assert_same(1260, $built['totals']['tax_total']);
	pg_assert_same(7560, $built['totals']['grand_total']);
}

// Half of each line back is half the invoice, its VAT taken on the group the
// credit is part of (7.00 on 35.00); the other half, afterwards, is what is
// left, and the two returns are the invoice to the kurus.
function test_erp_return_of_half_gives_back_half_of_the_credit_line()
{
	foreach (array(array(false, 4200, 700, 0), array(true, 3780, 630, 350)) as $case) {
		list($discount, $half, $tax, $off) = $case;
		$first = erp_return_build(pg_test_erp_return_credit_invoice($discount), array(1 => 1, 2 => 1));

		pg_assert_same('', $first['error']);
		pg_assert_false($first['is_full']);
		pg_assert_same(array(5000, -1500), pg_test_erp_return_taxes($first, 'line_total'));
		pg_assert_same($off, $first['totals']['discount_total']);
		pg_assert_same($tax, $first['totals']['tax_total']);
		pg_assert_same($half, $first['totals']['grand_total']);

		$given = array();
		foreach ($first['lines'] as $line) {
			$given[] = array(1, $line['tax_total'], 0, 0, $line['line_total'], $line['discount_amount']);
		}

		$last = erp_return_build(pg_test_erp_return_credit_invoice($discount, $given), array(1 => 1, 2 => 1));

		pg_assert_same('', $last['error']);
		pg_assert_same(array(5000, -1500), pg_test_erp_return_taxes($last, 'line_total'));
		pg_assert_same($discount ? 7560 : 8400, $first['totals']['grand_total'] + $last['totals']['grand_total']);
	}
}

// The goods without the credit line: a part, while it stays within what is
// left of the invoice (60.00 of 84.00, 54.00 of 75.60). All of them gave back
// 120.00 for an invoice of 84.00, and the last unit after the first 60.00 of
// 24.00 left: refused, and taken with the credit line it is the 24.00.
function test_erp_return_of_the_goods_alone_stays_within_the_invoice()
{
	foreach (array(array(false, 6000), array(true, 5400)) as $case) {
		list($discount, $one) = $case;
		$built = erp_return_build(pg_test_erp_return_credit_invoice($discount), array(1 => 1));

		pg_assert_same('', $built['error']);
		pg_assert_same($one, $built['totals']['grand_total']);

		pg_assert_true(erp_return_build(pg_test_erp_return_credit_invoice($discount), array(1 => 2))['error'] !== '');
	}

	$given = array(array(1, 1000, 0, 0, 5000, 0), array(0, 0, 0, 0));

	pg_assert_true(erp_return_build(pg_test_erp_return_credit_invoice(false, $given), array(1 => 1))['error'] !== '');

	$rest = erp_return_build(pg_test_erp_return_credit_invoice(false, $given), array(1 => 1, 2 => 2));
	pg_assert_same('', $rest['error']);
	pg_assert_same(2400, $rest['totals']['grand_total']);
}

// The credit line on its own takes the return below zero, which no ledger
// can credit (erp_account_post()): refused, with and without the discount.
function test_erp_return_of_the_credit_line_alone_is_refused()
{
	pg_assert_true(erp_return_build(pg_test_erp_return_credit_invoice(), array(2 => 1))['error'] !== '');
	pg_assert_true(erp_return_build(pg_test_erp_return_credit_invoice(true), array(2 => 2))['error'] !== '');
}

// A credit line at another VAT rate than some of the goods: what goes back at
// each rate stays within what is left of it. 100.00 at 20%, 100.00 at 0% and
// a credit of -30.00 at 20% (VAT 14.00, 184.00): the 20% goods alone gave
// back 20.00 of VAT for the 14.00 charged at that rate, inside the invoice's
// total.
function test_erp_return_stays_within_each_rate()
{
	$parents = array(
		pg_test_erp_return_line(1, 1, 10000, 20, 2000),
		pg_test_erp_return_line(2, 1, 10000, 0, 0),
		pg_test_erp_return_line(3, 1, -3000, 20, -600),
	);

	pg_assert_true(erp_return_build($parents, array(1 => 1))['error'] !== '');
	pg_assert_same(10000, erp_return_build($parents, array(2 => 1))['totals']['grand_total']);
	pg_assert_same(8400, erp_return_build($parents, array(1 => 1, 3 => 1))['totals']['grand_total']);
}
