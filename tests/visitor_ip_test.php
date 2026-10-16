<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/visitor_ip.php: the period as typed
 * into the settings, where a pass over the visits starts, and the statement
 * that takes the address out of one window of them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Empty and 0 keep the address with the record; a whole number within the
// bounds is a period.
function test_visitor_ip_days_from_input_accepts_periods()
{
	pg_assert_same(0, pg_visitor_ip_days_from_input(''));
	pg_assert_same(0, pg_visitor_ip_days_from_input('0'));
	pg_assert_same(30, pg_visitor_ip_days_from_input(' 30 '));
	pg_assert_same(PG_VISITOR_IP_MIN_DAYS, pg_visitor_ip_days_from_input((string) PG_VISITOR_IP_MIN_DAYS));
	pg_assert_same(PG_VISITOR_IP_MAX_DAYS, pg_visitor_ip_days_from_input((string) PG_VISITOR_IP_MAX_DAYS));
}

// Anything else is refused, a list included, rather than read as a number.
function test_visitor_ip_days_from_input_refuses_the_rest()
{
	pg_assert_same(null, pg_visitor_ip_days_from_input((string) (PG_VISITOR_IP_MAX_DAYS + 1)), 'above the maximum');
	pg_assert_same(null, pg_visitor_ip_days_from_input('-1'), 'negative');
	pg_assert_same(null, pg_visitor_ip_days_from_input('1.5'), 'fraction');
	pg_assert_same(null, pg_visitor_ip_days_from_input('00'), 'zero spelled twice');
	pg_assert_same(null, pg_visitor_ip_days_from_input(array('30')), 'a list');
	pg_assert_same(null, pg_visitor_retention_days_from_input(array('90')), 'a list for the retention period');
}

// Even the shortest period ends at a midnight at least a day back, clear of
// the 30 minutes the duplicate visit check looks at.
function test_visitor_ip_shortest_period_is_a_day_back()
{
	$now = mktime(0, 10, 0, 10, 16, 2026);

	pg_assert_true(pg_visitor_retention_cutoff(PG_VISITOR_IP_MIN_DAYS, $now) <= ($now - 86400), 'a day back at ten past midnight');
}

function pg_test_visitor_ip_last($changes = array())
{
	return array_merge(array(
		'cutoff' => 0,
		'cursor' => 0,
		'from' => 0,
		'done' => false,
		'cleared' => 0,
		'finished_at' => 0,
	), $changes);
}

// Without a marker the first pass starts at the beginning of the table.
function test_visitor_ip_pass_plan_first_pass()
{
	pg_assert_same(array('cursor' => 0, 'cleared' => 0), pg_visitor_ip_pass_plan(pg_test_visitor_ip_last(), 1000, 5000));
}

// A pass that is through for this boundary is not run again; the next
// boundary starts where it said.
function test_visitor_ip_pass_plan_after_a_pass()
{
	$last = pg_test_visitor_ip_last(array('cutoff' => 1000, 'cursor' => 4000, 'from' => 3500, 'done' => true, 'cleared' => 70));

	pg_assert_same(null, pg_visitor_ip_pass_plan($last, 1000, 5000), 'same boundary');
	pg_assert_same(array('cursor' => 3500, 'cleared' => 0), pg_visitor_ip_pass_plan($last, 1000 + 86400, 5000), 'next boundary');
}

// A pass that ran out of time goes on from where it got, with what it took
// out so far, also when the boundary moved meanwhile.
function test_visitor_ip_pass_plan_resumes()
{
	$last = pg_test_visitor_ip_last(array('cutoff' => 1000, 'cursor' => 2500, 'from' => 100, 'done' => false, 'cleared' => 40));

	pg_assert_same(array('cursor' => 2500, 'cleared' => 40), pg_visitor_ip_pass_plan($last, 1000, 5000), 'same boundary');
	pg_assert_same(array('cursor' => 2500, 'cleared' => 40), pg_visitor_ip_pass_plan($last, 1000 + 86400, 5000), 'moved boundary');
}

// A table that numbers its visits from 1 again (TRUNCATE, a rebuild) gets a
// pass from the beginning instead of one that passes over every visit, also
// on the day whose pass was through.
function test_visitor_ip_pass_plan_restarts_past_the_last_id()
{
	$last = pg_test_visitor_ip_last(array('cutoff' => 1000, 'cursor' => 900000, 'from' => 880000, 'done' => true));

	pg_assert_same(array('cursor' => 0, 'cleared' => 0), pg_visitor_ip_pass_plan($last, 1000 + 86400, 1200), 'next boundary');
	pg_assert_same(array('cursor' => 0, 'cleared' => 0), pg_visitor_ip_pass_plan($last, 1000, 1200), 'same boundary');
	pg_assert_same(array('cursor' => 0, 'cleared' => 0), pg_visitor_ip_pass_plan($last, 1000, 0), 'empty table');

	$last = pg_test_visitor_ip_last(array('cutoff' => 1000, 'cursor' => 2500, 'from' => 0, 'done' => false, 'cleared' => 40));

	pg_assert_same(array('cursor' => 0, 'cleared' => 0), pg_visitor_ip_pass_plan($last, 1000, 2000), 'a pass under way');
}

// A pass from the beginning that found nothing is not run again every hour
// on an empty table.
function test_visitor_ip_pass_plan_empty_table_settles()
{
	$last = pg_test_visitor_ip_last(array('cutoff' => 1000, 'cursor' => 0, 'from' => 0, 'done' => true));

	pg_assert_same(null, pg_visitor_ip_pass_plan($last, 1000, 0));
}

// One window of the primary key, before the boundary, only rows that still
// carry an address, and a visit still going on at the boundary only once it
// started more than the grace period before it.
function test_visitor_ip_clear_sql()
{
	$sql = preg_replace('/\s+/', ' ', pg_visitor_ip_clear_sql(100, 5100, 1760000000));

	pg_assert_contains('UPDATE visitors SET ip_address = 0', $sql);
	pg_assert_contains('(id > 100) AND (id <= 5100)', $sql);
	pg_assert_contains('(start_timestamp < 1760000000)', $sql);
	pg_assert_contains('((stop_timestamp < 1760000000) OR (start_timestamp < ' . (1760000000 - PG_VISITOR_IP_GRACE_SECONDS) . '))', $sql);
	pg_assert_contains('(ip_address <> 0)', $sql);
	pg_assert_false(stripos($sql, 'ORDER BY') !== false, 'no ORDER BY on the statement itself');
}
