<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the walk-in queue's API writes: a rehearsed "take a number"
 * meets the hours, cap and closing refusals before it stops.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/queue.php');

// The body of one function, from its declaration to the next one.
function api_queue_rehearsal_test_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	pg_assert_true($start !== false, $function . ' found');

	$end = strpos($source, "\nfunction ", $start + 10);

	return substr($source, $start, ($end === false) ? null : ($end - $start));
}

// The refusals of the hours, the cap and the closing time are read by one
// check that both the real take and the rehearsal run before any write.
function test_api_queue_take_checks_before_the_rehearsal_stops()
{
	$body = api_queue_rehearsal_test_body('includes/api/resources/queue.php', 'api_queue_tickets_create');
	$check = strpos($body, 'pg_waiting_take_check(');
	$stop = strpos($body, "api_dry_run_stop('created', 'queue_ticket'");
	$take = strpos($body, 'pg_waiting_take(');

	pg_assert_true($check !== false, 'the handler runs the take check');
	pg_assert_true(($stop !== false) && ($check < $stop), 'the check runs before the rehearsal stops');
	pg_assert_true(($take !== false) && ($take > $stop), 'the take stays below the stop');
	pg_assert_contains("'join_refused'", substr($body, $check, $stop - $check), 'a refusal is answered before the stop');

	$take_body = api_queue_rehearsal_test_body('includes/fn/walk_in_queue_tickets.php', 'pg_waiting_take');
	$take_check = strpos($take_body, 'pg_waiting_take_check(');
	$insert = strpos($take_body, 'INSERT INTO waiting_tickets');

	pg_assert_true(($take_check !== false) && ($insert !== false) && ($take_check < $insert), 'the real take runs the same check before its insert');
	pg_assert_same(0, substr_count($take_body, 'pg_waiting_join_refusal('), 'the take holds no second copy of the refusal');
}
