<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the meeting link's page (book_with.php and the page's helpers
 * in includes/fn/booking_links.php): free days and times that are the
 * engine's for the member alone, a time just taken, the POST's form token,
 * origin and rate limit, no cache and no index, and the meeting address in
 * a booking's calendar file and mail fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _book_with_test_member($overrides = array())
{
	return $overrides + array(
		'id'          => 7,
		'user_id'     => 42,
		'enabled'     => 1,
		'user_role'   => 2,
		'service_ids' => '[1,2]',
		'hours'       => '{"1":[["09:00","12:00"]],"2":[["09:00","12:00"]],"3":[["09:00","12:00"]],"4":[["09:00","12:00"]],"5":[["09:00","12:00"]]}',
		'exceptions'  => '{"2027-03-10":[]}',
	);
}

function _book_with_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/book_with.php');
}

// The month's days and the day's times are the engine's for that member,
// with only that member's busy ranges counted.
function test_book_with_free_times_are_the_engines()
{
	$zone = 'Europe/Istanbul';
	$policy = array('zone' => $zone, 'lead_minutes' => 0, 'horizon_days' => 60, 'step_minutes' => 60, 'holidays' => array());
	$service = array('id' => 1, 'length_minutes' => 60, 'buffer_before' => 0, 'buffer_after' => 0);
	$member = _book_with_test_member();
	$now = (new DateTime('2027-03-01 08:00:00', new DateTimeZone($zone)))->getTimestamp();
	$at = function ($day, $time) use ($zone) {
		return (new DateTime($day . ' ' . $time . ':00', new DateTimeZone($zone)))->getTimestamp();
	};

	$busy = array(7 => array(array($at('2027-03-02', '09:00'), $at('2027-03-02', '12:00'))));
	$days = pg_booking_link_days($service, $member, '2027-03', $busy, $now, $policy);

	pg_assert_same(pg_booking_month_days($service, array($member), '2027-03', $busy, $now, $policy), $days, 'the engine\'s days');
	pg_assert_false(in_array('2027-03-02', $days, true), 'a day the member is busy all morning');
	pg_assert_false(in_array('2027-03-10', $days, true), 'the member\'s day off');
	pg_assert_false(in_array('2027-03-06', $days, true), 'a Saturday without hours');
	pg_assert_true(in_array('2027-03-03', $days, true));

	$others = array(9 => $busy[7]);
	pg_assert_true(in_array('2027-03-02', pg_booking_link_days($service, $member, '2027-03', $others, $now, $policy), true), 'another member\'s busy time takes nothing');

	$day_busy = array(7 => array(array($at('2027-03-03', '10:00'), $at('2027-03-03', '11:00'))));
	pg_assert_same(array($at('2027-03-03', '09:00'), $at('2027-03-03', '11:00')), pg_booking_link_times($service, $member, '2027-03-03', $day_busy, $now, $policy));

	list($from, $until) = pg_booking_link_busy_window('2027-03-01', '+1 month', $zone);
	pg_assert_same($at('2027-02-28', '00:00'), $from, 'a day before the month');
	pg_assert_same($at('2027-04-02', '00:00'), $until, 'a day after the month');
	list($from, $until) = pg_booking_link_busy_window('2027-03-03', '+1 day', $zone);
	pg_assert_same(array($at('2027-03-02', '00:00'), $at('2027-03-05', '00:00')), array($from, $until));
}

// A time just taken says so; the page then draws the day's times again.
function test_book_with_taken_time()
{
	pg_assert_same('', pg_booking_link_answer_text('ok'));
	pg_assert_same(pg_booking_widget_taken_text(), pg_booking_link_answer_text('taken'));
	pg_assert_contains('ran out', pg_booking_link_answer_text('expired'));
	pg_assert_contains('went wrong', pg_booking_link_answer_text('error'));

	$source = _book_with_test_source();
	pg_assert_contains("in_array(\$bw_start, \$bw_starts, true)", $source, 'a hold only on a time the page offers');
	pg_assert_contains('pg_booking_hold(', $source);
	pg_assert_contains('pg_booking_confirm(', $source);
	pg_assert_contains('PG_BOOKING_LINK_SOURCE', $source, 'written with the link\'s source');
	pg_assert_contains("\$bw_day = (new DateTime('@' . \$bw_hold['start']))", $source, 'a taken confirmation shows that day\'s times again');
}

