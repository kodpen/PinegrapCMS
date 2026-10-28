<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - incoming e-mail: one raw mail of a mailbox into its channel.
 *
 * ws_mail_ingest() takes a raw RFC 5322 message (from an IMAP read, a push
 * endpoint or an .eml upload), keeps it once per box, holds back what would
 * start a mail loop, finds the conversation it answers, and writes it into
 * the channel: a new conversation as a card message, an answer under that
 * card. Its attachments go into the channel's folder under the channel's
 * own file rules, and the raw mail is kept outside the web root for
 * WS_MAIL_EML_DAYS days. The parser is includes/fn/mail_parse.php; the
 * tables are described in docs/memory/workspace/mail-tables.md.
 *
 * Mail text is untrusted: it is kept as plain text, tags that would read as
 * Workspace references are taken apart, and nothing in it is followed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Days the original .eml of a mail is kept. */
define('WS_MAIL_EML_DAYS', 30);

/** Mails one sender may bring into one box in an hour; past it they are held. */
define('WS_MAIL_SENDER_HOURLY', 20);

/** Attachments of one mail taken into the channel; the rest are listed by name. */
define('WS_MAIL_ATTACHMENTS_MAX', 20);

/** The header every mail the site sends carries, so it is never read back in. */
define('WS_MAIL_LOOP_HEADER', 'x-pinegrap-mail');

/** The longest mail text kept, in bytes. */
define('WS_MAIL_TEXT_MAX', 1048576);

/**
 * One mailbox row.
 *
 * @param int $box_id
 * @return array|null
 */
function ws_mail_box($box_id)
{
    $row = db_item("SELECT * FROM ws_mail_boxes WHERE id = '" . (int) $box_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The key a mail is kept once under in its box: the SHA-256 of its
 * Message-ID, or of the whole raw mail when it has none.
 *
 * @param string $message_id without angle brackets
 * @param string $raw
 * @return string 64 hex characters
 */
function ws_mail_hash($message_id, $raw)
{
    $message_id = trim((string) $message_id);

    return hash('sha256', ($message_id !== '') ? 'id:' . $message_id : 'raw:' . (string) $raw);
}

/**
 * Why a mail must not come in, from its headers: our own mail (this site's
 * mark in X-Pinegrap-Mail, or the box's own address as sender), an automatic one
 * (Auto-Submitted other than "no") or a list or bulk one (Precedence). Any
 * of them answered by the site could start a loop of answers.
 *
 * @param array  $parsed      pg_mail_parse()
 * @param string $box_address
 * @return string '' to let it in, else own | auto | bulk
 */
function ws_mail_loop_reason($parsed, $box_address)
{
    $headers = (array) ($parsed['headers'] ?? array());
    $first = function ($name) use ($headers) {
        return isset($headers[$name][0]) ? strtolower(trim((string) $headers[$name][0])) : '';
    };

    if ($first(WS_MAIL_LOOP_HEADER) === pg_mail_site_mark()) {
        return 'own';
    }

    $from = strtolower(trim((string) ($parsed['from']['email'] ?? '')));

    if (($from !== '') && ($from === strtolower(trim((string) $box_address)))) {
        return 'own';
    }

    // "auto-replied; owner-email=..." carries parameters after the keyword.
    $auto = trim((string) strtok($first('auto-submitted'), ';'));

    if (($auto !== '') && ($auto !== 'no')) {
        return 'auto';
    }

    if (in_array($first('precedence'), array('bulk', 'junk', 'list'), true)) {
        return 'bulk';
    }

    return '';
}

/**
 * Whether the sender's address was checked by the box's receiving server:
 * the DMARC result of an Authentication-Results header written by that
 * server. RFC 8601 has a server remove headers that claim its name, so only
 * one carrying the authserv-id the box names is believed; a box that names
 * none trusts no header, and its mail stays "none".
 *
 * @param array  $headers     pg_mail_parse()'s headers
 * @param string $auth_server the authserv-id, such as mx.google.com
 * @return string pass | fail | none
 */
function ws_mail_auth($headers, $auth_server)
{
    $auth_server = strtolower(trim((string) $auth_server));

    if ($auth_server === '') {
        return 'none';
    }

    foreach ((array) ($headers['authentication-results'] ?? array()) as $value) {
        $parts = explode(';', (string) $value, 2);
        $server = strtolower((string) strtok(trim($parts[0]), " \t"));

        if ($server !== $auth_server) {
            continue;
        }

        if (preg_match('/(?:^|[\s;])dmarc\s*=\s*(pass|fail)\b/i', (string) ($parts[1] ?? ''), $match)) {
            return (strtolower($match[1]) === 'pass') ? 'pass' : 'fail';
        }

        return 'none';
    }

    return 'none';
}

/**
 * The Message-IDs a mail answers, the closest first: In-Reply-To, then
 * References from the newest back. At most 30.
 *
 * @param array $parsed pg_mail_parse()
 * @return string[]
 */
function ws_mail_thread_ids($parsed)
{
    $ids = array_merge(
        (array) ($parsed['in_reply_to'] ?? array()),
        array_reverse((array) ($parsed['references'] ?? array()))
    );

    $ids = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $ids)), 'strlen')));

    return array_slice($ids, 0, 30);
}

