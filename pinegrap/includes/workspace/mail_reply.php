<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: answering an e-mail conversation by e-mail. The answer leaves
 * from the mailbox's own address, through the box's SMTP server or, when
 * the box's domain is the site's own mail domain, through the site's mail
 * settings. It carries the threading headers the customer's mail program
 * needs to put it under their mail, and the "[#T<id>]" tag that finds the
 * conversation again when a program drops them. Once sent it is kept as an
 * outgoing item and drawn under the conversation's card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest answer, in characters. */
define('WS_MAIL_REPLY_MAX', 50000);

/** The most Message-IDs carried in References. */
define('WS_MAIL_REPLY_REFERENCES', 20);

/** The most lines of the answered mail quoted under the answer. */
define('WS_MAIL_REPLY_QUOTE_LINES', 60);

/**
 * The Message-ID of an answer, without angle brackets: the conversation
 * it belongs to, a random part, and the site's host (the box's domain when
 * the site has no usable host name).
 *
 * @param int    $conversation_id
 * @param string $random
 * @param string $host
 * @return string
 */
function ws_mail_reply_message_id($conversation_id, $random, $host)
{
    $random = preg_replace('/[^a-z0-9]/', '', strtolower((string) $random));
    $host = preg_replace('/[^a-z0-9.\-]/', '', strtolower((string) $host));

    return 'pg.c' . (int) $conversation_id . '.' . $random . '@' . (($host !== '') ? $host : 'localhost');
}

/**
 * The subject of an answer: "Re: " unless the subject already answers
 * something, and the conversation's tag unless it already carries one.
 *
 * @param string $subject
 * @param int    $conversation_id
 * @return string
 */
function ws_mail_reply_subject($subject, $conversation_id)
{
    $subject = trim(preg_replace('/\s+/u', ' ', (string) $subject));

    if (!preg_match('/^(re|aw|sv|vs|ynt|antw|r)\s*(\[\d+\])?\s*:/iu', $subject)) {
        $subject = trim('Re: ' . $subject);
    }

    if (ws_mail_subject_tag($subject) !== (int) $conversation_id) {
        $subject .= ' [#T' . (int) $conversation_id . ']';
    }

    return mb_substr(rtrim($subject), 0, 255);
}

/**
 * The threading headers of an answer to a mail: In-Reply-To names the mail,
 * References carries the mail's own references and the mail, the oldest
 * dropped past WS_MAIL_REPLY_REFERENCES.
 *
 * @param array|null $answered ws_mail_items row (rfc_message_id, reference_ids), null when nothing is answered
 * @param string     $message_id the answer's own, without angle brackets
 * @return array Message-ID, and In-Reply-To and References when something is answered
 */
function ws_mail_reply_headers($answered, $message_id)
{
    $headers = array('Message-ID' => '<' . $message_id . '>');
    $answered_id = is_array($answered) ? trim((string) $answered['rfc_message_id']) : '';

    if ($answered_id === '') {
        return $headers;
    }

    $references = preg_split('/\s+/', trim((string) $answered['reference_ids']), -1, PREG_SPLIT_NO_EMPTY);
    $references[] = $answered_id;
    $references = array_slice(array_values(array_unique(array_map(function ($id) {
        return trim($id, '<>');
    }, $references))), -WS_MAIL_REPLY_REFERENCES);

    $headers['In-Reply-To'] = '<' . $answered_id . '>';
    $headers['References'] = '<' . implode('> <', $references) . '>';

    return $headers;
}

/**
 * The text of an answer: what was written, the signature with the writer's
 * name and the mailbox, then the answered mail quoted.
 *
 * @param string     $text
 * @param string     $writer
 * @param string     $box_name
 * @param array|null $answered ws_mail_items row (from_name, from_email, sent_at, created_at, body_text)
 * @return string
 */
