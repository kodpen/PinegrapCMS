<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - files of the file manager brought into a conversation: sent to
 * a channel from the file manager's menu, or picked under the paperclip of
 * the writing box in the one picker (kind channel_file of pg_picker_kinds(),
 * which asks ws_library_closed_tops() and ws_library_refusal() here).
 *
 * The channel gets a copy, kept in its own folder and checked the way an
 * upload is (ws_message_send_file_bytes()): what a channel refuses it refuses
 * here too, the people of the channel read the copy whatever rights they
 * hold in the file manager, and a later change to the original does not
 * change a file a conversation has already talked about.
 *
 * What a person may pick is what the file manager shows them: every folder
 * for staff, the folders a user may edit and everything below them for a
 * user (get_folders_that_user_has_access_to()). Three places are left out
 * for everybody: the Workspace folder (its files belong to the channels and
 * notes they were posted in; a message is forwarded, its file is not), the
 * recycle bin and archived folders. Files without a folder are other
 * modules' (ERP documents) and never come up. dkim.key, the key that signs
 * the site's mail, is listed like any file but never picked
 * (pg_file_withheld()): its copy in a channel would be an ordinary file under
 * another name, which get_file.php serves.
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
 * Every folder in a branch: the roots given and everything under them.
 * Roots that are not folders are ignored.
 *
 * @param array $parents folder id => parent folder id
 * @param int[] $roots
 * @return array folder id => true
 */
function ws_library_branch($parents, $roots)
{
    $children = array();

    foreach ($parents as $folder_id => $parent_id) {
        $children[(int) $parent_id][] = (int) $folder_id;
    }

    $branch = array();
    $stack = array_map('intval', (array) $roots);

    while (!empty($stack)) {
        $folder_id = array_pop($stack);

        if (isset($branch[$folder_id]) || !array_key_exists($folder_id, $parents)) {
            continue;
        }

        $branch[$folder_id] = true;

        foreach ($children[$folder_id] ?? array() as $child_id) {
            $stack[] = $child_id;
        }
    }

    return $branch;
}

/**
 * The folders a person may pick from: the ones granted to them, less every
 * folder in a closed branch.
 *
 * @param array $parents folder id => parent folder id
 * @param int[] $granted the folders the person may open, branches included
 * @param int[] $closed  the tops of the branches nobody picks from
 * @return array folder id => true
 */
function ws_library_open_set($parents, $granted, $closed)
{
    $shut = ws_library_branch($parents, $closed);
    $open = array();

    foreach ((array) $granted as $folder_id) {
        $folder_id = (int) $folder_id;

        if (array_key_exists($folder_id, $parents) && !isset($shut[$folder_id])) {
            $open[$folder_id] = true;
        }
    }

    return $open;
}

/**
 * Every folder of the file manager, read once a request.
 *
 * @return array folder id => parent, name, order, archived
 */
function ws_library_folders()
{
    static $folders = null;

    if ($folders === null) {
        $folders = array();

        foreach ((array) db_items("SELECT folder_id, folder_parent, folder_name, folder_order, folder_archived FROM folder") as $row) {
            $folders[(int) $row['folder_id']] = array(
                'parent'   => (int) $row['folder_parent'],
                'name'     => (string) $row['folder_name'],
                'order'    => (int) $row['folder_order'],
                'archived' => ((int) $row['folder_archived'] === 1),
            );
        }
    }

    return $folders;
}

/**
 * @return array folder id => parent folder id
 */
function ws_library_parents()
{
    $parents = array();

    foreach (ws_library_folders() as $folder_id => $folder) {
        $parents[$folder_id] = $folder['parent'];
    }

    return $parents;
}

/**
 * The Workspace folder, without making it: 0 when there is none yet.
 *
 * @return int
 */
function ws_library_workspace_folder()
{
    return ws_folders_ready() ? (int) db_value("SELECT ws_folder_id FROM config") : 0;
}

/**
 * The folders the file manager shows this person, closed ones included: every
 * folder for staff, the folders a user may edit and those below
 * (pg_explorer_folder_visible()'s rule).
 *
 * @param array $viewer
 * @return int[]
 */
function ws_library_granted($viewer)
{
    static $cache = array();

    $id = (int) $viewer['id'];

    if (!isset($cache[$id])) {
        if ((int) $viewer['role'] < 3) {
            $cache[$id] = array_keys(ws_library_folders());
        } else {
            $cache[$id] = function_exists('get_folders_that_user_has_access_to') ? array_map('intval', (array) get_folders_that_user_has_access_to($id)) : array();
        }
    }

    return $cache[$id];
}

/**
 * The folders this person may pick a file from.
 *
 * @param array $viewer
 * @return array folder id => true
 */
function ws_library_open($viewer)
{
    static $cache = array();

    $id = (int) $viewer['id'];

    if (isset($cache[$id])) {
        return $cache[$id];
    }

    return $cache[$id] = ws_library_open_set(ws_library_parents(), ws_library_granted($viewer), ws_library_closed_tops());
}

/**
 * The tops of the branches nobody picks a file from: the Workspace folder
 * (its files belong to the channels and notes they were posted in), the
 * recycle bin and every archived folder.
 *
 * @return int[]
 */
