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
	return array('price' => $amount, 'quantity' => 1, 'tax_total' => $tax_total, 'taxable' => $taxable, 'tax_rate' => $tax_rate);
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

// The order discount's tax share comes off as the checkout takes it (#9045):
// ₺4.999,00 at 20% with a 10% code is ₺899,82 on ₺4.499,10, not the lines'
// ₺999,80 the cart showed while the checkout charged ₺899,82.
function test_cart_tax_estimate_order_discount()
{
	$stored = array(cart_tax_estimate_test_charged(499900, 99980, '20.000'));
	pg_assert_same(89982, pg_cart_estimate_tax($stored, 20.0, 49990), 'stored taxes, the discount off');

	$preview = array(cart_tax_estimate_test_line(499900, 0));
	pg_assert_same(89982, pg_cart_estimate_tax($preview, 20.0, 49990), 'the preview, the discount off');

	pg_assert_same(539892, 499900 + pg_cart_estimate_tax($preview, 20.0, 49990) - 49990, 'the total the checkout charges');
	pg_assert_same(99980, pg_cart_estimate_tax($preview, 20.0), 'no discount, the lines\' tax');
	pg_assert_same(99980, pg_cart_estimate_tax($preview, 20.0, 0), 'a discount of 0 changes nothing');
}

// Two rates: the discount is shared over the lines by their amounts and the
// tax taken again per rate on what is left (the bug hunt's 15% cart:
// ₺1.164,64 at the checkout, ₺1.370,17 in the cart).
function test_cart_tax_estimate_order_discount_two_rates()
{
	$ten = cart_tax_estimate_test_line(123456, 0, 1, '10.000');
	$ten['quantity'] = 3;
	$preview = array($ten, cart_tax_estimate_test_line(499900, 0));
	pg_assert_same(137017, pg_cart_estimate_tax($preview, 20.0), 'the lines\' tax before the discount');
	pg_assert_same(116464, pg_cart_estimate_tax($preview, 20.0, 130540), 'the preview, the discount off');

	$ten = cart_tax_estimate_test_charged(123456, 37037, '10.000', 1, '10.000');
	$ten['quantity'] = 3;
	$stored = array($ten, cart_tax_estimate_test_charged(499900, 99980, '20.000'));
	pg_assert_same(116464, pg_cart_estimate_tax($stored, 20.0, 130540), 'stored taxes, the discount off');
}

// The cart's lines as the express order widget reads them (pg_express_order_total_lines()).
function cart_tax_estimate_test_checkout_lines($lines)
{
	$checkout = array();
	foreach ($lines as $line) {
		$checkout[] = array(
			'price' => $line['price'],
			'quantity' => $line['quantity'],
			'tax_total' => $line['tax_total'],
			'tax_rate' => array_key_exists('charged_rate', $line) ? $line['charged_rate'] : null,
			'recurring' => !empty($line['recurring']),
			'recurring_start_date' => $line['recurring_start_date'] ?? '',
			'start_days' => $line['start_days'] ?? 0,
			'gift_card' => 0,
		);
	}
	return $checkout;
}

// Once the checkout has taxed the lines, the cart's tax is the checkout's on
// the same lines, whatever the discount: one calculation for both.
function test_cart_tax_estimate_matches_checkout()
{
	$carts = array(
		'one rate' => array(cart_tax_estimate_test_charged(499900, 99980, '20.000')),
		'two rates and an untaxed line' => array(
			cart_tax_estimate_test_charged(370368, 37037, '10.000', 1, '10.000'),
			cart_tax_estimate_test_charged(499900, 99980, '20.000'),
			cart_tax_estimate_test_charged(2500, 0, '0.000', 0),
		),
		'a spread with remainders' => array(
			cart_tax_estimate_test_charged(333, 60, '18.000'),
			cart_tax_estimate_test_charged(333, 60, '18.000'),
			cart_tax_estimate_test_charged(333, 60, '18.000'),
		),
		'the buyer in another zone' => array(cart_tax_estimate_test_charged(10000, 800, '8.000')),
	);

	foreach ($carts as $name => $lines) {
		$subtotal = 0;
		foreach ($lines as $line) {
			$subtotal += $line['price'] * $line['quantity'];
		}
		foreach (array(0, 1, 49990, 130540, (int) round($subtotal / 3), $subtotal, $subtotal + 500) as $discount) {
			$checkout = pg_express_order_charged_totals(cart_tax_estimate_test_checkout_lines($lines), $discount, 0, 0, '0.00', '2026-11-12', false);
			pg_assert_same($checkout['tax'], pg_cart_estimate_tax($lines, 20.0, $discount, '2026-11-12'), $name . ', discount ' . $discount);
		}
	}
}

