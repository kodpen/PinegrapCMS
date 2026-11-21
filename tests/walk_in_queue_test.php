<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the walk-in queue's pure rules (includes/fn/walk_in_queue.php):
 * the order (priority, a booking within the grace window), a number's letter
 * and count, the cap and the closing rule, the estimate, the not-here timer
 * and the signed link of a number.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _walk_in_queue_test_ticket($id, $joined_at, $overrides = array())
{
	return $overrides + array(
		'id'         => $id,
		'service'    => 'a',
		'joined_at'  => $joined_at,
		'priority'   => '',
		'booking_at' => 0,
	);
}

function test_walk_in_queue_oldest_first_within_a_service()
{
	$tickets = array(
		_walk_in_queue_test_ticket(3, 1300),
		_walk_in_queue_test_ticket(1, 1100),
		_walk_in_queue_test_ticket(2, 1200),
		_walk_in_queue_test_ticket(4, 1200),
	);

	pg_assert_same(array(1, 2, 4, 3), array_column(pg_waiting_order($tickets, 600), 'id'), 'joined time, then id');
}

function test_walk_in_queue_priority_person_goes_first()
{
	$tickets = array(
		_walk_in_queue_test_ticket(1, 1000),
		_walk_in_queue_test_ticket(2, 1100),
		_walk_in_queue_test_ticket(3, 1500, array('priority' => 'elderly')),
		_walk_in_queue_test_ticket(4, 1400, array('priority' => 'pregnant')),
	);

	pg_assert_same(array(4, 3, 1, 2), array_column(pg_waiting_order($tickets, 600), 'id'), 'priority people first, oldest of them first');
}

function test_walk_in_queue_booking_within_grace_goes_in_at_its_time()
{
	$booked = 10000;

	// Ten minutes late, the grace window fifteen: in at the booked time.
	$tickets = array(
		_walk_in_queue_test_ticket(1, $booked - 300),
		_walk_in_queue_test_ticket(2, $booked + 300),
		_walk_in_queue_test_ticket(3, $booked + 600, array('booking_at' => $booked)),
	);

	pg_assert_same(array(1, 3, 2), array_column(pg_waiting_order($tickets, 900), 'id'), 'within the grace window');
	pg_assert_same($booked, pg_waiting_order_time($tickets[2], 900));

	// The same arrival with a five-minute grace window: a walk-in.
	pg_assert_same(array(1, 2, 3), array_column(pg_waiting_order($tickets, 300), 'id'), 'past the grace window it is a walk-in');
	pg_assert_same($booked + 600, pg_waiting_order_time($tickets[2], 300));

	// Early for a booking: not in before its time.
	$early = _walk_in_queue_test_ticket(5, $booked - 1200, array('booking_at' => $booked));
	pg_assert_same($booked, pg_waiting_order_time($early, 300));

	// A priority person still goes before a booking.
	$tickets[] = _walk_in_queue_test_ticket(6, $booked + 700, array('priority' => 'disabled'));
	pg_assert_same(array(6, 1, 3, 2), array_column(pg_waiting_order($tickets, 900), 'id'));
}

function test_walk_in_queue_next_for_a_desk_and_ahead()
{
	$tickets = array(
		_walk_in_queue_test_ticket(1, 1000, array('service' => 'b')),
		_walk_in_queue_test_ticket(2, 1100),
		_walk_in_queue_test_ticket(3, 1200),
		_walk_in_queue_test_ticket(4, 1300, array('service' => 'b', 'priority' => 'elderly')),
	);

	pg_assert_same(2, pg_waiting_next($tickets, array('a'), 600)['id'], 'the desk serves a only');
	pg_assert_same(4, pg_waiting_next($tickets, array('a', 'b'), 600)['id'], 'priority across the desk\'s services');
	pg_assert_same(null, pg_waiting_next($tickets, array('c'), 600), 'no one for this desk');

	pg_assert_same(0, pg_waiting_ahead($tickets, 2, 600));
	pg_assert_same(1, pg_waiting_ahead($tickets, 3, 600), 'only the same service counts');
	pg_assert_same(1, pg_waiting_ahead($tickets, 1, 600), 'the priority person of b is ahead');
	pg_assert_same(null, pg_waiting_ahead($tickets, 99, 600));
}

