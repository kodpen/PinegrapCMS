<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - canvases drawn in the image editor: "New canvas" in a channel's
 * Insert menu and in a note's toolbar. The editor opens an empty artboard,
 * draws it to a PNG on save and posts the PNG with its document to
 * workspace_file_save.php; the PNG becomes the message's file (or the note's),
 * and the document is kept beside it in file_versions, so a later "Edit the
 * picture" opens the layers again, texts still editable.
 *
 * A document that may not be kept (a pasted picture inside it, over 1 MB:
 * pg_file_versions_doc_storable()) leaves the picture only; it then opens as
 * any picture does.
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
 * Is this the document of a canvas: version 1, an artboard with its size, no
 * source picture? Works on the array and the object decode alike. Pure.
 *
 * @param mixed $doc
 * @return bool
 */
function ws_canvas_is_doc($doc)
{
    return pg_image_editor_canvas_is_doc($doc);
}

/**
 * The canvas document as kept in a version row: JSON, or '' when it is no
 * canvas or may not be kept (pg_file_versions_doc_storable()). The object
 * decode keeps an empty {} an object. Pure.
 *
 * @param mixed $doc the editor's document, decoded as objects
 * @return string
 */
function ws_canvas_doc_json($doc)
{
    return pg_image_editor_canvas_doc_json($doc);
}

/**
 * The name a new canvas's file goes by: what the editor sent, without an
 * extension, else "Canvas"; always a PNG. Pure but for lang().
 *
 * @param mixed $name
 * @return string
 */
function ws_canvas_file_name($name)
{
    $base = trim(preg_replace('/\.[a-z0-9]{2,5}$/i', '', is_string($name) ? $name : ''));
    $base = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', ' ', $base));

    if ($base === '') {
        $base = lang('Canvas');
    }

    return mb_substr($base, 0, 120) . '.png';
}

/**
 * The PNG of a canvas the editor drew (request picture), checked against its
 * document's artboard size. The request is the editor's save body decoded as
 * arrays.
 *
 * @param array $request
 * @return array error: '' or why not; data: the PNG's bytes
 */
function ws_canvas_picture($request)
{
    $refused = array('error' => lang('Invalid request.'), 'data' => '');
    $doc = $request['doc'] ?? null;

    if (!ws_canvas_is_doc($doc)) {
        return $refused;
    }

    $check = pg_image_edit_validate($doc);

    if (($check['error'] !== '') || !$check['artboard']) {
        return $refused;
    }

    $directory = PG_FUNCTIONS_DIR . '/data';
    $temporary = $directory . '/ws_canvas_' . bin2hex(random_bytes(8)) . '.png';

    if (!pg_image_editor_take_png($request['picture'] ?? '', $temporary, $check['artboard']['width'], $check['artboard']['height'])) {
        return $refused;
    }

    // The browser's PNG is recompressed when that makes it smaller, as a
    // new picture of the File Manager is.
    pg_process_image_file($temporary);

    $data = @file_get_contents($temporary);
    @unlink($temporary);

    if (!is_string($data) || ($data === '')) {
        return array('error' => lang('The file could not be saved.'), 'data' => '');
    }

    return array('error' => '', 'data' => $data);
}

/**
 * The file name a picture from this computer is kept under beside a
 * canvas: the name it came with, else "Picture", in the type of its bytes.
 * Pure but for lang().
 *
 * @param mixed  $name
 * @param string $type png or jpg
 * @return string
 */
function ws_canvas_picture_name($name, $type)
{
    $base = is_string($name) ? pg_basename(str_replace('\\', '/', $name)) : '';
    $base = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', ' ', preg_replace('/\.[a-z0-9]{2,5}$/i', '', $base)));

    if ($base === '') {
        $base = lang('Picture');
    }

    return mb_substr($base, 0, 120) . '.' . $type;
}

/**
 * Points the image layers of a canvas document at the files their pictures
 * were kept as ($saved: layer id => {id, url}) and counts the pictures left
 * in it as data: URLs, which keep the document from being stored. Pure.
 *
 * @param mixed $doc   decoded as objects; changed
 * @param array $saved
 * @return int
 */
