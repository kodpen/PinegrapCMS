<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the carrier scorecard and the two suggestions
 * (includes/fn/delivery_scorecard.php): the month keys, the fold of parcel
 * rows into a month and into twelve months, the 20-parcel minimum, the
 * arrival check against a method's transit days and the carrier order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A unix time at a local date and time in Istanbul (UTC+3, no daylight saving).
function _dsc_test_at($local)
{
	$date = new DateTime($local, new DateTimeZone('Europe/Istanbul'));
	return $date->getTimestamp();
}

// $count parcels of one carrier and province shipped on $day at 10:00.
function _dsc_test_rows($carrier, $state, $count, $day, $extra = array())
{
	$rows = array();
	$shipped = _dsc_test_at($day . ' 10:00');
	for ($i = 0; $i < $count; $i++) {
		$rows[] = array_merge(array(
			'carrier' => $carrier,
			'state' => $state,
			'city' => '',
			'shipped_at' => $shipped,
			'delivered_at' => $shipped + 2 * 86400,
			'delivery_date' => '0000-00-00',
			'status' => 'delivered',
			'has_problem' => 0,
		), $extra);
	}
	return $rows;
}

function test_delivery_scorecard_month_keys()
{
	$now = _dsc_test_at('2026-11-20 12:00');
	pg_assert_same('2026-10', pg_delivery_scorecard_month('2026-10', $now), 'a past month is kept');
	pg_assert_same('2026-11', pg_delivery_scorecard_month('2027-01', $now), 'a month to come is the current one');
	pg_assert_same('2026-11', pg_delivery_scorecard_month('2026-13', $now), 'a broken key is the current month');
	pg_assert_same('2026-11', pg_delivery_scorecard_month(array('2026-10'), $now), 'a key sent as a list (?month[]=x) is the current month');
	pg_assert_same('2026-01', pg_delivery_scorecard_month_step('2025-12', 1), 'a step crosses the year');
	pg_assert_same('2025-12', pg_delivery_scorecard_month_step('2026-01', -1), 'and back');
	$months = pg_delivery_scorecard_months('2026-11');
	pg_assert_same(12, count($months), 'twelve months');
	pg_assert_same('2025-12', $months[0], 'oldest first');
	pg_assert_same('2026-11', $months[11], 'ending with the month');
}

function test_delivery_scorecard_month_view()
{
	$state = array();
	$rows = array_merge(
		// 20 in İstanbul: 10 judged, 8 on time; 2 returned, 3 with a problem.
		_dsc_test_rows('yurtici', 'İstanbul', 8, '2026-11-02', array('delivery_date' => '2026-11-10')),
		_dsc_test_rows('yurtici', 'istanbul', 2, '2026-11-02', array('delivery_date' => '2026-11-03')),
		_dsc_test_rows('yurtici', 'İstanbul', 2, '2026-11-02', array('status' => 'returned', 'delivered_at' => 0)),
		_dsc_test_rows('yurtici', 'İstanbul', 3, '2026-11-02', array('has_problem' => 1)),
		_dsc_test_rows('yurtici', 'İstanbul', 5, '2026-11-02'),
		// 5 in Ankara: too few for the province's own figures.
		_dsc_test_rows('yurtici', 'Ankara', 5, '2026-11-03'),
		// Another carrier with 4: too few for the carrier.
		_dsc_test_rows('aras', 'Ankara', 4, '2026-11-03'),
		// A parcel never shipped is not counted; one of another month goes to it.
		_dsc_test_rows('aras', 'Ankara', 1, '2026-11-03', array('shipped_at' => 0)),
		_dsc_test_rows('aras', 'Ankara', 1, '2026-10-31')
	);
	pg_delivery_scorecard_add($state, $rows, true, 'Europe/Istanbul');
	$view = pg_delivery_scorecard_month_view($state, '2026-11', 20);

	pg_assert_same(29, $view['total']['parcels'], 'the period counts every shipped parcel of the month');
	pg_assert_same(2, count($view['carriers']), 'two carriers');
	$yurtici = $view['carriers'][0];
	pg_assert_same('yurtici', $yurtici['carrier'], 'most parcels first');
	pg_assert_same(25, $yurtici['figures']['parcels'], 'the carrier counts all its provinces');
	pg_assert_true($yurtici['figures']['enough'], '25 parcels are enough');
	pg_assert_same(80.0, $yurtici['figures']['on_time'], '8 of the 10 judged arrived on time');
	pg_assert_same(2.0, $yurtici['figures']['median'], 'median of the arrived parcels');
	pg_assert_same(8.0, $yurtici['figures']['returned'], '2 of 25 returned');
	pg_assert_same(12.0, $yurtici['figures']['problem'], '3 of 25 had a problem');

	pg_assert_same('istanbul', $yurtici['provinces'][0]['key'], 'İstanbul and istanbul are one province');
	pg_assert_same('İstanbul', $yurtici['provinces'][0]['name'], 'shown as first written');
	pg_assert_true($yurtici['provinces'][0]['figures']['enough'], '20 parcels are enough');
	pg_assert_false($yurtici['provinces'][1]['figures']['enough'], '5 in Ankara are too few');
	pg_assert_false($view['carriers'][1]['figures']['enough'], 'a carrier under 20 in all has no figures');
	pg_assert_same(null, pg_delivery_scorecard_month_view($state, '2026-09', 20)['total']['on_time'], 'an empty month judges nothing');
}

