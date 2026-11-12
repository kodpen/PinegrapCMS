<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent channel tools (tools/ws_client.php, tools/ws_post.php,
 * tools/ws_wait.php, tools/ws_poll.php): options, settings and their refusals,
 * the request queue waiter (which row wakes, the printed line, the interval),
 * the Basic header, the ETag read, the message rule, the post body, and
 * tools/ws_poll.php's ask body and close id. No network.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/ws_post.php');
require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/ws_wait.php');
require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/ws_poll.php');

function test_ws_channel_tools_options()
{
	list($options, $rest) = ws_client_options(array('--channel=12', '--skip-missing', 'hello', '--', '--not-an-option'));

	pg_assert_same(array('channel' => '12', 'skip-missing' => true), $options);
	pg_assert_same(array('hello', '--not-an-option'), $rest);
}

function test_ws_channel_tools_settings_missing_and_https()
{
	$all = array('PG_WS_SITE' => 'https://example.com/pinegrap/integration.php/', 'PG_WS_KEY' => 'k', 'PG_WS_SECRET' => 's', 'PG_WS_CHANNEL' => '7');
	$ok = ws_client_settings(array(), $all);

	pg_assert_same(array(), $ok['missing']);
	pg_assert_same('https://example.com/pinegrap/integration.php', $ok['site']);
	pg_assert_same(7, $ok['channel']);
	pg_assert_same(9, ws_client_settings(array('channel' => '9'), $all)['channel']);

	pg_assert_same(array('PG_WS_SITE', 'PG_WS_KEY', 'PG_WS_SECRET', 'PG_WS_CHANNEL'), ws_client_settings(array(), array())['missing']);

	$plain = ws_client_settings(array(), array('PG_WS_SITE' => 'http://example.com/integration.php') + $all);
	pg_assert_same(array('PG_WS_SITE (https)'), $plain['missing']);

	$local = ws_client_settings(array(), array('PG_WS_SITE' => 'http://localhost:8080/integration.php') + $all);
	pg_assert_same(array(), $local['missing']);

	// localhost.evil.com is not the local machine.
	$trick = ws_client_settings(array(), array('PG_WS_SITE' => 'http://localhost.evil.com/integration.php') + $all);
	pg_assert_same(array('PG_WS_SITE (https)'), $trick['missing']);
}

function test_ws_channel_tools_auth_and_etag()
{
	pg_assert_same('Authorization: Basic ' . base64_encode('key:se:cret'), ws_client_auth_header('key', 'se:cret'));
	pg_assert_same('"abc"', ws_client_etag("HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nETag: \"abc\"\r\n\r\n"));
	pg_assert_same('W/"x"', ws_client_etag("HTTP/2 200\r\netag: W/\"x\"\r\n"));
	pg_assert_same('', ws_client_etag("HTTP/1.1 304 Not Modified\r\n"));
}

function test_ws_channel_tools_message_rule()
{
	$message = array('id' => 40, 'text' => '[ci] green #12 branch', 'deleted' => false, 'hidden' => false);

	pg_assert_true(ws_client_message_matches($message, array(), 0));
	pg_assert_true(ws_client_message_matches($message, array('prefix' => '[ci]'), 39));
	pg_assert_false(ws_client_message_matches($message, array('prefix' => '[ci]'), 40), 'at the last id seen');
	pg_assert_false(ws_client_message_matches($message, array('prefix' => '[ready]'), 0));
	pg_assert_false(ws_client_message_matches($message, array('prefix' => '[CI]'), 0), 'prefix is case-sensitive');
	pg_assert_true(ws_client_message_matches($message, array('contains' => 'GREEN'), 0));
	pg_assert_true(ws_client_message_matches($message, array('prefix' => '[ci]', 'regex' => '/#12\b/'), 0));
	pg_assert_false(ws_client_message_matches($message, array('prefix' => '[ci]', 'regex' => '/#13\b/'), 0));
	pg_assert_false(ws_client_message_matches(array('deleted' => true) + $message, array(), 0));
	pg_assert_false(ws_client_message_matches(array('hidden' => true) + $message, array(), 0));
	pg_assert_false(ws_client_message_matches('not a message', array(), 0));
}

