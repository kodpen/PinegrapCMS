<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - discussions: a room of its own for talking over one message of
 * a channel, so the channel's conversation does not run on and bury what
 * matters, and the back-and-forth reaches only the people taking part.
 *
 * A discussion is started from a message (a message, a decision, a note or a
 * task card) by anybody who may write in its channel. It is a channel row of
 * kind "thread" tied to that message (ws_threads): the person who started it
 * and the one who wrote the message are in it, staff who read the channel
 * may join it, and those in it may bring in others who read the channel.
 * Nobody else reads it; the channel shows, under the message, that a
 * discussion is going on, who is in it and how far it has got.
 *
 * Everything decided in a discussion belongs to the channel. A message
 * marked as a decision or a note there, and a task opened there, is copied
 * into the channel as well (ws_thread_copies), where it stays when the
 * discussion is gone; the copy follows the original while the discussion
 * lasts and says which discussion it came from. A task opened in a
 * discussion is a task of the channel.
 *
 * The assistants may be asked in a discussion wherever they may be asked in
 * its channel; the first time one is, the channel's message says so.
 *
 * Anybody in a discussion may conclude it: it is kept as it was, read only,
 * for WS_THREAD_KEEP_DAYS days, and then deleted with everything in it
 * (ws_threads_purge(), run with the scheduled actions). A discussion whose
 * channel is gone is deleted at once. The decisions and tasks it produced
 * stay in the channel.
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
 * How long a concluded discussion is kept, in days.
 */
define('WS_THREAD_KEEP_DAYS', 30);

/**
 * The longest title of a discussion (the channel name column).
 */
define('WS_THREAD_TITLE_MAX', 80);

/**
 * The most discussions one purge deletes.
 */
define('WS_THREAD_PURGE_BATCH', 10);

/**
 * Are the tables there (2026.4.8, 8.83)?
 *
 * @return bool
 */
function ws_threads_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ws_threads', 'ws_thread_copies')") === 2)
            && (strpos((string) db_value("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_channels' AND COLUMN_NAME = 'kind'"), "'thread'") !== false);
    }

    return $ready;
}

/**
 * Is this channel a discussion?
 *
 * @param array|null $channel
 * @return bool
 */
function ws_channel_is_thread($channel)
{
    return is_array($channel) && ((string) ($channel['kind'] ?? '') === 'thread');
}

/**
 * The row of a discussion, by its channel.
 *
 * @param int $channel_id
 * @return array|null
 */
function ws_thread($channel_id)
{
    static $cache = array();

    $channel_id = (int) $channel_id;

    if (($channel_id <= 0) || !ws_threads_ready()) {
        return null;
    }

    if (!array_key_exists($channel_id, $cache)) {
        $row = db_item("SELECT * FROM ws_threads WHERE channel_id = '" . $channel_id . "'");
        $cache[$channel_id] = is_array($row) ? $row : null;
    }

    return $cache[$channel_id];
}

/**
 * The channel a discussion is about; null for any other channel.
 *
 * @param array|null $channel
 * @return array|null
 */
function ws_thread_parent_channel($channel)
{
    if (!ws_channel_is_thread($channel)) {
        return null;
    }

    $thread = ws_thread($channel['id']);
    $parent = $thread ? ws_channel((int) $thread['parent_channel_id']) : null;

    return ($parent && !ws_channel_is_thread($parent)) ? $parent : null;
}

/**
 * The id of the channel a discussion is about, 0 for any other channel.
 *
 * @param array|null $channel
 * @return int
 */
function ws_thread_parent_id($channel)
{
    $parent = ws_thread_parent_channel($channel);

    return $parent ? (int) $parent['id'] : 0;
}

/**
 * A title as it is kept: one line, at most WS_THREAD_TITLE_MAX characters.
 *
 * @param string $title
 * @return string
 */
function ws_thread_clean_title($title)
{
    return trim(mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $title)), 0, WS_THREAD_TITLE_MAX));
}

/**
 * Why this person may not start a discussion about a message, '' when they
 * may: they write in its channel, the channel is a channel of the team, and
 * the message is one somebody said.
 *
 * @param array $viewer
 * @param array $message
 * @return string
 */
