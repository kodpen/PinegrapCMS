<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the delivery tower's pure rules (includes/fn/delivery_tower.php):
 * the province key, the median and 90th percentile, working days, on time,
 * the measured figures with their minimum, the four problem kinds and how a
 * later status closes an open problem.
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
function _delivery_tower_test_at($local)
{
	$date = new DateTime($local, new DateTimeZone('Europe/Istanbul'));
	return $date->getTimestamp();
}

// $count parcels of one carrier and province, each delivered $days calendar days after shipping.
function _delivery_tower_test_rows($carrier, $state, $city, $count, $days, $source = 'carrier')
{
	$rows = array();
	$shipped = _delivery_tower_test_at('2026-11-02 10:00');
	for ($i = 0; $i < $count; $i++) {
		$rows[] = array(
			'carrier' => $carrier,
			'state' => $state,
			'city' => $city,
			'shipped_at' => $shipped,
			'delivered_at' => $shipped + $days * 86400,
			'source' => $source,
		);
	}
	return $rows;
}

function test_delivery_tower_province_key()
{
	pg_assert_same('istanbul', pg_delivery_tower_province_key('İstanbul', 'Kadıköy'), 'the state comes first');
	pg_assert_same('istanbul', pg_delivery_tower_province_key('  ISTANBUL ', ''), 'case and spaces do not split a province');
	pg_assert_same('izmir', pg_delivery_tower_province_key('', 'İzmir'), 'no state: the city');
	pg_assert_same('', pg_delivery_tower_province_key(' ', ''), 'neither: no province');
}

function test_delivery_tower_percentile_and_median()
{
	pg_assert_same(null, pg_delivery_tower_percentile(array(), 50), 'no values, no figure');
	pg_assert_same(3.0, pg_delivery_tower_percentile(array(3), 90), 'one value');
	pg_assert_same(2.5, pg_delivery_tower_median(array(4, 1, 2, 3)), 'even count: the middle two averaged');
	pg_assert_same(3.0, pg_delivery_tower_median(array(5, 1, 3)), 'odd count, unsorted');
	pg_assert_same(9.1, pg_delivery_tower_percentile(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), 90), 'interpolated between ranks');
}

function test_delivery_tower_working_days()
{
	$tz = 'Europe/Istanbul';
	$friday = _delivery_tower_test_at('2026-11-06 16:00');
	$monday = _delivery_tower_test_at('2026-11-09 09:00');
	$tuesday = _delivery_tower_test_at('2026-11-10 09:00');
	pg_assert_same(3, pg_delivery_tower_calendar_days($friday, $monday, $tz), 'calendar days count dates, not hours');
	pg_assert_same(1, pg_delivery_tower_working_days($friday, $monday, $tz), 'Saturday and Sunday are not working days');
	pg_assert_same(2, pg_delivery_tower_working_days($friday, $tuesday, $tz), 'Monday and Tuesday');
	pg_assert_same(0, pg_delivery_tower_working_days($monday, $monday + 3600, $tz), 'the same day');
	pg_assert_same(0, pg_delivery_tower_working_days($monday, $friday, $tz), 'backwards is no time');
}

function test_delivery_tower_on_time()
{
	$tz = 'Europe/Istanbul';
	$delivered = _delivery_tower_test_at('2026-11-12 18:30');
	pg_assert_same(true, pg_delivery_tower_on_time($delivered, '2026-11-12', $tz), 'on the estimated day is on time');
	pg_assert_same(false, pg_delivery_tower_on_time($delivered, '2026-11-11', $tz), 'a day late');
	pg_assert_same(null, pg_delivery_tower_on_time($delivered, '0000-00-00', $tz), 'no estimate: measured, not judged');
	pg_assert_same(null, pg_delivery_tower_on_time($delivered, '', $tz), 'empty estimate: not judged');
	pg_assert_same(null, pg_delivery_tower_on_time(0, '2026-11-12', $tz), 'not delivered: not judged');
}

