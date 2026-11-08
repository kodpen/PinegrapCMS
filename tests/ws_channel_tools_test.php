<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent channel tools (tools/ws_client.php, tools/ws_post.php,
 * tools/ws_wait.php): options, settings and their refusals, the Basic header,
 * the ETag read, the message rule and the post body. No network.
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
