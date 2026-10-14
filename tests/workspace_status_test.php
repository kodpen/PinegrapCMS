<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/workspace/status.php: the state a
 * channel's figures point at (ws_status_suggest()) and how a check-in
 * question is kept (ws_checkin_question()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in the file runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/status.php');

function test_workspace_status_nothing_late_is_on_track()
{
	pg_assert_same('on_track', ws_status_suggest(array('open' => 8, 'overdue' => 0, 'stale' => 0)));
	pg_assert_same('on_track', ws_status_suggest(array()));
}

function test_workspace_status_one_late_or_stale_task_is_at_risk()
{
	pg_assert_same('at_risk', ws_status_suggest(array('open' => 8, 'overdue' => 1, 'stale' => 0)));
	pg_assert_same('at_risk', ws_status_suggest(array('open' => 8, 'overdue' => 0, 'stale' => 2)));
}

function test_workspace_status_many_late_tasks_are_off_track()
{
	pg_assert_same('off_track', ws_status_suggest(array('open' => 20, 'overdue' => 3, 'stale' => 0)));
	// Half of the open tasks late is off track even when few.
	pg_assert_same('off_track', ws_status_suggest(array('open' => 2, 'overdue' => 1, 'stale' => 0)));
	pg_assert_same('at_risk', ws_status_suggest(array('open' => 5, 'overdue' => 2, 'stale' => 0)));
}

function test_workspace_status_checkin_question_is_one_trimmed_line()
{
	pg_assert_same('What did you finish?', ws_checkin_question("  What did\n you   finish?  "));
	pg_assert_same('', ws_checkin_question("  \n "));
	pg_assert_same(WS_CHECKIN_QUESTION_MAX, mb_strlen(ws_checkin_question(str_repeat('ğ', 400))));
}
