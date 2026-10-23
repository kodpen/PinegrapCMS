<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checkout's tax at a rate (pg_tax_apply_rate(),
 * pg_multiply_units(), pg_decimal_units() in includes/fn/ecommerce.php): the
 * ERP's exact rule (erp_apply_rate(), includes/erp/money.php), copied because
 * a store without the ERP never loads money.php, and held equal to it here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of a function without its comments.
function tax_apply_rate_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());
	$source = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
	$code = '';

	foreach (token_get_all('<?php ' . $source) as $token) {
		if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG), true)) {
			continue;
		}

		$code .= is_array($token) ? $token[1] : $token;
	}

	return $code;
}

// An amount whose tax at a rate of $units thousandths of a per cent is
// exactly a half (amount x units / 100000 ends in .5), where a product of
// floats can round either way; 0 when the rate has none. Below 10^8, so it
// also works in 32-bit integers.
function tax_apply_rate_test_half($units)
{
	$a = $units;
	$b = 50000;

	while ($b !== 0) {
		$rest = $a % $b;
		$a = $b;
		$b = $rest;
	}

	if ((intdiv($units, $a) % 2) === 0) {
		return 0;
	}

	return (2 * mt_rand(0, 999) + 1) * intdiv(50000, $a);
}

// 2.3% of 15.00 is 0.345, so 0.35; 4.35% of 30.00 is 1.305, so 1.31, though
// round(1500 * 2.3 / 100) is 34 on PHP 8.4 and later. Half away from zero
// below zero too. 25% of 999.99 passes 2^31 on the way (a 32-bit build).
function test_tax_apply_rate_rounds_the_written_decimals()
{
	pg_assert_same(35, pg_tax_apply_rate(1500, 2.3));
	pg_assert_same(35, pg_tax_apply_rate(1500, '2.300'));
	pg_assert_same(131, pg_tax_apply_rate(3000, 4.35));
	pg_assert_same(35, pg_tax_apply_rate(375, '9.200'));
	pg_assert_same(-35, pg_tax_apply_rate(-1500, 2.3));
	pg_assert_same(0, pg_tax_apply_rate(1500, 0));
	pg_assert_same(25000, pg_tax_apply_rate(99999, 25));
	pg_assert_same(400000000, pg_tax_apply_rate(2000000000, 20));
}

// The copies are the ERP's sources, both branches: CI is 64-bit and never
// runs the float steps a 32-bit build takes.
function test_tax_apply_rate_copies_are_the_erp_sources()
{
	$normal = function ($code) {
		return preg_replace('~\s+~', '', str_replace(array('erp_', 'pg_'), 'X_', $code));
	};

	foreach (array('pg_decimal_units' => 'erp_decimal_units', 'pg_multiply_units' => 'erp_multiply_units') as $copy => $original) {
		if (pg_assert_true(function_exists($copy), $copy)) {
			pg_assert_same($normal(tax_apply_rate_test_source($original)), $normal(tax_apply_rate_test_source($copy)), $copy);
		}
	}
}

// erp_apply_rate()'s figure for any amount a 32-bit int holds and any rate
// to 100% at three places, half of them at a half-kurus tax.
function test_tax_apply_rate_is_the_erps()
{
	mt_srand(20261023);

	for ($run = 0; $run < 20000; $run++) {
		$units = mt_rand(0, 100000);
		$rate = mt_rand(0, 1) ? (intdiv($units, 1000) . '.' . sprintf('%03d', $units % 1000)) : $units / 1000;
		$half = ($units > 0) ? tax_apply_rate_test_half($units) : 0;
		$amount = (($run % 2) && $half) ? $half : mt_rand(0, mt_getrandmax());
		$amount = mt_rand(0, 9) ? $amount : -$amount;

		pg_assert_same(erp_apply_rate($amount, $rate), pg_tax_apply_rate($amount, $rate), $amount . ' at ' . $rate);
	}
}

// No checkout tax is a float product of a rate put through round(): the
// result depends on the PHP version. The estimate pg_tax_rate_amount() looks
// around ($near) is not a tax.
function test_tax_apply_rate_no_float_round_of_a_rate()
{
	foreach (array('pg_tax_by_rate', 'pg_tax_rate_amount', 'get_tax_inclusive_price') as $function) {
		$code = preg_replace('~^.*\$near\s*=.*$~m', '', tax_apply_rate_test_source($function));
		pg_assert_false((bool) preg_match('~\bround\s*\([^;]*rate~i', $code), $function);
	}

	// The till (includes/local_sale.php, add_order.php) is not loaded here:
	// its files are read as text, comments left out.
	foreach (array('/includes/local_sale.php', '/add_order.php') as $file) {
		$code = '';

		foreach (token_get_all((string) file_get_contents(PG_FUNCTIONS_DIR . $file)) as $token) {
			if (!is_array($token) || !in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
				$code .= is_array($token) ? $token[1] : $token;
			}
		}

		pg_assert_false((bool) preg_match('~\bround\s*\([^;]*rate~i', $code), $file);
	}
}
