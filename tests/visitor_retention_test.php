<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the visitor retention period in
 * includes/fn/visitor_retention.php: the period as typed into the settings,
 * the boundary it gives, the remainder row of the daily summary, the SQL
 * the device and channel are counted with, what decides whether a MyISAM
 * table is moved to InnoDB, and whether an InnoDB one is rebuilt to make its
 * file smaller.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Empty and 0 keep every row; a whole number within the bounds is a period.
function test_visitor_retention_days_from_input_accepts_periods()
{
	pg_assert_same(0, pg_visitor_retention_days_from_input(''));
	pg_assert_same(0, pg_visitor_retention_days_from_input('0'));
	pg_assert_same(0, pg_visitor_retention_days_from_input(' 0 '));
	pg_assert_same(90, pg_visitor_retention_days_from_input('90'));
	pg_assert_same(90, pg_visitor_retention_days_from_input(' 90 '));
	pg_assert_same(PG_VISITOR_RETENTION_MIN_DAYS, pg_visitor_retention_days_from_input((string) PG_VISITOR_RETENTION_MIN_DAYS));
	pg_assert_same(PG_VISITOR_RETENTION_MAX_DAYS, pg_visitor_retention_days_from_input((string) PG_VISITOR_RETENTION_MAX_DAYS));
}

// Anything else is refused rather than read as some other number.
function test_visitor_retention_days_from_input_refuses_the_rest()
{
	pg_assert_same(null, pg_visitor_retention_days_from_input((string) (PG_VISITOR_RETENTION_MIN_DAYS - 1)), 'below the minimum');
	pg_assert_same(null, pg_visitor_retention_days_from_input((string) (PG_VISITOR_RETENTION_MAX_DAYS + 1)), 'above the maximum');
	pg_assert_same(null, pg_visitor_retention_days_from_input('-30'), 'negative');
	pg_assert_same(null, pg_visitor_retention_days_from_input('30.5'), 'fraction');
	pg_assert_same(null, pg_visitor_retention_days_from_input('30 days'), 'words');
	pg_assert_same(null, pg_visitor_retention_days_from_input('1e3'), 'exponent');
	pg_assert_same(null, pg_visitor_retention_days_from_input('00'), 'zero spelled twice');
}

// No period, no boundary.
function test_visitor_retention_cutoff_without_a_period()
{
	pg_assert_same(0, pg_visitor_retention_cutoff(0, time()));
	pg_assert_same(0, pg_visitor_retention_cutoff(-5, time()));
}

// The boundary is midnight of the day N days back, by the site's clock, also
// across a daylight saving change: 86400-second steps would land an hour off.
function test_visitor_retention_cutoff_is_midnight_across_dst()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');

	try {
		// Summer time began on 29 March 2026.
		$now = mktime(15, 30, 0, 4, 2, 2026);

		$cutoff = pg_visitor_retention_cutoff(7, $now);

		pg_assert_same(mktime(0, 0, 0, 3, 26, 2026), $cutoff, '7 days before 2 April');
		pg_assert_same('2026-03-26 00:00', date('Y-m-d H:i', $cutoff));

		$cutoff = pg_visitor_retention_cutoff(90, mktime(0, 0, 1, 11, 1, 2026));

		pg_assert_same('2026-08-03 00:00', date('Y-m-d H:i', $cutoff), '90 days before 1 November');
	} finally {
		date_default_timezone_set($zone);
	}
}