function test_delivery_tower_figures_too_few_parcels_fall_back_to_the_country()
{
	$tz = 'Europe/Istanbul';
	$rows = array_merge(
		_delivery_tower_test_rows('yurtici', 'Ankara', '', 20, 2),
		_delivery_tower_test_rows('yurtici', 'Van', '', 5, 6)
	);
	$figures = pg_delivery_tower_figures($rows, 20, $tz);

	pg_assert_same(20, $figures['yurtici']['provinces']['ankara']['count'], 'twenty parcels make a province figure');
	pg_assert_same(2.0, $figures['yurtici']['provinces']['ankara']['median'], 'the province median');
	pg_assert_false(isset($figures['yurtici']['provinces']['van']), 'five parcels make no province figure');
	pg_assert_same(25, $figures['yurtici']['country']['count'], 'every parcel counts for the country');

	$van = pg_delivery_tower_figure_for($figures, 'yurtici', 'van');
	pg_assert_same(25, $van['count'], 'a thin province borrows the carrier\'s country figure');
	$ankara = pg_delivery_tower_figure_for($figures, 'yurtici', 'ankara');
	pg_assert_same(20, $ankara['count'], 'a full province keeps its own');

	$thin = pg_delivery_tower_figures(_delivery_tower_test_rows('aras', 'Ankara', '', 19, 2), 20, $tz);
	pg_assert_same(null, pg_delivery_tower_figure_for($thin, 'aras', 'ankara'), 'under twenty in all: no figure');
	pg_assert_same(null, pg_delivery_tower_figure_for($figures, 'mng', 'ankara'), 'an unknown carrier: no figure');
}

function test_delivery_tower_figures_skip_rows_that_cannot_be_measured()
{
	$rows = _delivery_tower_test_rows('ptt', 'Bursa', '', 20, 3);
	$rows[] = array('carrier' => 'ptt', 'state' => 'Bursa', 'city' => '', 'shipped_at' => 0, 'delivered_at' => 1700000000, 'source' => 'panel');
	$rows[] = array('carrier' => 'ptt', 'state' => 'Bursa', 'city' => '', 'shipped_at' => 1700000000, 'delivered_at' => 1600000000, 'source' => 'panel');
	$rows[] = array('carrier' => '', 'state' => 'Bursa', 'city' => '', 'shipped_at' => 1700000000, 'delivered_at' => 1700086400, 'source' => 'panel');
	$figures = pg_delivery_tower_figures($rows, 20, 'Europe/Istanbul');
	pg_assert_same(20, $figures['ptt']['country']['count'], 'no ship date, a delivery before shipping and no carrier are left out');
	pg_assert_same(array('ptt'), array_keys($figures), 'a parcel without a carrier has no carrier figure');
}

function test_delivery_tower_figures_count_marketplace_parcels()
{
	$rows = array_merge(
		_delivery_tower_test_rows('hepsijet', 'Izmir', '', 10, 2, 'marketplace'),
		_delivery_tower_test_rows('hepsijet', 'Izmir', '', 10, 2, 'carrier')
	);
	$figures = pg_delivery_tower_figures($rows, 20, 'Europe/Istanbul');
	pg_assert_same(20, $figures['hepsijet']['provinces']['izmir']['count'], 'a marketplace parcel is measured like any other');
}

function test_delivery_tower_stuck_against_its_own_carrier()
{
	$tz = 'Europe/Istanbul';
	$figures = pg_delivery_tower_figures(array_merge(
		_delivery_tower_test_rows('yurtici', 'Ankara', '', 20, 2),
		_delivery_tower_test_rows('aras', 'Ankara', '', 20, 6)
	), 20, $tz);
	$settings = pg_delivery_tower_settings_clean(array());
	$now = _delivery_tower_test_at('2026-11-20 12:00');
	$shipped = $now - 5 * 86400;

	$fast = array('carrier' => 'yurtici', 'state' => 'Ankara', 'city' => '', 'status' => 'in_transit', 'shipped_at' => $shipped, 'label_at' => $shipped, 'status_read' => true);
	$slow = array('carrier' => 'aras') + $fast;

	pg_assert_same(array('stuck'), pg_delivery_tower_problems_found($fast, $figures, $now, $settings, $tz), 'five days against a 90th percentile of two plus one');
	pg_assert_same(array(), pg_delivery_tower_problems_found($slow, $figures, $now, $settings, $tz), 'five days is usual for a carrier whose 90th percentile is six');

	$edge = array('shipped_at' => $now - 3 * 86400) + $fast;
	pg_assert_same(array(), pg_delivery_tower_problems_found($edge, $figures, $now, $settings, $tz), 'exactly the percentile plus one day is not yet stuck');

	$unknown = array('carrier' => 'mng') + $fast;
	pg_assert_same(array(), pg_delivery_tower_problems_found($unknown, $figures, $now, $settings, $tz), 'no figure: never stuck');

	$off = pg_delivery_tower_settings_clean(array('stuck' => 0));
	pg_assert_same(array(), pg_delivery_tower_problems_found($fast, $figures, $now, $off, $tz), 'the kind turned off');
}

