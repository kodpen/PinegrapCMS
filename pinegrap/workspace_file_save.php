<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - where the image editor writes a picture edited in a
 * conversation. The editor (assets/js/pg_image_editor.js, save.url) posts
 * what it posts for a save, the document and the overlay, as JSON; the
 * picture is drawn here from the message's file (pg_image_editor_render())
 * and the message and what to do with the picture ride in the address:
 *
 *   workspace_file_save.php?message=<id>&as=replace|send
 *     { token, doc, overlay?, colour? } -> { status, mode, name, url, message_id }
 *
 * 'replace' puts the edited picture in the place of the one in the person's
 * own message and keeps the old one as an earlier version; 'send' posts it as
 * the person's own message in reply, the original staying as it is. The rules
 * are in includes/workspace/file_edits.php.
 *
 * A canvas (includes/workspace/canvas.php) is an artboard the editor draws
 * itself: its body carries the PNG (picture) in place of the overlay, and
 * the document is kept beside the file written, so it opens with its layers
 * again:
 *
 *   workspace_file_save.php?channel=<id>   a new canvas posted in a channel
 *     { token, doc, picture, name? } -> { status, mode: 'new', name, url, message_id }
 *   workspace_file_save.php?note=<id>      a new canvas kept for a note
 *     { token, doc, picture, name? } -> { status, mode: 'new', id, name, url }
 *   workspace_file_save.php?note=<id>&file=<stored name>   a canvas of the
 *     note's own folder: { token, action: 'canvas' } -> { status, doc } as
 *     for a message; a save { token, doc, picture } writes the canvas drawn
 *     again as a new file of the note -> { status, mode: 'replace', id,
 *     name, url }, and the screen links it in the old one's place. The old
 *     file stays in the folder, linked from the note's earlier version.
 *   workspace_file_save.php?message=<id>   with doc.artboard: as above, the
 *     canvas drawn again replacing or answering the message's picture
 *   A canvas's pictures from this computer (pictures [{id, data, name}])
 *   are kept as files in its file's folder, their layers pointed at them,
 *   and answered as pictures (layer id => {id, url}).
 *   workspace_file_save.php?message=<id>   { token, action: 'canvas' }
 *     -> { status, doc }: the canvas document the message's picture was
 *     drawn from, or null
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

header('Content-Type: application/json; charset=utf-8');

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

/**
 * Answers with an error and stops. Every answer is JSON: the editor shows
 * the message in its footer.
 *
 * @param string $message
 * @param int    $status
 */
function ws_file_save_refuse($message, $status = 200)
{
    http_response_code($status);
    echo encode_json(array('status' => 'error', 'message' => $message));
    exit();
}

$body = file_get_contents('php://input');

if (trim((string) $body) === '') {
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        ws_file_save_refuse(lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => (int) floor(ws_upload_max_bytes() / 1048576))), 413);
    }

    ws_file_save_refuse(lang('Invalid request.'));
}

$request = json_decode($body, true);
// The document once more as objects, to be kept as the editor sent it (an
// empty {} stays an object).
$objects = json_decode($body);
$doc_object = (is_object($objects) && isset($objects->doc)) ? $objects->doc : null;
unset($body, $objects);

if (!is_array($request)) {
    ws_file_save_refuse(lang('Invalid request.'));
}

// The JSON body never fills $_POST, so the token is compared here.
$token = (string) ($request['token'] ?? '');

if ((($_SESSION['software']['token'] ?? '') === '') || !hash_equals((string) $_SESSION['software']['token'], $token)) {
    ws_file_save_refuse(lang('Sorry, we could not accept your request because it appears that your session expired or you logged out.'), 403);
}

if (!ws_ready() || !ws_enabled()) {
    ws_file_save_refuse(lang('The workspace is not switched on.'));
}

$viewer = ws_viewer($user);

if (!$viewer['member']) {
    ws_file_save_refuse(lang('Access denied.'), 403);
}

session_write_close();

$canvas = ws_canvas_is_doc($request['doc'] ?? null);

// A new canvas kept for a note: linked in the note by the screen.
if (isset($_GET['note'])) {
    $note = ws_note_for($viewer, (int) $_GET['note'], 'edit');

    if (!$note) {
        ws_file_save_refuse(lang('That note could not be found.'));
    }

    // Behind the person's closed notes lock (includes/workspace/note_lock.php).
    $locked = function_exists('ws_note_lock_gate') ? ws_note_lock_gate($viewer, $note) : '';

    if ($locked !== '') {
        ws_file_save_refuse($locked);
    }

    // A canvas already in the note: only a file of the note's own folder.
    $edited = null;

    if (isset($_GET['file'])) {
        $folder_id = ws_note_folder_id($note, 0, false);
        $edited = ($folder_id > 0)
            ? db_item("SELECT id, name FROM files WHERE name = '" . e((string) $_GET['file']) . "' AND folder = '" . (int) $folder_id . "'")
            : null;

        if (!$edited) {
            ws_file_save_refuse(lang('The file could not be found.'));
        }

        if (($request['action'] ?? '') === 'canvas') {
            echo encode_json(array('status' => 'success', 'doc' => ws_canvas_open_doc((int) $edited['id'])));
            exit();
        }
    }

    $picture = $canvas ? ws_canvas_picture($request) : array('error' => lang('Invalid request.'), 'data' => '');

    if ($picture['error'] !== '') {
        ws_file_save_refuse($picture['error']);
    }

    $result = ws_note_file_store_bytes($viewer, $note, ws_canvas_file_name($request['name'] ?? ''), $picture['data']);

    if (!$result['ok']) {
        ws_file_save_refuse($result['error']);
    }

    $kept = ws_canvas_keep($result['id'], $doc_object, $viewer['id'], $request['pictures'] ?? null);

    echo encode_json(array('status' => 'success', 'mode' => $edited ? 'replace' : 'new', 'id' => (int) $result['id'], 'name' => $result['name'], 'url' => $result['url'], 'pictures' => $kept['pictures']));
    exit();
}