function test_delivery_scorecard_year_view()
{
	$state = array();
	pg_delivery_scorecard_add($state, array_merge(
		_dsc_test_rows('yurtici', 'Ankara', 20, '2026-10-05'),
		_dsc_test_rows('yurtici', 'Ankara', 3, '2026-11-05'),
		_dsc_test_rows('aras', 'Ankara', 30, '2026-11-05', array('status' => 'returned'))
	), false, 'Europe/Istanbul');
	pg_assert_same(array(), $state['2026-10']['yurtici']['provinces'], 'provinces are not kept for twelve months');
	$view = pg_delivery_scorecard_year_view($state, pg_delivery_scorecard_months('2026-11'), 20);
	pg_assert_same('aras', $view['carriers'][0]['carrier'], 'most parcels over the months first');
	pg_assert_same(100.0, $view['carriers'][0]['months']['2026-11']['returned'], 'a month\'s figure');
	$yurtici = $view['carriers'][1];
	pg_assert_true($yurtici['months']['2026-10']['enough'], '20 in October are enough');
	pg_assert_false($yurtici['months']['2026-11']['enough'], '3 in November are too few');
	pg_assert_same(0, $yurtici['months']['2026-01']['parcels'], 'a month without parcels is there, empty');
	pg_assert_same(53, $view['total']['parcels'], 'the period across carriers and months');
}

function test_delivery_tower_arrival_check()
{
	$figures = array('yurtici' => array('country' => array('count' => 40, 'median' => 3.5, 'p90' => 5, 'median_working' => 2.6, 'p90_working' => 4), 'provinces' => array()));
	$method = array('carrier' => 'yurtici', 'base_transit_days' => 1, 'transit_on_saturday' => 0, 'transit_on_sunday' => 0);
	pg_assert_same(array('measured' => 2.6, 'set' => 1), pg_delivery_tower_arrival_check($method, $figures), 'working days without weekend transit');
	$method['transit_on_saturday'] = 1;
	pg_assert_same(array('measured' => 3.5, 'set' => 1), pg_delivery_tower_arrival_check($method, $figures), 'calendar days with weekend transit');
	$method['base_transit_days'] = 3;
	pg_assert_same(null, pg_delivery_tower_arrival_check($method, $figures), 'under a day apart is not shown');
	$method['base_transit_days'] = 5;
	pg_assert_same(array('measured' => 3.5, 'set' => 5), pg_delivery_tower_arrival_check($method, $figures), 'set too long is shown too');
	pg_assert_same(null, pg_delivery_tower_arrival_check(array('carrier' => 'aras', 'base_transit_days' => 1), $figures), 'an unmeasured carrier has no check');
	pg_assert_same(null, pg_delivery_tower_arrival_check(array('carrier' => '', 'base_transit_days' => 1), $figures), 'a method with no carrier has none');
	pg_assert_same(null, pg_delivery_tower_arrival_check($method, array('yurtici' => array('country' => null, 'provinces' => array()))), 'a carrier under 20 parcels has none');
}

function test_delivery_tower_carrier_order()
{
	$on_time = array(
		'yurtici' => array('country' => array('judged' => 100, 'on_time' => 80), 'provinces' => array('ankara' => array('judged' => 40, 'on_time' => 20))),
		'aras' => array('country' => array('judged' => 100, 'on_time' => 70), 'provinces' => array()),
		'mng' => array('country' => array('judged' => 100, 'on_time' => 70), 'provinces' => array()),
	);
	$options = array(
		array('key' => 'a', 'carrier' => 'yurtici', 'price' => 5000),
		array('key' => 'b', 'carrier' => 'surat', 'price' => 1000),
		array('key' => 'c', 'carrier' => 'mng', 'price' => null),
		array('key' => 'd', 'carrier' => 'aras', 'price' => 6000),
	);
	$keys = function ($list) {
		return implode(',', array_column($list, 'key'));
	};
	pg_assert_same('a,d,c,b', $keys(pg_delivery_tower_carrier_order($options, $on_time, 'izmir')), 'country shares; a price before none; unmeasured last');
	$ankara = pg_delivery_tower_carrier_order($options, $on_time, 'ankara');
	pg_assert_same('d,c,a,b', $keys($ankara), 'the province\'s own share counts where it was measured');
	pg_assert_same(50.0, $ankara[2]['share'], 'the share goes with the option');
	pg_assert_same(null, $ankara[3]['share'], 'unmeasured: no share');
	pg_assert_same('b,a', $keys(pg_delivery_tower_carrier_order(array($options[1], $options[0]), array(), '')), 'nothing measured: the lower price first');
}
