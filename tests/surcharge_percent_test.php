<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the card surcharge (ECOMMERCE_SURCHARGE_PERCENTAGE) as a figure:
 * pg_percent_of() on what is left to pay in minor units, in submit_order.php,
 * pg_express_order_charged_totals() and the classic express order and order
 * preview pages, so PHP 8.4 and later, whose round() no longer pre-rounds,
 * charge and show the surcharge PHP 8.3 did, and the pages post the total
 * submit_order.php charges.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// $whole / 100000 rounded half up, for a whole $whole of zero or more below
// 2^53. Whole floats, so it also works where a 32-bit int overflows.
function surcharge_percent_test_exact($whole)
{
	$whole = (float) $whole;
	$rest = fmod($whole, 100000);
	$units = ($whole - $rest) / 100000;

	return (int) (($rest >= 50000) ? $units + 1 : $units);
}

// The largest total up to $total whose surcharge at $units thousandths of a
// percent ends in a half minor unit ($units x total ends in 50000), where a
// product of floats can round either way; $total itself when no total does.
function surcharge_percent_test_half($total, $units)
{
	$gcd = 100000;

	for ($rest = $units; $rest > 0; ) {
		list($gcd, $rest) = array($rest, $gcd % $rest);
	}

	if (50000 % $gcd) {
		return $total;
	}

	// The totals that do are one residue modulo 100000 / gcd: the inverse of
	// $units / gcd times 50000 / gcd.
	$modulo = intdiv(100000, $gcd);
	list($r0, $r1, $s0, $s1) = array($modulo, intdiv($units, $gcd) % $modulo, 0, 1);

	while ($r1 > 0) {
		$quotient = intdiv($r0, $r1);
		list($r0, $r1, $s0, $s1) = array($r1, $r0 - $quotient * $r1, $s1, $s0 - $quotient * $s1);
	}

	$residue = (int) fmod((float) intdiv(50000, $gcd) * ((($s0 % $modulo) + $modulo) % $modulo), $modulo);

	return $total - (((($total - $residue) % $modulo) + $modulo) % $modulo);
}

// The surcharge setting is decimal(6,3), so the database gives a string such
// as '2.900'. 2.9% of 5.00 is 0.145, so 0.15; 4.35% of 50.00 is 2.175, so
// 2.18; round('2.900' / 100 * 500) is 14 on PHP 8.4 and later.
function test_surcharge_percent_express_order_widget()
{
	$line = array(
		'price'                => 500,
		'quantity'             => 1,
		'tax_total'            => 0,
		'recurring'            => 0,
		'recurring_start_date' => '',
		'start_days'           => 0,
		'gift_card'            => 0,
	);

	$totals = pg_express_order_charged_totals(array($line), 0, 0, 0, '2.900', '2026-10-23', false);
	pg_assert_same(15, $totals['surcharge'], '2.9% of 5.00');
	pg_assert_same(515, $totals['total_with_surcharge']);

	$line['price'] = 5000;
	$totals = pg_express_order_charged_totals(array($line), 0, 0, 0, '4.350', '2026-10-23', false);
	pg_assert_same(218, $totals['surcharge'], '4.35% of 50.00');

	$totals = pg_express_order_charged_totals(array($line), 0, 0, 0, '0.000', '2026-10-23', false);
	pg_assert_same(0, $totals['surcharge'], 'no surcharge set');
}

// Totals a 32-bit int holds at percentages to three places up to 100, half of
// them at a half minor unit: the exact figure. The classic pages add the total
// up in major units and take the surcharge on it in minor units, which are
// the ones submit_order.php charges, so the total they post is the charge
// (round(0.03 * 5.50, 2) was 0.16 on PHP 8.4 and later where submit_order.php
// charged 0.17, and the posted total was refused). On PHP 8.3 and earlier,
// whose round() pre-rounded, the float formulas the surcharge used before give
// the same figures, so none moves there.
function test_surcharge_percent_parity()
{
	mt_srand(20261023);

	for ($run = 0; $run < 20000; $run++) {
		$units = mt_rand(0, 100000);
		$percent = sprintf('%.3F', $units / 1000);
		$subtotal = mt_rand(0, mt_getrandmax() >> 2);
		$tax = mt_rand(0, 99999);
		$shipping = mt_rand(0, 9999);
		$gift_card = ($run % 3) ? 0 : mt_rand(0, 9999);
		$total = $subtotal + $tax + $shipping - $gift_card;

		if ($run % 2) {
			$subtotal -= $total - surcharge_percent_test_half($total, $units);
			$total = $subtotal + $tax + $shipping - $gift_card;
		}

		if ($total <= 0) {
			continue;
		}

		$label = $total . ' at ' . $percent . '%';
		$surcharge = pg_percent_of($total, $percent);

		pg_assert_same(surcharge_percent_test_exact((float) $total * $units), $surcharge, $label);

		// The classic page: the same parts in major units, added in its order.
		$grand_total = $subtotal / 100;
		$grand_total = $grand_total + $tax / 100;
		$grand_total = $grand_total + $shipping / 100;
		$grand_total = $grand_total - $gift_card / 100;
		$page_surcharge = pg_percent_of(round($grand_total * 100), $percent) / 100;

		pg_assert_same($surcharge, (int) round($page_surcharge * 100), 'page ' . $label);
		pg_assert_same($total + $surcharge, (int) round((string) ($grand_total + $page_surcharge) * 100), 'posted ' . $label);

		if (PHP_VERSION_ID < 80400) {
			pg_assert_same($surcharge, (int) round($percent / 100 * $total), 'PHP 8.3 ' . $label);
			pg_assert_same($surcharge, (int) round(round($percent / 100 * $grand_total, 2) * 100), 'PHP 8.3 page ' . $label);
		}
	}
}

// No surcharge is a float product of the percentage: its round() depends on
// the PHP version. The pages are read as text, comments left out.
function test_surcharge_percent_no_float_product()
{
	$files = array_merge(glob(PG_FUNCTIONS_DIR . '/*.php'), glob(PG_FUNCTIONS_DIR . '/includes/fn/*.php'));
	$found = array();

	foreach ($files as $file) {
		$code = '';

		foreach (token_get_all((string) file_get_contents($file)) as $token) {
			if (!is_array($token) || !in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
				$code .= is_array($token) ? $token[1] : $token;
			}
		}

		if (preg_match_all('~surcharge_percent\w*\s*/\s*100\b~i', $code, $matches)) {
			$found[] = basename($file) . ': ' . count($matches[0]);
		}
	}

	pg_assert_same(array(), $found);
}
