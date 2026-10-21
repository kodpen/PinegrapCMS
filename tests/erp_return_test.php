<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lines of a return, erp_return_build()
 * (includes/erp/returns.php): the lines given back in part take their taxes
 * per group of one VAT rate and one withholding rate of the return
 * (erp_rate_vat()), capped by what is left on each line; the piece that
 * empties a line takes exactly what is left; and the order's lines, which
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
// returns gave back: [quantity, tax, withholding, second tax]; null leaves the
// keys out, the way an order's lines come (erp_order_refund_lines()).
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

	$line['remaining_qty'] = (float) $line['quantity'] - (float) $line['returned_qty'];

	if ($returned !== null) {
		$line['returned_tax'] = $returned[1];
		$line['returned_withholding'] = $returned[2];
		$line['returned_tax2'] = $returned[3];
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

// More than is left is refused, nothing chosen is refused.
function test_erp_return_refuses_too_much_and_nothing()
{
	$parents = array(pg_test_erp_return_line(1, 2, 102, 20, 41, array(), array(1, 20, 0, 0)));

	pg_assert_true(erp_return_build($parents, array(1 => 2))['error'] !== '');
	pg_assert_true(erp_return_build($parents, array())['error'] !== '');
}
