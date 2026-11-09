<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_update_check_state() (includes/fn/update.php), which the
 * Software Update tile of System Status reads: "up to date" only from a
 * recent check that got an answer, never from a failed, old or switched-off
 * one. Plus source pins: both paths of software_update_check() record the
 * answer, and the tile decides through the function.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_update_check_state_available_wins()
{
	$now = 2000000000;

	pg_assert_same('available', pg_update_check_state(true, true, $now - 60, $now - 50, $now), 'fresh answer');
	pg_assert_same('available', pg_update_check_state(true, false, 0, 0, $now), 'announced before checks were turned off');
}

function test_update_check_state_checks_off_is_unknown()
{
	$now = 2000000000;

	pg_assert_same('off', pg_update_check_state(false, false, $now - 60, $now - 50, $now), 'off with a recent answer');
	pg_assert_same('off', pg_update_check_state(false, false, $now - 86400 * 400, $now - 86400 * 400, $now), 'off for a year');
}

function test_update_check_state_never_checked()
{
	$now = 2000000000;

	pg_assert_same('never', pg_update_check_state(false, true, 0, 0, $now), 'nothing stamped');
	pg_assert_same('never', pg_update_check_state(false, true, 0, $now - 60, $now), 'stamp reset after an upgrade');
}

function test_update_check_state_failed_check()
{
	$now = 2000000000;

	// The attempt is stamped before the request; an answer is recorded after it.
	pg_assert_same('failed', pg_update_check_state(false, true, $now - 60, $now - 86400 * 4, $now), 'last attempt newer than the last answer');
}

// Right after an update to the code that records answers, no answer exists
// yet: the attempt's age decides, never "failed".
function test_update_check_state_no_answer_recorded_yet()
{
	$now = 2000000000;

	pg_assert_same('current', pg_update_check_state(false, true, $now - 86400, 0, $now), 'recent attempt');
	pg_assert_same('stale', pg_update_check_state(false, true, $now - PG_UPDATE_CHECK_STALE_AGE - 1, 0, $now), 'old attempt');
}

function test_update_check_state_old_answer()
{
	$now = 2000000000;
	$old = $now - PG_UPDATE_CHECK_STALE_AGE - 1;

	pg_assert_same('stale', pg_update_check_state(false, true, $old, $old, $now), 'answer older than the limit');
	pg_assert_same('current', pg_update_check_state(false, true, $now - PG_UPDATE_CHECK_STALE_AGE, $now - PG_UPDATE_CHECK_STALE_AGE, $now), 'at the limit');
	pg_assert_true(PG_UPDATE_CHECK_STALE_AGE > 259200, 'longer than the three days between periodic checks');
}

function test_update_check_state_current()
{
	$now = 2000000000;

	pg_assert_same('current', pg_update_check_state(false, true, $now - 60, $now - 60, $now), 'answered in the same second');
	pg_assert_same('current', pg_update_check_state(false, true, $now - 60, $now - 55, $now), 'answered after the stamp');
}

// Both paths of software_update_check() record an answer right after they
// store the flag, and only there.
function test_update_check_records_the_answer_on_both_paths()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/software_update_check.php');

	pg_assert_same(2, substr_count($source, 'pg_update_check_answered();'), 'one record per path');

	foreach (array(
		"db(\"UPDATE config SET software_update_available = '\" . (\$flag ? 1 : 0) . \"'\");",
		"db(\"UPDATE config SET software_update_available = '\" . \$software_update_available . \"'\");",
	) as $write) {
		$at = strpos($source, $write);
		pg_assert_true($at !== false, 'flag write found');
		$next = strpos($source, 'pg_update_check_answered();', (int) $at);
		pg_assert_true(($next !== false) && ($next - $at < 200), 'answer recorded right after the flag write');
	}
}

function test_update_tile_reads_the_state()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/system_status.php');

	pg_assert_true(strpos($source, 'pg_update_check_state(') !== false, 'the tile decides through pg_update_check_state()');
	pg_assert_true(strpos($source, '} elseif ($update_checked < 1) {') === false, 'no green answer from the stamp alone');
}
