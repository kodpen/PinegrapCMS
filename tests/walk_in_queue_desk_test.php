<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the walk-in queue's desk (includes/fn/walk_in_queue_desk.php):
 * a place's staff list, who reaches which place's desk, which buttons the
 * desk offers, a waiting row's pills, the minutes shown, the services a
 * number may be sent to and the wiring of the conditional writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_walk_in_queue_desk_staff_list_is_clean_ids()
{
	pg_assert_same(array(7, 3), pg_waiting_place_staff(array('staff' => array('7', 3, 7, 0, -2, 'x'))));
	pg_assert_same(array(), pg_waiting_place_staff(array('staff' => 'not a list')));
	pg_assert_same(array(), pg_waiting_place_staff(array()));
}

function test_walk_in_queue_desk_scope_is_every_place_for_managers_and_named_places_for_staff()
{
	$places = array(
		array('id' => 4, 'settings' => array('staff' => array(9, 12))),
		array('id' => 5, 'settings' => array('staff' => array(12))),
		array('id' => 6, 'settings' => array()),
	);

	pg_assert_same(null, pg_waiting_desk_scope(0, 1, $places));
	pg_assert_same(null, pg_waiting_desk_scope(2, 1, $places));
	pg_assert_same(array(4, 5), pg_waiting_desk_scope(3, 12, $places));
	pg_assert_same(array(4), pg_waiting_desk_scope(3, 9, $places));
	pg_assert_same(false, pg_waiting_desk_scope(3, 40, $places), 'a user named nowhere');
	pg_assert_same(false, pg_waiting_desk_scope(4, 12, $places), 'no role past 3');

	pg_assert_true(pg_waiting_desk_scope_allows(null, 6));
	pg_assert_true(pg_waiting_desk_scope_allows(array(4, 5), 5));
	pg_assert_same(false, pg_waiting_desk_scope_allows(array(4, 5), 6));
	pg_assert_same(false, pg_waiting_desk_scope_allows(false, 4));
}

function test_walk_in_queue_desk_buttons_follow_the_desk_and_the_number_at_it()
{
	$now = 1000000;

	// An open desk, nobody at it: only Call next (with someone waiting),
	// Pause and Close.
	$free = pg_waiting_desk_buttons('open', null, $now, 3, 2);
	pg_assert_same(array('call_next' => true, 'call_again' => false, 'not_here' => false, 'transfer' => false, 'finish' => false, 'pause' => true, 'resume' => false, 'close' => true), $free);
	pg_assert_same(false, pg_waiting_desk_buttons('open', null, $now, 3, 0)['call_next'], 'nobody waiting');

	// A number called a minute ago: Not here waits for the place's minutes,
	// Call next and Close wait for the number to be ended.
	$called = array('called_at' => $now - 60);
	$busy = pg_waiting_desk_buttons('open', $called, $now, 3, 2);
	pg_assert_same(false, $busy['call_next']);
	pg_assert_same(false, $busy['not_here']);
	pg_assert_same(false, $busy['close']);
	pg_assert_true($busy['call_again'] && $busy['transfer'] && $busy['finish']);
	pg_assert_true(pg_waiting_desk_buttons('open', array('called_at' => $now - 180), $now, 3, 2)['not_here']);

	// A paused desk calls nobody new; it may resume or close.
	$paused = pg_waiting_desk_buttons('paused', null, $now, 3, 5);
	pg_assert_same(false, $paused['call_next']);
	pg_assert_same(false, $paused['pause']);
	pg_assert_true($paused['resume'] && $paused['close']);

	pg_assert_same(array_fill_keys(array_keys($free), false), pg_waiting_desk_buttons('closed', null, $now, 3, 5), 'a closed desk offers nothing');
}

function test_walk_in_queue_desk_pills_name_priority_booking_and_on_the_way()
{
	$groups = array(array('key' => 'p1', 'name' => 'Elderly'), array('key' => 'p2', 'name' => 'Pregnant'));

	pg_assert_same(array(), pg_waiting_desk_pills(array('priority' => '', 'booking_id' => 0, 'on_way_at' => 0), $groups));
	pg_assert_same(array(
		array('priority', 'Pregnant'),
		array('booked', ''),
		array('on_way', ''),
	), pg_waiting_desk_pills(array('priority' => 'p2', 'booking_id' => 31, 'on_way_at' => 5), $groups));
	pg_assert_same(array(array('priority', '')), pg_waiting_desk_pills(array('priority' => 'p9', 'booking_id' => 0, 'on_way_at' => 0), $groups), 'a removed group still goes first');
}

