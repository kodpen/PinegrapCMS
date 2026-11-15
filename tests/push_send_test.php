<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the push sender's pure parts (includes/push.php): the body a
 * native push row gets, what each push service status means, and how a 429
 * with Retry-After holds a row back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/push.php');

// A broker row carries the version and the badge, no text; a browser row stays
// the empty push it always was.
function test_push_send_body_of_a_native_row_is_the_badge_only()
{
	pg_assert_same('{"v":1,"badge":7}', pg_push_body(array('provider' => 'fcm'), 7));
	pg_assert_same('{"v":1,"badge":0}', pg_push_body(array('provider' => 'apns'), 0));
	pg_assert_same('{"v":1,"badge":0}', pg_push_body(array('provider' => 'fcm'), -3), 'never below zero');
	pg_assert_same('', pg_push_body(array('provider' => ''), 7), 'a browser');
	pg_assert_same('', pg_push_body(array(), 7), 'a row read before the provider column');
}

function test_push_send_outcome_of_a_status()
{
	foreach (array(200 => 'ok', 201 => 'ok', 204 => 'ok', 404 => 'gone', 410 => 'gone', 429 => 'busy', 0 => 'failed', 400 => 'failed', 413 => 'failed', 500 => 'failed', 503 => 'failed') as $status => $outcome) {
		pg_assert_same($outcome, pg_push_outcome($status), (string) $status);
	}
}

function test_push_send_retry_after_in_seconds_or_as_a_date()
{
	$now = 1800000000;

	pg_assert_same(120, pg_push_retry_after('120', $now));
	pg_assert_same(1, pg_push_retry_after('0', $now), 'at least a second');
	pg_assert_same(86400, pg_push_retry_after('999999', $now), 'at most a day');
	pg_assert_same(300, pg_push_retry_after(gmdate('D, d M Y H:i:s', $now + 300) . ' GMT', $now), 'an HTTP date');
	pg_assert_same(1, pg_push_retry_after(gmdate('D, d M Y H:i:s', $now - 300) . ' GMT', $now), 'a date gone by');

	foreach (array('', 'soon', '-5', '1.5', null) as $value) {
		pg_assert_same(60, pg_push_retry_after($value, $now), var_export($value, true) . ': a minute');
	}
}

// The hold is written into last_error, which the sessions screen shows, and
// read back from it.
function test_push_send_hold_is_read_back_from_last_error()
{
	$until = 1800000600;
	$text = pg_push_hold_text($until);

	pg_assert_same('HTTP 429, retry after 2027-01-15 08:10:00 UTC', $text);
	pg_assert_same($until, pg_push_held_until($text));

	foreach (array('', 'HTTP 500', 'HTTP 429', 'retry after soon') as $other) {
		pg_assert_same(0, pg_push_held_until($other), $other);
	}
}

// The queue's wait goes into GREATEST() as a number: a quoted one makes MySQL
// compare the arguments as strings ('60' > '300'), so a rate-limited row would
// spend its six tries in minutes.
function test_push_send_queue_wait_is_a_number_in_greatest()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/push.php');

	pg_assert_contains('GREATEST(" . (int) $wait . ", CASE', $source);
	pg_assert_same(0, preg_match("/(GREATEST|LEAST)\\(\\s*'/", $source), 'no quoted argument in GREATEST or LEAST');
}