// What the top values do not cover becomes one remainder row, figure by
// figure.
function test_visitor_retention_summary_rest_row()
{
	$total = array('visits' => 10, 'page_views' => 25, 'forms_submitted' => 1, 'orders_created' => 2, 'orders_retrieved' => 0, 'orders_checked_out' => 1, 'orders_completed' => 1, 'order_total' => 5000);

	$rows = array(
		array('value' => 'google.com', 'visits' => '6', 'page_views' => '15', 'forms_submitted' => '1', 'orders_created' => '1', 'orders_retrieved' => '0', 'orders_checked_out' => '1', 'orders_completed' => '1', 'order_total' => '5000'),
		array('value' => '', 'visits' => '3', 'page_views' => '6', 'forms_submitted' => '0', 'orders_created' => '0', 'orders_retrieved' => '0', 'orders_checked_out' => '0', 'orders_completed' => '0', 'order_total' => '0'),
	);

	$lines = pg_visitor_summary_with_rest($rows, $total);

	pg_assert_same(3, count($lines), 'two values and the rest');
	pg_assert_same('google.com', $lines[0]['value']);
	pg_assert_same(0, $lines[0]['rest']);
	pg_assert_same(6, $lines[0]['visits']);
	pg_assert_same('', $lines[1]['value'], 'an empty value is a value, not the rest');
	pg_assert_same(0, $lines[1]['rest']);
	pg_assert_same(1, $lines[2]['rest']);
	pg_assert_same(1, $lines[2]['visits']);
	pg_assert_same(4, $lines[2]['page_views']);
	pg_assert_same(1, $lines[2]['orders_created']);
	pg_assert_same(0, $lines[2]['order_total']);
}

// When the top values cover every visit there is no remainder row.
function test_visitor_retention_summary_no_rest_when_covered()
{
	$total = array('visits' => 4, 'page_views' => 4, 'forms_submitted' => 0, 'orders_created' => 0, 'orders_retrieved' => 0, 'orders_checked_out' => 0, 'orders_completed' => 0, 'order_total' => 0);

	$lines = pg_visitor_summary_with_rest(array(array('value' => 'TR', 'visits' => 4, 'page_views' => 4)), $total);

	pg_assert_same(1, count($lines));
	pg_assert_same(0, $lines[0]['rest']);
	pg_assert_same(0, $lines[0]['order_total'], 'a figure the query left out counts as 0');
}

// Tablets are told apart before phones; no user agent is no device.
function test_visitor_retention_device_sql()
{
	$sql = pg_visitor_device_sql('visitors.user_agent');

	pg_assert_contains("WHEN (visitors.user_agent = '') THEN ''", $sql);
	pg_assert_true(strpos($sql, "'tablet'") < strpos($sql, "'mobile'"), 'tablet is tested first');
	pg_assert_contains("ELSE 'desktop' END", $sql);
}

// With no pay-per-click flag set nothing is paid: an empty pattern would match
// every visit.
function test_visitor_retention_channel_sql_without_flag()
{
	if (defined('PAY_PER_CLICK_FLAG') && (trim((string) PAY_PER_CLICK_FLAG) !== '')) {
		pg_assert_contains("THEN 'pay_per_click'", pg_visitor_paid_organic_sql());
		return;
	}

	$sql = pg_visitor_paid_organic_sql('v.');

	pg_assert_false(strpos($sql, 'pay_per_click') !== false, 'no paid branch without a flag');
	pg_assert_contains("WHEN (v.referring_search_engine <> '') THEN 'organic'", $sql);
}

// The database server is on this machine when the site connects to it by a
// local name or address, with or without a port or the persistent prefix.
function test_visitor_move_db_is_local()
{
	foreach (array('', 'localhost', 'LOCALHOST', '127.0.0.1', '127.0.0.1:3306', 'localhost:3307', 'p:localhost', 'p:127.0.0.1:3306', '::1', '[::1]', '[::1]:3306', '.') as $host) {
		pg_assert_true(pg_visitor_move_db_is_local($host), $host);
	}

	foreach (array('db.example.com', '10.0.0.5', '10.0.0.5:3306', 'p:db.example.com', 'mysql', '[2001:db8::1]:3306', 'localhost.example.com') as $host) {
		pg_assert_false(pg_visitor_move_db_is_local($host), $host);
	}
}