// The session's hold belongs to the link only for its member and a service
// the link still offers.
function test_book_with_held_time()
{
	$member = _book_with_test_member();
	$services = array(array('id' => 1, 'length_minutes' => 60), array('id' => 2, 'length_minutes' => 30));

	$held = pg_booking_link_held(55, array(2, 7, 1800000000), $member, $services);
	pg_assert_same(array('id' => 55, 'service' => $services[1], 'start' => 1800000000), $held);
	pg_assert_same(null, pg_booking_link_held(55, array(2, 8, 1800000000), $member, $services), 'another member\'s hold');
	pg_assert_same(null, pg_booking_link_held(55, array(3, 7, 1800000000), $member, $services), 'a service the link does not offer');
	pg_assert_same(null, pg_booking_link_held(0, array(2, 7, 1800000000), $member, $services), 'no hold');
	pg_assert_same(null, pg_booking_link_held(55, null, $member, $services));
	pg_assert_same(null, pg_booking_link_held(55, array(2, 7), $member, $services));
}

// The details are the widget's; the consent box is always on this form.
function test_book_with_details()
{
	$ok = pg_booking_link_details(array('name' => ' Ayşe  Yılmaz ', 'email' => 'ayse@example.com', 'phone' => '+90 555 111 22 33', 'notes' => 'Hi', 'consent' => '1'));
	pg_assert_same(array(), $ok['errors']);
	pg_assert_same('Ayşe Yılmaz', $ok['values']['name']);

	$missing = pg_booking_link_details(array('name' => 'A', 'email' => 'a@example.com'));
	pg_assert_true(isset($missing['errors']['consent']), 'an unticked box');

	$bad = pg_booking_link_details(array('name' => array('x'), 'email' => 'no', 'consent' => '1'));
	pg_assert_true(isset($bad['errors']['name']) && isset($bad['errors']['email']), 'a list is no name');
}

// A POST from another site and an address over the limit are refused before
// the page reads them; a link followed from another site is answered.
function test_book_with_post_origin_and_rate_limit()
{
	pg_assert_same(null, pg_booking_link_refusal(false, false, 0));
	pg_assert_same(null, pg_booking_link_refusal(false, true, 0), 'a link opened from a mail or another site');
	pg_assert_same(null, pg_booking_link_refusal(true, false, 0));

	$cross = pg_booking_link_refusal(true, true, 0);
	pg_assert_same(403, $cross['status']);

	$rate = pg_booking_link_refusal(true, false, 600);
	pg_assert_same(429, $rate['status']);
	pg_assert_same(600, $rate['retry']);
	pg_assert_same(429, pg_booking_link_refusal(false, false, 300)['status'], 'reads are limited too');

	$source = _book_with_test_source();
	$refusal = strpos($source, 'pg_booking_link_refusal(');
	$token = strpos($source, 'validate_token_field();');
	$member = strpos($source, 'pg_booking_link_member(');

	pg_assert_true(($refusal !== false) && ($token !== false) && ($member !== false), 'the three checks are there');
	pg_assert_true(($refusal < $token) && ($token < $member), 'origin and rate, then the form token, before the member is read');
	pg_assert_contains("pg_request_cross_site(\$_SERVER)", $source);
	pg_assert_contains("pg_booking_throttled(\$bw_post ? 'hold' : 'read')", $source);
	pg_assert_same(2, substr_count($source, 'get_token_field()'), 'both forms carry the token');
	pg_assert_same(2, substr_count($source, '<form method="post"'), 'and they are the only forms');
}

