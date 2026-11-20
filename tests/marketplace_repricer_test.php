<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the marketplace repricer's robot pass
 * (includes/fn/marketplace_repricer.php): Win and Grow while winning at and
 * over the floor, a lost buybox, the day's limits, the price sent, the
 * listing's state, the alerts and the log rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/marketplace_repricer.php');

// One listing as the pass hands it over: rules' price 100.00, floor 80.00,
// step 1.00, Grow while winning's limit 10%.
function marketplace_repricer_test_input($changes = array())
{
	return array_merge(array(
		'strategy' => MP_PRICING_STRATEGY_WIN,
		'rank' => 2,
		'others' => true,
		'buybox_price' => 9500,
		'current' => 10000,
		'rule_price' => 10000,
		'floor' => 8000,
		'step' => 100,
		'step_unit' => MP_PRICING_STEP_AMOUNT,
		'grow_cap' => 1000,
		'raised_today' => false,
		'lost_price' => 0,
		'lost_at' => 0,
		'now' => 1800000000,
	), $changes);
}

function test_marketplace_repricer_win_undercuts_by_one_step()
{
	$target = mp_repricer_target(marketplace_repricer_test_input());

	pg_assert_same(9400, $target['price'], 'buybox price less one step');
	pg_assert_same(MP_REPRICER_REASON_LOST, $target['reason'], 'lost buybox');
}

function test_marketplace_repricer_win_percent_step_from_the_buybox_price()
{
	$target = mp_repricer_target(marketplace_repricer_test_input(array(
		'step' => 250, 'step_unit' => MP_PRICING_STEP_PERCENT,
	)));

	pg_assert_same(9500 - 238, $target['price'], '2.5% of 95.00 is 2.375, rounded to 2.38');
}

function test_marketplace_repricer_win_stops_at_the_floor()
{
	$target = mp_repricer_target(marketplace_repricer_test_input(array('buybox_price' => 8050)));

	pg_assert_same(8000, $target['price'], 'never under the floor');
	pg_assert_same(MP_REPRICER_REASON_FLOOR, $target['reason'], 'floor reached');

	$under = mp_repricer_target(marketplace_repricer_test_input(array('buybox_price' => 7000)));

	pg_assert_same(8000, $under['price'], 'a buybox under the floor is not followed');
	pg_assert_same(MP_REPRICER_REASON_FLOOR, $under['reason'], 'floor reached');

	$held = mp_repricer_target(marketplace_repricer_test_input(array('buybox_price' => 7000, 'current' => 8000)));

	pg_assert_same(8000, $held['price'], 'already at the floor');
	pg_assert_same('', $held['reason'], 'no change at the floor');
}

function test_marketplace_repricer_win_never_over_the_rules_price()
{
	$target = mp_repricer_target(marketplace_repricer_test_input(array('buybox_price' => 15000, 'current' => 9000)));

	pg_assert_same(9000, $target['price'], 'losing while cheaper: not raised');
	pg_assert_same('', $target['reason'], 'no change');

	$alone = mp_repricer_target(marketplace_repricer_test_input(array(
		'rank' => 1, 'others' => false, 'current' => 9000,
	)));

	pg_assert_same(10000, $alone['price'], 'alone: back to the rules price');
	pg_assert_same(MP_REPRICER_REASON_RAISED, $alone['reason'], 'raised');

	$holding = mp_repricer_target(marketplace_repricer_test_input(array('rank' => 1, 'current' => 9000)));

	pg_assert_same(9000, $holding['price'], 'holding the buybox against others: kept');
	pg_assert_same('', $holding['reason'], 'no change');
}

function test_marketplace_repricer_no_floor_no_robot()
{
	$target = mp_repricer_target(marketplace_repricer_test_input(array('floor' => null)));

	pg_assert_same(10000, $target['price'], 'no floor: left at its price');
	pg_assert_same('', $target['reason'], 'no change');
}

