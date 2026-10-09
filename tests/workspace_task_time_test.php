<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the workspace's time spent on tasks
 * (includes/workspace/task_time.php: reading a typed duration, the timer's
 * minute, hours for an invoice line, the estimate bar, who may write time)
 * and of the links between tasks (includes/workspace/task_links.php: the
 * loop check, given a map of links instead of the database).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/task_time.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/task_links.php');

function test_task_time_parse_minutes_and_clock()
{
	pg_assert_same(90, ws_task_time_parse('90'));
	pg_assert_same(90, ws_task_time_parse(' 90 '));
	pg_assert_same(90, ws_task_time_parse('1:30'));
	pg_assert_same(605, ws_task_time_parse('10:05'));
	pg_assert_same(0, ws_task_time_parse('1:75'), 'minutes past 59');
}

// "d" is minutes here, never days.
function test_task_time_parse_units()
{
	pg_assert_same(90, ws_task_time_parse('1s 30d'));
	pg_assert_same(75, ws_task_time_parse('1s 15d'));
	pg_assert_same(90, ws_task_time_parse('1sa 30dk'));
	pg_assert_same(90, ws_task_time_parse('1h 30m'));
	pg_assert_same(90, ws_task_time_parse('1 saat 30 dakika'));
	pg_assert_same(120, ws_task_time_parse('2s'));
	pg_assert_same(45, ws_task_time_parse('45dk'));
	pg_assert_same(30, ws_task_time_parse('30d'));
	pg_assert_same(90, ws_task_time_parse('1,5s'));
	pg_assert_same(90, ws_task_time_parse('1.5h'));
	pg_assert_same(90, ws_task_time_parse('~1s 30d'));
	pg_assert_same(150, ws_task_time_parse('2S 30D'), 'upper case');
}

function test_task_time_parse_refuses_what_it_cannot_read()
{
	pg_assert_same(0, ws_task_time_parse(''));
	pg_assert_same(0, ws_task_time_parse('abc'));
	pg_assert_same(0, ws_task_time_parse('1g'), 'no day unit');
	pg_assert_same(0, ws_task_time_parse('1s abc'));
	pg_assert_same(0, ws_task_time_parse('-30'));
	pg_assert_same(0, ws_task_time_parse('1x 30d'));
}

// A timer stopped after two seconds keeps a minute; otherwise it rounds.
function test_task_time_elapsed_is_a_minute_at_least()
{
	pg_assert_same(1, ws_task_time_elapsed(1000, 1002));
	pg_assert_same(1, ws_task_time_elapsed(1000, 1000));
	pg_assert_same(1, ws_task_time_elapsed(1000, 900), 'a clock that went back');
	pg_assert_same(1, ws_task_time_elapsed(1000, 1089));
	pg_assert_same(2, ws_task_time_elapsed(1000, 1091));
	pg_assert_same(60, ws_task_time_elapsed(1000, 4600));
}

function test_task_time_hours_round_to_two_decimals()
{
	pg_assert_same(9.5, ws_task_time_hours(570));
	pg_assert_same(1.27, ws_task_time_hours(76));
	pg_assert_same(0.02, ws_task_time_hours(1));
	pg_assert_same(0.33, ws_task_time_hours(20));
	pg_assert_same(0.0, ws_task_time_hours(0));
}

// Hours times an hourly rate in kurus is the ERP's own line total.
function test_task_time_invoice_line_is_rounded_kurus()
{
	pg_assert_same(237500, erp_line_total(25000, ws_task_time_hours(570)));
	pg_assert_same(31750, erp_line_total(25000, ws_task_time_hours(76)));
	pg_assert_same(4125, erp_line_total(12500, ws_task_time_hours(20)), '0.33 x 125.00');
}

function test_task_time_share_against_estimate()
{
	pg_assert_same(null, ws_task_time_share(200, 0));
	pg_assert_same(array('percent' => 66, 'over' => false), ws_task_time_share(200, 300));
	pg_assert_same(array('percent' => 100, 'over' => true), ws_task_time_share(400, 300));
	pg_assert_same(array('percent' => 100, 'over' => false), ws_task_time_share(300, 300));
}

function test_task_time_may_log()
{
	$task = array('id' => 5, 'creator_id' => 2);
	$member = array('id' => 7, 'role' => 3, 'member' => true);
	$staff = array('id' => 1, 'role' => 2, 'member' => true);

	pg_assert_false(ws_task_time_may_log($member, $task, array(3)), 'not on the task');
	pg_assert_true(ws_task_time_may_log($member, $task, array(3, 7)), 'on the task');
	pg_assert_true(ws_task_time_may_log(array('id' => 2, 'role' => 3, 'member' => true), $task, array()), 'created it');
	pg_assert_false(ws_task_time_may_log($member, $task, array(7), 3), 'for somebody else');
	pg_assert_true(ws_task_time_may_log($staff, $task, array()), 'staff, own time');
	pg_assert_true(ws_task_time_may_log($staff, $task, array(), 7), 'staff, for somebody else');
	pg_assert_false(ws_task_time_may_log(array('id' => 7, 'role' => 3, 'member' => false), $task, array(7)), 'not in the team');
}

function test_task_link_cycle_refuses_self_and_loops()
{
	// 2 waits for 1, 3 waits for 2.
	$map = array(2 => array(1), 3 => array(2));

	pg_assert_same('self', ws_task_link_cycle($map, 4, 4));
	pg_assert_same('cycle', ws_task_link_cycle($map, 1, 2), '1 waiting for 2 closes 1-2');
	pg_assert_same('cycle', ws_task_link_cycle($map, 1, 3), '1 waiting for 3 closes 1-2-3');
	pg_assert_same('', ws_task_link_cycle($map, 3, 1), '3 waiting for 1 too is no loop');
	pg_assert_same('', ws_task_link_cycle($map, 4, 3));
	pg_assert_same('', ws_task_link_cycle(array(), 1, 2));
}

// A diamond (4 waits for 2 and 3, both wait for 1) has no loop, and the
// shared task is walked once.
function test_task_link_cycle_diamond()
{
	$map = array(4 => array(2, 3), 2 => array(1), 3 => array(1));

	pg_assert_same('', ws_task_link_cycle($map, 5, 4));
	pg_assert_same('cycle', ws_task_link_cycle($map, 1, 4));
}

function test_task_link_cycle_stops_at_the_depth()
{
	// 1 waits for 2, 2 for 3, ... 59 for 60.
	$map = array();

	for ($i = 1; $i < 60; $i++) {
		$map[$i] = array($i + 1);
	}

	pg_assert_same('too_deep', ws_task_link_cycle($map, 100, 1), 'a chain of 59 beyond 50 steps');
	pg_assert_same('', ws_task_link_cycle($map, 100, 1, 60));
	pg_assert_same('', ws_task_link_cycle($map, 100, 20), 'a chain of 40 within 50 steps');
	pg_assert_same('cycle', ws_task_link_cycle($map, 30, 5), '30 is 25 steps ahead of 5');
}
