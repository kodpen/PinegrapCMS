<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: the New e-mail form. What it offers a person (the mailboxes
 * they may send from, the channels a mail may be kept in, the limits), the
 * files of its request, which field a refusal points at, whether a
 * channel's menu offers it, and a mailbox's signature, which stands under
 * the writer's name in every mail the box sends. The send itself is
 * ws_mail_send() (mail_send.php); the form is drawn by
 * assets/js/workspace_mail.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most characters of a mailbox's signature. */
define('WS_MAIL_SIGNATURE_MAX', 1000);

/** The most lines of a mailbox's signature. */
define('WS_MAIL_SIGNATURE_LINES', 10);

/**
 * A signature as it is kept: plain text, one kind of line end, no control
 * characters but tabs, no run of blank lines, at most WS_MAIL_SIGNATURE_LINES
 * lines and WS_MAIL_SIGNATURE_MAX characters. Pure.
 *
 * @param mixed $text
 * @return string
 */
function ws_mail_signature_clean($text)
{
    if (!is_scalar($text)) {
        return '';
    }

    $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
    $text = (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text);
    $text = (string) preg_replace('/\n{3,}/', "\n\n", trim($text));
    $lines = array_slice(array_map('rtrim', explode("\n", $text)), 0, WS_MAIL_SIGNATURE_LINES);

    return trim(mb_substr(implode("\n", $lines), 0, WS_MAIL_SIGNATURE_MAX));
}

/**
 * A mailbox's signature ('' for none): kept in its settings, written on the
 * mailbox's form.
 *
 * @param array|null $box ws_mail_boxes row
 * @return string
 */
function ws_mail_box_signature($box)
{
    if (!is_array($box)) {
        return '';
    }

    $settings = ws_mail_box_settings($box);

    return ws_mail_signature_clean($settings['signature'] ?? '');
}

/**
 * What stands under the writer's name in a mail the box sends: its
 * signature, or its name without one.
 *
 * @param array $box ws_mail_boxes row
 * @return string
 */
function ws_mail_signature_line($box)
{
    $signature = ws_mail_box_signature($box);

    return ($signature !== '') ? $signature : (string) ($box['name'] ?? '');
}

/**
 * The limits the form checks before it sends; the server checks them again.
 * The files' bytes are the mail's own limit, or the request's where the
 * server takes less in one request.
 *
 * @return array addresses, files, bytes (all files), file (one file), text
 */
function ws_mail_compose_limits()
{
    $bytes = WS_MAIL_SEND_FILES_BYTES;
    $limits = function_exists('pg_upload_limits') ? pg_upload_limits() : array();
    $request = (int) ($limits['request_max'] ?? 0);

    if (($request > 0) && ($request < $bytes)) {
        $bytes = $request;
    }

    $file = function_exists('ws_upload_form_max_bytes') ? (int) ws_upload_form_max_bytes() : $bytes;

    return array(
        'addresses' => WS_MAIL_SEND_ADDRESSES,
        'files'     => WS_MAIL_SEND_FILES,
        'bytes'     => $bytes,
        'file'      => (($file > 0) && ($file < $bytes)) ? $file : $bytes,
        'text'      => WS_MAIL_REPLY_MAX,
    );
}

/**
 * What the form offers, from ws_mail_send_boxes(): each mailbox with its
 * signature and the channels its mail may be kept in, and the mailbox and
 * channel it opens with: the one asked for (a channel's menu), else the
 * first. Pure.
 *
 * @param array[] $entries    ws_mail_send_boxes()
 * @param int     $channel_id the channel the form was opened from, 0 for none
 * @param string  $writer     the person's name, under the text
 * @return array boxes, box_id, channel_id, writer, limits
 */