function test_marketplace_repricer_no_read_no_change()
{
	$target = mp_repricer_target(marketplace_repricer_test_input(array('rank' => 0)));

	pg_assert_same(10000, $target['price'], 'no rank: nothing to act on');
	pg_assert_same('', $target['reason'], 'no change');
}

function test_marketplace_repricer_fixed_never_moves()
{
	$target = mp_repricer_target(marketplace_repricer_test_input(array('strategy' => MP_PRICING_STRATEGY_FIXED)));

	pg_assert_same(10000, $target['price'], 'Fixed keeps the rules price');
	pg_assert_same('', $target['reason'], 'no change');
}

function test_marketplace_repricer_grow_raises_one_step_while_winning()
{
	$target = mp_repricer_target(marketplace_repricer_test_input(array(
		'strategy' => MP_PRICING_STRATEGY_GROW, 'rank' => 1,
	)));

	pg_assert_same(10100, $target['price'], 'one step up');
	pg_assert_same(MP_REPRICER_REASON_RAISED, $target['reason'], 'raised');

	$today = mp_repricer_target(marketplace_repricer_test_input(array(
		'strategy' => MP_PRICING_STRATEGY_GROW, 'rank' => 1, 'raised_today' => true,
	)));

	pg_assert_same(10000, $today['price'], 'one raise a day');
	pg_assert_same('', $today['reason'], 'no change');
}

function test_marketplace_repricer_grow_stops_at_its_limit()
{
	$target = mp_repricer_target(marketplace_repricer_test_input(array(
		'strategy' => MP_PRICING_STRATEGY_GROW, 'rank' => 1, 'current' => 10950,
	)));

	pg_assert_same(11000, $target['price'], 'at most 10% over the rules price');

	$held = mp_repricer_target(marketplace_repricer_test_input(array(
		'strategy' => MP_PRICING_STRATEGY_GROW, 'rank' => 1, 'current' => 11000,
	)));

	pg_assert_same(11000, $held['price'], 'already at its limit');
	pg_assert_same('', $held['reason'], 'no change');
}

function test_marketplace_repricer_grow_lost_buybox_steps_back_and_remembers()
{
	$input = marketplace_repricer_test_input(array(
		'strategy' => MP_PRICING_STRATEGY_GROW, 'rank' => 2, 'current' => 10300,
	));
	$target = mp_repricer_target($input);

	pg_assert_same(10200, $target['price'], 'one step back');
	pg_assert_same(MP_REPRICER_REASON_BACK, $target['reason'], 'stepped back');
	pg_assert_same(10300, $target['lost_price'], 'the lost price is kept');
	pg_assert_same($input['now'], $target['lost_at'], 'with its time');

	$at_rules = mp_repricer_target(marketplace_repricer_test_input(array(
		'strategy' => MP_PRICING_STRATEGY_GROW, 'rank' => 2,
	)));

	pg_assert_same(10000, $at_rules['price'], 'never under the rules price by stepping back');
	pg_assert_same('', $at_rules['reason'], 'no change');
}

function test_marketplace_repricer_grow_skips_a_lost_price_for_seven_days()
{
	$now = 1800000000;
	$input = array('strategy' => MP_PRICING_STRATEGY_GROW, 'rank' => 1, 'current' => 10200,
		'lost_price' => 10300, 'lost_at' => $now - 6 * 86400, 'now' => $now);

	$target = mp_repricer_target(marketplace_repricer_test_input($input));

	pg_assert_same(10200, $target['price'], 'the lost price is not tried within 7 days');
	pg_assert_same('', $target['reason'], 'no change');

	$input['lost_at'] = $now - 7 * 86400 - 1;
	$later = mp_repricer_target(marketplace_repricer_test_input($input));

	pg_assert_same(10300, $later['price'], 'tried again after 7 days');
}

