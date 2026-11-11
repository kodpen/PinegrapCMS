<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: sending an e-mail from a mailbox through the mail queue. A sent
 * mail is a conversation with its card in a channel of the mailbox, so the
 * channel's readers see it. The mail is built as an answer is (the box's
 * route, a Message-ID of the conversation, the subject tag, the site's mark)
 * and handed to the queue with an outcome handler, which marks the outgoing
 * item sent or failed. A mail that could not leave reads "Not sent", tells
 * its writer and can be sent again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most mails a person sends from the Workspace in an hour. */
define('WS_MAIL_SEND_HOURLY', 30);

/** The most addresses of one mail, To, Cc and Bcc together. */
define('WS_MAIL_SEND_ADDRESSES', 20);

/** The most files of one mail. */
define('WS_MAIL_SEND_FILES', 10);

/** The most bytes of one mail's files together. */
define('WS_MAIL_SEND_FILES_BYTES', 20971520);

/** The queue's handler name of a Workspace mail (pg_mail_outcome_handlers()). */
define('WS_MAIL_SEND_HANDLER', 'workspace_mail');

/**
 * Whether the send state is kept: the columns of 11.0.185. Before that
 * step has run nothing is sent or changed from here.
 *
 * @return bool
 */
function ws_mail_send_ready()
{
    return function_exists('ws_mail_ready') && ws_mail_ready() && pg_schema_has('ws_mail_items', 'send_state');
}

/**
 * The addresses of a To, Cc or Bcc field: a list, or text with commas,
 * semicolons or spaces between them; lower case, each once. Pure.
 *
 * @param mixed $value
 * @return string[]
 */
function ws_mail_send_addresses($value)
{
    $parts = is_array($value) ? $value : preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
    $out = array();

    foreach ((array) $parts as $part) {
        $address = strtolower(trim((string) (is_scalar($part) ? $part : ''), " \t\r\n<>\"'"));

        if (($address !== '') && !in_array($address, $out, true)) {
            $out[] = $address;
        }
    }

    return $out;
}

/**
 * What a mail asks to send, checked without the database: the addresses
 * (at least one To, every one valid, WS_MAIL_SEND_ADDRESSES together), the
 * subject, the text (1 to WS_MAIL_REPLY_MAX characters) and the files (the
 * channel's upload rules, WS_MAIL_SEND_FILES and WS_MAIL_SEND_FILES_BYTES).
 *
 * @param array $mail to, cc, bcc, subject, text, files (each name and bytes)
 * @return array ok, error, detail, to, cc, bcc, subject, text, files (name, extension, bytes)
 */
function ws_mail_send_input($mail)
{
    $mail = is_array($mail) ? $mail : array();
    $out = array('ok' => false, 'error' => '', 'detail' => '', 'to' => array(), 'cc' => array(), 'bcc' => array(),
        'subject' => '', 'text' => '', 'files' => array());
    $refuse = function ($error, $detail = '') use ($out) {
        return array_merge($out, array('error' => $error, 'detail' => (string) $detail));
    };

    $to = ws_mail_send_addresses($mail['to'] ?? '');
    // An address given twice is sent once, in its first field.
    $cc = array_values(array_diff(ws_mail_send_addresses($mail['cc'] ?? ''), $to));
    $bcc = array_values(array_diff(ws_mail_send_addresses($mail['bcc'] ?? ''), $to, $cc));

    if (!$to) {
        return $refuse('no_to');
    }

    if ((count($to) + count($cc) + count($bcc)) > WS_MAIL_SEND_ADDRESSES) {
        return $refuse('too_many');
    }

    foreach (array_merge($to, $cc, $bcc) as $address) {
        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || (mb_strlen($address) > 254)) {
            return $refuse('bad_address', $address);
        }
    }

    $subject = trim(preg_replace('/\s+/u', ' ', (string) (is_scalar($mail['subject'] ?? '') ? ($mail['subject'] ?? '') : '')));

    if ($subject === '') {
        return $refuse('no_subject');
    }

    $text = trim(str_replace(array("\r\n", "\r"), "\n", (string) (is_scalar($mail['text'] ?? '') ? ($mail['text'] ?? '') : '')));

    if ($text === '') {
        return $refuse('empty');
    }

    if (mb_strlen($text) > WS_MAIL_REPLY_MAX) {
        return $refuse('too_long');
    }

    $files = array_values(array_filter((array) ($mail['files'] ?? array()), 'is_array'));

    if (count($files) > WS_MAIL_SEND_FILES) {
        return $refuse('too_many_files');
    }

    $total = 0;
    $taken = array();

    foreach ($files as $file) {
        $name = (string) ($file['name'] ?? '');
        $bytes = isset($file['bytes']) && is_string($file['bytes']) ? $file['bytes'] : false;
        $inspected = ws_upload_inspect($name, $bytes);

        if (!$inspected['ok']) {
            return $refuse('file_refused', $name . ': ' . $inspected['error']);
        }

        $total += strlen((string) $bytes);

        if ($total > WS_MAIL_SEND_FILES_BYTES) {
            return $refuse('files_too_large');
        }

        $taken[] = array('name' => $inspected['name'], 'extension' => $inspected['extension'], 'bytes' => (string) $bytes);
    }

    return array_merge($out, array('ok' => true, 'to' => $to, 'cc' => $cc, 'bcc' => $bcc,
        'subject' => mb_substr($subject, 0, 200), 'text' => $text, 'files' => $taken));
}

