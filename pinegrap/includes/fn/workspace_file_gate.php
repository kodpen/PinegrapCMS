<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Who get_file.php serves a file of a Workspace channel's or note's folder
// to. The folder is private: staff pass every private folder of the file
// manager and a user passes the folders given to them (a channel gives its
// folder to its members, a note to its readers, includes/workspace/files.php
// and notes.php). That alone handed staff the files of every private
// channel, guest room, mailbox room and note by their address, while a
// channel is read only by its members and, for a private channel or guest
// room, by staff who opened it for inspection (ws_can_read_channel()), and
// a note gives administrators nothing by rank.
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
 * @return string open (a reader of the channel: the folder is not asked),
 *                folder (the folder's private access decides) or refuse
 */
function pg_ws_channel_file_verdict($kind, $role, $ws_member, $in_channel, $audit_open)
{
    $kind = (string) $kind;

    // Whoever reads the channel: the team a public one, its members any
    // other, staff a private channel or guest room opened for inspection
    // (never a mailbox's room). Asked by membership, not by the folder: the
    // folder's view right is given to users only, so a manager made a user
    // stays in their channels without one.
    if ($ws_member && (($kind === 'public') || $in_channel || ($audit_open && ((int) $role < 3) && ($kind !== 'mailbox')))) {
        return 'open';
    }

    // Any other user is left to the folder: a view right given by hand on
    // Edit User stays theirs. Staff pass every private folder, so they are
    // refused here.
    return ((int) $role >= 3) ? 'folder' : 'refuse';
}

/**
 * What get_file.php does with a file of a note's folder no public channel
 * opened to the team: staff who do not read the note are refused (a note
 * gives administrators nothing by rank, but staff pass every private
 * folder); anybody else is left to the folder, whose view right follows
 * the note's readers.
 *
 * @param int  $role   the visitor's role (3 when not signed in)
 * @param bool $reader the visitor reads the note (ws_note_readers())
 * @return string folder or refuse
 */
function pg_ws_note_file_verdict($role, $reader)
{
    return (((int) $role < 3) && !$reader) ? 'refuse' : 'folder';
}