// The room of the rows a copy holds: on MyISAM the data figure counts the
// removed rows, on InnoDB it does not.
function test_visitor_rows_room()
{
	pg_assert_same(0.0, pg_visitor_rows_room(array()), 'nothing known');
	pg_assert_same(300.0, pg_visitor_rows_room(array('engine' => 'myisam', 'data' => 900.0, 'free' => 700.0, 'index' => 100.0)));
	pg_assert_same(100.0, pg_visitor_rows_room(array('engine' => 'myisam', 'data' => 100.0, 'free' => 400.0, 'index' => 100.0)), 'free above data counts as no rows');
	pg_assert_same(1000.0, pg_visitor_rows_room(array('engine' => 'innodb', 'data' => 900.0, 'free' => 700.0, 'index' => 100.0)), 'InnoDB free space is outside the data figure');
}

// What is asked to be free for the move: what the copy holds, half again for
// InnoDB pages and the server's logs, and 256 MB for the rest of the server.
function test_visitor_move_disk_needed()
{
	$margin = 268435456.0;

	pg_assert_same($margin, pg_visitor_move_disk_needed(array()), 'nothing known');
	pg_assert_same(1.5 * 300.0 + $margin, pg_visitor_move_disk_needed(array('engine' => 'myisam', 'data' => 900.0, 'free' => 700.0, 'index' => 100.0)));
	pg_assert_same(1.5 * 1000.0 + $margin, pg_visitor_move_disk_needed(array('engine' => 'innodb', 'data' => 900.0, 'free' => 700.0, 'index' => 100.0)), 'a split copies every InnoDB row');

	// Rows that fit in 30 MB no longer ask for about 1.2 GB.
	$needed = pg_visitor_move_disk_needed(array('engine' => 'innodb', 'data' => 25.0 * 1048576, 'free' => 210.0 * 1048576, 'index' => 5.0 * 1048576));
	pg_assert_true($needed < 400.0 * 1048576, 'about 300 MB for 30 MB of rows');
}

// The bound under which a copy goes ahead without the free disk space read:
// rows left at most half the file, at most 5 million of them and at most
// 2 GB of room.
function test_visitor_blind_ok()
{
	$megabyte = 1048576.0;

	pg_assert_true(pg_visitor_blind_ok(200000, 30 * $megabyte, 240 * $megabyte), 'the sandbox table: 30 MB of rows in a 240 MB file');
	pg_assert_true(pg_visitor_blind_ok(5000000, 600 * $megabyte, 1200 * $megabyte), 'exactly half, exactly 5 million');
	pg_assert_false(pg_visitor_blind_ok(4960000, 1290 * $megabyte, 1300 * $megabyte), 'every record kept: the copy needs the whole file');
	pg_assert_false(pg_visitor_blind_ok(5000001, 600 * $megabyte, 1200 * $megabyte), 'over 5 million rows');
	pg_assert_false(pg_visitor_blind_ok(4000000, 2100 * $megabyte, 8000 * $megabyte), 'over 2 GB of rows');
	pg_assert_false(pg_visitor_blind_ok(200000, 30 * $megabyte, 0.0), 'file size unknown');
	pg_assert_false(pg_visitor_blind_ok(0, 0.0, 240 * $megabyte), 'nothing known about the rows');
}

// A try that started without the free disk space read and failed stops the
// next ones; a finished move or rebuild clears that.
function test_visitor_blind_failed_after()
{
	pg_assert_true(pg_visitor_blind_failed_after(false, true, 'failed'));
	pg_assert_false(pg_visitor_blind_failed_after(false, false, 'failed'), 'a try that had read the disk');
	pg_assert_true(pg_visitor_blind_failed_after(true, false, 'waiting'), 'kept while nothing finishes');
	pg_assert_true(pg_visitor_blind_failed_after(true, false, 'copying'));
	pg_assert_false(pg_visitor_blind_failed_after(true, false, 'done'), 'cleared once one finished');
	pg_assert_false(pg_visitor_blind_failed_after(true, true, 'done'));
}

