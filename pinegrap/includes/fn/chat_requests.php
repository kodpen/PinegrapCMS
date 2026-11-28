<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A site chat made into a support request from the panel's chat window:
 * "Make a request" builds a mail from the customer's address to a chosen
 * Workspace support box, holding the chat's text, and hands it to the box's
 * own intake (ws_mail_ingest()), so routes, rules, notices and the automatic
 * answer with its [#T<id>] tag and signed link follow as for any mail.
 *
 * The mail's Message-ID names the chat and its last message, so a second
 * click before anything new was written finds the same request instead of
 * opening another one. Loaded by chat.php where it is used, not by
 * functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/support_forms.php');

/** The longest subject taken from the customer's first words. */
define('PG_CHAT_REQUEST_SUBJECT_MAX', 80);

/**
 * Whether a chat may be made into a request by this viewer (pure): a site
 * chat, read from the team's side by a role of Manager or above, whose
 * customer left an address.
 *
 * @param array        $conversation chat_conversations row
 * @param string|false $side         pg_chat_side()
 * @param int          $role         the viewer's user_role
 * @return bool
 */
function pg_chat_request_allowed($conversation, $side, $role)
{
    return ((string) ($conversation['channel'] ?? '') === 'site')
        && ($side === 'target')
        && ((int) $role <= 2)
        && (bool) filter_var(trim((string) ($conversation['party_email'] ?? '')), FILTER_VALIDATE_EMAIL);
}

/**
 * Whether a chat message was written by the customer (pure): a visitor's,
 * or a signed-in member's own in the chat they started.
 *
 * @param array $message           chat_messages row (sender_kind, sender_user_id)
 * @param int   $initiator_user_id the chat's initiator_user_id
 * @return bool
 */
function pg_chat_request_from_customer($message, $initiator_user_id)
{
    $kind = (string) ($message['sender_kind'] ?? '');

    if ($kind === 'visitor') {
        return true;
    }

    return ($kind === 'user') && ((int) $initiator_user_id > 0) && ((int) ($message['sender_user_id'] ?? 0) === (int) $initiator_user_id);
}

/**
 * The request's subject (pure): the customer's first words on one line, cut
 * at a word, or "Live chat #<id>" when the customer wrote none.
 *
 * @param string $first_text the customer's first message
 * @param int    $chat_id
 * @param string $fallback   lang('Live chat #{var:1}') filled
 * @return string
 */
function pg_chat_request_subject($first_text, $chat_id, $fallback)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $first_text));

    if ($text === '') {
        return (string) $fallback;
    }

    if (mb_strlen($text) <= PG_CHAT_REQUEST_SUBJECT_MAX) {
        return $text;
    }

    $cut = mb_substr($text, 0, PG_CHAT_REQUEST_SUBJECT_MAX - 1);
    $space = mb_strrpos($cut, ' ');

    if (($space !== false) && ($space > PG_CHAT_REQUEST_SUBJECT_MAX / 2)) {
        $cut = mb_substr($cut, 0, $space);
    }

    return rtrim($cut, " ,.;:") . '…';
}

/**
 * The request's text (pure): an opening line, then one block a message, its
 * writer and time above its words; an attachment named on its own line.
 *
 * @param string $intro the opening line
 * @param array  $lines rows: who, when (shown time), text, file (name or '')
 * @param string $file_label lang('Attachment')
 * @return string
 */
function pg_chat_request_body($intro, array $lines, $file_label)
{
    $parts = array(trim((string) $intro));

    foreach ($lines as $line) {
        $text = trim(str_replace("\r\n", "\n", (string) ($line['text'] ?? '')));
        $file = trim((string) ($line['file'] ?? ''));

        if (($text === '') && ($file === '')) {
            continue;
        }

        $block = trim((string) ($line['who'] ?? '')) . ' (' . trim((string) ($line['when'] ?? '')) . '):';

        if ($text !== '') {
            $block .= "\n" . $text;
        }

        if ($file !== '') {
            $block .= "\n[" . $file_label . ': ' . $file . ']';
        }

        $parts[] = $block;
    }

    return implode("\n\n", $parts) . "\n";
}

/**
 * The request mail's Message-ID, without brackets (pure): the chat and its
 * last message, so the same chat made again unchanged is a duplicate.
 *
 * @param int    $chat_id
 * @param int    $last_message_id
 * @param string $host
 * @return string
 */
function pg_chat_request_message_id($chat_id, $last_message_id, $host)
{
    return 'pg-chat-' . (int) $chat_id . '-' . (int) $last_message_id . '@' . $host;
}

/**
 * The chat this viewer may make a request from, or the refusal.
 *
 * @param int $conversation_id
 * @return array conversation (row or null), message (refusal or '')
 */
function pg_chat_request_conversation($conversation_id)
{
    $conversation = pg_chat_conversation($conversation_id);

    if (!$conversation) {
        return array('conversation' => null, 'message' => lang('Conversation not found.'));
    }

    if (!pg_chat_request_allowed($conversation, pg_chat_side($conversation, (int) USER_ID), (int) USER_ROLE)) {
        return array('conversation' => null, 'message' => lang('Access denied'));
    }

    return array('conversation' => $conversation, 'message' => '');
}

/**
 * The support boxes a chat's request may go to (chat_request_boxes).
 *
 * @param int $conversation_id
 * @return array status, data.boxes (rows: id, label) or message
 */
function pg_chat_request_boxes($conversation_id)
{
    $found = pg_chat_request_conversation($conversation_id);

    if (!$found['conversation']) {
        return array('status' => 'error', 'message' => $found['message']);
    }

    $boxes = array();

    foreach (pg_support_form_boxes() as $id => $label) {
        $boxes[] = array('id' => (int) $id, 'label' => (string) $label);
    }

    return array('status' => 'success', 'data' => array('boxes' => $boxes, 'email' => (string) $found['conversation']['party_email']));
}

