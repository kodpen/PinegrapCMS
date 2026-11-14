<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a customer's WhatsApp message as a conversation in a channel,
 * as an e-mail is. The WhatsApp account is a ws_mail_boxes row of its own
 * (transport whatsapp) whose channel the Communication › WhatsApp card
 * names; a received message (includes/fn/whatsapp_webhook.php) joins the
 * newest conversation of its number in that box that is not closed, or
 * opens a new one. The conversation, its card, status, holder, notices and
 * a known customer's line are the mail tables' and screens' own: only what
 * is mail alone (subject, addresses, answering and forwarding by e-mail)
 * stays out.
 *
 * Answering by WhatsApp is not here: a phone conversation is offered no
 * "Reply by e-mail" and no "Forward as e-mail".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Whether the 2026.11.0 upgrade has made the mail tables ready for phone
 * conversations (11.0.199: the transports and customer_phone).
 *
 * @return bool
 */
function ws_mail_phone_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_mail_ready() && pg_schema_has('ws_mail_conversations', 'customer_phone');
    }

    return $ready;
}

/**
 * The transports whose conversations are by phone number.
 *
 * @return string[]
 */
function ws_mail_phone_transports()
{
    return array('whatsapp', 'sms');
}

/**
 * Whether a mail item belongs to a phone conversation (ws_mail_items_map()
 * gives each item its box's transport). Pure.
 *
 * @param array|null $item
 * @return bool
 */
function ws_mail_item_is_phone($item)
{
    return is_array($item) && in_array((string) ($item['transport'] ?? ''), ws_mail_phone_transports(), true);
}

/**
 * Whether a box is a phone account's (kept by its card in Site Settings ›
 * Communication), not a mailbox the Workspace's mailbox settings edit. Pure.
 *
 * @param array|null $box ws_mail_boxes row
 * @return bool
 */
function ws_mail_box_is_phone($box)
{
    return is_array($box) && in_array((string) ($box['transport'] ?? ''), ws_mail_phone_transports(), true);
}

/**
 * The box of a transport: the one row the account's card keeps.
 *
 * @param string $transport whatsapp | sms
 * @return array|null
 */
function ws_mail_phone_box($transport)
{
    if (!ws_mail_phone_ready() || !in_array($transport, ws_mail_phone_transports(), true)) {
        return null;
    }

    $box = db_item("SELECT * FROM ws_mail_boxes WHERE transport = '" . e($transport) . "' ORDER BY id LIMIT 1");

    return is_array($box) ? $box : null;
}

/**
 * The WhatsApp box's columns from the account and the chosen channel. Pure.
 *
 * @param array $app        pg_whatsapp_app()
 * @param int   $channel_id 0 for none: the box is kept, switched off
 * @return array name, address, settings (JSON), channel_id, enabled
 */
function ws_mail_phone_box_values($app, $channel_id)
{
    $number = trim((string) ($app['display_phone_number'] ?? ''));

    return array(
        'name'       => mb_substr(($number !== '') ? 'WhatsApp ' . $number : 'WhatsApp', 0, 100),
        'address'    => mb_substr($number, 0, 255),
        'settings'   => json_encode(array('app' => 'whatsapp', 'phone_number_id' => (string) ($app['phone_number_id'] ?? ''))),
        'channel_id' => max(0, (int) $channel_id),
        'enabled'    => ((int) $channel_id > 0) ? 1 : 0,
    );
}

/**
 * The channels the WhatsApp card offers: the open and private channels the
 * person may read, as a mailbox's form offers them.
 *
 * @param array $viewer ws_viewer()
 * @return array id => ws_channels row
 */
function ws_mail_phone_channels($viewer)
{
    return ws_mail_box_channels($viewer);
}

/**
 * What a post of the card's Channel field asks: the channel kept, a channel
 * the person may choose, or none. A channel already kept stays choosable by
 * someone who does not see it, so saving the card leaves it as it is. Pure.
 *
 * @param mixed $posted   the field's value
 * @param array $channels ws_mail_phone_channels()
 * @param int   $kept     the box's channel now, 0 for none
 * @return array ok, channel_id
 */
function ws_mail_phone_channel_decide($posted, $channels, $kept)
{
    $id = (is_scalar($posted) && ctype_digit((string) $posted)) ? (int) $posted : -1;

    if (($id === 0) || ($id === (int) $kept) || isset($channels[$id])) {
        return array('ok' => true, 'channel_id' => max(0, $id));
    }

    return array('ok' => false, 'channel_id' => (int) $kept);
}

