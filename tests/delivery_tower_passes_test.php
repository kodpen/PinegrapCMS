<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the delivery tower's passes (includes/fn/delivery_tower_passes.php):
 * when the nightly summary and the hourly problem pass are due, the summary
 * built from delivered parcels across ticks, the parcel read, and the problem
 * rows a pass opens and closes.
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
function _dt_passes_test_at($local)
{
	$date = new DateTime($local, new DateTimeZone('Europe/Istanbul'));
	return $date->getTimestamp();
}

// $count delivered parcel rows as the summary query reads them.
function _dt_passes_test_delivered($carrier, $state, $count, $days, $estimate = '0000-00-00')
{
	$rows = array();
	$shipped = _dt_passes_test_at('2026-11-02 10:00');
	for ($i = 0; $i < $count; $i++) {
		$rows[] = array(
			'id' => $i + 1,
			'carrier' => $carrier,
			'state' => $state,
			'city' => '',
			'shipped_at' => $shipped,
			'delivered_at' => $shipped + $days * 86400,
			'delivery_date' => $estimate,
		);
	}
	return $rows;
}

function test_delivery_tower_kind_codes()
{
	$codes = pg_delivery_tower_kind_codes();
	pg_assert_same(array('stuck' => 1, 'not_handed_over' => 2, 'problem' => 3, 'returned' => 4), $codes, 'the kinds keep the numbers of the schema decision');
	pg_assert_same(array_keys(pg_delivery_tower_kinds()), array_keys($codes), 'every rule kind has a number');
	$reasons = pg_delivery_tower_close_reasons();
	pg_assert_same(0, $reasons['open'], 'open is 0');
	pg_assert_same(1, $reasons['status'], 'a later status is 1');
	pg_assert_same(5, $reasons['other'], 'other is 5');
}

function test_delivery_tower_summary_due_nightly()
{
	$tz = 'Europe/Istanbul';
	pg_assert_true(pg_delivery_tower_summary_due(0, _dt_passes_test_at('2026-11-20 15:00'), $tz), 'no summary yet: due at once');
	pg_assert_true(!pg_delivery_tower_summary_due(_dt_passes_test_at('2026-11-20 02:10'), _dt_passes_test_at('2026-11-20 23:00'), $tz), 'built this night: not again today');
	pg_assert_true(!pg_delivery_tower_summary_due(_dt_passes_test_at('2026-11-19 02:10'), _dt_passes_test_at('2026-11-20 01:30'), $tz), 'before the night hour: yesterday\'s stays');
	pg_assert_true(pg_delivery_tower_summary_due(_dt_passes_test_at('2026-11-19 02:10'), _dt_passes_test_at('2026-11-20 02:00'), $tz), 'the night hour came: due');
	pg_assert_true(pg_delivery_tower_summary_due(_dt_passes_test_at('2026-11-19 15:00'), _dt_passes_test_at('2026-11-20 03:00'), $tz), 'a daytime build is redone the next night');
}

function test_delivery_tower_pass_due_hourly()
{
	$now = _dt_passes_test_at('2026-11-20 15:00');
	pg_assert_true(pg_delivery_tower_pass_due(array('after' => 0, 'at' => 0), $now), 'never run: due');
	pg_assert_true(!pg_delivery_tower_pass_due(array('after' => 0, 'at' => $now - 1800), $now), 'half an hour ago: not due');
	pg_assert_true(pg_delivery_tower_pass_due(array('after' => 0, 'at' => $now - 3600), $now), 'an hour ago: due');
	pg_assert_true(pg_delivery_tower_pass_due(array('after' => 120, 'at' => $now - 60), $now), 'a walk under way goes on next tick');
}

function test_delivery_tower_summary_across_ticks()
{
	$tz = 'Europe/Istanbul';
	$state = pg_delivery_tower_summary_state_new(_dt_passes_test_at('2026-11-20 02:00'));
	$rows = array_merge(
		_dt_passes_test_delivered('yurtici', 'İstanbul', 15, 2, '2026-11-03'),
		_dt_passes_test_delivered('yurtici', 'ISTANBUL', 10, 3, '2026-11-05'),
		_dt_passes_test_delivered('aras', 'Ankara', 5, 1)
	);
	// Ids as one id-ordered walk read them.
	foreach ($rows as $i => $row) {
		$rows[$i]['id'] = $i + 1;
	}
	pg_delivery_tower_summary_add($state, array_slice($rows, 0, 12), $tz);
	pg_assert_same(12, $state['after'], 'the place is the last id read');
	// Kept between ticks as text, then read back.
	$state = pg_delivery_tower_summary_state_decode(pg_delivery_tower_summary_state_encode($state), $state['started']);
	pg_assert_true(is_array($state), 'the kept state reads back');
	pg_assert_same(null, pg_delivery_tower_summary_state_decode(pg_delivery_tower_summary_state_encode($state), $state['started'] + 1), 'another walk\'s state is not taken');
	pg_delivery_tower_summary_add($state, array_slice($rows, 12), $tz);

	$summary = pg_delivery_tower_summary_finish($state, PG_DELIVERY_TOWER_MIN_PARCELS, $tz);
	pg_assert_same(30, $summary['parcels'], 'every delivered parcel counted');
	$figure = $summary['figures']['yurtici']['provinces']['istanbul'];
	pg_assert_same(25, $figure['count'], 'one province whatever the spelling');
	pg_assert_same(2.0, (float) $figure['median'], 'the province median');
	pg_assert_same(null, $summary['figures']['aras']['country'], 'five parcels: no figure');
	$on_time = $summary['on_time']['yurtici']['provinces']['istanbul'];
	pg_assert_same(array('judged' => 25, 'on_time' => 15), $on_time, 'on time against checkout\'s estimate');
	pg_assert_true(!isset($summary['on_time']['aras']), 'no estimate: measured, not judged');
	pg_assert_same($figure, pg_delivery_tower_figure_for($summary['figures'], 'yurtici', 'istanbul'), 'the pass reads the summary\'s figures as the rules give them');
}

