<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sales watch (includes/fn/sales_watch.php): the norm learnt
 * from hourly order counts, the Poisson and binomial tails, the windows, the
 * marketplace channel judged up to its last sync, the store alert holding
 * back the channel alerts, a broken checkout - and the once-a-day key by code
 * and subject in includes/fn/ops_alerts.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Hourly counts: $per_hour orders in every hour of the $weeks weeks before
// $cut (an hour index), except the weeks listed in $empty_weeks (1 = the
// week just before $cut).
function test_sales_watch_counts($cut, $per_hour, $weeks = 8, array $empty_weeks = array())
{
	$counts = array();

	for ($h = $cut - ($weeks * 168) - 2; $h < $cut; $h++) {

		$week = (int) floor(($cut - 1 - $h) / 168) + 1;

		if (!in_array($week, $empty_weeks, true) && ($per_hour > 0)) {
			$counts[$h] = $per_hour;
		}
	}

	return $counts;
}

// $n order times spread evenly over ($start, $end].
function test_sales_watch_times($start, $end, $n)
{
	$times = array();

	for ($i = 1; $i <= $n; $i++) {
		$times[] = (int) floor($start + (($end - $start) * $i / $n));
	}

	return $times;
}

// The tails against known values.
function test_sales_watch_tails()
{
	// e^-4.6: none expected under about 4.6 is just about 1%.
	pg_assert_true(abs(pg_sales_watch_poisson_lower(0, 4.6) - 0.010052) < 0.00001);
	pg_assert_true(abs(pg_sales_watch_poisson_lower(2, 3.0) - 0.423190) < 0.00001);
	pg_assert_same(1.0, pg_sales_watch_poisson_lower(5, 0.0));
	// A large expectation does not underflow to nothing.
	pg_assert_true(abs(pg_sales_watch_poisson_lower(2000, 2000.0) - 0.505947) < 0.001);

	pg_assert_true(abs(pg_sales_watch_binomial_lower(0, 5, 0.5) - 0.03125) < 0.000001);
	pg_assert_true(abs(pg_sales_watch_binomial_lower(3, 10, 0.5) - 0.171875) < 0.000001);
	pg_assert_same(1.0, pg_sales_watch_binomial_lower(10, 10, 0.3));
}

// The five windows, less those shorter than the channel's sync interval.
function test_sales_watch_windows()
{
	pg_assert_same(array(1, 2, 3, 6, 12), pg_sales_watch_windows(0));
	pg_assert_same(array(1, 2, 3, 6, 12), pg_sales_watch_windows(300));
	pg_assert_same(array(3, 6, 12), pg_sales_watch_windows(7200 + 1));
	pg_assert_same(array(), pg_sales_watch_windows(86400));
}

// A holiday week in the eight does not pull the norm down: the median
// leaves it out, and the scale stays inside 0.5 to 2.
function test_sales_watch_norm_holiday_week()
{
	$cut = 500000;
	$norm = pg_sales_watch_norm(test_sales_watch_counts($cut, 4, 8, array(3)), $cut);

	pg_assert_true(is_array($norm));
	pg_assert_same(4 * 168 * 7, $norm['total']);

	$hour = pg_sales_watch_expected_hour($norm, $cut - 1);
	pg_assert_true(($hour > 4.0) && ($hour < 4.7), 'expected ' . $hour);

	// The holiday was last week: the scale stops at one half.
	$norm = pg_sales_watch_norm(test_sales_watch_counts($cut, 4, 8, array(1)), $cut);
	pg_assert_same(0.5, $norm['scale']);
	pg_assert_true(abs(pg_sales_watch_expected_hour($norm, $cut - 1) - 2.0) < 0.0001);

	// Twelve hours without an order after a normal history are told.
	$end = $cut * 3600;
	$norm = pg_sales_watch_norm(test_sales_watch_counts($cut, 4), $cut);
	$found = pg_sales_watch_judge_orders($norm, array(), $end, 0, 0.002);

	pg_assert_true(is_array($found));
	pg_assert_same(0, $found['orders']);
	pg_assert_true($found['expected'] > 7.0);

	// The usual rhythm is not.
	pg_assert_same(null, pg_sales_watch_judge_orders($norm, test_sales_watch_times($end - 43200, $end, 48), $end, 0, 0.002));
}

// A quiet small shop: under 20 orders in the eight weeks there is no norm;
// a little over, a silent day is still not unusual.
function test_sales_watch_quiet_small_shop()
{
	$cut = 500000;
	$end = $cut * 3600;

	$counts = array();
	for ($i = 0; $i < 15; $i++) {
		$counts[$cut - 10 - ($i * 80)] = 1;
	}
	pg_assert_same(null, pg_sales_watch_norm($counts, $cut));

	for ($i = 15; $i < 30; $i++) {
		$counts[$cut - 10 - ($i * 80)] = 1;
	}
	$norm = pg_sales_watch_norm($counts, $cut);
	pg_assert_true(is_array($norm));
	pg_assert_same(null, pg_sales_watch_judge_orders($norm, array(), $end, 0, 0.002));
}

