<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what a person is told: a mention, a task handed to them, a task
 * of theirs finished, an invitation to a channel.
 *
 * Kept in the module's own inbox (ws_inbox), one row per person, rather than
 * in the panel's notification table: that table holds things everyone of a
 * role may see and decides per reader, while "Ayşe mentioned you" belongs to
 * one person only. The inbox is the badge on the menu item, the list on the
 * workspace screen, and - through the panel's push queue - the banner on a
 * phone.
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
 * Tells one person about one thing.
 *
 * A mention waits the chat's delay before it wakes a device: somebody reading
 * the channel as the message arrives reads the mention too, the inbox row is
 * marked read by that, and the queued banner is dropped unsent.
 *
 * @param int    $user_id
 * @param string $kind     mention | assigned | completed | invited | note | note_shared
 * @param array  $data     channel_id, message_id, task_id, note_id, actor_id
 * @return int the inbox row
 */
function ws_notify($user_id, $kind, $data)
{
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return 0;
    }

    $channel_id = (int) ($data['channel_id'] ?? 0);
    $message_id = (int) ($data['message_id'] ?? 0);
    $task_id = (int) ($data['task_id'] ?? 0);

    // A note handed over is named by the copy it became (4.110).
    $note_id = (function_exists('ws_notes_inbox_ready') && ws_notes_inbox_ready()) ? (int) ($data['note_id'] ?? 0) : 0;

    // The same thing twice is one row: an edit that mentions somebody again,
    // a task given to somebody who already had it.
    $existing = (int) db_value("SELECT id FROM ws_inbox
        WHERE user_id = '" . $user_id . "' AND kind = '" . e($kind) . "' AND read_at = 0
        AND channel_id = '" . $channel_id . "' AND message_id = '" . $message_id . "' AND task_id = '" . $task_id . "'"
        . (($note_id > 0) ? " AND note_id = '" . $note_id . "'" : '') . "
        LIMIT 1");

    if ($existing > 0) {
        return $existing;
    }

    db("INSERT INTO ws_inbox (user_id, kind, channel_id, message_id, task_id, " . (($note_id > 0) ? 'note_id, ' : '') . "actor_id, created_at)
        VALUES ('" . $user_id . "', '" . e($kind) . "', '" . $channel_id . "', '" . $message_id . "', '" . $task_id . "',
            " . (($note_id > 0) ? "'" . $note_id . "', " : '') . "'" . (int) ($data['actor_id'] ?? 0) . "', '" . time() . "')");

    $inbox_id = (int) mysqli_insert_id(db::$con);

    // The same thing in the person's bell, addressed to them alone (4.83).
    if ($inbox_id > 0) {
        ws_bell_add($user_id, $inbox_id);
    }

    if ($inbox_id > 0) {
        include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

        if (function_exists('pg_push_enqueue')) {
            // Somebody with the workspace open reads a mention on the screen,
            // so their device waits a minute and is not woken when they have.
            // Somebody who is not looking is woken now: the screen polls every
            // few seconds while it is open (ws_touch_seen()), so a person not
            // seen for WS_PUSH_SEEN seconds has closed it or looked away, and
            // the queue is worked right here instead of waiting for the
            // scheduled job, which not every site runs every minute.
            $looking = ws_seen_recently($user_id);
            $delay = (($kind === 'mention') && $looking) ? pg_push_chat_delay() : 0;

            pg_push_enqueue($user_id, 'ws', $inbox_id, $delay);

            if (($delay === 0) && function_exists('pg_push_queue_run')) {
                pg_push_queue_run(5);
            }
        }
    }

    return $inbox_id;
}

/**
 * Seconds without a look at the workspace after which a person counts as not
 * looking: the screen asks for news every 2 to 4 seconds while it is open and
 * in front, and stops when it is hidden or closed.
 */
if (!defined('WS_PUSH_SEEN')) {
    define('WS_PUSH_SEEN', 15);
}

/**
 * The workspace screen was just in front of this person. Kept in the panel's
 * own presence column, written at most every few seconds.
 *
 * @param int $user_id
 */
function ws_touch_seen($user_id)
{
    db("UPDATE user SET user_online_timestamp = UNIX_TIMESTAMP()
        WHERE user_id = '" . (int) $user_id . "' AND (UNIX_TIMESTAMP() - user_online_timestamp) > 5");
}

/**
 * Has this person had the workspace in front of them in the last moments?
 *
 * @param int $user_id
 * @return bool
 */
function ws_seen_recently($user_id)
{
    $seen = (int) db_value("SELECT user_online_timestamp FROM user WHERE user_id = '" . (int) $user_id . "'");

    return ($seen > 0) && ((time() - $seen) <= WS_PUSH_SEEN);
}

/**
 * Wakes the devices whose wait is over, from a screen that is asking for
 * news anyway: a site whose scheduled job runs rarely (or not at all, as on
 * many development machines) still gets its banners. One indexed read when
 * nothing is due.
 */
function ws_push_due_run()
{
    include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

    if (!function_exists('pg_push_queue_available') || !pg_push_queue_available()) {
        return;
    }

    if ((int) db_value("SELECT COUNT(*) FROM push_queue WHERE send_after <= UNIX_TIMESTAMP()") > 0) {
        pg_push_queue_run(3);
    }
}

/**
 * How many things wait for a person, for the menu badge.
 *
 * @param int $user_id
 * @return int
 */
function ws_inbox_unread_count($user_id)
{
    static $cache = array();

    $user_id = (int) $user_id;

    if (!isset($cache[$user_id])) {
        $cache[$user_id] = ws_ready()
            ? (int) db_value("SELECT COUNT(*) FROM ws_inbox WHERE user_id = '" . $user_id . "' AND read_at = 0")
            : 0;
    }

    return $cache[$user_id];
}

/**
 * One inbox row as a sentence, a place to go and a picture.
 *
 * @param array $viewer
 * @param array $row
 * @return array title, body, url, icon
 */
function ws_inbox_describe($viewer, $row)
{
    $actor = ws_person_name($row['actor_id']);
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $channel = ((int) $row['channel_id'] > 0) ? ws_channel($row['channel_id']) : null;
    $task = ((int) $row['task_id'] > 0) ? ws_task($row['task_id']) : null;
    $channel_name = $channel ? '#' . $channel['name'] : '';
    $task_title = $task ? ws_task_number($task['id']) . ' · ' . $task['title'] : '';

    switch ($row['kind']) {

        case 'mention':
            $message = ((int) $row['message_id'] > 0) ? ws_message($row['message_id']) : null;

            // Written by an application (Claude): its name, not a person's.
            if ($message && ($message['sender_kind'] === 'app')) {
                $actor = (string) (ws_app_sender((int) $message['sender_id'])['name'] ?? $actor);
            }

            return array(
                'title' => lang(array('string' => '{var:1} mentioned you in {var:2}', 'vars' => array($actor, $channel_name))),
                'body'  => $message ? ws_plain_excerpt($viewer, $message['body'], 140) : '',
                'url'   => $base . 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'],
                'icon'  => 'bi-at',
            );

        case 'assigned':
            return array(
                'title' => lang(array('string' => '{var:1} gave you a task', 'vars' => $actor)),
                'body'  => $task_title . ($task ? ' — ' . ws_task_due_label($task) : ''),
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => 'bi-check2-square',
            );

        case 'completed':
            return array(
                'title' => lang(array('string' => '{var:1} finished a task you created', 'vars' => $actor)),
                'body'  => $task_title,
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => 'bi-check2-circle',
            );

        case 'note':
            return array(
                'title' => lang(array('string' => '{var:1} mentioned you in a note on {var:2}', 'vars' => array($actor, $task ? ws_task_number($task['id']) : ''))),
                'body'  => $task ? (string) $task['title'] : '',
                'url'   => $base . 'workspace_tasks.php?task=' . (int) $row['task_id'],
                'icon'  => 'bi-journal-text',
            );

        case 'invited':
            return array(
                'title' => lang(array('string' => '{var:1} added you to {var:2}', 'vars' => array($actor, $channel_name))),
                'body'  => $channel ? (string) $channel['topic'] : '',
                'url'   => $base . 'workspace.php?channel=' . (int) $row['channel_id'],
                'icon'  => 'bi-hash',
            );

        case 'note_shared':
            $note = function_exists('ws_note') ? ws_note((int) ($row['note_id'] ?? 0)) : null;

            return array(
                'title' => lang(array('string' => '{var:1} shared a note with you', 'vars' => $actor)),
                'body'  => $note ? ws_note_title($viewer, $note) : '',
                'url'   => $base . 'workspace_notes.php' . ($note ? '?note=' . (int) $note['id'] : ''),
                'icon'  => 'bi-journal-arrow-down',
            );
    }

    return array('title' => lang('Workspace'), 'body' => '', 'url' => $base . 'workspace.php', 'icon' => 'bi-bell');
}

/**
 * A person's inbox, newest first. Rows about a channel or a task they can no
 * longer see are left out, and marked read so the badge does not count them.
 *
 * @param array $viewer
 * @param int   $limit
 * @return array[]
 */
function ws_inbox_list($viewer, $limit = 50)
{
    $rows = (array) db_items("SELECT * FROM ws_inbox WHERE user_id = '" . (int) $viewer['id'] . "'
        ORDER BY read_at = 0 DESC, id DESC LIMIT " . max(1, min(200, (int) $limit)));

    $out = array();
    $hidden = array();

    foreach ($rows as $row) {
        if (!ws_inbox_row_visible($viewer, $row)) {
            $hidden[] = (int) $row['id'];
            continue;
        }

        $describe = ws_inbox_describe($viewer, $row);

        $out[] = $describe + array(
            'id'     => (int) $row['id'],
            'kind'   => (string) $row['kind'],
            'unread' => ((int) $row['read_at'] === 0),
            'time'   => ws_time_label($row['created_at']),
            'actor'  => current(ws_people(array($row['actor_id']))) ?: null,
        );
    }

    if (!empty($hidden)) {
        db("UPDATE ws_inbox SET read_at = '" . time() . "' WHERE read_at = 0 AND id IN (" . implode(',', $hidden) . ")");
    }

    return $out;
}

/**
 * May the person still see what the row is about?
 *
 * @param array $viewer
 * @param array $row
 * @return bool
 */
function ws_inbox_row_visible($viewer, $row)
{
    if (!$viewer['member']) {
        return false;
    }

    if (((int) $row['channel_id'] > 0) && !ws_can_read_channel($viewer, ws_channel($row['channel_id']))) {
        return false;
    }

    if (((int) $row['task_id'] > 0)) {
        $task = ws_task($row['task_id']);

        if (!$task || !ws_can_see_task($viewer, $task)) {
            return false;
        }
    }

    if (((int) $row['message_id'] > 0)) {
        $message = ws_message($row['message_id']);

        if (!$message || ((int) $message['deleted_at'] > 0)) {
            return false;
        }
    }

    // A note shared is there as long as it is shared with the person.
    if ($row['kind'] === 'note_shared') {
        $note = function_exists('ws_note') ? ws_note((int) ($row['note_id'] ?? 0)) : null;

        if (!$note || (ws_note_access($viewer, $note) === '')) {
            return false;
        }
    }

    return true;
}

/**
 * Marks inbox rows read: some, or all of them.
 *
 * @param int         $user_id
 * @param int[]|string $ids 'all' for every one
 */
function ws_inbox_mark_read($user_id, $ids)
{
    $where = "user_id = '" . (int) $user_id . "' AND read_at = 0";

    if ($ids !== 'all') {
        $clean = array_filter(array_map('intval', (array) $ids));

        if (empty($clean)) {
            return;
        }

        $where .= " AND id IN (" . implode(',', $clean) . ")";
    }

    db("UPDATE ws_inbox SET read_at = '" . time() . "' WHERE " . $where);

    ws_bell_sync_read($user_id);
}

/**
 * Can the bell carry rows addressed to one person (4.83)?
 *
 * @return bool
 */
function ws_bell_ready()
{
    static $ready = null;

    if ($ready === null) {
        include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

        $ready = function_exists('pg_notification_targets_available') && pg_notification_targets_available();
    }

    return $ready;
}

/**
 * Puts an inbox row into its person's bell. The sentence is written into the
 * row as it reads now, for the day the inbox row is no longer there; the bell
 * describes it afresh from the inbox row while it is.
 *
 * @param int $user_id
 * @param int $inbox_id
 */
function ws_bell_add($user_id, $inbox_id)
{
    if (!ws_bell_ready()) {
        return;
    }

    $row = db_item("SELECT * FROM ws_inbox WHERE id = '" . (int) $inbox_id . "'");

    if (!is_array($row)) {
        return;
    }

    $described = ws_inbox_describe(ws_rights_for_id($user_id) + array('ecommerce' => false, 'contacts' => false, 'erp' => false), $row);

    db("INSERT INTO notifications (action, type, title, target_user_id, reference_id, user, readed, timestamp)
        VALUES ('workspace', 'info', '" . e(mb_substr($described['title'], 0, 250)) . "', '" . (int) $user_id . "', '" . (int) $inbox_id . "',
            '" . e((string) ($_SESSION['sessionusername'] ?? '')) . "', '0', UNIX_TIMESTAMP())");
}

/**
 * What a bell row about an inbox row says, for the person it is addressed
 * to. Null when the row is gone or no longer theirs to see.
 *
 * @param int $inbox_id
 * @param int $user_id
 * @return array|null title, body, url
 */
function ws_bell_describe($inbox_id, $user_id)
{
    $row = db_item("SELECT * FROM ws_inbox WHERE id = '" . (int) $inbox_id . "' AND user_id = '" . (int) $user_id . "'");

    if (!is_array($row)) {
        return null;
    }

    $user = function_exists('pg_load_user_row') ? pg_load_user_row((int) $user_id) : null;
    $viewer = is_array($user) ? ws_viewer($user) : ws_rights_for_id($user_id) + array('ecommerce' => false, 'contacts' => false, 'erp' => false);

    if (!ws_inbox_row_visible($viewer, $row)) {
        return null;
    }

    $described = ws_inbox_describe($viewer, $row);

    return array('title' => $described['title'], 'body' => $described['body'], 'url' => $described['url']);
}

/**
 * Whatever the workspace marked read is read in the bell as well.
 *
 * @param int $user_id
 */
function ws_bell_sync_read($user_id)
{
    if (!ws_bell_ready() || !function_exists('pg_notification_reads_available') || !pg_notification_reads_available()) {
        return;
    }

    $user_id = (int) $user_id;

    db("INSERT IGNORE INTO notification_reads (notification_id, user_id, timestamp)
        SELECT n.id, '" . $user_id . "', '" . time() . "'
        FROM notifications n
        INNER JOIN ws_inbox i ON i.id = n.reference_id AND i.read_at > 0
        WHERE n.action = 'workspace' AND n.target_user_id = '" . $user_id . "'");
}

/**
 * Is a queued device banner still worth sending? Asked by the push queue at
 * send time, after the wait.
 *
 * @param int $user_id
 * @param int $inbox_id
 * @return bool
 */
function ws_push_still_relevant($user_id, $inbox_id)
{
    if (!ws_ready()) {
        return false;
    }

    $row = db_item("SELECT * FROM ws_inbox WHERE id = '" . (int) $inbox_id . "' AND user_id = '" . (int) $user_id . "'");

    if (!is_array($row) || ((int) $row['read_at'] > 0)) {
        return false;
    }

    return ws_inbox_row_visible(ws_rights_for_id($user_id), $row);
}

/**
 * What waits for a person, as the service worker draws banners.
 *
 * @param array $user validate_user() array
 * @param int   $limit
 * @return array[]
 */
function ws_push_pending($user, $limit = 3)
{
    if (!ws_enabled()) {
        return array();
    }

    $viewer = ws_viewer($user);

    if (!$viewer['member']) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_inbox WHERE user_id = '" . (int) $viewer['id'] . "' AND read_at = 0
        ORDER BY id ASC LIMIT 20");

    $out = array();

    foreach ($rows as $row) {
        if (!ws_inbox_row_visible($viewer, $row)) {
            continue;
        }

        $describe = ws_inbox_describe($viewer, $row);

        $out[] = array(
            'id'        => 'ws-' . (int) $row['id'],
            'title'     => $describe['title'],
            'body'      => $describe['body'],
            'url'       => $describe['url'],
            'icon'      => 'assets/images/notification-chat.png',
            'badge'     => 'assets/images/notification-chat-badge.png',
            'tag'       => 'pg-ws-' . (int) $row['id'],
            'timestamp' => (int) $row['created_at'],
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}