function test_delivery_tower_label_never_scanned_over_a_weekend()
{
	$tz = 'Europe/Istanbul';
	$settings = pg_delivery_tower_settings_clean(array());
	$label = _delivery_tower_test_at('2026-11-06 16:00'); // Friday
	$parcel = array('carrier' => 'yurtici', 'state' => 'Ankara', 'city' => '', 'status' => 'created', 'shipped_at' => 0, 'label_at' => $label, 'status_read' => true);

	pg_assert_same(array(), pg_delivery_tower_problems_found($parcel, array(), _delivery_tower_test_at('2026-11-08 20:00'), $settings, $tz), 'Sunday: no working day has passed');
	pg_assert_same(array(), pg_delivery_tower_problems_found($parcel, array(), _delivery_tower_test_at('2026-11-09 17:00'), $settings, $tz), 'Monday: the working day is not over');
	pg_assert_same(array('not_handed_over'), pg_delivery_tower_problems_found($parcel, array(), _delivery_tower_test_at('2026-11-10 08:00'), $settings, $tz), 'Tuesday: Monday passed without a scan');

	$unread = array('status_read' => false) + $parcel;
	pg_assert_same(array(), pg_delivery_tower_problems_found($unread, array(), _delivery_tower_test_at('2026-11-12 08:00'), $settings, $tz), 'a status nobody reads is never judged');

	$two = pg_delivery_tower_settings_clean(array('not_handed_over_days' => 2));
	pg_assert_same(array(), pg_delivery_tower_problems_found($parcel, array(), _delivery_tower_test_at('2026-11-10 08:00'), $two, $tz), 'the day setting');
}

function test_delivery_tower_status_is_read()
{
	pg_assert_true(pg_delivery_tower_status_is_read('carrier', false), 'a carrier wrote it');
	pg_assert_true(pg_delivery_tower_status_is_read('marketplace', false), 'a marketplace reports it');
	pg_assert_true(pg_delivery_tower_status_is_read('panel', true), 'typed in the panel, but a tracking driver reads it');
	pg_assert_false(pg_delivery_tower_status_is_read('csv', false), 'imported and never read');
}

function test_delivery_tower_problem_and_returned_kinds()
{
	$tz = 'Europe/Istanbul';
	$settings = pg_delivery_tower_settings_clean(array());
	$now = _delivery_tower_test_at('2026-11-20 12:00');
	$parcel = array('carrier' => 'ptt', 'state' => '', 'city' => '', 'status' => 'exception', 'shipped_at' => $now - 86400, 'label_at' => $now - 86400, 'status_read' => true);
	pg_assert_same(array('problem'), pg_delivery_tower_problems_found($parcel, array(), $now, $settings, $tz), 'exception');
	$parcel['status'] = 'returned';
	pg_assert_same(array('returned'), pg_delivery_tower_problems_found($parcel, array(), $now, $settings, $tz), 'returned');
	$parcel['status'] = 'delivered';
	pg_assert_same(array(), pg_delivery_tower_problems_found($parcel, array(), $now, $settings, $tz), 'delivered: nothing');
}

