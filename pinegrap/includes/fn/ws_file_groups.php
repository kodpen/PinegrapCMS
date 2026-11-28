<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Files sent together in a Workspace channel: one group, read as one
 * message. A message still carries one file; ws_messages.file_group holds
 * the id of the group's first message, the head, which carries its own id,
 * and 0 is a message alone. A group is made when a second file arrives
 * naming the head: the new message is written with the head's id and the
 * head is marked with its own. A file sent alone writes nothing.
 *
 * ws_file_group_check() says, without a database, whether a message may
 * take one more file; ws_file_group_head() reads the head and asks it;
 * ws_file_group_mark() writes the member and the head. A refused group never
 * loses a file: the caller posts it alone.
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
 * The most files a group holds; the next one starts a group of its own.
 */
define('WS_FILE_GROUP_MAX', 10);

/**
 * How long after the head a file may still join its group, in seconds: a
 * send of many files takes a while, a file sent later is a message of its
 * own.
 */
define('WS_FILE_GROUP_SECONDS', 600);

/**
 * Whether the database has the group column (upgrade step 11.0.291).
 * Before it every file is posted alone, as it always was.
 *
 * @return bool
 */
function ws_file_group_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_messages', 'file_group');
    }

    return $ready;
}

/**
 * Whether a message may take one more file into its group. Pure.
 *
 * The head must be in the same channel as the new file (a discussion is a
 * channel of its own, so the same discussion too), written by the same
 * sender, carry a file, not be deleted, be under WS_FILE_GROUP_SECONDS old,
 * be no member of another group, and its group must hold fewer than
 * WS_FILE_GROUP_MAX files.
 *
 * @param array|null $head   the head's ws_messages row: id, channel_id,
 *                           sender_kind, sender_id, file_id, deleted_at,
 *                           created_at, file_group
 * @param array      $sender channel_id, sender_kind (user | app), sender_id
 * @param int        $files  the files the group holds now, the head's own included
 * @param int        $now    a Unix time
 * @return bool
 */
function ws_file_group_check($head, $sender, $files, $now)
{
    if (!is_array($head) || ((int) ($head['id'] ?? 0) <= 0)) {
        return false;
    }

    $head_id = (int) $head['id'];
    $group = (int) ($head['file_group'] ?? 0);
    $created = (int) ($head['created_at'] ?? 0);

    return ((int) ($head['channel_id'] ?? 0) > 0)
        && ((int) $head['channel_id'] === (int) ($sender['channel_id'] ?? 0))
        && ((string) ($head['sender_kind'] ?? '') === (string) ($sender['sender_kind'] ?? ''))
        && ((int) ($head['sender_id'] ?? 0) > 0)
        && ((int) $head['sender_id'] === (int) ($sender['sender_id'] ?? 0))
        && ((int) ($head['file_id'] ?? 0) > 0)
        && ((int) ($head['deleted_at'] ?? 0) === 0)
        && ($created > 0)
        && (((int) $now - $created) < WS_FILE_GROUP_SECONDS)
        && (($group === 0) || ($group === $head_id))
        && ((int) $files < WS_FILE_GROUP_MAX);
}

/**
 * The head a new file may join, read from the database and checked by
 * ws_file_group_check(); 0 when there is none to join, the column is
 * missing or the file is a voice recording, which is never a member.
 *
 * @param array  $sender  channel_id, sender_kind (user | app), sender_id
 * @param int    $head_id the message the sender named as the head
 * @param string $name    the new file's name
 * @return int the head's id, or 0 to post the file alone
 */
function ws_file_group_head($sender, $head_id, $name)
{
    $head_id = (int) $head_id;

    if (($head_id <= 0) || !ws_file_group_ready()) {
        return 0;
    }

    if (function_exists('ws_voice_is_recording') && ws_voice_is_recording($name)) {
        return 0;
    }

    $result = db_q("SELECT id, channel_id, sender_kind, sender_id, file_id, file_name, deleted_at, created_at, file_group
        FROM ws_messages WHERE id = ?", array($head_id));
    $head = $result ? mysqli_fetch_assoc($result) : null;

    if (!is_array($head)) {
        return 0;
    }

    // A recording heads no group either.
    if (function_exists('ws_voice_is_recording') && ws_voice_is_recording((string) $head['file_name'])) {
        return 0;
    }

    // An unmarked head is a group of its own file alone.
    $files = max(1, (int) db_value("SELECT COUNT(*) FROM ws_messages
        WHERE file_group = '" . $head_id . "' AND deleted_at = 0"));

    return ws_file_group_check($head, $sender, $files, time()) ? $head_id : 0;
}

/**
 * Writes a posted file into its head's group and marks the head with its
 * own id, which a head already marked keeps.
 *
 * @param int $message_id the message just posted with the file
 * @param int $head_id    ws_file_group_head()'s answer
 * @return bool whether the member was written
 */
function ws_file_group_mark($message_id, $head_id)
{
    $message_id = (int) $message_id;
    $head_id = (int) $head_id;

    if (($message_id <= 0) || ($head_id <= 0) || ($message_id === $head_id) || !ws_file_group_ready()) {
        return false;
    }

    if (!db_q("UPDATE ws_messages SET file_group = ? WHERE id = ?", array($head_id, $message_id))) {
        return false;
    }

    db_q("UPDATE ws_messages SET file_group = ? WHERE id = ? AND file_group = 0", array($head_id, $head_id));

    return true;
}