function ws_mail_reply_body($text, $writer, $box_name, $answered)
{
    $text = rtrim(str_replace(array("\r\n", "\r"), "\n", (string) $text));
    $signature = array_filter(array(trim((string) $writer), trim((string) $box_name)), 'strlen');
    $body = $text . ($signature ? "\n\n-- \n" . implode("\n", $signature) : '');

    if (!is_array($answered) || (trim((string) $answered['body_text']) === '')) {
        return $body;
    }

    $when = ((int) $answered['sent_at'] > 0) ? (int) $answered['sent_at'] : (int) $answered['created_at'];
    $who = (trim((string) $answered['from_name']) !== '')
        ? trim((string) $answered['from_name']) . ' <' . $answered['from_email'] . '>'
        : (string) $answered['from_email'];
    $lines = explode("\n", str_replace(array("\r\n", "\r"), "\n", trim((string) $answered['body_text'])));
    $quoted = array();

    foreach (array_slice($lines, 0, WS_MAIL_REPLY_QUOTE_LINES) as $line) {
        $quoted[] = rtrim('> ' . $line);
    }

    if (count($lines) > WS_MAIL_REPLY_QUOTE_LINES) {
        $quoted[] = '> …';
    }

    return $body . "\n\n" . lang(array('string' => 'On {var:1}, {var:2} wrote:', 'vars' => array(date('d.m.Y H:i', $when), $who)))
        . "\n" . implode("\n", $quoted);
}

/**
 * How a box sends: its own SMTP server (the box's sign-in, a server and
 * port of its own), or the site's mail settings only when the box's address
 * is on the site's mail domain, since a receiver checks the From domain
 * against the sending server (SPF, DKIM, DMARC). A connected account waits
 * for its own step.
 *
 * @param array  $box         ws_mail_boxes row
 * @param string $site_domain the domain the site signs its mail for
 * @return array ok, error (no_smtp | domain | connection), smtp (email()'s 'smtp', null for the site's settings),
 *               connection (email()'s 'connection', the account answers leave through; only when sent so)
 */
function ws_mail_reply_route($box, $site_domain)
{
    $settings = ws_mail_box_settings($box);
    $via = (string) ($box['reply_via'] ?? 'box');

    if ($via === 'site') {
        $domain = strtolower((string) substr(strrchr((string) $box['address'], '@'), 1));

        return (($domain !== '') && ($domain === strtolower(trim((string) $site_domain))))
            ? array('ok' => true, 'error' => '', 'smtp' => null)
            : array('ok' => false, 'error' => 'domain', 'smtp' => null);
    }

    // The box's connected account (purpose mail_read): email() hands the
    // finished message to the provider, as for the site's own mail.
    if ($via === 'connection') {
        $connection_id = (int) ($settings['connection_id'] ?? 0);

        return ($connection_id > 0)
            ? array('ok' => true, 'error' => '', 'smtp' => null, 'connection' => $connection_id)
            : array('ok' => false, 'error' => 'connection', 'smtp' => null);
    }

    if ($via !== 'box') {
        return array('ok' => false, 'error' => 'connection', 'smtp' => null);
    }

    if (((string) ($settings['smtp_host'] ?? '') === '') || ((string) ($settings['user'] ?? '') === '') || empty($settings['secret'])) {
        return array('ok' => false, 'error' => 'no_smtp', 'smtp' => null);
    }

    return array('ok' => true, 'error' => '', 'smtp' => array(
        'host'   => (string) $settings['smtp_host'],
        'port'   => (int) ($settings['smtp_port'] ?? 587),
        'user'   => (string) $settings['user'],
        'secret' => (string) $settings['secret'],
    ));
}

/**
 * The sending services that keep what they send in the account's own sent
 * folder: a copy added by the site would show twice.
 *
 * @return string[] domains; a sending server on one of them or below it
 */
function ws_mail_reply_keeps_sent()
{
    return array('gmail.com', 'googlemail.com', 'office365.com', 'outlook.com');
}

/**
 * Whether an answer that has left is copied into the box's sent folder, so
 * the mailbox shows it to a person who reads it in a mail program too: only
 * a box read over IMAP (the copy signs in with its user and password), with
 * the copy not switched off, and not through a service that keeps its own.
 *
 * @param array $box   ws_mail_boxes row
 * @param array $route ws_mail_reply_route()
 * @return array copy (bool), reason (off | no_imap | service), folder (as typed, '' to find it)
 */
