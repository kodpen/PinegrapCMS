<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page experiment results in includes/fn/experiments_results.php:
 * the two-proportion test against known pairs, the lift, the visits still
 * needed and the plain sentence a result gives.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_experiment_near($expected, $actual, $tolerance, $label)
{
	pg_assert_true(is_float($actual) || is_int($actual), $label . ': a number');
	pg_assert_true(abs($expected - $actual) <= $tolerance, $label . ': ' . $expected . ' ~ ' . var_export($actual, true));
}

// The normal distribution's two-sided p from z.
function test_experiment_normal_p()
{
	_test_experiment_near(1.0, pg_experiment_two_sided_p(0.0), 0.0001, 'z 0');
	_test_experiment_near(0.05, pg_experiment_two_sided_p(1.96), 0.0005, 'z 1.96');
	_test_experiment_near(0.05, pg_experiment_two_sided_p(-1.96), 0.0005, 'symmetric');
	_test_experiment_near(0.0027, pg_experiment_two_sided_p(3.0), 0.0002, 'z 3');
}

// 10 % against 13 % over 1,000 views each: z about 2.10, p about 0.036.
function test_experiment_significance_known_pair()
{
	$r = pg_experiment_significance(1000, 100, 1000, 130);

	_test_experiment_near(0.10, $r['rate_a'], 0.00001, 'rate a');
	_test_experiment_near(0.13, $r['rate_b'], 0.00001, 'rate b');
	_test_experiment_near(0.30, $r['lift'], 0.00001, 'lift');
	_test_experiment_near(2.103, $r['z'], 0.002, 'z');
	_test_experiment_near(0.0355, $r['p'], 0.001, 'p');
	pg_assert_true($r['significant'], 'beyond chance');
	pg_assert_same(0, $r['needed'], 'nothing more needed');
}

// 10 % against 12 % over 100 views each: chance; the visits still needed.
function test_experiment_significance_too_few()
{
	$r = pg_experiment_significance(100, 10, 100, 12);

	pg_assert_false($r['significant'], 'not beyond chance');
	_test_experiment_near(0.2, $r['lift'], 0.00001, 'lift');
	// (1.96 + 0.84)^2 * (0.09 + 0.1056) / 0.0004 = 3833 per variant.
	pg_assert_true(($r['needed'] > 3600) && ($r['needed'] < 3800), 'about 3,700 more each: ' . $r['needed']);
}

// Empty, equal and impossible figures never divide by zero.
function test_experiment_significance_edges()
{
	$r = pg_experiment_significance(0, 0, 0, 0);
	pg_assert_same(0.0, $r['rate_a'], 'no views');
	pg_assert_same(null, $r['lift'], 'no lift without a control rate');
	pg_assert_false($r['significant'], 'nothing to tell');
	pg_assert_same(null, $r['needed'], 'cannot say yet');

	$same = pg_experiment_significance(500, 50, 500, 50);
	pg_assert_false($same['significant'], 'equal rates');
	_test_experiment_near(0.0, $same['lift'], 0.00001, 'no lift');
	pg_assert_same(null, $same['needed'], 'equal rates never part');

	$clamped = pg_experiment_significance(10, 50, 10, -3);
	_test_experiment_near(1.0, $clamped['rate_a'], 0.00001, 'more goals than views counts as all');
	_test_experiment_near(0.0, $clamped['rate_b'], 0.00001, 'negative goals count as none');
}

// The sentence a result gives.
function test_experiment_verdict_kind()
{
	pg_assert_same('ahead', pg_experiment_verdict_kind(pg_experiment_significance(1000, 100, 1000, 130)), 'b ahead');
	pg_assert_same('behind', pg_experiment_verdict_kind(pg_experiment_significance(1000, 130, 1000, 100)), 'b behind');
	pg_assert_same('more', pg_experiment_verdict_kind(pg_experiment_significance(100, 10, 100, 12)), 'not enough yet');
	pg_assert_same('none', pg_experiment_verdict_kind(pg_experiment_significance(0, 0, 0, 0)), 'no visits');
	pg_assert_same('even', pg_experiment_verdict_kind(pg_experiment_significance(500, 50, 500, 50)), 'no difference');
}

// Daily rates from hourly rows: summed per day and variant, every day of the
// range present.
function test_experiment_daily_series()
{
	$rows = array(
		array('day' => '2026-11-01', 'variant_id' => 1, 'views' => 10, 'goals' => 1),
		array('day' => '2026-11-01', 'variant_id' => 1, 'views' => 10, 'goals' => 3),
		array('day' => '2026-11-03', 'variant_id' => 2, 'views' => 4, 'goals' => 1),
	);

	$series = pg_experiment_daily_series($rows, array(1, 2), '2026-11-01', '2026-11-03');

	pg_assert_same(array('2026-11-01', '2026-11-02', '2026-11-03'), $series['days'], 'every day');
	pg_assert_same(array(20.0, null, null), $series['rates'][1], 'a day without views has no rate');
	pg_assert_same(array(null, null, 25.0), $series['rates'][2], 'percent');
}
