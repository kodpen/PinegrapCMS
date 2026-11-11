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
 * may be kept in, the box's own channel first. Pure apart from the boxes
 * given.
 *
 * @param array[] $entries ws_mail_send_boxes()
 * @return array[] id, name, address, signature, channels (id, name)
 */
function ws_mail_compose_boxes($entries)
{
    $out = array();

    foreach ((array) $entries as $entry) {
        $box = $entry['box'];
        $own = (int) $box['channel_id'];
        $channels = array();

        foreach ((array) $entry['channels'] as $channel) {
            $row = array('id' => (int) $channel['id'], 'name' => (string) $channel['name']);

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
    );
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
        'mail_new_to'         => lang('To'),
        'mail_new_cc'         => lang('Cc'),
        'mail_new_bcc'        => lang('Bcc'),
        'mail_new_cc_bcc'     => lang('Cc and Bcc'),
        'mail_new_addresses'  => lang('Separate addresses with commas.'),
        'mail_new_subject'    => lang('Subject'),
        'mail_new_text'       => lang('Text'),
        'mail_new_signature'  => lang('Put under the text:'),
        'mail_new_files'      => lang('Files'),
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
