<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the backup age rule, pg_backup_age_state() (includes/fn/backup.php):
 * no backup is a failure, a newest backup up to a month old is fine, an older
 * one is a warning. The System Status tile, the operations alert and the
 * dashboard greeting all read it, so their answers are pinned here too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('TIME_FORMAT')) {
	define('TIME_FORMAT', 'twenty_four_hours');
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/brief.php');

// The threshold is one month of thirty days.
function test_backup_age_threshold_is_thirty_days()
{
	pg_assert_same(2592000, PG_BACKUP_WARN_AGE);
}

// No backup at all fails; any backup under a month passes; past it, a warning.
function test_backup_age_state_by_age()
{
	$now = 2000000000;

	pg_assert_same('fail', pg_backup_age_state(0, $now), 'none');
	pg_assert_same('fail', pg_backup_age_state(-5, $now), 'a broken time is none');
	pg_assert_same('pass', pg_backup_age_state($now, $now), 'just taken');
	pg_assert_same('pass', pg_backup_age_state($now - (3 * 86400), $now), 'three days');
	pg_assert_same('pass', pg_backup_age_state($now - (8 * 86400), $now), 'eight days');
	pg_assert_same('pass', pg_backup_age_state($now - (29 * 86400), $now), 'twenty-nine days');
	pg_assert_same('warn', pg_backup_age_state($now - (31 * 86400), $now), 'thirty-one days');
	pg_assert_same('warn', pg_backup_age_state($now - (400 * 86400), $now), 'over a year');
}

// Exactly thirty days is still a month old, one second more is older.
function test_backup_age_state_boundary()
{
	$now = 2000000000;

	pg_assert_same('pass', pg_backup_age_state($now - 2592000, $now), 'thirty days to the second');
	pg_assert_same('warn', pg_backup_age_state($now - 2592001, $now), 'one second past');
}

// A backup stamped ahead of the clock (a server clock set back) is fresh.
function test_backup_age_state_future_stamp()
{
	pg_assert_same('pass', pg_backup_age_state(2000000600, 2000000000));
}

// The alert follows the same month; never having a backup is not this alert.
function test_backup_age_ops_alert_follows_the_rule()
{
	$now = 2000000000;

	pg_assert_false(pg_ops_backup_stale(0, $now), 'none');
	pg_assert_false(pg_ops_backup_stale($now - (9 * 86400), $now), 'nine days');
	pg_assert_false(pg_ops_backup_stale($now - 2592000, $now), 'thirty days');
	pg_assert_true(pg_ops_backup_stale($now - 2592001, $now), 'past a month');
}

// A backup check that passes says nothing on the greeting.
function test_backup_age_greeting_silent_when_fresh()
{
	pg_assert_same(array(), pg_dashboard_brief_backup_advice('ok', false, 'backups.php'));
	pg_assert_same(array(), pg_dashboard_brief_backup_advice('info', false, 'backups.php'));
	pg_assert_same(array(), pg_dashboard_brief_backup_advice('', false, 'backups.php'));
}

// No backup at all is never called old.
function test_backup_age_greeting_none()
{
	$lines = pg_dashboard_brief_backup_advice('fail', false, 'backups.php');

	pg_assert_true(count($lines) > 0, 'something is said');

	foreach ($lines as $line) {
		pg_assert_false(strpos($line, 'older than it should be') !== false, 'not: older than it should be');
		pg_assert_false(strpos($line, 'last week') !== false, 'not: last week');
		pg_assert_false(strpos($line, 'recently') !== false, 'not: recently');
		pg_assert_contains('href="backups.php"', $line);
	}

	pg_assert_contains('No backup of this site has been taken yet', $lines[0]);
}

// An old backup is named as more than a month old.
function test_backup_age_greeting_old()
{
	$lines = pg_dashboard_brief_backup_advice('warn', false, 'backups.php');

	pg_assert_true(count($lines) > 0, 'something is said');

	foreach ($lines as $line) {
		pg_assert_false(strpos($line, 'older than it should be') !== false, 'not: older than it should be');
		pg_assert_contains('month', $line);
	}
}

// A warning carried by a failed remote copy is about the copy, not the age.
function test_backup_age_greeting_copy_failed()
{
	$lines = pg_dashboard_brief_backup_advice('warn', true, 'backups.php');

	pg_assert_same(1, count($lines));
	pg_assert_contains('The last remote backup copy failed', $lines[0]);
	pg_assert_contains('href="settings_maintenance.php#pgset-backup"', $lines[0]);
	pg_assert_false(strpos($lines[0], 'month') !== false, 'not: month');
}
