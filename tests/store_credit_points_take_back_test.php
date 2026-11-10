<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reward points taken back on a cancellation or a refund
 * (includes/fn/store_credit_points.php): the points of an order's lines,
 * the refund markers, how a take-back is split between the balance and the
 * store credit, and which points cards give the credit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function store_credit_points_take_back_lines()
{
	return array(
		array('order_item_id' => 11, 'reward_points' => '10', 'quantity' => '3'),
		array('order_item_id' => 12, 'reward_points' => '25', 'quantity' => '1'),
		array('order_item_id' => 13, 'reward_points' => '0', 'quantity' => '4'),
	);
}

function test_store_credit_points_of_lines_matches_the_grant()
{
	$lines = store_credit_points_take_back_lines();

	pg_assert_same(55, pg_credit_points_of_lines($lines, null, true), 'every line with its quantity');
	pg_assert_same(0, pg_credit_points_of_lines($lines, null, false), 'program off: none, as the grant');

	$granted = 0;
	foreach ($lines as $line) {
		$granted += pg_order_line_reward_points($line, true);
	}
	pg_assert_same($granted, pg_credit_points_of_lines($lines, null, true), 'the same figure the completion granted');
}

function test_store_credit_points_of_refunded_lines_only()
{
	$lines = store_credit_points_take_back_lines();

	pg_assert_same(20, pg_credit_points_of_lines($lines, array(11 => 2), true), 'two of three units');
	pg_assert_same(30, pg_credit_points_of_lines($lines, array(11 => 9), true), 'capped at the line quantity');
	pg_assert_same(25, pg_credit_points_of_lines($lines, array(12 => 1, 99 => 5), true), 'a line not on the order counts nothing');
	pg_assert_same(0, pg_credit_points_of_lines($lines, array(), true), 'no lines');
	pg_assert_same(0, pg_credit_points_of_lines($lines, array(11 => -2), true), 'negative quantity');
}

function test_store_credit_points_marker_round_trip()
{
	pg_assert_same('Reward points taken back: 40', pg_credit_points_marker(40), 'marker');
	pg_assert_same('', pg_credit_points_marker(0), 'nothing taken, no marker');
	pg_assert_same(55, pg_credit_points_marked(array(pg_credit_points_marker(40), pg_credit_points_marker(15), 'Return request #3', '')), 'summed, other notes ignored');
}

function test_store_credit_points_take_back_from_balance_first()
{
	pg_assert_same(array('points' => 30, 'short' => 0, 'value' => 0), pg_credit_points_take_back_plan(100, 30, 10), 'balance covers it');
	pg_assert_same(array('points' => 30, 'short' => 0, 'value' => 0), pg_credit_points_take_back_plan(30, 30, 10), 'exactly the balance');
	pg_assert_same(array('points' => 0, 'short' => 0, 'value' => 0), pg_credit_points_take_back_plan(50, 0, 10), 'nothing to take');
}

function test_store_credit_points_take_back_short_comes_off_the_credit()
{
	// Rate 10 points per unit: 1 point = 10 minor units.
	pg_assert_same(array('points' => 20, 'short' => 35, 'value' => 350), pg_credit_points_take_back_plan(20, 55, 10), 'balance to zero, the rest valued');
	pg_assert_same(array('points' => 0, 'short' => 55, 'value' => 550), pg_credit_points_take_back_plan(-4, 55, 10), 'never below zero');
	// Rate 3: 7 points are worth 233.33 minor units; the conversion gave
	// 233 at most, the take-back asks 234 (rounded toward the shop).
	pg_assert_same(234, pg_credit_points_take_back_plan(0, 7, 3)['value'], 'rounded up');
	pg_assert_same(100, pg_credit_points_take_back_plan(0, 7, 7)['value'], 'exact worth not raised by float error');
	pg_assert_same(0, pg_credit_points_take_back_plan(0, 7, 0)['value'], 'no rate: nothing valued');
}

function test_store_credit_points_take_back_never_less_than_the_conversion_gave()
{
	foreach (array(200, 1000, 3, 0.3333, 7, 10) as $rate) {
		foreach (array(1, 13, 99, 5000) as $points) {
			$conversion = pg_credit_points_take($points, $rate, 0);
			$plan = pg_credit_points_take_back_plan(0, $conversion['points'], $rate);
			pg_assert_true($plan['value'] >= $conversion['value'], 'rate ' . $rate . ', ' . $points . ' points: take-back covers what was given');
		}
	}
}

function test_store_credit_points_take_back_exact_on_large_values()
{
	// 79779 * 100 / 0.29 is exactly 27510000; float division gives
	// 27510000.000...1, which ceil() raised a minor unit.
	pg_assert_same(27510000, pg_credit_points_take_back_plan(0, 79779, 0.29)['value'], 'rate 0.29');
	pg_assert_same(31910000, pg_credit_points_take_back_plan(0, 130831, 0.41)['value'], 'rate 0.41');
	pg_assert_same(0, pg_credit_points_take_back_plan(0, 7, 0.00001)['value'], 'a rate under the stored precision is off');
}

function test_store_credit_points_card_cuts_newest_first()
{
	$cards = array(
		array('id' => 4, 'balance' => '500'),
		array('id' => 9, 'balance' => '120'),
		array('id' => 7, 'balance' => '300'),
	);

	pg_assert_same(array(9 => 100), pg_credit_points_card_cuts($cards, 100), 'the newest card reduced');
	pg_assert_same(array(9 => 120), pg_credit_points_card_cuts($cards, 120), 'the newest card closed');
	pg_assert_same(array(9 => 120, 7 => 300, 4 => 80), pg_credit_points_card_cuts($cards, 500), 'then the ones before it');
	pg_assert_same(array(9 => 120, 7 => 300, 4 => 500), pg_credit_points_card_cuts($cards, 5000), 'never more than the cards hold');
	pg_assert_same(array(), pg_credit_points_card_cuts($cards, 0), 'nothing to take');
	pg_assert_same(array(), pg_credit_points_card_cuts(array(), 100), 'no cards');
}

function test_store_credit_points_take_back_is_wired_to_cancel_and_refunds()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$ecommerce = file_get_contents($root . 'includes/fn/ecommerce.php');
	$returns = file_get_contents($root . 'includes/fn/returns.php');
	$view_order = file_get_contents($root . 'view_order.php');

	pg_assert_true(strpos($ecommerce, 'pg_credit_points_order_cancelled($order);') !== false, 'the cancellation takes the points back');
	pg_assert_true(strpos($returns, 'pg_credit_points_lines_refunded($return[\'order_id\'], $returned_quantities);') !== false, 'a return refund takes its lines back');
	pg_assert_true(strpos($view_order, 'pg_credit_points_marker($refund_points[\'total\'])') !== false, 'a refund by lines notes what it took');
}