function test_delivery_tower_summary_fits_its_text()
{
	$summary = array('at' => 1, 'parcels' => 0, 'figures' => array('ptt' => array('country' => null, 'provinces' => array())), 'on_time' => array());
	$figure = array('count' => 20, 'median' => 2.5, 'p90' => 4.5, 'median_working' => 2, 'p90_working' => 3.5);
	for ($i = 0; $i < 2000; $i++) {
		$figure['count'] = 20 + $i;
		$summary['figures']['ptt']['provinces']['city number ' . $i] = $figure;
	}
	$text = pg_delivery_tower_summary_encode($summary);
	pg_assert_true(strlen($text) <= 65535, 'the summary fits config_text');
	$back = json_decode($text, true);
	pg_assert_true(isset($back['figures']['ptt']['provinces']['city number 1999']), 'the busiest provinces stay');
	pg_assert_true(!isset($back['figures']['ptt']['provinces']['city number 0']), 'the quietest go first');
}

function test_delivery_tower_parcel_query()
{
	list($sql, $params) = pg_delivery_tower_parcel_query(500, 1700000000, 200);
	pg_assert_contains('shipping_tracking_numbers.id > ?', $sql, 'the walk goes on after its place');
	pg_assert_contains('ORDER BY shipping_tracking_numbers.id', $sql, 'in id order');
	pg_assert_contains('LIMIT 200', $sql, 'one page');
	pg_assert_contains('shipment_problems', $sql, 'a parcel with an open problem is read whatever its age');
	pg_assert_contains("NOT IN ('incomplete', 'cancelled')", $sql, 'a cancelled order is left out');
	pg_assert_same(array(500, 1700000000, 1700000000, 1700000000), $params, 'the place and the window');
}

function test_delivery_tower_parcel_changes()
{
	$tz = 'Europe/Istanbul';
	$settings = pg_delivery_tower_settings_clean(array());
	$figures = pg_delivery_tower_figures(_dt_passes_test_delivered('yurtici', 'İzmir', 20, 2), PG_DELIVERY_TOWER_MIN_PARCELS, $tz);
	$now = _dt_passes_test_at('2026-11-20 15:00');
	$parcel = array('order_id' => 7, 'ship_to_id' => 8, 'number' => 'N', 'carrier' => 'yurtici', 'state' => 'İzmir', 'city' => '', 'source' => 'panel', 'shipped_at' => 0, 'delivered_at' => 0);

	$parcels = array(
		// In transit for ten days against a 90th percentile of two: stuck.
		10 => array_merge($parcel, array('id' => 10, 'status' => 'in_transit', 'shipped_at' => _dt_passes_test_at('2026-11-10 10:00'))),
		// A label of a tracking driver three working days ago, still created.
		11 => array_merge($parcel, array('id' => 11, 'status' => 'created', 'number' => 'L')),
		// Exception, with an exception row already open: nothing new.
		12 => array_merge($parcel, array('id' => 12, 'status' => 'exception')),
		// Delivered: its open stuck row closes.
		13 => array_merge($parcel, array('id' => 13, 'status' => 'delivered', 'delivered_at' => $now - 60)),
		// Scanned after a not-handed-over row: that row closes.
		14 => array_merge($parcel, array('id' => 14, 'status' => 'in_transit', 'shipped_at' => $now - 3600)),
	);
	$labels = array('8|L' => array('driver' => 'yurtici', 'created_at' => _dt_passes_test_at('2026-11-17 10:00')));
	$open = array(12 => array('problem'), 13 => array('stuck'), 14 => array('not_handed_over'));

	$changes = pg_delivery_tower_parcel_changes($parcels, $labels, $open, $figures, $now, $settings, array('yurtici'), $tz);
	pg_assert_same(array(
		array('shipment_id' => 10, 'order_id' => 7, 'ship_to_id' => 8, 'kind' => 1),
		array('shipment_id' => 11, 'order_id' => 7, 'ship_to_id' => 8, 'kind' => 2),
	), $changes['open'], 'stuck and not handed over are opened, the open exception is not repeated');
	pg_assert_same(array(
		array('shipment_id' => 13, 'kind' => 1),
		array('shipment_id' => 14, 'kind' => 2),
	), $changes['close'], 'a delivery and a scan close their rows');

	// The label's driver does not track and the row was typed in the panel:
	// nobody reads its status, so "still created" means nothing.
	$changes = pg_delivery_tower_parcel_changes(array(11 => $parcels[11]), $labels, array(), $figures, $now, $settings, array(), $tz);
	pg_assert_same(array(), $changes['open'], 'a status nobody reads is not judged');

	// A kind switched off in the settings opens nothing.
	$off = pg_delivery_tower_settings_clean(array('stuck' => 0));
	$changes = pg_delivery_tower_parcel_changes(array(10 => $parcels[10]), array(), array(), $figures, $now, $off, array(), $tz);
	pg_assert_same(array(), $changes['open'], 'stuck switched off');
}
