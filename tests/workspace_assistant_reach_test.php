<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where an assistant asked in the workspace may read and answer,
 * that need no database: the answer goes in through the owner of its
 * application where that person may write and through the person who asked
 * where the owner is not in the channel or the discussion; Claude's
 * application reads such a place through the person who asked while the
 * request is open and Claude is switched on; only the application's key used
 * as itself counts as Claude; and Claude's and Pinegrap AI's answers and
 * refusals are written that way.
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

	// The work is carried on by whichever screen asks for it: a private
	// channel opened for inspection in that session lets nobody in.
	pg_assert_contains("ws_can_post_channel(array('role' => 3) + \$reader, \$channel)", workspace_assistant_reach_test_source('ws_assistant_writer'), 'never through inspection');
}

// The channel is read with the eyes of whoever asked the request being worked
// on, wherever it came from (asked in the channel, or a run on one of its
// tasks), then the others' oldest first; each person once.
function test_workspace_assistant_reach_asker_order()
{
	$rows = array(
		array('id' => '5', 'requested_by' => '3', 'status' => 'queued'),
		array('id' => '2', 'requested_by' => '3', 'status' => 'sent'),
		array('id' => '4', 'requested_by' => '0', 'status' => 'queued'),
		array('id' => '9', 'requested_by' => '7', 'status' => 'running'),
		array('id' => '8', 'requested_by' => '6', 'status' => 'queued'),
	);

	pg_assert_same(array(7, 3, 6), ws_claude_asker_order($rows), 'running first, then oldest first, each once, nobody for 0');
	pg_assert_same(array(), ws_claude_asker_order(array()), 'no request');
	pg_assert_same(array(4), ws_claude_asker_order(array(array('id' => 1, 'requested_by' => 4, 'status' => 'queued'))), 'one request');
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

// Without its application (none chosen, or the chosen one deleted) Pinegrap
// AI writes nothing in the channel: the person who asked must not stand in
// for an application that does not exist, which would post the words as that
// person's own message.
function test_workspace_assistant_reach_ai_writes_only_with_its_application()
{
	foreach (array('ws_ai_deliver', 'ws_ai_finish_fail') as $function) {
		$source = workspace_assistant_reach_test_source($function);
		$app = strpos($source, '$app = ws_ai_app();');
		$writer = strpos($source, 'ws_assistant_writer(');

		pg_assert_true($app !== false, $function . ': the application is looked up');
		pg_assert_true(($writer !== false) && ($app < $writer), $function . ': before a writer is chosen');
		pg_assert_contains("'app_id'    => (int) \$app['id']", $source, $function . ': the message carries that application');
		pg_assert_false(strpos($source, "'app_id'    => (int) ws_ai_config()['app_id']") !== false, $function . ': not the configured id, which may name no application');
	}

	pg_assert_contains('($channel && $app && ws_ai_channel_allowed($channel))', workspace_assistant_reach_test_source('ws_ai_finish_fail'), 'no application: the refusal is kept on the request only');
	pg_assert_contains('$writer = $app ? ws_assistant_writer(', workspace_assistant_reach_test_source('ws_ai_deliver'), 'no application: the answer is refused');
}

// The context Claude reads: through the person who asked only for Claude's
// application, only where Claude may be asked and only where the owner does
// not read; anywhere else the owner reads it as every endpoint does. The
// channel's customer is read the same way.
function test_workspace_assistant_reach_claude_context()
{
	foreach (array('ws_api_channel_context', 'ws_api_channel_customer') as $handler) {
		pg_assert_contains('ws_api_assistant_channel_reader($params[\'id\'])', workspace_assistant_reach_test_source($handler), $handler . ' reads its channel as an assistant\'s read');
	}

	$context = workspace_assistant_reach_test_source('ws_api_assistant_channel_reader');
	$asker = strpos($context, 'ws_claude_channel_asker($channel)');

	pg_assert_true($asker !== false, 'the person who asked is looked for');
	pg_assert_contains('ws_api_is_claude() && ws_claude_channel_allowed($channel) && !ws_app_reads_channel($owner, $channel)', $context, 'Claude\'s application, where Claude may be asked, where the owner does not read');
	pg_assert_true(strpos($context, 'ws_api_channel_or_404($viewer, $id)') > $asker, 'otherwise the ordinary check');

	$lookup = workspace_assistant_reach_test_source('ws_claude_channel_asker');

	pg_assert_contains("ws_claude_config()['enabled']", $lookup, 'nothing while Claude is switched off');
	pg_assert_contains("status IN ('queued', 'sent', 'running')", $lookup, 'only while the request is open');
	pg_assert_contains('ws_claude_only()', $lookup, 'Claude\'s requests only');
	pg_assert_contains("ws_claude_only('r')", $lookup, 'also for a run on a task of the channel');
	pg_assert_contains('ws_claude_asker_order($rows)', $lookup, 'both kinds of request in one order');
	pg_assert_contains('ws_app_reads_channel($asker, $channel)', $lookup, 'never through inspection');
}

// Claude is the application's key used as itself: a person signed in through
// that application on a device or over OAuth is not Claude, here nor at the
// queue's endpoints.
function test_workspace_assistant_reach_claude_is_the_key()
{
	pg_assert_contains('api_current_device() === null', workspace_assistant_reach_test_source('ws_api_is_claude'), 'no device session');
	pg_assert_contains('if (!ws_api_is_claude())', workspace_assistant_reach_test_source('ws_api_claude_guard'), 'the queue asks the same');
}