function ws_mail_compose_offer_shape($entries, $channel_id, $writer)
{
    $boxes = array();
    $box_id = 0;
    $chosen = 0;

    foreach ((array) $entries as $entry) {
        $channels = array();

        foreach ((array) ($entry['channels'] ?? array()) as $channel) {
            $channels[] = array('id' => (int) $channel['id'], 'name' => (string) $channel['name']);

            if (($chosen === 0) && ((int) $channel_id > 0) && ((int) $channel['id'] === (int) $channel_id)) {
                $box_id = (int) $entry['box']['id'];
                $chosen = (int) $channel['id'];
            }
        }

        if (!$channels) {
            continue;
        }

        $boxes[] = array(
            'id'        => (int) $entry['box']['id'],
            'name'      => (string) $entry['box']['name'],
            'address'   => (string) $entry['box']['address'],
            'signature' => ws_mail_box_signature($entry['box']),
            'channels'  => $channels,
        );
    }

    if (($chosen === 0) && $boxes) {
        $box_id = $boxes[0]['id'];
        $chosen = $boxes[0]['channels'][0]['id'];
    }

    return array('boxes' => $boxes, 'box_id' => $box_id, 'channel_id' => $chosen, 'writer' => (string) $writer, 'limits' => ws_mail_compose_limits());
}

/**
 * What the form offers this person.
 *
 * @param array $viewer
 * @param int   $channel_id the channel the form was opened from, 0 for none
 * @return array ws_mail_compose_offer_shape()
 */
function ws_mail_compose_offer($viewer, $channel_id = 0)
{
    return ws_mail_compose_offer_shape(ws_mail_send_boxes($viewer), (int) $channel_id, ws_person_name((int) $viewer['id']));
}

/**
 * Whether this person may send from any mailbox: the screen's New e-mail.
 *
 * @param array $viewer
 * @return bool
 */
function ws_mail_can_send($viewer)
{
    return function_exists('ws_mail_send_ready') && ws_mail_send_ready() && (bool) ws_mail_send_boxes($viewer);
}

/**
 * Whether a channel's menu offers New e-mail: the person may post in it and
 * an open mailbox with a working way to send writes into it (its own
 * channel, or one its routes write to). Team accounts only.
 *
 * @param array      $viewer
 * @param array|null $channel ws_channels row
 * @return bool
 */
function ws_mail_channel_sends($viewer, $channel)
{
    if (!is_array($channel) || empty($viewer['member']) || !function_exists('ws_mail_send_ready') || !ws_mail_send_ready()
        || !ws_can_post_channel($viewer, $channel)) {
        return false;
    }

    $site_domain = null;

    foreach ((array) db_items("SELECT * FROM ws_mail_boxes WHERE enabled = 1 ORDER BY name, id") as $box) {
        $channel_ids = array((int) $box['channel_id']);

        foreach (ws_mail_routes($box) as $route) {
            $channel_ids[] = (int) $route['channel_id'];
        }

        if (!in_array((int) $channel['id'], $channel_ids, true)) {
            continue;
        }

        if ($site_domain === null) {
            $site_domain = ws_mail_reply_site_domain();
        }

        if (ws_mail_reply_route($box, $site_domain)['ok']) {
            return true;
        }
    }

    return false;
}

/**
 * The parts of a files[] form field, as PHP lays them out (one list per
 * key), turned into one row a file; an empty file field is no file. Pure.
 *
 * @param mixed $files $_FILES['files']
 * @return array[] name, tmp_name, error, size
 */
function ws_mail_compose_file_parts($files)
{
    if (!is_array($files) || !isset($files['name'])) {
        return array();
    }

    $many = is_array($files['name']);
    $names = $many ? $files['name'] : array($files['name']);
    $out = array();

    foreach ($names as $index => $name) {
        $value = function ($key) use ($files, $many, $index) {
            $field = $files[$key] ?? null;
            return $many ? (is_array($field) ? ($field[$index] ?? null) : null) : $field;
        };
        $error = $value('error');
        $error = is_scalar($error) ? (int) $error : UPLOAD_ERR_NO_FILE;
        $tmp = $value('tmp_name');

        if (!is_scalar($name) || ($error === UPLOAD_ERR_NO_FILE)) {
            continue;
        }

        $out[] = array(
            'name'     => (string) $name,
            'tmp_name' => is_scalar($tmp) ? (string) $tmp : '',
            'error'    => $error,
            'size'     => is_scalar($value('size')) ? (int) $value('size') : 0,
        );
    }

    return $out;
}

/**
 * The files the form sent with the mail (form parts files[]), read for
 * ws_mail_send(): a part PHP did not take is refused by its name.
 *
 * @return array ok, error, detail, files (each name and bytes)
 */
