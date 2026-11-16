<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a booking staff member's meeting link
 * (includes/fn/booking_links.php): the code that cannot be guessed, one
 * answer for a wrong code and a member out of booking, the services a link
 * offers, and free times that come only from the booking engine.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _booking_links_test_member($overrides = array())
{
	return $overrides + array(
		'id'          => 7,
		'user_id'     => 42,
		'enabled'     => 1,
		'user_role'   => 2,
		'service_ids' => '[1,2,3]',
		'hours'       => '{"1":[["09:00","12:00"]],"2":[["09:00","12:00"]],"3":[["09:00","12:00"]],"4":[["09:00","12:00"]],"5":[["09:00","12:00"]]}',
		'exceptions'  => '{}',
	);
}

function test_booking_link_code_cannot_be_guessed()
{
	$key = str_repeat('k', 32);
	$member = _booking_links_test_member();
	$code = pg_booking_link_code($member, $key);

	pg_assert_true(preg_match('/^7-[a-f0-9]{32}$/', $code) === 1, 'the staff id and a 128-bit mark');
	pg_assert_same($code, pg_booking_link_code($member, $key), 'worked out again, the same code');
	pg_assert_true(pg_booking_link_code_valid($code, $member, $key));

	pg_assert_true($code !== pg_booking_link_code($member, str_repeat('x', 32)), 'another site, another code');
	pg_assert_true($code !== pg_booking_link_code(_booking_links_test_member(array('user_id' => 43)), $key), 'another user on the row, another code');
	pg_assert_true($code !== pg_booking_link_code(_booking_links_test_member(array('id' => 8)), $key), 'another row, another code');

	pg_assert_same(false, pg_booking_link_code_valid('7-' . str_repeat('0', 32), $member, $key), 'a guessed mark');
	pg_assert_same(false, pg_booking_link_code_valid(substr($code, 0, -1), $member, $key), 'a cut code');
	pg_assert_same(false, pg_booking_link_code_valid(strtoupper($code), $member, $key), 'capitals are another code');
	pg_assert_same(false, pg_booking_link_code_valid('8' . substr($code, 1), $member, $key), 'the mark of another row');
	pg_assert_same('', pg_booking_link_code($member, ''), 'no key, no link');
	pg_assert_same(false, pg_booking_link_code_valid($code, $member, ''), 'no key, no valid code');
}

function test_booking_link_code_staff_id()
{
	pg_assert_same(7, pg_booking_link_code_staff_id('7-' . str_repeat('a', 32)));
	pg_assert_same(0, pg_booking_link_code_staff_id('0-' . str_repeat('a', 32)));
	pg_assert_same(0, pg_booking_link_code_staff_id('07-' . str_repeat('a', 32)), 'one spelling per row');
	pg_assert_same(0, pg_booking_link_code_staff_id('7-' . str_repeat('a', 31)));
	pg_assert_same(0, pg_booking_link_code_staff_id(array('7')));
	pg_assert_same(0, pg_booking_link_code_staff_id("7-" . str_repeat('a', 32) . "\n"));
}

function test_booking_link_wrong_code_and_member_out_of_booking_are_one_answer()
{
	$key = str_repeat('k', 32);
	$member = _booking_links_test_member();
	$code = pg_booking_link_code($member, $key);

	pg_assert_same($member, pg_booking_link_member_pick($code, $member, $key));
	pg_assert_same(null, pg_booking_link_member_pick('7-' . str_repeat('0', 32), $member, $key), 'a wrong code');
	pg_assert_same(null, pg_booking_link_member_pick($code, null, $key), 'no such staff row');
	pg_assert_same(null, pg_booking_link_member_pick($code, _booking_links_test_member(array('enabled' => 0)), $key), 'taken out of booking');
	pg_assert_same(null, pg_booking_link_member_pick($code, _booking_links_test_member(array('user_role' => 3)), $key), 'a user of role 3 cannot be staff');
	pg_assert_same(null, pg_booking_link_member_pick($code, _booking_links_test_member(array('user_id' => 0)), $key), 'a deleted user');
	pg_assert_same(null, pg_booking_link_member_pick($code, _booking_links_test_member(array('service_ids' => '[]')), $key), 'no services left');
}

