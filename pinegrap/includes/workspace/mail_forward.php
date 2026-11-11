<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: Forward as e-mail. Channel messages chosen together, or one
 * mail of a mailbox, put into the New e-mail form: a subject, the original
 * quoted under the person's own words and its files carried while they fit
 * the limits of a mail. The form is sent through ws_mail_send() like any new
 * mail (mail_compose.php); the source is read again then, so the form can
 * carry no file the person may not read. A forwarded mail gets a line under
 * its conversation's card naming whom it went to and linking the new card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most characters of the quoted original: the rest of WS_MAIL_REPLY_MAX is left for the person's own words. */
define('WS_MAIL_FORWARD_QUOTE_MAX', 45000);

/**
 * Whether this person may forward as e-mail: a team member with a mailbox
 * to send from (ws_mail_send_boxes()).
 *
 * @param array $viewer
 * @return bool
 */
function ws_mail_forward_may($viewer)
{
    return !empty($viewer['member']) && function_exists('ws_mail_send_ready') && ws_mail_send_ready() && (bool) ws_mail_send_boxes($viewer);
}

/**
 * Ids as the browser sends them, a list or "1,2,3": positive, each once,
 * in their order, at most 100. Pure.
 *
 * @param mixed $value
 * @return int[]
 */
function ws_mail_forward_ids($value)
{
    $list = is_array($value) ? $value : (is_scalar($value) ? explode(',', (string) $value) : array());
    $out = array();

    foreach ($list as $one) {
        if (is_scalar($one) && ((int) $one > 0) && !isset($out[(int) $one]) && (count($out) < 100)) {
            $out[(int) $one] = (int) $one;
        }
    }

    return array_values($out);
}

/**
 * The subject of a forwarded mail: "Fwd: " in front once, the
 * conversation's [#T…] tag dropped, since the forward opens a conversation
 * of its own. Pure.
 *
 * @param string $subject
 * @return string
 */
function ws_mail_forward_subject($subject)
{
    $subject = trim(preg_replace('/\s+/u', ' ', (string) preg_replace('/\s*\[#T[0-9]{1,10}\]/', '', (string) $subject)));

    if (!preg_match('/\Afw(d)?\s*:/i', $subject)) {
        $subject = trim('Fwd: ' . $subject);
    }

    return mb_substr($subject, 0, 200);
}

/**
 * A text cut to at most $max characters, "[…]" on a line of its own where
 * it was cut. Pure.
 *
 * @param string $text
 * @param int    $max
 * @return string
 */
function ws_mail_forward_cut($text, $max)
{
    $text = (string) $text;

    return (mb_strlen($text) > $max) ? rtrim(mb_substr($text, 0, max(0, $max - 4))) . "\n[…]" : $text;
}

/**
 * A mail quoted: its From, date, subject, To and Cc (never its Bcc), then
 * its text. Pure but for the words.
 *
 * @param array $item ws_mail_items row
 * @return string
 */
function ws_mail_forward_quote_mail($item)
{
    $name = trim((string) $item['from_name']);
    $email = (string) $item['from_email'];
    $when = ((int) ($item['sent_at'] ?? 0) > 0) ? (int) $item['sent_at'] : (int) $item['created_at'];
    $lines = array(
        '---------- ' . lang('Forwarded message') . ' ----------',
        lang('From') . ': ' . (($name !== '') ? $name . ' <' . $email . '>' : $email),
        lang('Date') . ': ' . date('d.m.Y H:i', $when),
        lang('Subject') . ': ' . (string) $item['subject'],
    );

    foreach (array('to_list' => lang('To'), 'cc_list' => lang('Cc')) as $key => $label) {
        if (trim((string) ($item[$key] ?? '')) !== '') {
            $lines[] = $label . ': ' . (string) $item[$key];
        }
    }

    return implode("\n", $lines) . "\n\n" . trim(str_replace(array("\r\n", "\r"), "\n", (string) $item['body_text']));
}

/**
 * Channel messages quoted: a heading naming the channel, then each message
 * as its writer and time, its plain text and the name of its file. Pure but
 * for the words.
 *
 * @param string  $heading  the words naming the channel
 * @param array[] $messages name, time, text, file_name
 * @return string
 */
function ws_mail_forward_quote_messages($heading, $messages)
{
    $parts = array('---------- ' . $heading . ' ----------');

    foreach ((array) $messages as $message) {
        $block = (string) $message['name'] . ' · ' . date('d.m.Y H:i', (int) $message['time']);
        $text = trim(str_replace(array("\r\n", "\r"), "\n", (string) $message['text']));

        if ($text !== '') {
            $block .= "\n" . $text;
        }

        if ((string) $message['file_name'] !== '') {
            $block .= "\n" . lang(array('string' => 'File: {var:1}', 'vars' => (string) $message['file_name']));
        }

        $parts[] = $block;
    }

    return implode("\n\n", $parts);
}

