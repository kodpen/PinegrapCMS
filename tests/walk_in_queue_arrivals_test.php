<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how a walk-in queue number arrives
 * (includes/fn/walk_in_queue_arrivals.php): the priority group read from a
 * form, a booking matched by phone or e-mail and picked among the day's,
 * "I'm on my way", where a number moved back after Not here stands, and the
 * wiring of the join form, the person's page and the desk.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_walk_in_queue_arrivals_priority_is_one_of_the_place_groups()
{
	$groups = array(array('key' => 'p1', 'name' => 'Elderly'), array('key' => 'p3', 'name' => 'Pregnant'));

	pg_assert_same('p3', pg_waiting_priority_clean('p3', $groups));
	pg_assert_same('', pg_waiting_priority_clean('p2', $groups), 'a removed group');
	pg_assert_same('', pg_waiting_priority_clean('', $groups));
	pg_assert_same('', pg_waiting_priority_clean(array('p1'), $groups));
	pg_assert_same('', pg_waiting_priority_clean('p1', array()));
}

function test_walk_in_queue_arrivals_join_form_reads_priority_and_booking_only_where_asked()
{
	$place = array(
		'services' => array(array('key' => 's1', 'name' => 'Counter', 'letter' => 'A', 'minutes' => 5)),
		'settings' => array('party_size' => 0, 'priority' => array(array('key' => 'p1', 'name' => 'Elderly'))),
	);
	$post = array('priority' => 'p1', 'booking' => '1', 'email' => 'ada@example.com');

	$site = pg_waiting_join_input($post, $place);
	pg_assert_same('', $site['values']['priority'], 'the site page asks no priority');
	pg_assert_same(false, $site['values']['booking'], 'no booking where the place folds none in');

	$staff = pg_waiting_join_input($post, $place, true, true);
	pg_assert_same('', $staff['error']);
	pg_assert_same('p1', $staff['values']['priority']);
	pg_assert_same(true, $staff['values']['booking']);

	$bare = pg_waiting_join_input(array('booking' => '1'), $place, false, true);
	pg_assert_true($bare['error'] !== '', 'a booking needs the phone or e-mail it was made with');
}

function test_walk_in_queue_arrivals_booking_matches_by_email_or_phone()
{
	$phone = pg_sms_number('+90 532 111 22 33');
	$booking = array('email' => 'Ada@Example.com', 'phone' => '+905321112233');

	pg_assert_true(pg_waiting_booking_matches($booking, '', 'ada@example.com'), 'e-mail without case');
	pg_assert_true($phone !== '' && pg_waiting_booking_matches($booking, $phone, ''), 'phone as the SMS number');
	pg_assert_same(false, pg_waiting_booking_matches($booking, '', 'other@example.com'));
	pg_assert_same(false, pg_waiting_booking_matches(array('email' => '', 'phone' => ''), '', ''));
	pg_assert_same(false, pg_waiting_booking_matches(array('email' => '', 'phone' => ''), $phone, ''), 'a booking without a phone');
}

function test_walk_in_queue_arrivals_booking_pick_is_the_live_one_nearest_now_not_in_queue()
{
	$now = 1800000000;
	$rows = array(
		array('id' => 1, 'start_at' => $now - 7200, 'status' => 'confirmed'),
		array('id' => 2, 'start_at' => $now + 600, 'status' => 'confirmed'),
		array('id' => 3, 'start_at' => $now - 300, 'status' => 'cancelled'),
		array('id' => 4, 'start_at' => $now + 900, 'status' => 'requested'),
	);

	pg_assert_same(2, (int) pg_waiting_booking_pick($rows, array(), $now)['id']);
	pg_assert_same(4, (int) pg_waiting_booking_pick($rows, array(2), $now)['id'], 'a booking already in the queue is passed');
	pg_assert_same(1, (int) pg_waiting_booking_pick($rows, array(2, 4), $now)['id']);
	pg_assert_same(null, pg_waiting_booking_pick($rows, array(1, 2, 4), $now), 'a cancelled booking never joins');
	pg_assert_same(null, pg_waiting_booking_pick(array(), array(), $now));
}

function test_walk_in_queue_arrivals_day_bounds_are_the_site_day()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Istanbul');
	// 2026-11-23 10:00 in Istanbul (UTC+3).
	list($start, $end) = pg_waiting_day_bounds(1763881200);
	date_default_timezone_set($zone);

	pg_assert_same(1763845200, $start, 'midnight in the site zone');
	pg_assert_same(86400, $end - $start);
}

function test_walk_in_queue_arrivals_on_the_way_is_offered_once_to_a_site_number()
{
	pg_assert_true(pg_waiting_on_way_offered(array('source' => 'site', 'state' => 'waiting', 'on_way_at' => 0)));
	pg_assert_same(false, pg_waiting_on_way_offered(array('source' => 'qr', 'state' => 'waiting', 'on_way_at' => 0)), 'taken at the place');
	pg_assert_same(false, pg_waiting_on_way_offered(array('source' => 'site', 'state' => 'waiting', 'on_way_at' => 5)), 'already said');
	pg_assert_same(false, pg_waiting_on_way_offered(array('source' => 'site', 'state' => 'called', 'on_way_at' => 0)));
}

function test_walk_in_queue_arrivals_moved_back_number_stands_behind_those_waiting()
{
	$t = 1800000000;
	$tickets = array(
		array('id' => 1, 'service' => 's1', 'joined_at' => $t, 'priority' => '', 'booking_at' => 0, 'moved_back' => 1, 'called_at' => $t + 900),
		array('id' => 2, 'service' => 's1', 'joined_at' => $t + 300, 'priority' => '', 'booking_at' => 0, 'moved_back' => 0, 'called_at' => 0),
		array('id' => 3, 'service' => 's1', 'joined_at' => $t + 1200, 'priority' => '', 'booking_at' => 0, 'moved_back' => 0, 'called_at' => 0),
	);

	pg_assert_same(array(2, 1, 3), array_map('intval', array_column(pg_waiting_order($tickets, 600), 'id')), 'behind those waiting at the move, before later ones');
	pg_assert_same($t + 900, pg_waiting_order_time($tickets[0], 600));
	pg_assert_same($t, pg_waiting_order_time(array('joined_at' => $t, 'moved_back' => 1, 'called_at' => 0), 600), 'no move time kept: its own place');
}

function test_walk_in_queue_arrivals_wiring()
{
	$tickets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/walk_in_queue_tickets.php');
	$desk = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/walk_in_queue_desk.php');
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/queue.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/queue_desk.php');
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/walk_in_queue_arrivals.php');

	pg_assert_contains('priority, booking_id, booking_at', $tickets, 'the number names its priority and booking');
	pg_assert_contains('pg_waiting_booking_find(', $tickets);
	pg_assert_contains("called_at = '\" . \$now . \"'\") ? 'moved_back'", $desk, 'the move time is kept');
	pg_assert_contains("\$qu_action === 'on_way'", $page);
	pg_assert_contains('pg_waiting_join_input($_POST, $qu_place, false, $qu_bookings)', $page, 'the public page asks no priority');
	pg_assert_contains('submit_priority', $screen);
	pg_assert_contains("AND on_way_at = 0", $module, 'said once');
	pg_assert_contains("AND state = 'waiting'\", array(\$key", $module, 'priority only while waiting');
}