function ws_mail_reply_copy_plan($box, $route)
{
    $settings = ws_mail_box_settings($box);
    $skip = function ($reason) {
        return array('copy' => false, 'reason' => $reason, 'folder' => '');
    };

    if (isset($settings['sent_copy']) && empty($settings['sent_copy'])) {
        return $skip('off');
    }

    // The provider files what its API sends in the account's own sent folder.
    if (!empty($route['connection'])) {
        return $skip('service');
    }

    if (((string) ($box['transport'] ?? 'imap') !== 'imap') || ((string) ($settings['host'] ?? '') === '')
        || ((string) ($settings['user'] ?? '') === '') || (empty($settings['secret']) && ((int) ($settings['connection_id'] ?? 0) <= 0))) {
        return $skip('no_imap');
    }

    $smtp_host = !empty($route['smtp']) ? strtolower((string) $route['smtp']['host']) : '';

    foreach (ws_mail_reply_keeps_sent() as $domain) {
        if (($smtp_host === $domain) || (substr($smtp_host, -strlen('.' . $domain)) === '.' . $domain)) {
            return $skip('service');
        }
    }

    return array('copy' => true, 'reason' => '', 'folder' => (string) ($settings['sent_folder'] ?? ''));
}

/**
 * Puts a sent answer into the box's sent folder over IMAP: the folder typed
 * on the box, else the one the server marks as sent (pg_imap_sent_folder()).
 *
 * @param array  $box    ws_mail_boxes row
 * @param string $folder ws_mail_reply_copy_plan()'s folder
 * @param string $mime   the mail as it left (pg_mail_last_sent_mime())
 * @return bool
 */
function ws_mail_reply_copy($box, $folder, $mime)
{
    if ((string) $mime === '') {
        return false;
    }

    if (!function_exists('pg_imap_connect')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/imap_client.php');
    }

    $settings = ws_mail_box_settings($box);
    $credentials = ws_mail_box_credentials($box);

    if (!$credentials['ok']) {
        return false;
    }

    // The writer is still waiting for the answer's result: a short budget.
    $connected = pg_imap_connect((string) $settings['host'], (int) ($settings['port'] ?? 993), array('budget' => 10));

    if (!$connected['ok']) {
        return false;
    }

    $session = $connected['session'];
    $copied = false;

    if (ws_mail_box_login($session, $box, $credentials)) {
        if ((string) $folder !== '') {
            $copied = pg_imap_append($session, (string) $folder, $mime);
        } else {
            $found = pg_imap_sent_folder($session);
            $copied = ($found !== '') && pg_imap_append($session, $found, $mime, '(\\Seen)', true);
        }
    }

    pg_imap_logout($session);

    return $copied;
}

/**
 * Words for a reason an answer cannot leave.
 *
 * @param string $error
 * @return string
 */
function ws_mail_reply_error_text($error)
{
    $texts = array(
        'empty'        => lang('Write the answer first.'),
        'too_long'     => lang(array('string' => 'An answer is at most {var:1} characters.', 'vars' => pg_format_number(WS_MAIL_REPLY_MAX, 0))),
        'no_access'    => lang('You cannot write in this conversation\'s channel.'),
        'no_box'       => lang('The mailbox of this conversation is gone or switched off.'),
        'no_customer'  => lang('This conversation has no address to answer.'),
        'no_smtp'      => lang('Enter the mailbox\'s sending server first.'),
        'domain'       => lang('The site\'s mail settings send only for addresses on the site\'s own mail domain; give the mailbox a sending server.'),
        'connection'   => lang('Choose the connected account answers are sent through.'),
        'not_sent'     => lang('The answer could not be sent.'),
    );

    return $texts[$error] ?? $texts['not_sent'];
}

/**
 * The conversation a message offers to answer by e-mail: only the card of
 * a conversation (not its thread), for a reader who may write in the
 * channel. Whether the box can send is told when the answer is sent.
 *
 * @param array      $row      ws_messages row
 * @param array|null $item     ws_mail_items_map() entry
 * @param bool       $can_post
 * @return int conversation id, 0 for none
 */
function ws_mail_reply_offer($row, $item, $can_post)
{
    $conversation = is_array($item) ? $item['conversation'] : null;

    if (!$can_post || !is_array($conversation) || ((int) $conversation['message_id'] !== (int) $row['id'])) {
        return 0;
    }

    return (int) $conversation['id'];
}

