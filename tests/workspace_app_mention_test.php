<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the event that tells an application it was mentioned in a
 * workspace channel (workspace.app.mentioned) that need no database: which
 * applications a message tells, what the event carries, that it is queued
 * for the mentioned application's own subscriptions alone, and where the
 * message write and the edit announce it.
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

// The outbound webhook module asks for init.php; behind its gate it only
// declares functions, and nothing here sends anything.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function workspace_app_mention_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	// The calls, not the comments that name them.
	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// Each application a text mentions, once, in the order it first appears; a
// person, a department, a record and an application written with # are not
// applications mentioned.
function test_workspace_app_mention_ids()
{
	$tokens = ws_tokens('<@app:7> can you look at <#order:5>? <@user:2> <@app:3> and <@app:7> again, <@dept:4>');

	pg_assert_same(array(7, 3), ws_mentioned_app_ids($tokens), 'each once, in order');
	pg_assert_same(array(), ws_mentioned_app_ids(ws_tokens('<#app:9> <@app:0> hello')), 'no application mentioned with @');
	pg_assert_same(array(), ws_mentioned_app_ids(array()), 'no tags');
	pg_assert_same(array(12), ws_mentioned_app_ids(array(array('sigil' => '@', 'type' => 'app', 'id' => '12'))), 'an id read as text');
}

// The application that wrote the message is never told of its own words,
// and the assistants that answer inside the site are not told at all.
function test_workspace_app_mention_audience()
{
	$tokens = ws_tokens('<@app:7> <@app:3> <@app:5> <@app:7>');

	pg_assert_same(array(7, 3, 5), ws_app_mention_audience($tokens, 0, array()), 'a person wrote it: every application it mentions');
	pg_assert_same(array(3, 5), ws_app_mention_audience($tokens, 7, array()), 'application 7 wrote it: not itself');
	pg_assert_same(array(7, 3), ws_app_mention_audience($tokens, 0, array(5, 0)), 'an assistant is left out; no assistant set up is 0');
	pg_assert_same(array(), ws_app_mention_audience(ws_tokens('<@app:7> <@app:7>'), 7, array()), 'only itself, twice');
	pg_assert_same(array(3), ws_app_mention_audience($tokens, '7', array('5')), 'ids read as text');
}

// An application hears its mentions while it is active, its key has not run
// out and it may read the workspace: workspace:read, or workspace:write,
// which includes it.
function test_workspace_app_mention_hears()
{
	pg_assert_true(ws_app_hears_mentions(array('status' => 'active', 'scopes' => '["tasks:read","workspace:read"]')), 'reads the workspace');
	pg_assert_true(ws_app_hears_mentions(array('status' => 'active', 'scopes' => '["workspace:write"]')), 'writing includes reading');
	pg_assert_false(ws_app_hears_mentions(array('status' => 'active', 'scopes' => '["tasks:write","webhooks:manage"]')), 'no workspace permission');
	pg_assert_false(ws_app_hears_mentions(array('status' => 'disabled', 'scopes' => '["workspace:write"]')), 'switched off');
	pg_assert_false(ws_app_hears_mentions(array('status' => 'active', 'scopes' => 'workspace:read')), 'scopes that are not a list');
	pg_assert_false(ws_app_hears_mentions(array()), 'nothing known');

	// The API refuses a key once its end has passed (api_authenticate()), so
	// the words stop reaching it too; the moment of the end itself still reads.
	$key = array('status' => 'active', 'expires_at' => '2000', 'scopes' => '["workspace:read"]');

	pg_assert_true(ws_app_hears_mentions($key, 1999), 'a key that runs out later');
	pg_assert_true(ws_app_hears_mentions($key, 2000), 'the moment it runs out');
	pg_assert_false(ws_app_hears_mentions($key, 2001), 'a key that ran out');
	pg_assert_true(ws_app_hears_mentions(array('expires_at' => '0') + $key, 2001), 'a key that never runs out');
	pg_assert_false(ws_app_hears_mentions(array('expires_at' => (string) (time() - 60)) + $key), 'ran out a minute ago, judged now');
}

