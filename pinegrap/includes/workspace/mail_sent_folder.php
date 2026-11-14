<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a mailbox's Sent folder read by the mail job.
 *
 * A box with "Read the Sent folder" switched on (settings.read_sent) has its
 * Sent folder read after its own folder, under the same lock and session
 * (ws_mail_fetch_box()): the folder named on the box, else the one the
 * server marks as sent. The folder keeps a position of its own
 * (ws_mail_boxes.sent_uidvalidity, sent_last_uid); a first read, or a
 * renumbered folder, starts at the newest mail and brings nothing older in.
 *
 * A mail found there that answers one of the box's conversations (by its
 * threading headers, else the conversation's "[#T123]" tag with the
 * customer among its recipients) is kept as an outgoing item with
 * send_state 'external' and no writer, drawn under the card like a sent
 * answer. It never enters the mail queue, so it is never sent again. A mail
 * already in the box (the Workspace's own answers, copied there or kept by
 * the sending service) is known by its Message-ID and skipped. A mail that
 * answers no conversation is passed over. Its files stay in the .eml.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Mails downloaded from a box's Sent folder in one run. */
define('WS_MAIL_SENT_MAX', 10);

/** Seconds of the box's IMAP budget that must be left to start the Sent folder. */
define('WS_MAIL_SENT_LEFT', 5);

/**
 * Whether the 2026.11.0 step that keeps the Sent folder's position ran.
 *
 * @return bool
 */
function ws_mail_sent_ready()
{
    return pg_schema_has('ws_mail_boxes', 'sent_last_uid');
}

/**
 * Whether a box's Sent folder is read this run: switched on, the column is
 * there and enough of the session's time is left. Pure but for the clock
 * the caller passes.
 *
 * @param array $settings ws_mail_box_settings()
 * @param bool  $ready    ws_mail_sent_ready()
 * @param float $left     seconds left of the session's budget
 * @return bool
 */
function ws_mail_sent_due($settings, $ready, $left)
{
    return !empty($settings['read_sent']) && $ready && ((float) $left >= WS_MAIL_SENT_LEFT);
}

/**
 * Where the Sent folder's read starts. A first read, or a folder whose
 * UIDVALIDITY changed, reads nothing and takes the newest mail as its
 * position; otherwise every mail past the last UID. Pure.
 *
 * @param array $box      sent_uidvalidity, sent_last_uid
 * @param array $selected pg_imap_select(): uidvalidity, uidnext
 * @return array reset (bool), criteria ('' when nothing is searched), last_uid (the position on a reset)
 */
function ws_mail_sent_plan($box, $selected)
{
    $known = (int) ($box['sent_uidvalidity'] ?? 0);

    if (($known === 0) || ($known !== (int) $selected['uidvalidity'])) {
        return array('reset' => true, 'criteria' => '', 'last_uid' => max(0, (int) $selected['uidnext'] - 1));
    }

    return array('reset' => false, 'criteria' => 'UID ' . ((int) ($box['sent_last_uid'] ?? 0) + 1) . ':*', 'last_uid' => (int) ($box['sent_last_uid'] ?? 0));
}

/**
 * What a sent mail changes on its conversation, without writing it: it is
 * an answer, so the newest answer's time, the first answer's time when
 * there was none, and "waiting" when it is newer than the customer's last
 * mail; a closed conversation stays closed. Pure.
 *
 * @param array $conversation ws_mail_conversations row
 * @param int   $sent_at      the mail's Date, 0 when it had none
 * @param int   $now
 * @return array column => value
 */
function ws_mail_sent_conversation_changes($conversation, $sent_at, $now)
{
    $at = (((int) $sent_at > 0) && ((int) $sent_at <= (int) $now)) ? (int) $sent_at : (int) $now;
    $set = array('last_out_at' => max((int) $conversation['last_out_at'], $at));

    if ((int) $conversation['first_reply_at'] === 0) {
        $set['first_reply_at'] = $at;
    }

    if (((string) $conversation['status'] === 'open') && ($at >= (int) $conversation['last_in_at'])) {
        $set['status'] = 'waiting';
    }

    $set['updated_at'] = (int) $now;

    return $set;
}

/**
 * Whether a conversation's customer is among a sent mail's recipients
 * (To, Cc, Bcc). Pure.
 *
 * @param string $customer_email
 * @param array  $parsed pg_mail_parse()
 * @return bool
 */
function ws_mail_sent_to_customer($customer_email, $parsed)
{
    $customer = strtolower(trim((string) $customer_email));

    if ($customer === '') {
        return false;
    }

    foreach (array('to', 'cc', 'bcc') as $field) {
        foreach ((array) ($parsed[$field] ?? array()) as $address) {
            if (strtolower(trim((string) ($address['email'] ?? ''))) === $customer) {
                return true;
            }
        }
    }

    return false;
}

