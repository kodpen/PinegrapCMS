<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for e-mail conversations in the external API
 * (includes/workspace/api_mail.php) that need no database: the routes,
 * which are MCP tools, that a write runs the panel's check before the
 * rehearsal stops it, that an answer is the person's own, how a
 * conversation and a mail are handed out, and the webhook event's body.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function ws_api_mail_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		if (strpos($route['id'], 'workspace.mail_') === 0) {
			$routes[$route['id']] = $route;
		}
	}

	return $routes;
}

function ws_api_mail_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function test_ws_api_mail_routes()
{
	$routes = ws_api_mail_test_routes();
	$expected = array(
		'workspace.mail_conversations.list'   => array('GET', '/workspace/mail/conversations', 'workspace:read'),
		'workspace.mail_conversations.get'    => array('GET', '/workspace/mail/conversations/{id}', 'workspace:read'),
		'workspace.mail_conversations.update' => array('POST', '/workspace/mail/conversations/{id}', 'workspace:write'),
		'workspace.mail_conversations.reply'  => array('POST', '/workspace/mail/conversations/{id}/reply', 'workspace:write'),
	);

	pg_assert_same(array_keys($expected), array_keys($routes), 'the routes, in order');

	foreach ($expected as $id => $shape) {
		pg_assert_same($shape, array($routes[$id]['method'], $routes[$id]['path'], $routes[$id]['scope']), $id);
		pg_assert_true(function_exists($routes[$id]['handler']), $id . ' reaches a handler');
		pg_assert_same($routes[$id]['method'] !== 'GET', !empty($routes[$id]['dry_run']), $id . ': a write rehearses');
	}

	pg_assert_same(array('PATCH'), $routes['workspace.mail_conversations.update']['also_accepts'] ?? null);
	pg_assert_same(201, $routes['workspace.mail_conversations.reply']['status'] ?? null);
	pg_assert_same(WS_MAIL_REPLY_MAX, $routes['workspace.mail_conversations.reply']['params'][1]['max_length'], 'the panel\'s limit');
}

// Reading and changing a conversation are tools; an answer to a customer
// never is: an assistant does not write to customers.
function test_ws_api_mail_mcp_tools()
{
	$routes = ws_api_mail_test_routes();

	foreach ($routes as $id => $route) {
		if ($id === 'workspace.mail_conversations.reply') {
			pg_assert_same(false, $route['mcp'] ?? null, $id . ' carries \'mcp\' => false');
			pg_assert_false(pg_mcp_route_is_offered($route), $id . ' is no tool');
		} else {
			pg_assert_true(pg_mcp_route_is_offered($route), $id . ' is a tool');
		}
	}
}

function test_ws_api_mail_writes_check_before_the_rehearsal()
{
	$writes = array(
		'ws_api_mail_conversations_update' => array('ws_mail_conversation_check(', 'ws_mail_conversation_set('),
		'ws_api_mail_conversations_reply'  => array('ws_mail_reply_check(', 'ws_mail_reply_send('),
	);

	foreach ($writes as $handler => $calls) {
		$source = ws_api_mail_test_source($handler);
		$check = strpos($source, $calls[0]);
		$stop = strrpos($source, 'api_dry_run_stop(');
		$write = strpos($source, $calls[1]);

		pg_assert_true(($check !== false) && ($stop !== false) && ($write !== false) && ($check < $stop) && ($stop < $write), $handler . ': check, rehearsal, write');
	}

	$reply = ws_api_mail_test_source('ws_api_mail_conversations_reply');
	pg_assert_true(strpos($reply, 'ws_api_own_words_only(') < strpos($reply, 'api_dry_run_stop('), 'the person\'s own before anything');

	$panel = ws_api_mail_test_source('ws_mail_conversation_set');
	pg_assert_contains('ws_mail_conversation_check($viewer, $conversation_id, $changes)', $panel, 'the panel runs the same check');
}

function test_ws_api_mail_conversation_changes()
{
	pg_assert_same(array('status' => 'closed', 'assign' => ''), ws_api_mail_conversation_changes(array('status' => 'closed'), 7));
	pg_assert_same(array('status' => '', 'assign' => 'me'), ws_api_mail_conversation_changes(array('assignee_id' => 7), 7), 'the owner\'s own id');
	pg_assert_same(array('status' => '', 'assign' => '9'), ws_api_mail_conversation_changes(array('assignee_id' => 9), 7));
	pg_assert_same(array('status' => '', 'assign' => 'none'), ws_api_mail_conversation_changes(array('assignee_id' => ''), 7), 'null reaches the handler as empty');
	pg_assert_same(array('status' => '', 'assign' => 'none'), ws_api_mail_conversation_changes(array('assignee_id' => 0), 7));
}