function test_delivery_tower_marketplace_parcel_is_listed()
{
	$tz = 'Europe/Istanbul';
	$settings = pg_delivery_tower_settings_clean(array());
	$now = _delivery_tower_test_at('2026-11-20 12:00');
	$parcel = array('carrier' => 'trendyol', 'state' => 'Ankara', 'city' => '', 'status' => 'exception', 'shipped_at' => $now - 86400, 'label_at' => 0, 'status_read' => pg_delivery_tower_status_is_read('marketplace', false), 'order_type' => 'marketplace');
	pg_assert_same(array('problem'), pg_delivery_tower_problems_found($parcel, array(), $now, $settings, $tz), 'a marketplace parcel\'s problem is found and listed');
}

function test_delivery_tower_pass_writes_no_second_row_of_a_kind()
{
	$pass = pg_delivery_tower_pass('in_transit', array('stuck'), array('stuck'));
	pg_assert_same(array(), $pass['open'], 'already open: nothing new');
	pg_assert_same(array(), $pass['close'], 'still stuck: stays open');

	$pass = pg_delivery_tower_pass('exception', array(), array('problem'));
	pg_assert_same(array('problem'), $pass['open'], 'a new kind is opened');
}

function test_delivery_tower_problem_closed_by_a_delivery()
{
	$pass = pg_delivery_tower_pass('delivered', array('stuck', 'problem', 'not_handed_over'), array());
	pg_assert_same(array(), $pass['open'], 'a delivered parcel opens nothing');
	pg_assert_same(array('stuck' => 'delivered', 'problem' => 'delivered', 'not_handed_over' => 'delivered'), $pass['close'], 'a delivery ends every open problem');

	$pass = pg_delivery_tower_pass('in_transit', array('not_handed_over'), array());
	pg_assert_same(array('not_handed_over' => 'scanned'), $pass['close'], 'a scan closes "not handed over"');

	$pass = pg_delivery_tower_pass('in_transit', array('problem'), array());
	pg_assert_same(array('problem' => 'status_changed'), $pass['close'], 'moving again closes the delivery problem');

	$pass = pg_delivery_tower_pass('in_transit', array('stuck'), array());
	pg_assert_same(array(), $pass['close'], 'still in transit: a stuck row is not closed by a changed figure');

	$pass = pg_delivery_tower_pass('returned', array('stuck'), array('returned'));
	pg_assert_same(array('stuck' => 'status_changed'), $pass['close'], 'stuck ends when the parcel turns back');
	pg_assert_same(array('returned'), $pass['open'], 'and the return is opened');
}

function test_delivery_tower_pass_keeps_a_kind_closed_by_hand()
{
	$pass = pg_delivery_tower_pass('in_transit', array(), array('stuck'), array('stuck'));
	pg_assert_same(array(), $pass['open'], 'found and held: nothing to open');

	$pass = pg_delivery_tower_pass('in_transit', array(), array('stuck'), array());
	pg_assert_same(array('stuck'), $pass['open'], 'found and not held: opened');

	$pass = pg_delivery_tower_pass('exception', array(), array('problem', 'stuck'), array('stuck'));
	pg_assert_same(array('problem'), $pass['open'], 'held with another kind found: only the other opens');

	$pass = pg_delivery_tower_pass('in_transit', array('problem'), array(), array('problem'));
	pg_assert_same(array('problem' => 'status_changed'), $pass['close'], 'an open kind still closes when the status left');
}

function test_delivery_tower_held_kinds()
{
	$status_at = 1000;
	pg_assert_same(array('stuck'), pg_delivery_tower_held_kinds(array('stuck' => 1500), $status_at), 'closed by hand after the last status: held');
	pg_assert_same(array('stuck'), pg_delivery_tower_held_kinds(array('stuck' => 1000), $status_at), 'closed by hand at the moment of the last status: held');
	pg_assert_same(array(), pg_delivery_tower_held_kinds(array('stuck' => 900), $status_at), 'the status changed after the hand close: judged anew');
	pg_assert_same(array('not_handed_over'), pg_delivery_tower_held_kinds(array('not_handed_over' => 5), 0), 'a label never scanned stays held');
	pg_assert_same(array(), pg_delivery_tower_held_kinds(array(), $status_at), 'a row closed by a status holds nothing (only hand closes are handed in)');
	pg_assert_same(array('problem', 'returned'), pg_delivery_tower_held_kinds(array('problem' => 1200, 'returned' => 1300, 'stuck' => 800), $status_at), 'all four kinds follow the rule');
}

