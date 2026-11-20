<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the return spike of the sales watch
 * (includes/fn/sales_watch.php): the binomial upper tail, the return rate a
 * product is held to (its own, or the shop's with too little history), the
 * products told, the sentence of the bell row and the nightly turn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One product's figures as the pass reads them.
function sales_watch_returns_test_product($name, $returned, $sold, $base_returned, $base_sold, array $reasons = array())
{
	return array(
		'name'          => $name,
		'returned'      => $returned,
		'sold'          => $sold,
		'base_returned' => $base_returned,
		'base_sold'     => $base_sold,
		'reasons'       => $reasons,
	);
}

// The upper tail against known values.
function test_sales_watch_binomial_upper()
{
	// P(X >= 5 | n = 10, p = 0.5) = 638 / 1024.
	pg_assert_true(abs(pg_sales_watch_binomial_upper(5, 10, 0.5) - 0.623047) < 0.00001);
	// P(X >= 3 | n = 20, p = 0.05) = 0.075484.
	pg_assert_true(abs(pg_sales_watch_binomial_upper(3, 20, 0.05) - 0.075484) < 0.00001);
	pg_assert_same(1.0, pg_sales_watch_binomial_upper(0, 10, 0.1));
	pg_assert_same(0.0, pg_sales_watch_binomial_upper(11, 10, 0.1));
	pg_assert_same(0.0, pg_sales_watch_binomial_upper(1, 10, 0.0));
	// A tiny tail stays above zero and below the alpha, not lost to 1 - (1 - e).
	$p = pg_sales_watch_binomial_upper(40, 100, 0.02);
	pg_assert_true(($p > 0) && ($p < 1e-20));
}

// The rate: the product's own with history, the shop's without; never 0.
function test_sales_watch_return_rate()
{
	$shop = array('returned' => 50, 'sold' => 1000);

	// 4 of 200: about 2%.
	pg_assert_true(abs(pg_sales_watch_return_rate(4, 200, $shop) - (5 / 202)) < 0.000001);
	// 0 of 5: too little history, the shop's rate.
	pg_assert_true(abs(pg_sales_watch_return_rate(0, 5, $shop) - (51 / 1002)) < 0.000001);
	// A shop that never had a return still has a rate above 0.
	pg_assert_true(pg_sales_watch_return_rate(0, 0, array('returned' => 0, 'sold' => 0)) > 0);
}

// A product sent back far above its own rate is told; one at its usual rate
// is not; fewer than 5 units is never told.
function test_sales_watch_return_judge()
{
	$shop = array('returned' => 100, 'sold' => 5000);

	$products = array(
		// Usually 2% back; 15 of 60 sold now. Told, mostly damaged.
		11 => sales_watch_returns_test_product('Glass vase', 15, 60, 8, 400, array('damaged' => 12, 'changed_mind' => 3)),
		// Usually 25% back (shoes, sizes); 15 of 60 is its rate.
		12 => sales_watch_returns_test_product('Running shoe', 15, 60, 100, 400, array('size' => 15)),
		// 4 of 5 back, far above the rate, but under 5 units.
		13 => sales_watch_returns_test_product('Lamp', 4, 5, 0, 300, array('faulty' => 4)),
		// No history: held to the shop's 2%; 9 of 30 is told.
		14 => sales_watch_returns_test_product('New mug', 9, 30, 0, 0, array('faulty' => 6, 'damaged' => 3)),
	);

	$told = pg_sales_watch_return_judge($products, $shop, 0.01);

	pg_assert_same(array(11, 14), array_keys($told));
	pg_assert_same('damaged', $told[11]['reason']);
	pg_assert_same('faulty', $told[14]['reason']);
	pg_assert_same(15, $told[11]['returned']);
	pg_assert_same(60, $told[11]['sold']);
	pg_assert_true($told[11]['p'] < 0.01);
	pg_assert_true(abs($told[11]['expected'] - (60 * 9 / 402)) < 0.000001);

	// More units back than were sold the month before (bought earlier): the
	// tries are at least the units back, and it is still judged.
	$told = pg_sales_watch_return_judge(array(
		21 => sales_watch_returns_test_product('Old stock', 8, 2, 2, 500),
	), $shop, 0.01);
	pg_assert_same(array(21), array_keys($told));
	pg_assert_same(8, $told[21]['sold']);

	// The alpha is split over the products judged: one borderline product
	// among many is not told.
	$many = array();
	for ($i = 1; $i <= 50; $i++) {
		$many[$i] = sales_watch_returns_test_product('P' . $i, 5, 100, 5, 1000);
	}
	pg_assert_same(array(), pg_sales_watch_return_judge($many, $shop, 0.01));
	pg_assert_same(array(1), array_keys(pg_sales_watch_return_judge(array(1 => $many[1]), $shop, 0.01)));

	// The strongest first.
	$told = pg_sales_watch_return_judge(array(
		31 => sales_watch_returns_test_product('A', 6, 40, 4, 400),
		32 => sales_watch_returns_test_product('B', 30, 40, 4, 400),
	), $shop, 0.01);
	pg_assert_same(array(32, 31), array_keys($told));
}

// One sentence for the pass: the products counted, the first three named.
function test_sales_watch_returns_sentence()
{
	$told = array();
	foreach (array('Vase', 'Mug', 'Lamp', 'Shoe') as $i => $name) {
		$told[$i + 1] = array('name' => $name, 'returned' => 10, 'sold' => 40, 'expected' => 1.6, 'p' => 0.0001, 'reason' => ($i === 0) ? 'damaged' : '');
	}

	$sentence = pg_sales_watch_returns_sentence($told);

	pg_assert_true(strpos($sentence, '4') !== false);
	pg_assert_true(strpos($sentence, 'Vase') !== false);
	pg_assert_true(strpos($sentence, 'Lamp') !== false);
	pg_assert_true(strpos($sentence, 'Shoe') === false);
	pg_assert_true(strpos($sentence, 'It arrived damaged') !== false);
}

// Once a night: from 03:00 on, not twice on one day.
function test_sales_watch_returns_due()
{
	$night = mktime(3, 10, 0, 11, 20, 2026);

	pg_assert_true(pg_sales_watch_returns_due($night, 0));
	pg_assert_true(pg_sales_watch_returns_due($night, mktime(4, 0, 0, 11, 19, 2026)));
	pg_assert_true(!pg_sales_watch_returns_due($night, mktime(3, 0, 0, 11, 20, 2026)));
	pg_assert_true(!pg_sales_watch_returns_due(mktime(2, 50, 0, 11, 20, 2026), mktime(4, 0, 0, 11, 19, 2026)));
	// Later the same day, when the night's pass was missed.
	pg_assert_true(pg_sales_watch_returns_due(mktime(15, 0, 0, 11, 20, 2026), mktime(4, 0, 0, 11, 19, 2026)));
}