function test_ws_channel_tools_wait_plan_and_pick()
{
	$plan = ws_wait_plan(array('prefix' => '[ready]', 'timeout' => '30', 'interval' => '1'));
	pg_assert_same(array('prefix' => '[ready]'), $plan['rule']);
	pg_assert_same(30, $plan['timeout']);
	pg_assert_same(2, $plan['interval'], 'at least two seconds');
	pg_assert_same(-1, $plan['after_id']);
	pg_assert_same('', $plan['error']);

	pg_assert_true(ws_wait_plan(array('regex' => 'no delimiters('))['error'] !== '');
	pg_assert_true(ws_wait_plan(array('timeout' => '-5'))['error'] !== '');

	$sync = array('messages' => array(
		array('id' => 12, 'text' => '[ready] #2'),
		array('id' => 9, 'text' => '[ready] #1'),
		array('id' => 11, 'text' => 'chat'),
	));

	pg_assert_same(9, ws_wait_pick($sync, array('prefix' => '[ready]'), 0)['id'], 'oldest match first');
	pg_assert_same(12, ws_wait_pick($sync, array('prefix' => '[ready]'), 9)['id']);
	pg_assert_same(null, ws_wait_pick($sync, array('prefix' => '[ready]'), 12));
	pg_assert_same(12, ws_client_max_id($sync['messages'], 0));
	pg_assert_same(20, ws_client_max_id(array(), 20));
}

function test_ws_channel_tools_post_body()
{
	pg_assert_same(array('text' => '[ci] green'), ws_post_body("  [ci] green\n", array())['body']);
	pg_assert_same(array('text' => 'x', 'kind' => 'decision'), ws_post_body('x', array('kind' => 'decision'))['body']);
	pg_assert_same(null, ws_post_body('   ', array())['body']);
	pg_assert_same(null, ws_post_body('x', array('kind' => 'task'))['body']);
	pg_assert_same(null, ws_post_body(str_repeat('ş', 4001), array())['body']);
	pg_assert_same(array('text' => str_repeat('ş', 4000)), ws_post_body(str_repeat('ş', 4000), array())['body']);
}

function test_ws_channel_tools_from_and_mention()
{
	$person = array('id' => 50, 'text' => '@mimar look', 'body' => '<@app:7> look', 'sender_kind' => 'user', 'sender' => array('user_id' => 3, 'name' => 'Ada'));
	$app = array('id' => 51, 'text' => '[ci] green', 'body' => '[ci] green', 'sender_kind' => 'app', 'sender' => array('user_id' => null, 'name' => 'CI'));

	pg_assert_true(ws_client_message_matches($person, array('from' => 'person'), 0));
	pg_assert_false(ws_client_message_matches($app, array('from' => 'person'), 0), 'an application never wakes a session waiting for people');
	pg_assert_true(ws_client_message_matches($app, array('from' => 'app'), 0));
	pg_assert_false(ws_client_message_matches($person, array('from' => 'app'), 0));
	pg_assert_true(ws_client_message_matches($person, array('mention' => '7'), 0));
	pg_assert_false(ws_client_message_matches($person, array('mention' => '70'), 0), 'the whole tag');
	pg_assert_false(ws_client_message_matches($app, array('mention' => '7'), 0));

	pg_assert_true(ws_wait_plan(array('from' => 'bot'))['error'] !== '');
	pg_assert_true(ws_wait_plan(array('mention' => 'x'))['error'] !== '');
	pg_assert_same(array('from' => 'person', 'mention' => '7'), ws_wait_plan(array('from' => 'person', 'mention' => '7'))['rule']);
}

function test_ws_channel_tools_wait_changed()
{
	$plan = ws_wait_plan(array('changed' => '40, 41,40'));
	pg_assert_same(array(40, 41), $plan['changed']);
	pg_assert_same('', $plan['error']);
	pg_assert_true(ws_wait_plan(array('changed' => '40,x'))['error'] !== '');
	pg_assert_true(ws_wait_plan(array('changed' => true))['error'] !== '');

	$sync = array(
		'messages' => array(array('id' => 60, 'text' => 'chat', 'sender' => array('user_id' => 3))),
		'changed'  => array(array('id' => 39, 'text' => 'other'), array('id' => 41, 'text' => 'Ship?', 'checks' => array(array('item' => 0, 'checked' => true)))),
	);

	$hit = ws_wait_pick($sync, array(), 0, array(40, 41));
	pg_assert_same(41, $hit['id']);
	pg_assert_true($hit['checks'][0]['checked'], 'printed with its checks');
	pg_assert_same(null, ws_wait_pick(array('changed' => array()) + $sync, array(), 0, array(40)), 'with --changed alone a new message does not wake');
	pg_assert_same(60, ws_wait_pick(array('changed' => array()) + $sync, array('from' => 'person'), 0, array(40))['id'], 'with a rule as well either wakes');
	pg_assert_same(60, ws_wait_pick($sync, array(), 0)['id'], 'without --changed as before');
}

