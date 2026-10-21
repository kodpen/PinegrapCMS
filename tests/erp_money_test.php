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
