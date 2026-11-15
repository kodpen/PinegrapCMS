<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Undo send (includes/workspace/mail_later.php): the panel's Send
 * keeps the mail waiting for a few seconds, Undo turns it back into its
 * draft, the browser asks for it to leave afterwards. No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_undo_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_workspace_mail_undo_waits_ten_seconds()
{
	pg_assert_same(10, WS_MAIL_UNDO_SECONDS, 'ten seconds, no setting');
}

function test_workspace_mail_undo_send_at()
{
	$now = 1800000000;

	pg_assert_same($now + 10, ws_mail_undo_send_at(array('undo' => '1'), 0, true, $now), 'the panel asks: it waits ten seconds');
	pg_assert_same(0, ws_mail_undo_send_at(array(), 0, true, $now), 'not asked (an older page): at once');
	pg_assert_same(0, ws_mail_undo_send_at(array('undo' => '0'), 0, true, $now), 'asked no');
	pg_assert_same(0, ws_mail_undo_send_at(array('undo' => array('1')), 0, true, $now), 'not text');
	pg_assert_same(0, ws_mail_undo_send_at(array('undo' => '1'), 0, false, $now), 'before the column: at once');
	pg_assert_same($now + 3600, ws_mail_undo_send_at(array('undo' => '1'), $now + 3600, true, $now), 'a time chosen with Send later is kept');
}

function test_workspace_mail_undo_only_the_writers_own_waiting_mail()
{
	$item = array('id' => 7, 'user_id' => 3, 'direction' => 'out', 'send_state' => 'later', 'send_at' => 1800000010);

	pg_assert_true(ws_mail_undo_is_own($item, 3), 'the writer');
	pg_assert_true(!ws_mail_undo_is_own($item, 4), 'another person who may answer there');
	pg_assert_true(!ws_mail_undo_is_own(array('send_state' => 'queued') + $item, 3), 'already on its way');
	pg_assert_true(!ws_mail_undo_is_own(array('direction' => 'in') + $item, 3), 'an incoming mail');
	pg_assert_true(!ws_mail_undo_is_own(null, 3), 'no row');
}

function test_workspace_mail_undo_conversation_after_an_answer_is_taken_back()
{
	$conversation = array('status' => 'waiting', 'first_reply_at' => 1800000000, 'last_out_at' => 1800000000);
	$item = array('created_at' => 1800000000);

	pg_assert_same(array('status' => 'open', 'first_reply_at' => 0, 'last_out_at' => 0),
		ws_mail_undo_conversation_fields($conversation, $item, 0, 'in'), 'the first answer taken back: open again, never answered');

	pg_assert_same(array('status' => 'waiting', 'first_reply_at' => 1700000000, 'last_out_at' => 1750000000),
		ws_mail_undo_conversation_fields(array('first_reply_at' => 1700000000) + $conversation, $item, 1750000000, 'out'),
		'an earlier answer stays the first; the last one sent stays waiting');

	pg_assert_same('open', ws_mail_undo_conversation_fields($conversation, $item, 1750000000, 'in')['status'], 'the customer wrote last');
	pg_assert_same('closed', ws_mail_undo_conversation_fields(array('status' => 'closed') + $conversation, $item, 0, 'in')['status'], 'a status set since is left');
}

function test_workspace_mail_undo_wiring()
{
	$actions = workspace_mail_undo_test_source('includes/workspace/actions.php');
	$panel = workspace_mail_undo_test_source('includes/panel/actions.php');
	$script = workspace_mail_undo_test_source('assets/js/workspace_mail.js');
	$toast = workspace_mail_undo_test_source('assets/js/workspace.js');

	pg_assert_contains("'ws_mail_undo_send'", $actions, 'the member action list');
	pg_assert_contains("case 'ws_mail_undo_send':", $actions, 'the action');
	pg_assert_same(2, substr_count($actions, 'ws_mail_undo_send_at($request, $later[\'at\'], ws_mail_later_ready(), time())'), 'New e-mail and Reply by e-mail ask it');
	pg_assert_contains("'ws_mail_undo_send'", $panel, 'a row of pg_panel_actions()');
	pg_assert_contains("post.undo = 1", $script, 'the panel asks for it');
	pg_assert_contains("'ws_mail_undo_send'", $script, 'Undo');
	pg_assert_contains("'ws_mail_later_send'", $script, 'the browser asks for it to leave');
	pg_assert_contains('action.onClick', $toast, 'a toast with a button');
	pg_assert_true(strpos(workspace_mail_undo_test_source('includes/workspace/mail_send.php'), 'undo') === false, 'the API sends at once: ws_mail_send() knows nothing of it');
}
