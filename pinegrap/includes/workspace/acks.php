<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - read receipts: a message whose readers are asked to say they
 * have read it - a new rule, a price list, a change of plan - with a "Read
 * it" button under it and a count of who has.
 *
 * The person who wrote the message, or staff (roles 0-2), ask for it from the
 * message's menu, the pinned message's too. Everybody in the channel at the
 * moment is counted, except the writer: somebody who leaves the channel drops
 * out of the count, somebody who joins comes into it. Guests of a room are
 * not members of the channel and are never counted. The writer, whoever
 * asked and staff see who has read it and who has not.
 *
 * A day after it was asked, the people who have not said so get one line in
 * their inbox; the reminder runs with the scheduled actions
 * (ws_scheduled_run()).
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
 * How long after it was asked the people who have not read a message are
 * reminded, in seconds.
 */
define('WS_ACK_REMIND_AFTER', 86400);

/**
 * Are the tables there (2026.4.8, 8.89)?
 *
 * @return bool
 */
function ws_acks_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ws_ack_requests', 'ws_acks')") === 2);
    }

    return $ready;
}

/**
 * The person who wrote a message, when a person did.
 *
 * @param array $message
 * @return int 0 for an application or a guest
 */
function ws_ack_author($message)
{
    return ((string) $message['sender_kind'] === 'user') ? (int) $message['sender_id'] : 0;
}

/**
 * Who is counted for a message of a channel: its members now who are in the
 * team, the writer left out.
 *
 * @param int $channel_id
 * @param int $author_id
 * @return int[]
 */
