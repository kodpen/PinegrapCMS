<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - files of the file manager brought into a conversation: sent to
 * a channel from the file manager's menu, or picked from the file manager
 * under the paperclip of the writing box.
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
 * modules' (ERP documents) and never come up.
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
 * The most files one folder lists; a search narrows the rest.
 */
define('WS_LIBRARY_FOLDER_FILES', 200);

/**
 * The most files a search lists.
 */
define('WS_LIBRARY_SEARCH_FILES', 50);

/**
 * The most recently changed files shown where the picker opens.
 */
define('WS_LIBRARY_RECENT_FILES', 12);

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
 * Where the picker starts: the open folders whose parent is not open. Staff
 * have one, the top folder; a user has the top of each branch granted to
 * them.
 *
 * @param array $parents folder id => parent folder id
 * @param array $open    folder id => true
 * @return int[]
 */
function ws_library_tops($parents, $open)
{
    $tops = array();

    foreach ($open as $folder_id => $yes) {
        if (!isset($open[(int) $parents[$folder_id]])) {
            $tops[] = (int) $folder_id;
        }
    }

    return $tops;
}

/**
 * The way down to a folder from the highest folder above it the person may
 * open, that folder included.
 *
 * @param array $parents folder id => parent folder id
 * @param array $open    folder id => true
 * @param int   $folder_id
 * @return int[] the highest first
 */
function ws_library_trail($parents, $open, $folder_id)
{
    $trail = array();
    $seen = array();
    $folder_id = (int) $folder_id;

    while (isset($open[$folder_id]) && !isset($seen[$folder_id])) {
        $seen[$folder_id] = true;
        array_unshift($trail, $folder_id);
        $folder_id = (int) ($parents[$folder_id] ?? 0);
    }

    return $trail;
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

    $closed = array(ws_library_workspace_folder());

    if (function_exists('pg_recycle_bin_folder_id')) {
        $closed[] = (int) pg_recycle_bin_folder_id();
    }

    foreach (ws_library_folders() as $folder_id => $folder) {
        if ($folder['archived']) {
            $closed[] = $folder_id;
        }
    }

    return $cache[$id] = ws_library_open_set(ws_library_parents(), ws_library_granted($viewer), $closed);
}

/**
 * Folders as the picker lists them, in the file manager's order.
 *
 * @param int[] $folder_ids
 * @return array[] id, name
 */
function ws_library_folder_list($folder_ids)
{
    $folders = ws_library_folders();
    $list = array();

    foreach ($folder_ids as $folder_id) {
        if (isset($folders[$folder_id])) {
            $list[] = array('id' => (int) $folder_id, 'name' => $folders[$folder_id]['name'], 'order' => $folders[$folder_id]['order']);
        }
    }

    usort($list, function ($a, $b) {
        return ($a['order'] !== $b['order']) ? ($a['order'] - $b['order']) : strnatcasecmp($a['name'], $b['name']);
    });

    return array_map(function ($folder) {
        return array('id' => $folder['id'], 'name' => $folder['name']);
    }, $list);
}

/**
 * A file as the picker shows it: what it is, where it is, and why a
 * channel would not take it, so it is shown but cannot be picked.
 *
 * @param array $row id, name, folder, size, timestamp
 * @return array
 */
function ws_library_file_payload($row)
{
    $folders = ws_library_folders();
    $name = (string) $row['name'];
    $kind = ws_file_kind($name);
    $size = (int) $row['size'];
    $refused = '';

    if (ws_upload_refused($name)) {
        $refused = lang('Channels do not take this kind of file.');
    } elseif ($size > ws_upload_max_bytes()) {
        $refused = lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => (int) floor(ws_upload_max_bytes() / 1048576)));
    }

    return array(
        'id'      => (int) $row['id'],
        'name'    => $name,
        'kind'    => $kind,
        'ext'     => strtolower((string) pathinfo($name, PATHINFO_EXTENSION)),
        'size'    => function_exists('convert_bytes_to_string') ? convert_bytes_to_string($size, ($size >= 1048576) ? 1 : 0) : (string) $size,
        'time'    => ws_time_label((int) $row['timestamp']),
        'where'   => (string) ($folders[(int) $row['folder']]['name'] ?? ''),
        'thumb'   => (($kind === 'image') && ($refused === '')) ? PATH . encode_url_path($name) : '',
        'refused' => $refused,
    );
}

/**
 * What the picker shows: one folder (its folders and files), the place it
 * starts (the top, with the files changed last), or what a search found.
 *
 * @param array  $viewer
 * @param int    $folder_id 0 for where the picker starts
 * @param string $query     a part of a file name; searches every open folder
 * @return array folder (id, name or null at a top of several), trail, folders,
 *               files, recent, more (the list was cut), search, several (the
 *               person has more than one top), nothing (no folder is open to
 *               this person at all)
 */
