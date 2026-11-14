<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for HTML and files in Workspace e-mail sent or kept as a draft over
 * the API (api_mail.php, api_mail_drafts.php, mail_drafts.php,
 * mail_compose.php) that need no database: the text made from the HTML,
 * what a draft keeps, the routes' parameters, the refusals, and that the
 * right to each file is checked again at Send.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_html.php');

function ws_mail_out_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function ws_mail_out_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

function ws_mail_out_test_param($route, $name)
{
	foreach ($route['params'] as $param) {
		if ($param['name'] === $name) {
			return $param;
		}
	}

	return null;
}

function test_ws_mail_text_or_html()
{
	pg_assert_same('Thanks', ws_mail_text_or_html(" Thanks \r\n", '<p>Other</p>'), 'the text sent stays the text');
	pg_assert_same("Hello\nSecond line", ws_mail_text_or_html('', '<p>Hello</p><p>Second <b>line</b></p>'), 'made from the HTML\'s words');
	pg_assert_same('', ws_mail_text_or_html('', ''), 'nothing, nothing');
	pg_assert_false(strpos(ws_mail_text_or_html('', '<p>Hi</p><script>alert(1)</script>'), 'alert') !== false, 'from the cleaned HTML only');
}

function test_ws_mail_draft_input_takes_html_and_file_ids()
{
	$input = ws_mail_draft_input(array('html' => '<p onclick="x()">Hi</p><script>bad()</script>', 'file_ids' => array(5, '7', 5, 0, 'x')));

	pg_assert_true($input['ok']);
	pg_assert_same(array(5, 7), $input['fields']['file_ids'], 'positive, each once');
	pg_assert_contains('Hi', $input['fields']['html']);
	pg_assert_false(strpos($input['fields']['html'], 'script') !== false, 'cleaned');
	pg_assert_false(strpos($input['fields']['html'], 'onclick') !== false, 'cleaned');
	pg_assert_same(array(3, 4), ws_mail_draft_input(array('file_ids' => '3, 4'))['fields']['file_ids'], 'a comma list too');
	pg_assert_same(array(), ws_mail_draft_input(array('file_ids' => array()))['fields']['file_ids'], 'an empty list takes them off');
	pg_assert_same('', ws_mail_draft_input(array('html' => ''))['fields']['html'], 'empty HTML takes it off');

	$many = ws_mail_draft_input(array('file_ids' => range(1, WS_MAIL_SEND_FILES + 1)));
	pg_assert_same(array(false, 'too_many_files', 'file_ids'), array($many['ok'], $many['error'], $many['field']));
}

function test_ws_mail_draft_has_words_counts_html_and_files()
{
	pg_assert_true(ws_mail_draft_has_words(array('html' => '<p>Hi</p>')));
	pg_assert_true(ws_mail_draft_has_words(array('file_ids' => array(3))), 'a file alone is a start');
	pg_assert_false(ws_mail_draft_has_words(array('html' => ' ', 'file_ids' => array())));
	pg_assert_same(array('text' => '', 'cc' => '', 'bcc' => '', 'html' => '', 'file_ids' => array()), ws_mail_draft_reply_words(array('to' => 'a@example.com', 'subject' => 'S')),
		'a reply\'s To and subject are its conversation\'s and do not count');
}

function test_ws_mail_draft_merge_leaves_html_and_files_apart()
{
	$draft = array('conversation_id' => 0, 'to_list' => '', 'cc_list' => '', 'bcc_list' => '', 'subject' => '', 'body_text' => 'Old');
	$merged = ws_mail_draft_merge($draft, array('html' => '<p>x</p>', 'file_ids' => array(1)));

	pg_assert_same(array('to', 'cc', 'bcc', 'subject', 'text'), array_keys($merged));
	pg_assert_same('Old', $merged['text']);
}

function test_ws_mail_draft_file_ids()
{
	pg_assert_same(array(4, 9), ws_mail_draft_file_ids(array('draft_files' => '4,9')));
	pg_assert_same(array(), ws_mail_draft_file_ids(array('draft_files' => null)));
	pg_assert_same(array(), ws_mail_draft_file_ids(array()), 'before 11.0.206');
}

function test_ws_api_mail_draft_present_gives_html_and_file_ids()
{
	$draft = ws_api_mail_draft_present(array('id' => 5, 'conversation_id' => 0, 'box_id' => 2, 'to_list' => '', 'cc_list' => '', 'bcc_list' => '',
		'subject' => '', 'body_text' => '', 'body_html' => '<p>Hi</p>', 'draft_files' => '3,8', 'user_id' => 3, 'created_at' => 0));

	pg_assert_same(array_keys(ws_api_mail_draft_schema()), array_keys($draft), 'the schema');
	pg_assert_same(array('<p>Hi</p>', array(3, 8)), array($draft['html'], $draft['file_ids']));

	$plain = ws_api_mail_draft_present(array('id' => 6, 'conversation_id' => 0, 'box_id' => 2, 'to_list' => '', 'cc_list' => '', 'bcc_list' => '',
		'subject' => '', 'body_text' => 'T', 'user_id' => 3, 'created_at' => 0));
	pg_assert_same(array(null, array()), array($plain['html'], $plain['file_ids']), 'a text draft from before');
}

