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
 * The bytes of a file the panel sends base64, after a "data:...;base64,"
 * prefix or without one.
 *
 * @param string $data
 * @return string|false false when they could not be decoded
 */
function ws_upload_decode($data)
{
    $parts = explode('base64,', (string) $data, 2);

    return base64_decode(str_replace(' ', '+', end($parts)), true);
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
    return ws_store_upload_bytes($viewer, $channel, $original_name, ws_upload_decode($data));
}

/**
 * What a channel says of a file from its name and its bytes alone, before
 * anything is kept: its name, size and type, and that a picture is a picture
 * and nothing that reads as a page or a program is taken whatever it was
 * called. The bytes are looked at in memory, so a refused file never reaches
 * the disk and the API's dry run meets the same refusals.
 *
 * @param string       $original_name
 * @param string|false $binary false when the bytes could not be decoded
 * @return array ok, error, name (the name cleaned), extension
 */
function ws_upload_inspect($original_name, $binary)
{
    $original_name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', (string) $original_name))));
    $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));
    $refused = lang('This file type is not allowed.');

    if (($original_name === '') || ws_upload_refused($original_name)) {
        return array('ok' => false, 'error' => function_exists('pg_upload_blocked_message') ? pg_upload_blocked_message($original_name) : $refused);
    }

    if (($binary === false) || ((string) $binary === '')) {
        return array('ok' => false, 'error' => lang('The file could not be read.'));
    }

    if (strlen($binary) > ws_upload_max_bytes()) {
        return array('ok' => false, 'error' => lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => (int) floor(ws_upload_max_bytes() / 1048576))));
    }

    if ((ws_file_kind($original_name) === 'image') && (@getimagesizefromstring($binary) === false)) {
        return array('ok' => false, 'error' => $refused);
    }

    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) @finfo_buffer($finfo, $binary) : '';

        if ($finfo) {
            @finfo_close($finfo);
        }

        if (preg_match('#^(text/html|application/xhtml|application/x-php|text/x-php|application/x-httpd|application/x-msdownload|application/x-dosexec|application/x-sh|text/x-shellscript|application/x-executable|image/svg|application/javascript|text/javascript)#i', $mime)) {
            return array('ok' => false, 'error' => $refused);
        }
    }

    return array('ok' => true, 'error' => '', 'name' => $original_name, 'extension' => $extension);
}

/**
 * Whether a channel takes this file from this person: the checks of
 * ws_store_upload_bytes(), with nothing kept.
 *
 * @param array        $viewer
 * @param array        $channel
 * @param string       $original_name
 * @param string|false $binary
 * @return array ok, error, name (the name cleaned), extension
 */
function ws_upload_check($viewer, $channel, $original_name, $binary)
{
    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    return ws_upload_inspect($original_name, $binary);
}

/**
 * Stores a file posted into a channel, from its bytes: the panel's base64
 * once decoded, or a file the external API received as a form part.
 *
 * @param array        $viewer
 * @param array        $channel
 * @param string       $original_name
 * @param string|false $binary false when the bytes could not be decoded
 * @return array ok, error, file_id, name (the name the author gave it)
 */
function ws_store_upload_bytes($viewer, $channel, $original_name, $binary)
{
    $checked = ws_upload_check($viewer, $channel, $original_name, $binary);

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error']);
    }

    $original_name = $checked['name'];
    $extension = $checked['extension'];

    // A file of a discussion is kept in its channel's folder: the people of
    // a discussion are people who read the channel, and the file stays when
    // the discussion is gone.
    $folder_channel = (function_exists('ws_thread_parent_channel') && ws_thread_parent_channel($channel)) ? ws_thread_parent_channel($channel) : $channel;
    $folder_id = ws_channel_folder_id($folder_channel, $viewer['id']);

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

/**
 * Takes back a file ws_store_upload_bytes() kept, when the message it was
 * for could not be written after all.
 *
 * @param int $file_id
 */
function ws_upload_discard($file_id)
{
    $stored = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $file_id . "'");

    if ($stored === '') {
        return;
    }

    @unlink(FILE_DIRECTORY_PATH . '/' . basename($stored));
    db("DELETE FROM files WHERE id = '" . (int) $file_id . "'");
}

/**
 * Posts a file into a channel as a message carrying it, base64 as the panel
 * sends it. The file and the message are checked before anything is kept,
 * as the external API's files endpoint does: a message that would be refused
 * (too long, too many too fast) leaves no file on the disk, no row in the
 * file manager and no folder for the channel.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $body          the words beside the file, may be empty
 * @param string $original_name
 * @param string $data          base64
 * @param array  $options       as for ws_message_send(), file_id and file_name aside
 * @return array ok, error, message_id
 */
function ws_message_send_file($viewer, $channel, $body, $original_name, $data, $options = array())
{
    return ws_message_send_file_bytes($viewer, $channel, $body, $original_name, ws_upload_decode($data), $options);
}

/**
 * Posts a file into a channel as a message carrying it, from its bytes: the
 * panel's base64 once decoded, or a copy of a file in the file manager
 * (includes/workspace/library.php). Checked the way ws_message_send_file()
 * says.
 *
 * @param array        $viewer
 * @param array        $channel
 * @param string       $body          the words beside the file, may be empty
 * @param string       $original_name
 * @param string|false $binary        false when the bytes could not be decoded
 * @param array        $options       as for ws_message_send(), file_id and file_name aside
 * @return array ok, error, message_id
 */