/**
 * The limits that need the site's records: an address on the bounce list
 * is refused by name (the queue gates only To), and a person sends at most
 * WS_MAIL_SEND_HOURLY mails an hour.
 *
 * @param string[] $addresses To, Cc and Bcc
 * @param callable $blocked   address => bool (pg_mail_address_blocked())
 * @param int      $sent_last_hour the person's outgoing mails of the last hour
 * @return array ok, error, detail
 */
function ws_mail_send_limits($addresses, $blocked, $sent_last_hour)
{
    if ((int) $sent_last_hour >= WS_MAIL_SEND_HOURLY) {
        return array('ok' => false, 'error' => 'hourly', 'detail' => '');
    }

    foreach ((array) $addresses as $address) {
        if (call_user_func($blocked, (string) $address)) {
            return array('ok' => false, 'error' => 'blocked', 'detail' => (string) $address);
        }
    }

    return array('ok' => true, 'error' => '', 'detail' => '');
}

/**
 * How many mails this person sent from the Workspace in the last hour: new
 * mails and answers they wrote.
 *
 * @param int $user_id
 * @param int $now
 * @return int
 */
function ws_mail_send_last_hour($user_id, $now)
{
    return (int) db_value("SELECT COUNT(*) FROM ws_mail_items
        WHERE direction = 'out' AND user_id = '" . (int) $user_id . "' AND created_at > '" . ((int) $now - 3600) . "'");
}

/**
 * The mailboxes this person may send from, each with the channels its mail
 * may be kept in: an open box with a working route (ws_mail_reply_route()),
 * whose own channel or a channel one of its routes writes to the person may
 * post in. Team accounts only.
 *
 * @param array $viewer
 * @return array[] box, route, channels (ws_channels rows)
 */
function ws_mail_send_boxes($viewer)
{
    if (empty($viewer['member']) || !function_exists('ws_mail_ready') || !ws_mail_ready()) {
        return array();
    }

    $out = array();
    $site_domain = ws_mail_reply_site_domain();

    foreach ((array) db_items("SELECT * FROM ws_mail_boxes WHERE enabled = 1 ORDER BY name, id") as $box) {
        $route = ws_mail_reply_route($box, $site_domain);

        if (!$route['ok']) {
            continue;
        }

        $channel_ids = array((int) $box['channel_id']);

        foreach (ws_mail_routes($box) as $mail_route) {
            $channel_ids[] = (int) $mail_route['channel_id'];
        }

        $channels = array();

        foreach (array_unique(array_filter($channel_ids)) as $channel_id) {
            $channel = ws_channel($channel_id);

            if ($channel && ws_can_post_channel($viewer, $channel)) {
                $channels[] = $channel;
            }
        }

        if ($channels) {
            $out[] = array('box' => $box, 'route' => $route, 'channels' => $channels);
        }
    }

    return $out;
}

/**
 * Whether this person may send this mail: everything is checked before
 * anything is written. Reads the box, the channel, the bounce list and the
 * person's last hour; sends nothing.
 *
 * @param array $viewer
 * @param array $mail box_id, channel_id, to, cc, bcc, subject, text, files (each name and bytes)
 * @return array ok, error, detail, box, route, channel, input (ws_mail_send_input())
 */
function ws_mail_send_check($viewer, $mail)
{
    $out = array('ok' => false, 'error' => '', 'detail' => '', 'box' => null, 'route' => null, 'channel' => null, 'input' => null);

    if (!ws_mail_send_ready()) {
        return array_merge($out, array('error' => 'not_ready'));
    }

    $box_id = (int) ($mail['box_id'] ?? 0);
    $channel_id = (int) ($mail['channel_id'] ?? 0);
    $found = null;

    foreach (ws_mail_send_boxes($viewer) as $entry) {
        if ((int) $entry['box']['id'] === $box_id) {
            $found = $entry;
        }
    }

    if (!$found) {
        return array_merge($out, array('error' => 'no_box'));
    }

    $channel = null;

    foreach ($found['channels'] as $candidate) {
        if ((int) $candidate['id'] === $channel_id) {
            $channel = $candidate;
        }
    }

    if (!$channel) {
        return array_merge($out, array('error' => 'no_access'));
    }

    $input = ws_mail_send_input($mail);

    if (!$input['ok']) {
        return array_merge($out, array('error' => $input['error'], 'detail' => $input['detail']));
    }

    $limits = ws_mail_send_limits(array_merge($input['to'], $input['cc'], $input['bcc']), 'pg_mail_address_blocked',
        ws_mail_send_last_hour((int) $viewer['id'], time()));

    if (!$limits['ok']) {
        return array_merge($out, array('error' => $limits['error'], 'detail' => $limits['detail']));
    }

    return array('ok' => true, 'error' => '', 'detail' => '', 'box' => $found['box'], 'route' => $found['route'], 'channel' => $channel, 'input' => $input);
}

/**
 * Words for a reason a mail is not sent.
 *
 * @param string $error
 * @param string $detail the address or file it is about, the server's words
 * @return string
 */
function ws_mail_send_error_text($error, $detail = '')
{
    $detail = (string) $detail;
    $texts = array(
        'not_ready'       => lang('The database has not been upgraded yet.'),
        'no_box'          => lang('You cannot send from this mailbox.'),
        'no_access'       => lang('You cannot write in this channel.'),
        'no_to'           => lang('Enter at least one address under To.'),
        'too_many'        => lang(array('string' => 'An e-mail goes to at most {var:1} addresses.', 'vars' => WS_MAIL_SEND_ADDRESSES)),
        'bad_address'     => lang(array('string' => '{var:1} is not an e-mail address.', 'vars' => $detail)),
        'blocked'         => lang(array('string' => 'Mail to {var:1} bounced or was refused, so it is not sent there.', 'vars' => $detail)),
        'no_subject'      => lang('Enter a subject.'),
        'empty'           => lang('Write the e-mail first.'),
        'too_long'        => lang(array('string' => 'An e-mail is at most {var:1} characters.', 'vars' => pg_format_number(WS_MAIL_REPLY_MAX, 0))),
        'too_many_files'  => lang(array('string' => 'An e-mail carries at most {var:1} files.', 'vars' => WS_MAIL_SEND_FILES)),
        'files_too_large' => lang(array('string' => 'The files of an e-mail are at most {var:1} MB together.', 'vars' => (int) (WS_MAIL_SEND_FILES_BYTES / 1048576))),
        'file_refused'    => $detail,
        'hourly'          => lang(array('string' => 'You have sent {var:1} e-mails in the last hour; try again later.', 'vars' => WS_MAIL_SEND_HOURLY)),
        'not_failed'      => lang('This e-mail is not waiting to be sent again.'),
        'not_sent'        => lang('The e-mail could not be sent.'),
    );

    if (in_array($error, array('no_smtp', 'domain', 'connection'), true)) {
        return ws_mail_reply_error_text($error);
    }

    return (isset($texts[$error]) && ($texts[$error] !== '')) ? $texts[$error] : $texts['not_sent'];
}

/**
 * The subject of a new mail with its conversation's tag, unless it carries
 * it already. Pure.
 *
 * @param string $subject
 * @param int    $conversation_id
 * @return string
 */
function ws_mail_send_subject($subject, $conversation_id)
{
    $subject = trim(preg_replace('/\s+/u', ' ', (string) $subject));

    if (ws_mail_subject_tag($subject) !== (int) $conversation_id) {
        $subject = trim($subject . ' [#T' . (int) $conversation_id . ']');
    }

    return mb_substr($subject, 0, 255);
}

/**
 * The reference a queued mail carries back to its outcome: the item, and
 * the queue row the item named when this attempt was queued (0 for the
 * first). Pure.
 *
 * @param int $item_id
 * @param int $previous_outbox_id
 * @return string
 */
function ws_mail_send_ref($item_id, $previous_outbox_id)
{
    return (int) $item_id . ':' . (int) $previous_outbox_id;
}

/**
 * ws_mail_send_ref() read back. Pure.
 *
 * @param string $ref
 * @return array item, previous (both 0 when it is not one)
 */
function ws_mail_send_ref_parse($ref)
{
    return preg_match('/^([0-9]{1,10}):([0-9]{1,10})$/', (string) $ref, $match)
        ? array('item' => (int) $match[1], 'previous' => (int) $match[2])
        : array('item' => 0, 'previous' => 0);
}

/**
 * The send state an outgoing item reads as: '' (an answer written before
 * the state was kept, or no column yet) is sent. Pure.
 *
 * @param array|null $item ws_mail_items row
 * @return string queued | sent | failed, '' for a mail that came in
 */
function ws_mail_send_state($item)
{
    if (!is_array($item) || ((string) ($item['direction'] ?? 'in') !== 'out')) {
        return '';
    }

    $state = (string) ($item['send_state'] ?? '');

    return in_array($state, array('queued', 'failed'), true) ? $state : 'sent';
}

/**
 * What an outcome changes on an item, without writing it. Pure.
 *
 * - sent is final: any attempt of this item that left marks it sent, and
 *   nothing after that changes it.
 * - failed and suppressed count only for the item's current attempt: the
 *   row it names, or, while it waits, the row queued after the one it named
 *   (the queue may finish a row before its id is written on the item). An
 *   older row retried on the Mail queue screen leaves the item alone.
 * - The same outcome twice changes nothing.
 *
 * @param array|null $item      ws_mail_items row
 * @param int        $outbox_id the queue row, 0 for a mail sent at once
 * @param int        $previous  ws_mail_send_ref_parse()'s previous
 * @param string     $outcome   sent | failed | suppressed
 * @param string     $error
 * @param int        $now
 * @return array|null set (columns => values), notify (the writer is told), copy (put into the sent folder); null for nothing
 */
function ws_mail_send_outcome_change($item, $outbox_id, $previous, $outcome, $error, $now)
{
    if (!is_array($item) || ((string) ($item['direction'] ?? '') !== 'out') || !in_array($outcome, array('sent', 'failed', 'suppressed'), true)) {
        return null;
    }

    $state = (string) ($item['send_state'] ?? '');
    $current = (int) ($item['outbox_id'] ?? 0);

    // An answer written before the state was kept, or a mail already sent.
    if (($state === '') || ($state === 'sent')) {
        return null;
    }

    if ($outcome === 'sent') {
        return array('set' => array('send_state' => 'sent', 'send_error' => '', 'outbox_id' => (int) $outbox_id, 'sent_at' => (int) $now),
            'notify' => false, 'copy' => true);
    }

    $own = ((int) $outbox_id === $current) || (($state === 'queued') && ($current === (int) $previous));

    if (!$own || (($state === 'failed') && ($current === (int) $outbox_id))) {
        return null;
    }

    $reason = trim((string) $error);

    if ($reason === '') {
        $reason = ($outcome === 'suppressed') ? lang('Every recipient is on the bounce list.') : lang('No reason was given.');
    }

    return array('set' => array('send_state' => 'failed', 'send_error' => mb_substr($reason, 0, 500), 'outbox_id' => (int) $outbox_id),
        'notify' => true, 'copy' => false);
}

/**
 * The queue's outcome handler (pg_mail_outcome_handlers()): marks the item
 * sent (and copies it into the box's sent folder) or failed (and tells its
 * writer). Runs with no person behind it - job.php, mail_job.php, the Mail
 * queue screen - so it loads the Workspace itself; before 11.0.185 has run
 * it does nothing.
 *
 * @param int    $outbox_id
 * @param string $outcome sent | failed | suppressed
 * @param string $ref     ws_mail_send_ref()
 * @param string $error
 * @param string $mime    the mail as it left (keep_mime)
 */
function ws_mail_send_outcome($outbox_id, $outcome, $ref, $error, $mime)
{
    if (!function_exists('ws_mail_box')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    }

    $parsed = ws_mail_send_ref_parse($ref);

    if (($parsed['item'] <= 0) || !ws_mail_send_ready()) {
        return;
    }

    $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . $parsed['item'] . "'");
    $change = ws_mail_send_outcome_change(is_array($item) ? $item : null, (int) $outbox_id, $parsed['previous'], (string) $outcome, (string) $error, time());

    if (!$change) {
        return;
    }

    $sets = array();

    foreach ($change['set'] as $column => $value) {
        $sets[] = $column . " = '" . e((string) $value) . "'";
    }

    // Written only over the state it was read in: two outcomes at once
    // change the item once.
    db("UPDATE ws_mail_items SET " . implode(', ', $sets) . "
        WHERE id = '" . (int) $item['id'] . "' AND send_state = '" . e((string) $item['send_state']) . "' AND outbox_id = '" . (int) $item['outbox_id'] . "'");

    if (mysqli_affected_rows(db::$con) <= 0) {
        return;
    }

    // Every reader's screen draws the card again.
    if ((int) $item['message_id'] > 0) {
        ws_message_touch((int) $item['message_id']);
    }

    $box = ws_mail_box((int) $item['box_id']);

    if ($change['copy'] && $box && ((string) $mime !== '')) {
        $route = ws_mail_reply_route($box, ws_mail_reply_site_domain());
        $plan = ws_mail_reply_copy_plan($box, $route);

        if ($route['ok'] && $plan['copy']) {
            ws_mail_reply_copy($box, $plan['folder'], (string) $mime);
        }
    }

    if ($change['notify'] && ((int) $item['user_id'] > 0) && ((int) $item['message_id'] > 0)) {
        $channel_id = (int) db_value("SELECT channel_id FROM ws_messages WHERE id = '" . (int) $item['message_id'] . "'");

        ws_notify((int) $item['user_id'], 'mail_unsent', array('channel_id' => $channel_id, 'message_id' => (int) $item['message_id'], 'actor_id' => 0));
    }
}

/**
 * What email() is given for an outgoing item: the box's address and route,
 * the item's addresses, subject and Message-ID, the text with the writer's
 * name and the box's Signature (or its name) under it, and the files of the item's messages by
 * path, read when the mail leaves.
 *
 * @param array $item  ws_mail_items row
 * @param array $box   ws_mail_boxes row
 * @param array $route ws_mail_reply_route()
 * @return array
 */
function ws_mail_send_properties($item, $box, $route)
{
    $properties = array(
        'to'                 => ws_mail_send_addresses((string) $item['to_list']),
        'cc'                 => ws_mail_send_addresses((string) $item['cc_list']),
        'bcc'                => ws_mail_send_addresses((string) ($item['bcc_list'] ?? '')),
        'from_name'          => (string) $box['name'],
        'from_email_address' => (string) $box['address'],
        'subject'            => (string) $item['subject'],
        'body'               => ws_mail_send_body((string) $item['body_text'], (string) $item['from_name'], (string) $box['name'], ws_mail_box_signature($box)),
        'headers'            => ws_mail_reply_headers(null, (string) $item['rfc_message_id']),
        'type'               => 'workspace',
        // The text stays out of failure notices and the Site Log; the writer
        // hears in the Workspace, not by mail.
        'secret'             => true,
        'notify_sender'      => false,
        'keep_mime'          => true,
    );

    if (!empty($route['smtp'])) {
        $properties['smtp'] = $route['smtp'];
    }

    if (!empty($route['connection'])) {
        $properties['connection'] = (int) $route['connection'];
    }

    if ((int) $item['message_id'] > 0) {
        $files = db_items("SELECT m.file_id, m.file_name, f.name
            FROM ws_messages m
            INNER JOIN files f ON f.id = m.file_id
            WHERE m.sender_kind = 'mail' AND m.sender_id = '" . (int) $item['id'] . "' AND m.file_id > 0 AND m.deleted_at = 0
            ORDER BY m.id");

        foreach ((array) $files as $file) {
            // A file moved to the object store is read through its local copy.
            $path = pg_file_local_copy((int) $file['file_id']);

            if ($path !== '') {
                $properties['attachments'][] = array(
                    'path' => $path,
                    'name' => ((string) $file['file_name'] !== '') ? (string) $file['file_name'] : (string) $file['name'],
                );
            }
        }
    }

    return $properties;
}

/**
 * Hands an item that waits (send_state queued) to the mail: the queue while
 * the general job runs, with the outcome handler, else email() at once and
 * the outcome handled here, since email()'s own queue option calls no
 * handler.
 *
 * @param array $item     ws_mail_items row
 * @param int   $previous the queue row the item named before this attempt
 * @return string queued | sent | failed
 */
function ws_mail_send_dispatch($item, $previous)
{
    $box = ws_mail_box((int) $item['box_id']);
    $route = $box ? ws_mail_reply_route($box, ws_mail_reply_site_domain()) : array('ok' => false);
    $ref = ws_mail_send_ref((int) $item['id'], (int) $previous);

    if (!$route['ok']) {
        ws_mail_send_outcome(0, 'failed', $ref, ws_mail_send_error_text($box ? $route['error'] : 'no_box'), '');

        return 'failed';
    }

    $properties = ws_mail_send_properties($item, $box, $route);

    if (pg_mail_job_alive()) {
        $outbox_id = pg_mail_enqueue(array_merge($properties, array('on_outcome' => WS_MAIL_SEND_HANDLER, 'outcome_ref' => $ref)));

        if ($outbox_id > 0) {
            // The queue may have finished the row already; the handler then
            // wrote its id with the outcome, and this changes nothing.
            db("UPDATE ws_mail_items SET outbox_id = '" . (int) $outbox_id . "'
                WHERE id = '" . (int) $item['id'] . "' AND send_state = 'queued' AND outbox_id = '" . (int) $previous . "'");

            return 'queued';
        }
    }

    $sent = email($properties);

    ws_mail_send_outcome(0, $sent ? 'sent' : 'failed', $ref, $sent ? '' : (string) pg_mail_last_error(), $sent ? (string) pg_mail_last_sent_mime() : '');

    return $sent ? 'sent' : 'failed';
}

/**
 * Sends a new mail: checks it, writes the conversation (its customer the
 * first To, waiting, held by the writer), its card in the channel, the
 * outgoing item (queued) and its files, then hands it to the mail.
 *
 * @param array $viewer
 * @param array $mail ws_mail_send_check()'s
 * @return array ok, error, detail, conversation_id, item_id, message_id, state (queued | sent | failed)
 */
function ws_mail_send($viewer, $mail)
{
    $check = ws_mail_send_check($viewer, $mail);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => $check['error'], 'detail' => $check['detail'], 'conversation_id' => 0, 'item_id' => 0, 'message_id' => 0, 'state' => '');
    }

    $box = $check['box'];
    $channel = $check['channel'];
    $input = $check['input'];
    $now = time();
    $writer = mb_substr(ws_person_name((int) $viewer['id']), 0, 255);

    db("INSERT INTO ws_mail_conversations (box_id, channel_id, subject, customer_email, status, assignee_id, last_out_at, created_at, updated_at)
        VALUES ('" . (int) $box['id'] . "', '" . (int) $channel['id'] . "', '" . e($input['subject']) . "', '" . e($input['to'][0]) . "',
            'waiting', '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "', '" . $now . "')");

    $conversation_id = (int) mysqli_insert_id(db::$con);

    if ($conversation_id <= 0) {
        return array('ok' => false, 'error' => 'not_sent', 'detail' => '', 'conversation_id' => 0, 'item_id' => 0, 'message_id' => 0, 'state' => '');
    }

    $host = pg_mail_hostname();

    if ($host === '') {
        $host = (string) substr(strrchr((string) $box['address'], '@'), 1);
    }

    $message_id = ws_mail_reply_message_id($conversation_id, bin2hex(random_bytes(8)), $host);

    db("INSERT INTO ws_mail_items (box_id, conversation_id, direction, rfc_message_id, rfc_message_hash, from_email, from_name,
            to_list, cc_list, bcc_list, subject, body_text, auth, user_id, send_state, created_at)
        VALUES ('" . (int) $box['id'] . "', '" . $conversation_id . "', 'out', '" . e($message_id) . "', '" . e(ws_mail_hash($message_id, '')) . "',
            '" . e((string) $box['address']) . "', '" . e($writer) . "', '" . e(implode(', ', $input['to'])) . "',
            '" . e(implode(', ', $input['cc'])) . "', '" . e(implode(', ', $input['bcc'])) . "',
            '" . e(ws_mail_send_subject($input['subject'], $conversation_id)) . "', '" . e($input['text']) . "', 'pass', '" . (int) $viewer['id'] . "',
            'queued', '" . $now . "')");

    $item_id = (int) mysqli_insert_id(db::$con);

    if ($item_id <= 0) {
        return array('ok' => false, 'error' => 'not_sent', 'detail' => '', 'conversation_id' => $conversation_id, 'item_id' => 0, 'message_id' => 0, 'state' => '');
    }

    $card = ws_mail_message_write($channel['id'], 0, $item_id, ws_mail_message_body($input['text']), 0, '', $now);
    $refused = array();

    // Each file is a message of its own under the card, as an incoming
    // mail's attachments are; the mail takes them from there.
    foreach ($input['files'] as $file) {
        $stored = ws_store_file_bytes($channel, $file['name'], $file['extension'], $file['bytes'], (int) $viewer['id']);

        if (!$stored['ok'] || (ws_mail_message_write($channel['id'], $card, $item_id, '', $stored['file_id'], $stored['name'], $now) <= 0)) {
            if ($stored['ok']) {
                ws_upload_discard($stored['file_id']);
            }

            $refused[] = $file['name'];
        }
    }

    db("UPDATE ws_mail_conversations SET message_id = '" . (int) $card . "' WHERE id = '" . $conversation_id . "'");
    db("UPDATE ws_mail_items SET message_id = '" . (int) $card . "', refused_files = '" . e(implode("\n", $refused)) . "' WHERE id = '" . $item_id . "'");

    $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . $item_id . "'");
    $state = is_array($item) ? ws_mail_send_dispatch($item, 0) : 'failed';

    return array('ok' => true, 'error' => '', 'detail' => '', 'conversation_id' => $conversation_id, 'item_id' => $item_id, 'message_id' => (int) $card, 'state' => $state);
}

/**
 * The outgoing item a message offers to send again: a mail that could not
 * leave, for a team member who may post in its channel. Pure.
 *
 * @param array|null $item     ws_mail_items_map() entry
 * @param bool       $can_post
 * @param array      $viewer
 * @return int item id, 0 for none
 */
function ws_mail_resend_offer($item, $can_post, $viewer)
{
    if (!$can_post || empty($viewer['member']) || (ws_mail_send_state($item) !== 'failed')) {
        return 0;
    }

    return (int) $item['id'];
}

/**
 * Send again: an item whose mail could not leave goes to the mail once more,
 * for whoever may send from its channel (ws_can_post_channel(), team
 * accounts only), within the person's hourly limit and with no address on
 * the bounce list. Only one press counts: the item leaves "failed" once.
 *
 * @param array $viewer
 * @param int   $item_id
 * @return array ok, error, detail, state (queued | sent | failed)
 */
function ws_mail_resend($viewer, $item_id)
{
    $refuse = function ($error, $detail = '') {
        return array('ok' => false, 'error' => $error, 'detail' => (string) $detail, 'state' => '');
    };

    if (!ws_mail_send_ready()) {
        return $refuse('not_ready');
    }

    $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . (int) $item_id . "'");

    if (!is_array($item) || (ws_mail_send_state($item) !== 'failed')) {
        return $refuse('not_failed');
    }

    $channel_id = (int) db_value("SELECT channel_id FROM ws_messages WHERE id = '" . (int) $item['message_id'] . "'");
    $channel = ($channel_id > 0) ? ws_channel($channel_id) : null;

    if (!$channel || empty($viewer['member']) || !ws_can_post_channel($viewer, $channel)) {
        return $refuse('no_access');
    }

    $addresses = array_merge(ws_mail_send_addresses((string) $item['to_list']), ws_mail_send_addresses((string) $item['cc_list']),
        ws_mail_send_addresses((string) $item['bcc_list']));
    $limits = ws_mail_send_limits($addresses, 'pg_mail_address_blocked', ws_mail_send_last_hour((int) $viewer['id'], time()));

    if (!$limits['ok']) {
        return $refuse($limits['error'], $limits['detail']);
    }

    // The previous row stays named until the new one is written, so an
    // outcome of the new row before that is still the item's own.
    db("UPDATE ws_mail_items SET send_state = 'queued', send_error = ''
        WHERE id = '" . (int) $item['id'] . "' AND send_state = 'failed' AND outbox_id = '" . (int) $item['outbox_id'] . "'");

    if (mysqli_affected_rows(db::$con) <= 0) {
        return $refuse('not_failed');
    }

    if ((int) $item['message_id'] > 0) {
        ws_message_touch((int) $item['message_id']);
    }

    $item['send_state'] = 'queued';
    $item['send_error'] = '';

    return array('ok' => true, 'error' => '', 'detail' => '', 'state' => ws_mail_send_dispatch($item, (int) $item['outbox_id']));
}

/**
 * The badge of an outgoing mail by its send state: sent (and an answer
 * written before the state was kept), waiting to be sent, or not sent with
 * the reason.
 *
 * @param array $item ws_mail_items row
 * @return string HTML
 */
function ws_mail_send_badge_html($item)
{
    $state = ws_mail_send_state($item);

    if ($state === 'queued') {
        return '<span class="badge theme-secondary"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>' . h(lang('Waiting to be sent')) . '</span>';
    }

    if ($state === 'failed') {
        $reason = trim((string) ($item['send_error'] ?? ''));

        return '<span class="badge theme-danger"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' . h(lang('Not sent')) . '</span>'
            . (($reason !== '') ? ' <span class="ms-1">' . h($reason) . '</span>' : '');
    }

    return '<span class="badge theme-success"><i class="bi bi-send me-1" aria-hidden="true"></i>' . h(lang('Sent by e-mail')) . '</span>';
}

/**
 * An inbox line about a mail that could not leave (mail_unsent): its
 * subject is the body, the line opens the mail.
 *
 * @param array  $row ws_inbox row
 * @param string $channel_name
 * @param string $base the panel's address
 * @return array title, body, url, icon
 */
function ws_mail_unsent_describe($row, $channel_name, $base)
{
    $subject = ((int) $row['message_id'] > 0)
        ? (string) db_value("SELECT subject FROM ws_mail_items WHERE message_id = '" . (int) $row['message_id'] . "' AND direction = 'out' ORDER BY id DESC LIMIT 1")
        : '';

    return array(
        'title' => lang(array('string' => 'Your e-mail in {var:1} could not be sent', 'vars' => $channel_name)),
        'body'  => $subject,
        'url'   => $base . 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'],
        'icon'  => 'bi-envelope-x',
    );
}

/**
 * The texts of Send again (assets/js/workspace_mail.js).
 *
 * @return array key => text
 */
function ws_mail_send_js_strings()
{
    return array(
        'mail_send_again'      => lang('Send again'),
        'mail_send_again_done' => lang('The e-mail is on its way again.'),
    );
}