// Nobody reads for an application whose owner is gone, and a public channel
// is read by every member of the team. A private channel opened for
// inspection is never read on an application's behalf: the reader is asked
// as somebody who cannot inspect.
function test_workspace_app_mention_reads_channel()
{
	$member = array('id' => 4, 'role' => 0, 'member' => true);
	$public = array('id' => 9, 'kind' => 'public', 'archived_at' => 0);

	pg_assert_true(ws_app_reads_channel($member, $public), 'a public channel');
	pg_assert_false(ws_app_reads_channel(array('id' => 4, 'role' => 0, 'member' => false), $public), 'an owner outside the team');
	pg_assert_false(ws_app_reads_channel(null, $public), 'no owner');
	pg_assert_contains("ws_can_read_channel(array('role' => 3) + \$reader, \$channel)", workspace_app_mention_test_source('ws_app_reads_channel'), 'never through inspection');
}

// What the event carries: the message as workspace.message.created has it,
// the application mentioned, and where the message stands.
function test_workspace_app_mention_payload()
{
	$message = array(
		'id' => '501', 'channel_id' => '9', 'parent_id' => '0', 'sender_kind' => 'user', 'sender_id' => '4',
		'kind' => 'message', 'body' => '<@app:7> how many orders today?', 'task_id' => '0',
	);
	$channel = array('id' => '9', 'name' => 'sales', 'kind' => 'public');

	pg_assert_same(array(
		'id'               => 501,
		'channel_id'       => 9,
		'channel'          => 'sales',
		'channel_kind'     => 'public',
		'thread'           => null,
		'parent_id'        => 0,
		'sender_id'        => 4,
		'app_id'           => 0,
		'mentioned_app_id' => 7,
		'kind'             => 'message',
		'text'             => '@Acme how many orders today?',
		'body'             => '<@app:7> how many orders today?',
		'task_id'          => 0,
		'edited'           => false,
	), ws_app_mention_payload($message, $channel, null, 7, '@Acme how many orders today?'));

	// Written by another application, in a discussion, as an answer to a
	// message, newly mentioned by an edit.
	$by_app = array('sender_kind' => 'app', 'sender_id' => '3', 'parent_id' => '480', 'task_id' => '12', 'kind' => 'decision') + $message;
	$discussion = array('id' => '40', 'name' => 'Refund for #1045', 'kind' => 'thread');
	$thread = array('channel_id' => '40', 'parent_channel_id' => '9', 'message_id' => '470');
	$payload = ws_app_mention_payload($by_app, $discussion, $thread, '7', 'text', true);

	pg_assert_same(40, $payload['channel_id'], 'answered where it was written');
	pg_assert_same('thread', $payload['channel_kind']);
	pg_assert_same(array('channel_id' => 9, 'message_id' => 470), $payload['thread'], 'the channel and the message the discussion is about');
	pg_assert_same(480, $payload['parent_id']);
	pg_assert_same(0, $payload['sender_id'], 'no person wrote it');
	pg_assert_same(3, $payload['app_id'], 'the application that wrote it');
	pg_assert_same(7, $payload['mentioned_app_id']);
	pg_assert_same(12, $payload['task_id']);
	pg_assert_same(true, $payload['edited']);
}

// A mention goes to the application it names and nobody else; a mention
// that names none goes to nobody; every other event to every subscriber.
function test_workspace_app_mention_event_app_id()
{
	pg_assert_same(7, ws_webhook_event_app_id('workspace.app.mentioned', array('mentioned_app_id' => 7, 'app_id' => 3)));
	pg_assert_same(7, ws_webhook_event_app_id('workspace.app.mentioned', array('mentioned_app_id' => '7')), 'an id read as text');
	pg_assert_same(-1, ws_webhook_event_app_id('workspace.app.mentioned', array('app_id' => 3)), 'no application named: nobody, not everybody');
	pg_assert_same(-1, ws_webhook_event_app_id('workspace.app.mentioned', array('mentioned_app_id' => 0)));
	pg_assert_same(-1, ws_webhook_event_app_id('workspace.app.mentioned', array('mentioned_app_id' => array(7))), 'a list is no id');
	pg_assert_same(-1, ws_webhook_event_app_id('workspace.app.mentioned', array('mentioned_app_id' => true)), 'true is no id');
	pg_assert_same(0, ws_webhook_event_app_id('workspace.message.created', array('app_id' => 7)), 'a message: every subscriber');
	pg_assert_same(0, ws_webhook_event_app_id('order.created', array('id' => 1)));

	pg_assert_same(7, api_webhook_event_app_id('workspace.app.mentioned', array('mentioned_app_id' => 7)), 'the queue asks the workspace');
	pg_assert_same(0, api_webhook_event_app_id('order.created', array('id' => 1)));
}

