<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customer rhythm rules (includes/fn/customer_rhythm.php):
 * a product's days per unit, a customer's own interval, the chance curve,
 * the stages and how they move, and the products due for a reorder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A fixed "now" and a day in seconds for the cases below.
function _cr_test_now()
{
	return 1790000000;
}

function _cr_test_day()
{
	return 86400;
}

// One purchase line as the nightly pass hands it over.
function _cr_test_line($customer, $product, $quantity, $days_ago, $extra = array())
{
	return array_merge(array(
		'customer' => $customer,
		'product_id' => $product,
		'quantity' => $quantity,
		'time' => _cr_test_now() - ($days_ago * _cr_test_day()),
		'type' => 'online',
	), $extra);
}

// One counted order row, placed $days_ago days before now.
function _cr_test_order($days_ago, $total = 10000, $extra = array())
{
	return array_merge(array(
		'order_date' => _cr_test_now() - ($days_ago * _cr_test_day()),
		'total' => $total,
		'refunded_amount' => 0,
		'type' => 'online',
		'status' => 'complete',
		'cancelled_at' => null,
	), $extra);
}

function test_customer_rhythm_median()
{
	pg_assert_true(function_exists('pg_customer_rhythm_median'), 'the module is loaded');
	pg_assert_same(null, pg_customer_rhythm_median(array()));
	pg_assert_same(3.0, pg_customer_rhythm_median(array(5, 1, 3)));
	pg_assert_same(2.5, pg_customer_rhythm_median(array(4, 1, 3, 2)));
}

function test_customer_rhythm_two_pack_at_half_the_pace()
{
	$lines = array();

	// Five customers buy one unit every 30 days, five others a two-pack
	// every 60 days: both are 30 days per unit.
	for ($c = 1; $c <= 5; $c++) {
		foreach (array(300, 270, 240) as $ago) {
			$lines[] = _cr_test_line('one' . $c, 7, 1, $ago);
		}
		foreach (array(300, 240, 180) as $ago) {
			$lines[] = _cr_test_line('two' . $c, 7, 2, $ago);
		}
	}

	$rhythms = pg_customer_rhythm_products($lines, array(), array(), _cr_test_now());

	pg_assert_true(isset($rhythms[7]), 'the product has a rhythm');
	pg_assert_same(30.0, $rhythms[7]['days_per_unit']);
	pg_assert_same(20, $rhythms[7]['pairs']);
	pg_assert_same(10, $rhythms[7]['customers']);
	pg_assert_same('product', $rhythms[7]['from']);

	// A two-pack bought 10 days ago is due in 50 days, one unit in 20.
	$due = pg_customer_rhythm_due_products(array(_cr_test_line('x', 7, 2, 10)), $rhythms, _cr_test_now());
	pg_assert_same(array(), $due, 'not in its window yet');

	$due = pg_customer_rhythm_due_products(array(_cr_test_line('x', 7, 2, 58)), $rhythms, _cr_test_now());
	pg_assert_same(1, count($due), 'the window opens three days before');
	pg_assert_same(7, $due[0]['product_id']);
	pg_assert_same(2, $due[0]['quantity']);
	pg_assert_same(_cr_test_now() + (2 * _cr_test_day()), $due[0]['due']);
}

function test_customer_rhythm_product_needs_ten_pairs_of_five_customers()
{
	$lines = array();

	// Twelve pairs from only four customers: not enough customers.
	for ($c = 1; $c <= 4; $c++) {
		foreach (array(400, 380, 360, 340) as $ago) {
			$lines[] = _cr_test_line('c' . $c, 8, 1, $ago);
		}
	}

	pg_assert_same(array(), pg_customer_rhythm_products($lines, array(), array(), _cr_test_now()), 'no rhythm, no group');

	// Its group holds a product with enough pairs: the group's median.
	for ($c = 1; $c <= 5; $c++) {
		foreach (array(100, 90, 80) as $ago) {
			$lines[] = _cr_test_line('g' . $c, 9, 1, $ago);
		}
	}

	$rhythms = pg_customer_rhythm_products($lines, array(8 => array(3), 9 => array(3), 11 => 3), array(), _cr_test_now());

	pg_assert_same(10.0, $rhythms[9]['days_per_unit']);
	pg_assert_same('product', $rhythms[9]['from']);
	pg_assert_same('group', $rhythms[8]['from'], 'too few customers of its own');
	pg_assert_same(20.0, $rhythms[8]['days_per_unit'], 'the group pools its products: 12 pairs of 20 days, 10 of 10');
	pg_assert_same('group', $rhythms[11]['from'], 'a product never bought again takes its group');

	// A pair older than 24 months is not read.
	$old = array();
	for ($c = 1; $c <= 5; $c++) {
		$old[] = _cr_test_line('o' . $c, 12, 1, 800);
		$old[] = _cr_test_line('o' . $c, 12, 1, 760);
		$old[] = _cr_test_line('o' . $c, 12, 1, 720);
	}
	pg_assert_same(array(), pg_customer_rhythm_products($old, array(), array(), _cr_test_now()));
}

