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
    if (is_object($doc)) {
        $doc = json_decode(json_encode($doc), true);
    }

    if (!is_array($doc) || (($doc['v'] ?? null) !== 1) || !empty($doc['source'])) {
        return false;
    }

    $board = $doc['artboard'] ?? null;

    return is_array($board) && isset($board['width'], $board['height'])
        && is_numeric($board['width']) && is_numeric($board['height'])
        && ($board['width'] >= 1) && ($board['height'] >= 1);
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
    if (!is_object($doc) || !ws_canvas_is_doc($doc)) {
        return '';
    }

    $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return (is_string($json) && pg_file_versions_doc_storable($json)) ? $json : '';
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
 * Keeps a canvas's document beside the file just written from it, as the
 * file's newest version (source 'new'). Nothing when the versions table is
 * not installed yet or the document may not be kept.
 *
 * @param int    $file_id
 * @param mixed  $doc     decoded as objects
 * @param int    $user_id
 * @return bool kept
 */
function ws_canvas_keep($file_id, $doc, $user_id)
{
    $json = ws_canvas_doc_json($doc);
    $stored = ($json !== '') ? (string) db_value("SELECT name FROM files WHERE id = '" . (int) $file_id . "'") : '';

    if (($stored === '') || !pg_file_versions_ready()) {
        return false;
    }

    return (bool) pg_file_versions_add((int) $file_id, FILE_DIRECTORY_PATH . '/' . basename($stored), $json, 'new', $user_id);
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
    if (!pg_file_versions_ready()) {
        return null;
    }

    $stored = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $file_id . "'");
    $newest = ($stored !== '') ? pg_file_versions_newest((int) $file_id) : null;

    if (!$newest || ((string) $newest['doc_json'] === '') || !pg_file_versions_matches_file($newest, FILE_DIRECTORY_PATH . '/' . basename($stored))) {
        return null;
    }

    $doc = json_decode((string) $newest['doc_json']);

    return (is_object($doc) && ws_canvas_is_doc($doc)) ? $doc : null;
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