// The facts of a MyISAM table the general job may move on its own.
function pg_test_visitor_move_facts($changes = array())
{
	return $changes + array(
		'kind' => 'engine',
		'capable' => true,
		'fits' => true,
		'old_exists' => false,
		'requested' => false,
		'failed' => false,
		'days' => 90,
		'cleaned' => true,
		'backlog' => false,
		'disk_free' => 10.0 * 1073741824,
		'needed' => 2.0 * 1073741824,
		'blind_ok' => false,
		'blind_failed' => false,
	);
}

// Started on its own only once the cleanup is through and the disk is seen to
// have room; each reason in the order the screen explains it.
function test_visitor_move_hold_reason_on_its_own()
{
	pg_assert_same('', pg_visitor_move_hold_reason(pg_test_visitor_move_facts()));
	pg_assert_same('engine', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('kind' => ''))), 'nothing to move');
	pg_assert_same('', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('kind' => 'split', 'fits' => null))), 'an InnoDB table to split');
	pg_assert_same('backlog', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('kind' => 'split', 'backlog' => true))), 'a split waits for the cleanup too');
	pg_assert_same('unsupported', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('capable' => false))));
	pg_assert_same('too_wide', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('fits' => false))));
	pg_assert_same('', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('fits' => null))), 'an unknown row size does not hold it');
	pg_assert_same('name_taken', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('old_exists' => true))));
	pg_assert_same('failed', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('failed' => true))));
	pg_assert_same('no_period', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('days' => 0))));
	pg_assert_same('backlog', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('cleaned' => false))), 'the cleanup has not run yet');
	pg_assert_same('backlog', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('backlog' => true))));
	pg_assert_same('disk_unknown', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('disk_free' => null))));
	pg_assert_same('disk_low', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('disk_free' => 1.0 * 1073741824))));
}

// With the free disk space unknown, a copy small against the file goes ahead
// on its own, until one such try has failed; a disk read as too small is
// never overruled.
function test_visitor_move_hold_reason_disk_unknown()
{
	pg_assert_same('', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('disk_free' => null, 'blind_ok' => true))));
	pg_assert_same('', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('kind' => 'split', 'disk_free' => null, 'blind_ok' => true))), 'a split too');
	pg_assert_same('disk_unknown', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('disk_free' => null, 'blind_ok' => false))), 'too large a copy');
	pg_assert_same('blind_failed', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('disk_free' => null, 'blind_ok' => true, 'blind_failed' => true))));
	pg_assert_same('failed', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('disk_free' => null, 'blind_ok' => true, 'blind_failed' => true, 'failed' => true))), 'the week after the failure says failed');
	pg_assert_same('backlog', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('disk_free' => null, 'blind_ok' => true, 'backlog' => true))), 'still waits for the cleanup');
	pg_assert_same('disk_low', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('disk_free' => 1.0, 'blind_ok' => true))), 'a disk read as full');
	pg_assert_same('', pg_visitor_move_hold_reason(pg_test_visitor_move_facts(array('blind_failed' => true))), 'a readable disk with room');
}

// The operator's request skips the period, the backlog, an earlier failure and
// the disk, never what makes the move impossible.
function test_visitor_move_hold_reason_on_request()
{
	$requested = array('requested' => true, 'failed' => true, 'days' => 0, 'backlog' => true, 'disk_free' => null);

	pg_assert_same('', pg_visitor_move_hold_reason(pg_test_visitor_move_facts($requested)));
	pg_assert_same('engine', pg_visitor_move_hold_reason(pg_test_visitor_move_facts($requested + array('kind' => ''))));
	pg_assert_same('', pg_visitor_move_hold_reason(pg_test_visitor_move_facts($requested + array('kind' => 'split'))));
	pg_assert_same('unsupported', pg_visitor_move_hold_reason(pg_test_visitor_move_facts($requested + array('capable' => false))));
	pg_assert_same('too_wide', pg_visitor_move_hold_reason(pg_test_visitor_move_facts($requested + array('fits' => false))));
	pg_assert_same('name_taken', pg_visitor_move_hold_reason(pg_test_visitor_move_facts($requested + array('old_exists' => true))));
}

