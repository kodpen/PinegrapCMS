<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reward points turned into store credit
 * (includes/fn/store_credit_points.php): the settings parsers, the checks a
 * conversion makes and the fewest points worth the smallest conversion.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_store_credit_points_rate_is_stored_as_a_plain_number()
{
	pg_assert_same('10', pg_credit_points_parse_rate('10'), 'whole rate');
	pg_assert_same('2.5', pg_credit_points_parse_rate('2,5'), 'decimal comma');
	pg_assert_same('2.5', pg_credit_points_parse_rate('2.5'), 'decimal dot');
	pg_assert_same('0.3333', pg_credit_points_parse_rate('0.33333'), 'four decimals kept');
	pg_assert_same('1000000', pg_credit_points_parse_rate('99999999'), 'capped');
	pg_assert_same('', pg_credit_points_parse_rate(''), 'empty is off');
	pg_assert_same('', pg_credit_points_parse_rate('0'), 'zero is off');
	pg_assert_same('', pg_credit_points_parse_rate('-5'), 'negative is off');
	pg_assert_same('', pg_credit_points_parse_rate('abc'), 'not a number is off');
	pg_assert_same('', pg_credit_points_parse_rate(array('5')), 'an array is off');
}

function test_store_credit_points_smallest_is_minor_units()
{
	pg_assert_same(500, pg_credit_points_parse_smallest('5'), 'five units');
	pg_assert_same(1250, pg_credit_points_parse_smallest('12,50'), 'decimal comma');
	pg_assert_same(1, pg_credit_points_parse_smallest('0.005'), 'half a minor unit rounds up');
	pg_assert_same(0, pg_credit_points_parse_smallest(''), 'empty');
	pg_assert_same(0, pg_credit_points_parse_smallest('-3'), 'negative');
}

function test_store_credit_points_check_refuses_what_it_cannot_convert()
{
	pg_assert_same('', pg_credit_points_check('100', 250, 10, 0), 'whole points within the balance');
	pg_assert_same('', pg_credit_points_check(250, 250, 10, 0), 'all of them as an int');
	pg_assert_same('off', pg_credit_points_check('100', 250, 0, 0), 'no rate set');
	pg_assert_same('points', pg_credit_points_check('1.5', 250, 10, 0), 'a fraction');
	pg_assert_same('points', pg_credit_points_check('0', 250, 10, 0), 'zero');
	pg_assert_same('points', pg_credit_points_check('-5', 250, 10, 0), 'negative');
	pg_assert_same('points', pg_credit_points_check(array('5'), 250, 10, 0), 'an array');
	pg_assert_same('points', pg_credit_points_check('99999999999', 250, 10, 0), 'beyond the column');
	pg_assert_same('balance', pg_credit_points_check('251', 250, 10, 0), 'more than the balance');
	pg_assert_same('small', pg_credit_points_check('40', 250, 10, 500), 'worth 4,00 under a 5,00 smallest');
	pg_assert_same('', pg_credit_points_check('50', 250, 10, 500), 'worth exactly the smallest');
	pg_assert_same('small', pg_credit_points_check('1', 250, 1000, 0), 'worth less than half a minor unit');
}

function test_store_credit_points_minimum_is_the_fewest_points_that_reach_the_smallest()
{
	pg_assert_same(50, pg_credit_points_minimum(10, 500), '10 points a unit, 5,00 smallest');
	pg_assert_same(1, pg_credit_points_minimum(10, 0), 'no smallest: one point is 0,10');
	pg_assert_same(5, pg_credit_points_minimum(1000, 0), '1000 a unit: 5 points round to one minor unit');
	pg_assert_same(0, pg_credit_points_minimum(0, 500), 'off');

	foreach (array(array(3, 700), array(7, 1), array(0.3333, 999), array(250, 0)) as $case) {
		$minimum = pg_credit_points_minimum($case[0], $case[1]);
		pg_assert_true(pg_credit_points_value($minimum, $case[0], $case[1]) >= max(1, $case[1]), 'the minimum reaches the smallest');
		pg_assert_true(($minimum === 1) || (pg_credit_points_value($minimum - 1, $case[0], $case[1]) < max(1, $case[1])), 'one point fewer does not');
	}
}

function test_store_credit_points_conversion_never_runs_without_a_user()
{
	pg_assert_same('off', pg_credit_points_convert(0, '100')['error'], 'no user');
}
