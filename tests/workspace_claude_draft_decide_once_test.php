<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a task Claude (or Pinegrap AI) proposes in a channel is decided
 * once. Accepting read the pending status, opened the task and only then
 * marked the proposal accepted, with nothing in the UPDATE tying it to the
 * status it read: two presses at once (a double click, two people) both
 * passed the check and opened two tasks, and a dismiss arriving meanwhile
 * overwrote the accepted proposal that already had its task. The decision
 * now is a conditional UPDATE on the pending row, checked with its affected
 * rows, before anything is opened.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function workspace_claude_draft_decide_once_code($function)
{
	if (!function_exists($function)) {
		require_once(PG_FUNCTIONS_DIR . '/includes/workspace/claude.php');
	}

	$code = '';

	foreach (pg_test_source_tokens(pg_test_function_source($function)) as $token) {
		$code .= pg_test_source_text($token);
	}

	return $code;
}

// Accepting claims the pending row before the task is opened, and stops
// when another request claimed it first.
function test_workspace_claude_draft_accept_claims_pending_row_first()
{
	$code = workspace_claude_draft_decide_once_code('ws_claude_draft_accept');

	$claim = strpos($code, "UPDATE ws_ai_drafts SET status = 'accepted'");
	pg_assert_true($claim !== false, 'the claim UPDATE');

	$where = substr($code, $claim, strpos($code, '");', $claim) - $claim);
	pg_assert_contains("AND status = 'pending'", $where, 'the claim only takes a pending row');

	$affected = strpos($code, 'mysqli_affected_rows(db::$con)', $claim);
	$create = strpos($code, 'ws_task_create(');
	pg_assert_true($affected !== false, 'the claim is checked');
	pg_assert_true(($create !== false) && ($claim < $create) && ($affected < $create), 'claimed before the task is opened');
}

// A task that could not be opened gives the proposal back, so it can be
// accepted again.
function test_workspace_claude_draft_accept_releases_on_failure()
{
	$code = workspace_claude_draft_decide_once_code('ws_claude_draft_accept');

	$create = strpos($code, 'ws_task_create(');
	$release = strpos($code, "UPDATE ws_ai_drafts SET status = 'pending'", $create);
	pg_assert_true($release !== false, 'released after a failed task');

	$where = substr($code, $release, strpos($code, '");', $release) - $release);
	pg_assert_contains("AND status = 'accepted' AND task_id = 0", $where, 'only its own unfinished claim');
}

// Dismissing only takes a pending row too, so it cannot overwrite an
// accepted proposal that has its task.
function test_workspace_claude_draft_dismiss_only_pending()
{
	$code = workspace_claude_draft_decide_once_code('ws_claude_draft_dismiss');

	$update = strpos($code, "UPDATE ws_ai_drafts SET status = 'dismissed'");
	pg_assert_true($update !== false, 'the dismiss UPDATE');

	$where = substr($code, $update, strpos($code, '");', $update) - $update);
	pg_assert_contains("AND status = 'pending'", $where, 'dismiss only takes a pending row');
	pg_assert_true(strpos($code, 'mysqli_affected_rows(db::$con)', $update) !== false, 'the dismiss is checked');
}