/**
 * The texts of the "Reply by e-mail" drawer (assets/js/workspace_mail.js).
 *
 * @return array key => text
 */
function ws_mail_js_strings()
{
    return array(
        'mail_reply'         => lang('Reply by e-mail'),
        'mail_reply_to'      => ws_js_template('To {var:1}', 1),
        'mail_reply_hint'    => lang('Sent from the mailbox\'s address with your name under it; the customer\'s last e-mail is quoted below. Only the customer receives it.'),
        'mail_reply_send'    => lang('Send e-mail'),
        'mail_reply_sent'    => lang('The e-mail was sent.'),
        'mail_reply_text'    => lang('Answer'),
    );
}

/**
 * Whether this person may send this answer in this conversation. Reads the
 * conversation, its box and its channel; sends nothing.
 *
 * @param array  $viewer
 * @param int    $conversation_id
 * @param string $text
 * @return array ok, error, conversation, box, channel, route
 */
function ws_mail_reply_check($viewer, $conversation_id, $text)
{
    $out = array('ok' => false, 'error' => '', 'conversation' => null, 'box' => null, 'channel' => null, 'route' => null);
    $text = trim((string) $text);

    if ($text === '') {
        return array_merge($out, array('error' => 'empty'));
    }

    if (mb_strlen($text) > WS_MAIL_REPLY_MAX) {
        return array_merge($out, array('error' => 'too_long'));
    }

    $conversation = db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $conversation_id . "'");
    $channel = is_array($conversation) ? ws_channel((int) $conversation['channel_id']) : null;

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        return array_merge($out, array('error' => 'no_access'));
    }

    $box = ws_mail_box((int) $conversation['box_id']);

    if (!$box || ((int) $box['enabled'] !== 1)) {
        return array_merge($out, array('error' => 'no_box'));
    }

    if (!filter_var((string) $conversation['customer_email'], FILTER_VALIDATE_EMAIL)) {
        return array_merge($out, array('error' => 'no_customer'));
    }

    $route = ws_mail_reply_route($box, ws_mail_reply_site_domain());

    if (!$route['ok']) {
        return array_merge($out, array('error' => $route['error']));
    }

    return array('ok' => true, 'error' => '', 'conversation' => $conversation, 'box' => $box, 'channel' => $channel, 'route' => $route);
}

/**
 * The domain the site's own mail is signed for: the DKIM domain when set,
 * else the domain of the site's address.
 *
 * @return string
 */
function ws_mail_reply_site_domain()
{
    if (defined('DKIM_DOMAIN') && ((string) DKIM_DOMAIN !== '')) {
        return strtolower((string) DKIM_DOMAIN);
    }

    return defined('EMAIL_ADDRESS') ? strtolower((string) substr(strrchr((string) EMAIL_ADDRESS, '@'), 1)) : '';
}

/**
 * Sends an answer now (not through the queue: the writer waits for it and
 * is told when it fails), then keeps it as the conversation's outgoing
 * item, draws it under the card and notes the answer's time.
 *
 * @param array  $viewer
 * @param int    $conversation_id
 * @param string $text
 * @return array ok, error, detail (the server's words on not_sent), item_id,
 *               message_id, copy (copied | failed, '' when the box keeps none)
 */