function test_booking_link_services_follow_the_member()
{
	$services = array(
		array('id' => 1, 'price' => 0, 'deposit' => 0, 'deposit_product_id' => 0),
		array('id' => 2, 'price' => 10000, 'deposit' => 2000, 'deposit_product_id' => 55),
		array('id' => 4, 'price' => 5000, 'deposit' => 0, 'deposit_product_id' => 0),
		array('id' => 3, 'price' => 5000, 'deposit' => 1000, 'deposit_product_id' => 0),
	);
	$member = _booking_links_test_member();

	$ids = array_map(function ($row) { return (int) $row['id']; }, pg_booking_link_services($services, $member));
	pg_assert_same(array(1, 3), $ids, 'a deposit service is refused; a service of others is not offered; a half-set deposit asks for none');

	$lost = pg_booking_link_services($services, _booking_links_test_member(array('service_ids' => '[2,3]')));
	pg_assert_same(array(3), array_map(function ($row) { return (int) $row['id']; }, $lost), 'a service the member lost leaves the link');

	pg_assert_true(pg_booking_link_service($services, $member, 1) !== null);
	pg_assert_same(null, pg_booking_link_service($services, $member, 2), 'a deposit service cannot be booked through a link');
	pg_assert_same(null, pg_booking_link_service($services, $member, 4), 'not the member\'s service');
	pg_assert_same(null, pg_booking_link_service($services, _booking_links_test_member(array('service_ids' => '[3]')), 1), 'taken from the member');
}

function test_booking_link_private_plan_leaves_free_times_and_team_event_takes_its_time()
{
	$zone = 'Europe/Istanbul';
	$policy = array('zone' => $zone, 'lead_minutes' => 0, 'horizon_days' => 60, 'step_minutes' => 60, 'holidays' => array());
	$service = array('id' => 1, 'length_minutes' => 60, 'buffer_before' => 0, 'buffer_after' => 0);
	$member = _booking_links_test_member();
	$day = '2027-03-01';
	$now = (new DateTime('2027-02-20 12:00:00', new DateTimeZone($zone)))->getTimestamp();
	$at = function ($time) use ($day, $zone) {
		return (new DateTime($day . ' ' . $time, new DateTimeZone($zone)))->getTimestamp();
	};

	$free = pg_booking_link_times($service, $member, $day, array(), $now, $policy);
	pg_assert_same(array($at('09:00'), $at('10:00'), $at('11:00')), $free);
	pg_assert_same(
		array_map(function ($row) { return $row['start']; }, pg_booking_day_times($service, array($member), $day, array(), $now, $policy)),
		$free,
		'the engine\'s own times for that member');

	// A private plan never reaches the busy times: the busy ranges are read
	// only through the engine, which reads ws_events, never ws_personal_plans.
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/booking_links.php');
	pg_assert_true(strpos($source, 'pg_booking_busy_between(') !== false, 'busy times come from the engine');
	pg_assert_true(strpos($source, 'ws_personal_plan') === false, 'the link reads no private plan');
	$engine = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_booking.php');
	pg_assert_true(strpos($engine, 'ws_personal_plan') === false, 'the engine reads no private plan');

	// A team event the member is in takes its time.
	$event = array('id' => 5, 'scope' => 'people', 'department_id' => 0, 'people' => array(42), 'starts_at' => $at('10:00'), 'ends_at' => $at('11:00'), 'all_day' => 0);
	$busy = pg_booking_ws_busy(array($event), array($member), array());
	pg_assert_same(array($at('09:00'), $at('11:00')), pg_booking_link_times($service, $member, $day, $busy, $now, $policy));

	// Another member's busy ranges change nothing.
	pg_assert_same($free, pg_booking_link_times($service, $member, $day, array(9 => array(array($at('09:00'), $at('12:00')))), $now, $policy));
}

function test_booking_link_meeting_address_clean()
{
	pg_assert_same('', pg_booking_link_meeting_url_clean(''));
	pg_assert_same('https://meet.example.com/abc', pg_booking_link_meeting_url_clean('  https://meet.example.com/abc '));
	pg_assert_same(false, pg_booking_link_meeting_url_clean('javascript:alert(1)'));
	pg_assert_same(false, pg_booking_link_meeting_url_clean('https://' . str_repeat('a', 500) . '.com'));
}

function test_booking_link_meeting_address_of_a_booking()
{
	pg_assert_same('https://meet.example.com/a', pg_booking_link_booking_meeting_url(array('source' => 'link'), 'https://meet.example.com/a'));
	pg_assert_same('', pg_booking_link_booking_meeting_url(array('source' => 'site'), 'https://meet.example.com/a'), 'only a booking made through the link');
	pg_assert_same('', pg_booking_link_booking_meeting_url(array('source' => 'link'), null), 'the member has none');
	pg_assert_same('', pg_booking_link_booking_meeting_url(array('source' => 'link'), 'javascript:alert(1)'), 'read when shown, cleaned again');
}
