<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/site_time_sql.php: the shifts that make
 * FROM_UNIXTIME() under the fixed offset init.php gives the MySQL session
 * read a timestamp in the site's own time, daylight saving time included,
 * and the SQL the visitor report groups and filters its days with.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A Europe/Berlin site on 30 Oct 2026: winter time, the session at +01:00.
function pg_test_site_time_berlin_now()
{
	return strtotime('2026-10-30 12:00:00 UTC');
}

// The shift that applies to one timestamp, read from the segments.
function pg_test_site_time_shift($segments, $timestamp)
{
	foreach ($segments as $segment) {
		if (($segment['until'] === null) || ($timestamp < $segment['until'])) {
			return $segment['shift'];
		}
	}

	return null;
}

// The day MySQL gives FROM_UNIXTIME(ts + shift) under a session offset.
function pg_test_site_time_mysql_day($timestamp, $shift, $session_offset)
{
	return gmdate('Y-m-d H:i', $timestamp + $shift + $session_offset);
}

// A visit at 00:20 summer time on 1 Oct reads as 1 Oct under the winter
// session; without the shift it read as 30 Sep 23:20.
function test_site_time_shifts_summer_rows_under_a_winter_session()
{
	$zone = 'Europe/Berlin';
	$now = pg_test_site_time_berlin_now();
	$from = strtotime('2026-09-30 00:00:00 Europe/Berlin');
	$to = strtotime('2026-10-30 23:59:59 Europe/Berlin');
	$visit = strtotime('2026-10-01 00:20:00 Europe/Berlin');
	$after = strtotime('2026-10-28 00:20:00 Europe/Berlin');

	$segments = pg_site_time_shifts($from, $to, $zone, $now);

	pg_assert_same(2, count($segments), 'one change inside the window');
	pg_assert_same('2026-09-30 23:20', pg_test_site_time_mysql_day($visit, 0, 3600), 'the plain reading is an hour early');
	pg_assert_same('2026-10-01 00:20', pg_test_site_time_mysql_day($visit, pg_test_site_time_shift($segments, $visit), 3600), 'summer visit');
	pg_assert_same('2026-10-28 00:20', pg_test_site_time_mysql_day($after, pg_test_site_time_shift($segments, $after), 3600), 'winter visit');
	pg_assert_same(strtotime('2026-10-25 01:00:00 UTC'), $segments[0]['until'], 'the change is at 01:00 UTC');
}

// The SQL wraps the column in one CASE over the change.
function test_site_time_sql_cases_over_a_change()
{
	$zone = 'Europe/Berlin';
	$now = pg_test_site_time_berlin_now();
	$from = strtotime('2026-09-30 00:00:00 Europe/Berlin');
	$to = strtotime('2026-10-30 23:59:59 Europe/Berlin');
	$change = strtotime('2026-10-25 01:00:00 UTC');

	pg_assert_same(
		"FROM_UNIXTIME(CASE WHEN visitors.start_timestamp < " . $change . " THEN visitors.start_timestamp + 3600 ELSE visitors.start_timestamp END, '%d')",
		pg_site_time_sql('visitors.start_timestamp', '%d', $from, $to, $zone, $now)
	);
}

// A window wholly in the other season shifts every row, without a CASE.
function test_site_time_sql_shifts_a_window_in_the_other_season()
{
	$from = strtotime('2026-07-01 00:00:00 Europe/Berlin');
	$to = strtotime('2026-07-31 23:59:59 Europe/Berlin');

	pg_assert_same(
		"FROM_UNIXTIME(visitors.start_timestamp + 3600, '%Y-%m-%d')",
		pg_site_time_sql('visitors.start_timestamp', '%Y-%m-%d', $from, $to, 'Europe/Berlin', pg_test_site_time_berlin_now())
	);

	// Read in summer, a winter window shifts back.
	$summer_now = strtotime('2026-07-15 12:00:00 UTC');
	$from = strtotime('2026-01-01 00:00:00 Europe/Berlin');
	$to = strtotime('2026-01-31 23:59:59 Europe/Berlin');

	pg_assert_same(
		"FROM_UNIXTIME(visitors.start_timestamp - 3600, '%m')",
		pg_site_time_sql('visitors.start_timestamp', '%m', $from, $to, 'Europe/Berlin', $summer_now)
	);
}

// A window in the session's own season, and a zone without daylight saving
// time, keep the plain reading.
function test_site_time_sql_keeps_the_plain_reading()
{
	$from = strtotime('2026-11-01 00:00:00 Europe/Berlin');
	$to = strtotime('2026-11-30 23:59:59 Europe/Berlin');

	pg_assert_same(
		"FROM_UNIXTIME(visitors.start_timestamp, '%d')",
		pg_site_time_sql('visitors.start_timestamp', '%d', $from, $to, 'Europe/Berlin', pg_test_site_time_berlin_now())
	);

	pg_assert_same(
		"FROM_UNIXTIME(visitors.start_timestamp, '%Y')",
		pg_site_time_sql('visitors.start_timestamp', '%Y', strtotime('2020-01-01 UTC'), strtotime('2026-10-30 UTC'), 'Europe/Istanbul', pg_test_site_time_berlin_now())
	);

	pg_assert_same(array(array('until' => null, 'shift' => 0)), pg_site_time_shifts(strtotime('2020-01-01 UTC'), strtotime('2026-10-30 UTC'), 'Europe/Istanbul', pg_test_site_time_berlin_now()));
}

// Several years of changes give one branch per change, the same shift
// merged; an unknown zone or an empty window keeps the plain reading.
function test_site_time_shifts_over_years_and_edge_cases()
{
	$from = strtotime('2024-01-01 00:00:00 Europe/Berlin');
	$to = strtotime('2026-10-30 23:59:59 Europe/Berlin');
	$segments = pg_site_time_shifts($from, $to, 'Europe/Berlin', pg_test_site_time_berlin_now());

	// Winter, then summer and winter for 2024, 2025 and 2026.
	pg_assert_same(7, count($segments), 'seven segments');
	pg_assert_same(0, $segments[0]['shift']);
	pg_assert_same(3600, $segments[1]['shift']);
	pg_assert_same(null, $segments[6]['until']);
	pg_assert_same(0, $segments[6]['shift']);

	$plain = array(array('until' => null, 'shift' => 0));
	pg_assert_same($plain, pg_site_time_shifts($from, $to, 'Not/AZone', pg_test_site_time_berlin_now()), 'unknown zone');
	pg_assert_same($plain, pg_site_time_shifts($to, $from, 'Europe/Berlin', pg_test_site_time_berlin_now()), 'empty window');
}

// The Visitor Report filters and groups its days through the helper, over
// the window its rows come from; no plain FROM_UNIXTIME() day is left.
function test_visitor_report_reads_days_in_site_time()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor_report.php');

	pg_assert_contains("case 'date': \$operand_1 = pg_site_time_sql('visitors.start_timestamp', '%Y-%m-%d', \$site_time_from, \$site_time_to);", $source, 'Date filter');
	pg_assert_contains("case 'day': return pg_site_time_sql('visitors.start_timestamp', '%d', \$from, \$to);", $source, 'day grouping');
	pg_assert_same(3, substr_count($source, ", \$site_time_from, \$site_time_to) . ','"), 'three groupings get the window');
	pg_assert_same(0, preg_match("/FROM_UNIXTIME\\(visitors\\.start_timestamp, '/", str_replace("\\'", "'", $source)), 'no plain day reading');
}
