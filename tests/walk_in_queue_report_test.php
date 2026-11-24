<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the walk-in queue's report (includes/fn/walk_in_queue_report.php):
 * the period and target bounds, the days of a period, the fold of numbers
 * into counts and times per desk, staff member and service, the median and
 * the slowest tenth, the Erlang C wait and the desks needed, the staffing
 * line and the typed Excel rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/walk_in_queue_report.php';

/**
 * A number as the report reads it.
 */
function _walk_in_queue_report_ticket($fields)
{
	return array_merge(array('service' => 's1', 'state' => 'served', 'booking_at' => 0, 'desk_id' => 0, 'staff_user_id' => 0, 'joined_at' => 0, 'called_at' => 0, 'done_at' => 0, 'day' => 20261123), $fields);
}

function test_walk_in_queue_report_period_and_target_are_bounded()
{
	pg_assert_same(7, pg_waiting_report_period('7'));
	pg_assert_same(90, pg_waiting_report_period(90));
	pg_assert_same(30, pg_waiting_report_period('14'), 'a period not offered');
	pg_assert_same(30, pg_waiting_report_period(array()));
	pg_assert_same(15, pg_waiting_report_target('15'));
	pg_assert_same(10, pg_waiting_report_target('0'));
	pg_assert_same(10, pg_waiting_report_target('x'));
	pg_assert_same(120, pg_waiting_report_target(500));
}

function test_walk_in_queue_report_range_counts_each_weekday()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Istanbul');
	// Tuesday 2026-11-24 12:00 in Istanbul.
	$range = pg_waiting_report_range(7, 1795510800);
	pg_assert_same(20261118, $range['from']);
	pg_assert_same(20261124, $range['to']);
	pg_assert_same(array(1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1, 7 => 1), $range['weekdays']);

	$range = pg_waiting_report_range(30, 1795510800);
	pg_assert_same(20261026, $range['from']);
	pg_assert_same(5, $range['weekdays'][1], 'five Mondays in 30 days ending on a Tuesday');
	pg_assert_same(4, $range['weekdays'][3]);
	pg_assert_same(30, array_sum($range['weekdays']));
	date_default_timezone_set($zone);
}

function test_walk_in_queue_report_slowest_tenth_is_the_nearest_rank()
{
	pg_assert_same(null, pg_waiting_report_slowest_tenth(array()));
	pg_assert_same(5, pg_waiting_report_slowest_tenth(array(5)));
	pg_assert_same(90, pg_waiting_report_slowest_tenth(range(100, 1, -1)), 'order does not matter');
	pg_assert_same(9, pg_waiting_report_slowest_tenth(range(1, 10)));
	pg_assert_same(18, pg_waiting_report_slowest_tenth(range(1, 20)));
}

function test_walk_in_queue_report_fold_counts_and_times_per_desk_staff_and_service()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('UTC');
	$acc = pg_waiting_report_new();
	// Monday 2026-11-23 09:00 UTC.
	$nine = 1795424400;
	$tickets = array(
		array('desk_id' => 1, 'staff_user_id' => 7, 'joined_at' => $nine, 'called_at' => $nine + 300, 'done_at' => $nine + 900),
		array('desk_id' => 1, 'staff_user_id' => 7, 'joined_at' => $nine + 60, 'called_at' => $nine + 1500, 'done_at' => $nine + 1800),
		array('desk_id' => 2, 'staff_user_id' => 8, 'service' => 's2', 'state' => 'no_show', 'joined_at' => $nine + 3600, 'called_at' => $nine + 3700, 'done_at' => $nine + 3900),
		array('state' => 'left', 'joined_at' => $nine + 3700),
		array('state' => 'waiting', 'joined_at' => $nine + 3800),
		// A booking at 10:30 that came at 10:25 waits from its booked time.
		array('desk_id' => 2, 'staff_user_id' => 8, 'booking_at' => $nine + 5400, 'joined_at' => $nine + 5100, 'called_at' => $nine + 5520, 'done_at' => $nine + 6120),
	);

	foreach ($tickets as $ticket) {
		pg_waiting_report_add($acc, _walk_in_queue_report_ticket($ticket), 600);
	}

	$total = pg_waiting_report_figures($acc['total']);
	pg_assert_same(6, $total['taken']);
	pg_assert_same(3, $total['served']);
	pg_assert_same(1, $total['left']);
	pg_assert_same(1, $total['no_show']);
	pg_assert_same(array(300, 1440, 100, 120), $acc['total']['wait'], 'the booking waits 120 s from 10:30');
	pg_assert_same(array(600, 300, 600), $acc['total']['handle'], 'only served numbers are handled');
	pg_assert_same(210, $total['wait']);
	pg_assert_same(1440, $total['wait_slow']);
	pg_assert_same(600, $total['handle']);

	pg_assert_same(array(1, 2), array_keys($acc['desk']));
	pg_assert_same(2, $acc['desk'][1]['served']);
	pg_assert_same(1, $acc['staff'][8]['no_show']);
	pg_assert_same(5, $acc['service']['s1']['taken']);
	pg_assert_same(1, $acc['service']['s2']['taken']);

	pg_assert_same(array(9 => 2, 10 => 4), $acc['arrive'][1], 'arrivals by weekday and hour');
	pg_assert_same(array('20261123:1'), array_keys($acc['worked'][1][9]));
	pg_assert_same(array('20261123:2'), array_keys($acc['worked'][1][10]));
	pg_assert_same(range(9, 10), pg_waiting_report_hours($acc));
	date_default_timezone_set($zone);
}