/**
 * The card's Channel field: where customers' WhatsApp messages open their
 * conversations.
 *
 * @param array $channels ws_mail_phone_channels()
 * @param int   $kept     the box's channel now, 0 for none
 * @return string HTML
 */
function ws_mail_phone_channel_html($channels, $kept)
{
    $options = '<option value="0">' . h(lang('No channel: a notice and the contact\'s history only')) . '</option>';

    if (((int) $kept > 0) && !isset($channels[(int) $kept])) {
        $options .= '<option value="' . (int) $kept . '" selected>' . h(lang('A channel you do not see')) . '</option>';
    }

    foreach ($channels as $channel) {
        $options .= '<option value="' . (int) $channel['id'] . '"' . (((int) $channel['id'] === (int) $kept) ? ' selected' : '') . '># '
            . h((string) $channel['name']) . '</option>';
    }

    return '
                                                        <div class="pg-f-md">
                                                            <label for="whatsapp_channel" class="form-label">' . lang('Channel') . '</label>
                                                            <select name="whatsapp_channel" id="whatsapp_channel" class="form-control">' . $options . '</select>
                                                            <div class="form-text">' . lang('A customer\'s WhatsApp message opens a conversation in this channel, or joins the open one of their number, as an e-mail does in a mailbox\'s channel.') . '</div>
                                                        </div>';
}

/**
 * Writes the WhatsApp box: added the first time a channel is chosen, its
 * name, number and channel kept in step with the card after that; no
 * channel switches it off and keeps its conversations.
 *
 * @param array $app        pg_whatsapp_app()
 * @param int   $channel_id
 * @param int   $user_id
 * @return bool
 */
function ws_mail_phone_box_save($app, $channel_id, $user_id)
{
    if (!ws_mail_phone_ready()) {
        return false;
    }

    $box = ws_mail_phone_box('whatsapp');
    $values = ws_mail_phone_box_values($app, $channel_id);
    $now = time();

    if (!$box) {
        if ($values['channel_id'] <= 0) {
            return true;
        }

        return (bool) db_q("INSERT INTO ws_mail_boxes (name, address, transport, settings, channel_id, enabled, created_by, created_at, updated_at)
            VALUES (?, ?, 'whatsapp', ?, ?, ?, ?, ?, ?)", array(
                $values['name'], $values['address'], $values['settings'], $values['channel_id'], $values['enabled'], (int) $user_id, $now, $now,
            ));
    }

    return (bool) db_q("UPDATE ws_mail_boxes SET name = ?, address = ?, settings = ?, channel_id = ?, enabled = ?, updated_at = ? WHERE id = ?", array(
        $values['name'], $values['address'], $values['settings'], $values['channel_id'], $values['enabled'], $now, (int) $box['id'],
    ));
}

/**
 * What stands for a message without text in its card: its kind. Pure.
 *
 * @param string $kind the WhatsApp message type
 * @return string
 */
function ws_mail_phone_kind_text($kind)
{
    $kinds = array(
        'image'    => lang('A picture'),
        'video'    => lang('A video'),
        'audio'    => lang('A voice message'),
        'document' => lang('A document'),
        'sticker'  => lang('A sticker'),
        'location' => lang('A location'),
        'contacts' => lang('A contact card'),
        'reaction' => lang('A reaction'),
    );

    return isset($kinds[$kind]) ? '[' . $kinds[$kind] . ']' : '[' . lang('A message WhatsApp did not pass on') . ']';
}

/**
 * Brings a received WhatsApp message into the box's channel: it joins the
 * newest conversation of the number that is not closed, or opens one. The
 * whatsapp_inbox row stays as it is (the bell notice and the contact's
 * history); this adds the conversation.
 *
 * status answers what happened: stored, duplicate (the wamid is in the box
 * already), held (WS_MAIL_SENDER_HOURLY from one number this hour), refused
 * (reason: no_box, no_number, no_channel, not_saved).
 *
 * @param array $box     ws_mail_phone_box('whatsapp')
 * @param array $message wamid, from (E.164 digits), name, text, kind,
 *                       contact_id, at
 * @param int   $now
 * @return array status, reason, item_id, conversation_id, message_id
 */
