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

    // The applications drafts were kept through (draft_app_id, 11.0.206),
    // named from their rows now, so a renamed one reads as it is called.
    $app_ids = array_values(array_unique(array_filter(array_map(function ($row) {
        return (int) ($row['draft_app_id'] ?? 0);
    }, $rows))));
    $apps = array();

    if ($app_ids) {
        foreach ((array) db_items("SELECT id, name FROM api_apps WHERE id IN (" . implode(',', $app_ids) . ")") as $app) {
            $apps[(int) $app['id']] = (string) $app['name'];
        }
    }

    foreach ($rows as $index => $row) {
        $rows[$index]['app_name'] = ws_mail_draft_app_label((int) ($row['draft_app_id'] ?? 0), $apps);
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
 * Whom a draft's pill names: the application it was kept through, by its
 * name; "an assistant" for one since deleted; '' for a draft written in the
 * panel or by the person's own device or connection. Pure but for the words.
 *
 * @param int   $app_id draft_app_id
 * @param array $apps   api_apps id => name
 * @return string
 */
function ws_mail_draft_app_label($app_id, $apps)
{
    if ((int) $app_id <= 0) {
        return '';
    }

    $name = trim((string) ($apps[(int) $app_id] ?? ''));

    return ($name !== '') ? $name : lang('an assistant');
}

/**
 * The Drafts view: each draft as a row of the screen's list, with its kind,
 * whom it goes to and the time; its subject and first words; a meta line
 * with the mailbox, the application it was kept through, Open draft and
 * Delete. Pure but for the words.
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
            . ((trim((string) ($row['app_name'] ?? '')) !== '')
                ? '<span class="pg-pill pg-tone-violet" data-ws-mail-draft-app><span class="bi bi-stars" aria-hidden="true"></span> '
                    . h(lang(array('string' => 'Kept by {var:1}', 'vars' => (string) $row['app_name']))) . '</span>'
                : '')
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
 * conversation's), by whoever may change it. The HTML a draft keeps stays
 * while its text is unchanged; a changed text takes the writing box's HTML
 * (format markup) or none, so body_html never says other words than the
 * text. The form's Files list (file_ids) replaces the draft's, each file
 * checked as at Send.
 *
 * @param array $viewer
 * @param int   $id
 * @param array $request to, cc, bcc, subject, text, format, file_ids
 * @return array ok, error
 */
function ws_mail_draft_save($viewer, $id, $request)
{
    $draft = ws_mail_drafts_view_offered($viewer) ? ws_mail_draft((int) $id) : null;

    if (!$draft || !ws_mail_draft_reached($viewer, $draft, true)) {
        return array('ok' => false, 'error' => lang('This draft cannot be opened.'));
    }

    $request = ws_mail_draft_save_request($viewer, $draft, (array) $request);
    $input = ws_mail_draft_input(array_intersect_key($request, array_flip(array('to', 'cc', 'bcc', 'subject', 'text', 'html', 'file_ids'))));

    if (!$input['ok']) {
        return array('ok' => false, 'error' => ws_mail_draft_error_text($input['error']));
    }

    $merged = ws_mail_draft_merge($draft, $input['fields']);
    $body = array_intersect_key($input['fields'], array('html' => 1, 'file_ids' => 1));
    $words = array_merge($merged, array('html' => (string) ($draft['body_html'] ?? ''), 'file_ids' => ws_mail_draft_file_ids($draft)), $body);

    if (!ws_mail_draft_has_words(((int) $draft['conversation_id'] > 0) ? ws_mail_draft_reply_words($words) : $words)) {
        return array('ok' => false, 'error' => ws_mail_draft_error_text('empty'));
    }

    $checked = ws_mail_draft_body_check($viewer, $body);

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => ws_mail_draft_error_text($checked['error'], $checked['detail']));
    }

    if (!ws_mail_draft_update($draft, $merged, time())) {
        return array('ok' => false, 'error' => lang('The draft could not be saved.'));
    }

    ws_mail_draft_keep_body((int) $draft['id'], $body);

    return array('ok' => true, 'error' => '');
}

/**
 * The Save draft form's fields as the draft store takes them. An unchanged
 * text is left out, so a draft kept with HTML keeps it and its own text; a
 * changed one written in the writing box becomes its plain text and HTML
 * (ws_mail_markup_parts()), else plain text with no HTML. file_ids is read
 * only when the form sent it.
 *
 * @param array $viewer
 * @param array $draft   ws_mail_items row
 * @param array $request to, cc, bcc, subject, text, format, file_ids
 * @return array to, cc, bcc, subject, text, html, file_ids (the keys that change)
 */
