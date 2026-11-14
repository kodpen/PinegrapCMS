<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What a signed WhatsApp webhook post says, read and written down: the
 * delivery states of the messages the queue sent (onto their whatsapp_outbox
 * rows, by wamid), a template's approval state or category that Meta changed
 * (onto its whatsapp_templates row, by Meta's id), and a customer's message
 * to the site's number (a whatsapp_inbox row, a bell notice, and a line in the
 * contact's history).
 *
 * whatsapp_webhook.php checks the call (includes/fn/whatsapp.php) and hands
 * the decoded body here. Meta delivers an event again when the answer is not
 * 200, and may deliver it twice anyway, so every write is safe to repeat: a
 * state never goes back, a message is kept once by its wamid. Nothing is
 * written on a demonstration site.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// How long a received message is kept: two years.
define('PG_WHATSAPP_INBOX_KEEP', 63072000);

// The longest text kept of a received message; WhatsApp allows 4096.
define('PG_WHATSAPP_INBOX_BODY_MAX', 4096);

// Events read from one post at most; Meta batches far fewer.
define('PG_WHATSAPP_WEBHOOK_EVENTS_MAX', 500);

/**
 * Whether the 2026.11.0 upgrade has created whatsapp_inbox.
 *
 * @return bool
 */
function pg_whatsapp_inbox_ready()
{
    return pg_schema_has('whatsapp_inbox');
}

/**
 * The first $length characters of a UTF-8 text, never cutting a character.
 * Pure.
 *
 * @param mixed $text
 * @param int   $length
 * @return string
 */
function pg_whatsapp_clip($text, $length)
{
    $text = is_scalar($text) ? (string) $text : '';

    if (strlen($text) <= $length) {
        return $text;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, (int) $length, 'UTF-8');
    }

    return preg_match('/^.{0,' . (int) $length . '}/us', $text, $match) ? $match[0] : substr($text, 0, (int) $length);
}

/**
 * The events of a webhook body, in the order Meta wrote them. Only the
 * changes about the site's own number (its phone number id) are read for
 * statuses and messages; template changes are the account's. Anything this
 * does not understand is skipped. Pure.
 *
 * @param mixed  $payload  the decoded JSON body
 * @param string $phone_id the account's phone number id
 * @return array[] each with type: status (wamid, status, at, error_code,
 *                 error), message (wamid, at, from, from_user_id,
 *                 profile_name, kind, body, media_id, media_mime,
 *                 context_wamid), template (meta_id, name, language, event,
 *                 reason) or category (meta_id, name, language, category)
 */
