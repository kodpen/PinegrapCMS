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
