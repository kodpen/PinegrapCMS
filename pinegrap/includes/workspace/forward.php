<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - messages chosen together: an answer to several of them, and a
 * forward of them to another channel or into a note.
 *
 * An answer names its first message as its parent, as a single answer does;
 * the others it answers are kept in ws_message_quotes, in their order.
 *
 * A forward is a message of its own in the channel it goes to, with a copy
 * of each message it carries in ws_message_forwards: its words as they were,
 * who wrote them, when, and where. The copy is what is shown, so a message
 * changed or deleted later, or a channel the reader cannot open, does not
 * change what was forwarded; the way back to the original is offered only to
 * somebody who can read it. A file goes as its name: its folder belongs to
 * the channel it was written in.
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
 * The most messages chosen together.
 */
define('WS_FORWARD_MAX', 20);

/**
 * Has the database the answers to several and the forwards (2026.4.5,
 * 5.87)?
 *
 * @return bool
 */
function ws_forwards_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('ws_message_quotes', 'quoted_id')
            && waf_table_has_column('ws_message_forwards', 'source_message_id');
    }

    return $ready;
}

/**
 * The messages chosen, as they may be answered or forwarded: of one channel
 * the person reads, not deleted, written by a person or an application,
 * oldest first.
 *
 * @param array $viewer
 * @param int[] $message_ids
 * @return array ok, error, rows, channel
 */
function ws_forward_pick($viewer, $message_ids)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'rows' => array(), 'channel' => null);
    };

    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $message_ids))));

    if (empty($ids)) {
        return $fail(lang('Choose the messages first.'));
    }

    if (count($ids) > WS_FORWARD_MAX) {
        return $fail(lang(array('string' => 'At most {var:1} messages can be chosen together.', 'vars' => WS_FORWARD_MAX)));
    }

    $rows = (array) db_items("SELECT * FROM ws_messages WHERE id IN (" . implode(',', $ids) . ") ORDER BY id");

    if (count($rows) !== count($ids)) {
        return $fail(lang('That message could not be found.'));
    }

    $channel_id = (int) $rows[0]['channel_id'];

    foreach ($rows as $row) {
        if (((int) $row['channel_id'] !== $channel_id) || ((int) $row['deleted_at'] > 0)
            || !in_array($row['sender_kind'], array('user', 'app', 'guest'), true)
            || !in_array($row['kind'], array('message', 'note', 'decision'), true)) {
            return $fail(lang('Only messages of one channel, written by people, can be chosen together.'));
        }
    }

    $channel = ws_channel($channel_id);

    if (!$channel || !ws_can_read_channel($viewer, $channel)) {
        return $fail(lang('That message could not be found.'));
    }

    return array('ok' => true, 'error' => '', 'rows' => $rows, 'channel' => $channel);
}

/**
 * Keeps the other messages an answer answers.
 *
 * @param int   $message_id
 * @param int[] $quoted_ids in their order
 */