function pg_whatsapp_webhook_events($payload, $phone_id)
{
    $events = array();

    if (!is_array($payload) || (($payload['object'] ?? '') !== 'whatsapp_business_account') || !isset($payload['entry']) || !is_array($payload['entry'])) {
        return $events;
    }

    $text = function ($value, $length) {
        return is_scalar($value) ? pg_whatsapp_clip(trim((string) $value), $length) : '';
    };

    $id = function ($value) {
        return (is_scalar($value) && preg_match('/^[A-Za-z0-9._=:+\/-]{1,128}$/D', (string) $value)) ? (string) $value : '';
    };

    foreach ($payload['entry'] as $entry) {
        foreach ((is_array($entry) && isset($entry['changes']) && is_array($entry['changes'])) ? $entry['changes'] : array() as $change) {
            if (!is_array($change) || !isset($change['value']) || !is_array($change['value'])) {
                continue;
            }

            $field = (string) ($change['field'] ?? '');
            $value = $change['value'];

            if ($field === 'messages') {
                $number = (string) ($value['metadata']['phone_number_id'] ?? '');

                if (((string) $phone_id === '') || ($number !== (string) $phone_id)) {
                    continue;
                }

                foreach ((isset($value['statuses']) && is_array($value['statuses'])) ? $value['statuses'] : array() as $status) {
                    $wamid = is_array($status) ? $id($status['id'] ?? '') : '';
                    $state = is_array($status) ? strtolower($text($status['status'] ?? '', 16)) : '';

                    if (($wamid === '') || !in_array($state, array('sent', 'delivered', 'read', 'played', 'failed'), true)) {
                        continue;
                    }

                    $error = (isset($status['errors'][0]) && is_array($status['errors'][0])) ? $status['errors'][0] : array();
                    $detail = $text($error['error_data']['details'] ?? '', 400);

                    $events[] = array(
                        'type'       => 'status',
                        'wamid'      => $wamid,
                        'status'     => ($state === 'played') ? 'read' : $state,
                        'at'         => max(0, (int) ($status['timestamp'] ?? 0)),
                        'error_code' => preg_match('/^\d{1,15}$/D', (string) ($error['code'] ?? '')) ? (string) $error['code'] : '',
                        'error'      => trim($text($error['title'] ?? ($error['message'] ?? ''), 200) . (($detail !== '') ? ': ' . $detail : '')),
                    );
                }

                // The sender's profile, by the number or the user id it
                // came with.
                $profiles = array();

                foreach ((isset($value['contacts']) && is_array($value['contacts'])) ? $value['contacts'] : array() as $contact) {
                    if (!is_array($contact)) {
                        continue;
                    }

                    $name = $text($contact['profile']['name'] ?? '', 255);

                    foreach (array('wa_id', 'user_id') as $key) {
                        if ($id($contact[$key] ?? '') !== '') {
                            $profiles[(string) $contact[$key]] = $name;
                        }
                    }
                }

                foreach ((isset($value['messages']) && is_array($value['messages'])) ? $value['messages'] : array() as $message) {
                    if (!is_array($message)) {
                        continue;
                    }

                    $wamid = $id($message['id'] ?? '');
                    $from = function_exists('pg_sms_number') ? pg_sms_number(preg_match('/^\d{6,15}$/D', (string) ($message['from'] ?? '')) ? '+' . $message['from'] : '') : '';
                    $user_id = $id($message['from_user_id'] ?? ($message['user_id'] ?? ''));

                    if (($wamid === '') || (($from === '') && ($user_id === ''))) {
                        continue;
                    }

                    $read = pg_whatsapp_webhook_message_content($message);

                    $profile = '';

                    if (isset($profiles[(string) ($message['from'] ?? '')])) {
                        $profile = $profiles[(string) $message['from']];
                    } elseif (($user_id !== '') && isset($profiles[$user_id])) {
                        $profile = $profiles[$user_id];
                    }

                    $events[] = array(
                        'type'          => 'message',
                        'wamid'         => $wamid,
                        'at'            => max(0, (int) ($message['timestamp'] ?? 0)),
                        'from'          => $from,
                        'from_user_id'  => $user_id,
                        'profile_name'  => $profile,
                        'kind'          => $read['kind'],
                        'body'          => $read['body'],
                        'media_id'      => $read['media_id'],
                        'media_mime'    => $read['media_mime'],
                        'context_wamid' => $id($message['context']['id'] ?? ''),
                    );
                }
            } elseif (($field === 'message_template_status_update') || ($field === 'template_category_update')) {
                $meta_id = (string) ($value['message_template_id'] ?? '');

                if (!preg_match('/^\d{1,32}$/D', $meta_id)) {
                    continue;
                }

                $event = array(
                    'meta_id'  => $meta_id,
                    'name'     => $text($value['message_template_name'] ?? '', 512),
                    'language' => $text($value['message_template_language'] ?? '', 16),
                );

                if ($field === 'message_template_status_update') {
                    $state = strtoupper($text($value['event'] ?? '', 24));

                    if (!preg_match('/^[A-Z_]{2,24}$/D', $state)) {
                        continue;
                    }

                    $reason = strtoupper($text($value['reason'] ?? '', 200));
                    $other = (isset($value['other_info']) && is_array($value['other_info'])) ? $value['other_info'] : array();
                    $more = $text($other['description'] ?? ($other['recommendation'] ?? ($other['title'] ?? '')), 280);

                    $reason = (($reason === 'NONE') || ($reason === 'NULL')) ? '' : $reason;

                    $events[] = $event + array(
                        'type'   => 'template',
                        'event'  => $state,
                        'reason' => pg_whatsapp_clip(trim($reason . ((($reason !== '') && ($more !== '')) ? ': ' : '') . $more), 500),
                    );
                } else {
                    $category = strtoupper($text($value['new_category'] ?? '', 16));

                    if (!in_array($category, array('MARKETING', 'UTILITY', 'AUTHENTICATION'), true)) {
                        continue;
                    }

                    $events[] = $event + array('type' => 'category', 'category' => $category);
                }
            }

            if (count($events) >= PG_WHATSAPP_WEBHOOK_EVENTS_MAX) {
                return array_slice($events, 0, PG_WHATSAPP_WEBHOOK_EVENTS_MAX);
            }
        }
    }

    return $events;
}

/**
 * What a customer's message says, whatever its type: the text, a caption,
 * a button's or list item's title, a reaction's emoji, a place's name, a
 * file's name; the media id and type of a picture, video, voice message,
 * document or sticker. Pure.
 *
 * @param array $message one of a messages webhook's messages[]
 * @return array kind, body, media_id, media_mime
 */
