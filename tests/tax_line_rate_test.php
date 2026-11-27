<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the rate a line's tax stands for (pg_tax_line_rate()), what a renewal
 * and a marketplace import store as the line's rate, and the changes an
 * update brings to the EU's VAT rate list (pg_eu_vat_rate_changes()), which
 * the general job tells the administrators once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');

function test_tax_line_rate_whole_and_decimal()
{
	pg_assert_same(20.0, pg_tax_line_rate(10000, 2000), '20% of 100.00');
	pg_assert_same(18.0, pg_tax_line_rate(3333, 600), 'the fewest places that give the tax: 18, not 18.002');
	pg_assert_same(25.5, pg_tax_line_rate(10000, 2550), 'Finland\'s 25.5');
	pg_assert_same(8.875, pg_tax_line_rate(100000, 8875), 'three places');
	pg_assert_same(33.0, pg_tax_line_rate(3, 1), 'a small amount: the first rate that gives its tax');
}

function test_tax_line_rate_nothing_to_derive()
{
	pg_assert_same(0.0, pg_tax_line_rate(10000, 0), 'no tax');
	pg_assert_same(0.0, pg_tax_line_rate(0, 100), 'no amount');
	pg_assert_same(0.0, pg_tax_line_rate(10000, -100), 'a negative tax');
	pg_assert_same(999.999, pg_tax_line_rate(1, 5000), 'held inside DECIMAL(6,3)');
}

// A marketplace's tax is per unit, each unit rounded: the line's tax may be a
// unit or two off the rate x the line's amount.
function test_tax_line_rate_slack()
{
	// 3 x 33.33 at 20%: 6.67 a unit, 20.01 the line; 20% of 99.99 is 20.00.
	pg_assert_same(20.0, pg_tax_line_rate(9999, 2001, 3), 'per-unit rounding within the quantity');
	pg_assert_same(20.01, pg_tax_line_rate(9999, 2001), 'without slack the exact rate');
}

function test_tax_line_rate_renewal_keeps_stored_rate()
{
	pg_assert_same(20.0, pg_subscription_renewal_line_rate(array('tax_rate' => '20.000'), array('subtotal' => 10000, 'tax' => 1800)), 'the first line\'s stored rate');
	pg_assert_same(0.0, pg_subscription_renewal_line_rate(array('tax_rate' => '0.000'), array('subtotal' => 10000, 'tax' => 0)), 'a stored 0 stays');
	pg_assert_same(18.0, pg_subscription_renewal_line_rate(array('tax_rate' => null), array('subtotal' => 10000, 'tax' => 1800)), 'no stored rate: from the renewal\'s amounts');
	pg_assert_same(10.0, pg_subscription_renewal_line_rate(array(), array('subtotal' => 5000, 'tax' => 500)), 'no column read');
}

function test_tax_line_rate_marketplace_line()
{
	$line = array('quantity' => 2, 'price' => 5000, 'tax' => 900, 'discount' => 500);
	pg_assert_same(array('tax_total' => 1800, 'tax_rate' => 20.0), mp_order_line_tax($line), 'tax on the line less its discount');

	$line = array('quantity' => 3, 'price' => 10000, 'tax' => 0, 'discount' => 0, 'discount_total' => 1000);
	pg_assert_same(array('tax_total' => 0, 'tax_rate' => 0.0), mp_order_line_tax($line), 'tax inside the price: none on top');

	$line = array('quantity' => 3, 'price' => 3333, 'tax' => 667, 'discount' => 0);
	pg_assert_same(array('tax_total' => 2001, 'tax_rate' => 20.0), mp_order_line_tax($line), 'per-unit rounding');
}

function tax_line_rate_test_rows()
{
	return array(
		array('DE', 'standard', 19.0, '2016-01-01'),
		array('EE', 'standard', 22.0, '2024-01-01'),
		array('FR', 'standard', 20.0, '2016-01-01'),
	);
}

function test_tax_eu_rate_changes_none()
{
	$rows = tax_line_rate_test_rows();

	pg_assert_same(array(), pg_eu_vat_rate_changes($rows, $rows), 'the same list');
	pg_assert_same(array(), pg_eu_vat_rate_changes(array_reverse($rows), $rows), 'the same rows in another order');
}

function test_tax_eu_rate_changes_new_row()
{
	$rows = tax_line_rate_test_rows();
	$new = $rows;
	$new[] = array('EE', 'standard', 24.0, '2025-07-01');

	pg_assert_same(array(
		array('country' => 'EE', 'kind' => 'standard', 'old' => 22.0, 'new' => 24.0, 'from' => '2025-07-01'),
	), pg_eu_vat_rate_changes($rows, $new), 'a dated change');
}

function test_tax_eu_rate_changes_corrected_and_removed()
{
	$rows = tax_line_rate_test_rows();

	$new = $rows;
	$new[2] = array('FR', 'standard', 21.0, '2016-01-01');
	pg_assert_same(array(
		array('country' => 'FR', 'kind' => 'standard', 'old' => 20.0, 'new' => 21.0, 'from' => '2016-01-01'),
	), pg_eu_vat_rate_changes($rows, $new), 'a row corrected');

	$seen = $rows;
	$seen[] = array('DE', 'standard', 16.0, '2027-01-01');
	pg_assert_same(array(
		array('country' => 'DE', 'kind' => 'standard', 'old' => 16.0, 'new' => 19.0, 'from' => '2027-01-01'),
	), pg_eu_vat_rate_changes($seen, $rows), 'an announced change withdrawn');

	// A row that repeats the rate before it changes nothing.
	$new = $rows;
	$new[] = array('FR', 'standard', 20.0, '2027-01-01');
	pg_assert_same(array(), pg_eu_vat_rate_changes($rows, $new), 'the same rate again');
}

function test_tax_eu_rate_changes_new_state()
{
	$rows = tax_line_rate_test_rows();
	$new = $rows;
	$new[] = array('XX', 'standard', 15.0, '2030-01-01');

	pg_assert_same(array(
		array('country' => 'XX', 'kind' => 'standard', 'old' => false, 'new' => 15.0, 'from' => '2030-01-01'),
	), pg_eu_vat_rate_changes($rows, $new), 'a state joining');
}

function test_tax_eu_rate_notice_title()
{
	$changes = array(
		array('country' => 'EE', 'kind' => 'standard', 'old' => 22.0, 'new' => 24.0, 'from' => '2025-07-01'),
		array('country' => 'XX', 'kind' => 'standard', 'old' => false, 'new' => 15.0, 'from' => '2030-01-01'),
	);

	$title = pg_eu_vat_rate_notice_title($changes);
	pg_assert_same('EE 22>24 2025-07-01, XX ->15 2030-01-01', $title, 'country, old and new rate, date');
	pg_assert_same($changes[0]['country'], pg_eu_vat_rate_notice_parse($title)[0]['country'], 'read back');
	pg_assert_same(false, pg_eu_vat_rate_notice_parse($title)[1]['old'], 'no rate before');
	pg_assert_same(24.0, pg_eu_vat_rate_notice_parse($title)[0]['new'], 'the new rate');

	$many = array();
	for ($i = 0; $i < 40; $i++) {
		$many[] = array('country' => 'DE', 'kind' => 'standard', 'old' => 19.0, 'new' => 21.5, 'from' => '2027-01-01');
	}
	pg_assert_true(strlen(pg_eu_vat_rate_notice_title($many)) <= 256, 'cut to notifications.title, at a whole entry');
	pg_assert_true(preg_match('/ 2027-01-01$/', pg_eu_vat_rate_notice_title($many)) === 1, 'ends on a whole entry');
}