// A silent channel beside a busy one: only the silent one is told, and
// all channels silent is one store alert and no channel alert.
function test_sales_watch_silent_channel_and_store()
{
	$cut = 500000;
	$end = $cut * 3600;

	$site = pg_sales_watch_norm(test_sales_watch_counts($cut, 6), $cut);
	$market = pg_sales_watch_norm(test_sales_watch_counts($cut, 3), $cut);

	$judged = array(
		'site'  => array('label' => 'Site', 'result' => pg_sales_watch_judge_orders($site, test_sales_watch_times($end - 43200, $end, 72), $end, 0, 0.002)),
		'mp_4'  => array('label' => 'Trendyol', 'result' => pg_sales_watch_judge_orders($market, array(), $end, 300, 0.002)),
	);

	pg_assert_same(null, $judged['site']['result']);
	pg_assert_true(is_array($judged['mp_4']['result']));

	$alerts = pg_sales_watch_flow_alerts($judged);
	pg_assert_same(array('mp_4'), array_keys($alerts));
	pg_assert_contains('Trendyol: 0 orders in', $alerts['mp_4']);

	$judged['site']['result'] = pg_sales_watch_judge_orders($site, array(), $end, 0, 0.002);
	$alerts = pg_sales_watch_flow_alerts($judged);
	pg_assert_same(array('store'), array_keys($alerts));
	pg_assert_contains('Trendyol', $alerts['store']);
	pg_assert_contains('Site', $alerts['store']);

	// One channel alone is named by its own name, not "the store".
	$alerts = pg_sales_watch_flow_alerts(array('site' => $judged['site']));
	pg_assert_same(array('site'), array_keys($alerts));
}

// A marketplace channel between syncs: judged up to its last sync, the
// minutes since then do not read as silence.
function test_sales_watch_marketplace_between_syncs()
{
	$cut = 500000;
	$now = $cut * 3600;
	$synced = $now - 3000;

	$norm = pg_sales_watch_norm(test_sales_watch_counts($cut, 20), $cut);
	$times = test_sales_watch_times($synced - 43200, $synced, 240);

	pg_assert_true(is_array(pg_sales_watch_judge_orders($norm, $times, $now, 300, 0.002)), 'judged at now, the gap looks silent');
	pg_assert_same(null, pg_sales_watch_judge_orders($norm, $times, $synced, 300, 0.002));
}

// A broken checkout: carts reach payment and none completes.
function test_sales_watch_broken_checkout()
{
	$end = 1800000000;

	// Half the carts that reach payment complete, as a rule.
	pg_assert_same(null, pg_sales_watch_judge_payment(10, 10, array(), test_sales_watch_times($end - 7200, $end, 12), $end, 0.002), 'no norm under 20 orders');

	$found = pg_sales_watch_judge_payment(1000, 1000, array(), test_sales_watch_times($end - 7200, $end, 14), $end, 0.002);
	pg_assert_true(is_array($found));
	pg_assert_same(0, $found['completed']);
	pg_assert_true($found['carts'] >= 5);

	// The usual share, and too few carts to say.
	pg_assert_same(null, pg_sales_watch_judge_payment(1000, 1000, test_sales_watch_times($end - 7200, $end, 7), test_sales_watch_times($end - 7200, $end, 7), $end, 0.002));
	pg_assert_same(null, pg_sales_watch_judge_payment(1000, 1000, array(), test_sales_watch_times($end - 7200, $end, 4), $end, 0.002));

	pg_assert_same('Credit/Debit Card (3), not chosen (1)', pg_sales_watch_payment_methods(array('Credit/Debit Card', '', 'Credit/Debit Card', 'Credit/Debit Card')));
}

// The sensitivity is split over the five windows.
function test_sales_watch_alpha()
{
	pg_assert_true(abs(pg_sales_watch_alpha(0.01) - 0.002) < 0.0000001);
	pg_assert_true(abs(pg_sales_watch_alpha(0.0) - 0.002) < 0.0000001);
}

// The once-a-day key is the code, or the code and its subject; the state
// file keeps both shapes and drops anything else.
function test_sales_watch_ops_alert_subject_key()
{
	pg_assert_same('orders_stopped', pg_ops_alert_key('orders_stopped', ''));
	pg_assert_same('orders_stopped:mp_4', pg_ops_alert_key('orders_stopped', 'mp_4'));
	pg_assert_same('orders_stopped:store', pg_ops_alert_key('orders_stopped', 'Store!'));

	$state = pg_ops_alert_state_normalize(array('sent' => array(
		'orders_stopped:mp_4' => '1800000000',
		'disk_low'            => 5,
		'bad key'             => 7,
		'a:b:c'               => 9,
	)));

	pg_assert_same(array('orders_stopped:mp_4' => 1800000000, 'disk_low' => 5), $state['sent']);

	$codes = pg_ops_alert_codes();
	pg_assert_same('sales', $codes['orders_stopped']['group']);
	pg_assert_same('sales', $codes['payment_broken']['group']);
}