function pg_whatsapp_webhook_message_content($message)
{
    $kind = is_scalar($message['type'] ?? null) ? strtolower((string) $message['type']) : '';
    $kind = preg_match('/^[a-z_]{1,16}$/D', $kind) ? $kind : 'unknown';
    $part = (isset($message[$kind]) && is_array($message[$kind])) ? $message[$kind] : array();
    $string = function ($value) {
        return is_scalar($value) ? trim((string) $value) : '';
    };

    $body = '';
    $media_id = '';
    $media_mime = '';

    if ($kind === 'text') {
        $body = $string($part['body'] ?? '');
    } elseif (in_array($kind, array('image', 'video', 'audio', 'document', 'sticker'), true)) {
        $body = $string($part['caption'] ?? '');

        if (($body === '') && ($kind === 'document')) {
            $body = $string($part['filename'] ?? '');
        }

        $media_id = preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $string($part['id'] ?? '')) ? $string($part['id']) : '';
        $media_mime = preg_match('/^[a-z0-9.+-]{1,40}\/[a-z0-9.+-]{1,50}(?:; ?[a-z0-9=._-]{1,40})?$/iD', $string($part['mime_type'] ?? '')) ? strtolower($string($part['mime_type'])) : '';
    } elseif ($kind === 'button') {
        $body = $string($part['text'] ?? '');
    } elseif ($kind === 'interactive') {
        $reply = (isset($part['button_reply']) && is_array($part['button_reply'])) ? $part['button_reply'] : ((isset($part['list_reply']) && is_array($part['list_reply'])) ? $part['list_reply'] : array());
        $body = $string($reply['title'] ?? '');
    } elseif ($kind === 'reaction') {
        $body = $string($part['emoji'] ?? '');
    } elseif ($kind === 'location') {
        $body = trim($string($part['name'] ?? '') . ' ' . $string($part['address'] ?? ''));
    }

    return array(
        'kind'       => $kind,
        'body'       => pg_whatsapp_clip($body, PG_WHATSAPP_INBOX_BODY_MAX),
        'media_id'   => $media_id,
        'media_mime' => $media_mime,
    );
}

/**
 * What a delivery state does to a queued row: the columns to write, or none
 * when the row already went further. States only go forward (sent,
 * delivered, read); Meta's states may arrive out of order and twice. A
 * failure counts only before the message was delivered. Pure.
 *
 * @param array $row   delivery, delivered_at, read_at of the whatsapp_outbox row
 * @param array $event pg_whatsapp_webhook_events()'s status
 * @param int   $now   when the event carries no time
 * @return array column => value; empty for nothing to write
 */
function pg_whatsapp_delivery_change($row, $event, $now)
{
    $rank = array('' => 0, 'sent' => 1, 'failed' => 2, 'delivered' => 3, 'read' => 4);
    $current = (string) ($row['delivery'] ?? '');
    $state = (string) ($event['status'] ?? '');
    $at = ((int) ($event['at'] ?? 0) > 0) ? (int) $event['at'] : (int) $now;

    if (!isset($rank[$state]) || ($state === '')) {
        return array();
    }

    $current = isset($rank[$current]) ? $current : '';
    $change = array();

    if ($rank[$state] > $rank[$current]) {
        $change['delivery'] = $state;
    }

    // A read message was delivered too, even when that state never came.
    if ((($state === 'delivered') || ($state === 'read')) && ((int) ($row['delivered_at'] ?? 0) === 0)) {
        $change['delivered_at'] = $at;
    }

    if (($state === 'read') && ((int) ($row['read_at'] ?? 0) === 0)) {
        $change['read_at'] = $at;
    }

    if (($state === 'failed') && isset($change['delivery'])) {
        $change['error_code'] = (string) ($event['error_code'] ?? '');

        if ((string) ($event['error'] ?? '') !== '') {
            $change['last_error'] = pg_whatsapp_clip((string) $event['error'], 500);
        }
    }

    return $change;
}

/**
 * What a template change does to its row: the columns to write, or delete
 * for a template Meta deleted. Pure.
 *
 * @param array $event pg_whatsapp_webhook_events()'s template or category
 * @return array action (update, delete or none), set (column => value)
 */
