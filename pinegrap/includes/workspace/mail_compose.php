<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: writing a new e-mail. A mailbox's Signature (plain text kept in
 * its settings) put under the text, the mailboxes and channels the New
 * e-mail form offers, and the form's request turned into what
 * ws_mail_send() takes. Sending itself is mail_send.php's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most characters of a mailbox's Signature. */
define('WS_MAIL_SIGNATURE_MAX', 1000);

/** The most lines of a mailbox's Signature. */
define('WS_MAIL_SIGNATURE_LINES', 12);

/**
 * A Signature as typed, cleaned: line breaks kept, other control characters
 * dropped, spaces at line ends and empty lines at both ends removed. Pure.
 *
 * @param mixed $value
 * @return array ok, error, signature
 */
function ws_mail_signature_input($value)
{
    $text = is_scalar($value) ? str_replace(array("\r\n", "\r"), "\n", (string) $value) : '';
    $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', str_replace("\t", ' ', $text));
    $lines = array_map('rtrim', explode("\n", (string) $text));
    $text = trim(implode("\n", $lines), "\n");

    // A typed "-- " line is the separator the mail adds itself.
    $text = trim(preg_replace('/\A--\s*(\n|\z)/', '', $text), "\n");

    if (mb_strlen($text) > WS_MAIL_SIGNATURE_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'A signature is at most {var:1} characters.', 'vars' => WS_MAIL_SIGNATURE_MAX)), 'signature' => $text);
    }

    if (($text !== '') && (count(explode("\n", $text)) > WS_MAIL_SIGNATURE_LINES)) {
        return array('ok' => false, 'error' => lang(array('string' => 'A signature is at most {var:1} lines.', 'vars' => WS_MAIL_SIGNATURE_LINES)), 'signature' => $text);
    }

    return array('ok' => true, 'error' => '', 'signature' => $text);
}

/**
 * A mailbox's Signature, '' when it has none.
 *
 * @param array|null $box ws_mail_boxes row
 * @return string
 */
function ws_mail_box_signature($box)
{
    $signature = ws_mail_box_settings($box)['signature'] ?? '';

    return is_string($signature) ? $signature : '';
}

/**
 * The text of a new mail: what was written, then after the "-- " separator
 * the writer's name and the mailbox's Signature, or the mailbox's name when
 * it has none (the answer's signature, ws_mail_reply_body()). Pure.
 *
 * @param string $text
 * @param string $writer
 * @param string $box_name
 * @param string $signature
 * @return string
 */
function ws_mail_send_body($text, $writer, $box_name, $signature)
{
    $signature = trim((string) $signature, "\n ");

    return ws_mail_reply_body($text, $writer, ($signature !== '') ? $signature : $box_name, null);
}

/**
 * What the New e-mail form offers this person: each mailbox they may send
 * from (ws_mail_send_boxes()) with its Signature and the channels its mail
 * may be kept in, the box's own channel first. The room of a box kept in
 * no channel is marked (mailbox), so the form names it "The mailbox itself"
 * and not a channel. Pure apart from the boxes given.
 *
 * @param array[] $entries ws_mail_send_boxes()
 * @return array[] id, name, address, signature, channels (id, name, mailbox)
 */
function ws_mail_compose_boxes($entries)
{
    $out = array();

    foreach ((array) $entries as $entry) {
        $box = $entry['box'];
        $own = (int) $box['channel_id'];
        $channels = array();

        foreach ((array) $entry['channels'] as $channel) {
            $row = array('id' => (int) $channel['id'], 'name' => (string) $channel['name'], 'mailbox' => ws_channel_is_mailbox($channel));

            if ($row['id'] === $own) {
                array_unshift($channels, $row);
            } else {
                $channels[] = $row;
            }
        }

        $out[] = array(
            'id'        => (int) $box['id'],
            'name'      => (string) $box['name'],
            'address'   => (string) $box['address'],
            'signature' => ws_mail_box_signature($box),
            'channels'  => $channels,
        );
    }

    return $out;
}

