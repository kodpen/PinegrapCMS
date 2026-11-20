<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sales watch's price error shield (includes/fn/sales_watch.php):
 * the usual price over 30 days, a typo price that sells, a real sale price that
 * does not trip, a cost above the price, the 24 hours after a person puts a
 * product back, six products in one pass and the 20 a day.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The usual price is weighted by how long each price applied: six hours of a
// typo do not move a month of the real price.
function test_sales_watch_price_usual()
{
	$end = 1800000000;
	$start = $end - (30 * 86400);

	$rows = array(
		array('changed_at' => $start - 86400, 'price' => 100000),
		array('changed_at' => $end - (6 * 3600), 'price' => 10000),
	);

	pg_assert_same(100000, pg_sales_watch_price_usual($rows, $start, $end));

	// A price that held for most of the month is the usual one.
	$rows = array(
		array('changed_at' => $start - 86400, 'price' => 100000),
		array('changed_at' => $start + (5 * 86400), 'price' => 80000),
	);

	pg_assert_same(80000, pg_sales_watch_price_usual($rows, $start, $end));

	// A product younger than the window: the time it has.
	pg_assert_same(5000, pg_sales_watch_price_usual(array(array('changed_at' => $end - 3600, 'price' => 5000)), $start, $end));
	pg_assert_same(null, pg_sales_watch_price_usual(array(), $start, $end));
}

// A price typed 100 for 1,000 that sells: under half the usual price, units
// well above the norm.
function test_sales_watch_price_typo_trips()
{
	pg_assert_same('half', pg_sales_watch_price_reason(10000, 100000, null, 12, 0.5, 0.5));

	// Too few units: a single buyer is not a run on the price.
	pg_assert_same('', pg_sales_watch_price_reason(10000, 100000, null, 4, 0.1, 0.5));

	// Units at the norm: the product sells that well anyway.
	pg_assert_same('', pg_sales_watch_price_reason(10000, 100000, null, 12, 5.0, 0.5));
}

// A real sale (30% off) that sells ten times the norm does not trip.
function test_sales_watch_price_real_sale_does_not_trip()
{
	pg_assert_same('', pg_sales_watch_price_reason(70000, 100000, null, 40, 4.0, 0.5));

	// A shop that set the share to 0.8 is told of it.
	pg_assert_same('half', pg_sales_watch_price_reason(70000, 100000, null, 40, 4.0, 0.8));
}

// A price under the goods' cost trips with Accounting on (a cost given),
// even above half the usual price.
function test_sales_watch_price_under_cost()
{
	pg_assert_same('cost', pg_sales_watch_price_reason(60000, 100000, 75000, 10, 1.0, 0.5));
	pg_assert_same('', pg_sales_watch_price_reason(60000, 100000, null, 10, 1.0, 0.5));
	// No cost recorded yet.
	pg_assert_same('', pg_sales_watch_price_reason(60000, 100000, 0, 10, 1.0, 0.5));
}

// Flagged products, as the pass builds them.
function sales_watch_price_test_flagged($ids)
{
	$flagged = array();

	foreach ($ids as $id) {
		$flagged[$id] = array('name' => 'P' . $id, 'price' => 100, 'usual' => 1000, 'units' => 9, 'reason' => 'half');
	}

	return $flagged;
}

// A product a person put back is left alone for 24 hours, then judged again.
function test_sales_watch_price_put_back_wins()
{
	$now = 1800000000;

	$record = pg_sales_watch_closed_add(pg_sales_watch_closed_normalize(''), array(7 => array('price' => 100, 'reason' => 'half')), $now - 7200);

	pg_assert_true(isset($record['closed'][7]));

	// Still off sale: it stays recorded.
	$kept = pg_sales_watch_closed_reconcile($record, array(7 => false), $now - 3600);
	pg_assert_true(isset($kept['closed'][7]));

	// Enabled again, any way: the entry is cleared, the time kept.
	$record = pg_sales_watch_closed_reconcile($record, array(7 => true), $now - 3600);
	pg_assert_false(isset($record['closed'][7]));
	pg_assert_same($now - 3600, $record['back'][7]);

	$plan = pg_sales_watch_price_plan(sales_watch_price_test_flagged(array(7)), $record, $now, true);
	pg_assert_same(array(), $plan['close']);
	pg_assert_same(array(), $plan['told']);

	// A day later the shield judges it again.
	$later = $now + 86400;
	$record = pg_sales_watch_closed_reconcile($record, array(), $later);
	pg_assert_false(isset($record['back'][7]));

	$plan = pg_sales_watch_price_plan(sales_watch_price_test_flagged(array(7)), $record, $later, true);
	pg_assert_same(array(7), $plan['close']);

	// A product deleted while closed leaves the record.
	$record = pg_sales_watch_closed_add($record, array(9 => array('price' => 100, 'reason' => 'half')), $later);
	$record = pg_sales_watch_closed_reconcile($record, array(), $later + 60);
	pg_assert_false(isset($record['closed'][9]));
	pg_assert_false(isset($record['back'][9]));
}