function pg_whatsapp_template_change($event)
{
    if (($event['type'] ?? '') === 'category') {
        return array('action' => 'update', 'set' => array('category' => (string) $event['category']));
    }

    if (($event['type'] ?? '') !== 'template') {
        return array('action' => 'none', 'set' => array());
    }

    if ($event['event'] === 'DELETED') {
        return array('action' => 'delete', 'set' => array());
    }

    // The reason belongs to a refusal or a pause; any other state clears it.
    $keeps_reason = in_array($event['event'], array('REJECTED', 'PAUSED', 'DISABLED', 'FLAGGED'), true);

    return array('action' => 'update', 'set' => array(
        'status' => (string) $event['event'],
        'reason' => $keeps_reason ? (string) $event['reason'] : '',
    ));
}

/**
 * The bell notice for a received message, as create_notification()
 * properties: title the text (or its type when it has none), order_total
 * who sent it, form_id the whatsapp_inbox row. Pure.
 *
 * @param int   $inbox_id
 * @param array $event pg_whatsapp_webhook_events()'s message
 * @return array
 */
function pg_whatsapp_inbox_notice($inbox_id, $event)
{
    $body = trim(preg_replace('/\s+/u', ' ', (string) $event['body']));
    $sender = ((string) $event['profile_name'] !== '') ? (string) $event['profile_name'] : (((string) $event['from'] !== '') ? '+' . $event['from'] : (string) $event['from_user_id']);

    return array(
        'action'      => 'whatsapp_message',
        'type'        => 'info',
        'title'       => pg_whatsapp_clip(($body !== '') ? $body : '[' . $event['kind'] . ']', 255),
        'order_total' => pg_whatsapp_clip($sender, 100),
        'form_id'     => (int) $inbox_id,
        'user'        => 'system',
    );
}

/**
 * The contact a number belongs to: the newest contact whose mobile phone is
 * that number, however it was typed; 0 for none.
 *
 * @param string $digits pg_sms_number()
 * @return int
 */
