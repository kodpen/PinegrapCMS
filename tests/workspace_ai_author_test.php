<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the Workspace assistants name a message's author
 * (ws_ai_author_words(), includes/workspace/ai.php): a person, an
 * application, a guest, an e-mail from outside the team and an answer the
 * team sent by e-mail. The source checks hold that Pinegrap AI, the MCP
 * callers and the recap all word an author through it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function workspace_ai_author_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file);
}

function workspace_ai_author_test_body($source, $function)
{
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}\n", $start);

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

function test_workspace_ai_author_person_app_guest()
{
	pg_assert_same('Ayşe Yılmaz <@user:4>', ws_ai_author_words('user', 'Ayşe Yılmaz', '<@user:4>'));
	pg_assert_same('Ayşe Yılmaz', ws_ai_author_words('user', 'Ayşe Yılmaz'));
	pg_assert_same('Ayşe Yılmaz (the reader)', ws_ai_author_words('user', 'Ayşe Yılmaz', '(the reader)'));
	pg_assert_same('Claude', ws_ai_author_words('app', 'Claude'));
	pg_assert_same('Mehmet (guest)', ws_ai_author_words('guest', 'Mehmet'));
}

function test_workspace_ai_author_incoming_mail()
{
	pg_assert_same('Jane Doe <jane@example.com> (e-mail from outside the team)', ws_ai_author_words('mail', 'Jane Doe', 'jane@example.com'));
	pg_assert_same('jane@example.com (e-mail from outside the team)', ws_ai_author_words('mail', '', 'jane@example.com'));
	pg_assert_same('jane@example.com (e-mail from outside the team)', ws_ai_author_words('mail', 'jane@example.com', 'jane@example.com'));
	pg_assert_same('Unknown sender (e-mail from outside the team)', ws_ai_author_words('mail', '', ''));
}

function test_workspace_ai_author_mail_name_one_line()
{
	pg_assert_same('Jane Doe <jane@example.com> (e-mail from outside the team)', ws_ai_author_words('mail', "Jane\n\nDoe", "jane@example.com\r\n"));
	pg_assert_same(100, mb_strlen(explode(' <', ws_ai_author_words('mail', str_repeat('x', 300), 'a@b.c'))[0]));
}

function test_workspace_ai_author_answer_writer()
{
	pg_assert_same('Ayşe Yılmaz <@user:4> (e-mail answer)', ws_ai_author_words('mail_out', 'Ayşe Yılmaz', '<@user:4>'));
	pg_assert_same('Ayşe Yılmaz (e-mail answer)', ws_ai_author_words('mail_out', 'Ayşe Yılmaz'));
	pg_assert_same('Unknown sender (e-mail answer)', ws_ai_author_words('mail_out', ''));
}

function test_workspace_ai_author_system()
{
	pg_assert_same('System', ws_ai_author_words('system', 'x'));
	pg_assert_same('System', ws_ai_author_words('', ''));
}

function test_workspace_ai_author_one_helper()
{
	$ai = workspace_ai_author_test_source('ai.php');
	$author = workspace_ai_author_test_body($ai, 'ws_ai_author');
	pg_assert_true(strpos($author, 'ws_ai_author_words(') !== false, 'ws_ai_author() words through ws_ai_author_words()');
	pg_assert_true(strpos($author, "'guest'") !== false, 'ws_ai_author() names a guest');
	pg_assert_true(strpos($author, 'ws_ai_mail_author(') !== false, 'ws_ai_author() names a mail sender');
	pg_assert_true(strpos(workspace_ai_author_test_body($ai, 'ws_ai_mail_author'), 'ws_mail_sender(') !== false, 'an answer is named through ws_mail_sender()');

	// The channel context reads the mails of its messages once.
	pg_assert_true(strpos(workspace_ai_author_test_body($ai, 'ws_ai_channel_context'), 'ws_mail_items_map(') !== false, 'the channel context loads mail senders once');

	$recap = workspace_ai_author_test_source('briefing.php');
	pg_assert_true(strpos(workspace_ai_author_test_body($recap, 'ws_recap_author'), 'ws_ai_author_words(') !== false, 'the recap words through ws_ai_author_words()');
	pg_assert_true(strpos(workspace_ai_author_test_body($recap, 'ws_recap_author'), 'ws_ai_mail_author(') !== false, 'the recap names a mail sender');
	pg_assert_true(strpos(workspace_ai_author_test_body($recap, 'ws_recap_transcript'), 'ws_mail_items_map(') !== false, 'the recap loads mail senders once');

	pg_assert_true(substr_count(workspace_ai_author_test_source('mcp_ask.php'), 'ws_ai_author(') >= 2, 'the MCP request names authors through ws_ai_author()');
	pg_assert_true(strpos(workspace_ai_author_test_body(workspace_ai_author_test_source('mcp_agent.php'), 'ws_mcp_agent_author'), 'ws_ai_author(') !== false, 'the MCP agent names authors through ws_ai_author()');
}
