<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - files posted into a channel, kept in the file manager.
 *
 * Every channel that has had a file gets a folder of its own, named after the
 * channel, inside a private "Workspace" folder at the top of the file
 * manager. A name already taken anywhere in the file manager gets " [1]",
 * " [2]" ... the way the file manager names a copy. The folders are private:
 * staff see them as they see every private folder, and a basic user holds
 * private access to a channel's folder exactly as long as they are in the
 * channel. get_file.php adds the one thing the folder rights cannot say:
 * anyone in the team may open the files of a public channel.
 *
 * What may be uploaded is what the file manager takes (pg_upload_name_blocked:
 * no PHP, no .htaccess or web.config, no shell or Windows programs), less the
 * few documents a browser would run as a page of this site (SVG, HTML, XML,
 * JavaScript).
 *
 * The files of personal notes live under the same Workspace folder, in a
 * "Notes" folder with one folder per note (includes/workspace/notes.php).
 * The file manager's flat Files and Pictures views leave the whole Workspace
 * folder out; staff open it folder by folder.
 *
 * Files posted before a channel had a folder (named "ws-<channel>-..." and
 * kept outside the folders) are moved into it when it is made.
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
 * Extensions a channel refuses on top of the file manager's list: documents
 * that a browser opening them from this site would run as its own page.
 *
 * @return string[]
 */
function ws_upload_refused_extensions()
{
    return array('svg', 'svgz', 'html', 'htm', 'xhtml', 'xht', 'xml', 'js', 'mjs', 'swf');
}

/**
 * Would a channel refuse this name?
 *
 * @param string $name
 * @return bool
 */
function ws_upload_refused($name)
{
    $name = (string) $name;

    if (function_exists('pg_upload_name_blocked') && pg_upload_name_blocked($name)) {
        return true;
    }

    $parts = explode('.', mb_strtolower(basename(str_replace('\\', '/', $name))));
    array_shift($parts);

    return (bool) array_intersect($parts, ws_upload_refused_extensions());
}

/**
 * What a file is, for the way it is shown: a picture, a video, a sound, a PDF
 * or anything else.
 *
 * @param string $name
 * @return string image | video | audio | pdf | file
 */
function ws_file_kind($name)
{
    $extension = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));

    if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'), true)) {
        return 'image';
    }

    if (in_array($extension, array('mp4', 'webm', 'mov', 'm4v', 'ogv'), true)) {
        return 'video';
    }

    if (in_array($extension, array('mp3', 'm4a', 'wav', 'ogg', 'oga', 'opus', 'aac', 'weba'), true)) {
        return 'audio';
    }

    return ($extension === 'pdf') ? 'pdf' : 'file';
}

/**
 * The largest file a channel takes: 20 MB, or less when the server says so.
 *
 * @return int bytes
 */
function ws_upload_max_bytes()
{
    $max = 20 * 1024 * 1024;

    if (function_exists('pg_upload_limits')) {
        // The file travels base64 inside a JSON body, so the JSON ceiling is
        // the one that applies (see pg_upload_limits()).
        $limits = pg_upload_limits();
        $ceiling = (int) ($limits['json_max'] ?? 0);

        if ($ceiling <= 0) {
            $ceiling = (int) ($limits['file_max'] ?? 0);
        }

        if (($ceiling > 0) && ($ceiling < $max)) {
            $max = $ceiling;
        }
    }

    return $max;
}

/**
 * Can channels keep their files in folders yet (4.82)?
 *
 * @return bool
 */
function ws_folders_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_channels' AND COLUMN_NAME = 'folder_id'") > 0)
            && ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config' AND COLUMN_NAME = 'ws_folder_id'") > 0);
    }

    return $ready;
}

/**
 * A new folder, private, under a parent; its name made unique the way the
 * file manager makes it.
 *
 * @param string $name
 * @param int    $parent_id
 * @param int    $user_id
 * @return int the folder id, 0 when it could not be made
 */
