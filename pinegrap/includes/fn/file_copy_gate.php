<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the chat attachment check of the doors that
// copy a file somewhere else (File Manager paste, folder copy, zip archives,
// the Workspace file picker).
//
// get_file.php opens a panel team chat attachment only for a side of a
// conversation carrying it or a role-0 administrator, whatever its folder
// allows (pg_chat_attachment_access_verdict()). A copy, an archive entry or
// a channel copy is a new file no chat message carries, so get_file.php
// serves it by its folder alone: each such door asks here first.
//
// Loaded by functions.php through require_once, never on its own.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The file ids of $rows this person may not copy. $rows are chat attachment
// rows (file_id, initiator_user_id, target_user_id), one per conversation
// carrying the file; a file opens for a side of any one of them.
function pg_chat_attachment_refused_from_rows($rows, $user_id, $role)
{
    $by_file = array();

    foreach ((array) $rows as $row) {
        $by_file[(int) $row['file_id']][] = $row;
    }

    $refused = array();

    foreach ($by_file as $file_id => $conversations) {
        if (($file_id > 0) && !pg_chat_attachment_access_verdict($user_id, $role, $conversations)) {
            $refused[] = (int) $file_id;
        }
    }

    return $refused;
}

// The ids among $file_ids that are panel chat attachments $user (id, role)
// may not open, read as get_file.php reads them. Before the chat tables or
// the attachment column exist the query fails and nothing is refused.
function pg_chat_attachment_refused_ids($file_ids, $user)
{
    $ids = array();

    foreach ((array) $file_ids as $file_id) {
        if ((int) $file_id > 0) {
            $ids[(int) $file_id] = (int) $file_id;
        }
    }

    if (!$ids) {
        return array();
    }

    $result = @mysqli_query(db::$con, "SELECT m.attachment_file_id AS file_id, c.initiator_user_id, c.target_user_id
        FROM chat_messages m
        INNER JOIN chat_conversations c ON c.id = m.conversation_id AND c.channel = 'backend'
        WHERE m.attachment_file_id IN (" . implode(',', $ids) . ")");
    $rows = array();

    while (($result !== false) && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return pg_chat_attachment_refused_from_rows($rows, (int) $user['id'], (int) $user['role']);
}

// Whether $user may not copy this one file (pg_chat_attachment_refused_ids()).
function pg_chat_attachment_refused($file_id, $user)
{
    return in_array((int) $file_id, pg_chat_attachment_refused_ids(array($file_id), $user), true);
}
