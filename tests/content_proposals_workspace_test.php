<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace request of a waiting content change
 * (includes/fn/content_proposals.php): where it is asked, who is asked,
 * what a yes or a no does once the request settles, and a withdrawn or
 * replaced change taking its request back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

// A request that would be asked: every condition met.
function _content_proposals_ws_test_ctx($over = array())
{
	return array_merge(array(
		'workspace'  => true,
		'channel_id' => 5,
		'channel'    => array('id' => 5, 'kind' => 'public', 'archived_at' => 0),
		'may_post'   => true,
		'approvers'  => array(1, 2),
	), $over);
}

// A13: asked in the named channel when everything is in place.
function test_content_proposals_ws_asks_in_the_channel()
{
	pg_assert_true(pg_content_proposals_ask_where(_content_proposals_ws_test_ctx()), 'every condition met');
	pg_assert_true(pg_content_proposals_ask_where(_content_proposals_ws_test_ctx(array('channel' => array('id' => 5, 'kind' => 'private', 'archived_at' => 0)))), 'a private channel');
}

// A13: the Workspace off asks nowhere.
function test_content_proposals_ws_workspace_off_asks_nowhere()
{
	pg_assert_false(pg_content_proposals_ask_where(_content_proposals_ws_test_ctx(array('workspace' => false))));
}

// A13: no channel named on the card asks nowhere.
function test_content_proposals_ws_no_channel_asks_nowhere()
{
	pg_assert_false(pg_content_proposals_ask_where(_content_proposals_ws_test_ctx(array('channel_id' => 0))));
}

// A13: a named channel that is archived or gone asks nowhere.
function test_content_proposals_ws_archived_or_gone_channel_asks_nowhere()
{
	pg_assert_false(pg_content_proposals_ask_where(_content_proposals_ws_test_ctx(array('channel' => null))), 'gone');
	pg_assert_false(pg_content_proposals_ask_where(_content_proposals_ws_test_ctx(array('channel' => array('id' => 5, 'kind' => 'public', 'archived_at' => 1700000000)))), 'archived');
	pg_assert_false(pg_content_proposals_ask_where(_content_proposals_ws_test_ctx(array('channel' => array('id' => 5, 'kind' => 'mailbox', 'archived_at' => 0)))), 'not a channel the card offers');
	pg_assert_false(pg_content_proposals_ask_where(_content_proposals_ws_test_ctx(array('channel' => array('id' => 6, 'kind' => 'public', 'archived_at' => 0)))), 'another channel');
}

// A13: an editor who cannot post there, or a channel without a designer,
// gets only the bell lines.
function test_content_proposals_ws_editor_who_cannot_post_asks_nowhere()
{
	pg_assert_false(pg_content_proposals_ask_where(_content_proposals_ws_test_ctx(array('may_post' => false))), 'the editor cannot post');
	pg_assert_false(pg_content_proposals_ask_where(_content_proposals_ws_test_ctx(array('approvers' => array()))), 'nobody to ask');
}

// A13: the approvers are the channel's members of roles 0 and 1, ten at most,
// never the editor.
function test_content_proposals_ws_approvers_designers_ten_at_most()
{
	$members = array(
		array('user_id' => 4, 'role' => 2),
		array('user_id' => 7, 'role' => 3),
		array('user_id' => 9, 'role' => 1),
		array('user_id' => 1, 'role' => 0),
	);

	pg_assert_same(array(9, 1), pg_content_proposals_approvers_pick($members, 4));
	pg_assert_same(array(1), pg_content_proposals_approvers_pick(array(array('user_id' => 1, 'role' => 0), array('user_id' => 9, 'role' => 1)), 9), 'the editor is never asked');

	$many = array();

	for ($i = 1; $i <= 14; $i++) {
		$many[] = array('user_id' => $i, 'role' => 1);
	}

	pg_assert_same(range(1, 10), pg_content_proposals_approvers_pick($many, 0));
	pg_assert_same(10, PG_CONTENT_PROPOSALS_ASK_MAX);
}

// A14: a designer's yes applies the change.
function test_content_proposals_ws_designer_yes_applies()
{
	pg_assert_same('apply', pg_content_proposals_settle_plan('approved', 1, 'pending'), 'designer');
	pg_assert_same('apply', pg_content_proposals_settle_plan('approved', 0, 'pending'), 'administrator');
}

