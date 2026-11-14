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
	pg_assert_contains('<span class="ws-mail-subject">Price list</span><span class="ws-mail-snippet">Hello, the list is attached.</span>', $html, 'its subject and first words');
	pg_assert_contains('<div class="ws-mail-rows ws-mail-drafts">', $html, 'the screen\'s own list');
	pg_assert_contains('<span class="ws-mail-row-meta"><span>Support</span>', $html, 'the mailbox on the meta line');
	pg_assert_contains('Ayşe <span class="pg-list-row-title-verb">reply</span>', $html, 'a reply goes to its customer');
	pg_assert_contains('<span class="ws-mail-subject">Order &lt;12&gt;</span>', $html, 'a reply shows its conversation\'s subject, escaped');
	pg_assert_contains('No recipient yet', $html, 'an unfinished draft');
	pg_assert_contains('(no subject)', $html, 'no subject');
	pg_assert_contains('Nothing is sent until you press Send.', $html, 'what the view is');
}

function test_workspace_mail_drafts_screen_form_fields()
{
	$fields = ws_mail_draft_form_fields(workspace_mail_drafts_screen_test_row(array('cc_list' => 'a@b.test', 'bcc_list' => 'c@d.test')), 0);
	pg_assert_same(array('id' => 4, 'kind' => 'new', 'conversation_id' => 0, 'channel_id' => 0, 'box_id' => 2, 'to' => 'ali@example.com', 'cc' => 'a@b.test', 'bcc' => 'c@d.test',
		'subject' => 'Price list', 'text' => "Hello,\n\nthe list is attached.", 'files' => array(), 'html_note' => ''), $fields, 'a new mail\'s form');

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
	$drafts = ws_mail_list_filters_html(ws_mail_list_filters(array('view' => 'drafts')), array(), true, true, array('email', 'sms'));
	pg_assert_true(strpos($drafts, 'name="status"') === false && strpos($drafts, 'name="holder"') === false, 'no status or holder in Drafts');
	pg_assert_true(strpos($drafts, 'First answer overdue') === false, 'no first-answer filter in Drafts');
	pg_assert_contains("((\$filters['view'] ?? '') === 'drafts') ? ''", workspace_mail_drafts_screen_test_source('includes/workspace/mail_list.php'), 'no conversation rows in Drafts');
	pg_assert_contains("(\$filters['view'] === 'drafts') ? ws_mail_drafts_view_html(ws_mail_drafts_view(\$viewer))", workspace_mail_drafts_screen_test_source('messages_mail.php'), 'the screen draws it');
}

function test_workspace_mail_drafts_screen_wiring()
{
	$actions = workspace_mail_drafts_screen_test_source('includes/workspace/actions.php');

	pg_assert_contains("case 'ws_mail_draft_open':", $actions, 'Open');
	pg_assert_contains("case 'ws_mail_draft_remove':", $actions, 'Delete');
	pg_assert_contains("case 'ws_mail_draft_save':", $actions, 'Save draft');
	pg_assert_contains("ws_mail_draft_remove(\$viewer, (int) \$request['draft_id'], \$conversation_id);", $actions, 'an answer deletes its reply draft');
	pg_assert_contains("ws_mail_draft_remove(\$viewer, (int) \$request['draft_id'], 0);", $actions, 'a new mail deletes its draft');
	pg_assert_true(strpos($actions, "ws_mail_draft_remove(\$viewer, (int) \$request['draft_id'], \$conversation_id);") > strpos($actions, "\$result = ws_mail_send(\$viewer, array('conversation_id' => \$conversation_id"),
		'only after the answer is stored');

	foreach (array('ws_mail_draft_open', 'ws_mail_draft_save', 'ws_mail_draft_remove') as $action) {
		pg_assert_true(in_array($action, ws_mail_actions_without_workspace(), true), $action . ' works with the Workspace off');
		pg_assert_contains("'" . $action . "'", workspace_mail_drafts_screen_test_source('includes/panel/actions.php'), $action . ' is a panel action');
	}

	$js = workspace_mail_drafts_screen_test_source('assets/js/workspace_mail.js');
	pg_assert_contains('[data-ws-mail-draft-open], [data-ws-mail-draft-remove]', $js, 'the buttons');
	pg_assert_contains('post.draft_id = kept.id;', $js, 'the reply drawer sends its draft id');
	pg_assert_contains('post.draft_id = server.id;', $js, 'the New e-mail form sends its draft id');
	pg_assert_contains('var kept = server ? null : newDraftRead(key);', $js, 'the browser\'s kept form stays out of a server draft');
	pg_assert_contains("'draft_deleted'", workspace_mail_drafts_screen_test_source('includes/workspace/mail_pane.php'), 'the screen\'s words');
	pg_assert_contains("footer.insertBefore(draftSave(h, kept,", $js, 'Save draft beside Send in the reply drawer');
	pg_assert_contains("footer.appendChild(draftSave(h, server,", $js, 'and in the New e-mail form');
	pg_assert_contains("window.pgConfirm({ title: w('delete'), message: w('draft_delete')", $js, 'Delete asks first');

	$save = workspace_mail_drafts_screen_test_source('includes/workspace/mail_drafts_screen.php');
	pg_assert_contains('ws_mail_draft_reached($viewer, $draft, true)', $save, 'only whoever may change a draft opens or saves it');
	pg_assert_contains('ws_mail_draft_merge($draft, $input[\'fields\'])', $save, 'a reply\'s To and subject stay its conversation\'s');
}

function test_workspace_mail_drafts_screen_app_pill()
{
	pg_assert_same('', ws_mail_draft_app_label(0, array(5 => 'Claude')), 'a draft written in the panel or by the person names nobody');
	pg_assert_same('Claude', ws_mail_draft_app_label(5, array(5 => 'Claude')), 'the application by its name');
	pg_assert_same('an assistant', ws_mail_draft_app_label(7, array(5 => 'Claude')), 'a deleted application');

	$html = ws_mail_drafts_view_html(array(
		workspace_mail_drafts_screen_test_row(array('app_name' => 'Claude <bot>')),
		workspace_mail_drafts_screen_test_row(array('id' => 5, 'app_name' => '')),
	));

	pg_assert_contains('<span class="pg-pill pg-tone-violet" data-ws-mail-draft-app>', $html, 'a pill on the meta line');
	pg_assert_contains('Kept by Claude &lt;bot&gt;', $html, 'its name, escaped');
	pg_assert_same(1, substr_count($html, 'data-ws-mail-draft-app'), 'only the draft an application kept');
	pg_assert_contains('api_apps', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_drafts_screen.php'), 'the name read from the application\'s row');
}