function ws_mail_compose_request_files()
{
    $parts = ws_mail_compose_file_parts($_FILES['files'] ?? null);

    if (count($parts) > WS_MAIL_SEND_FILES) {
        return array('ok' => false, 'error' => 'too_many_files', 'detail' => '', 'files' => array());
    }

    $files = array();

    foreach ($parts as $part) {
        $uploaded = ($part['error'] === UPLOAD_ERR_OK) && ($part['tmp_name'] !== '') && is_uploaded_file($part['tmp_name']);
        $refusal = ws_upload_part_error($part['error'], $uploaded, $uploaded ? (int) @filesize($part['tmp_name']) : 0, ws_upload_form_max_bytes());
        $bytes = ($refusal === '') ? @file_get_contents($part['tmp_name']) : false;

        if (($refusal === '') && ($bytes === false)) {
            $refusal = lang('The file could not be read.');
        }

        if ($refusal !== '') {
            return array('ok' => false, 'error' => 'file_refused', 'detail' => $part['name'] . ': ' . $refusal, 'files' => array());
        }

        $files[] = array('name' => $part['name'], 'bytes' => (string) $bytes);
    }

    return array('ok' => true, 'error' => '', 'detail' => '', 'files' => $files);
}

/**
 * The form's field a refusal of ws_mail_send() is about, '' for none. Pure.
 *
 * @param string $error
 * @return string box | channel | to | subject | text | files | ''
 */
function ws_mail_compose_error_field($error)
{
    $fields = array(
        'no_box'          => 'box',
        'no_access'       => 'channel',
        'no_to'           => 'to',
        'too_many'        => 'to',
        'bad_address'     => 'to',
        'blocked'         => 'to',
        'no_subject'      => 'subject',
        'empty'           => 'text',
        'too_long'        => 'text',
        'too_many_files'  => 'files',
        'files_too_large' => 'files',
        'file_refused'    => 'files',
    );

    return $fields[(string) $error] ?? '';
}

/**
 * The texts of the New e-mail form (assets/js/workspace_mail.js).
 *
 * @return array key => text
 */
function ws_mail_compose_js_strings()
{
    return array(
        'mail_new'               => lang('New e-mail'),
        'mail_new_from'          => lang('From'),
        'mail_new_channel'       => lang('Kept in'),
        'mail_new_channel_hint'  => lang('The e-mail becomes a card in this channel: its people see it and its answers.'),
        'mail_new_to'            => lang('To'),
        'mail_new_cc'            => lang('Cc'),
        'mail_new_bcc'           => lang('Bcc'),
        'mail_new_to_hint'       => ws_js_template('Separate addresses with commas. To, Cc and Bcc together take at most {var:1} addresses.', 1),
        'mail_new_subject'       => lang('Subject'),
        'mail_new_text'          => lang('Text'),
        'mail_new_signature'     => lang('Under the text'),
        'mail_new_files'         => lang('Files'),
        'mail_new_files_hint'    => ws_js_template('At most {var:1} files, {var:2} MB together.', 2),
        'mail_new_files_many'    => ws_js_template('An e-mail carries at most {var:1} files.', 1),
        'mail_new_files_large'   => ws_js_template('The files of an e-mail are at most {var:1} MB together.', 1),
        'mail_new_file_large'    => ws_js_template('{var:1} is larger than {var:2} MB.', 2),
        'mail_new_file_remove'   => ws_js_template('Remove {var:1}', 1),
        'mail_new_send'          => lang('Send e-mail'),
        'mail_new_discard'       => lang('Discard'),
        'mail_new_discard_ask'   => lang('Discard this e-mail? What you wrote here is not kept.'),
        'mail_new_restored'      => lang('The e-mail you had not sent was brought back from this browser. Files are not kept; add them again.'),
        'mail_new_queued'        => lang('The e-mail is on its way.'),
        'mail_new_sent'          => lang('The e-mail was sent.'),
        'mail_new_failed'        => lang('The e-mail could not be sent. It is under Not sent, and its card offers Send again.'),
        'mail_new_none'          => lang('There is no mailbox you can send from: a mailbox needs a working way to send, and you need to be able to write in its channel.'),
        'mail_new_refused_files' => ws_js_template('These files were not added to the e-mail: {var:1}', 1),
    );
}
