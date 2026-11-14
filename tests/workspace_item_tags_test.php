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

	pg_assert_contains("ws_message_ask_noted(\$viewer, \$target, \$sent['message_id'], (string) \$comment, \$by_app);", $read('forward.php'), 'a forward\'s comment');
	pg_assert_contains("ws_message_ask_noted(\$viewer, \$channel, \$sent['message_id'], \$body, ws_message_by_app(\$app_id));\n\n    return array('ok' => true, 'error' => '', 'field' => '', 'message_id' => (int) \$sent['message_id'], 'approval_id' => \$approval_id);", $read('approvals.php'), 'an approval request, after its row');

	$status = $read('status.php');
	pg_assert_contains("ws_message_ask_noted(\$viewer, \$channel, \$sent['message_id'], \$body);", $status, 'a status update');
	pg_assert_contains("ws_message_ask_noted(\$viewer, \$channel, \$sent['message_id'], \$question, ws_message_by_app(\$app_id));", $status, 'a check-in question');

	$templates = $read('templates.php');
	pg_assert_contains("(string) \$template['welcome'], ws_message_by_app(\$app_id));", $templates, 'a template\'s welcome');
	pg_assert_contains("(string) \$template['pinned'], ws_message_by_app(\$app_id));", $templates, 'a template\'s pinned text');

	pg_assert_contains("ws_message_ask_noted(\$viewer, \$channel, \$sent['message_id'], \$body);", $read('scheduled.php'), 'a flow\'s Post a message');
}

// A tag outside a message's body (a poll, a check-in answer, a forwarded
// copy, a task) wakes only in an agent channel, only when the channel's
// owner or a manager wrote it, never in a canvas.
function test_ws_message_item_tags_may()
{
	pg_assert_true(ws_message_item_tags_may(true, true), 'a manager in an agent channel');
	pg_assert_false(ws_message_item_tags_may(true, false), 'a member who does not manage the channel');
	pg_assert_false(ws_message_item_tags_may(false, true), 'a team channel');
	pg_assert_false(ws_message_item_tags_may(true, true, true), 'a canvas');
}

function test_ws_poll_tag_text()
{
	pg_assert_same("Which one?\n@prmerge\nB", ws_poll_tag_text('Which one?', array('@prmerge', 'B')), 'options as texts');
	pg_assert_same("Q\nA @elcin", ws_poll_tag_text('Q', array(array('id' => 3, 'label' => 'A @elcin'))), 'options as rows');
	pg_assert_same('', ws_message_tags_added(ws_poll_tag_text('Q @x', array('@y')), ws_poll_tag_text('Q @x', array('@y', 'C'))), 'an added plain option asks nobody');
	pg_assert_same('@z', ws_message_tags_added(ws_poll_tag_text('Q @x', array('@y')), ws_poll_tag_text('Q @x', array('@y', '@z'))), 'an added option\'s tag');
}

function test_ws_task_tag_text()
{
	pg_assert_same("Fix @prmerge\nsee @elcin", ws_task_tag_text(array('title' => 'Fix @prmerge', 'description' => 'see @elcin')));
	pg_assert_same('@elcin', ws_message_tags_added(ws_task_tag_text(array('title' => 'Fix @prmerge', 'description' => '')), ws_task_tag_text(array('title' => 'Fix @prmerge', 'description' => '@elcin'))), 'a description edit asks its added tag');
}

// Polls, check-in answers, forwarded copies, tasks and task notes (TW3):
// their tags go through the writer's check (ws_message_ask_item()).
function test_ws_item_tags_outside_body_wired()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $path);
	};

	$interact = $read('interact.php');
	pg_assert_contains("ws_message_ask_item(\$viewer, \$channel, (int) \$sent['message_id'], '', ws_poll_tag_text(\$checked['question'], \$options), ws_message_by_app(\$app_id));", $interact, 'a new poll');
	pg_assert_contains("ws_message_ask_item(\$viewer, ws_channel(\$poll['channel_id']), (int) \$message['id'], \$before, ws_poll_tag_text(\$question, \$options));", $interact, 'a poll\'s edit');
	pg_assert_contains("ws_message_ask_item(\$viewer, \$channel, (int) \$message['id'], \$before, \$text);", $read('status.php'), 'a check-in answer');
	pg_assert_contains("ws_message_ask_item(\$viewer, \$target, (int) \$sent['message_id'], (string) \$comment,", $read('forward.php'), 'a forward\'s copies');

	$tasks = $read('tasks.php');
	pg_assert_contains("ws_task_ask_tags(\$viewer, \$now, ws_task_tag_text(\$task), ws_task_tag_text(\$now), ws_message_by_app(\$app_id));", $tasks, 'a task\'s edit');
	pg_assert_contains("return ws_message_ask_item(\$viewer, ws_channel(\$message['channel_id']), (int) \$message['id'], \$old, \$new, \$by_app, \$assistants);", $tasks, 'hung on the task\'s card');

	$notes = $read('task_work.php');
	pg_assert_contains("ws_task_ask_tags(\$viewer, \$task, '', \$clean['body'], ws_message_by_app(\$app_id), false);", $notes, 'a task note, registry agents alone');
	pg_assert_contains("ws_task_ask_tags(\$viewer, \$task, (string) \$note['body'], \$clean['body'], false, false);", $notes, 'a task note\'s edit');

	pg_assert_contains("ws_task_ask_tags(\$viewer, \$made, '', ws_task_tag_text(\$made));", $read('actions.php'), 'a task made in the panel');
	pg_assert_contains("ws_task_ask_tags(\$viewer, \$made, '', ws_task_tag_text(\$made));", $read('commands.php'), '/task');
	pg_assert_contains("ws_api_assistants_carry_on(ws_task_ask_tags(\$viewer, \$made, '', ws_task_tag_text(\$made), ws_api_writer_app_id() > 0));", $read('api_resources.php'), 'a task made over the API');
}
