<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shares of a workflow table
 * (includes/workspace/wtable_shares.php): what a share may name, who may
 * make, change and take back a share, the levels, and what a flow's box may
 * do with a table whose owner is gone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_shares.php');

// The owner sharing with a colleague who is in the team, nothing shared yet.
function ws_wtable_shares_test_facts($facts = array())
{
	return $facts + array('access' => 'owner', 'problem' => '', 'type' => 'user', 'is_owner' => false, 'member' => true, 'readable' => false, 'existing' => false, 'count' => 0);
}

function ws_wtable_shares_test_error($facts = array())
{
	$refusal = ws_wtable_share_set_refusal(ws_wtable_shares_test_facts($facts));

	return ($refusal === null) ? '' : $refusal['error'] . '/' . $refusal['field'] . '/' . $refusal['status'];
}

function test_workspace_wtable_shares_levels()
{
	pg_assert_same(array('view', 'edit'), array_keys(ws_wtable_share_levels()), 'no run level: a table is not run');
}

function test_workspace_wtable_shares_input()
{
	pg_assert_same('', ws_wtable_share_input_problem('user', 7, 'edit'));
	pg_assert_same('', ws_wtable_share_input_problem('user', '7', 'view'));
	pg_assert_same('', ws_wtable_share_input_problem('channel', 3, 'view'));
	pg_assert_same('target_type', ws_wtable_share_input_problem('group', 3, 'view'));
	pg_assert_same('target_type', ws_wtable_share_input_problem(array('user'), 3, 'view'));
	pg_assert_same('target_id', ws_wtable_share_input_problem('user', 0, 'view'));
	pg_assert_same('target_id', ws_wtable_share_input_problem('user', '7.5', 'view'));
	pg_assert_same('target_id', ws_wtable_share_input_problem('user', 'x', 'view'));
	pg_assert_same('level', ws_wtable_share_input_problem('user', 7, 'run'), 'a flow\'s run level is no table level');
	pg_assert_same('level', ws_wtable_share_input_problem('user', 7, 'owner'));
	pg_assert_same('channel_level', ws_wtable_share_input_problem('channel', 3, 'edit'), 'a channel is shared at view only');
}

// Only the owner shares; somebody the table does not reach does not find it.
function test_workspace_wtable_shares_who_shares()
{
	pg_assert_same('', ws_wtable_shares_test_error());
	pg_assert_same('not_found//404', ws_wtable_shares_test_error(array('access' => '')), 'an administrator without a share');
	pg_assert_same('forbidden//403', ws_wtable_shares_test_error(array('access' => 'edit')), 'a person shared at edit does not hand it on');
	pg_assert_same('forbidden//403', ws_wtable_shares_test_error(array('access' => 'view')));
}

function test_workspace_wtable_shares_targets()
{
	pg_assert_same('target_type_invalid/target_type/422', ws_wtable_shares_test_error(array('problem' => 'target_type')));
	pg_assert_same('level_invalid/level/422', ws_wtable_shares_test_error(array('problem' => 'level')));
	pg_assert_same('channel_level/level/422', ws_wtable_shares_test_error(array('problem' => 'channel_level')));
	pg_assert_same('target_is_owner/target_id/422', ws_wtable_shares_test_error(array('is_owner' => true)));
	pg_assert_same('target_not_member/target_id/422', ws_wtable_shares_test_error(array('member' => false)), 'a site member or somebody who left');
	pg_assert_same('channel_unreadable/target_id/422', ws_wtable_shares_test_error(array('type' => 'channel')));
	pg_assert_same('', ws_wtable_shares_test_error(array('type' => 'channel', 'readable' => true)));
}

// The limit stops a new share, not a change of level of one made before.
function test_workspace_wtable_shares_limit()
{
	pg_assert_same('', ws_wtable_shares_test_error(array('count' => WS_WTABLE_MAX_SHARES - 1)));
	pg_assert_same('too_many_shares//409', ws_wtable_shares_test_error(array('count' => WS_WTABLE_MAX_SHARES)));
	pg_assert_same('', ws_wtable_shares_test_error(array('count' => WS_WTABLE_MAX_SHARES, 'existing' => true)));
}

// A table whose owner is gone pauses the flow's box; one not reached far
// enough refuses it.
function test_workspace_wtable_shares_flow_gate()
{
	pg_assert_same('paused', ws_wtable_flow_gate(array('present' => false, 'access' => '', 'need' => 'view')));
	pg_assert_same('paused', ws_wtable_flow_gate(array('present' => false, 'access' => 'owner', 'need' => 'edit')), 'whatever was reached before');
	pg_assert_same('', ws_wtable_flow_gate(array('present' => true, 'access' => 'owner', 'need' => 'edit')));
	pg_assert_same('', ws_wtable_flow_gate(array('present' => true, 'access' => 'view', 'need' => 'view')));
	pg_assert_same('refused', ws_wtable_flow_gate(array('present' => true, 'access' => 'view', 'need' => 'edit')));
	pg_assert_same('refused', ws_wtable_flow_gate(array('present' => true, 'access' => '', 'need' => 'view')));
	pg_assert_same('', ws_wtable_flow_gate(array('present' => true, 'access' => 'view', 'need' => 'other')), 'an unknown need is read as view');
}

// The writes: the owner's checks before the statement, a new person told,
// and the bell line written as a line of its own.
function test_workspace_wtable_shares_writes()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_shares.php');

	preg_match('/^function ws_wtable_share_set\(\$viewer, \$table, \$data\)\n\{\n(.*?)\n\}\n/ms', $source, $match);
	$body = isset($match[1]) ? $match[1] : '';
	pg_assert_true(strpos($body, 'ws_wtable_share_set_check(') < strpos($body, 'INSERT INTO ws_wtable_shares'), 'checked before written');
	pg_assert_contains("if (\$created && (\$type === 'user'))", $body, 'only a person newly shared with is told');

	preg_match('/^function ws_wtable_share_tell\(.*?\)\n\{\n(.*?)\n\}\n/ms', $source, $match);
	pg_assert_contains("'separate' => true", isset($match[1]) ? $match[1] : '');

	preg_match('/^function ws_wtable_share_remove_check\(.*?\)\n\{\n(.*?)\n\}\n/ms', $source, $match);
	pg_assert_contains("ws_wtable_may(\$access, 'manage')", isset($match[1]) ? $match[1] : '', 'only the owner takes a share back');

	$notify = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');
	pg_assert_contains("case 'wtable_shared':", $notify, 'the bell describes the line');
}