function test_ws_channel_tools_post_parent()
{
	pg_assert_same(array('text' => 'Plan A', 'kind' => 'decision', 'parent_id' => 41), ws_post_body('Plan A', array('kind' => 'decision', 'parent' => '41'))['body']);
	pg_assert_same(null, ws_post_body('x', array('parent' => '0'))['body']);
	pg_assert_same(null, ws_post_body('x', array('parent' => true))['body']);
}

function test_ws_channel_tools_poll_ask_body()
{
	pg_assert_same(array('question' => 'Ship it?', 'options' => array('Yes', 'No')), ws_poll_ask_body("  Ship it?\n", array(' Yes ', 'No'))['body']);
	pg_assert_same(array('question' => 'Q', 'options' => array('B', 'A', 'C')), ws_poll_ask_body('Q', array('B', '', 'A', 'B', '  ', 'C'))['body'], 'blanks and repeats left out, order kept');
	pg_assert_same(null, ws_poll_ask_body('   ', array('A', 'B'))['body']);
	pg_assert_same(null, ws_poll_ask_body('Q', array('A'))['body']);
	pg_assert_same(null, ws_poll_ask_body('Q', array('A', 'A', ' '))['body'], 'one different option is not enough');
	pg_assert_same(null, ws_poll_ask_body('Q', range(1, 11))['body']);
	pg_assert_same(10, count(ws_poll_ask_body('Q', array_merge(range(1, 10), array('1')))['body']['options']));
	pg_assert_same(null, ws_poll_ask_body('Q', array('A', str_repeat('ş', 256)))['body']);
	pg_assert_same(str_repeat('ş', 255), ws_poll_ask_body('Q', array('A', str_repeat('ş', 255)))['body']['options'][1]);
	pg_assert_same(null, ws_poll_ask_body(str_repeat('ş', 4001), array('A', 'B'))['body']);
	pg_assert_same(str_repeat('ş', 4000), ws_poll_ask_body(str_repeat('ş', 4000), array('A', 'B'))['body']['question']);
	pg_assert_true(ws_poll_ask_body('Q', array('A'))['error'] !== '');
}

function test_ws_channel_tools_poll_command_and_id()
{
	pg_assert_same(array('command' => 'ask', 'args' => array('Q', 'A', 'B'), 'error' => ''), ws_poll_command(array('ask', 'Q', 'A', 'B')));
	pg_assert_same('close', ws_poll_command(array('close', '12'))['command']);
	pg_assert_true(ws_poll_command(array())['error'] !== '', 'no command');
	pg_assert_true(ws_poll_command(array('vote', '1'))['error'] !== '', 'unknown command');
	pg_assert_true(ws_poll_command(array('close'))['error'] !== '', 'close without an id');
	pg_assert_true(ws_poll_command(array('close', '1', '2'))['error'] !== '', 'close with two ids');

	pg_assert_same(12, ws_poll_id('12'));
	pg_assert_same(0, ws_poll_id('0'));
	pg_assert_same(0, ws_poll_id('-3'));
	pg_assert_same(0, ws_poll_id('12a'));
	pg_assert_same(0, ws_poll_id(''));
}

function test_ws_channel_tools_poll_settings()
{
	$no_channel = array('PG_WS_SITE' => 'https://example.com/pinegrap/integration.php', 'PG_WS_KEY' => 'k', 'PG_WS_SECRET' => 's');

	pg_assert_same(array(), ws_poll_settings('close', array(), $no_channel)['missing'], 'close needs no channel');
	pg_assert_same(array('PG_WS_CHANNEL'), ws_poll_settings('ask', array(), $no_channel)['missing']);
	pg_assert_same(array(), ws_poll_settings('ask', array('channel' => '5'), $no_channel)['missing']);
	pg_assert_same(array('PG_WS_KEY'), ws_poll_settings('close', array(), array('PG_WS_KEY' => '') + $no_channel)['missing']);
}

function test_ws_channel_tools_idempotency_header()
{
	pg_assert_same(array('Idempotency-Key: ask-q-1-'), ws_client_idempotency_headers(array('idempotency-key' => 'ask-q 1/')));
	pg_assert_same(64, strlen(ws_client_idempotency_headers(array('idempotency-key' => str_repeat('a', 80)))[0]) - strlen('Idempotency-Key: '));
	pg_assert_same(array(), ws_client_idempotency_headers(array()));
	pg_assert_same(array(), ws_client_idempotency_headers(array('idempotency-key' => true)));
}