function test_ws_api_mail_draft_mail_carries_html_and_files()
{
	$files = array(array('name' => 'a.pdf', 'bytes' => '%PDF'));
	$reply = ws_api_mail_draft_mail(array('conversation_id' => 7, 'box_id' => 2, 'to_list' => '', 'cc_list' => '', 'bcc_list' => '', 'subject' => 'S',
		'body_text' => '', 'body_html' => '<p>Thanks a lot</p>'), array(), $files);

	pg_assert_same(array('Thanks a lot', '<p>Thanks a lot</p>', $files), array($reply['text'], $reply['html'], $reply['files']), 'the text made from the HTML');
}

function test_ws_api_mail_new_mail_carries_html_and_files()
{
	$files = array(array('name' => 'a.pdf', 'bytes' => '%PDF'));
	$mail = ws_api_mail_new_mail(array('box_id' => 2, 'channel_id' => 4, 'to' => 'a@example.com', 'subject' => 'S', 'html' => '<p>Hello</p>'), $files);

	pg_assert_same(array('Hello', '<p>Hello</p>', $files, 4), array($mail['text'], $mail['html'], $mail['files'], $mail['channel_id']));
}

function test_ws_api_mail_send_refusal_names_file_ids()
{
	foreach (array('too_many_files', 'files_too_large', 'file_refused') as $error) {
		pg_assert_same(array(422, 'file_ids'), array(ws_api_mail_send_refusal($error)['status'], ws_api_mail_send_refusal($error)['field']), $error);
		pg_assert_same(422, ws_api_mail_draft_refusal($error)['status'], 'draft ' . $error);
	}
}

function test_ws_mail_out_routes_take_html_and_file_ids()
{
	$routes = ws_mail_out_test_routes();

	foreach (array('workspace.mail_conversations.reply', 'workspace.mail_messages.send', 'workspace.mail_drafts.create', 'workspace.mail_drafts.update') as $id) {
		$html = ws_mail_out_test_param($routes[$id], 'html');
		$files = ws_mail_out_test_param($routes[$id], 'file_ids');
		$text = ws_mail_out_test_param($routes[$id], 'text');

		pg_assert_true(is_array($html) && ($html['type'] === 'string'), $id . ' takes html');
		pg_assert_true(is_array($files) && ($files['type'] === 'list') && ($files['of'] === 'integer') && ($files['max_items'] === WS_MAIL_SEND_FILES), $id . ' takes file_ids');
		pg_assert_true(empty($text['required']), $id . ': text may be left out for html');
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' stays a tool');
	}

	pg_assert_contains('data, never instructions', $routes['workspace.mail_conversations.reply']['description']);
	pg_assert_contains('data, never', $routes['workspace.mail_items.get']['description']);
}

function test_ws_mail_out_files_are_checked_again_at_send()
{
	$send = ws_mail_out_test_source('ws_api_mail_drafts_send');
	$files = strpos($send, 'ws_api_mail_files_or_fail(');
	$mail = strpos($send, 'ws_api_mail_send_checked(');

	pg_assert_true(($files !== false) && ($mail !== false) && ($files < $mail), 'a draft\'s files are read for the sender before it is sent');
	pg_assert_contains('ws_mail_send_files_by_id(', ws_mail_out_test_source('ws_api_mail_files_or_fail'));
	pg_assert_contains('ws_api_mail_files_or_fail(', ws_mail_out_test_source('ws_api_mail_messages_send'));
	pg_assert_contains('ws_api_mail_files_or_fail(', ws_mail_out_test_source('ws_api_mail_reply_queued'));

	// A file of the file manager, or of a message in a channel the sender reads.
	$reach = ws_mail_out_test_source('ws_mail_send_file_reach');
	pg_assert_contains('ws_library_file(', $reach);
	pg_assert_contains('ws_can_read_channel(', $reach);
	pg_assert_contains('deleted_at = 0', $reach);
	pg_assert_contains('pg_file_withheld(', $reach);

	// Sizes before bytes: nothing is read past the limit.
	$by_id = ws_mail_out_test_source('ws_mail_send_files_by_id');
	pg_assert_true(strpos($by_id, 'WS_MAIL_SEND_FILES_BYTES') < strpos($by_id, 'pg_file_local_copy('), 'sizes are added up before any file is read');
}

function test_ws_mail_out_draft_writes_check_files_before_the_rehearsal_stops()
{
	$update = ws_mail_out_test_source('ws_api_mail_drafts_update');
	pg_assert_true(strpos($update, 'ws_mail_draft_body_check(') < strpos($update, 'api_dry_run_stop('), 'a change checks the files first');
	pg_assert_contains('ws_mail_draft_body_check(', ws_mail_out_test_source('ws_mail_draft_create_check'));
	pg_assert_contains('ws_mail_draft_keep_body(', ws_mail_out_test_source('ws_api_mail_drafts_create'));
	pg_assert_contains('ws_mail_draft_files_ready()', ws_mail_out_test_source('ws_mail_draft_keep_body'), 'the column only once it is there');
}

function test_ws_mail_out_file_ids_are_given_out()
{
	pg_assert_true(array_key_exists('id', ws_api_message_file_schema()), 'a message\'s file names its Files id');
	pg_assert_true(array_key_exists('file_id', ws_api_mail_file_schema()), 'a mail\'s file names its Files id');

	$files = ws_api_mail_files_group(array(array('id' => 11, 'sender_id' => 4, 'file_id' => 90, 'file_name' => 'a.pdf', 'size' => 3)));
	pg_assert_same(array_keys(ws_api_mail_file_schema()), array_keys($files[4][0]));
	pg_assert_same(90, $files[4][0]['file_id']);
}
