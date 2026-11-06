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

// The AI draft of a status update asks through pg_ai_chat(): a direct
// gateway call skipped the hold after a failed call (a 429 or a 5xx) and
// left a licence refusal and the hold unrecorded for every other caller.
function test_workspace_status_ai_draft_goes_through_pg_ai_chat()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/status.php');
	$start = strpos($source, 'function ws_status_ai_draft(');
	pg_assert_true($start !== false, 'the draft function is there');

	$end = strpos($source, "\nfunction ", $start + 10);
	$body = substr($source, $start, ($end === false) ? strlen($source) - $start : $end - $start);

	pg_assert_contains('pg_ai_chat(ws_status_ai_messages(', $body, 'asked through pg_ai_chat()');
	pg_assert_false(strpos($body, 'ws_ai_http(') !== false, 'no direct gateway call');
}

// The conversation: the rules first, then the figures as JSON data.
function test_workspace_status_ai_messages()
{
	$data = array('channel' => 'Proje', 'counts' => array('open' => 3), 'overdue' => 1, 'stale' => 0, 'finished' => 2, 'decisions' => array(), 'check_in' => null);
	$messages = ws_status_ai_messages($data, 'Turkish');

	pg_assert_same(2, count($messages), 'two messages');
	pg_assert_same('system', $messages[0]['role'], 'rules first');
	pg_assert_contains('Write in Turkish.', $messages[0]['content'], 'the language');
	pg_assert_contains("\nRules:\n", $messages[0]['content'], 'the shared writer rules');
	pg_assert_same('user', $messages[1]['role'], 'data second');
	pg_assert_same($data, json_decode($messages[1]['content'], true), 'the figures as JSON');
}