function test_marketplace_repricer_bound_keeps_floor_and_cap()
{
	pg_assert_same(10000, mp_repricer_bound(12000, 10000, 8000, MP_PRICING_STRATEGY_WIN, 1000), 'Win: rules price at most');
	pg_assert_same(11000, mp_repricer_bound(12000, 10000, 8000, MP_PRICING_STRATEGY_GROW, 1000), 'Grow: 10% over');
	pg_assert_same(8000, mp_repricer_bound(7000, 10000, 8000, MP_PRICING_STRATEGY_WIN, 1000), 'never under the floor');
	pg_assert_same(null, mp_repricer_bound(9000, 10000, null, MP_PRICING_STRATEGY_WIN, 1000), 'no floor: no robot price');
}

function test_marketplace_repricer_day_from_the_log()
{
	$now = 1800000000;
	$day = mp_repricer_day(array(
		array('old_price' => 10000, 'new_price' => 9800, 'reason' => 'lost', 'at' => $now - 7200),
		array('old_price' => 9800, 'new_price' => 9900, 'reason' => 'raised', 'at' => $now - 3600),
	), 9900);

	pg_assert_same(2, $day['changes'], 'two changes');
	pg_assert_same(10000, $day['base'], 'the price before the first');
	pg_assert_same(true, $day['raised'], 'raised today');

	$none = mp_repricer_day(array(), 9900);

	pg_assert_same(array('changes' => 0, 'base' => 9900, 'raised' => false), $none, 'no change today');
}

function test_marketplace_repricer_four_changes_a_day()
{
	$day = array('changes' => 4, 'base' => 10000, 'raised' => false);

	pg_assert_same(9600, mp_repricer_limit(9600, 9500, $day), 'a fifth change is held');

	$day['changes'] = 3;

	pg_assert_same(9500, mp_repricer_limit(9600, 9500, $day), 'a fourth goes');
}

function test_marketplace_repricer_ten_percent_a_day()
{
	$day = array('changes' => 1, 'base' => 10000, 'raised' => false);

	pg_assert_same(9000, mp_repricer_limit(9500, 8000, $day), 'down at most 10% of the day\'s first price');
	pg_assert_same(11000, mp_repricer_limit(10500, 12000, $day), 'up at most 10%');
	pg_assert_same(9000, mp_repricer_limit(9000, 8500, $day), 'already 10% down: held');
}

function test_marketplace_repricer_decide_applies_limits_after_the_strategy()
{
	$decision = mp_repricer_decide(marketplace_repricer_test_input(array('buybox_price' => 8050)),
		array('changes' => 0, 'base' => 10000, 'raised' => false));

	pg_assert_same(9000, $decision['price'], 'floor 80.00 wanted, 10% a day allowed');
	pg_assert_same(true, $decision['changed'], 'changed');
	pg_assert_same(MP_REPRICER_REASON_LOST, $decision['reason'], 'the floor was not reached');

	$held = mp_repricer_decide(marketplace_repricer_test_input(),
		array('changes' => 4, 'base' => 10000, 'raised' => false));

	pg_assert_same(10000, $held['price'], 'held by the day\'s count');
	pg_assert_same(false, $held['changed'], 'not changed');
	pg_assert_same(0, $held['lost_at'], 'nothing remembered when nothing moved');
}

function test_marketplace_repricer_decide_grow_raise_limited_once_a_day()
{
	$decision = mp_repricer_decide(marketplace_repricer_test_input(array(
		'strategy' => MP_PRICING_STRATEGY_GROW, 'rank' => 1,
	)), array('changes' => 1, 'base' => 10000, 'raised' => true));

	pg_assert_same(false, $decision['changed'], 'raised already today');
}

function test_marketplace_repricer_listing_state()
{
	pg_assert_same(MP_REPRICER_STATE_NO_FLOOR, mp_repricer_state(1, 10000, null), 'no floor');
	pg_assert_same(MP_REPRICER_STATE_NONE, mp_repricer_state(0, 10000, 8000), 'no read');
	pg_assert_same(MP_REPRICER_STATE_WINNING, mp_repricer_state(1, 8000, 8000), 'winning');
	pg_assert_same(MP_REPRICER_STATE_LOSING, mp_repricer_state(2, 9000, 8000), 'losing');
	pg_assert_same(MP_REPRICER_STATE_FLOOR, mp_repricer_state(3, 8000, 8000), 'losing at the floor');
}