function ws_library_browse($viewer, $folder_id = 0, $query = '')
{
    $open = ws_library_open($viewer);
    $query = trim(mb_substr((string) $query, 0, 100));
    $answer = array(
        'folder'  => null,
        'trail'   => array(),
        'folders' => array(),
        'files'   => array(),
        'recent'  => array(),
        'more'    => false,
        'search'  => ($query !== ''),
        'several' => false,
        'nothing' => empty($open),
    );

    if (empty($open)) {
        return $answer;
    }

    $in = implode(',', array_keys($open));
    $columns = "SELECT id, name, folder, size, timestamp FROM files";

    if ($query !== '') {
        $rows = (array) db_items($columns . "
            WHERE folder IN (" . $in . ") AND name LIKE '%" . e(escape_like($query)) . "%'
            ORDER BY timestamp DESC, id DESC
            LIMIT " . (WS_LIBRARY_SEARCH_FILES + 1));

        $answer['more'] = (count($rows) > WS_LIBRARY_SEARCH_FILES);
        $answer['files'] = array_map('ws_library_file_payload', array_slice($rows, 0, WS_LIBRARY_SEARCH_FILES));

        return $answer;
    }

    $parents = ws_library_parents();
    $tops = ws_library_tops($parents, $open);
    $folder_id = (int) $folder_id;
    $start = false;

    // Several tops are listed side by side, and every folder below them
    // leads back to that list.
    $answer['several'] = (count($tops) > 1);

    if (($folder_id <= 0) || !isset($open[$folder_id])) {
        $start = true;
        $folder_id = (count($tops) === 1) ? $tops[0] : 0;
    }

    if ($start) {
        $answer['recent'] = array_map('ws_library_file_payload', (array) db_items($columns . "
            WHERE folder IN (" . $in . ")
            ORDER BY timestamp DESC, id DESC
            LIMIT " . WS_LIBRARY_RECENT_FILES));
    }

    // A top of several: the branches granted to a user, side by side.
    if ($folder_id === 0) {
        $answer['folders'] = ws_library_folder_list($tops);

        return $answer;
    }

    $folders = ws_library_folders();
    $children = array();

    foreach ($open as $child_id => $yes) {
        if ($parents[$child_id] === $folder_id) {
            $children[] = $child_id;
        }
    }

    $answer['folder'] = array('id' => $folder_id, 'name' => $folders[$folder_id]['name']);

    foreach (ws_library_trail($parents, $open, $folder_id) as $step_id) {
        $answer['trail'][] = array('id' => $step_id, 'name' => $folders[$step_id]['name']);
    }

    $answer['folders'] = ws_library_folder_list($children);

    $rows = (array) db_items($columns . "
        WHERE folder = '" . $folder_id . "'
        ORDER BY name
        LIMIT " . (WS_LIBRARY_FOLDER_FILES + 1));

    $answer['more'] = (count($rows) > WS_LIBRARY_FOLDER_FILES);
    $answer['files'] = array_map('ws_library_file_payload', array_slice($rows, 0, WS_LIBRARY_FOLDER_FILES));

    return $answer;
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

    $open = ws_library_open($viewer);

    if (isset($open[(int) $row['folder']])) {
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
    $path = FILE_DIRECTORY_PATH . '/' . basename($name);
    $size = is_file($path) ? @filesize($path) : false;

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
 * @return array[] id, name, icon, hex
 */
function ws_library_channels($viewer)
{
    $channels = array();

    foreach (ws_channels_for($viewer) as $brief) {
        if (ws_can_post_channel($viewer, ws_channel($brief['id']))) {
            $channels[] = array('id' => $brief['id'], 'name' => $brief['name'], 'icon' => $brief['icon'], 'hex' => $brief['hex']);
        }
    }

    return $channels;
}

/**
 * The words of the picker under the paperclip.
 *
 * @return array key => text
 */
function ws_library_js_strings()
{
    return array(
        'lib_from_device'  => lang('From this device'),
        'lib_from_manager' => lang('From the file manager'),
        'lib_help'         => lang('The channel gets a copy of the file you pick; the original stays where it is.'),
        'lib_search'       => lang('Search the file manager'),
        'lib_top'          => lang('All folders'),
        'lib_recent'       => lang('Changed last'),
        'lib_empty'        => lang('There are no files in this folder.'),
        'lib_no_match'     => lang('No file matches that.'),
        'lib_nothing'      => lang('There is no folder in the file manager you can open.'),
        'lib_more'         => lang('Only the first files are listed. Search to find the others.'),
    );
}