/**
 * Whether the New e-mail form may be opened from a channel's menu: one of
 * the mailboxes this person may send from keeps its mail in that channel.
 * Pure apart from the boxes given.
 *
 * @param array[] $entries ws_mail_send_boxes()
 * @param int     $channel_id
 * @return bool
 */
function ws_mail_compose_in_channel($entries, $channel_id)
{
    foreach ((array) $entries as $entry) {
        foreach ((array) $entry['channels'] as $channel) {
            if ((int) $channel['id'] === (int) $channel_id) {
                return true;
            }
        }
    }

    return false;
}

/**
 * The files of the form as PHP gives them (the "files[]" form parts), each
 * name and bytes, or the reason one is not taken. Reads the temporary files.
 *
 * @param mixed $part $_FILES['files']
 * @return array ok, error, files (name, bytes)
 */
function ws_mail_compose_files($part)
{
    $out = array('ok' => true, 'error' => '', 'files' => array());

    if (!is_array($part) || !isset($part['name'])) {
        return $out;
    }

    $names = (array) $part['name'];

    if (count($names) > WS_MAIL_SEND_FILES) {
        return array('ok' => false, 'error' => ws_mail_send_error_text('too_many_files'), 'files' => array());
    }

    foreach (array_keys($names) as $key) {
        $name = is_string($names[$key]) ? $names[$key] : '';
        $error = (int) (((array) ($part['error'] ?? array()))[$key] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string) (((array) ($part['tmp_name'] ?? array()))[$key] ?? '');

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $uploaded = ($error === UPLOAD_ERR_OK) && ($tmp !== '') && is_uploaded_file($tmp);
        $refusal = ws_upload_part_error($error, $uploaded, $uploaded ? (int) @filesize($tmp) : 0, ws_upload_form_max_bytes());

        if ($refusal !== '') {
            return array('ok' => false, 'error' => $name . ': ' . $refusal, 'files' => array());
        }

        $bytes = @file_get_contents($tmp);

        $out['files'][] = array('name' => $name, 'bytes' => ($bytes === false) ? false : (string) $bytes);
    }

    return $out;
}

/**
 * The Files field of New e-mail and Reply by e-mail: the panel's picker
 * field over the files the writer may open in Files (kind channel_file, the
 * writing box's "From the file manager"), as WhatsApp's answer has it. ''
 * where the picker is missing.
 *
 * @param string $id the field's id
 * @return string HTML
 */
function ws_mail_file_field($id)
{
    if (!function_exists('pg_picker_field')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/picker.php');
    }

    return function_exists('pg_picker_field') ? pg_picker_field('channel_file', 'file_id', '', array('id' => (string) $id)) : '';
}

/**
 * The files of Files the form picked (file_ids, a comma list), each read as
 * the writing box's "From the file manager" reads one (ws_library_file()),
 * in ws_mail_compose_files()'s shape; the send checks kinds and sizes. A
 * file the file manager refuses is still taken when it is the file of a
 * message in a channel the person reads (ws_mail_send_file_reach()), as a
 * draft kept through the API may carry one into the form.
 *
 * @param array $viewer
 * @param array $request
 * @return array ok, error, files (name, bytes)
 */
function ws_mail_compose_library_files($viewer, $request)
{
    $out = array('ok' => true, 'error' => '', 'files' => array());
    $ids = ws_mail_compose_file_ids($request['file_ids'] ?? '');

    if (count($ids) > WS_MAIL_SEND_FILES) {
        return array('ok' => false, 'error' => ws_mail_send_error_text('too_many_files'), 'files' => array());
    }

    foreach ($ids as $file_id) {
        $library = ws_library_file($viewer, $file_id);

        if (!$library['ok']) {
            $reach = ws_mail_send_file_reach($viewer, $file_id);
            $library = $reach['ok'] ? array('ok' => true, 'error' => '', 'file' => $reach['file']) : $library;
        }

        $path = $library['ok'] ? pg_file_local_copy($file_id) : '';
        $bytes = ($path !== '') ? @file_get_contents($path) : false;

        if (!$library['ok'] || !is_string($bytes)) {
            return array('ok' => false, 'error' => $library['ok'] ? lang('Sorry, we could not find the file.') : (string) $library['error'], 'files' => array());
        }

        $out['files'][] = array('name' => (string) $library['file']['name'], 'bytes' => $bytes);
    }

    return $out;
}