function test_marketplace_repricer_send_price()
{
	$figures = array('strategy' => MP_PRICING_STRATEGY_WIN, 'robot_price' => 9400, 'own_price' => 0);
	$on = array('robot' => MP_PRICING_ROBOT_ON, 'grow_cap' => 1000);

	pg_assert_same(9400, mp_repricer_send_price($on, $figures, 10000, 8000), 'the robot price goes');
	pg_assert_same(null, mp_repricer_send_price(array('robot' => MP_PRICING_ROBOT_TRY, 'grow_cap' => 1000), $figures, 10000, 8000), 'try first sends nothing');
	pg_assert_same(null, mp_repricer_send_price($on, $figures, 10000, null), 'no floor: the rules price');
	pg_assert_same(null, mp_repricer_send_price($on, array_merge($figures, array('strategy' => MP_PRICING_STRATEGY_FIXED)), 10000, 8000), 'Fixed: the rules price');
	pg_assert_same(null, mp_repricer_send_price($on, array_merge($figures, array('robot_price' => 0)), 10000, 8000), 'no robot price yet');
	pg_assert_same(9000, mp_repricer_send_price($on, $figures, 9000, 8000), 'the site price fell: never over the rules price');
	pg_assert_same(9500, mp_repricer_send_price($on, $figures, 10000, 9500), 'the floor rose: never under it');
	pg_assert_same(null, mp_repricer_send_price(null, $figures, 10000, 8000), 'no account row: no robot');
}

function test_marketplace_repricer_losing_alert()
{
	pg_assert_same(true, mp_repricer_losing(10, 6), 'more than half');
	pg_assert_same(false, mp_repricer_losing(10, 5), 'half is not more than half');
	pg_assert_same(false, mp_repricer_losing(0, 0), 'no Win listings');
}

function test_marketplace_repricer_failures_count_to_three()
{
	$count = mp_repricer_failures(0, false);
	$count = mp_repricer_failures($count, false);

	pg_assert_same(2, $count, 'two failures');
	pg_assert_same(false, $count >= MP_REPRICER_FAILURES, 'no alert yet');

	$count = mp_repricer_failures($count, false);

	pg_assert_same(true, $count >= MP_REPRICER_FAILURES, 'the third read in a row alerts');
	pg_assert_same(0, mp_repricer_failures($count, true), 'a good read starts over');
}

function test_marketplace_repricer_alert_codes_are_known()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/ops_alerts.php');

	$codes = pg_ops_alert_codes();

	pg_assert_same(true, isset($codes[MP_REPRICER_ALERT_LOSING]), 'losing code');
	pg_assert_same(true, isset($codes[MP_REPRICER_ALERT_FAILING]), 'failing code');
}

function test_marketplace_repricer_reasons_are_listed_once()
{
	pg_assert_same(array('lost', 'floor', 'raised', 'back'), mp_repricer_reasons(), 'the four reasons');
}

function test_marketplace_repricer_log_sql()
{
	$sql = mp_repricer_log_sql(7, array(
		12 => array('old_price' => 10000, 'new_price' => 9400, 'floor' => 8000, 'buybox_price' => 9500,
			'reason' => MP_REPRICER_REASON_LOST),
	), 'TRY', false, 1800000000);

	pg_assert_same(true, strpos($sql, 'INSERT INTO marketplace_price_log') === 0, 'one insert');
	pg_assert_same(true, strpos($sql, "('7', '12', '10000', '9400', '8000', '9500', 'TRY', 'lost', '0', '1800000000')") !== false, 'the row, not sent');
	pg_assert_same('', mp_repricer_log_sql(7, array(), 'TRY', true, 1), 'nothing to log');
}