function ws_folder_create($name, $parent_id, $user_id)
{
    $parent = db_item("SELECT folder_id, folder_level FROM folder WHERE folder_id = '" . (int) $parent_id . "'");

    if (!is_array($parent)) {
        return 0;
    }

    $name = mb_substr(trim((string) $name), 0, 90);
    $name = ($name === '') ? 'Workspace' : $name;

    if (function_exists('get_unique_name')) {
        $name = get_unique_name(array('name' => $name, 'type' => 'folder'));
    }

    db("INSERT INTO folder (folder_name, folder_parent, folder_level, folder_order, folder_access_control_type, folder_archived, folder_timestamp, folder_user)
        VALUES ('" . e($name) . "', '" . (int) $parent['folder_id'] . "', '" . ((int) $parent['folder_level'] + 1) . "', '0', 'private', '0', UNIX_TIMESTAMP(), '" . (int) $user_id . "')");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * The "Workspace" folder at the top of the file manager, made when missing
 * and kept private.
 *
 * @param int $user_id who is making it, when it is made
 * @return int 0 when the file manager has no top folder
 */
function ws_root_folder_id($user_id = 0)
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $stored = (int) db_value("SELECT ws_folder_id FROM config");

    if (($stored > 0) && ((int) db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . $stored . "'") > 0)) {
        db("UPDATE folder SET folder_access_control_type = 'private' WHERE folder_id = '" . $stored . "' AND (folder_access_control_type IS NULL OR folder_access_control_type <> 'private')");

        return $cache = $stored;
    }

    $top = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");

    if ($top <= 0) {
        return $cache = 0;
    }

    // A folder already called Workspace at the top is taken over rather than
    // doubled.
    $existing = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '" . $top . "' AND folder_name = 'Workspace' ORDER BY folder_id LIMIT 1");

    if ($existing > 0) {
        db("UPDATE folder SET folder_access_control_type = 'private' WHERE folder_id = '" . $existing . "'");
        $folder_id = $existing;
    } else {
        $folder_id = ws_folder_create('Workspace', $top, $user_id);
    }

    if ($folder_id > 0) {
        db("UPDATE config SET ws_folder_id = '" . (int) $folder_id . "'");
    }

    return $cache = $folder_id;
}

/**
 * A channel's folder; made on its first file.
 *
 * @param array $channel
 * @param int   $user_id who is making it
 * @param bool  $create
 * @return int 0 when there is none (and none was to be made)
 */
