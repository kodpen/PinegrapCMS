<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for e-mail drafts kept on the server (includes/workspace/
 * mail_drafts.php, api_mail_drafts.php) that need no database: what a draft
 * keeps, how a change merges, how a draft is told from a sent mail, the
 * routes and tools, and that the readers of sent mail leave drafts out.
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

function ws_mail_drafts_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function test_ws_mail_draft_input_keeps_only_the_keys_sent()
{
	$input = ws_mail_draft_input(array('text' => "Hello\r\nthere  \n", 'cc' => '  a@example.com,   b@example.com '));

	pg_assert_true($input['ok']);
	pg_assert_same(array('cc' => 'a@example.com, b@example.com', 'text' => "Hello\nthere"), $input['fields'], 'cleaned, nothing added');
	pg_assert_same(array(), ws_mail_draft_input(array())['fields'], 'nothing sent, nothing changed');
	pg_assert_same('', ws_mail_draft_input(array('subject' => array('x')))['fields']['subject'], 'a list is not a subject');
}

function test_ws_mail_draft_input_refuses_what_is_too_long()
{
	$text = ws_mail_draft_input(array('text' => str_repeat('a', WS_MAIL_REPLY_MAX + 1)));
	pg_assert_same(array(false, 'too_long', 'text'), array($text['ok'], $text['error'], $text['field']));

	$subject = ws_mail_draft_input(array('subject' => str_repeat('s', WS_MAIL_DRAFT_SUBJECT_MAX + 1)));
	pg_assert_same(array('subject', 'subject'), array($subject['error'], $subject['field']));

	$bcc = ws_mail_draft_input(array('bcc' => str_repeat('b', WS_MAIL_DRAFT_ADDRESSES_MAX + 1)));
	pg_assert_same(array('addresses', 'bcc'), array($bcc['error'], $bcc['field']));

	pg_assert_true(ws_mail_draft_input(array('text' => str_repeat('a', WS_MAIL_REPLY_MAX)))['ok'], 'the panel\'s limit itself');
}

function test_ws_mail_draft_has_words()
{
	pg_assert_false(ws_mail_draft_has_words(array()));
	pg_assert_false(ws_mail_draft_has_words(array('to' => ' ', 'text' => "\n")));
	pg_assert_true(ws_mail_draft_has_words(array('to' => 'a@example.com')), 'an address alone is a start');
	pg_assert_true(ws_mail_draft_has_words(array('text' => 'Hi')));
}

function test_ws_mail_draft_merge_keeps_a_replys_to_and_subject()
{
	$reply = array('conversation_id' => 7, 'to_list' => 'customer@example.com', 'cc_list' => '', 'bcc_list' => '', 'subject' => 'Order 12', 'body_text' => 'Old');
	$merged = ws_mail_draft_merge($reply, array('to' => 'other@example.com', 'subject' => 'New', 'text' => 'Thanks', 'cc' => 'boss@example.com'));

	pg_assert_same(array('to' => 'customer@example.com', 'cc' => 'boss@example.com', 'bcc' => '', 'subject' => 'Order 12', 'text' => 'Thanks'), $merged);

	$new = array_merge($reply, array('conversation_id' => 0));
	$merged = ws_mail_draft_merge($new, array('to' => 'other@example.com', 'subject' => 'New'));

	pg_assert_same(array('other@example.com', 'New', 'Old'), array($merged['to'], $merged['subject'], $merged['text']), 'a new mail\'s fields all change');
}

function test_ws_mail_item_is_draft()
{
	pg_assert_true(ws_mail_item_is_draft(array('direction' => 'out', 'send_state' => 'draft')));
	pg_assert_false(ws_mail_item_is_draft(array('direction' => 'in', 'send_state' => 'draft')), 'only a mail going out');
	pg_assert_false(ws_mail_item_is_draft(array('direction' => 'out', 'send_state' => 'queued')));
	pg_assert_false(ws_mail_item_is_draft(null));
	pg_assert_true(strlen(WS_MAIL_DRAFT_STATE) <= 8, 'fits send_state VARCHAR(8)');
}

function test_ws_mail_drafts_excluded_sql()
{
	pg_assert_same("send_state <> 'draft'", ws_mail_drafts_excluded_sql('', true));
	pg_assert_same("i.send_state <> 'draft'", ws_mail_drafts_excluded_sql('i', true));
	pg_assert_same('1 = 1', ws_mail_drafts_excluded_sql('', false), 'before 11.0.185 the column is not named');
}

function test_ws_mail_draft_hash_is_never_empty_or_shared()
{
	$a = ws_mail_draft_hash();
	$b = ws_mail_draft_hash();

	pg_assert_same(64, strlen($a), 'fits rfc_message_hash CHAR(64)');
	pg_assert_true($a !== $b, 'two drafts of one box never share a hash (uq_box_message)');
}