/**
 * The conversation number of a "[#T123]" tag in a subject, 0 when there is
 * none. A mail client that drops the threading headers keeps the subject.
 *
 * @param string $subject
 * @return int
 */
function ws_mail_subject_tag($subject)
{
    return preg_match('/\[#T([0-9]{1,10})\]/', (string) $subject, $match) ? (int) $match[1] : 0;
}

/**
 * Plain text from a mail's HTML body: scripts, styles and the head dropped,
 * blocks and line breaks as new lines, list items with a dash, the
 * entities read. Never rendered, so the text only needs to read well.
 *
 * @param string $html
 * @return string
 */
function ws_mail_html_text($html)
{
    $html = preg_replace('#<(script|style|head|title)\b[^>]*>.*?</\1\s*>#is', '', (string) $html);
    $html = preg_replace('#<br\b[^>]*>#i', "\n", (string) $html);
    $html = preg_replace('#<li\b[^>]*>#i', "\n- ", (string) $html);
    $html = preg_replace('#</(p|div|tr|h[1-6]|blockquote|table|ul|ol)\s*>#i', "\n", (string) $html);

    return ws_mail_tidy_text(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/**
 * Mail text made even: one kind of line break, no spaces at line ends, no
 * more than one empty line in a row, no control characters but tab and
 * newline, cut to WS_MAIL_TEXT_MAX bytes on a character boundary.
 *
 * @param string $text
 * @return string
 */
function ws_mail_tidy_text($text)
{
    $text = str_replace(array("\r\n", "\r", "\xC2\xA0"), array("\n", "\n", ' '), (string) $text);
    $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text);
    $text = preg_replace('/[ \t]+$/m', '', (string) $text);
    $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);
    $text = trim((string) $text);

    if (strlen($text) > WS_MAIL_TEXT_MAX) {
        $text = mb_strcut($text, 0, WS_MAIL_TEXT_MAX, 'UTF-8');
    }

    return $text;
}

/**
 * The text a mail is kept with: its plain body, else its HTML as text.
 *
 * @param array $parsed pg_mail_parse()
 * @return string
 */
function ws_mail_text($parsed)
{
    $text = ws_mail_tidy_text((string) ($parsed['text'] ?? ''));

    return ($text !== '') ? $text : ws_mail_html_text((string) ($parsed['html'] ?? ''));
}

/**
 * What a mail's message in the channel holds: its text with every
 * "<@user:1>" or "<#task:2>" a sender could type taken out of its brackets
 * (so it names nothing and tells nobody), cut to WS_MESSAGE_MAX characters.
 * The whole text stays in ws_mail_items.
 *
 * @param string $text
 * @return string
 */
function ws_mail_message_body($text)
{
    $text = preg_replace('/<([@#][a-z_]+:[0-9]+)>/i', '$1', (string) $text);
    $max = defined('WS_MESSAGE_MAX') ? WS_MESSAGE_MAX : 4000;

    return (mb_strlen((string) $text) > $max) ? rtrim(mb_substr((string) $text, 0, $max - 1)) . '…' : (string) $text;
}