// A MyISAM table is moved (and split on the way), an InnoDB one only split,
// and only where the server can and it is not split yet.
function test_visitor_move_kind()
{
	pg_assert_same('engine', pg_visitor_move_kind('myisam', 'none', true));
	pg_assert_same('engine', pg_visitor_move_kind('myisam', 'none', false), 'moved unsplit where the server cannot split it');
	pg_assert_same('split', pg_visitor_move_kind('innodb', 'none', true));
	pg_assert_same('', pg_visitor_move_kind('innodb', 'none', false));
	pg_assert_same('', pg_visitor_move_kind('innodb', 'monthly', true), 'split already');
	pg_assert_same('', pg_visitor_move_kind('innodb', 'other', true), 'partitioned another way');
	pg_assert_same('', pg_visitor_move_kind('', 'missing', true));
}

// A rebuild is worth it from 128 MB free that is at least half the size of
// the rows and indexes left.
function test_visitor_shrink_worth()
{
	$megabyte = 1048576.0;

	pg_assert_true(pg_visitor_shrink_worth(array('free' => 1000 * $megabyte, 'bytes' => 300 * $megabyte)));
	pg_assert_true(pg_visitor_shrink_worth(array('free' => 128 * $megabyte, 'bytes' => 256 * $megabyte)), 'both bounds met exactly');
	pg_assert_false(pg_visitor_shrink_worth(array('free' => 127 * $megabyte, 'bytes' => 10 * $megabyte)), 'below 128 MB');
	pg_assert_false(pg_visitor_shrink_worth(array('free' => 500 * $megabyte, 'bytes' => 1200 * $megabyte)), 'a small share of a large file');
	pg_assert_false(pg_visitor_shrink_worth(array()), 'nothing known');
}

// What a rebuild asks to be free: the new file, the sort files of the
// indexes and 256 MB for the log of the writes made meanwhile and the rest of
// the server. The free space inside the file is not part of the rows.
function test_visitor_shrink_disk_needed()
{
	$margin = 268435456.0;

	pg_assert_same($margin, pg_visitor_shrink_disk_needed(array()), 'nothing known');
	pg_assert_same(300.0 + 100.0 + 100.0 + $margin, pg_visitor_shrink_disk_needed(array('engine' => 'innodb', 'data' => 300.0, 'free' => 900.0, 'index' => 100.0)));
}

// The hour with the fewest page views; a missing hour had none; a tie goes
// to the first hour from 03:00 on, and no figures at all mean 03:00.
function test_visitor_shrink_quiet_hour()
{
	pg_assert_same(3, pg_visitor_shrink_quiet_hour(array()), 'no figures');
	pg_assert_same(3, pg_visitor_shrink_quiet_hour(array_fill(0, 24, 0)), 'all zero');

	$views = array_fill(0, 24, 500);
	$views[5] = 20;
	pg_assert_same(5, pg_visitor_shrink_quiet_hour($views));

	$views[23] = 20;
	pg_assert_same(5, pg_visitor_shrink_quiet_hour($views), 'tie: the first from 03:00 on');

	$views = array_fill(0, 24, 500);
	$views[1] = 20;
	$views[4] = 20;
	pg_assert_same(4, pg_visitor_shrink_quiet_hour($views), 'tie across midnight: 04:00 comes before 01:00 from 03:00 on');

	$views = array_fill(0, 24, 500);
	unset($views[14]);
	pg_assert_same(14, pg_visitor_shrink_quiet_hour($views), 'an hour without a row had no page view');
}

