<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for faults found in Workspace e-mail read and drafted over the API
 * and MCP (api_mail.php, api_mail_drafts.php, mail_drafts.php) that need no
 * database.
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

function ws_mail_api_hunt_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function test_ws_api_mail_draft_change_of_text_drops_stale_html()
{
	$draft = array('id' => 5, 'conversation_id' => 0, 'body_text' => 'Hello there', 'body_html' => '<p>Hello <b>there</b></p>');

	pg_assert_same(array('text' => 'New words', 'html' => ''), ws_api_mail_draft_update_fields($draft, array('text' => 'New words')),
		'new words without html drop the HTML that says the old ones');
	pg_assert_same(array('text' => 'Hello  there'), ws_api_mail_draft_update_fields($draft, array('text' => 'Hello  there')),
		'the same words keep the HTML');
	pg_assert_same(array('text' => 'New', 'html' => '<p>New</p>'), ws_api_mail_draft_update_fields($draft, array('text' => 'New', 'html' => '<p>New</p>')),
		'html sent with the text is kept as sent');
	pg_assert_same(array('cc' => 'c@example.com'), ws_api_mail_draft_update_fields($draft, array('cc' => 'c@example.com')), 'no text, the HTML stays');
	pg_assert_same(array('text' => 'New'), ws_api_mail_draft_update_fields(array('body_text' => 'Old', 'body_html' => ''), array('text' => 'New')),
		'a draft without HTML gains no html key');

	$source = ws_mail_api_hunt_test_source('ws_api_mail_drafts_update');
	pg_assert_true(strpos($source, 'ws_api_mail_draft_update_fields(') < strpos($source, 'ws_mail_draft_merge('), 'the update reads the fields after the rule');
}

function test_ws_mail_draft_delete_lets_a_held_drafts_files_go()
{
	$lines = array(array('id' => 40, 'file_id' => 7), array('id' => 41, 'file_id' => 8), array('id' => 42, 'file_id' => 9));

	pg_assert_same(array(7, 9), ws_mail_draft_held_files_unused($lines, array(8)), 'a file another line or draft still holds stays');
	pg_assert_same(array(), ws_mail_draft_held_files_unused(array(), array()), 'no lines, nothing');
	pg_assert_same(array(7), ws_mail_draft_held_files_unused(array(array('id' => 1, 'file_id' => 7), array('id' => 2, 'file_id' => 7)), array()), 'each file once');

	$source = ws_mail_api_hunt_test_source('ws_mail_draft_delete');
	$delete = strpos($source, 'DELETE FROM ws_mail_items');
	$discard = strpos($source, 'ws_upload_discard(');
	pg_assert_true(($delete !== false) && ($discard !== false) && ($delete < $discard), 'the files go only after the draft row is gone');
	pg_assert_contains('deleted_at > 0', $source, 'only the held lines taken out of the channel');
}

function test_ws_mail_draft_reply_keeps_its_conversations_to_and_subject()
{
	$conversation = array('id' => 7, 'customer_email' => 'customer@example.com', 'subject' => 'Order 12');

	pg_assert_same(array('to' => 'customer@example.com', 'cc' => 'c@example.com', 'bcc' => '', 'subject' => 'Order 12', 'text' => 'Hi'),
		ws_mail_draft_reply_fields(array('to' => 'other@example.com', 'cc' => 'c@example.com', 'bcc' => '', 'subject' => 'Changed', 'text' => 'Hi'), $conversation),
		'a reply goes to its customer under its subject, whatever was sent');

	$source = ws_mail_api_hunt_test_source('ws_mail_draft_create_check');
	pg_assert_contains('ws_mail_draft_reply_fields(', $source, 'a new reply draft takes them from its conversation');
}