function test_customer_rhythm_subscription_product()
{
	$lines = array();

	for ($c = 1; $c <= 5; $c++) {
		foreach (array(120, 90, 60) as $ago) {
			$lines[] = _cr_test_line('s' . $c, 20, 1, $ago);
			$lines[] = _cr_test_line('s' . $c, 21, 1, $ago);
		}
	}

	$rhythms = pg_customer_rhythm_products($lines, array(20 => array(5), 21 => array(5)), array(20), _cr_test_now());

	pg_assert_false(isset($rhythms[20]), 'a subscription product has no rhythm, not even its group\'s');
	pg_assert_same(30.0, $rhythms[21]['days_per_unit']);

	$due = pg_customer_rhythm_due_products(array(_cr_test_line('s1', 20, 1, 60), _cr_test_line('s1', 21, 1, 60)), $rhythms, _cr_test_now());
	pg_assert_same(1, count($due));
	pg_assert_same(21, $due[0]['product_id']);
}

function test_customer_rhythm_marketplace_order()
{
	// A marketplace order counts for a product's rhythm.
	$lines = array();
	for ($c = 1; $c <= 5; $c++) {
		foreach (array(90, 60, 30) as $ago) {
			$lines[] = _cr_test_line('m' . $c, 30, 1, $ago, array('type' => 'marketplace'));
		}
	}
	$rhythms = pg_customer_rhythm_products($lines, array(), array(), _cr_test_now());
	pg_assert_same(30.0, $rhythms[30]['days_per_unit']);

	// It is never a reorder reminder.
	pg_assert_same(array(), pg_customer_rhythm_due_products(array(_cr_test_line('m1', 30, 1, 40, array('type' => 'marketplace'))), $rhythms, _cr_test_now()));

	// Nor does it make a customer's rhythm.
	$customer = pg_customer_rhythm_customer(array(
		_cr_test_order(100),
		_cr_test_order(50, 10000, array('type' => 'marketplace')),
		_cr_test_order(40),
	), 25.0, _cr_test_now());
	pg_assert_same(2, $customer['orders']);
	pg_assert_same(60.0, $customer['interval']);
}

function test_customer_rhythm_due_products_rules()
{
	$rhythms = array(40 => array('days_per_unit' => 10.0), 41 => array('days_per_unit' => 5.0));

	$lines = array(
		_cr_test_line('a', 40, 1, 30),
		_cr_test_line('a', 40, 1, 2),          // bought again since: not due
		_cr_test_line('a', 41, 1, 20),
		_cr_test_line('a', 41, 2, 20),         // two lines of one order add up
		_cr_test_line('a', 42, 1, 300),        // no rhythm
	);

	$due = pg_customer_rhythm_due_products($lines, $rhythms, _cr_test_now());
	pg_assert_same(1, count($due));
	pg_assert_same(41, $due[0]['product_id']);
	pg_assert_same(3, $due[0]['quantity']);

	// Off sale or out of stock: left out.
	pg_assert_same(array(), pg_customer_rhythm_due_products($lines, $rhythms, _cr_test_now(), array(41 => false)));
}