function ws_canvas_pictures_point($doc, $saved)
{
    if (!is_object($doc) || !isset($doc->layers) || !is_array($doc->layers)) {
        return 0;
    }

    $left = 0;

    foreach ($doc->layers as $layer) {
        if (!is_object($layer) || !isset($layer->id) || !is_string($layer->id)) {
            continue;
        }

        if (isset($saved[$layer->id])) {
            $layer->src = (string) $saved[$layer->id]['url'];
            $layer->fileId = (int) $saved[$layer->id]['id'];
        } elseif (isset($layer->src) && is_string($layer->src) && (strncmp($layer->src, 'data:', 5) === 0)) {
            $left++;
        }
    }

    return $left;
}

/**
 * Keeps each picture from this computer (the save's pictures, as
 * pg_image_editor_picture_list() reads them) as a file in the folder of the
 * canvas's file, so it is read by whoever may read the canvas, and points
 * its layer at it. A picture over the Workspace's file size, of a blocked
 * name or that cannot be written stays in the document as it came; the
 * document is then not kept and the canvas opens as a flat picture.
 *
 * @param int   $file_id the canvas's file
 * @param mixed $doc     decoded as objects; changed
 * @param mixed $pictures
 * @param int   $user_id
 * @return array layer id => {id, url}
 */