function test_walk_in_queue_desk_minutes_since_never_goes_below_zero()
{
	pg_assert_same(0, pg_waiting_minutes_since(1000, 1059));
	pg_assert_same(1, pg_waiting_minutes_since(1000, 1060));
	pg_assert_same(12, pg_waiting_minutes_since(1000, 1000 + 12 * 60 + 30));
	pg_assert_same(0, pg_waiting_minutes_since(2000, 1000), 'a clock behind');
	pg_assert_same(0, pg_waiting_minutes_since(0, 1000), 'no time');
}

function test_walk_in_queue_desk_transfer_offers_the_other_services()
{
	$services = array(
		array('key' => 's1', 'name' => 'Teller', 'letter' => 'A', 'minutes' => 6),
		array('key' => 's2', 'name' => 'Loans', 'letter' => 'B', 'minutes' => 20),
		array('key' => 's3', 'name' => 'Cards', 'letter' => 'C', 'minutes' => 5),
	);

	pg_assert_same(array('s2', 's3'), array_column(pg_waiting_transfer_services($services, 's1'), 'key'));
	pg_assert_same(array(), pg_waiting_transfer_services(array($services[0]), 's1'));
}

function test_walk_in_queue_desk_wiring()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/walk_in_queue_desk.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/queue_desk.php');

	// Two desks never call one person: the call takes the row only while it waits.
	pg_assert_contains("SET state = 'called'", $module);
	pg_assert_contains("AND state = 'waiting'", $module);
	pg_assert_contains("WHERE id = ? AND state = 'closed'", $module, 'a desk opens only while closed');
	pg_assert_contains('validate_token_field()', $screen, 'a write carries the form token');
	pg_assert_contains('pg_waiting_desk_scope_allows($qd_scope', $screen, 'staff reach only their places');
	pg_assert_contains('Pinegrap - Enterprise Website Platform', $screen);
}

function test_walk_in_queue_desk_call_lock_is_per_desk_and_database()
{
	$name = pg_waiting_desk_lock_name(12);

	pg_assert_contains('_12', $name, 'the lock names the desk');
	pg_assert_true($name !== pg_waiting_desk_lock_name(13), 'each desk has its own lock');
	pg_assert_true(strlen($name) <= 64, 'GET_LOCK takes at most 64 characters');

	// Two clicks of Call next at one desk ran side by side, both found the
	// desk free and each called a person: the call holds the desk's lock and
	// looks at the desk again inside it.
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/walk_in_queue_desk.php');
	$call = substr($module, strpos($module, 'function pg_waiting_call_next('));
	$call = substr($call, 0, strpos($call, "\n}\n"));
	pg_assert_contains('GET_LOCK', $call);
	pg_assert_contains('RELEASE_LOCK', $call);
	pg_assert_true(strpos($call, 'GET_LOCK') < strpos($call, 'pg_waiting_call_next_locked('), 'the call runs inside the lock');
	pg_assert_false(strpos($call, 'pg_waiting_desk_current(') !== false, 'the desk is not looked at before the lock');
	$locked = substr($module, strpos($module, 'function pg_waiting_call_next_locked('));
	pg_assert_contains('pg_waiting_desk_current(', substr($locked, 0, strpos($locked, "\n}\n")), 'the desk is looked at inside the lock');
}

// The role-3 ladder: a User-role person whose only access is a place's staff
// list reaches the panel (welcome screen, sign-in landing, the site's
// toolbar, the set and reset password mails) and so finds My desk.
function test_walk_in_queue_desk_staff_is_on_every_role_3_ladder()
{
	foreach (array('welcome.php', 'get_page.php', 'includes/fn/content.php', 'set_password.php') as $file) {
		pg_assert_contains("pg_waiting_user_is_staff(\$user)", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ' knows a desk\'s staff');
	}

	pg_assert_contains("pg_waiting_user_is_staff(array('id' => \$_POST['user_id'] ?? 0, 'role' => \$user_role))", (string) file_get_contents(PG_FUNCTIONS_DIR . '/reset_password.php'), 'reset_password.php names the panel login for a desk\'s staff');

	pg_assert_false(pg_waiting_user_is_staff(array('id' => 5, 'role' => 2)), 'a manager is no staff member: the role passes the ladder already');
	pg_assert_false(pg_waiting_user_is_staff(array('id' => 0, 'role' => 3)), 'no account');
}