function ws_message_quotes_store($message_id, $quoted_ids)
{
    if (!ws_forwards_ready()) {
        return;
    }

    $position = 0;

    foreach (array_values(array_unique(array_filter(array_map('intval', (array) $quoted_ids)))) as $quoted_id) {
        if ($quoted_id === (int) $message_id) {
            continue;
        }

        db("INSERT IGNORE INTO ws_message_quotes (message_id, quoted_id, position)
            VALUES ('" . (int) $message_id . "', '" . $quoted_id . "', '" . (++$position) . "')");
    }
}

/**
 * The other messages each of these answers, for the screen: who wrote them
 * and their first words.
 *
 * @param array $viewer
 * @param int[] $message_ids
 * @return array message id => [id, sender, text, deleted]
 */
function ws_message_quotes_map($viewer, $message_ids)
{
    $message_ids = array_values(array_unique(array_filter(array_map('intval', (array) $message_ids))));

    if (empty($message_ids) || !ws_forwards_ready()) {
        return array();
    }

    $links = (array) db_items("SELECT q.message_id, q.quoted_id, m.body, m.sender_kind, m.sender_id, m.deleted_at, m.file_name
        FROM ws_message_quotes q
        LEFT JOIN ws_messages m ON m.id = q.quoted_id
        WHERE q.message_id IN (" . implode(',', $message_ids) . ")
        ORDER BY q.message_id, q.position");

    if (empty($links)) {
        return array();
    }

    $people = ws_people(array_map(function ($row) { return ($row['sender_kind'] === 'user') ? (int) $row['sender_id'] : 0; }, $links));
    $out = array();

    foreach ($links as $row) {
        $deleted = ($row['body'] === null) || ((int) $row['deleted_at'] > 0);
        $sender = ws_forward_sender($row['sender_kind'], $row['sender_id'], $people);
        $text = $deleted ? '' : ws_plain_excerpt($viewer, (string) $row['body'], 90);

        $out[(int) $row['message_id']][] = array(
            'id'      => (int) $row['quoted_id'],
            'sender'  => $sender ? (string) $sender['name'] : '',
            'text'    => ($text !== '') ? $text : (string) $row['file_name'],
            'deleted' => $deleted,
        );
    }

    return $out;
}

/**
 * Forwards messages to another channel: a message there with a copy of each,
 * and the person's own words above them if they wrote any.
 *
 * @param array  $viewer
 * @param int[]  $message_ids
 * @param array  $target  the channel it goes to
 * @param string $comment
 * @return array ok, error, message_id
 */
function ws_forward_to_channel($viewer, $message_ids, $target, $comment = '')
{
    if (!ws_forwards_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'), 'message_id' => 0);
    }

    $picked = ws_forward_pick($viewer, $message_ids);

    if (!$picked['ok']) {
        return array('ok' => false, 'error' => $picked['error'], 'message_id' => 0);
    }

    if (!is_array($target) || !ws_can_post_channel($viewer, $target) || ((int) $target['archived_at'] > 0)) {
        return array('ok' => false, 'error' => lang('Choose a channel you can write in.'), 'message_id' => 0);
    }

    // What the team says to each other is not carried into a room a guest
    // reads.
    if ((string) $target['kind'] === 'guest') {
        return array('ok' => false, 'error' => lang('Messages cannot be forwarded into a room with a guest.'), 'message_id' => 0);
    }

    $sent = ws_message_send($viewer, $target, (string) $comment, array('forwards' => count($picked['rows'])));

    if (!$sent['ok']) {
        return array('ok' => false, 'error' => $sent['error'], 'message_id' => 0);
    }

    $position = 0;

    foreach ($picked['rows'] as $row) {
        db("INSERT INTO ws_message_forwards (message_id, position, source_message_id, source_channel_id, source_sender_kind, source_sender_id,
                source_created_at, body, file_name)
            VALUES ('" . (int) $sent['message_id'] . "', '" . (++$position) . "', '" . (int) $row['id'] . "', '" . (int) $row['channel_id'] . "',
                '" . e($row['sender_kind']) . "', '" . (int) $row['sender_id'] . "', '" . (int) $row['created_at'] . "',
                '" . e((string) $row['body']) . "', '" . e((string) $row['file_name']) . "')");
    }

    // A message that carries the forwards: its cards come with it, so the
    // screens are told it changed.
    ws_message_touch($sent['message_id']);

    return array('ok' => true, 'error' => '', 'message_id' => (int) $sent['message_id']);
}

/**
 * Forwards messages into a note: a new one, or one the person may change,
 * at its end. Each message goes with who wrote it, when and where.
 *
 * @param array  $viewer
 * @param int[]  $message_ids
 * @param int    $note_id 0 for a new note
 * @param string $comment
 * @return array ok, error, note_id
 */
function ws_forward_to_note($viewer, $message_ids, $note_id = 0, $comment = '')
{
    if (!function_exists('ws_note_save') || !ws_notes_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'), 'note_id' => 0);
    }

    $picked = ws_forward_pick($viewer, $message_ids);

    if (!$picked['ok']) {
        return array('ok' => false, 'error' => $picked['error'], 'note_id' => 0);
    }

    $people = ws_people(array_map(function ($row) { return ($row['sender_kind'] === 'user') ? (int) $row['sender_id'] : 0; }, $picked['rows']));
    $parts = array();

    if (trim((string) $comment) !== '') {
        $parts[] = trim((string) $comment);
    }

    foreach ($picked['rows'] as $row) {
        $sender = ws_forward_sender($row['sender_kind'], $row['sender_id'], $people);
        $head = '**' . ($sender ? $sender['name'] : lang('Someone')) . '** · ' . date('d.m.Y H:i', (int) $row['created_at']) . ' · #' . $picked['channel']['name'];
        $text = trim((string) $row['body']);

        if ((string) $row['file_name'] !== '') {
            $text .= (($text !== '') ? "\n" : '') . '📎 ' . $row['file_name'];
        }

        $parts[] = $head . "\n" . $text;
    }

    $block = implode("\n\n", $parts);
    $note_id = (int) $note_id;

    if ($note_id > 0) {
        $note = ws_note_for($viewer, $note_id, 'edit');

        if (!$note) {
            return array('ok' => false, 'error' => lang('That note could not be found.'), 'note_id' => 0);
        }

        $body = rtrim((string) $note['body']);
        $saved = ws_note_save($viewer, array('note_id' => $note_id, 'body' => (($body !== '') ? $body . "\n\n---\n\n" : '') . $block));
    } else {
        $saved = ws_note_save($viewer, array(
            'title' => lang(array('string' => 'From #{var:1}', 'vars' => $picked['channel']['name'])),
            'body'  => $block,
        ));
    }

    return $saved['ok']
        ? array('ok' => true, 'error' => '', 'note_id' => (int) $saved['note_id'])
        : array('ok' => false, 'error' => $saved['error'], 'note_id' => 0);
}

/**
 * The copies a forward carries, for the screen. The words are drawn for the
 * reader, so a tag they may not see stays hidden; the way back to the
 * original is there only when they can read it.
 *
 * @param array $viewer
 * @param int[] $message_ids
 * @return array message id => cards (source_id, channel, can_open, sender, time, html, file_name)
 */
function ws_message_forwards_map($viewer, $message_ids)
{
    $message_ids = array_values(array_unique(array_filter(array_map('intval', (array) $message_ids))));

    if (empty($message_ids) || !ws_forwards_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_message_forwards WHERE message_id IN (" . implode(',', $message_ids) . ") ORDER BY message_id, position");

    if (empty($rows)) {
        return array();
    }

    $tokens = array();
    $senders = array();

    foreach ($rows as $row) {
        foreach (ws_tokens($row['body']) as $token) {
            $tokens[] = $token;
        }

        if ($row['source_sender_kind'] === 'user') {
            $senders[] = (int) $row['source_sender_id'];
        }
    }

    $refs = ws_refs_resolve($viewer, $tokens);
    $people = ws_people($senders);
    $readable = array();
    $out = array();

    foreach ($rows as $row) {
        $channel_id = (int) $row['source_channel_id'];

        if (!isset($readable[$channel_id])) {
            $channel = ws_channel($channel_id);
            $readable[$channel_id] = ($channel && ws_can_read_channel($viewer, $channel)) ? $channel : false;
        }

        $channel = $readable[$channel_id];
        $sender = ws_forward_sender($row['source_sender_kind'], $row['source_sender_id'], $people);
        $open = $channel && ((int) db_value("SELECT deleted_at FROM ws_messages WHERE id = '" . (int) $row['source_message_id'] . "'") === 0);

        $out[(int) $row['message_id']][] = array(
            'source_id' => (int) $row['source_message_id'],
            'channel'   => $channel ? array('id' => (int) $channel['id'], 'name' => (string) $channel['name'], 'kind' => (string) $channel['kind']) : null,
            'can_open'  => $open,
            'sender'    => $sender,
            'time'      => ws_time_label($row['source_created_at']),
            'html'      => ws_render_body((string) $row['body'], $refs),
            'file_name' => (string) $row['file_name'],
        );
    }

    return $out;
}

/**
 * Who wrote a message that was forwarded or quoted: a person, an application
 * or the guest of a room.
 *
 * @param string $kind   user | app | guest
 * @param int    $id
 * @param array  $people ws_people() of the people among them
 * @return array|null
 */
function ws_forward_sender($kind, $id, $people)
{
    if ($kind === 'app') {
        return ws_app_sender((int) $id);
    }

    if (($kind === 'guest') && function_exists('ws_guest_sender')) {
        return ws_guest_sender((int) $id);
    }

    return ($kind === 'user') ? ($people[(int) $id] ?? null) : null;
}

/**
 * The words the screen needs for choosing messages together.
 *
 * @return array
 */
function ws_forward_js_strings()
{
    return array(
        'sel_start'        => lang('Choose messages'),
        'sel_count'        => ws_js_template('{var:1} chosen', 1),
        'sel_reply'        => lang('Reply'),
        'sel_forward'      => lang('Forward'),
        'sel_to_note'      => lang('Into a note'),
        'sel_cancel'       => lang('Done choosing'),
        'sel_max'          => ws_js_template('At most {var:1} messages can be chosen together.', 1),
        'sel_help'         => lang('Tap the messages to choose them.'),
        'reply_many'       => ws_js_template('Replying to {var:1} messages', 1),
        'fwd_title'        => lang('Forward the messages'),
        'fwd_to'           => lang('To the channel'),
        'fwd_comment'      => lang('A few words above them (optional)'),
        'fwd_private'      => lang('These messages are from a private channel. Whoever reads the channel you forward them to reads them too.'),
        'fwd_sent'         => lang('The messages are forwarded.'),
        'fwd_note_title'   => lang('Into a note'),
        'fwd_note_new'     => lang('A new note'),
        'fwd_note_pick'    => lang('The note'),
        'fwd_note_done'    => lang('The messages are in the note.'),
        'fwd_open_note'    => lang('Open the note'),
        'fwd_from'         => ws_js_template('Forwarded from #{var:1}', 1),
        'fwd_from_hidden'  => lang('Forwarded from a channel you cannot open'),
        'fwd_open'         => lang('Open the original'),
        'fwd_file'         => lang('The file stays in the channel it was sent in.'),
    );
}