// Never kept by a cache, never indexed, the code never sent as a referrer.
function test_book_with_no_cache_no_index()
{
	$headers = pg_booking_link_page_headers();

	pg_assert_true(in_array('Cache-Control: no-store, private', $headers, true));
	pg_assert_true(in_array('X-Robots-Tag: noindex, nofollow', $headers, true));
	pg_assert_true(in_array('Referrer-Policy: no-referrer', $headers, true));

	$source = _book_with_test_source();
	pg_assert_true(strpos($source, 'pg_booking_link_page_headers()') < strpos($source, 'pg_booking_link_refusal('), 'sent first, on every answer');
}

// A wrong code and a member who cannot be booked get the same 404.
function test_book_with_one_404()
{
	$source = _book_with_test_source();

	pg_assert_same(1, substr_count($source, 'set_response_code(404)'), 'one 404 answer');
	pg_assert_contains("if ((\$bw_member === null) || !\$bw_services)", $source, 'for no member and for a link that offers nothing');
}

// The meeting address in a booking's calendar file: a CONFERENCE, a line of
// the description, the place when the site has none; never a broken line.
function test_book_with_meeting_address_in_the_calendar_file()
{
	$booking = array('id' => 42, 'start_at' => 1793000000, 'end_at' => 1793002700, 'status' => 'confirmed', 'created_at' => 1, 'updated_at' => 1, 'service_name' => 'Call', 'staff_name' => 'Deniz Kaya');
	$url = 'https://meet.example.com/abc-defg-hij';
	$unfold = function ($ics) {
		return str_replace("\r\n ", '', $ics);
	};

	$ics = $unfold(pg_booking_ics($booking, array('host' => 'shop.example.com', 'place' => 'Main St 1', 'meeting_url' => $url), 1793000100));
	pg_assert_contains("\r\nCONFERENCE;VALUE=URI:" . $url . "\r\n", $ics);
	pg_assert_contains("\r\nLOCATION:Main St 1\r\n", $ics, 'the site\'s address stays the place');
	pg_assert_contains("\r\nDESCRIPTION:Deniz Kaya\\n" . $url . "\r\n", $ics);

	$ics = $unfold(pg_booking_ics($booking, array('host' => 'shop.example.com', 'meeting_url' => $url), 1793000100));
	pg_assert_contains("\r\nLOCATION:" . $url . "\r\n", $ics, 'no address: the meeting is the place');

	$ics = pg_booking_ics($booking, array('host' => 'shop.example.com', 'meeting_url' => "https://x.example/a\r\nATTACH:evil"), 1793000100);
	pg_assert_false(strpos($ics, 'CONFERENCE') !== false, 'a broken address is left out');
	pg_assert_false(strpos($ics, "\r\nATTACH") !== false);

	$ics = pg_booking_ics($booking, array('host' => 'shop.example.com'), 1793000100);
	pg_assert_false(strpos($ics, 'CONFERENCE') !== false, 'none without an address');
	pg_assert_false(strpos($ics, 'LOCATION') !== false);
}

// The mail field carries the context's address; a booking made elsewhere
// has none, and a cancelled one none either.
function test_book_with_meeting_address_as_a_mail_field()
{
	$meanings = pg_booking_mail_field_meanings();
	pg_assert_true(isset($meanings['booking_meeting_url']));

	$values = pg_booking_message_values(array('id' => 1, 'start_at' => 1793000000, 'end_at' => 1793002700), array('meeting_url' => 'https://meet.example.com/x'));
	pg_assert_same('https://meet.example.com/x', $values['meeting_url']);

	pg_assert_same('', pg_booking_message_meeting_url(array('source' => 'site', 'status' => 'confirmed', 'staff_user_id' => 42)), 'a widget booking');
	pg_assert_same('', pg_booking_message_meeting_url(array('source' => 'link', 'status' => 'cancelled', 'staff_user_id' => 42)), 'a cancelled booking');

	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	pg_assert_contains("'booking_meeting_url'", $mail, 'the meeting address loses the editor\'s http://');
}
