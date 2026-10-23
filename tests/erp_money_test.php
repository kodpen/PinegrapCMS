<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP money helpers (includes/erp/money.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A Turkish-formatted amount: point groups thousands, comma marks decimals.
function test_erp_kurus_reads_turkish_grouping()
{
	pg_assert_same(123456, erp_kurus('1.234,56'));
}

// Three digits behind the only separator make it a thousands separator.
function test_erp_kurus_reads_three_digits_as_thousands()
{
	pg_assert_same(123400, erp_kurus('1.234'));
}

// Taking 18% tax out of a gross amount leaves net and tax that add back up.
function test_erp_split_gross_takes_tax_out()
{
	pg_assert_same(array('net' => 10000, 'tax' => 1800), erp_split_gross(11800, 18));
}

// An even split that does not divide gives the left-over kurus to the first line.
function test_erp_allocate_hands_out_the_remainder()
{
	pg_assert_same(array(34, 33, 33), erp_allocate(100, array(1, 1, 1)));
}

// Equal remainders take the left-over kurus in the lines' own order however
// many lines there are: before PHP 8.0 sorting was not stable, and the equal
// remainders of more than 16 lines went to other lines than on PHP 8.
function test_erp_allocate_gives_equal_remainders_in_line_order()
{
	$weights = array_fill_keys(range(100, 119), 1);

	pg_assert_same(array_fill_keys(range(100, 109), 1) + array_fill_keys(range(110, 119), 0), erp_allocate(10, $weights));
}

// The ERP and the checkout (pg_tax_spread()) split alike, so an order's line
// taxes and its invoice's line VAT are the same figures on every PHP version;
// weights from a short list make many equal remainders.
function test_erp_allocate_splits_as_the_checkout()
{
	mt_srand(20261023);

	for ($run = 0; $run < 500; $run++) {
		$weights = array();
		$count = mt_rand(1, 40);

		for ($i = 0; $i < $count; $i++) {
			$weights[200 + $i] = mt_rand(0, 3) * (mt_rand(0, 1) ? 1 : 250);
		}

		$total = mt_rand(0, 1) ? mt_rand(0, 60) : mt_rand(0, 100000);

		pg_assert_same(pg_tax_spread($total, $weights), erp_allocate($total, $weights), 'run ' . $run);
	}
}

// Two lines of 1.02 at 20% carry 0.41 of VAT, taken on 2.04, not 0.20 + 0.20;
// the kurus of the spread goes to the first line.
function test_erp_rate_vat_takes_vat_on_the_rate_total()
{
	$result = erp_rate_vat(array(
		array('net' => 102, 'tax_rate' => 20),
		array('net' => 102, 'tax_rate' => 20),
	));

	pg_assert_same(41, $result['vat']);
	pg_assert_same(array(21, 20), array($result['lines'][0]['vat'], $result['lines'][1]['vat']));
	pg_assert_same(0, $result['withholding']);
	pg_assert_same(array(array('tax_rate' => 20.0, 'withholding_rate' => 0.0, 'net' => 204, 'vat' => 41)), $result['groups']);
}

// The figures Paraşüt worked out for these lines (demo company, 2026-10-21):
// VAT per group of VAT rate and withholding rate, the withholding summed over
// the groups and rounded once for the document.
function test_erp_rate_vat_reproduces_the_provider_figures()
{
	// Lines as [net, VAT %, withholding %], then the VAT and the withholding.
	$cases = array(
		array(array(array(105, 20, 40), array(105, 20, 40)), 42, 17),
		array(array(array(107, 20, 40)), 21, 8),
		array(array(array(103, 20, 40), array(103, 20, 40)), 41, 16),
		array(array(array(105, 20, 40), array(110, 20, 70)), 43, 24),
		array(array(array(105, 20, 40), array(110, 10, 40)), 32, 13),
		array(array(array(107, 20, 40), array(107, 20, 0)), 42, 8),
		array(array(array(103, 20, 40), array(103, 20, 70)), 42, 23),
		array(array(array(1245, 10, 0), array(1250, 1, 0)), 138, 0),
	);

	foreach ($cases as $index => $case) {
		$lines = array();
		foreach ($case[0] as $line) {
			$lines[] = array('net' => $line[0], 'tax_rate' => $line[1], 'withholding_rate' => $line[2]);
		}

		$result = erp_rate_vat($lines);

		pg_assert_same($case[1], $result['vat'], 'VAT, case ' . $index);
		pg_assert_same($case[2], $result['withholding'], 'withholding, case ' . $index);
	}
}

// The second tax is taken on its own rate's sum: two lines of 1.10 at 5% carry
// 0.11, not 0.06 + 0.06, and nothing of it is withheld.
function test_erp_rate_vat_takes_the_second_tax_per_rate()
{
	$result = erp_rate_vat(array(
		'a' => array('net' => 110, 'tax_rate' => 20, 'withholding_rate' => 50, 'tax2_rate' => 5),
		'b' => array('net' => 110, 'tax_rate' => 20, 'withholding_rate' => 50, 'tax2_rate' => 5),
	));

	pg_assert_same(11, $result['tax2']);
	pg_assert_same(11, $result['lines']['a']['tax2'] + $result['lines']['b']['tax2']);
	pg_assert_same(44, $result['vat']);
	pg_assert_same(22, $result['withholding']);
}

// The caller's keys come back in their order; a line without VAT has nothing
// withheld whatever rate it names.
function test_erp_rate_vat_keeps_the_keys_and_withholds_only_vat()
{
	$result = erp_rate_vat(array(
		7 => array('net' => 500, 'tax_rate' => 0, 'withholding_rate' => 40),
		'x' => array('net' => 1000, 'tax_rate' => '20.000', 'withholding_rate' => '50.000'),
	));

	pg_assert_same(array(7, 'x'), array_keys($result['lines']));
	pg_assert_same(array('vat' => 0, 'tax2' => 0, 'withholding' => 0), $result['lines'][7]);
	pg_assert_same(array('vat' => 200, 'tax2' => 0, 'withholding' => 100), $result['lines']['x']);
	pg_assert_same(2, count($result['groups']));
}

// No lines, no taxes.
function test_erp_rate_vat_of_nothing_is_zero()
{
	pg_assert_same(
		array('lines' => array(), 'groups' => array(), 'vat' => 0, 'tax2' => 0, 'withholding' => 0),
		erp_rate_vat(array())
	);
}

// On random documents the totals are the group arithmetic worked out in whole
// numbers, the lines add up to them, and each line stays within a kurus of
// what it would carry on its own.
function test_erp_rate_vat_adds_up_on_random_documents()
{
	mt_srand(20261021);
	$rates = array(0, 1, 10, 20);
	$withholdings = array(0, 0, 20, 40, 50, 70, 90);

	for ($run = 0; $run < 2000; $run++) {
		$lines = array();
		$count = mt_rand(1, 6);
		for ($i = 0; $i < $count; $i++) {
			$rate = $rates[mt_rand(0, 3)];
			$lines[] = array(
				'net' => mt_rand(0, 100000),
				'tax_rate' => $rate,
				'withholding_rate' => ($rate > 0) ? $withholdings[mt_rand(0, 6)] : 0,
				'tax2_rate' => (mt_rand(0, 3) === 0) ? 7 : 0,
			);
		}

		$result = erp_rate_vat($lines);

		// Half up in whole numbers; every rate here is a whole percentage.
		$groups = array();
		$tax2_net = 0;
		foreach ($lines as $line) {
			$key = $line['tax_rate'] . '/' . $line['withholding_rate'];
			if (!isset($groups[$key])) {
				$groups[$key] = array('rate' => $line['tax_rate'], 'withholding' => $line['withholding_rate'], 'net' => 0);
			}
			$groups[$key]['net'] += $line['net'];
			if ($line['tax2_rate'] > 0) {
				$tax2_net += $line['net'];
			}
		}
		$vat = 0;
		$withheld = 0;
		foreach ($groups as $group) {
			$group_vat = intdiv(2 * $group['net'] * $group['rate'] + 100, 200);
			$vat += $group_vat;
			$withheld += $group_vat * $group['withholding'];
		}

		pg_assert_same($vat, $result['vat'], 'VAT, run ' . $run);
		pg_assert_same(intdiv(2 * $withheld + 100, 200), $result['withholding'], 'withholding, run ' . $run);
		pg_assert_same(intdiv(2 * $tax2_net * 7 + 100, 200), $result['tax2'], 'second tax, run ' . $run);

		$sums = array('vat' => 0, 'tax2' => 0, 'withholding' => 0);
		foreach ($result['lines'] as $index => $figures) {
			$line = $lines[$index];
			foreach (array_keys($sums) as $name) {
				$sums[$name] += $figures[$name];
			}
			pg_assert_true(abs($figures['vat'] - erp_apply_rate($line['net'], $line['tax_rate'])) <= 1, 'line VAT, run ' . $run);
			pg_assert_true(abs($figures['tax2'] - erp_apply_rate($line['net'], $line['tax2_rate'])) <= 1, 'line second tax, run ' . $run);
			pg_assert_true(abs($figures['withholding'] - erp_apply_rate($figures['vat'], $line['withholding_rate'])) <= 1, 'line withholding, run ' . $run);
		}
		pg_assert_same(array('vat' => $result['vat'], 'tax2' => $result['tax2'], 'withholding' => $result['withholding']), $sums, 'lines add up, run ' . $run);
	}
}

// A decimal becomes whole units on the digits it is written with, half away
// from zero: 1.15 is 11500 although 1.15 x 10000 is 11499.999... in binary,
// and 1.33335 rounds up at four places. The units are a whole number held in
// a float, so 250,000 at four places is not clamped at PHP_INT_MAX on a
// 32-bit build.
function test_erp_decimal_units_round_on_the_written_digits()
{
	pg_assert_same(11500.0, erp_decimal_units(1.15, 4));
	pg_assert_same(13334.0, erp_decimal_units(1.33335, 4));
	pg_assert_same(13333.0, erp_decimal_units('1.3333333333', 4));
	pg_assert_same(-20001.0, erp_decimal_units(-2.00005, 4));
	pg_assert_same(4350.0, erp_decimal_units(4.35, 3));
	pg_assert_same(20000.0, erp_decimal_units('20.000', 3));
	pg_assert_same(0.0, erp_decimal_units(0.00004, 4));
	pg_assert_same(3.0, erp_decimal_units(2.5, 0));
	pg_assert_same(2500000000.0, erp_decimal_units(250000, 4));
	pg_assert_same('0', sprintf('%.0F', erp_decimal_units(-0.00004, 4)), 'no negative zero');
}

// A quantity is taken at the four places it is stored with.
function test_erp_quantity_round_keeps_four_places()
{
	pg_assert_same(1.3333, erp_quantity_round(1.3333333333));
	pg_assert_same(0.1235, erp_quantity_round('0.12345'));
	pg_assert_same(2.0, erp_quantity_round(2));
	pg_assert_same(0.0, erp_quantity_round(0.00004));
	pg_assert_same(250000.0, erp_quantity_round(250000));
	pg_assert_same(99999999999.9999, erp_quantity_round('99999999999.9999'));
}

// A line total and a rate are worked out on the decimals as written, the
// same on every PHP version and in the invoice editor: 13.10 x 1.15 is
// 15.065, so 15.07, although 1310 * 1.15 is 1506.4999... in binary; 4.35% of
// 30.00 is 1.305, so 1.31.
function test_erp_money_rounds_the_written_decimals()
{
	pg_assert_same(1507, erp_line_total(1310, 1.15));
	pg_assert_same(794, erp_line_total(345, 2.3));
	pg_assert_same(131, erp_apply_rate(3000, 4.35));
	pg_assert_same(35, erp_apply_rate(1500, 2.3));
	pg_assert_same(-131, erp_apply_rate(-3000, 4.35));
	pg_assert_same(-1507, erp_line_total(-1310, '1.1500'));
	pg_assert_same(0, erp_line_total(0, 3.5));
	pg_assert_same(199995, erp_line_total(150000, 1.3333333333));
	// A unit price past 2^31 kurus needs a 64-bit int to be passed in at all.
	if (PHP_INT_SIZE >= 8) {
		pg_assert_same(43211394672705, erp_line_total(123456789012, 350.0123));
	}
}

// Intermediate figures that pass 2^31 on the way to a result that fits: the
// remainder product of a rate reaches 10^10, and 300,000 at four places is
// more units than a 32-bit int holds. These run on every integer width.
function test_erp_money_intermediates_past_32_bits()
{
	pg_assert_same(25000, erp_apply_rate(99999, 25));
	pg_assert_same(22500, erp_apply_rate(25000, 90));
	pg_assert_same(21475, erp_apply_rate(85900, 25));
	pg_assert_same(50000, erp_apply_rate(99999, 50));
	pg_assert_same(-25000, erp_apply_rate(-99999, 25));
	pg_assert_same(30000000, erp_line_total(100, 300000));
	pg_assert_same(-30000000, erp_line_total(-100, 300000));
	pg_assert_same(30000050, erp_line_total(100, 300000.5));
	pg_assert_same(array('net' => 99999, 'tax' => 25000), erp_split_gross(124999, 25));
	// A result past PHP_INT_MAX is a float on a 32-bit build, an int on 64-bit.
	pg_assert_same(4000000000.0, (float) erp_line_total(2000000000, 2));
	pg_assert_same(-4000000000.0, (float) erp_line_total(-2000000000, 2));
}

// On random figures the result is the exact product rounded half away from
// zero, worked out here in whole numbers. Every other run takes a shop's
// figures - prices in steps of 0.05, quantities and rates with one or two
// places - where a product of floats lands next to a half most often. The
// reference products pass 2^31, so on a 32-bit build they are worked out in
// floats, which hold every whole number below 2^53 exactly.
function test_erp_money_line_total_and_rate_match_whole_number_arithmetic()
{
	mt_srand(20261022);

	for ($run = 0; $run < 10000; $run++) {
		$sign = mt_rand(0, 9) ? 1 : -1;

		if ($run % 2) {
			$amount = mt_rand(20, 4000) * 5;
			$quantity = mt_rand(1, 999) * (mt_rand(0, 1) ? 100 : 1000);
			$rate = mt_rand(1, 9999) * 10;
		} else {
			$amount = mt_rand(0, 1) ? mt_rand(0, 99999) : mt_rand(0, 999999999);
			$quantity = mt_rand(1, 999999);
			$rate = mt_rand(0, 100000);
		}

		if (PHP_INT_SIZE >= 8) {
			pg_assert_same($sign * intdiv(2 * $amount * $quantity + 10000, 20000), erp_line_total($sign * $amount, $quantity / 10000), 'line total, run ' . $run);
			pg_assert_same($sign * intdiv(2 * $amount * $rate + 100000, 200000), erp_apply_rate($sign * $amount, $rate / 1000), 'rate, run ' . $run);
		} else {
			pg_assert_same($sign * erp_test_float_whole_quotient(2.0 * $amount * $quantity + 10000, 20000), (float) erp_line_total($sign * $amount, $quantity / 10000), 'line total, run ' . $run);
			pg_assert_same($sign * erp_test_float_whole_quotient(2.0 * $amount * $rate + 100000, 200000), (float) erp_apply_rate($sign * $amount, $rate / 1000), 'rate, run ' . $run);
		}
	}
}

// The whole part of $numerator / $divisor for whole floats below 2^53, exact:
// fmod() of whole floats has no rounding error, and the division is then of a
// multiple of the divisor.
function erp_test_float_whole_quotient($numerator, $divisor)
{
	return ($numerator - fmod($numerator, $divisor)) / $divisor;
}