// Closing is off by default: told, not closed. An already closed product is
// neither told nor closed again.
function test_sales_watch_price_close_setting()
{
	$now = 1800000000;
	$record = pg_sales_watch_closed_normalize('');

	$plan = pg_sales_watch_price_plan(sales_watch_price_test_flagged(array(3, 4)), $record, $now, false);
	pg_assert_same(array(), $plan['close']);
	pg_assert_same(array(3, 4), $plan['told']);
	pg_assert_false($plan['campaign']);

	$record = pg_sales_watch_closed_add($record, array(3 => array('price' => 100, 'reason' => 'half')), $now - 600);
	$plan = pg_sales_watch_price_plan(sales_watch_price_test_flagged(array(3, 4)), $record, $now, true);
	pg_assert_same(array(4), $plan['close']);
	pg_assert_same(array(4), $plan['told']);
}

// Six products in one pass look like a campaign: none is closed, one alert
// asks.
function test_sales_watch_price_six_products_close_none()
{
	$now = 1800000000;

	$plan = pg_sales_watch_price_plan(sales_watch_price_test_flagged(array(1, 2, 3, 4, 5, 6)), pg_sales_watch_closed_normalize(''), $now, true);

	pg_assert_same(array(), $plan['close']);
	pg_assert_true($plan['campaign']);
	pg_assert_same(6, count($plan['told']));

	$detail = pg_sales_watch_price_detail(sales_watch_price_test_flagged(array(1, 2, 3, 4, 5, 6)), $plan);
	pg_assert_contains('Price drop on 6 products; a campaign?', $detail);

	// Five close.
	$plan = pg_sales_watch_price_plan(sales_watch_price_test_flagged(array(1, 2, 3, 4, 5)), pg_sales_watch_closed_normalize(''), $now, true);
	pg_assert_same(array(1, 2, 3, 4, 5), $plan['close']);
	pg_assert_false($plan['campaign']);
}

// At most 20 a day: with 18 closed in the last 24 hours, two more.
function test_sales_watch_price_twenty_a_day()
{
	$now = 1800000000;
	$record = pg_sales_watch_closed_normalize('');

	for ($i = 1; $i <= 18; $i++) {
		$record = pg_sales_watch_closed_add($record, array(100 + $i => array('price' => 100, 'reason' => 'half')), $now - (20 * 3600) + $i);
	}

	$plan = pg_sales_watch_price_plan(sales_watch_price_test_flagged(array(1, 2, 3, 4)), $record, $now, true);
	pg_assert_same(array(1, 2), $plan['close']);
	pg_assert_same(array(1, 2, 3, 4), $plan['told']);

	// The closings of yesterday no longer count.
	$record = pg_sales_watch_closed_reconcile($record, array_fill_keys(range(101, 118), false), $now + (5 * 3600));
	$plan = pg_sales_watch_price_plan(sales_watch_price_test_flagged(array(1, 2, 3, 4)), $record, $now + (5 * 3600), true);
	pg_assert_same(array(1, 2, 3, 4), $plan['close']);
}

// The record keeps at most 200 closed products, the newest.
function test_sales_watch_price_record_cap()
{
	$now = 1800000000;
	$record = pg_sales_watch_closed_normalize('');

	for ($i = 1; $i <= 205; $i++) {
		$record = pg_sales_watch_closed_add($record, array($i => array('price' => 100, 'reason' => 'half')), $now - (86400 * 3) + $i);
	}

	pg_assert_same(200, count($record['closed']));
	pg_assert_false(isset($record['closed'][1]));
	pg_assert_true(isset($record['closed'][205]));

	// A broken value reads as empty.
	$empty = pg_sales_watch_closed_normalize('{not json');
	pg_assert_same(array(), $empty['closed']);
}

// The alert names price, usual price and units of the first few, and says
// which were taken off sale.
function test_sales_watch_price_detail()
{
	$flagged = sales_watch_price_test_flagged(array(1, 2, 3, 4));
	$plan = array('told' => array(1, 2, 3, 4), 'close' => array(1), 'campaign' => false);

	$detail = pg_sales_watch_price_detail($flagged, $plan);

	pg_assert_contains('P1', $detail);
	pg_assert_contains('P3', $detail);
	pg_assert_false(strpos($detail, 'P4') !== false);
	pg_assert_contains('9', $detail);
	pg_assert_contains('Taken off sale: 1', $detail);
}
