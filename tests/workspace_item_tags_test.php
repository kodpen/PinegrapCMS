<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the tags of a channel item that ask an assistant or wake an
 * agent (includes/workspace/messages.php): the tags read from a text, the
 * tags an edit adds, who an item asks as, and that a message with a file,
 * an edit, a note and a decision reach the ask.
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

function test_ws_message_tags()
{
	pg_assert_same(array('@prmerge', '<@app:12>', '@ai'), ws_message_tags('@prmerge look <@app:12>, then @AI and @ai again'), 'each once, in order, @words lower-case');
	pg_assert_same(array('@elcin'), ws_message_tags('(@elcin) mail me at a@b.com'), 'an e-mail address is no tag');
	pg_assert_same(array('@mimar'), ws_message_tags("line\n@mimar: here"), 'a tag at a line start, its colon apart');
	pg_assert_same(array('@ci-bot'), ws_message_tags('ask @ci-bot.'), 'a hyphen kept, the full stop left');
	pg_assert_same(array('@ali'), ws_message_tags('@ali <@user:3>'), 'a person\'s tag asks nobody');
	pg_assert_same(array(), ws_message_tags(''), 'no text');
}

function test_ws_message_tags_added()
{
	pg_assert_same('', ws_message_tags_added('@prmerge look', '@PRMerge look again'), 'a tag kept asks nobody again');
	pg_assert_same('@elcin', ws_message_tags_added('@prmerge look', '@prmerge and @elcin look'), 'only the added tag');
	pg_assert_same('<@app:7> @ai', ws_message_tags_added('', 'hi <@app:7> and @ai'), 'a new item: all its tags');
	pg_assert_same('', ws_message_tags_added('@a @b', '@b'), 'a tag taken out asks nobody');
}

// The added tags alone wake by mention, not by an agent's prefix, all or
// replies rule (ws_agent_after_send($mentions_only)).
function test_ws_message_tags_added_wake_by_mention()
{
	$row = array('handle' => 'prmerge', 'app_id' => 4, 'wake_rule' => 'replies');
	$added = ws_message_tags_added('fix it', 'fix it @prmerge');

	pg_assert_true(ws_agent_wake_matches($row, $added, false, true), 'the added handle wakes');
	pg_assert_false(ws_agent_wake_matches($row, ws_message_tags_added('@x', '@x @y'), false, true), 'another tag does not');
	pg_assert_false(ws_agent_wake_matches(array('wake_rule' => 'all', 'handle' => 'z') + $row, '@y', false, true), 'all does not fire on an edit');
}

function test_ws_message_by_app()
{
	pg_assert_false(ws_message_by_app(0), 'a person');
	pg_assert_true(ws_message_by_app(7), 'an application\'s key');
	pg_assert_true(ws_message_by_app(0, 3), 'an OAuth connection');
	pg_assert_same(array('claude' => false, 'agent' => false, 'ai' => false, 'agents' => array(), 'models' => false), ws_message_asked_none());
}

function test_ws_item_tags_are_wired()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	$actions = $read('includes/workspace/actions.php');
	pg_assert_contains("ws_message_ask_added(\$viewer, ws_channel(\$message['channel_id']), \$message['id'], (string) \$message['body'], (string) \$edited['body']);", $actions, 'the panel\'s edit');
	pg_assert_contains("\$asked = (array) (\$result['asked'] ?? array());\n\n            if (!empty(\$asked['claude'])", $actions, 'the file manager\'s attach');

	$api = $read('includes/workspace/api_resources.php');
	pg_assert_contains("ws_message_ask_added(\$viewer, ws_channel(\$message['channel_id']), \$message['id'], (string) \$message['body'], (string) \$row['body'], \$as_app > 0)", $api, 'the API\'s edit');
	pg_assert_contains("ws_api_assistants_carry_on(ws_agent_after_app_send(\$viewer, \$channel, \$result['message_id'], (string) (\$params['text'] ?? '')));", $api, 'an application\'s file');
	pg_assert_contains('ws_message_ask_written($viewer, $channel, $row[\'id\'], (string) $row[\'body\'], ws_message_by_app($app_id))', $read('includes/workspace/api_canvas.php'), 'a canvas');

	pg_assert_contains("ws_message_ask_noted(\$viewer, \$channel, \$result['message_id'], \$rest);", $read('includes/workspace/commands.php'), '/note and /decision');
	pg_assert_contains("ws_message_ask_noted(\$viewer, \$channel, \$sent['message_id'], \$row['text']);", $read('includes/workspace/meetings.php'), 'a meeting\'s decisions');
}

// The other messages written for a person or an application (TW2): their
// tags ask once the message and its card are written.
function test_ws_item_tags_other_messages_wired()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $path);
	};

	pg_assert_contains("ws_message_ask_noted(\$viewer, \$target, \$sent['message_id'], (string) \$comment, ws_message_by_app(\$options['app_id'] ?? 0, \$options['via_client_id'] ?? 0));", $read('forward.php'), 'a forward\'s comment');
	pg_assert_contains("ws_message_ask_noted(\$viewer, \$channel, \$sent['message_id'], \$body, ws_message_by_app(\$app_id));\n\n    return array('ok' => true, 'error' => '', 'field' => '', 'message_id' => (int) \$sent['message_id'], 'approval_id' => \$approval_id);", $read('approvals.php'), 'an approval request, after its row');

	$status = $read('status.php');
	pg_assert_contains("ws_message_ask_noted(\$viewer, \$channel, \$sent['message_id'], \$body);", $status, 'a status update');
	pg_assert_contains("ws_message_ask_noted(\$viewer, \$channel, \$sent['message_id'], \$question, ws_message_by_app(\$app_id));", $status, 'a check-in question');

	$templates = $read('templates.php');
	pg_assert_contains("(string) \$template['welcome'], ws_message_by_app(\$app_id));", $templates, 'a template\'s welcome');
	pg_assert_contains("(string) \$template['pinned'], ws_message_by_app(\$app_id));", $templates, 'a template\'s pinned text');

	pg_assert_contains("ws_message_ask_noted(\$viewer, \$channel, \$sent['message_id'], \$body);", $read('scheduled.php'), 'a flow\'s Post a message');
}