function test_walk_in_queue_number_letter_and_count()
{
	pg_assert_same('A', pg_waiting_letter_clean('a'));
	pg_assert_same('K', pg_waiting_letter_clean(' K '));
	pg_assert_same('', pg_waiting_letter_clean('AB'));
	pg_assert_same('', pg_waiting_letter_clean('Ç'));
	pg_assert_same('', pg_waiting_letter_clean('1'));

	pg_assert_same('A-001', pg_waiting_number('A', 1));
	pg_assert_same('A-012', pg_waiting_number('a', 12));
	pg_assert_same('B-1000', pg_waiting_number('B', 1000), 'past 999 the count keeps going');
	pg_assert_same('', pg_waiting_number('', 3));
	pg_assert_same('', pg_waiting_number('A', 0));

	pg_assert_same(1, pg_waiting_next_count(0), 'the first number of the day');
	pg_assert_same(13, pg_waiting_next_count(12));
}

function test_walk_in_queue_cap_and_closing()
{
	$now = 100000;
	$closes = $now + 3600;

	pg_assert_same('', pg_waiting_join_refusal(10, 50, 1800, $now, $closes), 'room and time left');
	pg_assert_same('cap', pg_waiting_join_refusal(50, 50, 1800, $now, $closes), 'the daily cap is reached');
	pg_assert_same('', pg_waiting_join_refusal(500, 0, 1800, $now, $closes), 'no cap set');
	pg_assert_same('closed', pg_waiting_join_refusal(0, 0, 0, $now, 0), 'outside the hours');
	pg_assert_same('closed', pg_waiting_join_refusal(0, 0, 0, $closes, $closes), 'at the closing moment');

	// A number whose estimate passes the closing time is not given.
	pg_assert_same('closing', pg_waiting_join_refusal(10, 50, 3700, $now, $closes), 'after the closing estimate');
	pg_assert_same('', pg_waiting_join_refusal(10, 50, 3600, $now, $closes), 'served at the closing moment is still served');
	pg_assert_same('', pg_waiting_join_refusal(10, 50, null, $now, $closes), 'no estimate: only the hours count');
}

function test_walk_in_queue_closes_at_from_hours()
{
	$zone = new DateTimeZone('Europe/Istanbul');
	$hours = '{"1":[["09:00","12:00"],["13:00","17:00"]],"6":[["10:00","14:00"]]}';
	// 2026-11-23 is a Monday.
	$at = function ($text) use ($zone) {
		return (new DateTime($text, $zone))->getTimestamp();
	};

	pg_assert_same($at('2026-11-23 12:00'), pg_waiting_closes_at($hours, array(), $at('2026-11-23 10:30'), $zone));
	pg_assert_same(0, pg_waiting_closes_at($hours, array(), $at('2026-11-23 12:30'), $zone), 'the lunch gap');
	pg_assert_same($at('2026-11-23 17:00'), pg_waiting_closes_at($hours, array(), $at('2026-11-23 13:00'), $zone));
	pg_assert_same(0, pg_waiting_closes_at($hours, array(), $at('2026-11-23 17:00'), $zone), 'closed at the end');
	pg_assert_same(0, pg_waiting_closes_at($hours, array(), $at('2026-11-24 10:00'), $zone), 'a day off');
	pg_assert_same(0, pg_waiting_closes_at($hours, array('2026-11-23' => array()), $at('2026-11-23 10:30'), $zone), 'a dated day off');
	pg_assert_same($at('2026-11-24 11:00'), pg_waiting_closes_at($hours, array('2026-11-24' => array(array('09:00', '11:00'))), $at('2026-11-24 10:00'), $zone), 'dated hours');
}

function test_walk_in_queue_handling_time_needs_twenty_handled()
{
	$nineteen = array_fill(0, 19, 600);
	$twenty = array_merge($nineteen, array(60));

	pg_assert_same(900, pg_waiting_handling_seconds($nineteen, 15), '19 handled: the length the place gives');
	pg_assert_same(null, pg_waiting_handling_seconds($nineteen, 0), '19 handled and no length: only N ahead');
	pg_assert_same(600, pg_waiting_handling_seconds($twenty, 15), '20 handled: the median');

	pg_assert_same(450, pg_waiting_median(array(600, 300, 900, 100)), 'even count: the middle two');
	pg_assert_same(300, pg_waiting_median(array(900, 300, 100)));
	pg_assert_same(null, pg_waiting_median(array()));
}

