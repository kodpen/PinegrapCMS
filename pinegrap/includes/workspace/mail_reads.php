<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: who has read which e-mail conversation (ws_mail_reads). A
 * conversation is unread for a person while its newest incoming mail is
 * past both their mark on it and their floor, the row with conversation 0
 * that says everything up to that mail is read. The floor is written at
 * the newest mail the first time a person's count is asked for, so nobody
 * starts with a backlog, and moved by Mark all as read. Opening a
 * conversation in the E-mails screen's pane, or its card and replies in
 * the channel, marks it.
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
 * Whether the read marks' table is there (step 11.0.185).
 *
 * @return bool
 */
function ws_mail_reads_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_mail_ready() && pg_schema_has('ws_mail_reads');
    }

    return $ready;
}

/**
 * Whether a conversation is unread. Pure.
 *
 * @param int $last_in_id the newest incoming mail of the conversation
 * @param int $mark       the person's mark on it, 0 for none
 * @param int $floor      the person's floor
 * @return bool
 */
function ws_mail_unread($last_in_id, $mark, $floor)
{
    return (int) $last_in_id > max((int) $mark, (int) $floor);
}

/**
 * The condition on ws_mail_conversations rows that keeps the unread ones,
 * for a query that names the table without an alias. Pure.
 *
 * @param int $user_id
 * @param int $floor
 * @return string
 */
function ws_mail_unread_sql($user_id, $floor)
{
    return "(SELECT MAX(i.id) FROM ws_mail_items i WHERE i.conversation_id = ws_mail_conversations.id AND i.direction = 'in')"
        . " > GREATEST('" . (int) $floor . "', COALESCE((SELECT r.item_id FROM ws_mail_reads r"
        . " WHERE r.conversation_id = ws_mail_conversations.id AND r.user_id = '" . (int) $user_id . "'), 0))";
}

/**
 * The person's floor, written at the newest mail when they have none.
 *
 * @param int $user_id
 * @return int
 */
function ws_mail_read_floor($user_id)
{
    $floor = db_value("SELECT item_id FROM ws_mail_reads WHERE conversation_id = 0 AND user_id = '" . (int) $user_id . "'");

    if ($floor !== null && $floor !== false) {
        return (int) $floor;
    }

    $newest = (int) db_value("SELECT MAX(id) FROM ws_mail_items");
    ws_mail_read_mark($user_id, 0, $newest);

    return $newest;
}

/**
 * The person's marks on some conversations.
 *
 * @param int   $user_id
 * @param int[] $conversation_ids
 * @return array conversation id => item id
 */
function ws_mail_read_marks($user_id, $conversation_ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $conversation_ids)));
    $out = array();

    if (!$ids) {
        return $out;
    }

    foreach ((array) db_items("SELECT conversation_id, item_id FROM ws_mail_reads
        WHERE user_id = '" . (int) $user_id . "' AND conversation_id IN (" . implode(',', $ids) . ")") as $row) {
        $out[(int) $row['conversation_id']] = (int) $row['item_id'];
    }

    return $out;
}

/**
 * Marks a conversation read up to a mail; a mark never moves back.
 * Conversation 0 is the floor.
 *
 * @param int $user_id
 * @param int $conversation_id
 * @param int $item_id
 * @return void
 */
function ws_mail_read_mark($user_id, $conversation_id, $item_id)
{
    if ((int) $user_id <= 0) {
        return;
    }

    db("INSERT INTO ws_mail_reads (conversation_id, user_id, item_id)
        VALUES ('" . (int) $conversation_id . "', '" . (int) $user_id . "', '" . max(0, (int) $item_id) . "')
        ON DUPLICATE KEY UPDATE item_id = GREATEST(item_id, VALUES(item_id))");
}

/**
 * The mails behind the mailbox's messages among some channel messages.
 * Pure.
 *
 * @param array[] $rows ws_messages rows
 * @return int[] ws_mail_items ids
 */
function ws_mail_read_item_ids($rows)
{
    $ids = array();

    foreach ((array) $rows as $row) {
        if (((string) ($row['sender_kind'] ?? '') === 'mail') && ((int) ($row['sender_id'] ?? 0) > 0)) {
            $ids[(int) $row['sender_id']] = (int) $row['sender_id'];
        }
    }

    return array_values($ids);
}

/**
 * Marks read the conversations whose card or replies a person was just
 * shown in a channel, each up to the newest of its mails among them.
 *
 * @param array   $viewer
 * @param array[] $rows   ws_messages rows the screen drew
 * @return void
 */
function ws_mail_read_rows($viewer, $rows)
{
    $ids = ws_mail_read_item_ids($rows);

    if (!$ids || !ws_mail_reads_ready()) {
        return;
    }

    foreach ((array) db_items("SELECT conversation_id, MAX(id) AS item_id FROM ws_mail_items
        WHERE id IN (" . implode(',', $ids) . ") AND conversation_id > 0 GROUP BY conversation_id") as $row) {
        ws_mail_read_mark((int) $viewer['id'], (int) $row['conversation_id'], (int) $row['item_id']);
    }
}

/**
 * Mark all as read: the floor moves to the newest mail and the marks it
 * covers go.
 *
 * @param array $viewer
 * @return void
 */
function ws_mail_read_all($viewer)
{
    $user_id = (int) $viewer['id'];
    $newest = (int) db_value("SELECT MAX(id) FROM ws_mail_items");

    ws_mail_read_mark($user_id, 0, $newest);
    db("DELETE FROM ws_mail_reads WHERE user_id = '" . $user_id . "' AND conversation_id > 0 AND item_id <= '" . $newest . "'");
}

/**
 * How many open conversations of the channels a person reads are unread
 * for them: the number on the E-mails tile of the menu.
 *
 * @param array $viewer
 * @return int
 */
function ws_mail_unread_count($viewer)
{
    if (!ws_mail_reads_ready() || empty($viewer['member'])) {
        return 0;
    }

    $where = ws_mail_list_where(array('view' => 'all', 'status' => 'open', 'holder' => 'all', 'overdue' => false), (int) $viewer['id'], ws_mail_list_channels($viewer));

    if ($where === '') {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM ws_mail_conversations WHERE " . $where
        . " AND " . ws_mail_unread_sql((int) $viewer['id'], ws_mail_read_floor((int) $viewer['id'])));
}
