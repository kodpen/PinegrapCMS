<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the booking widget's pure helpers (includes/fn/widgets_booking.php):
 * the staff of a service, the days of a month with a free time, the times of
 * a day over several staff members ("anyone" picks the freest), and the busy
 * ranges of Workspace calendar events.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function booking_widget_test_at($local)
{
	$moment = new DateTime($local, new DateTimeZone('Europe/Istanbul'));

	return $moment->getTimestamp();
}

function booking_widget_test_staff($id, $days = array(1, 2, 3, 4, 5))
{
	$hours = array();

	foreach ($days as $day) {
		$hours[(string) $day] = array(array('09:00', '12:00'));
	}

	return array('id' => $id, 'user_id' => 100 + $id, 'name' => 'Staff ' . $id, 'service_ids' => '[1,2]', 'enabled' => 1, 'hours' => $hours, 'exceptions' => array());
}

function test_booking_widget_service_ids()
{
	pg_assert_same(array(1, 2), pg_booking_service_ids('[1,"2",2,0,-3,"x"]'));
	pg_assert_same(array(), pg_booking_service_ids('not json'));
	pg_assert_same(array(4), pg_booking_service_ids(array(4)));
}

function test_booking_widget_staff_for()
{
	$staff = array(
		array('id' => 1, 'service_ids' => '[1]', 'enabled' => 1),
		array('id' => 2, 'service_ids' => '[2]', 'enabled' => 1),
		array('id' => 3, 'service_ids' => '[1,2]', 'enabled' => 0),
		array('id' => 4, 'service_ids' => '[2,1]', 'enabled' => 1),
	);

	$ids = array();
	foreach (pg_booking_staff_for($staff, 1) as $row) {
		$ids[] = $row['id'];
	}

	pg_assert_same(array(1, 4), $ids, 'a switched-off staff member is left out');

	$map = pg_booking_widget_staff_map(array(array('id' => 2)), array(array('id' => 2, 'name' => 'Ayşe', 'service_ids' => '[2]', 'enabled' => 1)));
	pg_assert_same(array('2' => array(array('id' => 2, 'name' => 'Ayşe'))), $map);
}

function test_booking_widget_month()
{
	pg_assert_true(pg_booking_month_valid('2026-11'));
	pg_assert_false(pg_booking_month_valid('2026-13'));
	pg_assert_false(pg_booking_month_valid('2026-1'));
	pg_assert_false(pg_booking_month_valid(array('2026-11')));
	pg_assert_same(28, count(pg_booking_month_dates('2026-02')));
	pg_assert_same('2026-02-28', pg_booking_month_dates('2026-02')[27]);
	pg_assert_same(array(), pg_booking_month_dates('x'));
}

function test_booking_widget_month_days()
{
	$service = array('length_minutes' => 60, 'buffer_before' => 0, 'buffer_after' => 0);
	$policy = array('zone' => 'Europe/Istanbul', 'lead_minutes' => 60, 'horizon_days' => 10);
	$now = booking_widget_test_at('2026-11-03 10:30');

	// Monday to Friday, 09:00-12:00; at 10:30 with an hour's lead the
	// earliest start is 11:30, so today has nothing left.
	$days = pg_booking_month_days($service, array(booking_widget_test_staff(1)), '2026-11', array(), $now, $policy);

	pg_assert_same('2026-11-04', $days[0], 'today has no time left after the lead time');
	pg_assert_false(in_array('2026-11-07', $days, true), 'no hours on Saturday');
	pg_assert_same('2026-11-13', end($days), 'the horizon ends the month early');

	// A day fully busy for the only staff member is not offered.
	$busy = array(1 => array(array(booking_widget_test_at('2026-11-04 09:00'), booking_widget_test_at('2026-11-04 12:00'))));
	$days = pg_booking_month_days($service, array(booking_widget_test_staff(1)), '2026-11', $busy, $now, $policy);
	pg_assert_same('2026-11-05', $days[0]);

	// Another staff member free that day brings it back.
	$days = pg_booking_month_days($service, array(booking_widget_test_staff(1), booking_widget_test_staff(2)), '2026-11', $busy, $now, $policy);
	pg_assert_same('2026-11-04', $days[0]);

	pg_assert_same(array(), pg_booking_month_days($service, array(booking_widget_test_staff(1)), '2026-10', array(), $now, $policy), 'a past month');
}