function ws_mail_phone_ingest($box, $message, $now)
{
    $result = array('status' => 'refused', 'reason' => '', 'item_id' => 0, 'conversation_id' => 0, 'message_id' => 0);

    if (!is_array($box) || empty($box['enabled']) || ((int) $box['channel_id'] <= 0)) {
        return array_merge($result, array('reason' => 'no_box'));
    }

    $box_id = (int) $box['id'];
    $digits = (string) $message['from'];

    // A user known only by a WhatsApp username has no number to thread by.
    if (!preg_match('/^[1-9]\d{6,14}$/D', $digits)) {
        return array_merge($result, array('reason' => 'no_number'));
    }

    $number = '+' . $digits;
    $hash = hash('sha256', 'whatsapp:' . (string) $message['wamid']);

    if ((int) db_value("SELECT id FROM ws_mail_items WHERE box_id = '" . $box_id . "' AND rfc_message_hash = '" . e($hash) . "'") > 0) {
        return array_merge($result, array('status' => 'duplicate'));
    }

    if ((int) db_value("SELECT COUNT(*) FROM ws_mail_items
        WHERE box_id = '" . $box_id . "' AND from_email = '" . e($number) . "' AND created_at > '" . ((int) $now - 3600) . "'") >= WS_MAIL_SENDER_HOURLY) {
        return array_merge($result, array('status' => 'held'));
    }

    $conversation = db_item("SELECT * FROM ws_mail_conversations
        WHERE box_id = '" . $box_id . "' AND customer_phone = '" . e($digits) . "' AND status <> 'closed'
        ORDER BY id DESC LIMIT 1");
    $conversation = is_array($conversation) ? $conversation : null;
    $channel = ws_channel($conversation ? (int) $conversation['channel_id'] : (int) $box['channel_id']);

    if (!$channel || ((int) $channel['archived_at'] > 0)) {
        return array_merge($result, array('reason' => 'no_channel'));
    }

    $text = trim((string) $message['text']);
    $name = mb_substr(trim((string) $message['name']), 0, 255);
    $at = ((int) $message['at'] > 0) ? (int) $message['at'] : (int) $now;

    $written = db_q("INSERT INTO ws_mail_items (box_id, direction, rfc_message_id, rfc_message_hash, from_email, from_name, subject, body_text, auth, sent_at, created_at)
        VALUES (?, 'in', ?, ?, ?, ?, '', ?, 'pass', ?, ?)", array(
            $box_id, mb_substr((string) $message['wamid'], 0, 255), $hash, $number, $name, $text, $at, (int) $now,
        ));
    $item_id = $written ? (int) mysqli_insert_id(db::$con) : 0;

    if ($item_id <= 0) {
        return array_merge($result, array('reason' => 'not_saved'));
    }

    $body = ws_mail_message_body(($text !== '') ? $text : ws_mail_phone_kind_text((string) $message['kind']));
    $opened = !$conversation;

    if ($conversation) {
        $conversation_id = (int) $conversation['id'];
        $card = ws_message((int) $conversation['message_id']);
        $parent_id = ($card && ((int) $card['channel_id'] === (int) $channel['id']) && ((int) $card['deleted_at'] === 0)) ? (int) $card['id'] : 0;
        $message_id = ws_mail_message_write($channel['id'], $parent_id, $item_id, $body, 0, '', $now);

        db("UPDATE ws_mail_conversations SET last_in_at = '" . (int) $now . "', status = 'open', closed_at = 0, updated_at = '" . (int) $now . "'
            WHERE id = '" . $conversation_id . "'");

        if ($parent_id > 0) {
            ws_message_touch($parent_id);
        } else {
            db("UPDATE ws_mail_conversations SET message_id = '" . (int) $message_id . "' WHERE id = '" . $conversation_id . "'");
        }
    } else {
        $contact_id = max(0, (int) $message['contact_id']);

        db_q("INSERT INTO ws_mail_conversations (box_id, channel_id, subject, customer_email, customer_name, customer_phone, contact_id, user_id,
                status, first_in_at, last_in_at, created_at, updated_at)
            VALUES (?, ?, '', '', ?, ?, ?, 0, 'open', ?, ?, ?, ?)", array(
                $box_id, (int) $channel['id'], $name, $digits, $contact_id, (int) $now, (int) $now, (int) $now, (int) $now,
            ));

        $conversation_id = (int) mysqli_insert_id(db::$con);
        $message_id = ws_mail_message_write($channel['id'], 0, $item_id, $body, 0, '', $now);

        db("UPDATE ws_mail_conversations SET message_id = '" . (int) $message_id . "' WHERE id = '" . $conversation_id . "'");

        // A known customer's channels hear of it, as of a new e-mail.
        if ($contact_id > 0) {
            ws_mail_customer_lines(array('channel_id' => (int) $channel['id'], 'subject' => mb_substr($body, 0, 200), 'contact_id' => $contact_id, 'user_id' => 0),
                ($name !== '') ? $name : $number, 'whatsapp');
        }
    }

    db("UPDATE ws_mail_items SET conversation_id = '" . $conversation_id . "', message_id = '" . (int) $message_id . "' WHERE id = '" . $item_id . "'");

    // Whoever holds an ongoing conversation hears the customer wrote again.
    if (!$opened && ((int) $conversation['assignee_id'] > 0)) {
        ws_notify((int) $conversation['assignee_id'], 'mail_customer', array('channel_id' => (int) $channel['id'], 'message_id' => (int) $message_id, 'actor_id' => 0));
    }

    ws_mail_phone_received_event($box, $conversation_id, (int) $channel['id'], (int) $message_id, $opened, ($name !== '') ? $name : $number, $number);

    return array_merge($result, array(
        'status'          => 'stored',
        'item_id'         => $item_id,
        'conversation_id' => $conversation_id,
        'message_id'      => (int) $message_id,
    ));
}

