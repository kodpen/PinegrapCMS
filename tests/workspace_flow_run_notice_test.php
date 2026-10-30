<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the inbox line of a flow run that ended and for a run
 * cancelled (includes/workspace/notify.php, flow_runs.php): the line opens
 * that run on the canvas, a failed run has a title and an icon of its own,
 * and each open box of a cancelled run says what stays of what it opened.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');

// One function as written, comments left out.
function ws_flow_run_notice_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The line opens the flow with that run shown.
function test_workspace_flow_run_notice_opens_the_run()
{
	$line = ws_inbox_flow_run_line(array('id' => '12', 'flow_id' => '3', 'status' => 'done'), '/pg/');

	pg_assert_same('/pg/workspace_flows.php?flow=3&run=12', $line['url']);
	pg_assert_same('A run of your flow ended', $line['title']);
	pg_assert_same('bi-diagram-3', $line['icon']);
}

// A failed run stands out by its title and icon.
function test_workspace_flow_run_notice_failed_run_stands_out()
{
	$line = ws_inbox_flow_run_line(array('id' => 12, 'flow_id' => 3, 'status' => 'failed'), '/pg/');

	pg_assert_same('A run of your flow failed', $line['title']);
	pg_assert_same('bi-exclamation-octagon', $line['icon']);
	pg_assert_same('/pg/workspace_flows.php?flow=3&run=12', $line['url']);
}

// A line written before the run was kept, or a run deleted since, opens the
// flow list.
function test_workspace_flow_run_notice_without_a_run_opens_the_list()
{
	$line = ws_inbox_flow_run_line(null, '/pg/');

	pg_assert_same('/pg/workspace_flows.php', $line['url']);
	pg_assert_same('A run of your flow ended', $line['title']);
}

// The notice keeps the run, and the line reads it from there.
function test_workspace_flow_run_notice_keeps_the_run()
{
	pg_assert_contains("'\" . (int) \$run['id'] . \"'", ws_flow_run_notice_test_source('ws_flow_run_tell_owner'));
	pg_assert_contains('JOIN ws_flow_runs r ON r.id = n.action_id', ws_flow_run_notice_test_source('ws_inbox_flow_run'));
	pg_assert_contains('ws_inbox_flow_run_line(ws_inbox_flow_run(', ws_flow_run_notice_test_source('ws_inbox_describe'));
}

// Only a box that opened a task says its task stays; an approval box says
// its request stays open; a wait or a box not carried out yet says neither.
function test_workspace_flow_run_notice_cancel_sentence_by_box()
{
	pg_assert_same('Cancelled with the run by Ada; its task stays as it is.', ws_flow_cancel_result('task:5', 'Ada'));
	pg_assert_same('Cancelled with the run by Ada; its approval request stays open.', ws_flow_cancel_result('approval:7', 'Ada'));
	pg_assert_same('Cancelled with the run by Ada.', ws_flow_cancel_result('', 'Ada'));
	pg_assert_same('Cancelled with the run by Ada.', ws_flow_cancel_result('began:task', 'Ada'));
	pg_assert_same('Cancelled with the run by Ada.', ws_flow_cancel_result('approved:7', 'Ada'));

	$cancel = ws_flow_run_notice_test_source('ws_flow_run_cancel');

	pg_assert_contains('ws_flow_cancel_result($step[\'effect\'], $name)', $cancel);
	pg_assert_false(strpos($cancel, "effect <> ''") !== false, 'no sentence chosen by effect alone');
}