function ws_mail_reply_send($viewer, $conversation_id, $text)
{
    $check = ws_mail_reply_check($viewer, $conversation_id, $text);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => $check['error'], 'detail' => '', 'item_id' => 0, 'message_id' => 0);
    }

    $conversation = $check['conversation'];
    $box = $check['box'];
    $conversation_id = (int) $conversation['id'];
    $now = time();
    $writer = ws_person_name((int) $viewer['id']);

    // The newest mail of the customer is the one answered.
    $answered = db_item("SELECT * FROM ws_mail_items
        WHERE conversation_id = '" . $conversation_id . "' AND direction = 'in'
        ORDER BY id DESC LIMIT 1");
    $answered = is_array($answered) && $answered ? $answered : null;

    $host = pg_mail_hostname();

    if ($host === '') {
        $host = (string) substr(strrchr((string) $box['address'], '@'), 1);
    }

    $message_id = ws_mail_reply_message_id($conversation_id, bin2hex(random_bytes(8)), $host);
    $headers = ws_mail_reply_headers($answered, $message_id);
    $subject = ws_mail_reply_subject($answered ? (string) $answered['subject'] : (string) $conversation['subject'], $conversation_id);
    $body = ws_mail_reply_body(trim((string) $text), $writer, (string) $box['name'], $answered);

    $properties = array(
        'to'                 => (string) $conversation['customer_email'],
        'to_name'            => (string) $conversation['customer_name'],
        'from_name'          => (string) $box['name'],
        'from_email_address' => (string) $box['address'],
        'subject'            => $subject,
        'body'               => $body,
        'headers'            => $headers,
        'notify_sender'      => false,
        'keep_mime'          => true,
    );

    if ($check['route']['smtp']) {
        $properties['smtp'] = $check['route']['smtp'];
    }

    if (!empty($check['route']['connection'])) {
        $properties['connection'] = (int) $check['route']['connection'];
    }

    if (!email($properties)) {
        return array('ok' => false, 'error' => 'not_sent', 'detail' => (string) pg_mail_last_error(), 'item_id' => 0, 'message_id' => 0);
    }

    $mime = (string) pg_mail_last_sent_mime();

    $references = trim(isset($headers['References']) ? str_replace(array('<', '>'), '', $headers['References']) : '');

    db("INSERT INTO ws_mail_items (box_id, conversation_id, direction, rfc_message_id, rfc_message_hash, in_reply_to, reference_ids,
            from_email, from_name, to_list, subject, body_text, auth, user_id, sent_at, created_at)
        VALUES ('" . (int) $box['id'] . "', '" . $conversation_id . "', 'out', '" . e($message_id) . "', '" . e(ws_mail_hash($message_id, '')) . "',
            '" . e($answered ? (string) $answered['rfc_message_id'] : '') . "', '" . e($references) . "',
            '" . e((string) $box['address']) . "', '" . e(mb_substr($writer, 0, 255)) . "', '" . e((string) $conversation['customer_email']) . "',
            '" . e($subject) . "', '" . e(trim((string) $text)) . "', 'pass', '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

    $item_id = (int) mysqli_insert_id(db::$con);

    // Copied last: the record of the answer never waits on the mailbox.
    $copy = function () use ($box, $check, $mime) {
        $plan = ws_mail_reply_copy_plan($box, $check['route']);

        // No mail kept (a demonstration site sends none): nothing to copy.
        if (!$plan['copy'] || ($mime === '')) {
            return '';
        }

        return ws_mail_reply_copy($box, $plan['folder'], $mime) ? 'copied' : 'failed';
    };

    // The mail has left; a lost row only costs the record of it.
    if ($item_id <= 0) {
        return array('ok' => true, 'error' => '', 'detail' => '', 'item_id' => 0, 'message_id' => 0, 'copy' => $copy());
    }

    $card = ws_message((int) $conversation['message_id']);
    $parent_id = ($card && ((int) $card['channel_id'] === (int) $check['channel']['id']) && ((int) $card['deleted_at'] === 0)) ? (int) $card['id'] : 0;
    $message = ws_mail_message_write($check['channel']['id'], $parent_id, $item_id, ws_mail_message_body(trim((string) $text)), 0, '', $now);

    if ($parent_id > 0) {
        ws_message_touch($parent_id);
    }

    db("UPDATE ws_mail_items SET message_id = '" . (int) $message . "' WHERE id = '" . $item_id . "'");
    // Answered: the conversation waits for the customer, whose next mail opens it again.
    db("UPDATE ws_mail_conversations SET last_out_at = '" . $now . "', status = 'waiting', closed_at = 0,
            first_reply_at = IF(first_reply_at = 0, '" . $now . "', first_reply_at), updated_at = '" . $now . "'
        WHERE id = '" . $conversation_id . "'");

    return array('ok' => true, 'error' => '', 'detail' => '', 'item_id' => $item_id, 'message_id' => (int) $message, 'copy' => $copy());
}