/**
 * The received event of a phone message, as an e-mail's (ws_mail_ingest()):
 * the scheduled actions and flows hear the conversation, its transport and
 * number; webhook subscribers hear ids and the transport only. The message's
 * text stays out: it is untrusted, and the card carries it.
 *
 * @param array  $box             the phone box
 * @param int    $conversation_id
 * @param int    $channel_id
 * @param int    $message_id
 * @param bool   $opened          it opened the conversation
 * @param string $customer        the sender's name, or the number
 * @param string $number          E.164 with its plus
 */
function ws_mail_phone_received_event($box, $conversation_id, $channel_id, $message_id, $opened, $customer, $number)
{
    $transport = ws_mail_transport_kind($box['transport'] ?? '');

    if (function_exists('ws_event')) {
        ws_event('workspace.mail.received', array(
            'id'              => (int) $conversation_id,
            'conversation_id' => (int) $conversation_id,
            'channel_id'      => (int) $channel_id,
            'message_id'      => (int) $message_id,
            'box_id'          => (int) $box['id'],
            'new'             => (bool) $opened,
            'customer'        => (string) $customer,
            'customer_email'  => '',
            'customer_phone'  => (string) $number,
            'subject'         => '',
            'transport'       => $transport,
            'verified'        => true,
        ));
    }

    if (function_exists('pg_announce')) {
        pg_announce('workspace.mail.received', array(
            'conversation_id' => (int) $conversation_id,
            'channel_id'      => (int) $channel_id,
            'message_id'      => (int) $message_id,
            'box_id'          => (int) $box['id'],
            'new'             => (bool) $opened,
            'transport'       => $transport,
            'verified'        => true,
        ));
    }
}

/**
 * The person the WhatsApp card is drawn for or saved by, as the Workspace
 * reads them; null when the Workspace cannot be used.
 *
 * @param int $user_id
 * @return array|null ws_viewer()
 */
function ws_mail_phone_card_viewer($user_id)
{
    if (!ws_enabled() || !ws_mail_phone_ready() || !function_exists('pg_load_user_row')) {
        return null;
    }

    $row = pg_load_user_row((int) $user_id);

    return is_array($row) ? ws_viewer($row) : null;
}

/**
 * The card's Channel step for the signed-in person; '' while the Workspace
 * is off or not upgraded.
 *
 * @param int $user_id
 * @return string HTML
 */
function ws_mail_phone_card_field($user_id)
{
    $viewer = ws_mail_phone_card_viewer($user_id);

    if (!$viewer) {
        return '';
    }

    $box = ws_mail_phone_box('whatsapp');

    return ws_mail_phone_channel_html(ws_mail_phone_channels($viewer), $box ? (int) $box['channel_id'] : 0);
}

/**
 * Reads the card's Channel field from a post: a channel the person may not
 * choose is marked on the field and nothing is saved.
 *
 * @param array  $post
 * @param int    $user_id
 * @param object $liveform
 * @return array|null channel_id; null when the field was not drawn or refused
 */
function ws_mail_phone_card_check($post, $user_id, $liveform)
{
    $viewer = ws_mail_phone_card_viewer($user_id);

    if (!$viewer || !array_key_exists('whatsapp_channel', (array) $post)) {
        return null;
    }

    $box = ws_mail_phone_box('whatsapp');
    $decided = ws_mail_phone_channel_decide($post['whatsapp_channel'], ws_mail_phone_channels($viewer), $box ? (int) $box['channel_id'] : 0);

    if (!$decided['ok']) {
        $liveform->mark_error('whatsapp_channel', lang('Choose a channel you can see.'));

        return null;
    }

    return array('channel_id' => $decided['channel_id']);
}