function ws_mail_draft_save_request($viewer, $draft, $request)
{
    $out = array_intersect_key($request, array_flip(array('to', 'cc', 'bcc', 'subject', 'file_ids')));

    if (!array_key_exists('text', $request) || !ws_mail_draft_text_changed($draft, $request['text'])) {
        return $out;
    }

    if ((string) ($request['format'] ?? '') === 'markup') {
        $parts = ws_mail_markup_parts($viewer, (string) $request['text']);

        return array_merge($out, array('text' => $parts['text'], 'html' => $parts['html']));
    }

    return array_merge($out, array('text' => (string) $request['text'], 'html' => ''));
}

/**
 * The text a draft's form shows: its own, else its HTML's words. Pure.
 *
 * @param array $draft ws_mail_items row
 * @return string
 */
function ws_mail_draft_form_text($draft)
{
    return ws_mail_text_or_html((string) ($draft['body_text'] ?? ''), (string) ($draft['body_html'] ?? ''));
}

/**
 * Whether the form's text says other words than the draft's form text,
 * spaces and line ends aside. Pure.
 *
 * @param array $draft ws_mail_items row
 * @param mixed $text  the form's text
 * @return bool
 */
function ws_mail_draft_text_changed($draft, $text)
{
    $even = function ($value) {
        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    };

    return $even(is_scalar($text) ? $text : '') !== $even(ws_mail_draft_form_text($draft));
}

/**
 * The HTML a mail sent from a draft keeps: the draft's own while the form's
 * text is still the draft's, so a draft kept with HTML is sent as it was
 * kept; '' once the person changed the text. Pure.
 *
 * @param array $draft ws_mail_items row
 * @param mixed $text  the form's text, before the writing box's markup is read
 * @return string
 */
function ws_mail_draft_sent_html($draft, $text)
{
    $html = (string) ($draft['body_html'] ?? '');

    return (($html !== '') && !ws_mail_draft_text_changed($draft, $text)) ? $html : '';
}

/**
 * A mail the panel sends from a draft (draft_id in the form): the draft's
 * HTML in place of the writing box's while its text is unchanged. Any
 * other mail is left as it came.
 *
 * @param array $viewer
 * @param array $mail    the mail ws_mail_send() takes
 * @param array $request the form's request (draft_id, text)
 * @return array
 */
function ws_mail_draft_sent_mail($viewer, $mail, $request)
{
    $draft = (!empty($request['draft_id']) && ws_mail_drafts_view_offered($viewer)) ? ws_mail_draft((int) $request['draft_id']) : null;

    if (!$draft || !ws_mail_draft_reached($viewer, $draft, true)) {
        return $mail;
    }

    $html = ws_mail_draft_sent_html($draft, $request['text'] ?? '');

    return ($html !== '') ? array_merge($mail, array('html' => $html)) : $mail;
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

    return array('ok' => true, 'error' => '', 'draft' => ws_mail_draft_form_fields($draft, $channel_id, ws_mail_draft_form_files($viewer, $draft)));
}

/**
 * The files a draft keeps, as the form's Files list takes them (id, name):
 * only those the person may send (ws_mail_send_file_reach()); Send checks
 * them again.
 *
 * @param array $viewer
 * @param array $draft ws_mail_items row
 * @return array[] id, name
 */
function ws_mail_draft_form_files($viewer, $draft)
{
    $files = array();

    foreach (ws_mail_draft_file_ids($draft) as $file_id) {
        $reach = ws_mail_send_file_reach($viewer, $file_id);

        if ($reach['ok']) {
            $files[] = array('id' => (int) $file_id, 'name' => (string) $reach['file']['name']);
        }
    }

    return $files;
}

/**
 * A draft's row as the forms take it: its text (its HTML's words when it
 * has none), its files for the Files list, and html_note when it keeps HTML
 * that the form does not show. Pure but for the words.
 *
 * @param array   $draft      ws_mail_items row
 * @param int     $channel_id the reply's conversation's channel, 0 for a new mail
 * @param array[] $files      ws_mail_draft_form_files()
 * @return array
 */
function ws_mail_draft_form_fields($draft, $channel_id, $files = array())
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
        'text'            => ws_mail_draft_form_text($draft),
        'files'           => array_values((array) $files),
        'html_note'       => ((string) ($draft['body_html'] ?? '') !== '')
            ? lang('This draft has a formatted body. It is sent as kept while you leave the text as it is; a changed text is sent as you write it.') : '',
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