function ws_canvas_pictures_keep($file_id, $doc, $pictures, $user_id)
{
    $list = pg_image_editor_picture_list($pictures);
    $folder = $list ? (int) db_value("SELECT folder FROM files WHERE id = '" . (int) $file_id . "'") : 0;

    // A canvas outside a folder (the schema not updated yet) has no access
    // of its own to share.
    if (!$list || ($folder <= 0) || !is_object($doc)) {
        return array();
    }

    $settings = pg_image_settings();
    $saved = array();

    foreach ($list as $picture) {
        $type = (strncmp($picture['data'], 'data:image/png', 14) === 0) ? 'png' : 'jpg';
        $name = get_unique_name(array('name' => prepare_file_name(ws_canvas_picture_name($picture['name'], $type)), 'type' => 'file'));

        if (pg_upload_name_blocked($name)) {
            continue;
        }

        $path = FILE_DIRECTORY_PATH . '/' . $name;
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (!pg_image_editor_take_picture($picture['data'], $temporary)) {
            continue;
        }

        // The site's upload cap, as an upload has it.
        if (!pg_image_upload_cap_apply($temporary, $settings['upload_max_dimension'], $settings['resize_quality'])) {
            pg_process_image_file($temporary);
        }

        if (((int) @filesize($temporary) > ws_upload_max_bytes()) || file_exists($path) || !@rename($temporary, $path)) {
            @unlink($temporary);
            continue;
        }

        db("INSERT INTO files (name, folder, type, size, user, design, optimized, timestamp)
            VALUES ('" . e($name) . "', '" . $folder . "', '" . e($type) . "', '" . (int) filesize($path) . "',
                '" . (int) $user_id . "', '0', '0', UNIX_TIMESTAMP())");

        $id = (int) mysqli_insert_id(db::$con);

        if ($id <= 0) {
            @unlink($path);
            continue;
        }

        $saved[$picture['id']] = array('id' => $id, 'url' => PATH . encode_url_path($name));
    }

    ws_canvas_pictures_point($doc, $saved);

    return $saved;
}

/**
 * Keeps a canvas's document beside the file just written from it, as the
 * file's newest version (source 'new'), after its pictures from this
 * computer became files beside it (ws_canvas_pictures_keep()). Nothing when
 * the versions table is not installed yet or the document may not be kept.
 *
 * @param int    $file_id
 * @param mixed  $doc      decoded as objects; its picture layers are changed
 * @param int    $user_id
 * @param mixed  $pictures the save's pictures
 * @return array kept (bool), pictures: layer id => {id, url}
 */
function ws_canvas_keep($file_id, $doc, $user_id, $pictures = null)
{
    $saved = ws_canvas_pictures_keep($file_id, $doc, $pictures, $user_id);

    return array(
        'kept'     => pg_image_editor_canvas_keep($file_id, $doc, $user_id, 'new'),
        'pictures' => (object) $saved,
    );
}

/**
 * The canvas document a file was drawn from, while the file still holds
 * those pixels (not changed outside since); null for any other picture.
 *
 * @param int $file_id
 * @return object|null decoded as objects
 */
function ws_canvas_open_doc($file_id)
{
    return pg_image_editor_canvas_open_doc($file_id);
}

/**
 * The texts of the canvas in the channel and the notes screens.
 *
 * @return array key => text
 */
function ws_canvas_js_strings()
{
    return array(
        'canvas_new'     => lang('New canvas'),
        'canvas_send'    => lang('Send the canvas'),
        'canvas_sent'    => lang('The canvas was sent.'),
        'canvas_to_note' => lang('Add to the note'),
        'canvas_edited_by' => ws_js_template('Edited by @{var:1}', 1),
        'canvas_undo'      => lang('Undo'),
        'canvas_undo_ask'  => lang('Put back the canvas as it was before this edit? The edit stays among the versions.'),
        'canvas_undone'    => lang('The canvas is back as it was.'),
    );
}

/**
 * Whether a canvas version's picture is still to be drawn by a member's
 * browser: an agent's version (an agent cannot draw) whose document says
 * picture: pending. A person's save may carry the mark along from the
 * document it opened, but the browser drew that picture. Pure.
 *
 * @param mixed  $doc    decoded as arrays or objects
 * @param string $source the version row's source
 * @return bool
 */
function ws_canvas_doc_pending($doc, $source)
{
    return ws_canvas_doc_mark($doc, $source) === 'pending';
}

/**
 * The mark of an agent's canvas version: pending (its picture is blank
 * until a browser draws it), server (the server drew it,
 * includes/workspace/canvas_draw.php; a browser that shows it draws it
 * again and its picture replaces the server's) or '' (a browser drew it,
 * or the version is a person's). Pure.
 *
 * @param mixed  $doc    decoded as arrays or objects
 * @param string $source the version row's source
 * @return string
 */
function ws_canvas_doc_mark($doc, $source)
{
    if (is_object($doc)) {
        $doc = json_decode(json_encode($doc), true);
    }

    $mark = (is_array($doc) && isset($doc['picture'])) ? $doc['picture'] : '';

    return (((string) $source === 'agent') && in_array($mark, array('pending', 'server'), true)) ? $mark : '';
}

/**
 * The address a canvas's picture is shown under in the conversation: the
 * file's address with its version, so a browser that kept the blank picture
 * (or an older one, or the server's) asks again once the picture is drawn.
 * Pure.
 *
 * @param string     $url     the file's address
 * @param array|null $newest  version_no, pending and server of the newest
 *                            version, or null
 * @return string
 */
function ws_canvas_picture_url($url, $newest)
{
    if (!is_array($newest) || ((int) ($newest['version_no'] ?? 0) <= 0)) {
        return (string) $url;
    }

    $mark = !empty($newest['pending']) ? 'p' : (!empty($newest['server']) ? 's' : '');

    return $url . ((strpos((string) $url, '?') === false) ? '?' : '&') . 'v=' . (int) $newest['version_no'] . $mark;
}

/**
 * The newest version of a message's canvas as the conversation needs it:
 * its number, whether its picture waits for a browser (pending) and
 * whether the server drew it (server: a browser draws it again). One
 * query; null for a picture with no kept document.
 *
 * @param int $file_id
 * @return array|null version_no, pending, server, edited_by
 */
function ws_canvas_message_state($file_id)
{
    if (((int) $file_id <= 0) || !pg_file_versions_ready()) {
        return null;
    }

    // An agent's version carries the agent as the document's last member
    // ("by", api_canvas.php): only that tail is read, never the whole
    // document. Whether an earlier version is there tells an edit from
    // the version the agent made the canvas with.
    $row = db_item("SELECT version_no, source, doc_json <> '' AS has_doc, doc_json LIKE '%\"picture\":\"pending\"%' AS marked,
            doc_json LIKE '%\"picture\":\"server\"%' AS served,
            IF(source = 'agent', SUBSTRING(doc_json, LOCATE('\"by\":{', doc_json), 300), '') AS by_part,
            (SELECT COUNT(*) FROM file_versions AS older WHERE older.file_id = file_versions.file_id AND older.version_no < file_versions.version_no) AS earlier
        FROM file_versions WHERE file_id = '" . (int) $file_id . "' ORDER BY version_no DESC LIMIT 1");

    if (!$row || empty($row['has_doc'])) {
        return null;
    }

    $agent = ((string) $row['source'] === 'agent');

    return array(
        'version_no' => (int) $row['version_no'],
        'pending'    => !empty($row['marked']) && $agent,
        'server'     => empty($row['marked']) && !empty($row['served']) && $agent,
        'edited_by'  => ($agent && ((int) $row['earlier'] > 0)) ? ws_canvas_by_handle((string) $row['by_part']) : '',
    );
}

/**
 * The agent's handle from the tail of a version's document that starts at
 * its "by" member; '' when there is none. Pure.
 *
 * @param string $part
 * @return string
 */
function ws_canvas_by_handle($part)
{
    if ((strpos((string) $part, '"by":{') !== 0) || !preg_match('/^"by":\{[^{}]*"handle":"([A-Za-z0-9_-]{1,64})"/', (string) $part, $match)) {
        return '';
    }

    return $match[1];
}

/**
 * The mark a person's canvas carries in the conversation while its newest
 * version is an agent's edit: the agent's handle, the version it made and
 * whether this reader may put back the version before (Undo: whoever may
 * edit the message, ws_canvas_undo()). Null otherwise. Pure.
 *
 * @param array|null $state       ws_canvas_message_state()
 * @param string     $sender_kind the message's sender kind
 * @param bool       $may_edit    may the reader edit the message
 * @return array|null handle, version, can_undo
 */
function ws_canvas_edit_mark($state, $sender_kind, $may_edit)
{
    if (!is_array($state) || ((string) ($state['edited_by'] ?? '') === '') || ((string) $sender_kind !== 'user')) {
        return null;
    }

    return array(
        'handle'   => (string) $state['edited_by'],
        'version'  => (int) $state['version_no'],
        'can_undo' => (bool) $may_edit,
    );
}

/**
 * Undo of an agent's edit on a person's canvas: the version before the
 * agent's becomes the newest one again, as a new version (source restore)
 * with its document and its pixels, so the agent's edit stays among the
 * versions. Only for whoever may edit the message (its author, while they
 * may write in the channel), only while the newest version is still the
 * agent's edit the screen showed ($version; 409 otherwise), and not when
 * the version before is itself an agent's picture no browser drew yet.
 *
 * @param array $viewer
 * @param array $message ws_messages row
 * @param int   $version the version_no of the agent's edit
 * @return array ok, status (HTTP), error
 */
function ws_canvas_undo($viewer, $message, $version)
{
    $refuse = function ($error, $status) {
        return array('ok' => false, 'status' => $status, 'error' => $error);
    };

    $checked = ws_message_edit_check($viewer, $message, (string) $message['body']);

    if (!$checked['ok'] || ((string) $message['sender_kind'] !== 'user')) {
        return $refuse($checked['ok'] ? lang('You can only change your own messages.') : $checked['error'], 403);
    }

    if (!pg_file_versions_ready()) {
        return $refuse(lang('The database has to be updated first.'), 503);
    }

    $file_id = (int) $message['file_id'];
    $stored = ($file_id > 0) ? (string) db_value("SELECT name FROM files WHERE id = '" . $file_id . "'") : '';
    $newest = ($stored !== '') ? pg_file_versions_newest($file_id) : null;

    if (!$newest || ((string) $newest['source'] !== 'agent') || ((int) $newest['version_no'] !== (int) $version)) {
        return $refuse(lang('The canvas changed since. Look at it again.'), 409);
    }

    $before = db_item("SELECT * FROM file_versions WHERE file_id = '" . $file_id . "' AND version_no < '" . (int) $newest['version_no'] . "'
        ORDER BY version_no DESC LIMIT 1");

    if (!$before || ((string) $before['doc_json'] === '') || !is_file(pg_file_versions_path($before))) {
        return $refuse(lang('There is no version before this edit.'), 409);
    }

    if (ws_canvas_doc_pending(json_decode((string) $before['doc_json'], true), $before['source'])) {
        return $refuse(lang('The version before this edit has no picture yet. Try again in a moment.'), 409);
    }

    $data = @file_get_contents(pg_file_versions_path($before));
    // Written in the file directory, as the picture's take writes it.
    $name = basename($stored);
    $path = FILE_DIRECTORY_PATH . '/' . $name;

    if (!is_string($data) || !pg_image_editor_put($path, $data)
        || !pg_file_versions_add($file_id, $path, (string) $before['doc_json'], 'restore', (int) $viewer['id'], (int) $before['redacted'] === 1)) {
        return $refuse(lang('The file could not be saved.'), 500);
    }

    pg_file_versions_prune($file_id);

    // Every write goes to the file directory, which the general job takes
    // as the file's bytes (pg_file_storage_reconcile_run()).
    db_q("UPDATE files SET size = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", array(strlen($data), $file_id));

    if (function_exists('pg_file_cache_forget')) {
        pg_file_cache_forget($file_id);
    }

    ws_message_touch((int) $message['id']);

    return array('ok' => true, 'status' => 200, 'error' => '');
}

/**
 * The picture a member's browser drew of a pending canvas (action
 * canvas_picture of workspace_file_save.php), taken once: only while the
 * newest version is the one drawn ($version), is an agent's and is still
 * pending or drawn by the server, the file still the picture of that
 * version, and only from a member who may write in the channel. The PNG
 * must be the artboard's size (ws_canvas_picture()). It is written over the
 * message's file in place (same file, same address) and over the version's
 * copy, and the version's document loses the mark, so the canvas opens with
 * its layers as before and the read says drawn. The first browser wins the
 * conditional UPDATE; any other is answered 409.
 *
 * @param array  $viewer
 * @param array  $message ws_messages row
 * @param array  $channel
 * @param int    $version the version_no the browser drew
 * @param mixed  $picture data: URL of the PNG
 * @return array ok, status (HTTP), error, url
 */
function ws_canvas_picture_take($viewer, $message, $channel, $version, $picture)
{
    $refuse = function ($error, $status) {
        return array('ok' => false, 'status' => $status, 'error' => $error, 'url' => '');
    };

    if (!ws_can_post_channel($viewer, $channel)) {
        return $refuse(lang('You cannot post in that channel.'), 403);
    }

    if (!pg_file_versions_ready()) {
        return $refuse(lang('The database has to be updated first.'), 503);
    }

    $file_id = (int) $message['file_id'];
    $stored = (string) db_value("SELECT name FROM files WHERE id = '" . $file_id . "'");
    $name = basename($stored);
    // Written in the file directory; read through the object store's copy
    // when the file was moved there.
    $path = FILE_DIRECTORY_PATH . '/' . $name;
    $newest = ($stored !== '') ? pg_file_versions_newest($file_id) : null;
    $doc = $newest ? json_decode((string) $newest['doc_json'], true) : null;

    if (!$newest || ((int) $newest['version_no'] !== (int) $version) || (ws_canvas_doc_mark($doc, $newest['source']) === '')
        || !pg_file_versions_matches_file($newest, pg_file_local_copy($file_id))) {
        return $refuse(lang('Invalid request.'), 409);
    }

    $png = ws_canvas_picture(array('doc' => $doc, 'picture' => $picture));

    if ($png['error'] !== '') {
        return $refuse($png['error'], 422);
    }

    $object = json_decode((string) $newest['doc_json']);
    unset($object->picture);
    $json = ws_canvas_doc_json($object);

    if ($json === '') {
        return $refuse(lang('Invalid request.'), 422);
    }

    // The claim: only the browser whose UPDATE finds the marked document
    // writes the picture.
    $claimed = db_q("UPDATE file_versions SET doc_json = ?, doc_hash = ?, size = ? WHERE version_id = ? AND doc_hash = ?", array(
        $json, sha1($json), strlen($png['data']), (int) $newest['version_id'], (string) $newest['doc_hash']));

    if (!$claimed || (mysqli_affected_rows(db::$con) !== 1)) {
        return $refuse(lang('Invalid request.'), 409);
    }

    if (!pg_image_editor_put(pg_file_versions_path($newest), $png['data']) || !pg_image_editor_put($path, $png['data'])) {
        // Marked again, so another browser may try; the picture before is
        // put back where it was already written over.
        db_q("UPDATE file_versions SET doc_json = ?, doc_hash = ?, size = ? WHERE version_id = ?", array(
            (string) $newest['doc_json'], (string) $newest['doc_hash'], (int) $newest['size'], (int) $newest['version_id']));

        return $refuse(lang('The file could not be saved.'), 500);
    }

    // Every write goes to the file directory, which the general job takes
    // as the file's bytes (pg_file_storage_reconcile_run()).
    db_q("UPDATE files SET size = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", array(strlen($png['data']), $file_id));

    if (function_exists('pg_file_cache_forget')) {
        pg_file_cache_forget($file_id);
    }

    // The others' conversations fetch the message again with the drawn address.
    ws_message_touch((int) $message['id']);

    return array(
        'ok'     => true,
        'status' => 200,
        'error'  => '',
        'url'    => ws_canvas_picture_url(PATH . encode_url_path($stored), array('version_no' => (int) $newest['version_no'], 'pending' => false)),
    );
}
