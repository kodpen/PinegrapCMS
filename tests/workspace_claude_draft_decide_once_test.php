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
 * now is a conditional UPDATE on the open row, checked with its affected
 * rows, before anything is opened. A row a dead request left accepted with
 * no task is open again after ws_claude_draft_stuck_after(), and the task
 * that request may have opened is looked for before a new one.
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

	$where = substr($code, $claim, strpos($code, ');', $claim) - $claim);
	pg_assert_contains('ws_claude_draft_open_sql(', $where, 'the claim only takes a row still open: pending, or stuck');

	$affected = strpos($code, 'mysqli_affected_rows(db::$con)', $claim);
	$create = strpos($code, 'ws_task_create(');
	pg_assert_true($affected !== false, 'the claim is checked');
	pg_assert_true(($create !== false) && ($claim < $create) && ($affected < $create), 'claimed before the task is opened');

	$orphan = strpos($code, 'ws_claude_draft_orphan_task(');
	pg_assert_true(($orphan !== false) && ($affected < $orphan) && ($orphan < $create), 'a stuck claim looks for the task the dead request opened, after claiming and before opening one');
	pg_assert_contains('ws_claude_draft_open(', substr($code, 0, $claim), 'the first read refuses a row that is neither pending nor stuck');
}

// A row left accepted with no task (the request died between the claim and
// the task) is open again once it is older than any request can live; a
// fresh claim, a row with its task and a dismissed row are not.
function test_workspace_claude_draft_stuck_rows()
{
	workspace_claude_draft_decide_once_code('ws_claude_draft_accept');

	$now = 1800000000;
	$after = ws_claude_draft_stuck_after();
	$row = function ($status, $task_id, $decided_at) {
		return array('status' => $status, 'task_id' => $task_id, 'decided_at' => $decided_at);
	};

	pg_assert_true($after >= 600, 'well past max_execution_time');
	pg_assert_true(ws_claude_draft_stuck($row('accepted', 0, $now - $after), $now), 'accepted, no task, at the cutoff');
	pg_assert_true(ws_claude_draft_stuck($row('accepted', '0', 0), $now), 'no time written counts as old');
	pg_assert_false(ws_claude_draft_stuck($row('accepted', 0, $now - $after + 1), $now), 'a fresh claim: its request may still be running');
	pg_assert_false(ws_claude_draft_stuck($row('accepted', 12, $now - 99999), $now), 'accepted with its task');
	pg_assert_false(ws_claude_draft_stuck($row('dismissed', 0, $now - 99999), $now), 'dismissed');
	pg_assert_false(ws_claude_draft_stuck($row('pending', 0, 0), $now), 'pending is not stuck');

	pg_assert_true(ws_claude_draft_open($row('pending', 0, 0), $now), 'pending is open');
	pg_assert_true(ws_claude_draft_open($row('accepted', 0, $now - $after - 5), $now), 'stuck is open');
	pg_assert_false(ws_claude_draft_open($row('accepted', 0, $now - 5), $now), 'a fresh claim is not');
	pg_assert_false(ws_claude_draft_open($row('accepted', 3, 0), $now), 'a row with its task is not');

	pg_assert_same("(status = 'pending' OR (status = 'accepted' AND task_id = 0 AND decided_at <= '" . ($now - $after) . "'))", ws_claude_draft_open_sql($now), 'the claim\'s condition, the same cutoff');
}

// The card offers a stuck row's buttons again, as a pending one.
function test_workspace_claude_draft_map_offers_stuck_rows()
{
	$code = workspace_claude_draft_decide_once_code('ws_claude_drafts_map');

	pg_assert_contains('ws_claude_draft_stuck($row, $now)', $code, 'the map asks whether a row is stuck');
	pg_assert_false(strpos($code, "'can_decide'  => (\$row['status'] === 'pending')") !== false, 'can_decide is no longer pending only');
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

// Dismissing only takes an open row too (pending, or stuck with no task
// the dead request opened), so it cannot overwrite an accepted proposal that
// has its task.
function test_workspace_claude_draft_dismiss_only_pending()
{
	$code = workspace_claude_draft_decide_once_code('ws_claude_draft_dismiss');

	$update = strpos($code, "UPDATE ws_ai_drafts SET status = 'dismissed'");
	pg_assert_true($update !== false, 'the dismiss UPDATE');

	$where = substr($code, $update, strpos($code, ');', $update) - $update);
	pg_assert_contains('ws_claude_draft_open_sql(', $where, 'dismiss only takes an open row');

	$orphan = strpos($code, 'ws_claude_draft_orphan_task(');
	pg_assert_true(($orphan !== false) && ($orphan < $update), 'a stuck row whose task exists is linked to it, not dismissed');
	pg_assert_true(strpos($code, 'mysqli_affected_rows(db::$con)', $update) !== false, 'the dismiss is checked');
}