function test_customer_rhythm_one_order_customer()
{
	$customer = pg_customer_rhythm_customer(array(_cr_test_order(10, 5000)), 45.0, _cr_test_now());

	pg_assert_same(1, $customer['orders']);
	pg_assert_same(45.0, $customer['interval'], 'the store\'s median days to a second order');
	pg_assert_false($customer['own']);
	pg_assert_same(10, $customer['days']);
	pg_assert_same('regular', pg_customer_rhythm_stage($customer['days'], $customer['interval'], 0.5, false));

	// A store where no one ordered twice yet: no interval, no stage.
	$none = pg_customer_rhythm_customer(array(_cr_test_order(10)), null, _cr_test_now());
	pg_assert_same(null, $none['interval']);
	pg_assert_same('', pg_customer_rhythm_stage($none['days'], $none['interval'], 0.5, false));

	// Orders that do not count are not read.
	$customer = pg_customer_rhythm_customer(array(
		_cr_test_order(10),
		_cr_test_order(5, 10000, array('status' => 'cancelled')),
		_cr_test_order(3, 10000, array('order_date' => 0)),
	), 45.0, _cr_test_now());
	pg_assert_same(1, $customer['orders']);

	pg_assert_same(30.0, pg_customer_rhythm_store_median(array(
		pg_customer_rhythm_customer(array(_cr_test_order(100), _cr_test_order(80)), null, _cr_test_now()),
		pg_customer_rhythm_customer(array(_cr_test_order(100), _cr_test_order(60), _cr_test_order(1)), null, _cr_test_now()),
		pg_customer_rhythm_customer(array(_cr_test_order(100)), null, _cr_test_now()),
	)), 'days to a second order: 20 and 40');
}

function test_customer_rhythm_own_interval_and_value()
{
	$customer = pg_customer_rhythm_customer(array(
		_cr_test_order(100, 10000),
		_cr_test_order(80, 20000),
		_cr_test_order(80, 30000),   // same day: one visit, no gap
		_cr_test_order(50, 30000),
		_cr_test_order(10, 10000),
	), 45.0, _cr_test_now());

	pg_assert_same(5, $customer['orders']);
	pg_assert_true($customer['own']);
	pg_assert_same(array(20.0, 30.0, 40.0), $customer['gaps']);
	pg_assert_same(30.0, $customer['interval']);
	pg_assert_same(20000, $customer['average']);
	pg_assert_same(100000, $customer['best_year']);

	// chance 0.5 x 365 / 30 x 200.00 = 1216.67, under twice the best year.
	pg_assert_same(121667, pg_customer_rhythm_expected(0.5, 30.0, 20000, 100000));
	pg_assert_same(200000, pg_customer_rhythm_expected(1.0, 10.0, 20000, 100000), 'at most twice the best year');
	pg_assert_same(0, pg_customer_rhythm_expected(0.5, null, 20000, 100000));
}

function test_customer_rhythm_stages()
{
	pg_assert_same('regular', pg_customer_rhythm_stage(29, 30.0, 0.9, false));
	pg_assert_same('due', pg_customer_rhythm_stage(30, 30.0, 0.9, false));
	pg_assert_same('late', pg_customer_rhythm_stage(60, 30.0, 0.9, false));
	pg_assert_same('slipping', pg_customer_rhythm_stage(90, 30.0, 0.10, false));
	pg_assert_same('lost', pg_customer_rhythm_stage(90, 30.0, 0.09, false));
	pg_assert_same('late', pg_customer_rhythm_stage(60, 30.0, 0.01, false), 'lost only past three intervals');
	pg_assert_same('won_back', pg_customer_rhythm_stage(29, 30.0, 0.9, true));
	pg_assert_same('due', pg_customer_rhythm_stage(30, 30.0, 0.9, true), 'won back until d reaches i');
}

function test_customer_rhythm_stage_never_goes_back_between_orders()
{
	$curve = array('fixed' => true, 'bands' => array());
	$orders = array(_cr_test_order(200), _cr_test_order(170), _cr_test_order(140), _cr_test_order(110));
	$customer = pg_customer_rhythm_customer($orders, 45.0, _cr_test_now());

	// 110 days at a 30-day interval: past three intervals.
	$step = pg_customer_rhythm_step(null, $customer, $curve);
	pg_assert_same('slipping', $step['stage']);
	pg_assert_same(30.0, $step['interval']);

	// Stored as Lost; a curve that now gives more chance does not bring it back.
	$stored = array('stage' => 'lost', 'interval' => 30.0, 'last' => $customer['last']);
	$step = pg_customer_rhythm_step($stored, $customer, $curve);
	pg_assert_same('lost', $step['stage']);
	pg_assert_false($step['moved']);

	// The bounds stay fixed between orders: a stored 10-day interval is kept
	// even when the rebuild works out another.
	$stored = array('stage' => 'regular', 'interval' => 10.0, 'last' => $customer['last']);
	$step = pg_customer_rhythm_step($stored, $customer, $curve);
	pg_assert_same(10.0, $step['interval']);
	pg_assert_same('lost', $step['stage'], '110 days at 10 days, fixed curve');
	pg_assert_true($step['moved']);
	pg_assert_same('regular', $step['from']);

	pg_assert_same('lost', pg_customer_rhythm_stage_forward('lost', 'due'));
	pg_assert_same('late', pg_customer_rhythm_stage_forward('due', 'late'));
	pg_assert_same('due', pg_customer_rhythm_stage_forward('won_back', 'due'));
	pg_assert_same('won_back', pg_customer_rhythm_stage_forward('won_back', 'regular'));
}