function test_booking_widget_day_times_freest()
{
	$service = array('length_minutes' => 60, 'buffer_before' => 0, 'buffer_after' => 0);
	$policy = array('zone' => 'Europe/Istanbul');
	$now = booking_widget_test_at('2026-11-01 08:00');

	// Staff 1 has 10:00 taken; staff 2 is free all morning.
	$busy = array(1 => array(array(booking_widget_test_at('2026-11-04 10:00'), booking_widget_test_at('2026-11-04 11:00'))));
	$times = pg_booking_day_times($service, array(booking_widget_test_staff(1), booking_widget_test_staff(2)), '2026-11-04', $busy, $now, $policy);

	pg_assert_same(3, count($times), 'each start once');
	pg_assert_same(booking_widget_test_at('2026-11-04 09:00'), $times[0]['start']);
	pg_assert_same(2, $times[0]['staff_id'], 'the freer staff member takes a shared start');
	pg_assert_same(2, $times[1]['staff_id'], 'only staff 2 is free at 10:00');

	// Equal load: the lowest id.
	$times = pg_booking_day_times($service, array(booking_widget_test_staff(3), booking_widget_test_staff(2)), '2026-11-04', array(), $now, $policy);
	pg_assert_same(2, $times[0]['staff_id']);

	pg_assert_same(array(), pg_booking_day_times($service, array(booking_widget_test_staff(1)), '2026-11-07', array(), $now, $policy), 'a day off');
}

function test_booking_widget_ws_busy()
{
	$staff = array(array('id' => 1, 'user_id' => 10), array('id' => 2, 'user_id' => 20));
	$events = array(
		array('scope' => 'people', 'department_id' => 0, 'people' => array(10), 'starts_at' => 100, 'ends_at' => 200, 'all_day' => 0),
		array('scope' => 'department', 'department_id' => 7, 'people' => array(), 'starts_at' => 300, 'ends_at' => 399, 'all_day' => 1),
		array('scope' => 'company', 'department_id' => 0, 'people' => array(), 'starts_at' => 500, 'ends_at' => 600, 'all_day' => 0),
	);

	pg_assert_same(
		array(1 => array(array(100, 200), array(500, 600)), 2 => array(array(300, 400), array(500, 600))),
		pg_booking_ws_busy($events, $staff, array(20 => array(7))),
		'named people, the department and the company; an all-day event ends a second later');
}

function test_booking_widget_render()
{
	$node = function ($tag, $props, $children = array()) {
		return array('type' => 'semantic', 'props' => array('tag' => $tag) + $props, 'children' => $children);
	};
	$tree = json_encode(array('type' => 'root', 'props' => array(), 'children' => array(
		$node('div', array('_bindings' => array('section' => 'booking_step_service')), array(
			array('type' => 'loop_area', 'props' => array(), 'children' => array(
				$node('button', array('text' => 'x', '_bindings' => array('action' => 'booking_service', 'text' => '__service_name'),
					'_attrs' => array(array('name' => 'type', 'value' => 'submit')))),
			)),
		)),
		$node('div', array('_bindings' => array('section' => 'booking_times'))),
		$node('div', array('_bindings' => array('section' => 'not_ours'))),
		$node('p', array('text' => 'none', '_bindings' => array('eo_visible_if' => 'no_services'))),
	)));
	$services = array(array('id' => 3, 'name' => '<b>Cut</b>', 'description' => '', 'length_minutes' => 45, 'price' => 0));
	$staff = array(array('id' => 9, 'user_id' => 1, 'name' => 'Ayşe', 'service_ids' => '[3]', 'enabled' => 1));

	$html = _render_system_widget_booking($tree, 41, array(), 'preview', array($services, $staff));

	pg_assert_contains('data-pg-booking-part="step_service"', $html);
	pg_assert_contains('data-pg-booking-part="times"', $html);
	pg_assert_false(strpos($html, 'not_ours') !== false, 'only the widget\'s own parts are marked');
	pg_assert_contains('data-pg-booking-action="service"', $html);
	pg_assert_contains('data-pg-booking-service="3"', $html, 'the row\'s service id');
	pg_assert_contains('type="button"', $html, 'a service button never submits');
	pg_assert_false(strpos($html, 'type="submit"') !== false, 'the stored type is replaced');
	pg_assert_contains('&lt;b&gt;Cut&lt;/b&gt;', $html, 'the name is escaped');
	pg_assert_false(strpos($html, '>none<') !== false, 'the empty note is gone with services');
	pg_assert_true(preg_match('/^<div data-pg-booking="([^"]+)">/', $html, $m) === 1, 'the settings wrap the widget');
	$settings = json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true);
	pg_assert_same(array('3' => array(array('id' => 9, 'name' => 'Ayşe'))), $settings['staff']);
	pg_assert_contains('/booking_slots.php', $settings['url']);

	$empty = _render_system_widget_booking($tree, 42, array(), 'preview', array(array(), array()));
	pg_assert_contains('>none<', $empty, 'no service: the note');
	pg_assert_false(strpos($empty, 'data-pg-booking=') !== false, 'no settings, so no script');
}

