<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where an assistant asked in the workspace may read and answer,
 * that need no database: the answer goes in through the owner of its
 * application where that person may write and through the person who asked
 * where the owner is not in the channel or the discussion; Claude's
 * application reads such a place through the person who asked while the
 * request is open; and Claude's and Pinegrap AI's answers and refusals are
 * written that way.
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

function workspace_assistant_reach_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	// The calls, not the comments that name them.
	return preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// The owner of the application writes where they may; the person who asked
// stands in where the owner may not, and nobody writes where neither may.
function test_workspace_assistant_reach_writer()
{
	$owner = array('id' => 4, 'role' => 0, 'member' => true);
	$asker = array('id' => 9, 'role' => 3, 'member' => true);
	$gone = array('id' => 4, 'role' => 0, 'member' => false);
	$channel = array('id' => 30, 'kind' => 'public', 'archived_at' => 0);

	pg_assert_same($owner, ws_assistant_writer($owner, $asker, $channel), 'the owner first, where they may write');
	pg_assert_same($asker, ws_assistant_writer($gone, $asker, $channel), 'an owner who may not write: the person who asked');
	pg_assert_same($asker, ws_assistant_writer(null, $asker, $channel), 'no owner: the person who asked');
	pg_assert_same($owner, ws_assistant_writer($owner, null, $channel), 'the person who asked is gone: the owner');
	pg_assert_same(null, ws_assistant_writer($gone, array('member' => false) + $asker, $channel), 'neither is in the team');
	pg_assert_same(null, ws_assistant_writer(null, null, $channel), 'nobody');
	pg_assert_same(null, ws_assistant_writer($owner, $asker, array('archived_at' => 1700000000) + $channel), 'nothing is written in an archived channel');
	pg_assert_same(null, ws_assistant_writer($owner, $asker, null), 'no channel');
}

// Claude's answer and its refusal go in through ws_assistant_writer(), with
// the person who asked as the stand-in, and the owner is no longer required
// to write there.
function test_workspace_assistant_reach_claude_writes_through_the_writer()
{
	$channel = workspace_assistant_reach_test_source('ws_api_claude_channel');

	pg_assert_contains("ws_assistant_writer(\$viewer, ws_change_viewer_for_id((int) \$row['requested_by']), \$channel)", $channel, 'the owner, then the person who asked');
	pg_assert_false(strpos($channel, 'ws_can_post_channel($viewer, $channel)') !== false, 'the owner is not required to write there');
	pg_assert_true(strpos($channel, 'ws_claude_channel_allowed($channel)') < strpos($channel, 'ws_assistant_writer('), 'where Claude may be asked at all comes first');

	$answer = workspace_assistant_reach_test_source('ws_api_claude_answer');
	$checked = strpos($answer, "ws_message_send_check(\$place['writer'], \$channel, \$text, \$options)");
	$sent = strpos($answer, "ws_message_send(\$place['writer'], \$channel, \$text, \$options)");

	pg_assert_true($checked !== false, 'the dry run asks the same writer');
	pg_assert_true(($sent !== false) && ($checked < $sent), 'the answer is written through the writer');

	$fail = workspace_assistant_reach_test_source('ws_api_claude_fail');

	pg_assert_contains("ws_assistant_writer(\$viewer, ws_change_viewer_for_id((int) \$row['requested_by']), \$channel)", $fail, 'a refusal goes in the same way');
	pg_assert_contains('ws_message_send($writer, $channel,', $fail);
}

// Pinegrap AI's answer and its refusal go in the same way.
function test_workspace_assistant_reach_ai_writes_through_the_writer()
{
	$deliver = workspace_assistant_reach_test_source('ws_ai_deliver');
	$writer = strpos($deliver, "ws_assistant_writer(ws_ai_owner(), ws_ai_viewer((int) \$row['requested_by']), \$channel)");

	pg_assert_true($writer !== false, 'the owner, then the person who asked');
	pg_assert_true($writer < strpos($deliver, 'ws_message_send($writer, $channel, $text,'), 'the answer is written through the writer');
	pg_assert_false(strpos($deliver, 'ws_can_post_channel($owner, $channel)') !== false, 'the owner is not required to write there');

	$fail = workspace_assistant_reach_test_source('ws_ai_finish_fail');

	pg_assert_contains("ws_assistant_writer(ws_ai_owner(), ws_ai_viewer((int) \$row['requested_by']), \$channel)", $fail, 'a refusal goes in the same way');
	pg_assert_contains('ws_message_send($writer, $channel,', $fail);
}

// The context Claude reads: through the person who asked only for Claude's
// application, only where Claude may be asked and only where the owner does
// not read; anywhere else the owner reads it as every endpoint does.
function test_workspace_assistant_reach_claude_context()
{
	$context = workspace_assistant_reach_test_source('ws_api_channel_context');
	$asker = strpos($context, 'ws_claude_channel_asker($channel)');

	pg_assert_true($asker !== false, 'the person who asked is looked for');
	pg_assert_contains('ws_api_is_claude() && ws_claude_channel_allowed($channel) && !ws_app_reads_channel($owner, $channel)', $context, 'Claude\'s application, where Claude may be asked, where the owner does not read');
	pg_assert_true(strpos($context, 'ws_api_channel_or_404($viewer, $params[\'id\'])') > $asker, 'otherwise the ordinary check');

	$lookup = workspace_assistant_reach_test_source('ws_claude_channel_asker');

	pg_assert_contains("status IN ('queued', 'sent', 'running')", $lookup, 'only while the request is open');
	pg_assert_contains('ws_claude_only()', $lookup, 'Claude\'s requests only');
	pg_assert_contains("ws_claude_only('r')", $lookup, 'also for a run on a task of the channel');
	pg_assert_contains('ws_app_reads_channel($asker, $channel)', $lookup, 'never through inspection');
}