/**
 * Which of a mail's attachments come into the channel. Inline pictures
 * without a name (logos of a signature) are left out silently; a part
 * without a name, a name a channel refuses, one larger than a channel
 * takes, and those past WS_MAIL_ATTACHMENTS_MAX are refused by name.
 *
 * @param array    $attachments pg_mail_parse()'s, with content
 * @param callable $refused     function ($name) => bool, the channel's rule
 * @param int      $max_bytes
 * @return array take (the attachments), refused (names)
 */
function ws_mail_attachments_sort($attachments, $refused, $max_bytes)
{
    $take = array();
    $names = array();

    foreach ((array) $attachments as $attachment) {
        $name = (string) ($attachment['filename'] ?? '');

        if ($name === '') {
            if (empty($attachment['inline'])) {
                $names[] = (string) ($attachment['content_type'] ?? 'application/octet-stream');
            }

            continue;
        }

        if (call_user_func($refused, $name) || ((int) ($attachment['size'] ?? 0) > (int) $max_bytes)
            || (count($take) >= WS_MAIL_ATTACHMENTS_MAX) || !isset($attachment['content'])) {
            $names[] = $name;

            continue;
        }

        $take[] = $attachment;
    }

    return array('take' => $take, 'refused' => $names);
}

/**
 * Where a raw mail is kept, relative to the data folder (data/mail is out of
 * the web server's reach like the rest of data/).
 *
 * @param int    $box_id
 * @param string $hash ws_mail_hash()
 * @param int    $time
 * @return string
 */
function ws_mail_eml_path($box_id, $hash, $time)
{
    return 'mail/' . (int) $box_id . '/' . gmdate('Ym', (int) $time) . '/' . preg_replace('/[^0-9a-f]/', '', strtolower((string) $hash)) . '.eml';
}

/**
 * Keeps a raw mail under the data folder; '' when it could not be written
 * (the mail still comes in, only its original is missing).
 *
 * @param string $relative ws_mail_eml_path()
 * @param string $raw
 * @return string the relative path kept, or ''
 */
function ws_mail_eml_store($relative, $raw)
{
    $root = PG_FUNCTIONS_DIR . '/data/mail';
    $path = PG_FUNCTIONS_DIR . '/data/' . $relative;

    if (!is_dir(dirname($path)) && !@mkdir(dirname($path), 0755, true)) {
        return '';
    }

    // data/ is closed to the web server; a rule of its own as well, in case
    // the folder is ever moved or served on its own.
    if (!file_exists($root . '/.htaccess')) {
        @file_put_contents($root . '/.htaccess', "deny from all\n");
    }

    return (@file_put_contents($path, (string) $raw) === false) ? '' : $relative;
}

/**
 * Deletes the raw mails older than WS_MAIL_EML_DAYS days, the oldest first;
 * the mail and its messages stay. Run by workspace_recurring_job.php.
 *
 * @param int $limit
 * @return int how many were deleted
 */