function test_ws_api_mail_reply_refusal()
{
	pg_assert_same(array('status' => 422, 'code' => 'validation_failed', 'field' => 'text'), ws_api_mail_reply_refusal('empty'));
	pg_assert_same(403, ws_api_mail_reply_refusal('no_access')['status']);
	pg_assert_same('not_sent', ws_api_mail_reply_refusal('not_sent')['code']);

	foreach (array('no_box', 'no_customer', 'no_smtp', 'domain', 'connection') as $error) {
		pg_assert_same(array('status' => 409, 'code' => 'cannot_send', 'field' => null), ws_api_mail_reply_refusal($error), $error);
	}
}

function test_ws_api_mail_present()
{
	$conversation = array('id' => 4, 'box_id' => 2, 'channel_id' => 3, 'message_id' => 90, 'subject' => 'Late', 'customer_email' => 'ayse@example.com', 'customer_name' => 'Ayşe',
		'contact_id' => 0, 'status' => 'open', 'assignee_id' => 0, 'first_in_at' => 1000, 'first_reply_at' => 0, 'last_in_at' => 1000, 'last_out_at' => 0, 'closed_at' => 0);
	$present = ws_api_mail_conversation_present($conversation, 1, 1000 + 3601);

	pg_assert_same(array_keys(ws_api_mail_conversation_schema()), array_keys($present), 'the schema names every field');
	pg_assert_same(null, $present['contact_id']);
	pg_assert_same(null, $present['assignee_id'], 'nobody holds it');
	pg_assert_true($present['overdue']);
	pg_assert_same(api_time(1000 + 3600), $present['first_reply_due_at']);
	pg_assert_same(null, $present['mails'], 'no mails in a list');
	pg_assert_same(null, ws_api_mail_conversation_present($conversation, 0, 5000)['first_reply_due_at'], 'no target');

	$in = array('id' => 10, 'direction' => 'in', 'message_id' => 90, 'conversation_id' => 4, 'from_email' => 'ayse@example.com', 'from_name' => 'Ayşe', 'to_list' => 'support@shop.test',
		'cc_list' => '', 'subject' => 'Late', 'body_text' => 'Where is it?', 'auth' => 'pass', 'user_id' => 0, 'refused_files' => "a.exe\nb.js", 'sent_at' => 999, 'created_at' => 1000);
	$mail = ws_api_mail_present($in);

	pg_assert_same(array_keys(ws_api_mail_schema()), array_keys($mail));
	pg_assert_true($mail['verified']);
	pg_assert_same(array('a.exe', 'b.js'), $mail['refused_files']);
	pg_assert_false($mail['automatic']);
	pg_assert_same(null, $mail['user_id']);

	$auto = ws_api_mail_present(array_merge($in, array('direction' => 'out', 'refused_files' => '')));
	pg_assert_true($auto['automatic'], 'an answer with no writer');
	pg_assert_false($auto['verified'], 'only incoming mail is verified');

	$bounce = ws_api_mail_present(array_merge($in, array('conversation_id' => 0, 'user_id' => 5, 'auth' => 'none')));
	pg_assert_true($bounce['bounce']);
	pg_assert_false($bounce['automatic']);

	pg_assert_same(array(1, 5, 9), array_column(ws_api_mail_items_order(array(array('id' => 9), array('id' => 1), array('id' => 5))), 'id'));
}

// Subscribers hear ids only; the mail's words stay on the site.
function test_ws_api_mail_webhook_event()
{
	pg_assert_true(isset(ws_webhook_events()['workspace.mail.received']));

	$ingest = ws_api_mail_test_source('ws_mail_ingest');
	$start = strpos($ingest, "pg_announce('workspace.mail.received'");
	pg_assert_true($start !== false, 'announced where a mail is stored');

	$call = substr($ingest, $start, strpos($ingest, '));', $start) - $start);
	pg_assert_contains("'channel_id'      => (int) \$channel['id'],", $call, 'matched by channel group');

	foreach (array('subject', 'text', 'customer', 'from_email', 'body') as $word) {
		pg_assert_true(strpos($call, "'" . $word) === false, $word . ' stays out');
	}
}

function test_ws_api_mail_message_carries_its_mail()
{
	pg_assert_true(isset(ws_api_message_schema()['mail']));
	pg_assert_same(array('item_id', 'conversation_id', 'direction', 'from_email'), array_keys(ws_api_message_mail_schema()));
	pg_assert_contains("\$row['sender_kind'] === 'mail'", ws_api_mail_test_source('ws_api_message_present'));
}
