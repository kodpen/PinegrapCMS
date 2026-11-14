<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: the E-mails screen's Drafts view. The drafts kept on the server
 * (mail_drafts.php) that the person reads are listed, newest change first:
 * their own new mails' and the replies' of the conversations whose channel
 * they read. Open fills the Reply by e-mail drawer or the New e-mail form
 * (workspace_mail.js), and Send there is the person's: once the mail is
 * stored the draft is deleted. Save draft there writes the changes back;
 * Delete, asked first, removes a draft without sending it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most drafts the Drafts view lists. */
define('WS_MAIL_DRAFTS_VIEW_MAX', 100);

/**
 * Whether the person gets the Drafts view: a team member once drafts are
 * kept. Guests write none.
 *
 * @param array $viewer
 * @return bool
 */
function ws_mail_drafts_view_offered($viewer)
{
    return !empty($viewer['member']) && function_exists('ws_mail_drafts_ready') && ws_mail_drafts_ready();
}

/**
 * The drafts the person reads, each with its mailbox's name and, for a
 * reply, its conversation's subject and customer.
 *
 * @param array $viewer
 * @return array[] ws_mail_items rows with box_name, conversation_subject, customer
 */
function ws_mail_drafts_view($viewer)
{
    if (!ws_mail_drafts_view_offered($viewer)) {
        return array();
    }

    $rows = array_slice(ws_mail_drafts_list($viewer, array(), WS_MAIL_DRAFTS_VIEW_MAX), 0, WS_MAIL_DRAFTS_VIEW_MAX);
    $boxes = array();
    $conversations = array();

    foreach ((array) db_items("SELECT id, name FROM ws_mail_boxes") as $box) {
        $boxes[(int) $box['id']] = (string) $box['name'];
    }

    $ids = array_values(array_unique(array_filter(array_map(function ($row) {
        return (int) $row['conversation_id'];
    }, $rows))));

    if ($ids) {
        foreach ((array) db_items("SELECT id, subject, customer_name, customer_email FROM ws_mail_conversations WHERE id IN (" . implode(',', $ids) . ")") as $conversation) {
            $conversations[(int) $conversation['id']] = $conversation;
        }
    }

    foreach ($rows as $index => $row) {
        $conversation = $conversations[(int) $row['conversation_id']] ?? null;
        $rows[$index]['box_name'] = (string) ($boxes[(int) $row['box_id']] ?? '');
        $rows[$index]['conversation_subject'] = $conversation ? (string) $conversation['subject'] : '';
        $rows[$index]['customer'] = $conversation
            ? ((trim((string) $conversation['customer_name']) !== '') ? (string) $conversation['customer_name'] : (string) $conversation['customer_email'])
            : '';
    }

    return $rows;
}

/**
 * The Drafts view: each draft as a row of the screen's list, with its kind,
 * whom it goes to and the time; its subject and first words; a meta line
 * with the mailbox, Open draft and Delete. Pure but for the words.
 *
 * @param array[] $rows ws_mail_drafts_view()
 * @return string HTML
 */
function ws_mail_drafts_view_html($rows)
{
    $intro = '<p class="pg-field-help px-3 my-3">' . h(lang('Drafts kept on the server, by you or an assistant. Nothing is sent until you press Send.')) . '</p>';

    if (!$rows) {
        return $intro . '<p class="fg-secondary my-5 px-3">' . h(lang('No draft.')) . '</p>';
    }

    $html = '';

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $reply = ((int) $row['conversation_id'] > 0);
        $subject = $reply ? (string) $row['conversation_subject'] : (string) $row['subject'];
        $subject = (trim($subject) !== '') ? $subject : lang('(no subject)');
        $to = $reply ? (string) $row['customer'] : (string) $row['to_list'];
        $words = trim(preg_replace('/\s+/u', ' ', (string) $row['body_text']));

        $html .= '<div class="ws-mail-row ws-mail-draft" data-ws-mail-draft-row="' . $id . '">'
            . '<span class="ws-mail-row-head">'
            . '<span class="pg-icon-tile pg-tone-blue" aria-hidden="true"><span class="bi ' . ($reply ? 'bi-reply' : 'bi-pencil-square') . '"></span></span>'
            . '<span class="ws-mail-who">' . h(($to !== '') ? $to : lang('No recipient yet'))
            . ' <span class="pg-list-row-title-verb">' . h($reply ? lang('reply') : lang('new e-mail')) . '</span></span>'
            . '<span class="ws-mail-time">' . h(ws_time_label((int) $row['created_at'])) . '</span></span>'
            . '<span class="ws-mail-subject">' . h($subject) . '</span>'
            . (($words !== '') ? '<span class="ws-mail-snippet">' . h(mb_substr($words, 0, 160)) . '</span>' : '')
            . '<span class="ws-mail-row-meta"><span>' . h((string) $row['box_name']) . '</span>'
            . '<span class="ms-auto d-inline-flex gap-2">'
            . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-ws-mail-draft-open="' . $id . '">' . h(lang('Open draft')) . '</button>'
            . '<button type="button" class="pg-button pg-button-ghost pg-button-sm" data-ws-mail-draft-remove="' . $id . '">' . h(lang('Delete')) . '</button>'
            . '</span></span></div>';
    }

    return $intro . '<div class="ws-mail-rows ws-mail-drafts">' . $html . '</div>';
}

