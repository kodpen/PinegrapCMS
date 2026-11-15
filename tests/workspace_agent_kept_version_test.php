<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that an agent request reaches no message of an earlier version of a
 * cleared conversation (includes/workspace/eras.php): Ask again is not
 * offered on such a message, an agent's emoji is refused there, the clear
 * cancels the open requests it freezes, and claim, answer, fail and the
 * reaction check read the floor.
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

function workspace_agent_kept_version_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// Ask again is not offered when the request's message is deleted or kept.
function test_workspace_agent_kept_version_no_retry()
{
	$asker = array('id' => 5, 'role' => 3);
	$model = array('kind' => 'model', 'status' => 'active', 'door_kind' => '');
	$failed = array('status' => 'failed', 'requested_by' => 5, 'message_id' => 10, 'channel_id' => 2);

	pg_assert_true(ws_agent_activity_actions($asker, $failed, $model, true)['retry'], 'a message of the current conversation');
	pg_assert_same(false, ws_agent_activity_actions($asker, $failed, $model, false)['retry'], 'a kept or deleted message');
	pg_assert_true(ws_agent_activity_actions($asker, array('status' => 'queued') + $failed, $model, false)['cancel'], 'a waiting one is still cancelled');
}

// The refusal Ask again gives is the one an answer meets.
function test_workspace_agent_kept_version_refusal()
{
	$kept = array('id' => 10, 'channel_id' => 2, 'deleted_at' => 0);

	pg_assert_same(ws_message_parent_refusal($kept, 2, 10), ws_agent_request_message_refusal($kept, 10));
	pg_assert_same('', ws_agent_request_message_refusal($kept, 9));
	pg_assert_same(ws_message_parent_refusal(null, 2, 0), ws_agent_request_message_refusal(null, 0), 'a message that is gone');
	pg_assert_same(ws_message_parent_refusal(array('deleted_at' => 5) + $kept, 2, 0), ws_agent_request_message_refusal(array('deleted_at' => 5) + $kept, 0));
}

// No emoji on a message of an earlier version.
function test_workspace_agent_kept_version_emoji()
{
	$asked = array('id' => 40, 'channel_id' => 2, 'sender_kind' => 'user', 'sender_id' => 7);

	pg_assert_same('', ws_agent_emoji_problem('🎉', $asked, 7, false));
	pg_assert_same(ws_message_parent_refusal(array('deleted_at' => 0) + $asked, 2, 40), ws_agent_emoji_problem('🎉', $asked, 7, true));
}

// The clear cancels what it freezes; claim, answer and fail read the floor
// before they write; the marks and the reaction check stay off a kept message.
function test_workspace_agent_kept_version_guards()
{
	$clear = workspace_agent_kept_version_test_source('ws_channel_clear');

	pg_assert_contains('ws_agent_requests_freeze(', $clear);
	pg_assert_true(strpos($clear, 'ws_agent_requests_freeze(') > strpos($clear, 'era_floor'), 'after the floor moved');

	$freeze = workspace_agent_kept_version_test_source('ws_agent_requests_freeze') . workspace_agent_kept_version_test_source('ws_agent_request_freeze')
		. workspace_agent_kept_version_test_source('ws_agent_request_cancel');

	pg_assert_contains("status = 'cancelled'", $freeze);
	pg_assert_contains("'queued', 'sent', 'running'", $freeze);
	pg_assert_contains('ws_agent_request_cleared_reason()', $freeze);
	pg_assert_true(strpos($freeze, 'ws_agent_react(') === false && strpos($freeze, 'ws_message_touch(') === false, 'the kept message is not touched');

	foreach (array('ws_api_agent_request_claim', 'ws_api_agent_request_answer') as $function) {
		$source = workspace_agent_kept_version_test_source($function);

		pg_assert_contains('ws_api_agent_request_kept_stop(', $source, $function);
		pg_assert_true(strpos($source, 'ws_api_agent_request_kept_stop(') < strpos($source, 'api_dry_run_stop('), $function . ' refuses before the dry run');
		pg_assert_true(strpos($source, 'ws_api_agent_request_kept_stop(') < strpos($source, 'ws_agent_react('), $function . ' leaves no mark first');
	}

	$stop = workspace_agent_kept_version_test_source('ws_api_agent_request_kept_stop');

	pg_assert_contains('ws_agent_request_in_past(', $stop);
	pg_assert_contains('api_dry_run_requested()', $stop, 'a dry run cancels nothing');
	pg_assert_contains('ws_agent_request_freeze(', $stop);

	$fail = workspace_agent_kept_version_test_source('ws_api_agent_request_fail');

	pg_assert_contains('ws_agent_request_in_past(', $fail);

	pg_assert_contains('ws_message_kept(', workspace_agent_kept_version_test_source('ws_agent_react'));
	pg_assert_contains('ws_message_in_past(', workspace_agent_kept_version_test_source('ws_reaction_check'));
	pg_assert_contains('ws_agent_request_message_check(', workspace_agent_kept_version_test_source('ws_agent_activity_retry'));
	pg_assert_contains('ws_agent_request_message_check(', workspace_agent_kept_version_test_source('ws_agent_activity_block'));
	pg_assert_contains('ws_agent_request_message_refusal(', workspace_agent_kept_version_test_source('ws_agent_request_message_check'));
}