function test_walk_in_queue_estimate_with_two_desks()
{
	$desks = array(
		array('state' => 'open', 'services' => array('a')),
		array('state' => 'open', 'services' => array('a', 'b')),
		array('state' => 'paused', 'services' => array('a')),
		array('state' => 'closed', 'services' => array('a')),
	);

	pg_assert_same(2, pg_waiting_open_desks($desks, 'a'), 'only open desks');
	pg_assert_same(1, pg_waiting_open_desks($desks, 'b'));
	pg_assert_same(0, pg_waiting_open_desks($desks, 'c'));

	// Three ahead, ten minutes each, two desks: fifteen minutes.
	pg_assert_same(900, pg_waiting_estimate_seconds(3, 600, 2));
	pg_assert_same(null, pg_waiting_estimate_seconds(3, 600, 0), 'no open desk: no estimate');
	pg_assert_same(null, pg_waiting_estimate_seconds(3, null, 2), 'no handling time: no estimate');
	pg_assert_same(0, pg_waiting_estimate_seconds(0, 600, 2));

	pg_assert_same(15, pg_waiting_estimate_minutes(900));
	pg_assert_same(5, pg_waiting_estimate_minutes(60), 'never below five while someone is ahead');
	pg_assert_same(10, pg_waiting_estimate_minutes(451), 'to the nearest five');
	pg_assert_same(5, pg_waiting_estimate_minutes(449));
	pg_assert_same(0, pg_waiting_estimate_minutes(0));
	pg_assert_same(null, pg_waiting_estimate_minutes(null));
}

function test_walk_in_queue_not_here_timer()
{
	pg_assert_false(pg_waiting_not_here_allowed(1000, 1000 + 179, 3), 'before M minutes');
	pg_assert_true(pg_waiting_not_here_allowed(1000, 1000 + 180, 3), 'at M minutes');
	pg_assert_true(pg_waiting_not_here_allowed(1000, 1000, 0), 'no wait set');
	pg_assert_false(pg_waiting_not_here_allowed(0, 5000, 3), 'not called');

	pg_assert_same('left', pg_waiting_not_here_outcome(0, false), 'moving back is off by default');
	pg_assert_same('moved_back', pg_waiting_not_here_outcome(0, true), 'the first time, moved back');
	pg_assert_same('left', pg_waiting_not_here_outcome(1, true), 'the second time they leave');
}

function test_walk_in_queue_signed_link_token()
{
	$key = str_repeat('k', 32);
	$token = pg_waiting_ticket_token(42, 1700000000, $key);

	pg_assert_true(preg_match('/^[0-9a-f]{64}$/', $token) === 1);
	pg_assert_same($token, pg_waiting_ticket_token(42, 1700000000, $key), 'worked out again');
	pg_assert_true($token !== pg_waiting_ticket_token(43, 1700000000, $key), 'another number');
	pg_assert_true($token !== pg_waiting_ticket_token(42, 1700000001, $key), 'another join time');
	pg_assert_true($token !== pg_waiting_ticket_token(42, 1700000000, str_repeat('x', 32)), 'another site');
	pg_assert_true($token !== pg_booking_manage_token(42, 1700000000, $key), 'not a booking\'s manage token');
	pg_assert_same('', pg_waiting_ticket_token(0, 1700000000, $key));
	pg_assert_same('', pg_waiting_ticket_token(42, 1700000000, ''));

	$stored = hash('sha256', $token);
	pg_assert_true(pg_waiting_ticket_token_valid($token, $stored));
	pg_assert_false(pg_waiting_ticket_token_valid(strtoupper($token), $stored), 'hex lower case only');
	pg_assert_false(pg_waiting_ticket_token_valid($token, ''), 'a cleared number opens nothing');
	pg_assert_false(pg_waiting_ticket_token_valid(pg_waiting_ticket_token(43, 1700000000, $key), $stored));
}