function test_customer_rhythm_new_order_resets_the_bounds()
{
	$curve = array('fixed' => true, 'bands' => array());
	$before = pg_customer_rhythm_customer(array(_cr_test_order(300), _cr_test_order(270), _cr_test_order(240)), 45.0, _cr_test_now());
	$stored = pg_customer_rhythm_step(null, $before, $curve);
	pg_assert_same('lost', $stored['stage'], '240 days at 30 days');

	// An order after a Lost stage: Won back, with the new interval.
	$after = pg_customer_rhythm_customer(array(_cr_test_order(300), _cr_test_order(270), _cr_test_order(240), _cr_test_order(5)), 45.0, _cr_test_now());
	$step = pg_customer_rhythm_step($stored, $after, $curve);
	pg_assert_same('won_back', $step['stage']);
	pg_assert_same(30.0, $step['interval']);
	pg_assert_true($step['moved']);

	// An order on time: Regular, never Won back.
	$stored = array('stage' => 'due', 'interval' => 30.0, 'last' => _cr_test_now() - (40 * _cr_test_day()));
	$after = pg_customer_rhythm_customer(array(_cr_test_order(70), _cr_test_order(40), _cr_test_order(2)), 45.0, _cr_test_now());
	$step = pg_customer_rhythm_step($stored, $after, $curve);
	pg_assert_same('regular', $step['stage']);
	pg_assert_same(34.0, $step['interval']);
}

function test_customer_rhythm_chance_curve()
{
	// Under 200 customers with two orders: the fixed curve.
	$few = array(pg_customer_rhythm_customer(array(_cr_test_order(60), _cr_test_order(30)), 30.0, _cr_test_now()));
	$curve = pg_customer_rhythm_curve($few);
	pg_assert_true($curve['fixed']);
	pg_assert_same(pg_customer_rhythm_fixed_chance(0), pg_customer_rhythm_chance($curve, 0, 30.0));
	pg_assert_true(pg_customer_rhythm_chance($curve, 30, 30.0) > pg_customer_rhythm_chance($curve, 120, 30.0), 'the fixed curve falls');
	pg_assert_true(pg_customer_rhythm_chance($curve, 1000, 30.0) < 0.10, 'far past the interval it is under ten percent');

	// 300 customers with a 10-day interval: 200 once waited 2.0x and came
	// back; 100 came back after 1.0x and are now silent for 2.5x.
	$customers = array();
	for ($c = 0; $c < 200; $c++) {
		$customers[] = pg_customer_rhythm_customer(array(_cr_test_order(40), _cr_test_order(30), _cr_test_order(20), _cr_test_order(0)), 30.0, _cr_test_now());
	}
	for ($c = 0; $c < 100; $c++) {
		$customers[] = pg_customer_rhythm_customer(array(_cr_test_order(45), _cr_test_order(35), _cr_test_order(25)), 30.0, _cr_test_now());
	}

	$curve = pg_customer_rhythm_curve($customers);
	pg_assert_false($curve['fixed']);

	pg_assert_same(1.0, pg_customer_rhythm_chance($curve, 10, 10.0), '1.0x: everyone reached it and came back');
	pg_assert_same(200 / 300, pg_customer_rhythm_chance($curve, 15, 10.0), '1.5x: 200 of 300 came back');
	pg_assert_same(0.0, pg_customer_rhythm_chance($curve, 25, 10.0), '2.5x: only the silent ones reached it');
	pg_assert_same(0.0, pg_customer_rhythm_chance($curve, 1000, 10.0), 'past the data: never above the last band');
	pg_assert_same(0.0, pg_customer_rhythm_chance($curve, 10, null), 'no interval, no chance');
}
