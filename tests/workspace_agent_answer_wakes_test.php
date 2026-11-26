<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the refusal of an application agent's answer that would not
 * wake the agent that asked: the answer route asks the same rule that makes
 * requests (ws_agent_wake_matches()), as an answer to the asker's message
 * written by an application.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function workspace_agent_answer_wakes_asker($rule)
{
	return array('id' => 4, 'kind' => 'app', 'handle' => 'k1', 'app_id' => 7, 'wake_rule' => $rule, 'wake_prefix' => '', 'reach_from' => 0);
}

// An asker woken only when named: an answer without its name is refused,
// and the refusal names the handle and the two ways on.
function test_workspace_agent_answer_wakes_mention_unnamed_refused()
{
	$said = ws_agent_answer_wake_refusal(workspace_agent_answer_wakes_asker('mention'), 'K1, the PR is merged.');

	pg_assert_contains('@k1', $said);
	pg_assert_contains('Begin the answer with @k1', $said);
	pg_assert_contains('no_answer', $said);
}

// Named by its handle or its application's tag, the answer goes.
function test_workspace_agent_answer_wakes_mention_named_taken()
{
	$asker = workspace_agent_answer_wakes_asker('mention');

	pg_assert_same('', ws_agent_answer_wake_refusal($asker, '@k1 the PR is merged.'));
	pg_assert_same('', ws_agent_answer_wake_refusal($asker, '<@app:7> the PR is merged.'));
	pg_assert_true(ws_agent_answer_wake_refusal($asker, '<@app:8> the PR is merged.') !== '', 'another application\'s tag is no name of the asker');
}

// Every and prefix rules never match an application's words: refused too.
function test_workspace_agent_answer_wakes_all_rule_unnamed_refused()
{
	pg_assert_true(ws_agent_answer_wake_refusal(workspace_agent_answer_wakes_asker('all'), 'Merged.') !== '', 'all wakes on no application\'s words');
}

// An asker woken by answers to its messages is reached without its name.
function test_workspace_agent_answer_wakes_replies_taken()
{
	pg_assert_same('', ws_agent_answer_wake_refusal(workspace_agent_answer_wakes_asker('replies'), 'Merged.'));
}

// A person asked, or the asker is paused or no longer allowed (no row read):
// nothing is refused.
function test_workspace_agent_answer_wakes_no_asker_row_taken()
{
	pg_assert_same('', ws_agent_answer_wake_refusal(null, 'Merged.'));
}