// The facts of an InnoDB table the general job may rebuild on its own.
function pg_test_visitor_shrink_facts($changes = array())
{
	return $changes + array(
		'engine' => 'innodb',
		'moving' => false,
		'partitioned' => false,
		'per_table' => true,
		'requested' => false,
		'worth' => true,
		'failed' => false,
		'recent' => false,
		'backlog' => false,
		'disk_free' => 10.0 * 1073741824,
		'needed' => 2.0 * 1073741824,
		'hour' => 3,
		'quiet_hour' => 3,
		'blind_ok' => false,
		'blind_failed' => false,
	);
}

// Rebuilt on its own only when much of the file is free, the cleanup is
// through, the disk is seen to have room and it is the quietest hour; each
// reason in the order the screen explains it.
function test_visitor_shrink_hold_reason_on_its_own()
{
	pg_assert_same('', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts()));
	pg_assert_same('engine', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('engine' => 'myisam'))));
	pg_assert_same('moving', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('moving' => true))));
	pg_assert_same('shared_tablespace', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('per_table' => false))));
	pg_assert_same('', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('per_table' => null))), 'an unreadable setting does not hold it');
	pg_assert_same('partitioned', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('partitioned' => true))), 'a dropped month gives its file back');
	pg_assert_same('small', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('worth' => false))));
	pg_assert_same('failed', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('failed' => true))));
	pg_assert_same('recent', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('recent' => true))));
	pg_assert_same('backlog', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('backlog' => true))));
	pg_assert_same('disk_unknown', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('disk_free' => null))));
	pg_assert_same('disk_low', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('disk_free' => 1.0 * 1073741824))));
	pg_assert_same('hour', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('hour' => 14))));
	pg_assert_same('small', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('worth' => false, 'disk_free' => null, 'hour' => 14))), 'nothing to gain comes first');
}

// With the free disk space unknown, a rebuild whose rows are small against the
// file goes ahead in the quietest hour, until one such try has failed.
function test_visitor_shrink_hold_reason_disk_unknown()
{
	pg_assert_same('', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('disk_free' => null, 'blind_ok' => true))));
	pg_assert_same('disk_unknown', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('disk_free' => null))));
	pg_assert_same('blind_failed', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('disk_free' => null, 'blind_ok' => true, 'blind_failed' => true))));
	pg_assert_same('hour', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('disk_free' => null, 'blind_ok' => true, 'hour' => 14))), 'still in the quietest hour only');
	pg_assert_same('small', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('disk_free' => null, 'blind_ok' => true, 'worth' => false))));
	pg_assert_same('partitioned', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('disk_free' => null, 'blind_ok' => true, 'partitioned' => true))));
	pg_assert_same('disk_low', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts(array('disk_free' => 1.0, 'blind_ok' => true))), 'a disk read as full');
}

// The operator's request skips the size, an earlier rebuild, the cleanup,
// the disk and the hour, never what makes the rebuild pointless.
function test_visitor_shrink_hold_reason_on_request()
{
	$requested = array('requested' => true, 'worth' => false, 'failed' => true, 'recent' => true, 'backlog' => true, 'disk_free' => null, 'hour' => 14);

	pg_assert_same('', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts($requested)));
	pg_assert_same('', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts($requested + array('partitioned' => true))), 'Shrink now works on a split table');
	pg_assert_same('engine', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts($requested + array('engine' => 'myisam'))));
	pg_assert_same('moving', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts($requested + array('moving' => true))));
	pg_assert_same('shared_tablespace', pg_visitor_shrink_hold_reason(pg_test_visitor_shrink_facts($requested + array('per_table' => false))));
}

// Names as the server spells them in its data directory: '-' and '.' become
// '@002d' and '@002e'; a name beyond ASCII is not guessed at.
function test_visitor_db_file_name()
{
	pg_assert_same('pinegrap_site', pg_visitor_db_file_name('pinegrap_site'));
	pg_assert_same('Pinegrap2026', pg_visitor_db_file_name('Pinegrap2026'), 'letters keep their case');
	pg_assert_same('my@002dsite', pg_visitor_db_file_name('my-site'));
	pg_assert_same('a@002eb@0020c@0024', pg_visitor_db_file_name('a.b c$'));
	pg_assert_same(null, pg_visitor_db_file_name('şirket'), 'beyond ASCII');
	pg_assert_same(null, pg_visitor_db_file_name(''), 'no name');
}