function test_booking_widget_inject()
{
	$page = '<html><body><div data-pg-booking="{}"></div></body></html>';
	$out = pg_booking_widget_inject($page, '/s/pg_booking.js');
	pg_assert_contains('<script src="/s/pg_booking.js" defer></script>' . "\n" . '</body>', $out);
	pg_assert_same($out, pg_booking_widget_inject($out, '/s/pg_booking.js'), 'once');
	pg_assert_same('<p>x</p>', pg_booking_widget_inject('<p>x</p>', '/s/pg_booking.js'), 'no widget, nothing');
	pg_assert_same(1, preg_match(pg_site_datetime_pattern(), $page), 'the widget brings the date control');
	pg_assert_same(0, preg_match(pg_site_datetime_pattern(), '<div data-pg-booking-part="x">'), 'a part does not');
}

function test_booking_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['booking']), 'panel label');
	pg_assert_contains('_render_system_widget_booking(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php'));
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'booking',", $js, 'in SW_TYPES');
	pg_assert_contains("case 'booking': {", $js, 'has a starter tree');
	pg_assert_contains('booking:           SW_BOOKING_TOKEN_GROUPS', $js, 'has a token palette');

	foreach (pg_booking_widget_parts() as $part) {
		pg_assert_contains("'" . $part . "'", $js, 'the editor offers part ' . $part);
	}
}

function test_booking_widget_details_clean()
{
	$good = pg_booking_widget_details_clean(array('name' => "  Ada \n Lovelace ", 'email' => ' ada@example.com ', 'phone' => '+90 (532) 111-22-33', 'notes' => "First visit\r\nPlease call", 'consent' => true));
	pg_assert_same(array(), $good['errors'], 'a full form passes');
	pg_assert_same('Ada Lovelace', $good['values']['name'], 'spaces folded');
	pg_assert_same('ada@example.com', $good['values']['email']);
	pg_assert_same('+90 (532) 111-22-33', $good['values']['phone']);
	pg_assert_same("First visit\nPlease call", $good['values']['notes'], 'line ends kept as \n');

	$empty = pg_booking_widget_details_clean(array());
	pg_assert_same(array('name', 'email'), array_keys($empty['errors']), 'name and e-mail are needed; no consent box sent, none asked');

	$bad = pg_booking_widget_details_clean(array('name' => 'A', 'email' => 'not-mail', 'phone' => 'call me', 'consent' => false));
	pg_assert_same(array('email', 'phone', 'consent'), array_keys($bad['errors']), 'bad e-mail, phone with letters, consent box left empty');

	$long = pg_booking_widget_details_clean(array('name' => str_repeat('a', 150), 'email' => 'a@b.co', 'notes' => str_repeat('n', 3000), 'consent' => 'yes'));
	pg_assert_same(100, mb_strlen($long['values']['name']), 'name cut to its column');
	pg_assert_same(1000, mb_strlen($long['values']['notes']), 'notes bounded');
	pg_assert_true(isset($long['errors']['consent']), 'only a true consent counts');

	$arrays = pg_booking_widget_details_clean(array('name' => array('x'), 'email' => array('a@b.co')));
	pg_assert_same(array('name', 'email'), array_keys($arrays['errors']), 'a list is no value');
}

function test_booking_widget_render_details()
{
	$node = function ($tag, $props, $children = array()) {
		return array('type' => 'semantic', 'props' => array('tag' => $tag) + $props, 'children' => $children);
	};
	$tree = json_encode(array('type' => 'root', 'props' => array(), 'children' => array(
		$node('form', array('_bindings' => array('section' => 'booking_form')), array(
			$node('span', array('_bindings' => array('eo_visible_if' => 'has_privacy_page')), array(
				$node('a', array('text' => 'Privacy', 'href' => '#', '_bindings' => array('href' => '__privacy_url'))),
			)),
		)),
		$node('div', array('_bindings' => array('section' => 'booking_step_done')), array(
			$node('p', array('_bindings' => array('section' => 'booking_done'))),
		)),
	)));
	$services = array(array('id' => 3, 'name' => 'Cut', 'description' => '', 'length_minutes' => 45, 'price' => 0));
	$staff = array(array('id' => 9, 'user_id' => 1, 'name' => 'Ayşe', 'service_ids' => '[3]', 'enabled' => 1));

	$html = _render_system_widget_booking($tree, 43, array(), 'preview', array($services, $staff, '/privacy-policy'));
	pg_assert_contains('data-pg-booking-part="form"', $html);
	pg_assert_contains('data-pg-booking-part="step_done"', $html);
	pg_assert_contains('data-pg-booking-part="done"', $html);
	pg_assert_contains('href="/privacy-policy"', $html, 'the consent box links the privacy notice');

	$none = _render_system_widget_booking($tree, 44, array(), 'preview', array($services, $staff, ''));
	pg_assert_false(strpos($none, 'Privacy') !== false, 'no privacy page: no dead link');
}

function test_booking_widget_done_text()
{
	pg_assert_same('See you soon.', pg_booking_widget_done_text('confirmed', array('done' => 'See you soon.')), 'the site\'s own words');
	pg_assert_true(pg_booking_widget_done_text('confirmed', array()) !== pg_booking_widget_done_text('requested', array()), 'a request waits, a booking is made');
}
