<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for answering a WhatsApp conversation over the external API
 * (includes/workspace/api_mail.php): how a refusal is told, the order of
 * the person's own gate, the check, the rehearsal and the send, and that the
 * answer leaves only through the WhatsApp queue. Sending needs the database
 * and is not run here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// The source of one function of api_mail.php.
function workspace_api_mail_phone_test_source($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_mail.php');
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 10);

	return substr($source, $start, ($end === false) ? strlen($source) - $start : $end - $start);
}

function test_workspace_api_mail_phone_refusal()
{
	pg_assert_same(array('status' => 422, 'code' => 'validation_failed', 'field' => 'text'), ws_api_mail_phone_reply_refusal('empty'));
	pg_assert_same(array('status' => 422, 'code' => 'validation_failed', 'field' => 'text'), ws_api_mail_phone_reply_refusal('too_long'), 'WhatsApp takes 4096 characters');
	pg_assert_same(array('status' => 403, 'code' => 'forbidden', 'field' => null), ws_api_mail_phone_reply_refusal('no_access'));
	pg_assert_same(array('status' => 503, 'code' => 'service_unavailable', 'field' => null), ws_api_mail_phone_reply_refusal('not_ready'));
	foreach (array('window', 'off', 'number', 'demo', 'not_phone', 'not_sent', 'anything') as $error) {
		pg_assert_same(array('status' => 409, 'code' => 'cannot_send', 'field' => null), ws_api_mail_phone_reply_refusal($error), $error);
	}
}

function test_workspace_api_mail_phone_reply_order()
{
	$reply = workspace_api_mail_phone_test_source('ws_api_mail_conversations_reply');
	$own = strpos($reply, 'ws_api_own_words_only(');
	$phone = strpos($reply, 'ws_api_mail_phone_reply(');
	pg_assert_true(($own !== false) && ($phone !== false) && ($own < $phone), 'the person\'s own session before a WhatsApp answer');
	pg_assert_true($phone < strpos($reply, 'ws_mail_reply_check('), 'a WhatsApp conversation never reaches the e-mail path');

	$source = workspace_api_mail_phone_test_source('ws_api_mail_phone_reply');
	$check = strpos($source, 'ws_api_mail_phone_reply_check(');
	$stop = strpos($source, 'api_dry_run_stop(');
	$send = strpos($source, 'ws_mail_phone_reply_send(');
	pg_assert_true(($check !== false) && ($stop !== false) && ($send !== false) && ($check < $stop) && ($stop < $send), 'check, rehearsal, write');
	pg_assert_true(strpos($source, 'pg_whatsapp_cloud_send') === false && strpos($source, 'email(') === false, 'only through the queue');

	$check_source = workspace_api_mail_phone_test_source('ws_api_mail_phone_reply_check');
	pg_assert_contains('ws_mail_phone_reply_target(', $check_source, 'the card\'s own rights and window');
	pg_assert_contains('pg_whatsapp_reply_row(', $check_source, 'the queue\'s own text rules');
	pg_assert_contains('pg_demo()', $check_source, 'a demo site sends nothing');
}

function test_workspace_api_mail_phone_route_text()
{
	foreach (ws_mail_api_routes() as $route) {
		if ($route['id'] === 'workspace.mail_conversations.reply') {
			pg_assert_contains('WhatsApp', $route['description']);
			pg_assert_contains('24 hours', $route['description']);
			pg_assert_same(false, $route['mcp'], 'still no tool');

			return;
		}
	}

	pg_assert_true(false, 'the reply route');
}