// InnoDB's .ibd, MyISAM's two files together, the database folder also in
// lower case for lower_case_table_names = 1, whatever slash ends the path.
function test_visitor_table_file_paths()
{
	pg_assert_same(
		array(array('/var/lib/mysql/site/visitors.ibd')),
		pg_visitor_table_file_paths('/var/lib/mysql/', 'site', 'visitors', 'innodb'));

	pg_assert_same(
		array(
			array('C:\ProgramData\MySQL\MySQL Server 8.0\Data/My@002dSite/visitors.ibd'),
			array('C:\ProgramData\MySQL\MySQL Server 8.0\Data/my@002dsite/visitors.ibd'),
		),
		pg_visitor_table_file_paths('C:\ProgramData\MySQL\MySQL Server 8.0\Data\\', 'My-Site', 'visitors', 'innodb'),
		'Windows data directory, a name with capitals');

	pg_assert_same(
		array(array('/var/lib/mysql/site/visitors.MYD', '/var/lib/mysql/site/visitors.MYI')),
		pg_visitor_table_file_paths('/var/lib/mysql', 'site', 'visitors', 'myisam'));

	pg_assert_same(array(), pg_visitor_table_file_paths('/var/lib/mysql', 'site', 'visitors', 'aria'), 'another engine');
	pg_assert_same(array(), pg_visitor_table_file_paths('', 'site', 'visitors', 'innodb'), 'no data directory');
	pg_assert_same(array(), pg_visitor_table_file_paths('/var/lib/mysql', 'şirket', 'visitors', 'innodb'), 'a name that cannot be spelled');
}

// Without a connection only the statistics are there: MyISAM's are the sizes
// of its files, InnoDB's an estimate that adds the free space on top.
function test_visitor_table_file_size_from_statistics()
{
	$row = array('data' => 300.0, 'index' => 100.0, 'free' => 50.0);

	pg_assert_same(array('bytes' => 400.0, 'source' => 'server'), pg_visitor_table_file_size(array('engine' => 'myisam') + $row));
	pg_assert_same(array('bytes' => 450.0, 'source' => 'estimate'), pg_visitor_table_file_size(array('engine' => 'innodb') + $row));
	pg_assert_same(array('bytes' => 450.0, 'source' => 'estimate'), pg_visitor_table_file_size(array('engine' => '') + $row), 'unreadable definition');
	pg_assert_same(array('bytes' => 0.0, 'source' => 'estimate'), pg_visitor_table_file_size(null), 'no row');
}

// Either threshold is enough: the records, or the size of the file.
function test_visitor_table_is_large()
{
	$limits = pg_visitor_health_thresholds();

	pg_assert_false(pg_visitor_table_is_large(array('rows' => $limits['rows'] - 1, 'file' => $limits['bytes'] - 1)));
	pg_assert_true(pg_visitor_table_is_large(array('rows' => $limits['rows'], 'file' => 0.0)), 'records');
	pg_assert_true(pg_visitor_table_is_large(array('rows' => 10, 'file' => $limits['bytes'])), 'a file mostly free space counts as well');
	pg_assert_false(pg_visitor_table_is_large(null), 'nothing known');
}

// "about" goes in front of estimates only.
function test_visitor_figure_text()
{
	foreach (array('counted', 'file', 'server') as $source) {
		pg_assert_same('620 MB', pg_visitor_figure_text('620 MB', $source), $source);
	}

	foreach (array('estimate', 'shared') as $source) {
		$text = pg_visitor_figure_text('620 MB', $source);
		pg_assert_true(($text !== '620 MB') && (strpos($text, '620 MB') !== false), $source);
	}
}
