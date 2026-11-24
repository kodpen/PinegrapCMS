<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Who get_file.php serves a file of a Workspace channel's folder to. The
// folder is private: staff pass every private folder of the file manager and
// a user passes the folders given to them (a channel gives its folder to its
// members, includes/workspace/files.php). That alone handed staff the files
// of every private channel, guest room and mailbox room by their address,
// while the channel itself is read only by its members and, for a private
// channel or guest room, by staff who opened it for inspection
// (ws_can_read_channel()).
//
// Not in the functions.php manifest: loaded with require_once by
// get_file.php, which loads no functions.php code; pure.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * What get_file.php does with a file of a channel's folder.
 *
 * @param string $kind       the channel's kind: public, private, guest, mailbox
 * @param int    $role       the visitor's role (3 when not signed in)
 * @param bool   $ws_member  the visitor is in the team (the Workspace right,
 *                           not "Not in the team")
 * @param bool   $in_channel the visitor is a member of the channel
 * @param bool   $audit_open staff opened the channel for inspection and no
 *                           member of staff is in it
 * @return string open (served to the team, the folder is not asked), folder
 *                (the folder's private access decides) or refuse
 */
function pg_ws_channel_file_verdict($kind, $role, $ws_member, $in_channel, $audit_open)
{
    $kind = (string) $kind;

    if ($ws_member && ($kind === 'public')) {
        return 'open';
    }

    // A user's view right on the folder follows the channel's members; one
    // an administrator gave by hand on Edit User stays theirs.
    if ((int) $role >= 3) {
        return 'folder';
    }

    if (!$ws_member) {
        return 'refuse';
    }

    if ($in_channel) {
        return 'folder';
    }

    // A mailbox's room is never opened for inspection.
    return ($audit_open && ($kind !== 'mailbox')) ? 'folder' : 'refuse';
}
