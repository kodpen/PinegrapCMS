<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: the reading pane of the E-mails screen. A conversation's mails
 * in the order they came and went, each with who wrote it, to and Cc, its
 * text, its files and, under an answer, whether it was not sent, waits to
 * be sent or came back undelivered. Whoever may read the conversation's
 * channel may read the pane (ws_can_read_channel()). The server draws it for
 * the screen's address (?conversation=), the screen's script in place, and
 * the script adds the answer and the status controls for whoever may write.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The newest mails of a conversation the pane shows. */
define('WS_MAIL_PANE_MAX', 200);

/**
 * A conversation for the pane, when the person may read its channel.
 *
 * @param array $viewer
 * @param int   $conversation_id
 * @return array|null conversation, channel, box_name, can_post, items (each
 *                    with files and bounces), last_id
 */
function ws_mail_pane($viewer, $conversation_id)
{
    $conversation = ((int) $conversation_id > 0)
        ? db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $conversation_id . "' AND message_id > 0") : null;
    $channel = $conversation ? ws_channel((int) $conversation['channel_id']) : null;

    if (!$channel || !ws_can_read_channel($viewer, $channel)) {
        return null;
    }

    $items = array_reverse((array) db_items("SELECT * FROM ws_mail_items WHERE conversation_id = '" . (int) $conversation['id'] . "'
        ORDER BY id DESC LIMIT " . WS_MAIL_PANE_MAX));
    $ids = array();
    $sent = array();

    foreach ($items as $index => $item) {
        $ids[] = (int) $item['id'];
        $items[$index]['files'] = array();
        $items[$index]['bounces'] = array();

        if (((string) $item['direction'] === 'out') && ((string) $item['rfc_message_id'] !== '')) {
            $sent[(string) $item['rfc_message_id']] = $index;
        }
    }

    $at = array_flip($ids);

    // MySQL 8 reserves STORED: the stored name is aliased stored_name.
    if ($ids) {
        foreach ((array) db_items("SELECT m.sender_id, m.file_name, f.name AS stored_name FROM ws_messages m INNER JOIN files f ON f.id = m.file_id
            WHERE m.sender_kind = 'mail' AND m.sender_id IN (" . implode(',', $ids) . ") AND m.file_id > 0 AND m.deleted_at = 0 ORDER BY m.id") as $file) {
            $items[$at[(int) $file['sender_id']]]['files'][] = array('name' => (string) $file['file_name'], 'url' => PATH . encode_url_path((string) $file['stored_name']));
        }
    }

    // A delivery failure stays outside the conversation, tied to the answer
    // it returns by that answer's Message-ID (ws_mail_bounce_store()).
    if ($sent) {
        $quoted = implode(',', array_map(function ($id) {
            return "'" . e((string) $id) . "'";
        }, array_keys($sent)));

        foreach ((array) db_items("SELECT * FROM ws_mail_items WHERE box_id = '" . (int) $conversation['box_id'] . "' AND conversation_id = 0
            AND direction = 'in' AND user_id > 0 AND in_reply_to IN (" . $quoted . ") ORDER BY id LIMIT 50") as $bounce) {
            $items[$sent[(string) $bounce['in_reply_to']]]['bounces'][] = $bounce;
        }
    }

    return array(
        'conversation' => $conversation,
        'channel'      => $channel,
        'box_name'     => (string) db_value("SELECT name FROM ws_mail_boxes WHERE id = '" . (int) $conversation['box_id'] . "'"),
        'can_post'     => ws_can_post_channel($viewer, $channel),
        'items'        => $items,
        'last_id'      => $ids ? max($ids) : 0,
    );
}

/**
 * A conversation opened in the pane: read from then on for the person, its
 * mails' Forward offered (mail_forward.php). The screen's address and its
 * script both open it so.
 *
 * @param array $viewer
 * @param int   $conversation_id
 * @return array|null ws_mail_pane()
 */
function ws_mail_pane_open($viewer, $conversation_id)
{
    $pane = ws_mail_pane($viewer, $conversation_id);

    if (!$pane) {
        return null;
    }

    ws_mail_read_mark((int) $viewer['id'], (int) $pane['conversation']['id'], $pane['last_id']);

    return ws_mail_forward_pane($pane, ws_mail_forward_may($viewer));
}

/**
 * The pane the server draws for the screen's address (?conversation=), so
 * a row opens its mail with or without the script; Close leads back to the
 * list. The script reads the pane again and adds the answer and the status
 * controls (workspace_mail.js).
 *
 * @param array|null $pane  ws_mail_pane_open(), null when it cannot be opened
 * @param string     $base  the panel's address, ending in /
 * @param string     $close the list's address without the conversation
 * @return string HTML
 */
function ws_mail_pane_page_html($pane, $base, $close)
{
    $shut = '<a class="btn btn-sm btn-text theme-secondary" href="' . h($close) . '" data-ws-mail-close>'
        . '<i class="bi bi-x-lg me-1" aria-hidden="true"></i>' . h(lang('Close')) . '</a>';

    if (!$pane) {
        return '<div class="ws-mail-pane-tools">' . $shut . '</div><p class="ws-empty">' . h(lang('This conversation cannot be opened.')) . '</p>';
    }

    return ws_mail_pane_html($pane, $base, $shut);
}

/**
 * The pane: the conversation's heading and its mails. Pure but for the
 * words and the time labels.
 *
 * @param array  $pane  ws_mail_pane()
 * @param string $base  the panel's address, ending in /
 * @param string $tools more of the heading's tools, HTML
 * @return string HTML
 */
function ws_mail_pane_html($pane, $base, $tools = '')
{
    $conversation = $pane['conversation'];
    $subject = (trim((string) $conversation['subject']) !== '') ? (string) $conversation['subject'] : lang('(no subject)');
    $customer = trim((string) $conversation['customer_name'] . ' <' . (string) $conversation['customer_email'] . '>');
    $url = $base . 'workspace.php?channel=' . (int) $conversation['channel_id'] . '&message=' . (int) $conversation['message_id'];

    $html = '<header class="ws-mail-pane-head"><h2 class="h5 mb-1" tabindex="-1">' . h($subject) . '</h2>'
        . '<div class="small fg-secondary">' . h($customer)
        . (($pane['box_name'] !== '') ? ' · ' . h($pane['box_name']) : '') . ' · ' . h('#' . (string) $pane['channel']['name']) . '</div>'
        . '<div class="ws-mail-pane-tools"><a class="btn btn-sm btn-text theme-secondary" href="' . h($url) . '"><i class="bi bi-hash me-1" aria-hidden="true"></i>' . h(lang('Open in channel')) . '</a>' . $tools . '</div>'
        . '</header>';

    if (count($pane['items']) >= WS_MAIL_PANE_MAX) {
        $html .= '<p class="small fg-secondary">' . h(lang(array('string' => 'The newest {var:1} e-mails are shown; the channel has them all.', 'vars' => WS_MAIL_PANE_MAX))) . '</p>';
    }

    foreach ($pane['items'] as $item) {
        $html .= ws_mail_pane_item_html($item);
    }

    return $html;
}

/**
 * One mail of the pane.
 *
 * @param array $item ws_mail_items row with files and bounces
 * @return string HTML
 */
function ws_mail_pane_item_html($item)
{
    $out = ((string) $item['direction'] === 'out');
    $name = (trim((string) $item['from_name']) !== '') ? (string) $item['from_name'] : (string) $item['from_email'];
    $time = ((int) $item['sent_at'] > 0) ? (int) $item['sent_at'] : (int) $item['created_at'];
    $lines = array('To' => lang('To'), 'Cc' => lang('Cc'));
    $lists = array('To' => (string) $item['to_list'], 'Cc' => (string) $item['cc_list']);

    $html = '<article class="ws-mail-item ' . ($out ? 'ws-mail-out' : 'ws-mail-in') . '">'
        . '<div class="ws-mail-item-head"><span class="fw-semibold">' . h($name) . '</span>'
        . (($name !== (string) $item['from_email']) ? ' <span class="small fg-secondary">' . h('<' . (string) $item['from_email'] . '>') . '</span>' : '')
        . '<span class="ws-mail-time small fg-secondary">' . h(ws_time_label($time)) . '</span></div>';

    foreach ($lists as $key => $list) {
        if (trim($list) !== '') {
            $html .= '<div class="small fg-secondary">' . h($lines[$key]) . ': ' . h($list) . '</div>';
        }
    }

    // A mail that left needs no word here; one that waits or failed gets
    // the card's badge (includes/workspace/mail_send.php).
    if (in_array(ws_mail_send_state($item), array('queued', 'failed'), true)) {
        $html .= '<div class="mt-1 small">' . ws_mail_send_badge_html($item) . '</div>';
    }

    if (ws_mail_item_is_auto_reply($item)) {
        $html .= '<div class="mt-1"><span class="badge theme-secondary">' . h(lang('Automatic answer')) . '</span></div>';
    }

    $html .= '<div class="ws-mail-text">' . nl2br(h(mb_substr((string) $item['body_text'], 0, 100000)), false) . '</div>';

    foreach ($item['files'] as $file) {
        $html .= '<div class="small"><i class="bi bi-paperclip me-1" aria-hidden="true"></i><a href="' . h($file['url']) . '" target="_blank" rel="noopener">' . h($file['name']) . '</a></div>';
    }

    $refused = array_filter(explode("\n", (string) $item['refused_files']), 'strlen');

    if ($refused) {
        $html .= '<div class="small fg-secondary">' . h(lang(array('string' => 'Not taken in: {var:1}', 'vars' => implode(', ', $refused)))) . '</div>';
    }

    foreach ($item['bounces'] as $bounce) {
        $html .= '<div class="ws-mail-bounce small mt-1"><span class="badge theme-danger">' . h(lang('Not delivered')) . '</span> '
            . h(lang(array('string' => 'Not delivered to {var:1}.', 'vars' => (string) $bounce['to_list'])))
            . ((trim((string) $bounce['body_text']) !== '') ? ' ' . h((string) $bounce['body_text']) : '') . '</div>';
    }

    // Forward as e-mail (mail_forward.php, ws_mail_forward_pane()).
    if (!empty($item['forward'])) {
        $html .= '<div class="ws-mail-item-tools mt-2"><button type="button" class="btn btn-sm btn-text theme-secondary px-0" data-ws-mail-forward="' . (int) $item['id'] . '">'
            . '<i class="bi bi-envelope-arrow-up me-1" aria-hidden="true"></i>' . h(lang('Forward')) . '</button></div>';
    }

    return $html . '</article>';
}

/**
 * What the screen's script needs to answer and change a conversation from
 * the pane: the people of its channel for Held by, and its status.
 *
 * @param array $pane   ws_mail_pane()
 * @param array $viewer
 * @return array reply (the conversation id, 0 when the person may not
 *               write), to, channel_id, state, members, others (how many
 *               Reply to all adds, mail_reply.php)
 */
function ws_mail_pane_tools($pane, $viewer)
{
    $conversation = $pane['conversation'];
    $members = array();
    $others = 0;

    if ($pane['can_post']) {
        $ids = (array) db_values("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . (int) $pane['channel']['id'] . "' ORDER BY user_id LIMIT 100");

        foreach (ws_people($ids) as $person) {
            $members[] = array('id' => (int) $person['id'], 'name' => (string) $person['name']);
        }

        $box = ws_mail_box((int) $conversation['box_id']);

        if ($box) {
            $others = count(ws_mail_answer_others(ws_mail_reply_answered((int) $conversation['id']), (string) $conversation['customer_email'],
                (string) $box['address'], ws_mail_answer_aliases($box)));
        }
    }

    return array(
        'reply'      => $pane['can_post'] ? (int) $conversation['id'] : 0,
        'to'         => (string) $conversation['customer_email'],
        'channel_id' => (int) $pane['channel']['id'],
        'state'      => $pane['can_post'] ? ws_mail_state_offer(array('conversation' => $conversation), (int) $conversation['id'], (int) $viewer['id']) : null,
        'members'    => $members,
        'others'     => $others,
    );
}

/**
 * The texts of the screen's script (assets/js/workspace_mail.js).
 *
 * @return array key => text
 */
function ws_mail_screen_js_strings()
{
    return array(
        'close'      => lang('Close'),
        'loading'    => lang('Loading…'),
        'read_all'   => lang('All e-mails are marked as read.'),
        'not_found'  => lang('This conversation cannot be opened.'),
    );
}