// A recurring line that starts later is not charged today: the discount is
// shared over today's lines only, as the checkout shares it, and the later
// line keeps its own tax in the cart.
function test_cart_tax_estimate_discount_recurring_later()
{
	$later = cart_tax_estimate_test_charged(10000, 2000, '20.000');
	$later['recurring'] = 1;
	$later['recurring_start_date'] = '0000-00-00';
	$later['start_days'] = 30;
	$lines = array(cart_tax_estimate_test_charged(10000, 2000, '20.000'), $later);

	pg_assert_same(3000, pg_cart_estimate_tax($lines, 20.0, 5000, '2026-11-12'), 'today: 20% of 50,00; later: its own 20,00');

	$checkout = pg_express_order_charged_totals(cart_tax_estimate_test_checkout_lines($lines), 5000, 0, 0, '0.00', '2026-11-12', false);
	pg_assert_same(1000, $checkout['tax'], 'the checkout charges today\'s line alone');


	// Starting today it is charged today and takes its share, but not on
	// ClearCommerce, which charges recurring lines itself.
	$today = cart_tax_estimate_test_charged(10000, 2000, '20.000');
	$today['recurring'] = 1;
	$today['recurring_start_date'] = '2026-11-12';
	$lines = array(cart_tax_estimate_test_charged(10000, 1000, '10.000', 1, '10.000'), $today);
	pg_assert_same(2250, pg_cart_estimate_tax($lines, 20.0, 5000, '2026-11-12'), 'both lines share the discount');
	pg_assert_same(2500, pg_cart_estimate_tax($lines, 20.0, 5000, '2026-11-12', true), 'ClearCommerce: the one-time line alone');
}

// A stored tax whose rate was not kept (a schema before order_items.tax_rate):
// lowered by the discount's share of the subtotal, as the checkout lowers it.
function test_cart_tax_estimate_discount_without_kept_rate()
{
	$lines = array(cart_tax_estimate_test_line(10000, 2000), cart_tax_estimate_test_line(5000, 0, 0));
	pg_assert_same(1867, pg_cart_estimate_tax($lines, 20.0, 1000, '2026-11-12'), '2000 less its 1000/15000 share');

	$checkout = pg_express_order_charged_totals(cart_tax_estimate_test_checkout_lines($lines), 1000, 0, 0, '0.00', '2026-11-12', false);
	pg_assert_same(1867, $checkout['tax'], 'the checkout\'s figure');
}

// The cart widget hands the estimate its order discount and reads the lines
// in the checkout's order.
function test_cart_tax_estimate_widget_passes_the_discount()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
	pg_assert_true((bool) preg_match('/pg_cart_estimate_tax\(\s*\$_pg_tax_lines,\s*\$_pg_def_rate,\s*\$discount_cents,/', $source), 'the discount goes in');
	pg_assert_true((bool) preg_match('/oi\.tax_rate AS charged_rate" : ""\) \. "\s*FROM order_items oi\s*LEFT JOIN products p ON oi\.product_id = p\.id\s*WHERE oi\.order_id = \'\$oid_esc\'" \. pg_order_item_active_sql\(\'oi\'\) \. "\s*ORDER BY oi\.id ASC"/', $source), 'the lines in the order of their ids');
}
