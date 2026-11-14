<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the E-mails screen's Drafts view
 * (includes/workspace/mail_drafts_screen.php): the view's rows, a draft's
 * fields as its form takes them, which sent mail deletes which draft, the
 * view in the list's filters and toolbar, and the wiring into the actions,
 * the send paths and the screen's script. No database.
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

function workspace_mail_drafts_screen_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function workspace_mail_drafts_screen_test_row($fields)
{
	return array_merge(array(
		'id' => 4, 'conversation_id' => 0, 'box_id' => 2, 'to_list' => 'ali@example.com', 'cc_list' => '', 'bcc_list' => '',
		'subject' => 'Price list', 'body_text' => "Hello,\n\nthe list is attached.", 'created_at' => time(),
		'box_name' => 'Support', 'conversation_subject' => '', 'customer' => '',
	), $fields);
}

function test_workspace_mail_drafts_screen_view_html()
{
	pg_assert_contains('No draft.', ws_mail_drafts_view_html(array()), 'empty');

	$html = ws_mail_drafts_view_html(array(
		workspace_mail_drafts_screen_test_row(array()),
		workspace_mail_drafts_screen_test_row(array('id' => 5, 'conversation_id' => 9, 'to_list' => '', 'subject' => '', 'conversation_subject' => 'Order <12>', 'customer' => 'Ayşe')),
		workspace_mail_drafts_screen_test_row(array('id' => 6, 'to_list' => '', 'subject' => '', 'body_text' => '')),
	));

	pg_assert_contains('data-ws-mail-draft-open="4"', $html, 'Open');
	pg_assert_contains('data-ws-mail-draft-remove="4"', $html, 'Delete');
	pg_assert_contains('ali@example.com <span class="pg-list-row-title-verb">new e-mail</span>', $html, 'a new mail goes to its To');
	pg_assert_contains('<b>Price list</b> · Hello, the list is attached.', $html, 'its subject and first words on one line');
	pg_assert_contains('Ayşe <span class="pg-list-row-title-verb">reply</span>', $html, 'a reply goes to its customer');
	pg_assert_contains('<b>Order &lt;12&gt;</b>', $html, 'a reply shows its conversation\'s subject, escaped');
	pg_assert_contains('No recipient yet', $html, 'an unfinished draft');
	pg_assert_contains('(no subject)', $html, 'no subject');
	pg_assert_contains('Nothing is sent until you press Send.', $html, 'what the view is');
}

function test_workspace_mail_drafts_screen_form_fields()
{
	$fields = ws_mail_draft_form_fields(workspace_mail_drafts_screen_test_row(array('cc_list' => 'a@b.test', 'bcc_list' => 'c@d.test')), 0);
	pg_assert_same(array('id' => 4, 'kind' => 'new', 'conversation_id' => 0, 'channel_id' => 0, 'box_id' => 2, 'to' => 'ali@example.com', 'cc' => 'a@b.test', 'bcc' => 'c@d.test',
		'subject' => 'Price list', 'text' => "Hello,\n\nthe list is attached."), $fields, 'a new mail\'s form');

	$fields = ws_mail_draft_form_fields(workspace_mail_drafts_screen_test_row(array('conversation_id' => 9)), 3);
	pg_assert_same(array('reply', 9, 3, '', ''), array($fields['kind'], $fields['conversation_id'], $fields['channel_id'], $fields['to'], $fields['subject']),
		'a reply\'s: To and subject are the conversation\'s');
}

function test_workspace_mail_drafts_screen_sent_matches()
{
	pg_assert_true(ws_mail_draft_sent_matches(array('conversation_id' => 9), 9), 'an answer in its conversation');
	pg_assert_false(ws_mail_draft_sent_matches(array('conversation_id' => 9), 8), 'not another conversation\'s answer');
	pg_assert_false(ws_mail_draft_sent_matches(array('conversation_id' => 9), 0), 'not a new mail');
	pg_assert_true(ws_mail_draft_sent_matches(array('conversation_id' => 0), 0), 'a new mail\'s draft and a new mail');
	pg_assert_false(ws_mail_draft_sent_matches(array('conversation_id' => 0), 9), 'not an answer');
}

function test_workspace_mail_drafts_screen_view_in_the_list()
{
	pg_assert_same('drafts', ws_mail_list_filters(array('view' => 'drafts'))['view'], 'the view is known');
	pg_assert_contains('>Drafts</a>', ws_mail_list_filters_html(ws_mail_list_filters(array()), array(), true), 'offered to whoever writes mail');
	pg_assert_true(strpos(ws_mail_list_filters_html(ws_mail_list_filters(array()), array(), false), '>Drafts</a>') === false, 'not to a reader alone');
	pg_assert_contains('btn-group btn-group-sm flex-wrap', ws_mail_list_filters_html(ws_mail_list_filters(array()), array(), true), 'the views wrap on a phone');
	pg_assert_contains("((\$filters['view'] ?? '') === 'drafts') ? ''", workspace_mail_drafts_screen_test_source('includes/workspace/mail_list.php'), 'no conversation rows in Drafts');
	pg_assert_contains("(\$filters['view'] === 'drafts') ? ws_mail_drafts_view_html(ws_mail_drafts_view(\$viewer))", workspace_mail_drafts_screen_test_source('messages_mail.php'), 'the screen draws it');
}

function test_workspace_mail_drafts_screen_wiring()
{
	$actions = workspace_mail_drafts_screen_test_source('includes/workspace/actions.php');

	pg_assert_contains("case 'ws_mail_draft_open':", $actions, 'Open');
	pg_assert_contains("case 'ws_mail_draft_remove':", $actions, 'Delete');
	pg_assert_contains("ws_mail_draft_remove(\$viewer, (int) \$request['draft_id'], \$conversation_id);", $actions, 'an answer deletes its reply draft');
	pg_assert_contains("ws_mail_draft_remove(\$viewer, (int) \$request['draft_id'], 0);", $actions, 'a new mail deletes its draft');
	pg_assert_true(strpos($actions, "ws_mail_draft_remove(\$viewer, (int) \$request['draft_id'], \$conversation_id);") > strpos($actions, "\$result = ws_mail_send(\$viewer, array('conversation_id' => \$conversation_id"),
		'only after the answer is stored');

	foreach (array('ws_mail_draft_open', 'ws_mail_draft_remove') as $action) {
		pg_assert_true(in_array($action, ws_mail_actions_without_workspace(), true), $action . ' works with the Workspace off');
		pg_assert_contains("'" . $action . "'", workspace_mail_drafts_screen_test_source('includes/panel/actions.php'), $action . ' is a panel action');
	}

	$js = workspace_mail_drafts_screen_test_source('assets/js/workspace_mail.js');
	pg_assert_contains('[data-ws-mail-draft-open], [data-ws-mail-draft-remove]', $js, 'the buttons');
	pg_assert_contains('post.draft_id = kept.id;', $js, 'the reply drawer sends its draft id');
	pg_assert_contains('post.draft_id = server.id;', $js, 'the New e-mail form sends its draft id');
	pg_assert_contains('var kept = server ? null : newDraftRead(key);', $js, 'the browser\'s kept form stays out of a server draft');
	pg_assert_contains("'draft_deleted'", workspace_mail_drafts_screen_test_source('includes/workspace/mail_pane.php'), 'the screen\'s words');
}
