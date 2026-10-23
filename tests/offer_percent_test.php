<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an offer's percentage of a figure (pg_percent_of(),
 * pg_percent_off() in includes/fn/ecommerce.php) and the offer figures that
 * take it: the order discount, a gift's price, a discounted product and a
 * discounted shipping cost round the exact product half away from zero, so
 * PHP 8.4 and later, whose round() no longer pre-rounds, give the figures
 * PHP 8.3 gave.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// $whole / 100 rounded half up, for a whole $whole of zero or more below
// 2^53. Whole floats, so it also works where a 32-bit int overflows.
function offer_percent_test_exact($whole)
{
	$whole = (float) $whole;
	$rest = fmod($whole, 100);
	$units = ($whole - $rest) / 100;

	return (int) (($rest >= 50) ? $units + 1 : $units);
}

// An amount near $amount whose $percent x amount ends in a half minor unit
// (x 100 ends in 50), where a product of floats can round either way;
// $amount itself when the percentage has none.
function offer_percent_test_half($amount, $percent)
{
	for ($step = 0; $step < 100; $step++) {
		if (fmod((float) ($amount - $step) * $percent, 100) == 50) {
			return $amount - $step;
		}
	}

	return $amount;
}

// 35% of 13.10 is 4.585, so 4.59; 29% of 14.50 is 4.205, so 4.21; round(1310
// * (35 / 100)) is 458 on PHP 8.4 and later. Half away from zero below zero.
function test_offer_percent_of_rounds_the_exact_product()
{
	pg_assert_same(459, pg_percent_of(1310, 35));
	pg_assert_same(421, pg_percent_of('1450', '29'), 'as the database gives them');
	pg_assert_same(17, pg_percent_of(333, 5), '16.65');
	pg_assert_same(35, pg_percent_of(1500, 2.3), 'a decimal percentage, 34.5');
	pg_assert_same(0, pg_percent_of(1310, 0));
	pg_assert_same(1310, pg_percent_of(1310, 100));
	pg_assert_same(-459, pg_percent_of(-1310, 35));
	pg_assert_same(536870912, pg_percent_of(2147483647, 25), 'past 2^31 on the way');
}

// 55% off 6.50 leaves 2.925, so 2.93: what is left is rounded, as
// round($amount - $amount * $percent / 100) always did, not the amount less
// a rounded discount (2.92). round(650 - 650 * (55 / 100)) is 292 on PHP 8.4
// and later.
function test_offer_percent_off_rounds_what_is_left()
{
	pg_assert_same(293, pg_percent_off(650, 55));
	pg_assert_same(293, pg_percent_off('650', '55'), 'as the database gives them');
	pg_assert_same(851, pg_percent_off(1001, 15), '850.85');
	pg_assert_same(1699, pg_percent_off(1999, 15), '1699.15');
	pg_assert_same(650, pg_percent_off(650, 0));
	pg_assert_same(0, pg_percent_off(650, 100));
	pg_assert_same(0, pg_percent_off(null, 15), 'a product that is gone');
	pg_assert_same(1610612735, pg_percent_off(2147483647, 25), 'past 2^31 on the way');
}

// The offer figures built on them, at amounts PHP 8.4's round() takes a
// minor unit off.
function test_offer_percent_figures()
{
	pg_assert_same(459, pg_offer_order_discount_value(0, 35, array(), 1310), 'order discount, 4.585');
	pg_assert_same(421, pg_offer_order_discount_value(0, 5, array(array('min' => 1000, 'percent' => 29)), 1450), 'ladder rung, 4.205');
	pg_assert_same(array(7, 459), pg_offer_best_order_discount(array(
		array('offer_id' => 7, 'amount' => 0, 'percentage' => 35, 'tiers' => array()),
		array('offer_id' => 8, 'amount' => 458, 'percentage' => 0, 'tiers' => array())), 1310), 'the percentage beats 4.58');
	pg_assert_same(293, pg_offer_gift_price(650, 0, 55), 'gift, 2.925');
	pg_assert_same(293, pg_offer_gift_price('650', '0', '55'), 'gift as the database gives it');
}

// Any amount a 32-bit int holds at any whole percentage (the offer columns
// are TINYINT), half of them at a half minor unit: the exact figure, and on
// PHP 8.3 and earlier, whose round() pre-rounded, the float formula the
// offers used before, so no figure moves there.
function test_offer_percent_parity()
{
	mt_srand(20261023);

	for ($run = 0; $run < 20000; $run++) {
		$percent = mt_rand(0, 100);
		$amount = mt_rand(0, mt_getrandmax());

		if ($run % 2) {
			$amount = offer_percent_test_half($amount, ($run % 4 === 1) ? $percent : (100 - $percent));
		}

		$label = $amount . ' at ' . $percent . '%';

		pg_assert_same(offer_percent_test_exact((float) $amount * $percent), pg_percent_of($amount, $percent), 'of ' . $label);
		pg_assert_same(offer_percent_test_exact((float) $amount * (100 - $percent)), pg_percent_off($amount, $percent), 'off ' . $label);

		if (PHP_VERSION_ID < 80400) {
			pg_assert_same((int) round($amount * ($percent / 100)), pg_percent_of($amount, $percent), 'PHP 8.3 of ' . $label);
			pg_assert_same((int) round($amount - ($amount * ($percent / 100))), pg_percent_off($amount, $percent), 'PHP 8.3 off ' . $label);
		}
	}
}

// No offer percentage is a float product: its round() depends on the PHP
// version. The pages that discount shipping are read as text, comments left
// out.
function test_offer_percent_no_float_product()
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

		if (preg_match_all('~discount_\w*percentage\b[^;]*?/\s*100\b~', $code, $matches)) {
			$found[] = basename($file) . ': ' . implode(' | ', $matches[0]);
		}
	}

	pg_assert_same(array(), $found);
}
