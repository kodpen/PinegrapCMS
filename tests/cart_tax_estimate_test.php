<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the tax the cart widget of a page built with the visual editor
 * estimates (pg_cart_estimate_tax(), includes/fn/widgets_cart.php): the
 * stored line taxes while every taxable line has one, else the preview on
 * the site's default rate over the whole cart.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A cart line as the estimate reads it.
function cart_tax_estimate_test_line($amount, $tax_total, $taxable = 1, $tax_rate = null)
{
	return array('amount' => $amount, 'tax_total' => $tax_total, 'taxable' => $taxable, 'tax_rate' => $tax_rate);
}

// A line added after the checkout was reached is taxed too.
function test_cart_tax_estimate_mixed_lines()
{
	$lines = array(
		cart_tax_estimate_test_line(999800, 199960),
		cart_tax_estimate_test_line(749900, 0),
	);
	pg_assert_same(349940, pg_cart_estimate_tax($lines, 20.0), 'both lines at 20%');
}

// Every taxable line taxed: the stored taxes, taken on the checkout address.
function test_cart_tax_estimate_all_taxed()
{
	$lines = array(
		cart_tax_estimate_test_line(10000, 800),
		cart_tax_estimate_test_line(5000, 400),
	);
	pg_assert_same(1200, pg_cart_estimate_tax($lines, 20.0), 'the stored taxes');
}

// No line taxed yet: the preview on the default rate, product rates first.
function test_cart_tax_estimate_none_taxed()
{
	$lines = array(
		cart_tax_estimate_test_line(10000, 0),
		cart_tax_estimate_test_line(5000, 0, 1, '10.000'),
	);
	pg_assert_same(2500, pg_cart_estimate_tax($lines, 20.0), 'the preview');
	pg_assert_same(0, pg_cart_estimate_tax($lines, false), 'no zone for the default country');
}

// A product that is not taxable, or zero-rated, stays 0 and asks for no preview.
function test_cart_tax_estimate_untaxable_line()
{
	$lines = array(
		cart_tax_estimate_test_line(10000, 2000),
		cart_tax_estimate_test_line(5000, 0, 0),
		cart_tax_estimate_test_line(3000, 0, 1, '0.000'),
	);
	pg_assert_same(2000, pg_cart_estimate_tax($lines, 20.0), 'the stored tax alone');

	$lines = array(cart_tax_estimate_test_line(5000, 0, 0));
	pg_assert_same(0, pg_cart_estimate_tax($lines, 20.0), 'nothing taxable');
}

// A line as the cart reads it on a schema with order_items.tax_rate: the rate
// the checkout charged it at goes with its tax.
function cart_tax_estimate_test_charged($amount, $tax_total, $charged_rate, $taxable = 1, $tax_rate = null)
{
	$line = cart_tax_estimate_test_line($amount, $tax_total, $taxable, $tax_rate);
	$line['charged_rate'] = $charged_rate;
	return $line;
}

// The quantity doubled after the checkout: the stored tax is of the old amount.
function test_cart_tax_estimate_quantity_changed()
{
	$lines = array(
		cart_tax_estimate_test_charged(20000, 2000, '20.000'),
		cart_tax_estimate_test_charged(5000, 1000, '20.000'),
	);
	pg_assert_same(5000, pg_cart_estimate_tax($lines, 20.0), 'the preview, not the old 3000');
}

// A donation amount changed after the checkout: the preview.
function test_cart_tax_estimate_donation_changed()
{
	$lines = array(cart_tax_estimate_test_charged(7500, 1000, '20.000'));
	pg_assert_same(1500, pg_cart_estimate_tax($lines, 20.0), 'the preview on the new amount');
}

// Amounts unchanged: the stored taxes, even at a rate other than the default one
// (the buyer's address is in another zone).
function test_cart_tax_estimate_unchanged_amounts()
{
	$lines = array(
		cart_tax_estimate_test_charged(10000, 800, '8.000'),
		cart_tax_estimate_test_charged(5000, 400, '8.000'),
		cart_tax_estimate_test_charged(3333, 333, '10.000', 1, '10.000'),
	);
	pg_assert_same(1533, pg_cart_estimate_tax($lines, 20.0), 'the stored taxes');

	// Rounded once per rate over the lines, spread back: still matches.
	$lines = array(
		cart_tax_estimate_test_charged(333, 60, '18.000'),
		cart_tax_estimate_test_charged(333, 60, '18.000'),
		cart_tax_estimate_test_charged(333, 60, '18.000'),
	);
	pg_assert_same(180, pg_cart_estimate_tax($lines, 20.0), 'the group tax, spread');
}

// A stored tax with no stored rate cannot be checked: the preview.
function test_cart_tax_estimate_tax_without_rate()
{
	$lines = array(cart_tax_estimate_test_charged(10000, 800, null));
	pg_assert_same(2000, pg_cart_estimate_tax($lines, 20.0), 'no rate');

	$lines = array(cart_tax_estimate_test_charged(10000, 800, '0.000'));
	pg_assert_same(2000, pg_cart_estimate_tax($lines, 20.0), 'a zero rate');
}