function test_walk_in_queue_report_late_booking_waits_from_its_arrival()
{
	$ticket = array('booking_at' => 1000, 'joined_at' => 2000);
	pg_assert_same(2000, pg_waiting_report_wait_start($ticket, 600), 'past the grace window');
	pg_assert_same(1000, pg_waiting_report_wait_start($ticket, 1200), 'within the grace window');
	pg_assert_same(2000, pg_waiting_report_wait_start(array('joined_at' => 2000, 'moved_back' => 1, 'called_at' => 3000), 0), 'a move back keeps the first arrival');
}

function test_walk_in_queue_report_erlang_wait_and_desks_needed()
{
	pg_assert_same(null, pg_waiting_report_erlang_wait(2, 2.0, 600), 'as many desks as the traffic never keep up');
	pg_assert_same(0.0, pg_waiting_report_erlang_wait(1, 0, 600));
	// One desk, half busy: M/M/1 waits rho / (1 - rho) * h = h.
	pg_assert_same(600, (int) round(pg_waiting_report_erlang_wait(1, 0.5, 600)));
	// Two desks, traffic 1: Erlang C 1/3, wait 1/3 * h / 1.
	pg_assert_same(200, (int) round(pg_waiting_report_erlang_wait(2, 1.0, 600)));

	pg_assert_same(0, pg_waiting_report_desks_needed(0, 600, 600));
	pg_assert_same(0, pg_waiting_report_desks_needed(5, 0, 600));
	// 3 an hour at 10 minutes: traffic 0.5, one desk waits 10 minutes.
	pg_assert_same(1, pg_waiting_report_desks_needed(3, 600, 600));
	pg_assert_same(2, pg_waiting_report_desks_needed(3, 600, 300));
	// 30 an hour at 10 minutes: traffic 5, at least 6 desks.
	pg_assert_true(pg_waiting_report_desks_needed(30, 600, 600) >= 6);
	pg_assert_true(pg_waiting_report_desks_needed(30, 600, 60) > pg_waiting_report_desks_needed(30, 600, 600), 'a tighter target needs more desks');
	pg_assert_same(PG_WAITING_REPORT_DESKS_MAX, pg_waiting_report_desks_needed(100000, 600, 60));
}

function test_walk_in_queue_report_staffing_averages_over_the_weekdays()
{
	$acc = pg_waiting_report_new();
	$acc['arrive'][1][9] = 12;
	$acc['worked'][1][9] = array('20261116:1' => true, '20261123:1' => true, '20261123:2' => true);
	$weekdays = array(1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2, 6 => 2, 7 => 2);

	pg_assert_same(array(), pg_waiting_report_staffing($acc, $weekdays, null, 10), 'no handling time, no line');

	$line = pg_waiting_report_staffing($acc, $weekdays, 600, 10);
	pg_assert_same(6.0, $line[1][9]['arrivals']);
	pg_assert_same(1.5, $line[1][9]['worked']);
	pg_assert_same(pg_waiting_report_desks_needed(6, 600, 600), $line[1][9]['needed']);
	pg_assert_same(0, $line[2][9]['needed']);

	pg_assert_same(600, pg_waiting_report_handling($acc, array(array('minutes' => 5), array('minutes' => 15))), 'the services\' starting minutes before 20 served');
	$acc['total']['handle'] = array_fill(0, 20, 240);
	pg_assert_same(240, pg_waiting_report_handling($acc, array(array('minutes' => 5))));
}

function test_walk_in_queue_report_lines_are_named_and_sorted()
{
	$groups = array(3 => pg_waiting_report_group_new(), 5 => pg_waiting_report_group_new());
	$groups[5]['taken'] = 4;
	$groups[3]['taken'] = 2;
	$groups[3]['wait'] = array(60, 180);
	$lines = pg_waiting_report_lines($groups, array(3 => 'Desk 1'));

	pg_assert_same('#5', $lines[0]['name'], 'a removed desk by its id');
	pg_assert_same('Desk 1', $lines[1]['name']);
	pg_assert_same(array('Desk 1', 2, 0, 0, 0, 2.0, 3.0, '', ''), pg_waiting_report_line_cells($lines[1]));
}

function test_walk_in_queue_report_sheet_is_typed_and_never_a_formula()
{
	$sheet = pg_waiting_report_sheet(array('Desk', 'Taken'), array(array('=SUM(A1)', 3), array('Desk 2', '')));

	pg_assert_same(array(1, 2, 3), array_keys($sheet));
	pg_assert_same(array('t' => 's', 'v' => '=SUM(A1)'), $sheet[2][0]);
	pg_assert_same(array('t' => 'n', 'v' => '3'), $sheet[2][1]);
	pg_assert_same(null, $sheet[3][1]);

	list($head, $rows) = pg_waiting_report_grid(array(8, 9), array(1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'), function ($weekday, $hour) {
		return $weekday * 100 + $hour;
	});
	pg_assert_same(array('', '08', '09'), $head);
	pg_assert_same(array('Tue', 208, 209), $rows[1]);
	pg_assert_same(7, count($rows));
}

function test_walk_in_queue_report_place_tabs_mark_the_screen_shown()
{
	$html = pg_waiting_place_tabs(4, 'report', 2);

	pg_assert_contains('class="pg-seg-item is-active" aria-current="page" href="queue_report.php?place=4"', $html);
	pg_assert_contains('href="edit_queue_place.php?id=4&amp;view=desks"', $html);
	pg_assert_contains('<span class="pg-seg-count">2</span>', $html);
	pg_assert_same(1, substr_count($html, 'is-active'));
}
