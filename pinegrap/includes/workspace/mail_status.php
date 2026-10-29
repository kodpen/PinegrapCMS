<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: the state of an e-mail conversation and the mailbox's
 * automatic "request received" answer. A conversation is open, waiting
 * for the customer (after the team answered) or closed; a new mail from
 * the customer opens it again. One person may take it. A box may answer a
 * new conversation once by itself, off unless switched on, with a text of
 * its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest automatic answer, in characters. */
define('WS_MAIL_AUTO_REPLY_MAX', 5000);

/** One automatic answer per sender and box in this many seconds. */
define('WS_MAIL_AUTO_REPLY_EVERY', 86400);

/**
 * The states of a conversation and their words.
 *
 * @return array status => label
 */
function ws_mail_statuses()
{
    return array(
        'open'    => lang('Open'),
        'waiting' => lang('Waiting for the customer'),
        'closed'  => lang('Closed'),
    );
}

/**
 * What a conversation's card shows of its state: the status and who took
 * it.
 *
 * @param array  $conversation ws_mail_conversations row
 * @param string $assignee     the name of the person who took it, '' for nobody
 * @return string HTML
 */
function ws_mail_status_html($conversation, $assignee)
{
    $status = (string) ($conversation['status'] ?? 'open');
    $statuses = ws_mail_statuses();
    $themes = array('open' => 'theme-primary', 'waiting' => 'theme-warning', 'closed' => 'theme-secondary');

    if (!isset($statuses[$status])) {
        $status = 'open';
    }

    $html = '<span class="badge ' . $themes[$status] . ' ms-1">' . h($statuses[$status]) . '</span>';

    if ((string) $assignee !== '') {
        $html .= '<span class="ms-1"><i class="bi bi-person-check me-1" aria-hidden="true"></i>' . h($assignee) . '</span>';
    }

    return $html;
}

/**
 * What a reader who may answer can change on a conversation's card: its
 * status, and whether they hold it.
 *
 * @param array|null $item   ws_mail_items_map() entry
 * @param int        $offer  ws_mail_reply_offer() (0: not the card, or may not answer)
 * @param int        $viewer_id
 * @return array|null status, mine (bool), taken (bool); null for none
 */
function ws_mail_state_offer($item, $offer, $viewer_id)
{
    $conversation = is_array($item) ? $item['conversation'] : null;

    if (((int) $offer <= 0) || !is_array($conversation)) {
        return null;
    }

    $assignee = (int) ($conversation['assignee_id'] ?? 0);

    return array(
        'status' => (string) ($conversation['status'] ?? 'open'),
        'mine'   => ($assignee > 0) && ($assignee === (int) $viewer_id),
        'taken'  => $assignee > 0,
    );
}

/**
 * Changes a conversation's status or holder: status open | waiting |
 * closed; assign 'me' (the viewer takes it) or 'none' (nobody holds it).
 * Whoever may write in the conversation's channel may do both.
 *
 * @param array $viewer
 * @param int   $conversation_id
 * @param array $changes status, assign (each optional)
 * @return array ok, error (no_access | status | nothing)
 */
function ws_mail_conversation_set($viewer, $conversation_id, $changes)
{
    $conversation = db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $conversation_id . "'");
    $channel = is_array($conversation) && $conversation ? ws_channel((int) $conversation['channel_id']) : null;

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => 'no_access');
    }

    $changed = ws_mail_conversation_changes($conversation, $changes, (int) $viewer['id'], time());

    if (!$changed['ok']) {
        return array('ok' => false, 'error' => $changed['error']);
    }

    $set = array();

    foreach ($changed['fields'] as $column => $value) {
        $set[] = $column . " = '" . e((string) $value) . "'";
    }

    db("UPDATE ws_mail_conversations SET " . implode(', ', $set) . " WHERE id = '" . (int) $conversation['id'] . "'");

    $card = ws_message((int) $conversation['message_id']);

    // The card is drawn again for everybody reading the channel.
    if ($card && ((int) $card['deleted_at'] === 0)) {
        ws_message_touch((int) $card['id']);
    }

    return array('ok' => true, 'error' => '');
}

/**
 * The columns a conversation change writes. Pure.
 *
 * @param array $conversation
 * @param array $changes   status, assign
 * @param int   $viewer_id
 * @param int   $now
 * @return array ok, error, fields (column => value)
 */