// The event is in the workspace's catalogue, says how to answer and fits
// the queue's column.
function test_workspace_app_mention_in_the_catalogue()
{
	$events = ws_webhook_events();

	pg_assert_true(isset($events['workspace.app.mentioned']), 'listed');
	pg_assert_contains('POST /workspace/channels/{channel_id}/messages', (string) ($events['workspace.app.mentioned'] ?? ''), 'says how to answer');
	pg_assert_true(strlen('workspace.app.mentioned') <= 50, 'fits api_webhook_queue.event');
}

// The queue matches a subscription's application before anything is
// written, and a mention that names nobody is not queued at all.
function test_workspace_app_mention_queued_for_its_application_alone()
{
	$enqueue = workspace_app_mention_test_source('api_webhook_enqueue');

	pg_assert_contains('api_webhooks.app_id', $enqueue, 'the subscription\'s application is read');

	$asked = strpos($enqueue, 'api_webhook_event_app_id(');
	$early = strpos($enqueue, 'if ($addressed < 0)');
	$read = strpos($enqueue, 'FROM api_webhooks');
	$matched = strpos($enqueue, "((int)\$hook['app_id'] !== \$addressed)");
	$written = strpos($enqueue, 'INSERT INTO api_webhook_queue');

	pg_assert_true(($asked !== false) && ($early !== false) && ($asked < $early) && ($early < $read), 'asked first; nobody named, nothing read');
	pg_assert_true(($matched !== false) && ($matched < $written), 'matched before the row is written');
}

// Announced after the message is kept and its people are told, for a new
// message and for the tags an edit adds; the words go as the application's
// owner reads them, and only once that owner is known to read the channel.
function test_workspace_app_mention_announced_after_the_write()
{
	$send = workspace_app_mention_test_source('ws_message_send');

	pg_assert_true(strpos($send, 'ws_message_tell_apps($channel, $message_id, $tokens)') > strpos($send, 'INSERT INTO ws_messages'), 'a new message: after it is written');
	pg_assert_true(strpos($send, 'ws_message_tell_apps(') > strpos($send, 'ws_message_notify_mentions('), 'after the people are told');

	$edit = workspace_app_mention_test_source('ws_message_edit');

	pg_assert_true(strpos($edit, 'ws_message_tell_apps($channel, $message[\'id\'], $new, true)') > strpos($edit, 'UPDATE ws_messages SET body'), 'an edit: the tags it adds, after it is written');

	$tell = workspace_app_mention_test_source('ws_message_tell_apps');
	$announced = strpos($tell, "pg_announce('workspace.app.mentioned', ws_app_mention_payload(");

	pg_assert_true($announced !== false, 'announced with its payload');
	pg_assert_true(strpos($tell, 'ws_app_mention_audience(') < $announced, 'the writer and the assistants are left out first');
	pg_assert_true(strpos($tell, 'ws_app_hears_mentions(') < $announced, 'an application that may not read the workspace is not told');
	pg_assert_contains('SELECT id, status, expires_at, owner_user_id, scopes FROM api_apps', $tell, 'the key\'s end is read with it');
	pg_assert_true(strpos($tell, 'ws_app_reads_channel($reader, $channel)') < $announced, 'nor one whose owner does not read the channel');
	pg_assert_contains('ws_plain_text($reader, $message[\'body\'])', $tell, 'the words as the owner reads them');
	pg_assert_contains("'channel_id'", workspace_app_mention_test_source('ws_app_mention_payload'), 'the event names its channel');
}