/**
 * The files a forward carries, in their order, each while it still fits a
 * mail (WS_MAIL_SEND_FILES, WS_MAIL_SEND_FILES_BYTES); the others are named
 * and left out. Pure.
 *
 * @param array[] $files id, name, size
 * @return array taken (id, name, size), left (names)
 */
function ws_mail_forward_take($files)
{
    $taken = array();
    $left = array();
    $total = 0;

    foreach ((array) $files as $file) {
        $size = max(0, (int) $file['size']);

        if ((count($taken) < WS_MAIL_SEND_FILES) && (($total + $size) <= WS_MAIL_SEND_FILES_BYTES)) {
            $taken[] = array('id' => (int) $file['id'], 'name' => (string) $file['name'], 'size' => $size);
            $total += $size;
        } else {
            $left[] = (string) $file['name'];
        }
    }

    return array('taken' => $taken, 'left' => $left);
}

/**
 * The files of some messages with their size, in the messages' order; a
 * file whose row is gone is skipped.
 *
 * @param array[] $rows ws_messages rows
 * @return array[] id, name, size
 */
function ws_mail_forward_files_of($rows)
{
    $ids = array();

    foreach ((array) $rows as $row) {
        if ((int) $row['file_id'] > 0) {
            $ids[] = (int) $row['file_id'];
        }
    }

    if (!$ids) {
        return array();
    }

    $known = array();

    foreach ((array) db_items("SELECT id, name, size FROM files WHERE id IN (" . implode(',', $ids) . ")") as $file) {
        $known[(int) $file['id']] = $file;
    }

    $out = array();

    foreach ((array) $rows as $row) {
        $file = $known[(int) $row['file_id']] ?? null;

        if ($file) {
            $out[] = array(
                'id'   => (int) $file['id'],
                'name' => ((string) $row['file_name'] !== '') ? (string) $row['file_name'] : (string) $file['name'],
                'size' => (int) $file['size'],
            );
        }
    }

    return $out;
}

/**
 * What a forward starts from, read again when it is sent: channel messages
 * chosen together (ws_forward_pick(): one channel the person reads, up to
 * WS_FORWARD_MAX, written by people or applications), or one mail of a
 * channel the person reads, never a delivery failure. The mail is read
 * where the pane shows it (the conversation's channel) or where the
 * channel screen does (its own message's).
 *
 * @param array  $viewer
 * @param string $kind messages | mail
 * @param mixed  $ids  the messages' ids, or the mail's (ws_mail_items) id
 * @return array ok, error, kind, ids, source ("kind:ids"), subject, text,
 *               files (id, name, size), left (names), channel_id, card
 *               (the message a line goes under, for a mail)
 */