function test_ws_channel_tools_requests_plan_and_settings()
{
	$plan = ws_wait_plan(array('requests' => true));
	pg_assert_true($plan['requests']);
	pg_assert_same(15, $plan['interval'], 'the queue is asked every 15 s by default');
	pg_assert_same(5, ws_wait_plan(array('requests' => true, 'interval' => '2'))['interval'], 'at least five seconds');
	pg_assert_same(30, ws_wait_plan(array('requests' => true, 'interval' => '30'))['interval']);
	pg_assert_same('', $plan['error']);

	// The text mode keeps its own default and floor.
	pg_assert_false(ws_wait_plan(array())['requests']);
	pg_assert_same(5, ws_wait_plan(array())['interval']);
	pg_assert_same(2, ws_wait_plan(array('interval' => '2'))['interval']);

	pg_assert_true(ws_wait_plan(array('requests' => true, 'prefix' => '[ci]'))['error'] !== '', 'no text rule');
	pg_assert_true(ws_wait_plan(array('requests' => true, 'changed' => '4'))['error'] !== '', 'no --changed');
	pg_assert_true(ws_wait_plan(array('requests' => true, 'after-id' => '4'))['error'] !== '', 'no --after-id');
	pg_assert_true(ws_wait_plan(array('requests' => 'yes'))['error'] !== '', 'a flag');

	// No channel is needed to read the queue; the text mode still needs one.
	$env = array('PG_WS_SITE' => 'https://example.com/integration.php', 'PG_WS_KEY' => 'k', 'PG_WS_SECRET' => 's');
	pg_assert_same(array(), ws_client_settings(array(), $env, false)['missing']);
	pg_assert_same(array('PG_WS_CHANNEL'), ws_client_settings(array(), $env)['missing']);
	pg_assert_same(array('PG_WS_KEY'), ws_client_settings(array(), array('PG_WS_KEY' => '') + $env, false)['missing']);
}

function test_ws_channel_tools_requests_pick_queued_only()
{
	$list = array('data' => array(
		array('id' => 3, 'status' => 'sent'),
		array('id' => 4, 'status' => 'running'),
		array('id' => 9, 'status' => 'queued'),
		array('id' => 7, 'status' => 'queued'),
	), 'page' => array());

	pg_assert_same(7, ws_wait_request_pick($list)['id'], 'the oldest queued request');
	pg_assert_same(null, ws_wait_request_pick(array('data' => array(array('id' => 3, 'status' => 'sent'), array('id' => 4, 'status' => 'running')))), 'one handed out wakes nobody');
	pg_assert_same(null, ws_wait_request_pick(array('data' => array())));
	pg_assert_same(null, ws_wait_request_pick('not a list'));
	pg_assert_same(null, ws_wait_request_pick(array('data' => array('x', array('status' => 'queued')))), 'a row without an id');
}

function test_ws_channel_tools_requests_line()
{
	$row = array(
		'id' => 7,
		'status' => 'queued',
		'channel' => array('id' => 12, 'name' => 'ajanlar', 'kind' => 'public'),
		'message' => array('id' => 340, 'text' => str_repeat('ş', 600), 'body' => '<@app:3> ' . str_repeat('ş', 600)),
		'wake' => array('kind' => '', 'note' => '', 'due_at' => null),
		'requested_by' => array('user_id' => 1, 'name' => 'Erdal'),
		'created_at' => '2026-11-11T22:00:00Z',
	);
	$line = ws_wait_request_line($row);

	pg_assert_same(array('request_id', 'message_id', 'channel_id', 'channel', 'wake', 'wake_kind', 'requested_by', 'text'), array_keys($line));
	pg_assert_same(array(7, 340, 12, 'ajanlar', false, ''), array($line['request_id'], $line['message_id'], $line['channel_id'], $line['channel'], $line['wake'], $line['wake_kind']));
	pg_assert_same(array('user_id' => 1, 'name' => 'Erdal'), $line['requested_by']);
	pg_assert_same(str_repeat('ş', 500), $line['text'], '500 characters, none cut in half');

	// A scheduled wake with no message: its note is the words.
	$wake = ws_wait_request_line(array('id' => 8, 'status' => 'queued', 'message' => null, 'channel' => array('id' => 12, 'name' => 'ajanlar'),
		'wake' => array('kind' => 'scheduled', 'note' => 'Daily check', 'due_at' => '2026-11-12T06:00:00Z'), 'requested_by' => array('user_id' => null, 'name' => '')));

	pg_assert_same(array(0, true, 'scheduled', 'Daily check', null), array($wake['message_id'], $wake['wake'], $wake['wake_kind'], $wake['text'], $wake['requested_by']['user_id']));

	// Nothing of the key leaves the line: it holds only the row's fields.
	pg_assert_false(strpos(json_encode($line), 'Authorization') !== false);
}