function ws_mail_conversation_changes($conversation, $changes, $viewer_id, $now)
{
    $set = array();

    if (isset($changes['status']) && ((string) $changes['status'] !== '')) {
        $status = (string) $changes['status'];

        if (!isset(ws_mail_statuses()[$status])) {
            return array('ok' => false, 'error' => 'status', 'fields' => array());
        }

        if ($status !== (string) $conversation['status']) {
            $set['status'] = $status;
            $set['closed_at'] = ($status === 'closed') ? (int) $now : 0;
        }
    }

    $assign = (string) ($changes['assign'] ?? '');

    if (($assign === 'me') && ((int) $conversation['assignee_id'] !== (int) $viewer_id)) {
        $set['assignee_id'] = (int) $viewer_id;
    } elseif (($assign === 'none') && ((int) $conversation['assignee_id'] > 0)) {
        $set['assignee_id'] = 0;
    }

    if (!$set) {
        return array('ok' => false, 'error' => 'nothing', 'fields' => array());
    }

    $set['updated_at'] = (int) $now;

    return array('ok' => true, 'error' => '', 'fields' => $set);
}

/**
 * Words for a reason a conversation was not changed.
 *
 * @param string $error
 * @return string
 */
function ws_mail_conversation_error_text($error)
{
    $texts = array(
        'no_access' => lang('You cannot write in this conversation\'s channel.'),
        'status'    => lang('That status is not known.'),
        'nothing'   => lang('Nothing was changed.'),
    );

    return $texts[$error] ?? $texts['nothing'];
}

/**
 * The texts of the conversation's controls (assets/js/workspace_mail.js).
 *
 * @return array key => text
 */
function ws_mail_status_js_strings()
{
    $strings = array(
        'mail_status'  => lang('Status'),
        'mail_take'    => lang('Take it'),
        'mail_release' => lang('Let it go'),
    );

    foreach (ws_mail_statuses() as $key => $label) {
        $strings['mail_status_' . $key] = $label;
    }

    return $strings;
}

/* ---------------------------------------------------------------------------
   The automatic answer
   --------------------------------------------------------------------------- */

/**
 * Whether a new conversation gets the box's automatic answer. Pure: the
 * box has it on with a text, the sender was not shown to be forged (an
 * answer to a forged address goes to a stranger), and the sender had none
 * from this box in the last WS_MAIL_AUTO_REPLY_EVERY seconds.
 *
 * @param array  $box    ws_mail_boxes row
 * @param string $auth   the mail's pass | fail | none
 * @param int    $recent automatic answers to this sender in that time
 * @return bool
 */
function ws_mail_auto_reply_due($box, $auth, $recent)
{
    return ((int) ($box['auto_reply'] ?? 0) === 1)
        && (trim((string) ($box['auto_reply_text'] ?? '')) !== '')
        && ((string) $auth !== 'fail')
        && ((int) $recent === 0);
}

/**
 * Sends the box's automatic answer to the customer of a new conversation,
 * through the box's own route, with Auto-Submitted (RFC 3834) so the
 * customer's own automatic answers stay quiet, and through the mail queue
 * so a mailbox reader never waits on a server. It is kept as an outgoing
 * item without a writer and drawn under the card; it is no first answer,
 * and the conversation stays open.
 *
 * @param array $box
 * @param int   $item_id the mail that opened the conversation
 * @return bool whether it was handed over
 */