/**
 * A file a person may send by its Files id (files.id), from the API: one
 * the file manager lets them bring into a conversation (ws_library_file()),
 * or the file of a message that is not deleted in a channel they read, since
 * the file manager does not reach a channel's own folder. $reached narrows
 * the channels as the API does for the Claude application.
 *
 * @param array    $viewer
 * @param int      $file_id
 * @param callable $reached channel row => bool; null for none
 * @return array ok, file (id, name, size) or null
 */
function ws_mail_send_file_reach($viewer, $file_id, $reached = null)
{
    $file_id = (int) $file_id;
    $missing = array('ok' => false, 'file' => null);

    if ($file_id <= 0) {
        return $missing;
    }

    $library = ws_library_file($viewer, $file_id);

    if ($library['ok']) {
        return array('ok' => true, 'file' => array('id' => $file_id, 'name' => (string) $library['file']['name'], 'size' => (int) $library['file']['size']));
    }

    $stored = db_item("SELECT id, name, size FROM files WHERE id = '" . $file_id . "'");

    if (!is_array($stored) || !$stored || pg_file_withheld((string) $stored['name'])
        || (function_exists('pg_files_include_erp_document') && pg_files_include_erp_document(array($file_id)))) {
        return $missing;
    }

    $messages = (array) db_items("SELECT channel_id, file_name FROM ws_messages
        WHERE file_id = '" . $file_id . "' AND deleted_at = 0 ORDER BY id LIMIT 50");

    // A mail held at its time keeps its files on the deleted lines of its
    // card (ws_mail_later_hold()): they are reached as its draft is - a
    // reply's by the channel's readers, a new mail's by its writer.
    if (defined('WS_MAIL_DRAFT_STATE')) {
        $messages = array_merge($messages, (array) db_items("SELECT m.channel_id, m.file_name FROM ws_messages m
            INNER JOIN ws_mail_items i ON i.id = m.sender_id
            WHERE m.file_id = '" . $file_id . "' AND m.deleted_at > 0 AND m.sender_kind = 'mail'
                AND i.send_state = '" . WS_MAIL_DRAFT_STATE . "' AND (i.conversation_id > 0 OR i.user_id = '" . (int) ($viewer['id'] ?? 0) . "')
            ORDER BY m.id LIMIT 50"));
    }

    foreach ($messages as $message) {
        $channel = ws_channel((int) $message['channel_id']);

        if ($channel && ws_can_read_channel($viewer, $channel) && (($reached === null) || call_user_func($reached, $channel))) {
            $name = ((string) $message['file_name'] !== '') ? (string) $message['file_name'] : (string) $stored['name'];

            return array('ok' => true, 'file' => array('id' => $file_id, 'name' => $name, 'size' => (int) $stored['size']));
        }
    }

    return $missing;
}

/**
 * The files an API mail or draft names by Files id, each one the sender may
 * send (ws_mail_send_file_reach()), at most WS_MAIL_SEND_FILES of together
 * at most WS_MAIL_SEND_FILES_BYTES, checked by size before any is read.
 * With $read the bytes come in ws_mail_compose_files()'s shape; without it
 * only the check is made (a draft keeps the ids).
 *
 * @param array    $viewer
 * @param int[]    $ids
 * @param callable $reached as ws_mail_send_file_reach()
 * @param bool     $read
 * @return array ok, error (too_many_files | files_too_large | file_refused), detail, files (name, bytes)
 */
function ws_mail_send_files_by_id($viewer, $ids, $reached = null, $read = true)
{
    $out = array('ok' => true, 'error' => '', 'detail' => '', 'files' => array());
    $ids = ws_mail_compose_file_ids(implode(',', array_map('intval', (array) $ids)));

    if (count($ids) > WS_MAIL_SEND_FILES) {
        return array_merge($out, array('ok' => false, 'error' => 'too_many_files'));
    }

    $found = array();
    $total = 0;

    foreach ($ids as $file_id) {
        $reach = ws_mail_send_file_reach($viewer, $file_id, $reached);

        if (!$reach['ok']) {
            return array_merge($out, array('ok' => false, 'error' => 'file_refused',
                'detail' => lang(array('string' => 'File {var:1} was not found, or you cannot open it.', 'vars' => $file_id))));
        }

        $total += (int) $reach['file']['size'];

        if ($total > WS_MAIL_SEND_FILES_BYTES) {
            return array_merge($out, array('ok' => false, 'error' => 'files_too_large'));
        }

        $found[] = $reach['file'];
    }

    if (!$read) {
        return $out;
    }

    foreach ($found as $file) {
        $path = pg_file_local_copy((int) $file['id']);
        $bytes = ($path !== '') ? @file_get_contents($path) : false;

        if (!is_string($bytes)) {
            return array_merge($out, array('ok' => false, 'error' => 'file_refused',
                'detail' => lang(array('string' => 'File {var:1} was not found, or you cannot open it.', 'vars' => (int) $file['id']))));
        }

        $out['files'][] = array('name' => (string) $file['name'], 'bytes' => $bytes);
    }

    return $out;
}

/**
 * The text an API mail is kept and quoted with: the text sent, else the
 * words of its HTML once cleaned (pg_mail_html_clean()), so search, the
 * card and a later quote read it. Pure.
 *
 * @param string $text
 * @param string $html
 * @return string
 */
function ws_mail_text_or_html($text, $html)
{
    $text = trim(str_replace(array("\r\n", "\r"), "\n", is_scalar($text) ? (string) $text : ''));
    $html = is_string($html) ? trim($html) : '';

    if (($text !== '') || ($html === '') || !function_exists('pg_mail_html_clean')) {
        return $text;
    }

    return ws_mail_html_text(pg_mail_html_clean($html));
}

/**
 * The file ids a form posted as a comma list: positive, each once, in order.
 * Pure.
 *
 * @param mixed $value
 * @return int[]
 */
function ws_mail_compose_file_ids($value)
{
    $ids = array();

    foreach (explode(',', is_scalar($value) ? (string) $value : '') as $part) {
        $part = trim($part);

        if (ctype_digit($part) && ((int) $part > 0)) {
            $ids[(int) $part] = (int) $part;
        }
    }

    return array_values($ids);
}

/**
 * The form's request as ws_mail_send() takes it: the mailbox, the channel
 * it is kept in, To, Cc, Bcc, Subject, the text and the files read from the
 * form parts. Pure.
 *
 * @param array $request
 * @param array $files ws_mail_compose_files()['files']
 * @return array box_id, channel_id, to, cc, bcc, subject, text, files
 */
function ws_mail_compose_mail($request, $files)
{
    $text = function ($key) use ($request) {
        return (isset($request[$key]) && is_scalar($request[$key])) ? (string) $request[$key] : '';
    };

    return array(
        'box_id'     => (int) $text('box_id'),
        'channel_id' => (int) $text('channel_id'),
        'to'         => $text('to'),
        'cc'         => $text('cc'),
        'bcc'        => $text('bcc'),
        'subject'    => $text('subject'),
        'text'       => $text('text'),
        'files'      => array_values((array) $files),
        // Set when the person pressed Send anyway after the unknown-domain warning.
        'send_anyway' => !empty($request['send_anyway']) && !in_array($text('send_anyway'), array('0', 'false'), true),
    );
}

/**
 * A mail written in the Workspace's writing box (bold, italic, struck,
 * highlighted, code, links, lists, a quote, a table: the light markup of
 * includes/workspace/render.php) as the two parts it leaves with: the plain
 * text (ws_plain_text()) that search, the card and a quote read, and the HTML
 * the renderer draws, a chip picked with @ or # as its plain words. Mail
 * programs read no class rules, so the quote and a
 * table carry their look inline. The HTML is cleaned again by
 * ws_mail_send_input().
 *
 * @param array  $viewer
 * @param string $markup
 * @return array text, html
 */
function ws_mail_markup_parts($viewer, $markup)
{
    $markup = trim(str_replace(array("\r\n", "\r"), "\n", (string) $markup));

    if ($markup === '') {
        return array('text' => '', 'html' => '');
    }

    // A person or a record picked with @ or # is a chip nobody outside can
    // open: in the mail it is their plain words.
    $words = preg_replace_callback('/<[@#][a-z_]+:[0-9]{1,10}>/', function ($token) use ($viewer) {
        return ws_plain_text($viewer, $token[0]);
    }, $markup);

    $html = strtr(ws_render_body($words, array()), array(
        ' class="ws-quote"'             => ' style="margin: 0 0 0 .5em; padding-left: .75em; border-left: 3px solid #d0d7de; color: #57606a;"',
        ' class="table table-sm ws-table"' => ' border="1" cellpadding="6" style="border-collapse: collapse; border-color: #d0d7de;"',
    ));

    return array('text' => ws_plain_text($viewer, $markup), 'html' => $html);
}

/**
 * The form's mail with its text written in the writing box (the request's
 * format "markup"): the text becomes the plain part and the HTML is added.
 * Anything else is left as it came, plain text as before.
 *
 * @param array $viewer
 * @param array $mail    ws_mail_compose_mail()'s
 * @param array $request the form's request
 * @return array
 */
function ws_mail_markup_apply($viewer, $mail, $request)
{
    if (!isset($request['format']) || ($request['format'] !== 'markup')) {
        return $mail;
    }

    $parts = ws_mail_markup_parts($viewer, (string) ($mail['text'] ?? ''));

    return array_merge($mail, $parts);
}

/**
 * The words of the New e-mail form (assets/js/workspace_mail.js).
 *
 * @return array key => text
 */
function ws_mail_compose_js_strings()
{
    return array(
        'mail_new'            => lang('New e-mail'),
        'mail_new_from'       => lang('From'),
        'mail_new_channel'    => lang('Kept in'),
        'mail_new_channel_hint' => lang('The e-mail becomes a card in this channel; its answers come under it.'),
        'mail_new_in_mailbox' => lang('The mailbox itself'),
        'mail_new_to'         => lang('To'),
        'mail_new_cc'         => lang('Cc'),
        'mail_new_bcc'        => lang('Bcc'),
        'mail_new_cc_bcc'     => lang('Cc and Bcc'),
        'mail_new_addresses'  => lang('Separate addresses with commas.'),
        'mail_suggest_label'  => lang('Suggested addresses'),
        'mail_suggest_bounced' => lang('Bounced address'),
        'mail_new_subject'    => lang('Subject'),
        'mail_new_text'       => lang('Text'),
        'mail_new_signature'  => lang('Put under the text:'),
        'mail_new_files'      => lang('Files'),
        'mail_upload'         => lang('Upload from your computer'),
        'mail_new_files_hint' => lang(array('string' => 'At most {var:1} files, {var:2} MB together.', 'vars' => array(WS_MAIL_SEND_FILES, (int) (WS_MAIL_SEND_FILES_BYTES / 1048576)))),
        'mail_new_remove'     => lang('Remove'),
        'mail_new_send'       => lang('Send'),
        'mail_new_sent'       => lang('The e-mail is on its way.'),
        'mail_new_failed'     => lang('The e-mail was kept but could not be sent; it is under Not sent.'),
        'mail_new_none'       => lang('There is no mailbox you can send from. A mailbox needs a way to send answers and a channel you can write in.'),
        'mail_new_draft'      => lang('Your unsent e-mail was brought back.'),
        'mail_new_discard'    => lang('Discard'),
        'mail_new_open'       => lang('Open in channel'),
    );
}