function ws_message_send_file_bytes($viewer, $channel, $body, $original_name, $binary, $options = array())
{
    $checked = ws_upload_check($viewer, $channel, $original_name, $binary);

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error'], 'message_id' => 0);
    }

    // The file it is about to carry stands in for its id: words are optional
    // beside a file.
    $message = ws_message_send_check($viewer, $channel, $body, array_merge($options, array('file_id' => PHP_INT_MAX)));

    if (!$message['ok']) {
        return array('ok' => false, 'error' => $message['error'], 'message_id' => 0);
    }

    $stored = ws_store_upload_bytes($viewer, $channel, $original_name, $binary);

    if (!$stored['ok']) {
        return array('ok' => false, 'error' => $stored['error'], 'message_id' => 0);
    }

    $result = ws_message_send($viewer, $channel, $body, array_merge($options, array(
        'file_id'   => $stored['file_id'],
        'file_name' => $stored['name'],
    )));

    // Refused after all (the limit reached in between, the row not written):
    // no message, no file.
    if (!$result['ok']) {
        ws_upload_discard($stored['file_id']);
    }

    return $result;
}

/**
 * How a channel's file is handed out: pictures, sound, video and PDF with
 * their own type, to be shown where they are opened; anything else, and
 * anything a browser could run (HTML, SVG), as plain bytes to be saved.
 *
 * @param string $name the name the author gave it
 * @return array mime, inline (bool)
 */
function ws_file_type($name)
{
    $types = array(
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'avif' => 'image/avif', 'bmp' => 'image/bmp', 'pdf' => 'application/pdf',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'm4v' => 'video/mp4', 'ogv' => 'video/ogg',
        'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
        'opus' => 'audio/ogg', 'aac' => 'audio/aac', 'weba' => 'audio/webm',
    );

    $extension = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));

    return isset($types[$extension])
        ? array('mime' => $types[$extension], 'inline' => true)
        : array('mime' => 'application/octet-stream', 'inline' => false);
}

/**
 * The longest edge a smaller copy of a channel's picture is made at: the
 * first of a few fixed sizes that is at least the one asked for, the largest
 * of them at most. Fixed sizes keep one copy per picture and size on disk
 * however many screen widths ask, and keep a caller from having a picture
 * redrawn at every size from one to four thousand.
 *
 * @param int $wanted the longest edge the caller would like, in pixels
 * @return int
 */
function ws_file_preview_edge($wanted)
{
    $edges = array(160, 320, 640, 1280, 1920);

    foreach ($edges as $edge) {
        if ((int) $wanted <= $edge) {
            return $edge;
        }
    }

    return end($edges);
}

/**
 * A smaller copy of a channel's picture, for a phone that shows a channel's
 * pictures in a list before anyone opens one.
 *
 * Drawn by the same engine that optimizes and resizes the file manager's
 * pictures (pg_process_image_file()), in the picture's own format, upright
 * (the EXIF turn is applied) and without its metadata. The copy is kept in
 * data/temp under a name made of the stored file's name, its size, its time
 * and the edge, so a file replaced under the same name gets a new copy and
 * the old one is never served for it. Copies older than thirty days are swept
 * now and then, and drawn again when asked for.
 *
 * Null - send the picture as it is - when it is no larger than the edge
 * already, when it cannot be drawn smaller here (an animated GIF, a format
 * the image library does not read, no image library, not enough memory) or
 * when the copy cannot be kept.
 *
 * @param string $path   the stored file
 * @param int    $wanted the longest edge asked for
 * @return array|null path, mime
 */
function ws_file_preview($path, $wanted)
{
    $edge = ws_file_preview_edge($wanted);
    $size = @getimagesize($path);

    if (!$size || empty($size[0]) || empty($size[1]) || (max((int) $size[0], (int) $size[1]) <= $edge)) {
        return null;
    }

    // The copy is written in the format the picture was read as, which is
    // what its bytes are rather than what its name says.
    $mime = image_type_to_mime_type((int) $size[2]);

    if (strpos($mime, 'image/') !== 0) {
        return null;
    }

    clearstatcache(true, $path);

    $directory = PG_FUNCTIONS_DIR . '/data/temp/workspace_previews';
    $copy = $directory . '/' . sha1(basename($path) . '|' . (int) @filesize($path) . '|' . (int) @filemtime($path) . '|' . $edge);

    if (is_file($copy)) {
        return array('path' => $copy, 'mime' => $mime);
    }

    if (!function_exists('pg_process_image_file')) {
        return null;
    }

    $report = pg_process_image_file($path, array('max_dimension' => $edge, 'quality' => 80, 'write' => false));

    if (($report['status'] !== 'success') || empty($report['resized']) || ((string) $report['data'] === '')) {
        return null;
    }

    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        return null;
    }

    // Written beside its final name and moved over it, so a request that
    // arrives while another is still writing never reads half a picture. A
    // move refused because another request got there first and is sending
    // its copy (Windows keeps an open file in place) still leaves a copy.
    $temporary = $copy . '.' . (function_exists('getmypid') ? getmypid() : uniqid());

    if ((@file_put_contents($temporary, $report['data']) === false) || !@rename($temporary, $copy)) {
        @unlink($temporary);

        return is_file($copy) ? array('path' => $copy, 'mime' => $mime) : null;
    }

    if (mt_rand(1, 50) === 1) {
        ws_file_preview_sweep($directory);
    }

    return array('path' => $copy, 'mime' => $mime);
}

/**
 * Takes away the smaller copies drawn more than thirty days ago, every copy
 * of a picture since deleted among them; one still asked for is drawn again.
 *
 * @param string $directory
 */
function ws_file_preview_sweep($directory)
{
    $before = time() - (30 * 86400);

    foreach ((array) @glob($directory . '/*') as $file) {
        if (is_file($file) && ((int) @filemtime($file) < $before)) {
            @unlink($file);
        }
    }
}