function ws_library_closed_tops()
{
    $closed = array(ws_library_workspace_folder());

    if (function_exists('pg_recycle_bin_folder_id')) {
        $closed[] = (int) pg_recycle_bin_folder_id();
    }

    foreach (ws_library_folders() as $folder_id => $folder) {
        if ($folder['archived']) {
            $closed[] = $folder_id;
        }
    }

    return $closed;
}

/**
 * Why the key that signs the site's mail cannot be picked.
 *
 * @return string
 */
function ws_library_withheld_message()
{
    return lang('This is the key that signs the site\'s mail. It stays on the server.');
}

/**
 * Why a channel would not take a file, so the picker shows it marked and it
 * cannot be chosen: the key that signs the site's mail, a kind of file a
 * channel refuses (ws_upload_refused()), or one past the upload limit.
 *
 * @param string $name files.name
 * @param int    $size bytes
 * @return string '' when a channel takes it
 */
function ws_library_refusal($name, $size)
{
    $name = (string) $name;

    if (pg_file_withheld($name)) {
        return ws_library_withheld_message();
    }

    if (ws_upload_refused($name)) {
        return lang('Channels do not take this kind of file.');
    }

    if ((int) $size > ws_upload_max_bytes()) {
        return lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => (int) floor(ws_upload_max_bytes() / 1048576)));
    }

    return '';
}

/**
 * A file of the file manager this person may bring into a conversation.
 *
 * @param array $viewer
 * @param int   $file_id
 * @return array ok, error, file (id, name, folder, size)
 */
function ws_library_file($viewer, $file_id)
{
    $missing = array('ok' => false, 'error' => lang('Sorry, we could not find the file.'), 'file' => null);
    $row = db_item("SELECT id, name, folder, size FROM files WHERE id = '" . (int) $file_id . "'");

    if (!is_array($row) || ((int) $row['folder'] <= 0)) {
        return $missing;
    }

    if (function_exists('pg_files_include_erp_document') && pg_files_include_erp_document(array((int) $row['id']))) {
        return $missing;
    }

    // A team chat attachment opens only for a side of its conversation or a
    // role-0 administrator (get_file.php); its channel copy would open for
    // every member, so anyone else hears that there is no such file.
    if (pg_chat_attachment_refused($row['id'], $viewer)) {
        return $missing;
    }

    $open = ws_library_open($viewer);

    if (isset($open[(int) $row['folder']])) {
        // The picker lists the signing key with this reason; whoever sees it
        // there hears the same reason here, and nobody gets a copy.
        if (pg_file_withheld($row['name'])) {
            return array('ok' => false, 'error' => ws_library_withheld_message(), 'file' => null);
        }

        return array('ok' => true, 'error' => '', 'file' => $row);
    }

    // Said plainly to a person the file manager shows the file to, for the
    // one place they are turned away from: it already belongs to a
    // conversation. Anybody else hears only that there is no such file.
    $workspace = ws_library_workspace_folder();

    if (($workspace > 0) && isset(ws_library_branch(ws_library_parents(), array($workspace))[(int) $row['folder']])
        && in_array((int) $row['folder'], ws_library_granted($viewer), true)) {
        return array('ok' => false, 'error' => lang('This file is already in the Workspace. Forward the message that carries it instead.'), 'file' => null);
    }

    return $missing;
}

/**
 * Posts a copy of a file of the file manager into a channel, as a message
 * carrying it.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param int    $file_id
 * @param string $body    the words beside the file, may be empty
 * @return array ok, error, message_id
 */
function ws_library_send($viewer, $channel, $file_id, $body = '')
{
    $found = ws_library_file($viewer, $file_id);

    if (!$found['ok']) {
        return array('ok' => false, 'error' => $found['error'], 'message_id' => 0);
    }

    $name = (string) $found['file']['name'];
    $path = pg_file_reader_path(basename($name));
    $size = ($path !== '') ? @filesize($path) : false;

    if ($size === false) {
        return array('ok' => false, 'error' => lang('The file could not be read.'), 'message_id' => 0);
    }

    // Measured before it is read: a file past the channel's limit is not
    // loaded only to be refused.
    if ($size > ws_upload_max_bytes()) {
        return array('ok' => false, 'error' => lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => (int) floor(ws_upload_max_bytes() / 1048576))), 'message_id' => 0);
    }

    return ws_message_send_file_bytes($viewer, $channel, (string) $body, $name, @file_get_contents($path));
}

/**
 * The channels a file may be sent to from the file manager: the ones this
 * person may write in, as the sidebar orders them.
 *
 * @param array $viewer
 * @return array[] id, name, icon, badge (ws_channel_icon_badge()), hex
 */
function ws_library_channels($viewer)
{
    $channels = array();

    foreach (ws_channels_for($viewer) as $brief) {
        if (ws_can_post_channel($viewer, ws_channel($brief['id']))) {
            $channels[] = array('id' => $brief['id'], 'name' => $brief['name'], 'icon' => $brief['icon'], 'badge' => $brief['icon_badge'], 'hex' => $brief['hex']);
        }
    }

    return $channels;
}

/**
 * The words of the paperclip's menu.
 *
 * @return array key => text
 */
function ws_library_js_strings()
{
    return array(
        'lib_from_device'  => lang('From this device'),
        'lib_from_manager' => lang('From the file manager'),
    );
}