/**
 * Save draft in an opened draft's form: its changed fields written back,
 * as the API's change does (a reply's To and subject stay its
 * conversation's), by whoever may change it.
 *
 * @param array $viewer
 * @param int   $id
 * @param array $request to, cc, bcc, subject, text
 * @return array ok, error
 */
function ws_mail_draft_save($viewer, $id, $request)
{
    $draft = ws_mail_drafts_view_offered($viewer) ? ws_mail_draft((int) $id) : null;

    if (!$draft || !ws_mail_draft_reached($viewer, $draft, true)) {
        return array('ok' => false, 'error' => lang('This draft cannot be opened.'));
    }

    $input = ws_mail_draft_input(array_intersect_key((array) $request, array_flip(array('to', 'cc', 'bcc', 'subject', 'text'))));

    if (!$input['ok']) {
        return array('ok' => false, 'error' => ws_mail_draft_error_text($input['error']));
    }

    $merged = ws_mail_draft_merge($draft, $input['fields']);

    if (!ws_mail_draft_has_words($merged)) {
        return array('ok' => false, 'error' => ws_mail_draft_error_text('empty'));
    }

    return ws_mail_draft_update($draft, $merged, time())
        ? array('ok' => true, 'error' => '')
        : array('ok' => false, 'error' => lang('The draft could not be saved.'));
}

/**
 * A draft opened from the Drafts view, as the form it fills takes it. Only
 * whoever may change it opens it: they are the one who sends it.
 *
 * @param array $viewer
 * @param int   $id
 * @return array ok, error, draft (id, kind, conversation_id, channel_id,
 *               box_id, to, cc, bcc, subject, text)
 */
function ws_mail_draft_open($viewer, $id)
{
    $draft = ws_mail_drafts_view_offered($viewer) ? ws_mail_draft((int) $id) : null;

    if (!$draft || !ws_mail_draft_reached($viewer, $draft, true)) {
        return array('ok' => false, 'error' => lang('This draft cannot be opened.'), 'draft' => null);
    }

    $conversation_id = (int) $draft['conversation_id'];
    $channel_id = ($conversation_id > 0) ? (int) db_value("SELECT channel_id FROM ws_mail_conversations WHERE id = '" . $conversation_id . "'") : 0;

    return array('ok' => true, 'error' => '', 'draft' => ws_mail_draft_form_fields($draft, $channel_id));
}

/**
 * A draft's row as the forms take it. Pure.
 *
 * @param array $draft      ws_mail_items row
 * @param int   $channel_id the reply's conversation's channel, 0 for a new mail
 * @return array
 */
function ws_mail_draft_form_fields($draft, $channel_id)
{
    $reply = ((int) $draft['conversation_id'] > 0);

    return array(
        'id'              => (int) $draft['id'],
        'kind'            => $reply ? 'reply' : 'new',
        'conversation_id' => (int) $draft['conversation_id'],
        'channel_id'      => (int) $channel_id,
        'box_id'          => (int) $draft['box_id'],
        'to'              => $reply ? '' : (string) $draft['to_list'],
        'cc'              => (string) $draft['cc_list'],
        'bcc'             => (string) ($draft['bcc_list'] ?? ''),
        'subject'         => $reply ? '' : (string) $draft['subject'],
        'text'            => (string) $draft['body_text'],
    );
}

/**
 * Delete in the Drafts view, and the draft a sent mail came from: removed
 * when the person may change it. A reply's draft goes only with a mail of
 * its own conversation, a new mail's only with a new mail.
 *
 * @param array    $viewer
 * @param int      $id
 * @param int|null $conversation_id the sent mail's conversation; null for Delete,
 *                                  0 for a new mail
 * @return bool deleted
 */
function ws_mail_draft_remove($viewer, $id, $conversation_id = null)
{
    $draft = ((int) $id > 0) && ws_mail_drafts_view_offered($viewer) ? ws_mail_draft((int) $id) : null;

    if (!$draft || !ws_mail_draft_reached($viewer, $draft, true)) {
        return false;
    }

    if (($conversation_id !== null) && !ws_mail_draft_sent_matches($draft, (int) $conversation_id)) {
        return false;
    }

    return ws_mail_draft_delete((int) $draft['id']);
}

/**
 * Whether a sent mail came from this draft: a reply's draft and an answer
 * in its conversation, or a new mail's draft and a new mail. Pure.
 *
 * @param array $draft
 * @param int   $conversation_id the answered conversation; 0 for a new mail
 * @return bool
 */
function ws_mail_draft_sent_matches($draft, $conversation_id)
{
    return (int) $draft['conversation_id'] === (int) $conversation_id;
}
