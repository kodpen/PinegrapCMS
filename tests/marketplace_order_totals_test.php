<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the money of an imported marketplace order: what the n11
 * connector hands the importer per line (mp_n11_package()) and what the
 * importer adds up from it (mp_order_totals(), includes/api/outbound/orders.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The outbound files sit behind init.php's gate and only declare functions
// behind it, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/n11.php');

// One n11 package with one line, in the shape of their shipmentPackages answer.
function order_totals_test_n11_package($line)
{
	return mp_n11_package(array(
		'id'                    => '9001',
		'orderNumber'           => '200001',
		'shipmentPackageStatus' => 'Created',
		'lines'                 => array(array_merge(array('orderLineId' => 1, 'stockCode' => 'ABC'), $line)),
	));
}

function test_marketplace_order_totals_n11_line_discount_that_does_not_divide_keeps_its_amount()
{
	$package = order_totals_test_n11_package(array('quantity' => 3, 'price' => '100.00', 'sellerDiscount' => '10.00'));

	$line = $package['lines'][0];

	pg_assert_same(10000, $line['price'], 'price is per unit');
	pg_assert_same(333, $line['discount'], 'the unit discount is rounded');
	pg_assert_same(1000, $line['discount_total'], 'the line keeps its own discount');

	$totals = mp_order_totals($package['lines']);

	pg_assert_same(30000, $totals['subtotal']);
	pg_assert_same(1000, $totals['discount'], 'not 333 x 3 = 999');
	pg_assert_same(29000, $totals['total'], 'what the marketplace charged');
}

function test_marketplace_order_totals_n11_rounding_up_does_not_overstate_the_discount()
{
	// 10.00 over seven is 1.43 a unit, and 1.43 x 7 is 10.01.
	$package = order_totals_test_n11_package(array('quantity' => 7, 'price' => '25,00', 'sellerDiscount' => '10,00'));

	$totals = mp_order_totals($package['lines']);

	pg_assert_same(17500, $totals['subtotal']);
	pg_assert_same(1000, $totals['discount']);
	pg_assert_same(16500, $totals['total']);
}

function test_marketplace_order_totals_n11_counts_the_seller_coupon_and_not_the_mall_discount()
{
	$package = order_totals_test_n11_package(array('quantity' => 3, 'price' => '50.00',
		'sellerDiscount' => '5.00', 'sellerCouponDiscount' => '2.50', 'mallDiscount' => '20.00'));

	pg_assert_same(750, $package['lines'][0]['discount_total']);

	$totals = mp_order_totals($package['lines'], 1990);

	pg_assert_same(15000, $totals['subtotal']);
	pg_assert_same(750, $totals['discount']);
	pg_assert_same(1990, $totals['shipping']);
	pg_assert_same(15000 - 750 + 1990, $totals['total']);
}

function test_marketplace_order_totals_without_a_line_total_use_the_unit_discount()
{
	// A connector that gives only the unit discount, as the contract allowed
	// before discount_total.
	$totals = mp_order_totals(array(
		array('quantity' => 2, 'price' => 1500, 'tax' => 270, 'discount' => 100),
		array('quantity' => 1, 'price' => 999, 'tax' => 0, 'discount' => 0, 'discount_total' => 49),
	));

	pg_assert_same(3999, $totals['subtotal']);
	pg_assert_same(249, $totals['discount']);
	pg_assert_same(540, $totals['tax']);
	pg_assert_same(3999 - 249 + 540, $totals['total']);
}