function ws_thread_start_refusal($viewer, $message)
{
    if (!ws_threads_ready()) {
        return lang('The workspace is not installed yet: the database has to be updated first.');
    }

    $channel = ws_channel((int) $message['channel_id']);

    if (!$channel || !in_array((string) $channel['kind'], array('public', 'private'), true)) {
        return lang('A discussion is started from a message of a channel.');
    }

    if (!ws_can_post_channel($viewer, $channel)) {
        return lang('You cannot post in that channel.');
    }

    if (((int) $message['deleted_at'] > 0) || !in_array((string) $message['kind'], array('message', 'decision', 'note', 'task'), true)) {
        return lang('That message could not be found.');
    }

    if (function_exists('ws_message_in_past') && ws_message_in_past($message)) {
        return lang('This message is in an earlier version of the conversation, which is kept as it was.');
    }

    return '';
}

/**
 * The discussion about a message, open or concluded, if there is one.
 *
 * @param int $message_id
 * @return array|null
 */
function ws_thread_for_message($message_id)
{
    if (!ws_threads_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_threads WHERE message_id = '" . (int) $message_id . "' ORDER BY channel_id DESC LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Starts a discussion about a message: its own room, with the person who
 * starts it and the one who wrote the message in it. A message has one
 * discussion; asked again, the one there is answered.
 *
 * @param array  $viewer
 * @param array  $message
 * @param string $title empty for the first words of the message
 * @return array ok, error, thread_id (the discussion's channel), existing
 */
function ws_thread_start($viewer, $message, $title = '')
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'thread_id' => 0, 'existing' => false);
    };

    $refusal = ws_thread_start_refusal($viewer, $message);

    if ($refusal !== '') {
        return $fail($refusal);
    }

    $existing = ws_thread_for_message($message['id']);

    if ($existing && ws_channel((int) $existing['channel_id'])) {
        return array('ok' => true, 'error' => '', 'thread_id' => (int) $existing['channel_id'], 'existing' => true);
    }

    $channel = ws_channel((int) $message['channel_id']);
    $title = ws_thread_clean_title($title);

    if ($title === '') {
        $title = ws_thread_clean_title(ws_plain_excerpt($viewer, (string) $message['body'], WS_THREAD_TITLE_MAX));
    }

    if (($title === '') && ((int) $message['task_id'] > 0)) {
        $task = ws_task((int) $message['task_id']);
        $title = $task ? ws_thread_clean_title($task['title']) : '';
    }

    if (($title === '') && ((string) $message['file_name'] !== '')) {
        $title = ws_thread_clean_title($message['file_name']);
    }

    if ($title === '') {
        $title = lang('Discussion');
    }

    $now = time();

    db("INSERT INTO ws_channels (name, kind, topic, owner_user_id, contact_id, department_id, created_by, created_at, last_message_at)
        VALUES ('" . e($title) . "', 'thread', '', '" . (int) $viewer['id'] . "', 0, '" . (int) $channel['department_id'] . "',
            '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

    $thread_id = (int) mysqli_insert_id(db::$con);

    if ($thread_id <= 0) {
        return $fail(lang('The discussion could not be started.'));
    }

    db("INSERT INTO ws_threads (channel_id, parent_channel_id, message_id, created_by, created_at)
        VALUES ('" . $thread_id . "', '" . (int) $channel['id'] . "', '" . (int) $message['id'] . "', '" . (int) $viewer['id'] . "', '" . $now . "')");

    db("INSERT IGNORE INTO ws_channel_members (channel_id, user_id, role, joined_at)
        VALUES ('" . $thread_id . "', '" . (int) $viewer['id'] . "', 'owner', '" . $now . "')");

    // The one who wrote the message takes part, if they still read the
    // channel.
    $author = ($message['sender_kind'] === 'user') ? (int) $message['sender_id'] : 0;

    if (($author > 0) && ($author !== (int) $viewer['id']) && ws_is_team_member($author) && ws_can_read_channel(ws_rights_for_id($author), $channel)) {
        db("INSERT IGNORE INTO ws_channel_members (channel_id, user_id, role, joined_at)
            VALUES ('" . $thread_id . "', '" . $author . "', 'member', '" . $now . "')");

        ws_notify($author, 'thread', array('channel_id' => $thread_id, 'message_id' => (int) $message['id'], 'actor_id' => (int) $viewer['id']));
    }

    ws_channel_membership_forget();

    ws_message_system($thread_id, lang(array('string' => '{var:1} started this discussion', 'vars' => '<@user:' . (int) $viewer['id'] . '>')));

    // The channel's screens draw the message again, with the discussion
    // under it.
    ws_message_touch($message['id']);

    return array('ok' => true, 'error' => '', 'thread_id' => $thread_id, 'existing' => false);
}

/**
 * May this person join a discussion they are not in? Staff who read its
 * channel, while it is open.
 *
 * @param array $viewer
 * @param array $channel the discussion
 * @return bool
 */
function ws_thread_can_join($viewer, $channel)
{
    $parent = ws_thread_parent_channel($channel);

    return $parent && !empty($viewer['member']) && ((int) $viewer['role'] < 3)
        && ((int) $channel['archived_at'] === 0) && ((int) $parent['archived_at'] === 0)
        && !ws_channel_membership($channel['id'], $viewer['id'])
        && ws_can_read_channel($viewer, $parent);
}

/**
 * Joins a discussion.
 *
 * @param array $viewer
 * @param array $channel the discussion
 * @return array ok, error
 */
function ws_thread_join($viewer, $channel)
{
    if (!ws_thread_can_join($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('Access denied.'));
    }

    db("INSERT IGNORE INTO ws_channel_members (channel_id, user_id, role, last_read_id, joined_at)
        VALUES ('" . (int) $channel['id'] . "', '" . (int) $viewer['id'] . "', 'member', 0, '" . time() . "')");

    ws_channel_membership_forget();

    ws_message_system($channel['id'], lang(array('string' => '{var:1} joined the discussion', 'vars' => '<@user:' . (int) $viewer['id'] . '>')));

    $thread = ws_thread($channel['id']);

    if ($thread) {
        ws_message_touch((int) $thread['message_id']);
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Concludes a discussion: kept, read only, for WS_THREAD_KEEP_DAYS days,
 * then deleted. Anybody in it may.
 *
 * @param array $viewer
 * @param array $channel the discussion
 * @return array ok, error
 */
function ws_thread_close($viewer, $channel)
{
    $thread = ws_thread($channel['id']);

    if (!$thread || !ws_channel_membership($channel['id'], $viewer['id'])) {
        return array('ok' => false, 'error' => lang('Only the people in a discussion can conclude it.'));
    }

    if ((int) $thread['closed_at'] > 0) {
        return array('ok' => true, 'error' => '');
    }

    $now = time();
    $purge = strtotime('+' . WS_THREAD_KEEP_DAYS . ' days', $now);

    ws_message_system($channel['id'], lang(array('string' => '{var:1} concluded the discussion. It is kept until {var:2}, then deleted; its decisions and tasks stay in the channel.', 'vars' => array(
        '<@user:' . (int) $viewer['id'] . '>', date('d.m.Y', $purge)))));

    db("UPDATE ws_threads SET closed_at = '" . $now . "', closed_by = '" . (int) $viewer['id'] . "', purge_at = '" . $purge . "' WHERE channel_id = '" . (int) $channel['id'] . "'");
    db("UPDATE ws_channels SET archived_at = '" . $now . "' WHERE id = '" . (int) $channel['id'] . "'");

    ws_message_touch((int) $thread['message_id']);

    return array('ok' => true, 'error' => '');
}

/**
 * Renames a discussion. Anybody in it may, while it is open.
 *
 * @param array  $viewer
 * @param array  $channel the discussion
 * @param string $title
 * @return array ok, error
 */
function ws_thread_rename($viewer, $channel, $title)
{
    $thread = ws_thread($channel['id']);
    $title = ws_thread_clean_title($title);

    if (!$thread || !ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('Only the people in a discussion can change it.'));
    }

    if ($title === '') {
        return array('ok' => false, 'error' => lang('A discussion needs a title.'));
    }

    db("UPDATE ws_channels SET name = '" . e($title) . "' WHERE id = '" . (int) $channel['id'] . "'");
    db("UPDATE ws_thread_copies SET title = '" . e($title) . "' WHERE thread_channel_id = '" . (int) $channel['id'] . "'");

    ws_message_touch((int) $thread['message_id']);

    return array('ok' => true, 'error' => '');
}

/**
 * Notes that an assistant was asked in a discussion: the channel's message
 * says so from then on.
 *
 * @param array  $channel the discussion
 * @param string $agent   ai | claude
 */
function ws_thread_assistant_asked($channel, $agent)
{
    $thread = ws_channel_is_thread($channel) ? ws_thread($channel['id']) : null;

    if (!$thread) {
        return;
    }

    $agents = array_filter(explode(',', (string) $thread['assistants']));

    if (in_array($agent, $agents, true)) {
        return;
    }

    $agents[] = ($agent === 'claude') ? 'claude' : 'ai';

    db("UPDATE ws_threads SET assistants = '" . e(implode(',', array_unique($agents))) . "' WHERE channel_id = '" . (int) $channel['id'] . "'");

    ws_message_touch((int) $thread['message_id']);
}

/**
 * A discussion as the screens show it: in the channel under its message,
 * and at the head of its own panel.
 *
 * @param array $viewer
 * @param array $thread  the ws_threads row
 * @param array $channel the discussion's channel row
 * @return array
 */
function ws_thread_present($viewer, $thread, $channel)
{
    $member = ws_channel_membership($channel['id'], $viewer['id']);
    $people = array_map('intval', (array) db_values("SELECT user_id FROM ws_channel_members
        WHERE channel_id = '" . (int) $channel['id'] . "' ORDER BY role = 'owner' DESC, joined_at"));
    $counts = db_item("SELECT COUNT(*) AS total, MAX(id) AS last_id, MAX(created_at) AS last_at FROM ws_messages
        WHERE channel_id = '" . (int) $channel['id'] . "' AND deleted_at = 0 AND sender_kind <> 'system'");
    $closed = ((int) $thread['closed_at'] > 0);
    $agents = array_values(array_filter(explode(',', (string) $thread['assistants'])));
    $names = array();

    foreach ($agents as $agent) {
        $names[] = ($agent === 'claude') ? 'Claude' : 'Pinegrap AI';
    }

    return array(
        'id'         => (int) $channel['id'],
        'title'      => (string) $channel['name'],
        'channel_id' => (int) $thread['parent_channel_id'],
        'message_id' => (int) $thread['message_id'],
        'people'     => array_values(ws_people(array_slice($people, 0, 8))),
        'count'      => (int) ($counts['total'] ?? 0),
        'last'       => ((int) ($counts['last_at'] ?? 0) > 0) ? ws_time_label((int) $counts['last_at']) : '',
        'unread'     => $member ? (int) db_value("SELECT COUNT(*) FROM ws_messages
            WHERE channel_id = '" . (int) $channel['id'] . "' AND id > '" . (int) $member['last_read_id'] . "' AND deleted_at = 0
            AND sender_kind <> 'system' AND NOT (sender_kind = 'user' AND sender_id = '" . (int) $viewer['id'] . "')") : 0,
        'mine'       => (bool) $member,
        'can_join'   => ws_thread_can_join($viewer, $channel),
        'closed'     => $closed,
        'closed_text' => $closed ? lang(array('string' => 'Concluded by {var:1} on {var:2}; kept until {var:3}', 'vars' => array(
            ws_person_name($thread['closed_by']), date('d.m.Y', (int) $thread['closed_at']), date('d.m.Y', (int) $thread['purge_at'])))) : '',
        'assistants' => $names,
    );
}

/**
 * The discussions about these messages, for the bar under each.
 *
 * @param array $viewer
 * @param int[] $message_ids
 * @return array message id => ws_thread_present()
 */
function ws_threads_map($viewer, $message_ids)
{
    $message_ids = array_values(array_unique(array_filter(array_map('intval', (array) $message_ids))));

    if (empty($message_ids) || !ws_threads_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT t.*, c.id AS c_id, c.name AS c_name, c.archived_at AS c_archived
        FROM ws_threads t
        INNER JOIN ws_channels c ON c.id = t.channel_id
        WHERE t.message_id IN (" . implode(',', $message_ids) . ")") as $row) {
        $channel = ws_channel((int) $row['channel_id']);

        if ($channel) {
            $out[(int) $row['message_id']] = ws_thread_present($viewer, $row, $channel);
        }
    }

    return $out;
}

/**
 * Of these messages of a channel, the ones that are copies of what was
 * decided in a discussion: which discussion, and whether it is still there.
 *
 * @param int[] $message_ids
 * @return array message id => title, thread_id (0 once deleted), open
 */
function ws_thread_copies_map($message_ids)
{
    $message_ids = array_values(array_unique(array_filter(array_map('intval', (array) $message_ids))));

    if (empty($message_ids) || !ws_threads_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT p.channel_message_id, p.thread_channel_id, p.title, t.closed_at, t.channel_id AS alive
        FROM ws_thread_copies p
        LEFT JOIN ws_threads t ON t.channel_id = p.thread_channel_id
        WHERE p.channel_message_id IN (" . implode(',', $message_ids) . ")") as $row) {
        $out[(int) $row['channel_message_id']] = array(
            'title'     => (string) $row['title'],
            'thread_id' => ($row['alive'] !== null) ? (int) $row['thread_channel_id'] : 0,
            'open'      => ($row['alive'] !== null) && ((int) $row['closed_at'] === 0),
        );
    }

    return $out;
}

/**
 * The open discussions this person is in, for the sidebar: the newest
 * first, with what they have not read. Empty when there are none, and the
 * sidebar leaves its section out then.
 *
 * @param array $viewer
 * @return array[]
 */
function ws_threads_for($viewer)
{
    if (!ws_threads_ready() || empty($viewer['member'])) {
        return array();
    }

    $me = (int) $viewer['id'];
    $out = array();

    foreach ((array) db_items("SELECT c.id, c.name, c.last_message_at, m.last_read_id, t.parent_channel_id, t.message_id, p.name AS parent_name, p.kind AS parent_kind
        FROM ws_channel_members m
        INNER JOIN ws_channels c ON c.id = m.channel_id AND c.kind = 'thread' AND c.archived_at = 0
        INNER JOIN ws_threads t ON t.channel_id = c.id AND t.closed_at = 0
        INNER JOIN ws_channels p ON p.id = t.parent_channel_id AND p.archived_at = 0
        WHERE m.user_id = '" . $me . "'
        ORDER BY c.last_message_at DESC
        LIMIT 50") as $row) {

        // A discussion of a channel the person no longer reads waits until
        // they read it again.
        if (!ws_can_read_channel($viewer, ws_channel((int) $row['parent_channel_id']))) {
            continue;
        }

        $out[] = array(
            'id'         => (int) $row['id'],
            'title'      => (string) $row['name'],
            'channel_id' => (int) $row['parent_channel_id'],
            'channel'    => (string) $row['parent_name'],
            'message_id' => (int) $row['message_id'],
            'unread'     => (int) db_value("SELECT COUNT(*) FROM ws_messages
                WHERE channel_id = '" . (int) $row['id'] . "' AND id > '" . (int) $row['last_read_id'] . "' AND deleted_at = 0
                AND sender_kind <> 'system' AND NOT (sender_kind = 'user' AND sender_id = '" . $me . "')"),
            'last_message_at' => (int) $row['last_message_at'],
        );
    }

    return $out;
}

/**
 * Keeps the channel's copy of a message of a discussion in step with it: a
 * decision, a note or a task card of the discussion has a copy in the
 * channel, written as by the same person, marked by the same person; one
 * that is no longer any of those, or is deleted, takes its copy away. A copy
 * somebody deleted in the channel stays deleted.
 *
 * @param int $message_id a message of a discussion (any other is left alone)
 */
function ws_thread_copy_sync($message_id)
{
    if (!ws_threads_ready()) {
        return;
    }

    $message = ws_message((int) $message_id);
    $channel = $message ? ws_channel((int) $message['channel_id']) : null;
    $parent = ws_thread_parent_channel($channel);

    if (!$parent) {
        return;
    }

    $copy = db_item("SELECT * FROM ws_thread_copies WHERE thread_message_id = '" . (int) $message['id'] . "'");
    $copied = is_array($copy) ? ws_message((int) $copy['channel_message_id']) : null;
    $wanted = ((int) $message['deleted_at'] === 0) && in_array((string) $message['kind'], array('decision', 'note', 'task'), true);
    $locked = function_exists('ws_changes_ready') && ws_changes_ready();

    if (!$wanted) {
        if ($copied && ((int) $copied['deleted_at'] === 0)) {
            db("UPDATE ws_messages SET body = '', file_id = 0, file_name = '', deleted_at = '" . time() . "' WHERE id = '" . (int) $copied['id'] . "'");
            db("DELETE FROM ws_refs WHERE source_type = 'message' AND source_id = '" . (int) $copied['id'] . "'");
            ws_message_touch($copied['id']);
        }

        if (is_array($copy)) {
            db("DELETE FROM ws_thread_copies WHERE channel_message_id = '" . (int) $copy['channel_message_id'] . "'");
        }

        return;
    }

    if ($copied) {
        if ((int) $copied['deleted_at'] > 0) {
            return;
        }

        db("UPDATE ws_messages SET
                kind = '" . e($message['kind']) . "',
                body = '" . e($message['body']) . "',
                task_id = '" . (int) $message['task_id'] . "',
                file_id = '" . (int) $message['file_id'] . "',
                file_name = '" . e($message['file_name']) . "',
                marked_by = '" . (int) $message['marked_by'] . "',
                marked_at = '" . (int) $message['marked_at'] . "'"
                . ($locked ? ", locked = '" . (int) ($message['locked'] ?? 0) . "'" : '') . "
            WHERE id = '" . (int) $copied['id'] . "'");

        ws_refs_store('message', (int) $copied['id'], (int) $parent['id'], ws_tokens($message['body']));
        ws_message_touch($copied['id']);

        return;
    }

    $now = time();

    db("INSERT INTO ws_messages (channel_id, parent_id, sender_kind, sender_id, kind, body, task_id, file_id, file_name,
            marked_by, marked_at, created_at" . ($locked ? ', locked' : '') . ")
        VALUES ('" . (int) $parent['id'] . "', 0, '" . e($message['sender_kind']) . "', '" . (int) $message['sender_id'] . "',
            '" . e($message['kind']) . "', '" . e($message['body']) . "', '" . (int) $message['task_id'] . "',
            '" . (int) $message['file_id'] . "', '" . e($message['file_name']) . "',
            '" . (int) $message['marked_by'] . "', '" . (int) $message['marked_at'] . "', '" . $now . "'"
            . ($locked ? ", '" . (int) ($message['locked'] ?? 0) . "'" : '') . ")");

    $copy_id = (int) mysqli_insert_id(db::$con);

    if ($copy_id <= 0) {
        return;
    }

    db("UPDATE ws_channels SET last_message_id = '" . $copy_id . "', last_message_at = '" . $now . "' WHERE id = '" . (int) $parent['id'] . "'");
    db("INSERT INTO ws_thread_copies (channel_message_id, thread_channel_id, thread_message_id, title, created_at)
        VALUES ('" . $copy_id . "', '" . (int) $channel['id'] . "', '" . (int) $message['id'] . "', '" . e((string) $channel['name']) . "', '" . $now . "')");

    ws_refs_store('message', $copy_id, (int) $parent['id'], ws_tokens($message['body']));

    // A task card: the task's own message is the channel's, which outlives
    // the discussion.
    if ((int) $message['task_id'] > 0) {
        db("UPDATE ws_tasks SET source_message_id = '" . $copy_id . "'
            WHERE id = '" . (int) $message['task_id'] . "' AND source_message_id IN (0, '" . (int) $message['id'] . "')");
    }
}

/**
 * Deletes the discussions whose time is up, and those whose channel is
 * gone, with everything said in them. What they decided stays in the
 * channel (ws_thread_copies keeps the title the copies show). A few at a
 * time; run with the scheduled actions.
 *
 * @param int $limit
 * @return int how many were deleted
 */
function ws_threads_purge($limit = WS_THREAD_PURGE_BATCH)
{
    if (!ws_threads_ready()) {
        return 0;
    }

    $due = array_map('intval', (array) db_values("SELECT t.channel_id FROM ws_threads t
        LEFT JOIN ws_channels p ON p.id = t.parent_channel_id
        WHERE (t.purge_at > 0 AND t.purge_at <= '" . time() . "') OR p.id IS NULL
        ORDER BY t.purge_at LIMIT " . max(1, (int) $limit)));

    foreach ($due as $thread_id) {
        $messages = "SELECT id FROM ws_messages WHERE channel_id = '" . $thread_id . "'";
        $polls = "SELECT id FROM ws_polls WHERE channel_id = '" . $thread_id . "'";

        foreach (array('ws_reactions', 'ws_checks', 'ws_message_hides', 'ws_message_quotes', 'ws_message_forwards', 'ws_file_edits') as $table) {
            db("DELETE FROM " . $table . " WHERE message_id IN (" . $messages . ")");
        }

        db("DELETE FROM ws_poll_votes WHERE poll_id IN (" . $polls . ")");
        db("DELETE FROM ws_poll_options WHERE poll_id IN (" . $polls . ")");

        foreach (array('ws_polls', 'ws_refs', 'ws_blocks', 'ws_inbox', 'ws_note_shares', 'ws_channel_eras', 'ws_ai_drafts', 'ws_ai_changes', 'ws_ai_requests', 'design_proposals') as $table) {
            db("DELETE FROM " . $table . " WHERE channel_id = '" . $thread_id . "'");
        }

        db("DELETE FROM ws_messages WHERE channel_id = '" . $thread_id . "'");
        db("DELETE FROM ws_channel_members WHERE channel_id = '" . $thread_id . "'");
        db("DELETE FROM ws_threads WHERE channel_id = '" . $thread_id . "'");
        db("DELETE FROM ws_channels WHERE id = '" . $thread_id . "' AND kind = 'thread'");
    }

    return count($due);
}

/**
 * The texts of discussions, on the channel screen.
 *
 * @return array key => text
 */
function ws_threads_js_strings()
{
    return array(
        'th_start'          => lang('Start a discussion'),
        'th_start_help'     => lang('Talk it over with the people concerned in a room of its own, beside the channel. What you decide there, and the tasks you open, go into the channel too.'),
        'th_title'          => lang('Title'),
        'th_title_help'     => lang('Left empty, the first words of the message.'),
        'th_create'         => lang('Start'),
        'th_open'           => lang('Open the discussion'),
        'th_join'           => lang('Join'),
        'th_joined'         => lang('You joined the discussion.'),
        'th_bar'            => ws_js_template('Discussion: {var:1}', 1),
        'th_bar_count'      => ws_js_template('{var:1} messages', 1),
        'th_bar_last'       => ws_js_template('last {var:1}', 1),
        'th_bar_closed'     => lang('Concluded'),
        'th_bar_assistants' => ws_js_template('{var:1} was asked in this discussion', 1),
        'th_section'        => lang('Discussions'),
        'th_in'             => ws_js_template('in #{var:1}', 1),
        'th_about'          => lang('The message it is about'),
        'th_go_message'     => lang('Show it in the channel'),
        'th_close_panel'    => lang('Close the discussion panel'),
        'th_resize'         => lang('Drag to resize the discussion panel'),
        'th_conclude'       => lang('Conclude the discussion'),
        'th_conclude_confirm' => lang('Conclude the discussion? It is kept, read only, for 30 days and then deleted. The decisions and tasks it produced stay in the channel.'),
        'th_concluded'      => lang('The discussion was concluded.'),
        'th_rename'         => lang('Rename'),
        'th_rename_title'   => lang('The title of the discussion'),
        'th_people'         => lang('People in the discussion'),
        'th_add_people'     => lang('Bring people in'),
        'th_add_people_help' => lang('Only people who read the channel can take part.'),
        'th_leave'          => lang('Leave the discussion'),
        'th_leave_confirm'  => lang('Leave the discussion? You will no longer read it.'),
        'th_from'           => ws_js_template('From the discussion “{var:1}”', 1),
        'th_readonly'       => lang('The discussion is concluded; it can be read but not written in.'),
        'th_empty'          => lang('Nobody has written yet. Start the discussion.'),
        'th_show_all'       => ws_js_template('Show all ({var:1})', 1),
        'th_show_fewer'     => lang('Show fewer'),
    );
}