/**
 * The box's conversation a sent mail answers: one of the mails it names in
 * In-Reply-To or References, else the conversation its subject's tag names
 * when its customer is among the recipients; null for none.
 *
 * @param array $box
 * @param array $parsed pg_mail_parse()
 * @return array|null ws_mail_conversations row
 */
function ws_mail_sent_conversation($box, $parsed)
{
    $hashes = array();

    foreach (ws_mail_thread_ids($parsed) as $id) {
        $hashes[] = "'" . e(ws_mail_hash($id, '')) . "'";
    }

    if ($hashes) {
        $conversation_id = (int) db_value("SELECT conversation_id FROM ws_mail_items
            WHERE box_id = '" . (int) $box['id'] . "' AND rfc_message_hash IN (" . implode(',', $hashes) . ") AND conversation_id > 0
            ORDER BY id DESC LIMIT 1");

        if ($conversation_id > 0) {
            $row = db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . $conversation_id . "' AND box_id = '" . (int) $box['id'] . "'");

            if (is_array($row) && $row) {
                return $row;
            }
        }
    }

    $tag = ws_mail_subject_tag((string) ($parsed['subject'] ?? ''));

    if ($tag > 0) {
        $row = db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . $tag . "' AND box_id = '" . (int) $box['id'] . "'");

        if (is_array($row) && $row && ws_mail_sent_to_customer((string) $row['customer_email'], $parsed)) {
            return $row;
        }
    }

    return null;
}

/**
 * Keeps one mail of the box's Sent folder under its conversation.
 *
 * @param string $raw
 * @param array  $box ws_mail_boxes row
 * @return array status (stored | duplicate | no_conversation | refused),
 *               reason (refused: too_large | no_channel | not_saved), item_id
 */
function ws_mail_sent_store($raw, $box)
{
    $result = array('status' => 'refused', 'reason' => '', 'item_id' => 0);
    $raw = (string) $raw;
    $box_id = (int) $box['id'];
    $parsed = pg_mail_parse($raw, array('with_content' => true));

    if (in_array('too_large', $parsed['errors'], true)) {
        return array_merge($result, array('reason' => 'too_large'));
    }

    $hash = ws_mail_hash($parsed['message_id'], $raw);
    $existing = (int) db_value("SELECT id FROM ws_mail_items WHERE box_id = '" . $box_id . "' AND rfc_message_hash = '" . e($hash) . "'");

    if ($existing > 0) {
        return array_merge($result, array('status' => 'duplicate', 'item_id' => $existing));
    }

    $conversation = ws_mail_sent_conversation($box, $parsed);

    if (!$conversation) {
        return array_merge($result, array('status' => 'no_conversation'));
    }

    $channel = ws_channel((int) $conversation['channel_id']);

    if (!$channel || ((int) $channel['archived_at'] > 0)) {
        return array_merge($result, array('reason' => 'no_channel'));
    }

    $now = time();
    $list = function ($addresses) {
        return implode(', ', array_map(function ($address) {
            return (string) $address['email'];
        }, (array) $addresses));
    };
    $text = ws_mail_text($parsed);
    $html = ws_mail_html_ready() ? pg_mail_html_clean((string) ($parsed['html'] ?? '')) : '';
    $from_email = mb_substr(strtolower(trim((string) ($parsed['from']['email'] ?? ''))), 0, 255);

    $written = db("INSERT INTO ws_mail_items (box_id, conversation_id, direction, rfc_message_id, rfc_message_hash, in_reply_to, reference_ids,
            from_email, from_name, to_list, cc_list, bcc_list, subject, body_text, auth, user_id, send_state, sent_at, created_at)
        VALUES ('" . $box_id . "', '" . (int) $conversation['id'] . "', 'out', '" . e(mb_substr((string) $parsed['message_id'], 0, 255)) . "', '" . e($hash) . "',
            '" . e(mb_substr((string) ($parsed['in_reply_to'][0] ?? ''), 0, 255)) . "', '" . e(implode(' ', (array) $parsed['references'])) . "',
            '" . e(($from_email !== '') ? $from_email : (string) $box['address']) . "', '" . e(mb_substr((string) ($parsed['from']['name'] ?? ''), 0, 255)) . "',
            '" . e($list($parsed['to'])) . "', '" . e($list($parsed['cc'])) . "', '" . e($list($parsed['bcc'] ?? array())) . "',
            '" . e(mb_substr((string) $parsed['subject'], 0, 255)) . "', '" . e($text) . "', 'pass', 0, 'external',
            '" . max(0, (int) $parsed['date']) . "', '" . $now . "')");

    $item_id = $written ? (int) mysqli_insert_id(db::$con) : 0;

    if ($item_id <= 0) {
        return array_merge($result, array('reason' => 'not_saved'));
    }

    $card = ws_message((int) $conversation['message_id']);
    $parent_id = ($card && ((int) $card['channel_id'] === (int) $channel['id']) && ((int) $card['deleted_at'] === 0)) ? (int) $card['id'] : 0;
    $message_id = ws_mail_message_write($channel['id'], $parent_id, $item_id, ws_mail_message_body($text), 0, '', $now);
    $raw_path = ws_mail_eml_store(ws_mail_eml_path($box_id, $hash, $now), $raw);

    db("UPDATE ws_mail_items SET message_id = '" . (int) $message_id . "', raw_path = '" . e($raw_path) . "'"
        . (($html !== '') ? ", body_html = '" . e($html) . "'" : '') . " WHERE id = '" . $item_id . "'");

    $sets = array();

    foreach (ws_mail_sent_conversation_changes($conversation, (int) $parsed['date'], $now) as $column => $value) {
        $sets[] = $column . " = '" . e((string) $value) . "'";
    }

    db("UPDATE ws_mail_conversations SET " . implode(', ', $sets) . " WHERE id = '" . (int) $conversation['id'] . "'");

    if ($parent_id > 0) {
        ws_message_touch($parent_id);
    }

    return array_merge($result, array('status' => 'stored', 'item_id' => $item_id));
}