function test_delivery_tower_settings_clean()
{
	$defaults = pg_delivery_tower_settings_clean(array());
	pg_assert_same(true, $defaults['stuck'], 'every kind on by default');
	pg_assert_same(1, $defaults['not_handed_over_days'], 'one working day');
	pg_assert_same(1, $defaults['stuck_extra_days'], 'plus one day');

	$odd = pg_delivery_tower_settings_clean(array('not_handed_over_days' => '-4', 'stuck_extra_days' => 'abc', 'returned' => '0', 'other' => 1));
	pg_assert_same(1, $odd['not_handed_over_days'], 'a day setting under one is one');
	pg_assert_same(1, $odd['stuck_extra_days'], 'not a number: the default');
	pg_assert_same(false, $odd['returned'], 'a kind turned off');
	pg_assert_false(isset($odd['other']), 'an unknown key is dropped');
}

function test_delivery_tower_segments_by_right()
{
	$manager = array('role' => 2, 'manage_ecommerce' => false, 'manage_ecommerce_reports' => false);
	$orders = array('role' => 3, 'manage_ecommerce' => true, 'manage_ecommerce_reports' => false);
	$reports = array('role' => 3, 'manage_ecommerce' => false, 'manage_ecommerce_reports' => true);
	$both = array('role' => 3, 'manage_ecommerce' => true, 'manage_ecommerce_reports' => true);
	$none = array('role' => 3, 'manage_ecommerce' => false, 'manage_ecommerce_reports' => false);

	pg_assert_same(array('problems', 'scorecard'), array_keys(pg_delivery_tower_segments($manager, true)), 'Manager and up see both, Problems first');
	pg_assert_same(array('problems', 'scorecard'), array_keys(pg_delivery_tower_segments($both, true)), 'a user with both rights sees both');
	pg_assert_same(array('problems'), array_keys(pg_delivery_tower_segments($orders, true)), 'the orders right alone: Problems');
	pg_assert_same(array('scorecard'), array_keys(pg_delivery_tower_segments($reports, true)), 'the reports right alone: the scorecard');
	pg_assert_same(array(), array_keys(pg_delivery_tower_segments($none, true)), 'neither right: nothing');
	pg_assert_same(array('problems'), array_keys(pg_delivery_tower_segments($manager, false)), 'shipping off: no scorecard');
	pg_assert_same('view_carrier_scorecard.php', pg_delivery_tower_segments($manager, true)['scorecard'][1], 'the scorecard segment opens its screen');
}

function test_delivery_tower_segments_html()
{
	$manager = array('role' => 2);
	$html = pg_delivery_tower_segments_html('scorecard', $manager, true);

	pg_assert_contains('<nav class="pg-area-tabs mb-3"', $html, 'an area strip, centred like the others');
	pg_assert_same(2, substr_count($html, 'class="pg-seg-item'), 'two segments');
	pg_assert_same(1, substr_count($html, 'aria-current="page"'), 'one segment selected');
	pg_assert_contains('<a class="pg-seg-item is-active" href="view_carrier_scorecard.php" aria-current="page">', $html, 'the open screen is the selected one');
	pg_assert_contains('<a class="pg-seg-item" href="view_delivery_problems.php">', $html, 'the other one is its link');
	pg_assert_same(1, substr_count(pg_delivery_tower_segments_html('problems', $manager, true), 'is-active" href="view_delivery_problems.php"'), 'on Delivery problems, Problems is selected');
	pg_assert_same('', pg_delivery_tower_segments_html('problems', array('role' => 3, 'manage_ecommerce' => true), true), 'one segment leads nowhere: no strip');
	pg_assert_same('', pg_delivery_tower_segments_html('problems', $manager, false), 'shipping off: no strip');

	foreach (array('view_delivery_problems.php' => "'problems'", 'view_carrier_scorecard.php' => "'scorecard'") as $file => $key) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains("<main id=\"content\" class=\"container-fluid\">\n    ' . pg_delivery_tower_segments_html(" . $key, $source, $file . ' prints the strip first in its content');
	}
}
