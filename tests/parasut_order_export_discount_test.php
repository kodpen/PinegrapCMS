<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Orders > Export for Paraşüt carries the order discount: the invoice
 * workbook's İNDİRİM TUTARI (column Q) of the product lines holds
 * orders.discount spread over them by their value, so Paraşüt's invoice
 * comes to what the customer paid (#9139). The spread is pure and tested
 * here; the workbook is built on a request and a database, so the export's
 * use of it is pinned in the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The order the hunt found: one product at 2,999.00 with 10% off (299.90).
// Paraşüt then works out (2,999.00 - 299.90) + 20% = 3,238.92, what the
// customer paid.
function test_parasut_export_discount_one_line()
{
	pg_assert_same(array(29990), pg_order_parasut_line_discounts(29990, array(299900)));

	$net = 299900 - 29990;
	pg_assert_same(323892, $net + (int) round($net * 20 / 100), 'the invoice total');
}

// Several lines share it by value, the remainder going to the largest
// remainders, and the shares add up to the discount exactly.
function test_parasut_export_discount_spread_by_value()
{
	$shares = pg_order_parasut_line_discounts(1000, array('a' => 3000, 'b' => 1500, 'c' => 1500));
	pg_assert_same(array('a' => 500, 'b' => 250, 'c' => 250), $shares);

	$shares = pg_order_parasut_line_discounts(100, array(100, 100, 100));
	pg_assert_same(100, array_sum($shares), 'adds up to the discount');
	pg_assert_same(array(34, 33, 33), $shares, 'the remainder by largest remainder, first line first');

	foreach (array(array(1001, array(333, 333, 335)), array(7, array(5, 3)), array(12345, array(99999, 1, 450))) as $case) {
		$shares = pg_order_parasut_line_discounts($case[0], $case[1]);
		pg_assert_same($case[0], array_sum($shares), 'sum for ' . json_encode($case));

		foreach ($shares as $key => $share) {
			pg_assert_true(($share >= 0) && ($share <= $case[1][$key]), 'no line below zero for ' . json_encode($case));
		}
	}
}

// No discount, a line of no value (a free gift) or below zero, and a discount
// larger than the lines: no line ever goes below zero, which Paraşüt refuses.
function test_parasut_export_discount_edges()
{
	pg_assert_same(array(0, 0), pg_order_parasut_line_discounts(0, array(1000, 500)));
	pg_assert_same(array(0, 0), pg_order_parasut_line_discounts(-50, array(1000, 500)));
	pg_assert_same(array(0 => 100, 1 => 0, 2 => 0), pg_order_parasut_line_discounts(100, array(1000, 0, -200)));
	pg_assert_same(array(1000, 500), pg_order_parasut_line_discounts(5000, array(1000, 500)), 'capped at the lines');
	pg_assert_same(array(), pg_order_parasut_line_discounts(100, array()));
	pg_assert_same(array(0), pg_order_parasut_line_discounts(100, array(0)), 'nothing to take it');
}

// The export block of edit_orders.php.
function parasut_export_discount_block()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_orders.php');
	$start = strpos($source, "case 'export_orders_for_parasut':");
	pg_assert_true($start !== false, 'export_orders_for_parasut case exists');
	$end = strpos($source, "case 'remove_card_data':", $start);

	return substr($source, $start, $end - $start);
}

// The export reads the order's discount, spreads it over the product lines
// and writes each share to Q on the same footing as the unit price: net of
// the dialog's VAT when the prices include it. The gift card is a means of
// payment and stays out.
function test_parasut_export_writes_the_discount()
{
	$block = parasut_export_discount_block();

	pg_assert_true(strpos($block, 'orders.discount,') !== false, 'the order query reads the discount');
	pg_assert_true(strpos($block, "pg_order_parasut_line_discounts((int) \$row['discount'], \$line_totals)") !== false, 'spread over the lines');
	pg_assert_true(strpos($block, '$product_discount = 0;') === false, 'no line forced to no discount');
	pg_assert_true(strpos($block, "->setCellValue('Q' . \$i_obj_1, (\$product_discount / 100))") !== false, 'the share in Q');
	pg_assert_true(strpos($block, '$product_discount = $product_discount / (1+$product_tax_rate/100);') !== false, 'net of VAT like the price');
	pg_assert_true(strpos($block, 'gift_card_discount') === false, 'the gift card is not a discount');
}
