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
    );
}