// A14: anybody else's yes is only noted; only a designer may publish.
function test_content_proposals_ws_other_yes_only_noted()
{
	pg_assert_same('note', pg_content_proposals_settle_plan('approved', 2, 'pending'), 'manager');
	pg_assert_same('note', pg_content_proposals_settle_plan('approved', 3, 'pending'), 'user');
	pg_assert_same('note', pg_content_proposals_settle_plan('approved', null, 'pending'), 'nobody known');

	$steps = pg_content_proposals_noted(array('lines' => array('text n1')), 4, 'approved', 1700000000);
	pg_assert_same(array('text n1'), $steps['lines'], 'the lines stay');
	pg_assert_same(array(array('user_id' => 4, 'decision' => 'approved', 'at' => 1700000000)), $steps['noted']);

	$steps = pg_content_proposals_noted($steps, 5, 'rejected', 1700000100);
	pg_assert_same(2, count($steps['noted']), 'each answer is kept');
}

// A14: a no dismisses the change with the answer's text.
function test_content_proposals_ws_no_dismisses()
{
	pg_assert_same('dismiss', pg_content_proposals_settle_plan('rejected', 1, 'pending'));
	pg_assert_same('dismiss', pg_content_proposals_settle_plan('rejected', 0, 'pending'));
	pg_assert_same('note', pg_content_proposals_settle_plan('rejected', 2, 'pending'), 'a no of somebody no longer a designer');
	pg_assert_same('Too dark', pg_content_proposals_answer_note(array(
		array('user_id' => 3, 'decision' => 'pending', 'note' => ''),
		array('user_id' => 9, 'decision' => 'rejected', 'note' => 'Too dark'),
	), 9));
	pg_assert_same('', pg_content_proposals_answer_note(array(), 9), 'no answer row');
}

// A14, A15: a request settled otherwise, or about a change no longer
// waiting, does nothing.
function test_content_proposals_ws_settle_otherwise_nothing()
{
	foreach (array('withdrawn', 'expired', 'open') as $outcome) {
		pg_assert_same('none', pg_content_proposals_settle_plan($outcome, 0, 'pending'), $outcome);
	}

	foreach (array('applied', 'dismissed', 'stale', 'reverted') as $status) {
		pg_assert_same('none', pg_content_proposals_settle_plan('approved', 0, $status), $status);
		pg_assert_same('none', pg_content_proposals_settle_plan('rejected', 0, $status), $status);
	}
}

// A15: a withdrawn or replaced change takes its approval request back.
function test_content_proposals_ws_withdrawn_takes_request_back()
{
	pg_assert_true(pg_content_proposals_request_closes('withdraw', 31), 'withdrawn');
	pg_assert_true(pg_content_proposals_request_closes('replace', 31), 'replaced');
	pg_assert_false(pg_content_proposals_request_closes('insert', 0), 'a new change has none');
	pg_assert_false(pg_content_proposals_request_closes('none', 31), 'nothing changed');
	pg_assert_false(pg_content_proposals_request_closes('withdraw', 0), 'never asked');
}

// The wiring: one guarded call in ws_approval_settle(); the hold asks and
// takes back; the designer's list closes the request of what it decides.
function test_content_proposals_ws_wiring()
{
	$approvals = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

	pg_assert_same(1, substr_count($approvals, 'pg_content_proposals_settled('), 'one call in approvals.php');
	pg_assert_contains("if (function_exists('pg_content_proposals_settled')) {", $approvals, 'guarded');
	pg_assert_true(strpos($approvals, 'pg_content_proposals_settled(') > strpos($approvals, 'function ws_approval_settle('), 'inside the settle');
	pg_assert_true(strpos($approvals, 'pg_content_proposals_settled(') > strpos($approvals, "WHERE id = '\" . (int) \$approval['id'] . \"' AND closed_at = 0\");"), 'after the request is closed');

	pg_assert_contains('$created = ws_approval_create($viewer, $channel, array(', $module, 'the Workspace\'s own function opens it');
	pg_assert_contains("'<#page:' . (int) \$page_id . '>'", $module, 'the page is tagged');
	pg_assert_contains("'rule'      => 'any',", $module, 'rule any');
	pg_assert_contains("ws_approval_settle(\$approval, 'withdrawn', (int) \$actor_id);", $module, 'taken back through the settle');
	pg_assert_contains('pg_content_proposals_request_close($waiting, (int) $user[\'id\']);', $module, 'the hold takes the old request back');
	pg_assert_contains('pg_content_proposals_ask($user, $id, $page_id, $name);', $module, 'the hold asks');
	pg_assert_same(false, strpos($module, 'INSERT INTO ws_approvals'), 'no hand-written request');
}