/**
 * Reads the box's Sent folder in an open session, after its own folder.
 * Opened read-only: nothing there is marked or moved. A failure leaves the
 * position as it was and never fails the box's own read.
 *
 * @param array $session pg_imap_connect()'s, signed in
 * @param array $box
 * @param array $settings ws_mail_box_settings()
 * @return array|null sent_uidvalidity, sent_last_uid, sent_stored; null when nothing moved
 */
function ws_mail_sent_read(&$session, $box, $settings)
{
    $named = (string) ($settings['sent_folder'] ?? '');
    $folder = ($named !== '') ? $named : pg_imap_sent_folder($session);

    if (($folder === '') || ($session['error'] !== '')) {
        return null;
    }

    $selected = pg_imap_select($session, $folder, true, $named === '');

    if (!$selected['ok'] || ($selected['uidvalidity'] <= 0)) {
        return null;
    }

    $plan = ws_mail_sent_plan($box, $selected);

    // A start at the present needs the server's UIDNEXT: without it the
    // position would be 0 and the next run would read the whole folder.
    if ($plan['reset'] && ((int) $selected['uidnext'] <= 0)) {
        return null;
    }

    $out = array('sent_uidvalidity' => (int) $selected['uidvalidity'], 'sent_last_uid' => $plan['last_uid'], 'sent_stored' => 0);

    if ($plan['reset']) {
        return $out;
    }

    $found = pg_imap_uid_search($session, $plan['criteria']);

    if ($found === null) {
        return null;
    }

    $candidates = ws_mail_fetch_candidates($found, false, $plan['last_uid']);
    $outcomes = array_fill_keys($candidates, 'waiting');

    if ($candidates) {
        $heads = pg_imap_uid_fetch($session, $candidates, '(UID RFC822.SIZE BODY.PEEK[HEADER.FIELDS (MESSAGE-ID)])');

        if ($heads === null) {
            return null;
        }

        $hashes = array();

        foreach ($heads as $uid => $head) {
            $message_id = ws_mail_fetch_message_id((string) pg_imap_fetch_section($head, 'BODY[HEADER'));

            if ($message_id !== '') {
                $hashes[$uid] = ws_mail_hash($message_id, '');
            }
        }

        $known = array();

        if ($hashes) {
            foreach ((array) db_items("SELECT rfc_message_hash FROM ws_mail_items WHERE box_id = '" . (int) $box['id'] . "'
                AND rfc_message_hash IN ('" . implode("','", array_map('e', array_values($hashes))) . "')") as $row) {
                $known[(string) $row['rfc_message_hash']] = true;
            }
        }

        $downloads = 0;

        foreach ($candidates as $uid) {
            if (!isset($heads[$uid]) || isset($hashes[$uid], $known[$hashes[$uid]])
                || ((int) ($heads[$uid]['RFC822.SIZE'] ?? 0) > WS_MAIL_FETCH_MAX_BYTES)) {
                $outcomes[$uid] = 'done';
                continue;
            }

            if ($downloads >= WS_MAIL_SENT_MAX) {
                break;
            }

            $body = pg_imap_uid_fetch($session, array($uid), '(UID BODY.PEEK[])');
            $downloads++;

            if (($body === null) || !isset($body[$uid])) {
                break;
            }

            $stored = ws_mail_sent_store((string) pg_imap_fetch_section($body[$uid], 'BODY[]'), $box);

            // Nothing could be written: the position stays before this mail.
            if (($stored['status'] === 'refused') && ($stored['reason'] === 'not_saved')) {
                break;
            }

            $outcomes[$uid] = 'done';
            $out['sent_stored'] += ($stored['status'] === 'stored') ? 1 : 0;
        }
    }

    $out['sent_last_uid'] = ws_mail_fetch_cursor($plan['last_uid'], false, $selected['uidnext'], $outcomes);

    return $out;
}