function pg_whatsapp_contact_by_number($digits)
{
    $variants = ((string) $digits !== '') ? pg_sms_number_variants((string) $digits) : array();

    if (!$variants) {
        return 0;
    }

    $in = "('" . implode("', '", array_map('e', $variants)) . "')";

    return (int) db_value("SELECT id FROM contacts
        WHERE (mobile_phone <> '') AND (" . pg_sms_number_sql('mobile_phone') . " IN " . $in . ")
        ORDER BY id DESC
        LIMIT 1");
}

/**
 * The contact a user known only by a WhatsApp username wrote as before: the
 * contact of their latest kept message.
 *
 * @param string $user_id the business-scoped user id
 * @return int
 */
function pg_whatsapp_contact_by_user_id($user_id)
{
    if ((string) $user_id === '') {
        return 0;
    }

    return (int) db_value("SELECT contact_id FROM whatsapp_inbox
        WHERE from_user_id = '" . e($user_id) . "' AND contact_id > 0
        ORDER BY received_at DESC, id DESC
        LIMIT 1");
}

/**
 * Writes what a webhook post said. Nothing on a demonstration site, nothing
 * for a table the upgrade has not made yet; a customer's message only while
 * WhatsApp is switched on.
 *
 * @param array[] $events pg_whatsapp_webhook_events()
 * @param array   $app    pg_whatsapp_app()
 * @return array counts: status, template, message
 */
function pg_whatsapp_webhook_apply($events, $app)
{
    $done = array('status' => 0, 'template' => 0, 'message' => 0);

    if (!$events || (function_exists('pg_demo') && pg_demo())) {
        return $done;
    }

    $now = time();
    $outbox = pg_whatsapp_queue_ready();
    $templates = pg_whatsapp_templates_ready();
    $inbox = !empty($app['enabled']) && pg_whatsapp_inbox_ready();

    foreach ($events as $event) {
        if (($event['type'] === 'status') && $outbox) {
            $row = db_item("SELECT id, delivery, delivered_at, read_at FROM whatsapp_outbox WHERE wamid = '" . e($event['wamid']) . "' LIMIT 1");

            if (!is_array($row)) {
                continue;
            }

            $change = pg_whatsapp_delivery_change($row, $event, $now);

            if ($change) {
                pg_whatsapp_webhook_update('whatsapp_outbox', $change, "id = '" . (int) $row['id'] . "'");
                $done['status']++;
            }
        } elseif ((($event['type'] === 'template') || ($event['type'] === 'category')) && $templates) {
            $change = pg_whatsapp_template_change($event);
            $where = "meta_id = '" . e($event['meta_id']) . "'";

            if ($change['action'] === 'delete') {
                db("DELETE FROM whatsapp_templates WHERE " . $where);
                $done['template']++;
            } elseif ($change['action'] === 'update') {
                $change['set']['updated_at'] = $now;
                pg_whatsapp_webhook_update('whatsapp_templates', $change['set'], $where);
                $done['template']++;
            }
        } elseif (($event['type'] === 'message') && $inbox) {
            if (pg_whatsapp_inbox_receive($event, $now)) {
                $done['message']++;
            }
        }
    }

    return $done;
}

/**
 * An UPDATE of the given columns, each value bound.
 *
 * @param string $table a trusted table name
 * @param array  $set   column (trusted) => value
 * @param string $where a trusted condition
 */
function pg_whatsapp_webhook_update($table, $set, $where)
{
    $columns = array();
    $values = array();

    foreach ($set as $column => $value) {
        $columns[] = $column . ' = ?';
        $values[] = is_int($value) ? $value : (string) $value;
    }

    db_q("UPDATE " . $table . " SET " . implode(', ', $columns) . " WHERE " . $where, $values);
}

/**
 * Keeps a customer's message once: a message Meta delivers again finds its
 * wamid taken and is neither kept nor announced twice. The contact is the
 * one the number belongs to, else the one the same WhatsApp user wrote as.
 *
 * @param array $event pg_whatsapp_webhook_events()'s message
 * @param int   $now
 * @return bool whether it was new
 */
function pg_whatsapp_inbox_receive($event, $now)
{
    $contact_id = pg_whatsapp_contact_by_number($event['from']);

    if ($contact_id === 0) {
        $contact_id = pg_whatsapp_contact_by_user_id($event['from_user_id']);
    }

    $at = ((int) $event['at'] > 0) ? min((int) $event['at'], (int) $now) : (int) $now;

    $written = db_q("INSERT IGNORE INTO whatsapp_inbox
        (wamid, received_at, from_number, from_user_id, profile_name, contact_id, type, body, media_id, media_mime, context_wamid)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", array(
            (string) $event['wamid'], $at, (string) $event['from'], (string) $event['from_user_id'], (string) $event['profile_name'],
            $contact_id, (string) $event['kind'], (string) $event['body'], (string) $event['media_id'], (string) $event['media_mime'],
            (string) $event['context_wamid'],
        ));

    if (($written === false) || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    $inbox_id = (int) mysqli_insert_id(db::$con);

    create_notification(pg_whatsapp_inbox_notice($inbox_id, $event));

    // Flows that start on "A WhatsApp message arrived", and the API's
    // webhooks; the payload names the message's type as type.
    pg_announce('whatsapp.received', pg_whatsapp_received_event($inbox_id, array('type' => (string) $event['kind']) + $event, $contact_id));

    return true;
}

/**
 * Removes received messages older than two years; run by the general job.
 *
 * @param int $now
 */
function pg_whatsapp_inbox_sweep($now)
{
    if (pg_whatsapp_inbox_ready()) {
        db("DELETE FROM whatsapp_inbox WHERE received_at < '" . ((int) $now - PG_WHATSAPP_INBOX_KEEP) . "' LIMIT 1000");
    }
}

/**
 * A received message as the bell shows it: who sent it, the contact it is
 * tied to, and the text; read from its row while it is kept.
 *
 * @param int $inbox_id
 * @return array|null contact_id, from_number, profile_name
 */
function pg_whatsapp_inbox_row($inbox_id)
{
    // The bell asks once to decide who sees a row and again to draw it.
    static $rows = array();

    $inbox_id = (int) $inbox_id;

    if (($inbox_id <= 0) || !pg_whatsapp_inbox_ready()) {
        return null;
    }

    if (!array_key_exists($inbox_id, $rows)) {
        $row = db_item("SELECT contact_id, from_number, profile_name FROM whatsapp_inbox WHERE id = '" . $inbox_id . "' LIMIT 1");
        $rows[$inbox_id] = is_array($row) ? $row : null;
    }

    return $rows[$inbox_id];
}

/**
 * A contact's received WhatsApp messages below the history page's start,
 * newest first.
 *
 * @param int $contact_id
 * @param int $before 0 for none
 * @param int $limit
 * @return array[] id, received_at, contact_id, from_number, profile_name, type, body
 */
function pg_whatsapp_inbox_contact_rows($contact_id, $before, $limit)
{
    if (((int) $contact_id <= 0) || !pg_whatsapp_inbox_ready()) {
        return array();
    }

    return (array) db_items("SELECT id, received_at, contact_id, from_number, profile_name, type, body FROM whatsapp_inbox
        WHERE contact_id = '" . (int) $contact_id . "'" . (((int) $before > 0) ? " AND received_at < '" . (int) $before . "'" : '') . "
        ORDER BY received_at DESC, id DESC
        LIMIT " . max(1, (int) $limit));
}