// A new canvas posted in a channel as the person's own message.
if (isset($_GET['channel'])) {
    $channel = ws_channel((int) $_GET['channel']);

    if (!$channel || !ws_can_read_channel($viewer, $channel)) {
        ws_file_save_refuse(lang('Access denied.'), 403);
    }

    $picture = $canvas ? ws_canvas_picture($request) : array('error' => lang('Invalid request.'), 'data' => '');

    if ($picture['error'] !== '') {
        ws_file_save_refuse($picture['error']);
    }

    $result = ws_message_send_file_bytes($viewer, $channel, '', ws_canvas_file_name($request['name'] ?? ''), $picture['data']);

    if (!$result['ok']) {
        ws_file_save_refuse($result['error']);
    }

    $saved = ws_message((int) $result['message_id']);
    $kept = $saved ? ws_canvas_keep((int) $saved['file_id'], $doc_object, $viewer['id'], $request['pictures'] ?? null) : array('pictures' => (object) array());

    $stored = $saved ? (string) db_value("SELECT name FROM files WHERE id = '" . (int) $saved['file_id'] . "'") : '';

    echo encode_json(array(
        'status'     => 'success',
        'mode'       => 'new',
        'name'       => $saved ? (string) $saved['file_name'] : '',
        'url'        => ($stored !== '') ? PATH . encode_url_path($stored) : '',
        'message_id' => (int) $result['message_id'],
        'pictures'   => $kept['pictures'],
    ));
    exit();
}

$target = ws_file_edit_target($viewer, (int) ($_GET['message'] ?? 0), 'image');

if (!is_array($target)) {
    ws_file_save_refuse($target);
}

list($message, $channel) = $target;

// Before the editor opens a picture of the conversation: the canvas it was
// drawn from, if it was one.
if (($request['action'] ?? '') === 'canvas') {
    echo encode_json(array('status' => 'success', 'doc' => ws_canvas_open_doc((int) $message['file_id'])));
    exit();
}

if ($canvas) {
    // The canvas drawn again: its PNG is the picture.
    $picture = ws_canvas_picture($request);
    $picture['extension'] = 'png';
} else {
    $stored_name = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $message['file_id'] . "'");
    // The editor reads the picture it was opened on, which may be a copy of
    // a file in the object store; the result is posted as a new file.
    $picture = pg_image_editor_render(pg_file_reader_path($stored_name), $request);
}

// The pictures from this computer, kept as files once the canvas is written.
$pictures = $request['pictures'] ?? null;
unset($request);

if ($picture['error'] !== '') {
    ws_file_save_refuse($picture['error']);
}

$extension = $picture['extension'];
// A canvas has no redaction to burn in.
$redacted = !empty($picture['redacted']);
$mime = array('jpg' => 'jpeg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif');
$data = 'data:image/' . $mime[$extension] . ';base64,' . base64_encode($picture['data']);
unset($picture);

$name = ws_file_edit_name($message['file_name'], $extension);
$as = (($_GET['as'] ?? '') === 'replace') ? 'replace' : 'send';

if ($as === 'replace') {
    $result = ws_message_file_replace($viewer, $message, $channel, $name, $data, $redacted);
    $message_id = (int) $message['id'];
} else {
    $result = ws_message_file_send($viewer, $message, $channel, $name, $data);
    $message_id = (int) ($result['message_id'] ?? 0);
}

if (!$result['ok']) {
    ws_file_save_refuse($result['error']);
}

$saved = ws_message($message_id);
$stored = $saved ? (string) db_value("SELECT name FROM files WHERE id = '" . (int) $saved['file_id'] . "'") : '';

$kept = ($canvas && $saved) ? ws_canvas_keep((int) $saved['file_id'], $doc_object, $viewer['id'], $pictures) : array('pictures' => (object) array());

echo encode_json(array(
    'status'     => 'success',
    'mode'       => $as,
    'name'       => $saved ? (string) $saved['file_name'] : $name,
    'url'        => ($stored !== '') ? PATH . encode_url_path($stored) : '',
    'message_id' => $message_id,
    'pictures'   => $kept['pictures'],
));