function ws_mail_eml_sweep($limit = 500)
{
    $rows = db_items("SELECT id, raw_path FROM ws_mail_items
        WHERE raw_path <> '' AND created_at < '" . (time() - (WS_MAIL_EML_DAYS * 86400)) . "'
        ORDER BY id
        LIMIT " . max(1, (int) $limit));

    $done = 0;

    foreach ((array) $rows as $row) {
        $relative = (string) $row['raw_path'];

        if ((strpos($relative, 'mail/') === 0) && (strpos($relative, '..') === false)) {
            @unlink(PG_FUNCTIONS_DIR . '/data/' . $relative);
        }

        db("UPDATE ws_mail_items SET raw_path = '' WHERE id = '" . (int) $row['id'] . "'");
        $done++;
    }

    return $done;
}

/**
 * The conversation of a box that a mail answers: the one holding a mail it
 * names by Message-ID (ours or the customer's), else the one its subject tag
 * names when that conversation is with the same sender.
 *
 * @param array  $box
 * @param array  $parsed pg_mail_parse()
 * @param string $from_email
 * @return array|null ws_mail_conversations row
 */
function ws_mail_find_conversation($box, $parsed, $from_email)
{
    $hashes = array();

    foreach (ws_mail_thread_ids($parsed) as $id) {
        $hashes[] = "'" . e(ws_mail_hash($id, '')) . "'";
    }

    $conversation_id = 0;

    if ($hashes) {
        $conversation_id = (int) db_value("SELECT conversation_id FROM ws_mail_items
            WHERE box_id = '" . (int) $box['id'] . "' AND rfc_message_hash IN (" . implode(',', $hashes) . ") AND conversation_id > 0
            ORDER BY id DESC
            LIMIT 1");
    }

    if ($conversation_id > 0) {
        $row = db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . $conversation_id . "' AND box_id = '" . (int) $box['id'] . "'");

        if (is_array($row) && $row) {
            return $row;
        }
    }

    $tag = ws_mail_subject_tag((string) ($parsed['subject'] ?? ''));

    if ($tag > 0) {
        $row = db_item("SELECT * FROM ws_mail_conversations
            WHERE id = '" . $tag . "' AND box_id = '" . (int) $box['id'] . "' AND customer_email = '" . e($from_email) . "'");

        if (is_array($row) && $row) {
            return $row;
        }
    }

    return null;
}

/**
 * A message of a mail in its channel. sender_kind mail names the
 * ws_mail_items row; the panel draws it from there.
 *
 * @param int    $channel_id
 * @param int    $parent_id the conversation's card, 0 for the card itself
 * @param int    $item_id
 * @param string $body
 * @param int    $file_id
 * @param string $file_name
 * @param int    $now
 * @return int the message id, 0 when it was not written
 */
function ws_mail_message_write($channel_id, $parent_id, $item_id, $body, $file_id, $file_name, $now)
{
    db("INSERT INTO ws_messages (channel_id, parent_id, sender_kind, sender_id, kind, body, file_id, file_name, created_at)
        VALUES ('" . (int) $channel_id . "', '" . (int) $parent_id . "', 'mail', '" . (int) $item_id . "', 'message',
            '" . e($body) . "', '" . (int) $file_id . "', '" . e(mb_substr((string) $file_name, 0, 255)) . "', '" . (int) $now . "')");

    $message_id = (int) mysqli_insert_id(db::$con);

    if ($message_id > 0) {
        db("UPDATE ws_channels SET last_message_id = '" . $message_id . "', last_message_at = '" . (int) $now . "'
            WHERE id = '" . (int) $channel_id . "'");
    }

    return $message_id;
}

/**
 * Brings one raw mail into its box's channel.
 *
 * status answers what happened:
 * - stored:    written; item_id, conversation_id and message_id name it.
 * - duplicate: the box already holds this mail (item_id is that one).
 * - loop:      our own, automatic or bulk mail (reason); nothing kept.
 * - held:      the sender passed WS_MAIL_SENDER_HOURLY this hour; nothing
 *              kept, so a reader leaves it in the mailbox for a later run.
 * - refused:   no sender, too large to read, or no channel to write in
 *              (missing or archived; reason); nothing kept.
 *
 * @param string $raw
 * @param array  $box ws_mail_boxes row
 * @return array status, reason, item_id, conversation_id, message_id
 */
function ws_mail_ingest($raw, $box)
{
    $result = array('status' => 'refused', 'reason' => '', 'item_id' => 0, 'conversation_id' => 0, 'message_id' => 0);
    $raw = (string) $raw;
    $box_id = (int) ($box['id'] ?? 0);
    $parsed = pg_mail_parse($raw, array('with_content' => true));

    if (in_array('too_large', $parsed['errors'], true)) {
        return array_merge($result, array('reason' => 'too_large'));
    }

    $from_email = mb_substr(strtolower(trim((string) ($parsed['from']['email'] ?? ''))), 0, 255);

    if ($from_email === '') {
        return array_merge($result, array('reason' => 'no_sender'));
    }

    $hash = ws_mail_hash($parsed['message_id'], $raw);
    $existing = (int) db_value("SELECT id FROM ws_mail_items WHERE box_id = '" . $box_id . "' AND rfc_message_hash = '" . e($hash) . "'");

    if ($existing > 0) {
        return array_merge($result, array('status' => 'duplicate', 'item_id' => $existing));
    }

    $loop = ws_mail_loop_reason($parsed, (string) ($box['address'] ?? ''));

    if ($loop !== '') {
        return array_merge($result, array('status' => 'loop', 'reason' => $loop));
    }

    $now = time();

    if ((int) db_value("SELECT COUNT(*) FROM ws_mail_items
        WHERE box_id = '" . $box_id . "' AND from_email = '" . e($from_email) . "' AND created_at > '" . ($now - 3600) . "'") >= WS_MAIL_SENDER_HOURLY) {
        return array_merge($result, array('status' => 'held'));
    }

    $settings = json_decode((string) ($box['settings'] ?? ''), true);
    $auth = ws_mail_auth($parsed['headers'], is_array($settings) ? (string) ($settings['auth_server'] ?? '') : '');
    $conversation = ws_mail_find_conversation($box, $parsed, $from_email);
    $channel = ws_channel($conversation ? (int) $conversation['channel_id'] : (int) ($box['channel_id'] ?? 0));

    if (!$channel || ((int) $channel['archived_at'] > 0)) {
        return array_merge($result, array('reason' => 'no_channel'));
    }

    $text = ws_mail_text($parsed);
    $subject = mb_substr((string) $parsed['subject'], 0, 255);
    $from_name = mb_substr((string) ($parsed['from']['name'] ?? ''), 0, 255);
    $list = function ($addresses) {
        return implode(', ', array_map(function ($address) {
            return (string) $address['email'];
        }, (array) $addresses));
    };

    // The row first: its id is the sender of the messages, and the unique key
    // turns a second reader of the same mail away here.
    $written = db("INSERT INTO ws_mail_items (box_id, direction, rfc_message_id, rfc_message_hash, in_reply_to, reference_ids,
            from_email, from_name, to_list, cc_list, subject, body_text, auth, sent_at, created_at)
        VALUES ('" . $box_id . "', 'in', '" . e(mb_substr((string) $parsed['message_id'], 0, 255)) . "', '" . e($hash) . "',
            '" . e(mb_substr((string) ($parsed['in_reply_to'][0] ?? ''), 0, 255)) . "', '" . e(implode(' ', (array) $parsed['references'])) . "',
            '" . e($from_email) . "', '" . e($from_name) . "', '" . e($list($parsed['to'])) . "', '" . e($list($parsed['cc'])) . "',
            '" . e($subject) . "', '" . e($text) . "', '" . e($auth) . "', '" . max(0, (int) $parsed['date']) . "', '" . $now . "')");

    $item_id = $written ? (int) mysqli_insert_id(db::$con) : 0;

    if ($item_id <= 0) {
        $existing = (int) db_value("SELECT id FROM ws_mail_items WHERE box_id = '" . $box_id . "' AND rfc_message_hash = '" . e($hash) . "'");

        return ($existing > 0)
            ? array_merge($result, array('status' => 'duplicate', 'item_id' => $existing))
            : array_merge($result, array('reason' => 'not_saved'));
    }

    $body = ws_mail_message_body($text);

    if ($conversation) {
        $conversation_id = (int) $conversation['id'];
        $card = ws_message((int) $conversation['message_id']);
        $parent_id = ($card && ((int) $card['channel_id'] === (int) $channel['id']) && ((int) $card['deleted_at'] === 0)) ? (int) $card['id'] : 0;
        $message_id = ws_mail_message_write($channel['id'], $parent_id, $item_id, $body, 0, '', $now);

        // A new mail opens a conversation that waited or was closed again.
        db("UPDATE ws_mail_conversations SET last_in_at = '" . $now . "', status = 'open', closed_at = 0, updated_at = '" . $now . "'
            WHERE id = '" . $conversation_id . "'");

        if ($parent_id > 0) {
            ws_message_touch($parent_id);
        } else {
            db("UPDATE ws_mail_conversations SET message_id = '" . $message_id . "' WHERE id = '" . $conversation_id . "'");
            $parent_id = $message_id;
        }
    } else {
        // Only a sender the box's server vouched for is tied to a record.
        $contact_id = ($auth === 'pass') ? (int) db_value("SELECT id FROM contacts WHERE email_address = '" . e($from_email) . "' ORDER BY id DESC LIMIT 1") : 0;
        $user_id = ($auth === 'pass') ? (int) db_value("SELECT user_id FROM user WHERE user_email = '" . e($from_email) . "' ORDER BY user_id LIMIT 1") : 0;

        db("INSERT INTO ws_mail_conversations (box_id, channel_id, subject, customer_email, customer_name, contact_id, user_id,
                status, first_in_at, last_in_at, created_at, updated_at)
            VALUES ('" . $box_id . "', '" . (int) $channel['id'] . "', '" . e($subject) . "', '" . e($from_email) . "', '" . e($from_name) . "',
                '" . $contact_id . "', '" . $user_id . "', 'open', '" . $now . "', '" . $now . "', '" . $now . "', '" . $now . "')");

        $conversation_id = (int) mysqli_insert_id(db::$con);
        $message_id = ws_mail_message_write($channel['id'], 0, $item_id, $body, 0, '', $now);
        $parent_id = $message_id;

        db("UPDATE ws_mail_conversations SET message_id = '" . $message_id . "' WHERE id = '" . $conversation_id . "'");
    }

    // Each attachment is a message of its own under the card, carrying the
    // file the way an upload does.
    $sorted = ws_mail_attachments_sort($parsed['attachments'], 'ws_upload_refused', ws_upload_max_bytes());
    $refused = $sorted['refused'];

    foreach ($sorted['take'] as $attachment) {
        $inspected = ws_upload_inspect((string) $attachment['filename'], (string) $attachment['content']);
        $stored = $inspected['ok']
            ? ws_store_file_bytes($channel, $inspected['name'], $inspected['extension'], (string) $attachment['content'], (int) ($box['created_by'] ?? 0))
            : array('ok' => false);

        if (!$stored['ok']) {
            $refused[] = (string) $attachment['filename'];

            continue;
        }

        if (ws_mail_message_write($channel['id'], $parent_id, $item_id, '', $stored['file_id'], $stored['name'], $now) <= 0) {
            ws_upload_discard($stored['file_id']);
            $refused[] = (string) $attachment['filename'];
        }
    }

    $raw_path = ws_mail_eml_store(ws_mail_eml_path($box_id, $hash, $now), $raw);

    db("UPDATE ws_mail_items SET conversation_id = '" . $conversation_id . "', message_id = '" . (int) $message_id . "',
            raw_path = '" . e($raw_path) . "', refused_files = '" . e(implode("\n", array_slice($refused, 0, 100))) . "'
        WHERE id = '" . $item_id . "'");

    return array_merge($result, array(
        'status'          => 'stored',
        'item_id'         => $item_id,
        'conversation_id' => $conversation_id,
        'message_id'      => (int) $message_id,
    ));
}

/* ---------------------------------------------------------------------------
   The card in the channel (messages.php draws it)
   --------------------------------------------------------------------------- */

/**
 * The mails and conversations a page of messages names: one query each for
 * the whole page.
 *
 * @param array[] $rows ws_messages rows
 * @return array item id => ws_mail_items row, with a 'conversation' key (the
 *               ws_mail_conversations row or null)
 */
function ws_mail_items_map($rows)
{
    $ids = array();

    foreach ((array) $rows as $row) {
        if ((string) $row['sender_kind'] === 'mail') {
            $ids[(int) $row['sender_id']] = (int) $row['sender_id'];
        }
    }

    if (!$ids || !pg_schema_has('ws_mail_items')) {
        return array();
    }

    $items = array();
    $conversation_ids = array();

    foreach ((array) db_items("SELECT * FROM ws_mail_items WHERE id IN (" . implode(',', $ids) . ")") as $item) {
        $items[(int) $item['id']] = $item + array('conversation' => null);
        $conversation_ids[(int) $item['conversation_id']] = (int) $item['conversation_id'];
    }

    $conversation_ids = array_filter($conversation_ids);

    if ($conversation_ids) {
        $conversations = array();

        foreach ((array) db_items("SELECT * FROM ws_mail_conversations WHERE id IN (" . implode(',', $conversation_ids) . ")") as $conversation) {
            $conversations[(int) $conversation['id']] = $conversation;
        }

        foreach ($items as $id => $item) {
            $items[$id]['conversation'] = $conversations[(int) $item['conversation_id']] ?? null;
        }
    }

    return $items;
}

/**
 * Who a mail's message is drawn under: the sender as the mail names them,
 * with letters for a picture, like a guest of a room; an answer the team
 * sent, under its writer.
 *
 * @param array|null $item ws_mail_items row
 * @return array
 */
function ws_mail_sender($item)
{
    // An answer the team sent: its writer, from the mailbox's address.
    if (is_array($item) && ((string) ($item['direction'] ?? 'in') === 'out') && ((int) $item['user_id'] > 0) && function_exists('ws_people')) {
        $people = ws_people(array((int) $item['user_id']));

        if (isset($people[(int) $item['user_id']])) {
            return array_merge($people[(int) $item['user_id']], array('title' => (string) $item['from_email'], 'mail' => true));
        }
    }

    $email = is_array($item) ? (string) $item['from_email'] : '';
    $name = (is_array($item) && (trim((string) $item['from_name']) !== '')) ? (string) $item['from_name'] : (($email !== '') ? $email : lang('E-mail'));
    $seed = 'mail' . md5(strtolower($email));

    return array(
        'id'          => 0,
        'name'        => $name,
        'username'    => '',
        'role'        => 3,
        'title'       => $email,
        'avatar'      => function_exists('pg_initials_avatar_url') ? pg_initials_avatar_url(pg_initials($name, ''), 'contact', $seed) : '',
        'avatar_kind' => 'contact',
        'presence'    => 'offline',
        'mail'        => true,
    ) + (function_exists('pg_avatar_letters') ? pg_avatar_letters(pg_initials($name, ''), $seed) : array());
}

/**
 * What a mail's message shows: whether the sender was verified, the address,
 * the subject on the conversation's card, the text as plain text (never as
 * HTML or Workspace markup), the whole text behind "Show all" when the
 * message holds less, and the attachments that were not taken in. A message
 * that only carries an attachment shows nothing of its own.
 *
 * @param array      $row  ws_messages row
 * @param array|null $item ws_mail_items_map() entry
 * @return string HTML
 */
function ws_mail_card_html($row, $item)
{
    if (!is_array($item)) {
        return '<p class="fg-secondary">' . h(lang('This e-mail is no longer available.')) . '</p>';
    }

    if (((int) $row['file_id'] > 0) && (trim((string) $row['body']) === '')) {
        return '';
    }

    $conversation = $item['conversation'];
    $card = is_array($conversation) && ((int) $conversation['message_id'] === (int) $row['id']);
    $out = ((string) ($item['direction'] ?? 'in') === 'out');
    $badge = $out
        ? '<span class="badge theme-success"><i class="bi bi-send me-1" aria-hidden="true"></i>' . h(lang('Sent by e-mail')) . '</span>'
        : (((string) $item['auth'] === 'pass')
        ? '<span class="badge theme-info"><i class="bi bi-envelope me-1" aria-hidden="true"></i>' . h(lang('E-mail')) . '</span>'
        : '<span class="badge theme-warning"><i class="bi bi-envelope-exclamation me-1" aria-hidden="true"></i>' . h(lang('Sender not verified')) . '</span>');

    // An answer names whom it went to; a mail, who sent it.
    $html = '<div class="ws-mail-card">'
        . '<div class="small fg-secondary mb-1">' . $badge . ' <span class="ms-1">'
        . h($out ? lang(array('string' => 'To {var:1}', 'vars' => (string) $item['to_list'])) : (string) $item['from_email']) . '</span></div>';

    if ($card && ((string) $item['subject'] !== '')) {
        $html .= '<div class="fw-semibold mb-1">' . h((string) $item['subject']) . '</div>';
    }

    $html .= '<div>' . nl2br(h((string) $row['body']), false) . '</div>';

    $full = (string) $item['body_text'];

    if (mb_strlen($full) > mb_strlen((string) $row['body'])) {
        $html .= '<details class="mt-1"><summary class="small">' . h(lang('Show all')) . '</summary><div class="mt-1">'
            . nl2br(h(mb_substr($full, 0, 100000)), false) . '</div></details>';
    }

    $refused = array_filter(explode("\n", (string) $item['refused_files']), 'strlen');

    if ($refused) {
        $html .= '<div class="small fg-secondary mt-1"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>'
            . h(lang(array('string' => 'Not taken in: {var:1}', 'vars' => implode(', ', $refused)))) . '</div>';
    }

    return $html . '</div>';
}