function ws_ack_counted_ids($channel_id, $author_id)
{
    static $members = array();

    $channel_id = (int) $channel_id;
    $key = (int) ($GLOBALS['ws_membership_generation'] ?? 0) . ':' . $channel_id;

    if (!isset($members[$key])) {
        $team = array_flip(ws_team_ids());
        $members[$key] = array();

        foreach ((array) db_values("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . $channel_id . "' ORDER BY joined_at") as $user_id) {
            if (isset($team[(int) $user_id])) {
                $members[$key][] = (int) $user_id;
            }
        }
    }

    return array_values(array_diff($members[$key], array((int) $author_id)));
}

/**
 * May this person ask the readers of the message to say they read it, or
 * take that back? The writer and staff, while they may write in the channel.
 *
 * @param array $viewer
 * @param array $message
 * @return bool
 */
function ws_ack_can_request($viewer, $message)
{
    if (!ws_acks_ready() || !is_array($message) || ((int) $message['deleted_at'] > 0)
        || !in_array((string) $message['kind'], array('message', 'note', 'decision'), true)
        || !in_array((string) $message['sender_kind'], array('user', 'app'), true)
        || ws_message_in_past($message)) {
        return false;
    }

    if ((ws_ack_author($message) !== (int) $viewer['id']) && ((int) $viewer['role'] >= 3)) {
        return false;
    }

    return ws_can_post_channel($viewer, ws_channel($message['channel_id']));
}

/**
 * Asks the readers of a message to say they read it, or takes it back
 * (with the answers given).
 *
 * @param array $viewer
 * @param array $message
 * @param bool  $on
 * @return array ok, error
 */
function ws_ack_request($viewer, $message, $on)
{
    if (!ws_ack_can_request($viewer, $message)) {
        return array('ok' => false, 'error' => lang('Only the writer of the message, or staff, can ask for read receipts.'));
    }

    if ($on) {
        db("INSERT IGNORE INTO ws_ack_requests (message_id, requested_by, requested_at)
            VALUES ('" . (int) $message['id'] . "', '" . (int) $viewer['id'] . "', '" . time() . "')");
    } else {
        db("DELETE FROM ws_ack_requests WHERE message_id = '" . (int) $message['id'] . "'");
        db("DELETE FROM ws_acks WHERE message_id = '" . (int) $message['id'] . "'");
    }

    ws_message_touch($message['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * The person says they read the message. Only the people counted for it,
 * once.
 *
 * @param array $viewer
 * @param array $message
 * @return array ok, error
 */
function ws_ack_set($viewer, $message)
{
    if (!ws_acks_ready() || !is_array($message) || ((int) $message['deleted_at'] > 0)) {
        return array('ok' => false, 'error' => lang('That message could not be found.'));
    }

    if (!db_value("SELECT message_id FROM ws_ack_requests WHERE message_id = '" . (int) $message['id'] . "'")) {
        return array('ok' => false, 'error' => lang('Nobody asked for a read receipt on this message.'));
    }

    if (!ws_can_read_channel($viewer, ws_channel($message['channel_id']))
        || !in_array((int) $viewer['id'], ws_ack_counted_ids($message['channel_id'], ws_ack_author($message)), true)) {
        return array('ok' => false, 'error' => lang('Only the people of the channel are asked to read it.'));
    }

    db("INSERT IGNORE INTO ws_acks (message_id, user_id, acked_at)
        VALUES ('" . (int) $message['id'] . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

    // The inbox line that reminded them has done its work.
    db("UPDATE ws_inbox SET read_at = '" . time() . "'
        WHERE user_id = '" . (int) $viewer['id'] . "' AND kind = 'ack_reminder' AND message_id = '" . (int) $message['id'] . "' AND read_at = 0");

    ws_message_touch($message['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * The read receipts asked for on a set of messages, as this reader sees
 * them, and whether the reader may ask for one on the others.
 *
 * @param array[] $rows   ws_messages rows
 * @param array   $viewer
 * @return array message id => wanted, count, total, mine, done, can_ack, can_list (null when none was asked for)
 */
function ws_acks_map($rows, $viewer)
{
    $ids = array();

    foreach ((array) $rows as $row) {
        $ids[] = (int) $row['id'];
    }

    $ids = array_values(array_filter($ids));

    if (empty($ids) || !ws_acks_ready()) {
        return array();
    }

    $requests = array();

    foreach ((array) db_items("SELECT * FROM ws_ack_requests WHERE message_id IN (" . implode(',', $ids) . ")") as $request) {
        $requests[(int) $request['message_id']] = $request;
    }

    if (empty($requests)) {
        return array();
    }

    $acks = array();

    foreach ((array) db_items("SELECT message_id, user_id FROM ws_acks WHERE message_id IN (" . implode(',', array_keys($requests)) . ")") as $ack) {
        $acks[(int) $ack['message_id']][(int) $ack['user_id']] = true;
    }

    $guest = ((int) ($viewer['guest_id'] ?? 0) > 0);
    $out = array();

    foreach ((array) $rows as $row) {
        $id = (int) $row['id'];

        if (!isset($requests[$id])) {
            continue;
        }

        $author = ws_ack_author($row);
        $counted = ws_ack_counted_ids($row['channel_id'], $author);
        $read = 0;

        foreach ($counted as $user_id) {
            if (isset($acks[$id][$user_id])) {
                $read++;
            }
        }

        $me = (int) $viewer['id'];
        $counted_me = !$guest && in_array($me, $counted, true);

        $out[$id] = array(
            'wanted'   => true,
            'count'    => $read,
            'total'    => count($counted),
            'mine'     => $counted_me && isset($acks[$id][$me]),
            'done'     => ($read > 0) && ($read === count($counted)),
            'can_ack'  => $counted_me && !isset($acks[$id][$me]) && ((int) $row['deleted_at'] === 0),
            'can_list' => !$guest && (($author === $me) || ((int) $requests[$id]['requested_by'] === $me) || ((int) ($viewer['role'] ?? 3) < 3)),
        );
    }

    return $out;
}

/**
 * Who has read a message and who has not, for the writer, whoever asked and
 * staff.
 *
 * @param array $viewer
 * @param array $message
 * @return array ok, error, read (id, name, avatar, time), unread (id, name, avatar)
 */
function ws_ack_people($viewer, $message)
{
    $map = ws_acks_map(array($message), $viewer);

    if (!isset($map[(int) $message['id']])) {
        return array('ok' => false, 'error' => lang('Nobody asked for a read receipt on this message.'));
    }

    if (!$map[(int) $message['id']]['can_list']) {
        return array('ok' => false, 'error' => lang('Only the writer of the message, or staff, can see who read it.'));
    }

    $counted = ws_ack_counted_ids($message['channel_id'], ws_ack_author($message));
    $when = array();

    foreach ((array) db_items("SELECT user_id, acked_at FROM ws_acks WHERE message_id = '" . (int) $message['id'] . "' ORDER BY acked_at") as $ack) {
        $when[(int) $ack['user_id']] = (int) $ack['acked_at'];
    }

    $people = ws_people($counted);
    $read = array();
    $unread = array();

    foreach ($counted as $user_id) {
        $person = $people[$user_id] ?? array('id' => $user_id, 'name' => ws_person_name($user_id), 'avatar' => '');
        $entry = array('id' => $user_id, 'name' => (string) $person['name'], 'avatar' => (string) ($person['avatar'] ?? ''), 'avatar_kind' => (string) ($person['avatar_kind'] ?? ''));

        if (isset($when[$user_id])) {
            $entry['time'] = ws_time_label($when[$user_id]);
            $entry['at'] = $when[$user_id];
            $read[] = $entry;
        } else {
            $unread[] = $entry;
        }
    }

    usort($read, function ($a, $b) { return $a['at'] - $b['at']; });

    foreach ($read as $index => $entry) {
        unset($read[$index]['at']);
    }

    return array('ok' => true, 'error' => '', 'read' => array_values($read), 'unread' => $unread);
}

/**
 * Is a reminder due? One indexed read (idx_remind), for ws_scheduled_due().
 *
 * @return bool
 */
function ws_acks_due()
{
    if (!ws_acks_ready()) {
        return false;
    }

    return (bool) db_value("SELECT message_id FROM ws_ack_requests
        WHERE reminded_at = 0 AND requested_at <= '" . (time() - WS_ACK_REMIND_AFTER) . "'
        LIMIT 1");
}

/**
 * Reminds, once, the people who a day after it was asked have not said they
 * read a message. Run with the scheduled actions; a few at a time.
 *
 * @param int $limit
 * @return int how many were reminded
 */
function ws_acks_run($limit = 20)
{
    if (!ws_acks_ready()) {
        return 0;
    }

    $now = time();
    $reminded = 0;

    foreach ((array) db_items("SELECT * FROM ws_ack_requests
        WHERE reminded_at = 0 AND requested_at <= '" . ($now - WS_ACK_REMIND_AFTER) . "'
        ORDER BY requested_at LIMIT " . max(1, (int) $limit)) as $request) {
        // Claimed first: two runs that overlap remind once.
        db("UPDATE ws_ack_requests SET reminded_at = '" . $now . "' WHERE message_id = '" . (int) $request['message_id'] . "' AND reminded_at = 0");

        if (mysqli_affected_rows(db::$con) < 1) {
            continue;
        }

        $message = ws_message($request['message_id']);

        if (!$message || ((int) $message['deleted_at'] > 0)) {
            continue;
        }

        $read = array_flip(array_map('intval', (array) db_values("SELECT user_id FROM ws_acks WHERE message_id = '" . (int) $message['id'] . "'")));

        foreach (ws_ack_counted_ids($message['channel_id'], ws_ack_author($message)) as $user_id) {
            if (!isset($read[$user_id])) {
                ws_notify($user_id, 'ack_reminder', array('channel_id' => (int) $message['channel_id'], 'message_id' => (int) $message['id'], 'actor_id' => (int) $request['requested_by']));
                $reminded++;
            }
        }
    }

    return $reminded;
}

/**
 * The messages waiting for this person to say they read them, newest first:
 * for the overview.
 *
 * @param array $viewer
 * @param int   $limit
 * @return array[]
 */
function ws_acks_waiting($viewer, $limit = 5)
{
    if (!ws_acks_ready()) {
        return array();
    }

    $me = (int) $viewer['id'];
    $rows = (array) db_items("SELECT m.*, r.requested_by FROM ws_ack_requests r
        INNER JOIN ws_messages m ON m.id = r.message_id AND m.deleted_at = 0
        INNER JOIN ws_channel_members cm ON cm.channel_id = m.channel_id AND cm.user_id = '" . $me . "'
        LEFT JOIN ws_acks a ON a.message_id = r.message_id AND a.user_id = '" . $me . "'
        WHERE a.user_id IS NULL AND NOT (m.sender_kind = 'user' AND m.sender_id = '" . $me . "')
        ORDER BY r.requested_at DESC LIMIT 20");

    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php';
    $out = array();

    foreach ($rows as $row) {
        $channel = ws_channel($row['channel_id']);

        if (!$channel || !ws_can_read_channel($viewer, $channel)) {
            continue;
        }

        $text = ws_plain_excerpt($viewer, (string) $row['body'], 140);

        $out[] = array(
            'message_id' => (int) $row['id'],
            'text'       => ($text !== '') ? $text : (string) $row['file_name'],
            'asker'      => ws_person_name($row['requested_by']),
            'time'       => ws_time_label($row['created_at']),
            'channel'    => array('id' => (int) $channel['id'], 'name' => (string) $channel['name'], 'private' => ($channel['kind'] !== 'public')),
            'url'        => $base . '?channel=' . (int) $channel['id'] . '&message=' . (int) $row['id'],
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * The texts of read receipts on the channel screen and the overview.
 *
 * @return array key => text
 */
function ws_acks_js_strings()
{
    return array(
        'ack_request'      => lang('Ask for read receipts'),
        'ack_unrequest'    => lang('Stop asking for read receipts'),
        'ack_unrequest_confirm' => lang('Stop asking for read receipts? The answers given so far are dropped.'),
        'ack_button'       => lang('I have read it'),
        'ack_done_mine'    => lang('You read it'),
        'ack_count'        => ws_js_template('{var:1} / {var:2} read', 2),
        'ack_people'       => lang('Who has read it'),
        'ack_read'         => lang('Has read'),
        'ack_unread'       => lang('Not yet'),
        'ack_nobody_left'  => lang('Everybody has read it.'),
        'ack_home'         => lang('Messages waiting for your read receipt'),
        'ack_home_asked'   => ws_js_template('{var:1} asks', 1),
    );
}