function ws_mail_auto_reply($box, $item_id)
{
    $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . (int) $item_id . "'");
    $conversation = $item ? db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $item['conversation_id'] . "'") : null;

    if (!$item || !$conversation || !filter_var((string) $conversation['customer_email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $recent = (int) db_value("SELECT COUNT(*) FROM ws_mail_items
        WHERE box_id = '" . (int) $box['id'] . "' AND direction = 'out' AND user_id = 0
            AND to_list = '" . e((string) $conversation['customer_email']) . "' AND created_at > '" . (time() - WS_MAIL_AUTO_REPLY_EVERY) . "'");

    if (!ws_mail_auto_reply_due($box, (string) $item['auth'], $recent)) {
        return false;
    }

    $route = ws_mail_reply_route($box, ws_mail_reply_site_domain());

    if (!$route['ok']) {
        return false;
    }

    $conversation_id = (int) $conversation['id'];
    $host = pg_mail_hostname();

    if ($host === '') {
        $host = (string) substr(strrchr((string) $box['address'], '@'), 1);
    }

    $text = mb_substr(trim((string) $box['auto_reply_text']), 0, WS_MAIL_AUTO_REPLY_MAX);
    $message_id = ws_mail_reply_message_id($conversation_id, bin2hex(random_bytes(8)), $host);
    $headers = ws_mail_reply_headers($item, $message_id);
    $subject = ws_mail_reply_subject((string) $item['subject'], $conversation_id);
    $properties = array(
        'to'                 => (string) $conversation['customer_email'],
        'to_name'            => (string) $conversation['customer_name'],
        'from_name'          => (string) $box['name'],
        'from_email_address' => (string) $box['address'],
        'subject'            => $subject,
        'body'               => ws_mail_reply_body($text, '', (string) $box['name'], null),
        'headers'            => $headers,
        'notify_sender'      => false,
        'auto_submitted'     => true,
        'queue'              => true,
    );

    if ($route['smtp']) {
        $properties['smtp'] = $route['smtp'];
    }

    if (!email($properties)) {
        return false;
    }

    $now = time();
    $references = trim(isset($headers['References']) ? str_replace(array('<', '>'), '', $headers['References']) : '');

    db("INSERT INTO ws_mail_items (box_id, conversation_id, direction, rfc_message_id, rfc_message_hash, in_reply_to, reference_ids,
            from_email, from_name, to_list, subject, body_text, auth, user_id, sent_at, created_at)
        VALUES ('" . (int) $box['id'] . "', '" . $conversation_id . "', 'out', '" . e($message_id) . "', '" . e(ws_mail_hash($message_id, '')) . "',
            '" . e((string) $item['rfc_message_id']) . "', '" . e($references) . "',
            '" . e((string) $box['address']) . "', '" . e(mb_substr((string) $box['name'], 0, 255)) . "', '" . e((string) $conversation['customer_email']) . "',
            '" . e($subject) . "', '" . e($text) . "', 'pass', 0, '" . $now . "', '" . $now . "')");

    $out_id = (int) mysqli_insert_id(db::$con);
    $card = ws_message((int) $conversation['message_id']);

    if (($out_id > 0) && $card && ((int) $card['deleted_at'] === 0)) {
        $message = ws_mail_message_write((int) $card['channel_id'], (int) $card['id'], $out_id, ws_mail_message_body($text), 0, '', $now);
        db("UPDATE ws_mail_items SET message_id = '" . (int) $message . "' WHERE id = '" . $out_id . "'");
    }

    return true;
}

/**
 * Whether an outgoing item is the box's automatic answer: no writer.
 *
 * @param array|null $item ws_mail_items row
 * @return bool
 */
function ws_mail_item_is_auto_reply($item)
{
    return is_array($item) && ((string) ($item['direction'] ?? 'in') === 'out') && ((int) ($item['user_id'] ?? 0) === 0);
}

/**
 * The automatic answer's half of a box's form: the switch and the text.
 *
 * @param string     $prefix
 * @param array|null $box
 * @return string HTML
 */
function ws_mail_auto_reply_fields($prefix, $box)
{
    $on = $box && ((int) ($box['auto_reply'] ?? 0) === 1);
    $text = $box ? (string) ($box['auto_reply_text'] ?? '') : '';

    return '
                <div class="md:col-12"><h3 class="h6 mt-3 mb-0">' . h(lang('Automatic answer')) . '</h3>
                    <div class="form-text">' . h(lang('Sent once to the customer of a new conversation, from the mailbox\'s address and the way answers are sent; not to a sender the checking server found forged, and at most once a day to the same address.')) . '</div>
                </div>
                <div class="md:col-12">
                    <div class="form-field">
                        <div class="switch"><input type="checkbox" name="auto_reply" value="1" id="' . $prefix . 'auto_reply"' . ($on ? ' checked' : '') . '></div>
                        <label for="' . $prefix . 'auto_reply">' . h(lang('Answer new conversations automatically')) . '</label>
                    </div>
                </div>
                <div class="md:col-12">
                    <label class="form-label" for="' . $prefix . 'auto_reply_text">' . h(lang('Text of the automatic answer')) . '</label>
                    <textarea class="form-control form-control-sm" name="auto_reply_text" id="' . $prefix . 'auto_reply_text" rows="4" maxlength="' . WS_MAIL_AUTO_REPLY_MAX . '" placeholder="' . h(lang('Thank you for your e-mail. We have received it and will answer as soon as we can.')) . '">' . h($text) . '</textarea>
                </div>';
}

/**
 * The automatic answer as posted with a box form, checked. Pure.
 *
 * @param array $input auto_reply, auto_reply_text
 * @return array ok, error, auto_reply (0|1), auto_reply_text
 */
function ws_mail_auto_reply_input($input)
{
    $on = !empty($input['auto_reply']) ? 1 : 0;
    $text = trim(str_replace(array("\r\n", "\r"), "\n", is_scalar($input['auto_reply_text'] ?? '') ? (string) ($input['auto_reply_text'] ?? '') : ''));

    if (mb_strlen($text) > WS_MAIL_AUTO_REPLY_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'The automatic answer is at most {var:1} characters.', 'vars' => pg_format_number(WS_MAIL_AUTO_REPLY_MAX, 0))), 'auto_reply' => $on, 'auto_reply_text' => $text);
    }

    if ($on && ($text === '')) {
        return array('ok' => false, 'error' => lang('Write the automatic answer, or switch it off.'), 'auto_reply' => $on, 'auto_reply_text' => $text);
    }

    return array('ok' => true, 'error' => '', 'auto_reply' => $on, 'auto_reply_text' => $text);
}