/**
 * Makes a chat into a support request in the chosen box (chat_request).
 * The intake's refusal writes an activity-log line; a duplicate answers
 * with the request already made.
 *
 * @param int $conversation_id
 * @param int $box_id
 * @return array status, data (conversation_id, number, again) or message
 */
function pg_chat_request_make($conversation_id, $box_id)
{
    $found = pg_chat_request_conversation($conversation_id);
    $chat = $found['conversation'];

    if (!$chat) {
        return array('status' => 'error', 'message' => $found['message']);
    }

    $box = pg_support_form_ready() ? ws_mail_box((int) $box_id) : null;

    if (!$box || !pg_support_form_box_offered($box)) {
        return array('status' => 'error', 'message' => lang('Choose a support box.'));
    }

    $select = pg_chat_message_select();
    $messages = (array) db_items("
        SELECT chat_messages.id, chat_messages.sender_kind, chat_messages.sender_user_id, chat_messages.body, chat_messages.created_at" . $select['cols'] . "
        FROM chat_messages" . $select['join'] . "
        WHERE chat_messages.conversation_id = '" . (int) $chat['id'] . "'
        ORDER BY chat_messages.id
        LIMIT 500");

    $address = mb_strtolower(trim((string) $chat['party_email']));
    $customer = trim((string) $chat['party_name']);
    $customer_label = ($customer !== '') ? $customer : $address;
    $names = array();
    $lines = array();
    $first = '';

    foreach ($messages as $message) {
        if ((string) $message['sender_kind'] === 'system') {
            continue;
        }

        if (pg_chat_request_from_customer($message, (int) $chat['initiator_user_id'])) {
            $who = $customer_label;

            if ($first === '') {
                $first = (string) $message['body'];
            }
        } else {
            $user_id = (int) $message['sender_user_id'];

            if (!isset($names[$user_id])) {
                $row = pg_chat_user_row($user_id);
                $names[$user_id] = $row ? pg_chat_display_name($row['first_name'], $row['last_name'], $row['username']) : lang('Team');
            }

            $who = $names[$user_id];
        }

        $lines[] = array(
            'who'  => $who,
            'when' => date('Y-m-d H:i', (int) $message['created_at']),
            'text' => (string) $message['body'],
            'file' => (string) ($message['attachment_name'] ?? ''),
        );
    }

    $intro = lang(array('string' => 'Live chat #{var:1}, started {var:2}.', 'vars' => array((int) $chat['id'], date('Y-m-d H:i', (int) $chat['created_at']))));

    if (trim((string) $chat['page_url']) !== '') {
        $intro .= "\n" . lang('Page') . ': ' . trim((string) $chat['page_url']);
    }

    $raw = pg_support_form_mail_raw(array(
        'from_email' => $address,
        'from_name'  => $customer,
        'to'         => (string) $box['address'],
        'subject'    => pg_chat_request_subject($first, (int) $chat['id'], lang(array('string' => 'Live chat #{var:1}', 'vars' => array((int) $chat['id'])))),
        'text'       => pg_chat_request_body($intro, $lines, lang('Attachment')),
        'message_id' => pg_chat_request_message_id((int) $chat['id'], (int) $chat['last_message_id'], pg_mail_hostname()),
        'date'       => time(),
    ));

    $tie = array('contact_id' => 0, 'user_id' => 0);

    if ((int) $chat['initiator_user_id'] > 0) {
        $member = pg_chat_user_row((int) $chat['initiator_user_id']);
        $contact_email = ($member && (int) $member['contact_id'] > 0) ? (string) db_value("SELECT email_address FROM contacts WHERE id = '" . (int) $member['contact_id'] . "'") : '';
        $tie = pg_support_form_tie($address, (int) $chat['initiator_user_id'], (string) ($member['email_address'] ?? ''), (int) ($member['contact_id'] ?? 0), $contact_email);
    }

    try {
        // The request's list label leads back to the chat.
        $result = ws_mail_ingest($raw, $box, array('tie' => $tie, 'source' => 'chat', 'source_id' => (int) $chat['id']));
    } catch (Throwable $error) {
        $result = array('status' => 'refused', 'reason' => 'error', 'conversation_id' => 0, 'item_id' => 0);
    }

    $again = ($result['status'] === 'duplicate');
    $request_id = $again
        ? (int) db_value("SELECT conversation_id FROM ws_mail_items WHERE id = '" . (int) ($result['item_id'] ?? 0) . "'")
        : (int) ($result['conversation_id'] ?? 0);

    if (!in_array($result['status'], array('stored', 'duplicate'), true) || ($request_id <= 0)) {
        log_activity(pg_log_lang(array('string' => 'chat conversation (#{var:1}) opened no support request: {var:2}', 'vars' => array((int) $chat['id'], trim($result['status'] . ' ' . $result['reason'])))), $_SESSION['sessionusername'] ?? '');

        return array('status' => 'error', 'message' => lang('The request could not be made.'));
    }

    if (!$again) {
        log_activity(pg_log_lang(array('string' => 'chat conversation (#{var:1}) made into support request #T{var:2}', 'vars' => array((int) $chat['id'], $request_id))), $_SESSION['sessionusername'] ?? '');
    }

    return array('status' => 'success', 'data' => array(
        'conversation_id' => $request_id,
        'number'          => '#T' . $request_id,
        'again'           => $again,
        'message'         => $again
            ? lang(array('string' => 'This chat is already request {var:1}.', 'vars' => array('#T' . $request_id)))
            : lang(array('string' => 'Request {var:1} was made in {var:2}.', 'vars' => array('#T' . $request_id, pg_support_form_box_label($box)))),
    ));
}
