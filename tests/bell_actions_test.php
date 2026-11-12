<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a bell row's in-place action: which kinds of row offer one
 * (ws_bell_act_kind()), that each is offered only after the dry-run check of
 * the action it calls (ws_bell_act()), and how the drawer runs it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');

// The part of a source from one marker to the next top-level closing brace.
function bell_actions_part($source, $marker)
{
	$at = strpos($source, $marker);
	pg_assert_true($at !== false, $marker . ' is there');
	$end = strpos($source, "\n}\n", (int) $at);

	return (string) substr($source, (int) $at, (($end === false) ? strlen($source) : (int) $end) - (int) $at);
}

// Approve for an approval request, Try again for a failed run, a task done
// for a task given, reminded or overdue; nothing for any other row.
function test_bell_actions_kinds()
{
	pg_assert_same('approve', ws_bell_act_kind('approval_requested'));
	pg_assert_same('retry', ws_bell_act_kind('flow_finished', 'failed'));
	pg_assert_same('', ws_bell_act_kind('flow_finished', 'done'), 'a run that went well');
	pg_assert_same('', ws_bell_act_kind('flow_finished', ''), 'a run not known');

	foreach (array('assigned', 'task_reminder', 'task_overdue') as $kind) {
		pg_assert_same('done', ws_bell_act_kind($kind), $kind);
	}

	foreach (array('approval_reminder', 'approval_answered', 'mention', 'question_asked', 'meeting_soon', 'thread', '') as $kind) {
		pg_assert_same('', ws_bell_act_kind($kind, 'failed'), 'kind ' . $kind);
	}
}

// A reader without the right gets no action: each kind runs the dry-run
// check of the action it calls and returns null when it refuses.
function test_bell_actions_need_the_right()
{
	$notify = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');
	$act = bell_actions_part($notify, 'function ws_bell_act($viewer, $row)');
	$row_act = bell_actions_part($notify, 'function ws_bell_row_act($inbox_id, $user_id)');

	pg_assert_contains("if (!\$approval || !ws_approval_decide_check(\$viewer, \$approval, 'approve', '')['ok']) {\n                return null;", $act, 'approve: the person was asked and has not answered');
	pg_assert_contains("if (!is_array(\$step) || !ws_flow_step_mend_check(\$viewer, \$run, (string) \$step['node_id'])['ok']) {\n                return null;", $act, 'retry: only the flow\'s owner');
	pg_assert_contains("if (!\$task || ((string) \$task['status'] === 'done') || !ws_can_see_task(\$viewer, \$task) || !ws_task_set_status_check(\$viewer, \$task, 'done')['ok']) {\n                return null;", $act, 'done: a task the person may change');

	// The reader's own row, which they may see.
	pg_assert_contains("AND user_id = '\" . (int) \$user_id . \"'", $row_act);
	pg_assert_contains('return ws_inbox_row_visible($viewer, $row) ? ws_bell_act($viewer, $row) : null;', $row_act);

	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/notifications.php');
	pg_assert_contains("? ws_bell_row_act((int) (\$notification['reference_id'] ?? 0), (int) \$user['id'])", $endpoint, 'checked for the signed-in reader');
}

// The drawer runs the Workspace's own actions; after success the row is
// read and the list drawn anew, a refusal shows the bell's toast.
function test_bell_actions_in_the_drawer()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$at = strpos($js, "\$(document).on('click', '#notifications .pg-bell-do', function () {");
	pg_assert_true($at !== false, 'the action handler is there');
	$handler = (string) substr($js, (int) $at, (int) strpos($js, "\n});\n", (int) $at) - (int) $at);
	$row = bell_actions_part($js, 'function pg_bell_row(');

	pg_assert_contains("request.action = 'ws_approval_decide';", $handler);
	pg_assert_contains("request.action = 'ws_flow_step_mend';", $handler);
	pg_assert_contains("request.how = 'retry';", $handler);
	pg_assert_contains("request.action = 'ws_task_status';", $handler);
	pg_assert_contains("request.status = 'done';", $handler);
	pg_assert_contains("edit_notification(notification_id, 'mark_read', redraw);", $handler, 'the row is read');
	pg_assert_contains('get_notifications(true);', $handler, 'and drawn anew');
	pg_assert_contains('pg_bell_refused(response);', $handler);
	pg_assert_contains('pg_bell_refused(null, xhr);', $handler);
	pg_assert_true(substr_count($handler, 'button.disabled = false;') === 2, 'enabled again on every failing path');

	pg_assert_contains("act: { approve: lang('Approve'), retry: lang('Try again'), done: lang('Mark as done') },", $js);
	pg_assert_contains("var act = words.act[act_row.kind] ? String(act_row.kind) : '';", $row);

	$output = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	pg_assert_contains("'Try again' => lang('Try again'),", $output);
	pg_assert_contains("'Mark as done' => lang('Mark as done'),", $output);
}