function ws_channel_folder_id($channel, $user_id = 0, $create = true)
{
    if (!ws_folders_ready() || !is_array($channel)) {
        return 0;
    }

    $folder_id = (int) db_value("SELECT folder_id FROM ws_channels WHERE id = '" . (int) $channel['id'] . "'");

    if (($folder_id > 0) && ((int) db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . $folder_id . "'") > 0)) {
        return $folder_id;
    }

    if (!$create) {
        return 0;
    }

    $root = ws_root_folder_id($user_id);

    if ($root <= 0) {
        return 0;
    }

    $folder_id = ws_folder_create((string) $channel['name'], $root, $user_id);

    if ($folder_id <= 0) {
        return 0;
    }

    db("UPDATE ws_channels SET folder_id = '" . $folder_id . "' WHERE id = '" . (int) $channel['id'] . "'");

    // Files posted before the channel had a folder move into it.
    db("UPDATE files f
        INNER JOIN ws_messages m ON m.file_id = f.id AND m.channel_id = '" . (int) $channel['id'] . "'
        SET f.folder = '" . $folder_id . "'
        WHERE f.folder = 0");

    // Everyone already in the channel may open it.
    ws_folder_grant($folder_id, array_map('intval', (array) db_values("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . (int) $channel['id'] . "'")));

    return $folder_id;
}

/**
 * Private access to a folder for the basic users among these people. Staff
 * see every private folder anyway; a right somebody already holds there
 * (edit rights given by hand, say) is left as it is.
 *
 * @param int   $folder_id
 * @param int[] $user_ids
 */
function ws_folder_grant($folder_id, $user_ids)
{
    $folder_id = (int) $folder_id;
    $user_ids = array_values(array_filter(array_map('intval', (array) $user_ids)));

    if (($folder_id <= 0) || empty($user_ids)) {
        return;
    }

    foreach ((array) db_values("SELECT user_id FROM user WHERE user_role = 3 AND user_id IN (" . implode(',', $user_ids) . ")") as $user_id) {
        $held = (int) db_value("SELECT COUNT(*) FROM aclfolder WHERE aclfolder_user = '" . (int) $user_id . "' AND aclfolder_folder = '" . $folder_id . "'");

        if ($held === 0) {
            db("INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date)
                VALUES ('" . (int) $user_id . "', '" . $folder_id . "', '1', '0000-00-00')");
        }
    }
}

/**
 * Takes the private access to a folder away again. Only the plain private
 * access the channel gave is taken; edit rights stay.
 *
 * @param int   $folder_id
 * @param int[] $user_ids
 */
function ws_folder_revoke($folder_id, $user_ids)
{
    $folder_id = (int) $folder_id;
    $user_ids = array_values(array_filter(array_map('intval', (array) $user_ids)));

    if (($folder_id <= 0) || empty($user_ids)) {
        return;
    }

    db("DELETE FROM aclfolder
        WHERE aclfolder_folder = '" . $folder_id . "' AND aclfolder_rights = 1
        AND aclfolder_user IN (" . implode(',', $user_ids) . ")");
}

/**
 * Keeps a channel's folder rights in step with who is in the channel, and
 * the folders of the notes shared in it (includes/workspace/notes.php).
 *
 * @param array $channel
 * @param int[] $user_ids
 * @param bool  $in joined (true) or left (false)
 */
function ws_channel_folder_access($channel, $user_ids, $in)
{
    if (function_exists('ws_note_channel_sync')) {
        ws_note_channel_sync((int) $channel['id']);
    }

    $folder_id = ws_channel_folder_id($channel, 0, false);

    if ($folder_id <= 0) {
        return;
    }

    if ($in) {
        ws_folder_grant($folder_id, $user_ids);
    } else {
        ws_folder_revoke($folder_id, $user_ids);
    }
}

/**
 * Renames a channel's folder with the channel.
 *
 * @param array  $channel
 * @param string $name
 */
function ws_channel_folder_rename($channel, $name)
{
    $folder_id = ws_channel_folder_id($channel, 0, false);

    if ($folder_id <= 0) {
        return;
    }

    $current = (string) db_value("SELECT folder_name FROM folder WHERE folder_id = '" . $folder_id . "'");
    $name = mb_substr(trim((string) $name), 0, 90);

    if (($name === '') || ($current === $name)) {
        return;
    }

    if (function_exists('get_unique_name')) {
        $name = get_unique_name(array('name' => $name, 'type' => 'folder'));
    }

    db("UPDATE folder SET folder_name = '" . e($name) . "' WHERE folder_id = '" . $folder_id . "'");
}

/**
 * Stores a file posted into a channel, base64 as the panel sends it.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $original_name
 * @param string $data
 * @return array ok, error, file_id, name (the name the author gave it)
 */
function ws_store_upload($viewer, $channel, $original_name, $data)
{
    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $original_name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', (string) $original_name))));
    $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));

    if (($original_name === '') || ws_upload_refused($original_name)) {
        return array('ok' => false, 'error' => function_exists('pg_upload_blocked_message') ? pg_upload_blocked_message($original_name) : lang('This file type is not allowed.'));
    }

    $parts = explode('base64,', (string) $data, 2);
    $binary = base64_decode(str_replace(' ', '+', end($parts)), true);

    if (($binary === false) || ($binary === '')) {
        return array('ok' => false, 'error' => lang('The file could not be read.'));
    }

    if (strlen($binary) > ws_upload_max_bytes()) {
        return array('ok' => false, 'error' => lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => (int) floor(ws_upload_max_bytes() / 1048576))));
    }

    $folder_id = ws_channel_folder_id($channel, $viewer['id']);

    // In a folder the file keeps a name a person can read; without one (the
    // schema not updated yet) it keeps the old channel-marked name.
    if ($folder_id > 0) {
        $stored_name = function_exists('prepare_file_name') ? prepare_file_name($original_name) : preg_replace('/[^A-Za-z0-9._-]/', '_', $original_name);

        if (function_exists('get_unique_name')) {
            $stored_name = get_unique_name(array('name' => $stored_name, 'type' => 'file'));
        }
    } else {
        $stored_name = 'ws-' . (int) $channel['id'] . '-' . time() . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    }

    $stored_path = FILE_DIRECTORY_PATH . '/' . $stored_name;

    if (file_exists($stored_path) || (@file_put_contents($stored_path, $binary) === false)) {
        return array('ok' => false, 'error' => lang('The file could not be saved.'));
    }

    // A picture has to be a picture, and nothing that reads as a page or a
    // program is kept whatever it was called.
    if ((ws_file_kind($original_name) === 'image') && (@getimagesize($stored_path) === false)) {
        @unlink($stored_path);

        return array('ok' => false, 'error' => lang('This file type is not allowed.'));
    }

    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) @finfo_file($finfo, $stored_path) : '';

        if ($finfo) {
            @finfo_close($finfo);
        }

        if (preg_match('#^(text/html|application/xhtml|application/x-php|text/x-php|application/x-httpd|application/x-msdownload|application/x-dosexec|application/x-sh|text/x-shellscript|application/x-executable|image/svg|application/javascript|text/javascript)#i', $mime)) {
            @unlink($stored_path);

            return array('ok' => false, 'error' => lang('This file type is not allowed.'));
        }
    }

    db("INSERT INTO files (name, folder, type, size, user, design, optimized, timestamp)
        VALUES ('" . e($stored_name) . "', '" . (int) $folder_id . "', '" . e($extension) . "', '" . (int) filesize($stored_path) . "',
            '" . (int) $viewer['id'] . "', '0', '0', UNIX_TIMESTAMP())");

    $file_id = (int) mysqli_insert_id(db::$con);

    if ($file_id <= 0) {
        @unlink($stored_path);

        return array('ok' => false, 'error' => lang('The file could not be saved.'));
    }

    return array('ok' => true, 'error' => '', 'file_id' => $file_id, 'name' => mb_substr($original_name, 0, 200));
}
