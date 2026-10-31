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