function test_ws_mail_draft_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		if (strpos($route['id'], 'workspace.mail_drafts.') === 0) {
			$routes[$route['id']] = $route;
		}
	}

	$expected = array(
		'workspace.mail_drafts.list'        => array('GET', '/workspace/mail/drafts', 'workspace:read'),
		'workspace.mail_drafts.get'         => array('GET', '/workspace/mail/drafts/{id}', 'workspace:read'),
		'workspace.mail_drafts.create'      => array('POST', '/workspace/mail/drafts', 'workspace:write'),
		'workspace.mail_drafts.update'      => array('POST', '/workspace/mail/drafts/{id}', 'workspace:write'),
		'workspace.mail_drafts.delete'      => array('DELETE', '/workspace/mail/drafts/{id}', 'workspace:write'),
		'workspace.mail_drafts.delete_post' => array('POST', '/workspace/mail/drafts/{id}/delete', 'workspace:write'),
	);

	pg_assert_same(array_keys($expected), array_keys($routes), 'the routes, in order');

	foreach ($expected as $id => $shape) {
		pg_assert_same($shape, array($routes[$id]['method'], $routes[$id]['path'], $routes[$id]['scope']), $id);
		pg_assert_true(function_exists($routes[$id]['handler']), $id . ' reaches a handler');
		pg_assert_same($routes[$id]['method'] !== 'GET', !empty($routes[$id]['dry_run']), $id . ': a write rehearses');
		pg_assert_true(empty($routes[$id]['moves_money']), $id . ': no money');
	}

	pg_assert_same(array('PATCH'), $routes['workspace.mail_drafts.update']['also_accepts'] ?? null);
	pg_assert_same(201, $routes['workspace.mail_drafts.create']['status'] ?? null);

	// Every caller that reads a conversation keeps drafts: all are tools but
	// the POST twin of the deletion.
	foreach ($routes as $id => $route) {
		pg_assert_same($id !== 'workspace.mail_drafts.delete_post', pg_mcp_route_is_offered($route), $id . ' as a tool');

		if (strpos($id, '.delete') === false) {
			pg_assert_contains('never sent by itself', $route['description'], $id . ' says a draft is not sent');
		}
	}

	pg_assert_true(strlen(pg_mcp_tool_name('workspace.mail_drafts.delete_post')) <= 64, 'tool names fit');
}

function test_ws_api_mail_draft_present()
{
	$draft = ws_api_mail_draft_present(array('id' => '5', 'conversation_id' => '0', 'box_id' => '2', 'to_list' => 'a@example.com', 'cc_list' => null,
		'bcc_list' => '', 'subject' => 'Hi', 'body_text' => 'Text', 'user_id' => '3', 'created_at' => '0'));

	pg_assert_same(array_keys(ws_api_mail_draft_schema()), array_keys($draft), 'the schema');
	pg_assert_same(array('new', null, 2, '', 3), array($draft['kind'], $draft['conversation_id'], $draft['box_id'], $draft['cc'], $draft['user_id']));

	$reply = ws_api_mail_draft_present(array('id' => 6, 'conversation_id' => 9, 'box_id' => 2, 'to_list' => '', 'cc_list' => '', 'bcc_list' => '',
		'subject' => '', 'body_text' => '', 'user_id' => 3, 'created_at' => 0));
	pg_assert_same(array('reply', 9), array($reply['kind'], $reply['conversation_id']));
}

function test_ws_api_mail_draft_refusal()
{
	pg_assert_same(404, ws_api_mail_draft_refusal('conversation')['status']);
	pg_assert_same(403, ws_api_mail_draft_refusal('no_access')['status']);
	pg_assert_same(503, ws_api_mail_draft_refusal('not_ready')['status']);

	foreach (array('box_id', 'too_many', 'empty', 'too_long', 'addresses', 'subject') as $error) {
		pg_assert_same(422, ws_api_mail_draft_refusal($error)['status'], $error);
	}
}

function test_ws_mail_draft_writes_check_before_the_rehearsal_stops()
{
	foreach (array('ws_api_mail_drafts_create' => 'ws_mail_draft_create_check', 'ws_api_mail_drafts_update' => 'ws_mail_draft_input',
		'ws_api_mail_drafts_delete' => 'ws_api_mail_draft_or_404') as $handler => $check) {
		$source = ws_mail_drafts_test_source($handler);
		$stop = strpos($source, 'api_dry_run_stop(');

		pg_assert_true(($stop !== false) && (strpos($source, $check . '(') < $stop), $handler . ' checks before the dry run stops');
	}

	pg_assert_contains('true', ws_mail_drafts_test_source('ws_api_mail_drafts_update'), 'a change needs the right to write');
}

function test_ws_mail_drafts_stay_out_of_the_readers_of_sent_mail()
{
	foreach (array('ws_api_mail_conversation_answer', 'ws_api_mail_items_get', 'ws_api_mail_conversations_read', 'ws_mail_pane',
		'ws_mail_list_mails', 'ws_mail_items_map', 'ws_mail_find_conversation') as $reader) {
		pg_assert_contains('ws_mail_drafts_excluded_sql(', ws_mail_drafts_test_source($reader), $reader . ' leaves drafts out');
	}
}

function test_ws_mail_draft_is_never_read_as_sent()
{
	pg_assert_same('draft', ws_mail_send_state(array('direction' => 'out', 'send_state' => 'draft')), 'its own state');
	pg_assert_same('sent', ws_mail_send_state(array('direction' => 'out', 'send_state' => '')), 'an answer from before the send state is still sent');
	pg_assert_contains("send_state <> 'draft'", ws_mail_drafts_test_source('ws_mail_send_last_hour'), 'a draft does not count against the hourly limit');
	pg_assert_contains('ws_mail_drafts_excluded_sql(', ws_mail_drafts_test_source('ws_mail_list_where'), 'the list\'s search does not find a draft');
}