function ws_mail_forward_source($viewer, $kind, $ids)
{
    $out = array('ok' => false, 'error' => '', 'kind' => '', 'ids' => array(), 'source' => '', 'subject' => '', 'text' => '',
        'files' => array(), 'left' => array(), 'channel_id' => 0, 'card' => null);
    $ids = ws_mail_forward_ids($ids);

    if ($kind === 'messages') {
        $picked = ws_forward_pick($viewer, $ids);

        if (!$picked['ok']) {
            return array_merge($out, array('error' => $picked['error']));
        }

        $people = ws_people(array_map(function ($row) {
            return ($row['sender_kind'] === 'user') ? (int) $row['sender_id'] : 0;
        }, $picked['rows']));
        $messages = array();

        foreach ($picked['rows'] as $row) {
            $sender = ws_forward_sender($row['sender_kind'], $row['sender_id'], $people);
            $messages[] = array(
                'name'      => $sender ? (string) $sender['name'] : lang('Someone'),
                'time'      => (int) $row['created_at'],
                'text'      => ws_plain_text($viewer, (string) $row['body']),
                'file_name' => (string) $row['file_name'],
            );
        }

        $name = (string) $picked['channel']['name'];
        $heading = (count($messages) === 1)
            ? lang(array('string' => 'A message from #{var:1}', 'vars' => $name))
            : lang(array('string' => 'Messages from #{var:1}', 'vars' => $name));
        $files = ws_mail_forward_take(ws_mail_forward_files_of($picked['rows']));
        $ids = array_map(function ($row) {
            return (int) $row['id'];
        }, $picked['rows']);

        return array_merge($out, array('ok' => true, 'kind' => 'messages', 'ids' => $ids, 'source' => 'messages:' . implode(',', $ids),
            'subject' => mb_substr($heading, 0, 200), 'text' => ws_mail_forward_cut(ws_mail_forward_quote_messages($heading, $messages), WS_MAIL_FORWARD_QUOTE_MAX),
            'files' => $files['taken'], 'left' => $files['left'], 'channel_id' => (int) $picked['channel']['id']));
    }

    $refused = array_merge($out, array('error' => lang('This e-mail cannot be forwarded.')));
    $item = (($kind === 'mail') && $ids) ? db_item("SELECT * FROM ws_mail_items WHERE id = '" . (int) $ids[0] . "'") : null;
    $message = is_array($item) ? ws_message((int) $item['message_id']) : null;

    if (!$message || ((int) $message['deleted_at'] > 0) || ws_mail_item_is_bounce($item)) {
        return $refused;
    }

    $conversation = ((int) $item['conversation_id'] > 0)
        ? db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $item['conversation_id'] . "'") : null;
    $readable = false;

    foreach (array((int) $message['channel_id'], is_array($conversation) ? (int) $conversation['channel_id'] : 0) as $channel_id) {
        $channel = ($channel_id > 0) ? ws_channel($channel_id) : null;
        $readable = $readable || ($channel && ws_can_read_channel($viewer, $channel));
    }

    if (!$readable) {
        return $refused;
    }

    // The line goes under the conversation's card, else under the mail.
    $card = (is_array($conversation) && ((int) $conversation['message_id'] > 0)) ? ws_message((int) $conversation['message_id']) : null;
    $card = ($card && ((int) $card['deleted_at'] === 0)) ? $card : $message;
    $files = ws_mail_forward_take(ws_mail_forward_files_of(db_items("SELECT * FROM ws_messages
        WHERE sender_kind = 'mail' AND sender_id = '" . (int) $item['id'] . "' AND file_id > 0 AND deleted_at = 0 ORDER BY id LIMIT 100")));
    $subject = ((string) $item['subject'] !== '') ? (string) $item['subject'] : (is_array($conversation) ? (string) $conversation['subject'] : '');

    return array_merge($out, array('ok' => true, 'kind' => 'mail', 'ids' => array((int) $item['id']), 'source' => 'mail:' . (int) $item['id'],
        'subject' => ws_mail_forward_subject($subject), 'text' => ws_mail_forward_cut(ws_mail_forward_quote_mail($item), WS_MAIL_FORWARD_QUOTE_MAX),
        'files' => $files['taken'], 'left' => $files['left'], 'channel_id' => (int) $message['channel_id'], 'card' => $card));
}

/**
 * What the form is filled with, for the browser: the person writes above
 * the quote. Pure.
 *
 * @param array $source ws_mail_forward_source()
 * @return array kind, ids, source, subject, text, files, left, channel_id
 */
function ws_mail_forward_fill($source)
{
    return array(
        'kind'       => (string) $source['kind'],
        'ids'        => array_values((array) $source['ids']),
        'source'     => (string) $source['source'],
        'subject'    => (string) $source['subject'],
        'text'       => "\n\n" . (string) $source['text'],
        'files'      => array_values((array) $source['files']),
        'left'       => array_values((array) $source['left']),
        'channel_id' => (int) $source['channel_id'],
    );
}

/**
 * The forward a New e-mail request carries (forward_kind, forward_ids,
 * forward_files): its source read again and the files it still names read
 * for the mail. An id the source does not carry names nothing. A request
 * without a forward carries nothing.
 *
 * @param array $viewer
 * @param array $request
 * @return array ok, error, source (ws_mail_forward_source() or null), files (name, bytes)
 */
function ws_mail_forward_request($viewer, $request)
{
    $kind = (string) (is_scalar($request['forward_kind'] ?? '') ? ($request['forward_kind'] ?? '') : '');

    if ($kind === '') {
        return array('ok' => true, 'error' => '', 'source' => null, 'files' => array());
    }

    $source = ws_mail_forward_source($viewer, $kind, $request['forward_ids'] ?? '');

    if (!$source['ok']) {
        return array('ok' => false, 'error' => $source['error'], 'source' => null, 'files' => array());
    }

    $kept = ws_mail_forward_ids($request['forward_files'] ?? '');
    $files = array();

    foreach ($source['files'] as $file) {
        if (!in_array((int) $file['id'], $kept, true)) {
            continue;
        }

        $path = pg_file_local_copy((int) $file['id']);
        $bytes = ($path !== '') ? @file_get_contents($path) : false;

        if ($bytes === false) {
            return array('ok' => false, 'error' => lang(array('string' => 'The file {var:1} could not be read.', 'vars' => $file['name'])), 'source' => null, 'files' => array());
        }

        $files[] = array('name' => (string) $file['name'], 'bytes' => (string) $bytes);
    }

    return array('ok' => true, 'error' => '', 'source' => $source, 'files' => $files);
}

/**
 * The line under a forwarded mail: a message of the new mail under the
 * source's card, drawn as whom it went to with a link to the new card
 * (ws_mail_forward_line_html()). Not written into an archived channel or
 * one the person does not read.
 *
 * @param array      $viewer
 * @param array|null $source  ws_mail_forward_source()
 * @param int        $item_id the new mail
 * @return int the line's message id, 0 for none
 */
function ws_mail_forward_line($viewer, $source, $item_id)
{
    if (!is_array($source) || ($source['kind'] !== 'mail') || !is_array($source['card']) || ((int) $item_id <= 0)) {
        return 0;
    }

    $card = $source['card'];
    $channel = ws_channel((int) $card['channel_id']);

    if (!$channel || ((int) $channel['archived_at'] > 0) || !ws_can_read_channel($viewer, $channel)) {
        return 0;
    }

    $parent = ((int) $card['parent_id'] > 0) ? (int) $card['parent_id'] : (int) $card['id'];
    $line = ws_mail_message_write((int) $channel['id'], $parent, (int) $item_id, '', 0, '', time());

    if (($line > 0) && function_exists('ws_message_touch')) {
        ws_message_touch($parent);
    }

    return $line;
}

/**
 * Whether a mail's message is a line a forward left under its source: a
 * message of an outgoing mail that is neither its text nor a file. Pure.
 *
 * @param array      $row  ws_messages row
 * @param array|null $item ws_mail_items row
 * @return bool
 */
function ws_mail_forward_is_line($row, $item)
{
    return is_array($item) && ((string) ($item['direction'] ?? '') === 'out') && ((int) $row['file_id'] === 0)
        && ((int) ($item['message_id'] ?? 0) > 0) && ((int) $item['message_id'] !== (int) $row['id']);
}

/**
 * The line: the new mail's state, whom it went to, and its card. Pure but
 * for the words.
 *
 * @param array $item ws_mail_items_map() entry
 * @return string HTML
 */
function ws_mail_forward_line_html($item)
{
    $conversation = $item['conversation'] ?? null;
    $url = 'workspace.php?channel=' . (is_array($conversation) ? (int) $conversation['channel_id'] : 0) . '&message=' . (int) $item['message_id'];

    return '<div class="ws-mail-card ws-mail-forward-line"><div class="small fg-secondary">' . ws_mail_send_badge_html($item)
        . ' <span class="ms-1">' . h(lang(array('string' => 'Forwarded this e-mail to {var:1}', 'vars' => (string) $item['to_list']))) . '</span></div>'
        . '<a class="small" href="' . h($url) . '"><i class="bi bi-envelope-arrow-up me-1" aria-hidden="true"></i>' . h(lang('Open the e-mail')) . '</a></div>';
}

/**
 * The mail a message offers to forward: the mail's own text, of a mail
 * that is no delivery failure, for a team member. Pure.
 *
 * @param array      $row    ws_messages row
 * @param array|null $item   ws_mail_items_map() entry
 * @param array      $viewer
 * @return int the mail's id, 0 for none
 */
function ws_mail_forward_offer($row, $item, $viewer)
{
    if (empty($viewer['member']) || !is_array($item) || ((int) $row['file_id'] > 0)
        || ((int) ($item['message_id'] ?? 0) !== (int) $row['id']) || ws_mail_item_is_bounce($item)) {
        return 0;
    }

    return (int) $item['id'];
}

/**
 * The reading pane's mails, each marked whether it may be forwarded. Pure.
 *
 * @param array $pane ws_mail_pane()
 * @param bool  $may  ws_mail_forward_may()
 * @return array the pane
 */
function ws_mail_forward_pane($pane, $may)
{
    foreach ($pane['items'] as $index => $item) {
        $pane['items'][$index]['forward'] = $may && ((int) $item['message_id'] > 0) && !ws_mail_item_is_bounce($item);
    }

    return $pane;
}

/**
 * The words of Forward as e-mail (assets/js/workspace.js, workspace_mail.js).
 *
 * @return array key => text
 */
function ws_mail_forward_js_strings()
{
    return array(
        'mail_fwd'      => lang('Forward as e-mail'),
        'mail_fwd_left' => ws_js_template('Left out, over the limits of an e-mail: {var:1}', 1),
    );
}
